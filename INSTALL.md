# Installation — ch.ipik.swissQRinvoice

## 1. Copier l'extension

Placer le dossier `ch.ipik.swissQRinvoice` dans le répertoire d'extensions CiviCRM :
```
/path/to/civicrm/ext/ch.ipik.swissQRinvoice/
```

## 2. Installer la librairie QR suisse

La génération du QR code requiert la librairie PHP `sprain/swiss-qr-bill`.

```bash
cd /path/to/civicrm/ext/ch.ipik.swissQRinvoice
composer install --no-dev
```

> Si `composer` n'est pas disponible sur le serveur :
> `curl -sS https://getcomposer.org/installer | php && php composer.phar install --no-dev`

## 3. Activer l'extension dans CiviCRM

**Administrer > Système > Extensions** → rechercher "Swiss QR Invoice" → Installer

## 4. Configurer l'extension

**Facturation > Paramètres** dans le menu CiviCRM :
- Sélectionner le contact organisation expéditeur
- Saisir l'IBAN (format CH : `CH88 0076 7000 S560 6787 8`)
- Uploader logo et signature (chemins absolus sur le serveur)
- Personnaliser le format de numérotation (ex. `{YEAR}-{SEQ:4}`)
- Configurer le template d'email

## 5. Logo et signature

Uploader les fichiers sur le serveur (ex. via l'interface Infomaniak ou SFTP), puis noter le chemin absolu à saisir dans les paramètres.

Exemple : `/home/clients/xxx/files/kerma-logo.png`

## Notes techniques

- **TCPDF** : déjà inclus dans CiviCRM, pas d'installation supplémentaire
- **sprain/swiss-qr-bill** : génère les données QR conformes au Swiss Payment Standard (ISO 20022)
- Le QR est intégré directement en bas de page 1 (modèle Invoice_0091)
- Ligne de découpe conforme au standard bancaire suisse
- Les permissions sont gérées via les rôles CiviCRM

## Structure des tables

- `civicrm_swissqr_invoice` : une ligne par facture
- `civicrm_swissqr_invoice_line` : lignes d'articles (n lignes par facture)

## Multilingue (v2)

La structure i18n est préparée (domaine `ch.ipik.swissQRinvoice`).
Pour ajouter une langue : créer `l10n/de_DE/LC_MESSAGES/ch.ipik.swissQRinvoice.po`

## Mass generation (v2)

La table `civicrm_swissqr_invoice` supporte déjà le batch.
L'UI de génération en masse sera ajoutée dans une version ultérieure.
