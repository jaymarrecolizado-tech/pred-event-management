-- Guest status: present (signed in), absent (admin-marked). No row = in vicinity.
ALTER TABLE attendance
  ADD COLUMN status ENUM('present','absent') NOT NULL DEFAULT 'present' AFTER event_id;

ALTER TABLE attendance
  MODIFY signature_path VARCHAR(255) NULL;
