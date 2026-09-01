<?php
return [
  'swissqr_org_contact_id'        => ['group'=>'domain','name'=>'swissqr_org_contact_id','type'=>'Integer','default'=>null,'add'=>'1.0'],
  'swissqr_iban'                   => ['group'=>'domain','name'=>'swissqr_iban','type'=>'String','default'=>'','add'=>'1.0'],
  'swissqr_logo_path'              => ['group'=>'domain','name'=>'swissqr_logo_path','type'=>'String','default'=>'','add'=>'1.0'],
  'swissqr_signature_path'         => ['group'=>'domain','name'=>'swissqr_signature_path','type'=>'String','default'=>'','add'=>'1.0'],
  'swissqr_signatory_name'         => ['group'=>'domain','name'=>'swissqr_signatory_name','type'=>'String','default'=>'','add'=>'1.0'],
  'swissqr_vat_note'               => ['group'=>'domain','name'=>'swissqr_vat_note','type'=>'String','default'=>'','add'=>'1.0'],
  'swissqr_invoice_number_format'  => ['group'=>'domain','name'=>'swissqr_invoice_number_format','type'=>'String','default'=>'{YEAR}-{SEQ:4}','add'=>'1.0'],
  'swissqr_invoice_seq'            => ['group'=>'domain','name'=>'swissqr_invoice_seq','type'=>'Integer','default'=>0,'add'=>'1.0'],
  'swissqr_qr_reference_template'  => ['group'=>'domain','name'=>'swissqr_qr_reference_template','type'=>'String','default'=>'Facture N° {NUMBER}','add'=>'1.0'],
  'swissqr_email_subject_template' => ['group'=>'domain','name'=>'swissqr_email_subject_template','type'=>'String','default'=>'Facture N° {invoice_number}','add'=>'1.0'],
  'swissqr_email_body_template'    => ['group'=>'domain','name'=>'swissqr_email_body_template','type'=>'String','default'=>'','add'=>'1.0'],
  'swissqr_allowed_roles'          => ['group'=>'domain','name'=>'swissqr_allowed_roles','type'=>'Array','default'=>[],'add'=>'1.0'],
  'swissqr_default_lang'           => ['group'=>'domain','name'=>'swissqr_default_lang','type'=>'String','default'=>'fr','add'=>'1.0'],
  'swissqr_single_page_pdf'        => ['group'=>'domain','name'=>'swissqr_single_page_pdf','type'=>'Boolean','default'=>false,'add'=>'1.0'],
  'swissqr_signature_offset_y'     => ['group'=>'domain','name'=>'swissqr_signature_offset_y','type'=>'Float','default'=>0,'add'=>'1.0'],
];
