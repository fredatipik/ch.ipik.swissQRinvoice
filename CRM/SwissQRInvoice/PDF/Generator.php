<?php
class CRM_SwissQRInvoice_PDF_Generator {
  private array $invoice;
  private array $settings;
  private array $orgContact;
  const MARGIN_L=20; const MARGIN_R=15; const MARGIN_T=15; const PAGE_W=210; const CONTENT_W=175;

  public function __construct(array $invoice) {
    $this->invoice=$invoice;
    $this->settings=CRM_SwissQRInvoice_Utils::getSettings();
    $this->orgContact=CRM_SwissQRInvoice_Utils::getOrgContact((int)($this->settings['swissqr_org_contact_id']??0));
  }

  public function generate(): string {
    $autoload=__DIR__.'/../../../vendor/autoload.php';
    if(file_exists($autoload)) require_once $autoload;
    foreach([__DIR__.'/../../../vendor/tecnickcom/tcpdf/tcpdf.php',dirname(__DIR__,5).'/vendor/tecnickcom/tcpdf/tcpdf.php'] as $p){if(file_exists($p)){require_once $p;break;}}
    $pdf=new TCPDF('P','mm','A4',true,'UTF-8',false);
    $pdf->SetCreator('CiviCRM Swiss QR Invoice');
    $pdf->SetAuthor($this->orgContact['display_name']??'');
    $pdf->SetTitle('Facture '.$this->invoice['invoice_number']);
    $pdf->setPrintHeader(false);$pdf->setPrintFooter(false);
    $pdf->SetMargins(self::MARGIN_L,self::MARGIN_T,self::MARGIN_R);
    $pdf->SetAutoPageBreak(false);
    $pdf->AddPage();
    $pdf->SetFont('helvetica','',9);
    $this->renderHeader($pdf);
    $this->renderRecipient($pdf);
    $this->renderInvoiceMeta($pdf);
    $this->renderLines($pdf);
    $this->renderSummary($pdf);
    $this->renderFooterNote($pdf);
    // Option single_page désactivée : TcPdfOutput ne supporte pas d'offsetY arbitraire
    // dans la version sprain/swiss-qr-bill embarquée. Le QR slip est toujours en page 2.
    $this->renderQRSlip($pdf);
    return $pdf->Output('','S');
  }

  private function renderHeader(TCPDF $pdf): void {
    $org=$this->orgContact;
    $logoPath=$this->settings['swissqr_logo_path']??'';
    $offsetX=(float)($this->settings['swissqr_logo_offset_x']??0);
    if($logoPath&&file_exists($logoPath)){
      $pdf->Image($logoPath,self::MARGIN_L+$offsetX,self::MARGIN_T,70,22,'','','T',false,300,'',false,false,0,true);
    }
    $marginBottom = (float)($this->settings['swissqr_logo_margin_bottom'] ?? 0);
    $pdf->SetY(self::MARGIN_T+24+$marginBottom);
    $pdf->SetFont('helvetica','',8.5);
    $pdf->SetTextColor(60,60,60);
    foreach(array_filter([$org['street_address']??'',trim(($org['postal_code']??'').' '.($org['city']??'')),$org['phone']??'',$org['email']??'']) as $l){
      $pdf->SetX(self::MARGIN_L);$pdf->Cell(90,4.5,$l,0,1,'L');
    }
    $pdf->SetTextColor(0,0,0);
  }

  private function renderRecipient(TCPDF $pdf): void {
    try{
      $c=civicrm_api3('Contact','getsingle',['id'=>$this->invoice['contact_id'],'return'=>'display_name,street_address,city,postal_code']);
    }catch(Exception $e){$c=['display_name'=>$this->invoice['contact_name']??''];}
    $y=55;
    $pdf->SetFont('helvetica','',8.5);
    foreach(array_filter([$c['display_name']??'',$c['street_address']??'',trim(($c['postal_code']??'').' '.($c['city']??''))]) as $l){
      $pdf->SetXY(120,$y);$pdf->Cell(75,4.5,$l,0,0,'L');$y+=4.5;
    }
    $pdf->SetY(max($y+5,72));
  }

  private function renderInvoiceMeta(TCPDF $pdf): void {
    $date=date('d.m.Y',strtotime($this->invoice['invoice_date']));
    $city=$this->orgContact['city']??'';
    $due=!empty($this->invoice['due_date'])?date('d.m.Y',strtotime($this->invoice['due_date'])):'';
    $pdf->SetFont('helvetica','',9);
    $pdf->SetXY(120,$pdf->GetY());$pdf->Cell(75,5,($city?"{$city}, le ":'').$date,0,1,'L');
    $pdf->Ln(12);
    $pdf->SetFont('helvetica','B',16);$pdf->SetX(self::MARGIN_L);$pdf->Cell(50,9,'Facture',0,0,'L');
    $pdf->SetFont('helvetica','',12);$pdf->Cell(0,9,$this->invoice['invoice_number'],0,1,'L');
    $pdf->SetFont('helvetica','',8.5);$pdf->SetX(self::MARGIN_L);$pdf->Cell(0,4.5,$date,0,1,'L');
    if($due){$pdf->SetTextColor(100,100,100);$pdf->SetX(self::MARGIN_L);$pdf->Cell(0,4,"Échéance : {$due}",0,1,'L');$pdf->SetTextColor(0,0,0);}
    $pdf->Ln(5);
  }

  private function renderLines(TCPDF $pdf): void {
    $cw=[45,82,22,12,14];$align=['L','L','R','R','R'];
    $pdf->SetFont('helvetica','B',8);$pdf->SetFillColor(235,235,235);
    foreach(['Article','Description','Coût unit.','Qté','Total HT'] as $k=>$h) $pdf->Cell($cw[$k],6,$h,'B',0,$align[$k],true);
    $pdf->Ln();$pdf->SetFillColor(255,255,255);
    $pdf->SetFont('helvetica','',8.5);
    foreach($this->invoice['lines'] as $i=>$line){
      $bg=($i%2===1);if($bg)$pdf->SetFillColor(248,248,248);
      $pdf->Cell($cw[0],5.5,$line['article'],0,0,'L',$bg);
      $pdf->Cell($cw[1],5.5,$line['description'],0,0,'L',$bg);
      $pdf->Cell($cw[2],5.5,'CHF '.number_format((float)$line['unit_price'],2,'.',chr(39)),0,0,'R',$bg);
      $pdf->Cell($cw[3],5.5,(int)$line['quantity'],0,0,'R',$bg);
      $pdf->Cell($cw[4],5.5,number_format((float)$line['line_total'],2,'.',chr(39)),0,1,'R',$bg);
      if($bg)$pdf->SetFillColor(255,255,255);
    }
    $pdf->SetDrawColor(180,180,180);$pdf->SetLineWidth(0.3);
    $pdf->Line(self::MARGIN_L,$pdf->GetY(),self::PAGE_W-self::MARGIN_R,$pdf->GetY());
    $pdf->SetDrawColor(0,0,0);$pdf->Ln(2);
  }

  private function renderSummary(TCPDF $pdf): void {
    $lX=145;$vW=35;$lH=5.5;
    if(($this->invoice['discount']??0)>0){
      $pdf->SetFont('helvetica','',8.5);$pdf->SetXY($lX,$pdf->GetY()+2);
      $pdf->Cell($vW,$lH,'Sous-total',0,0,'L');$pdf->Cell(15,$lH,number_format((float)$this->invoice['subtotal'],2,'.',chr(39)),0,1,'R');
      $pdf->SetTextColor(100,100,100);$pdf->SetX($lX);
      $pdf->Cell($vW,$lH,'Rabais',0,0,'L');$pdf->Cell(15,$lH,'- '.number_format((float)$this->invoice['discount'],2,'.',chr(39)),0,1,'R');
      $pdf->SetTextColor(0,0,0);
    }
    foreach([['Total',number_format((float)$this->invoice['total'],2,'.',chr(39)),true],['Payé à ce jour',number_format((float)$this->invoice['amount_paid'],2,'.',chr(39)),false],['Solde dû',number_format((float)$this->invoice['amount_due'],2,'.',chr(39)),true]] as[$label,$value,$bold]){
      $pdf->SetFont('helvetica',$bold?'B':'',9);$pdf->SetX($lX);
      $pdf->Cell($vW,$lH,$label,0,0,'L');$pdf->Cell(15,$lH,'CHF '.$value,0,1,'R');
    }
    $pdf->Ln(8);
  }

  private function renderFooterNote(TCPDF $pdf): void {
    $vat=trim($this->settings['swissqr_vat_note']??'');
    if($vat){$pdf->SetFont('helvetica','I',8);$pdf->SetTextColor(80,80,80);$pdf->SetX(self::MARGIN_L);$pdf->Cell(0,4.5,$vat,0,1,'L');$pdf->SetTextColor(0,0,0);$pdf->Ln(5);}
    $pdf->SetFont('helvetica','',9);$pdf->SetX(self::MARGIN_L);$pdf->Cell(0,5,'Avec nos meilleures salutations,',0,1,'L');$pdf->Ln(8);
    $sig=$this->settings['swissqr_signature_path']??'';
    $sigOffsetY=(float)($this->settings['swissqr_signature_offset_y']??0);
    if($sig&&file_exists($sig)){
      $sigW=35;
      $info=@getimagesize($sig);
      $sigH=($info&&$info[1]>0)?round($sigW*$info[1]/$info[0],1):15;
      $sigY=$pdf->GetY()+$sigOffsetY;
      $pdf->Image($sig,self::MARGIN_L,$sigY,$sigW,0,'','','T',false,300);
      // Avancer le curseur sous l'image (prendre le max entre position courante et bas de l'image)
      $pdf->SetY(max($pdf->GetY(), $sigY+$sigH)+3);
    }
    $sn=$this->settings['swissqr_signatory_name']??'';
    if($sn){$pdf->SetFont('helvetica','',9);$pdf->SetX(self::MARGIN_L);$pdf->Cell(0,5,$sn,0,1,'L');}
  }

  private function renderQRSlip(TCPDF $pdf): void {
    try{$this->_buildQRSlip($pdf);}
    catch(\Throwable $e){CRM_Core_Error::debug_log_message('SwissQRInvoice PDF error: '.$e->getMessage());}
  }

  private function _buildQRSlip(TCPDF $pdf, bool $inline = false): void {
    $iban=(string)($this->settings['swissqr_iban']??'');
    $amount=(float)$this->invoice['amount_due'];
    $ref=$this->invoice['reference']??'';
    $org=$this->orgContact;
    try{$d=civicrm_api3('Contact','getsingle',['id'=>$this->invoice['contact_id'],'return'=>'display_name,street_address,city,postal_code']);}
    catch(Exception $e){$d=['display_name'=>$this->invoice['contact_name']??''];}
    $qr=\Sprain\SwissQrBill\QrBill::create();
    $qr->setCreditor(\Sprain\SwissQrBill\DataGroup\Element\CombinedAddress::create($org['display_name']??'',$org['street_address']??'',($org['postal_code']??'').' '.($org['city']??''),'CH'));
    $qr->setCreditorInformation(\Sprain\SwissQrBill\DataGroup\Element\CreditorInformation::create($iban));
    $qr->setPaymentAmountInformation($amount>0?\Sprain\SwissQrBill\DataGroup\Element\PaymentAmountInformation::create('CHF',$amount):\Sprain\SwissQrBill\DataGroup\Element\PaymentAmountInformation::create('CHF'));
    if(!empty($d['street_address']))$qr->setUltimateDebtor(\Sprain\SwissQrBill\DataGroup\Element\CombinedAddress::create($d['display_name']??'',$d['street_address']??'',($d['postal_code']??'').' '.($d['city']??''),'CH'));
    $qr->setPaymentReference(\Sprain\SwissQrBill\DataGroup\Element\PaymentReference::create(\Sprain\SwissQrBill\DataGroup\Element\PaymentReference::TYPE_NON,null,$ref?:null));
    if ($inline) {
      // Mode une page : le QR slip occupe les 105 mm du bas de la page courante.
      // TcPdfOutput s'attend à démarrer à Y=0 de la page ; on utilise offsetY=148
      // (A4=297mm, slip=105mm depuis le bas → y_start=192mm).
      // Certaines versions de sprain/swiss-qr-bill acceptent un $offsetY en 4e arg.
      $slipY = 297 - 105; // 192mm
      // Ligne de séparation pointillée
      $pdf->SetDrawColor(150,150,150);
      $pdf->SetLineStyle(['width'=>0.3,'dash'=>'2,2']);
      $pdf->Line(self::MARGIN_L, $slipY, self::PAGE_W - self::MARGIN_R, $slipY);
      $pdf->SetLineStyle(['width'=>0.2,'dash'=>'']);
      $pdf->SetDrawColor(0,0,0);
      (new \Sprain\SwissQrBill\PaymentPart\Output\TcPdfOutput\TcPdfOutput($qr,'fr',$pdf, $slipY, 0))->getPaymentPart();
    } else {
      $pdf->AddPage();
      (new \Sprain\SwissQrBill\PaymentPart\Output\TcPdfOutput\TcPdfOutput($qr,'fr',$pdf,0,0))->getPaymentPart();
    }
  }

  // ── QR slip inline (bas de page 1, mode single_page) ─────────────────────
  private function renderQRSlipInline(TCPDF $pdf): void {
    try { $this->_buildQRSlip($pdf, true); }
    catch (\Throwable $e) {
      CRM_Core_Error::debug_log_message('SwissQRInvoice PDF inline error: ' . $e->getMessage());
    }
  }
}
