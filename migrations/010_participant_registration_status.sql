-- Participant registration source status (online vs CSV import)
ALTER TABLE participants
  ADD COLUMN registration_status VARCHAR(20) NOT NULL DEFAULT 'Registered' AFTER contact_no,
  ADD INDEX idx_participants_reg_status (registration_status);
