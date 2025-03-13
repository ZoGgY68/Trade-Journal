-- Add is_admin column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS is_admin BOOLEAN DEFAULT FALSE;

-- Update existing admin user if exists
UPDATE users SET is_admin = TRUE WHERE username = 'admin';

-- Add last_login column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login DATETIME NULL;

-- Add locked_at column if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS locked_at DATETIME NULL;
