-- Make sure the columns exist
ALTER TABLE users 
    ADD COLUMN IF NOT EXISTS is_locked BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS is_admin BOOLEAN DEFAULT FALSE;

-- Update all existing users
UPDATE users SET 
    is_locked = FALSE 
WHERE is_locked IS NULL;

-- Update admin account
UPDATE users SET 
    is_admin = TRUE,
    is_locked = FALSE,
    is_verified = TRUE 
WHERE username = 'admin';

-- Check the results
SELECT username, is_locked, is_admin FROM users;
