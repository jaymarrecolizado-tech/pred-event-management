CREATE TABLE IF NOT EXISTS participants (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL UNIQUE,
  timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  email VARCHAR(191) NULL,
  first_name VARCHAR(100) NOT NULL,
  middle_name VARCHAR(100) NULL,
  last_name VARCHAR(100) NOT NULL,
  nickname VARCHAR(100) NULL,
  sex ENUM('Male','Female','Other') NULL,
  sector VARCHAR(100) NULL,
  agency VARCHAR(255) NULL,
  designation VARCHAR(150) NULL,
  office_email VARCHAR(191) NULL,
  contact_no VARCHAR(50) NULL,
  qr_path VARCHAR(255) NULL,
  created_by INT NULL,
  INDEX idx_name_agency (first_name(50), last_name(50), agency(100)),
  UNIQUE KEY uq_email (email)
);

CREATE TABLE IF NOT EXISTS attendance (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  participant_id BIGINT UNSIGNED NOT NULL,
  attendance_date DATE NOT NULL,
  time_in TIME NOT NULL,
  signature_path VARCHAR(255) NOT NULL,
  event_id INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_attendance_participant FOREIGN KEY (participant_id)
    REFERENCES participants(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  email VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admin_username (username)
);

CREATE TABLE IF NOT EXISTS import_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT,
  file_name VARCHAR(255),
  action ENUM('preview','execute','cancel') NOT NULL,
  duplicate_strategy ENUM('skip','override_duplicates','override_all') NULL,
  summary TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);