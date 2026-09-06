#!/bin/bash
# deploy.sh — Swiss QR Invoice deployment script
# Usage: bash deploy.sh /path/to/ext/directory
# Example: bash deploy.sh ~/sites/apocal.ipik.ch/lab/wp-content/uploads/civicrm/ext

set -e

EXT_NAME="ch.ipik.swissQRinvoice"
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
EXT_DIR="${1:-$(dirname "$SCRIPT_DIR")}"
TARGET="${EXT_DIR}/${EXT_NAME}"

echo "=== Swiss QR Invoice — Déploiement ==="
echo "Source : ${SCRIPT_DIR}"
echo "Cible  : ${TARGET}"

# Copier les fichiers (sans vendor, sans deploy.sh lui-même)
rsync -av --exclude='vendor/' --exclude='deploy.sh' --exclude='.git' \
  "${SCRIPT_DIR}/" "${TARGET}/"

# Installer/mettre à jour les dépendances composer
echo ""
echo "=== Installation des dépendances composer ==="
cd "${TARGET}"
composer install --no-dev --no-interaction --quiet
echo "Vendor OK."

echo ""
echo "=== Déploiement terminé ==="
echo "N'oubliez pas : ~/bin/cv updb"
