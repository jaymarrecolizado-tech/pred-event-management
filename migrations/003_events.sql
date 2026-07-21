CREATE TABLE IF NOT EXISTS events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  enforce_single_time_in TINYINT(1) DEFAULT 1,
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE attendance ADD INDEX idx_attendance_pid_date (participant_id, attendance_date);