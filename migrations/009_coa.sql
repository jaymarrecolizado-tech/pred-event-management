CREATE TABLE IF NOT EXISTS coa_signatories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(191) NOT NULL,
  title VARCHAR(255) NOT NULL,
  signature_path VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS coa_send_batches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id INT NULL,
  date_from DATE NULL,
  date_to DATE NULL,
  venue VARCHAR(255) NOT NULL,
  purpose TEXT NOT NULL,
  inclusive_dates VARCHAR(255) NOT NULL,
  issue_date DATE NOT NULL,
  default_particulars JSON NOT NULL,
  signatory_id INT UNSIGNED NOT NULL,
  admin_id INT NULL,
  sent_count INT NOT NULL DEFAULT 0,
  failed_count INT NOT NULL DEFAULT 0,
  skipped_count INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_coa_batches_created (created_at),
  INDEX idx_coa_batches_signatory (signatory_id)
);

CREATE TABLE IF NOT EXISTS coa_send_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id BIGINT UNSIGNED NOT NULL,
  participant_id BIGINT UNSIGNED NOT NULL,
  attendance_summary VARCHAR(255) NULL,
  particulars JSON NOT NULL,
  pdf_path VARCHAR(255) NULL,
  email_to VARCHAR(191) NULL,
  status ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
  error TEXT NULL,
  sent_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_coa_items_batch (batch_id),
  INDEX idx_coa_items_participant (participant_id)
);
