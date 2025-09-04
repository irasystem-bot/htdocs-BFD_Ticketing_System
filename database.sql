-- database.sql
-- Create database and tickets table for Repair Ticketing System

CREATE DATABASE IF NOT EXISTS bfd_ticketing_system CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE bfd_ticketing_system;

CREATE TABLE IF NOT EXISTS tickets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  department VARCHAR(100) NOT NULL,
  description TEXT,
  priority ENUM('Low','Medium','High') DEFAULT 'Medium',
  status ENUM('Open','In Progress','Closed') DEFAULT 'Open',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  end_at DATETIME NULL,
  github_url VARCHAR(512) NULL,
  attachment VARCHAR(255) NULL,
  INDEX (created_at),
  INDEX (status),
  INDEX (department)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
