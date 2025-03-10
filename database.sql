DROP TABLE IF EXISTS trades;
DROP TABLE IF EXISTS users;

CREATE DATABASE trade_one;

USE trade_one;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    token VARCHAR(32), -- Add token column
    is_verified TINYINT(1) DEFAULT 0, -- Add is_verified column
    is_locked TINYINT(1) DEFAULT 0, -- Add is_locked column
    locked_at TIMESTAMP NULL DEFAULT NULL, -- Add locked_at column
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE trades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    trade_date DATE NOT NULL,
    symbol VARCHAR(10) NOT NULL,
    quantity INT NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    exit_date DATE,
    exit_price DECIMAL(10, 2),
    stop_loss DECIMAL(10, 2),
    take_profit DECIMAL(10, 2),
    profit_loss DECIMAL(10, 2),
    strategy VARCHAR(255),
    comment TEXT,
    trade_direction ENUM('short', 'long') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

ALTER TABLE users
ADD COLUMN token VARCHAR(32), -- Add token column
ADD COLUMN is_verified TINYINT(1) DEFAULT 0, -- Add is_verified column
ADD COLUMN is_locked TINYINT(1) DEFAULT 0, -- Add is_locked column
ADD COLUMN locked_at TIMESTAMP NULL DEFAULT NULL; -- Add locked_at column
