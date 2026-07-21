ALTER TABLE admins
  ADD COLUMN role ENUM('admin','checker','seo_viewer') NOT NULL DEFAULT 'admin' AFTER email,
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER role,
  ADD COLUMN last_login_at DATETIME NULL AFTER is_active,
  ADD COLUMN display_name VARCHAR(120) NULL AFTER username;

UPDATE admins SET role = 'admin' WHERE role = '' OR role IS NULL;
