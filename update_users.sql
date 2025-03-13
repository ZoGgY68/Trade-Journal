-- Add columns if they don't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_locked BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN IF NOT EXISTS locked_at DATETIME NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login DATETIME NULL;

-- Update admin user
UPDATE users SET 
    is_admin = TRUE,
    is_locked = FALSE,
    is_verified = TRUE
WHERE username = 'admin';

-- Set default values for existing users
UPDATE users SET 
    is_admin = FALSE,
    is_locked = FALSE
WHERE username != 'admin';
