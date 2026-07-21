ALTER TABLE participants
  ADD COLUMN is_vip TINYINT(1) NOT NULL DEFAULT 0 AFTER designation,
  ADD INDEX idx_participants_vip (is_vip);
