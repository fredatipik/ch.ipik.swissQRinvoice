CREATE TABLE IF NOT EXISTS `civicrm_swissqr_invoice` (
  `id`                        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_number`            VARCHAR(64)  NOT NULL,
  `contact_id`                INT UNSIGNED NOT NULL,
  `organization_contact_id`   INT UNSIGNED NOT NULL,
  `invoice_date`              DATE         NOT NULL,
  `due_date`                  DATE         DEFAULT NULL,
  `status`                    ENUM('draft','sent','paid','cancelled') NOT NULL DEFAULT 'draft',
  `amount_paid`               DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `notes`                     TEXT         DEFAULT NULL,
  `reference`                 VARCHAR(140) DEFAULT NULL,
  `discount_type`             ENUM('none','amount','percent') NOT NULL DEFAULT 'none',
  `discount_value`            DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `contribution_id`           INT UNSIGNED DEFAULT NULL,
  `payment_contribution_id`   INT UNSIGNED DEFAULT NULL,
  `paid_date`                 DATE         DEFAULT NULL,
  `sent_date`                 DATETIME     DEFAULT NULL,
  `sent_to_email`             VARCHAR(255) DEFAULT NULL,
  `created_by`                INT UNSIGNED DEFAULT NULL,
  `created_at`                DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`                DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_contact_id` (`contact_id`),
  INDEX `idx_org_contact_id` (`organization_contact_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_invoice_number` (`invoice_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `civicrm_swissqr_invoice_line` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `invoice_id`  INT UNSIGNED NOT NULL,
  `article`     VARCHAR(255) NOT NULL DEFAULT '',
  `description` TEXT         DEFAULT NULL,
  `unit_price`  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `quantity`    DECIMAL(10,3) NOT NULL DEFAULT 1.000,
  `line_total`  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `idx_invoice_id` (`invoice_id`),
  CONSTRAINT `fk_swissqr_line_invoice`
    FOREIGN KEY (`invoice_id`) REFERENCES `civicrm_swissqr_invoice`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `civicrm_swissqr_service` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(255) NOT NULL,
  `description` TEXT         DEFAULT NULL,
  `unit_price`  DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order`  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO civicrm_financial_type (name, description, is_deductible, is_reserved, is_active) VALUES ('Facture QR', 'Paiements de factures Swiss QR', 0, 0, 1);
