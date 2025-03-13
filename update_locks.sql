-- Update all existing users to have is_locked set to 0
UPDATE users SET 
    is_locked = 0,
    locked_at = NULL
WHERE is_locked IS NULL;

-- Set admin user's flags
UPDATE users SET 
    is_locked = 0,
    is_admin = 1,
    is_verified = 1
WHERE username = 'admin';

-- Make sure all other users are not admins
UPDATE users SET 
    is_admin = 0
WHERE username != 'admin';
