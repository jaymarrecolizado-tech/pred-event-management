CREATE TABLE IF NOT EXISTS rate_limits (
  rate_key VARCHAR(191) NOT NULL PRIMARY KEY,
  count INT UNSIGNED NOT NULL DEFAULT 0,
  window_start INT UNSIGNED NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

ALTER TABLE participants ADD INDEX idx_participants_agency (agency(100));

ALTER TABLE attendance ADD INDEX idx_attendance_date (attendance_date);
