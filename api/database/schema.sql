-- ============================================================
-- Accident Detection System — Database Schema
-- Run this in phpMyAdmin or Laragon's HeidiSQL
-- ============================================================

CREATE DATABASE IF NOT EXISTS accident_detection
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE accident_detection;

-- ── Drivers ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS drivers (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name      VARCHAR(100) NOT NULL,
    phone          VARCHAR(20)  NOT NULL,
    email          VARCHAR(100),
    license_number VARCHAR(50)  NOT NULL UNIQUE,
    address        TEXT,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── Vehicles ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS vehicles (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(20)  NOT NULL UNIQUE,
    make         VARCHAR(50),               -- e.g. Toyota
    model        VARCHAR(50),               -- e.g. Corolla
    year         YEAR,
    color        VARCHAR(30),
    driver_id    INT UNSIGNED,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL
);

-- ── Devices ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS devices (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_code VARCHAR(50) NOT NULL UNIQUE,  -- e.g. DEV-001
    vehicle_id  INT UNSIGNED,
    status      ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    notes       TEXT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL
);

-- ── Accidents ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS accidents (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id        INT UNSIGNED NOT NULL,
    severity         ENUM('urgent', 'medium', 'normal') NOT NULL,
    latitude         DECIMAL(10, 7),
    longitude        DECIMAL(10, 7),
    accel_x          FLOAT,
    accel_y          FLOAT,
    accel_z          FLOAT,
    gyro_x           FLOAT,
    gyro_y           FLOAT,
    gyro_z           FLOAT,
    image_path       VARCHAR(255),
    browser_alerted  TINYINT(1) DEFAULT 0,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
);

-- ── Users (dashboard login) ──────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    is_active     TINYINT(1)   DEFAULT 1,
    last_login    DATETIME     DEFAULT NULL,
    created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP
);

-- Default admin account: username=admin password=admin123
-- Change the password after first login!
INSERT INTO users (username, password_hash, full_name) VALUES
('admin', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator');


INSERT INTO drivers (full_name, phone, email, license_number) VALUES
('Jean Pierre', '+250788000001', 'jean@example.com', 'LIC-001'),
('Marie Claire', '+250788000002', 'marie@example.com', 'LIC-002');

INSERT INTO vehicles (plate_number, make, model, year, color, driver_id) VALUES
('RAB 001 A', 'Toyota', 'Corolla', 2020, 'White', 1),
('RAB 002 B', 'Honda',  'Civic',   2019, 'Black', 2);

INSERT INTO devices (device_code, vehicle_id, status) VALUES
('DEV-001', 1, 'active'),
('DEV-002', 2, 'active');
