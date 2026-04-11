-- @migrate up
-- @transaction off
CREATE INDEX idx_users_name ON users (name);

-- @migrate down
-- @transaction off
DROP INDEX idx_users_name;
