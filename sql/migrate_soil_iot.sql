-- Run this on an EXISTING farmer_dss database to add IoT soil tables only.
-- Safe to run once; will fail if tables already exist.

USE farmer_dss;

CREATE TABLE IF NOT EXISTS fields (
  id INT AUTO_INCREMENT PRIMARY KEY,
  field_name VARCHAR(100) NOT NULL,
  district VARCHAR(100) NOT NULL,
  soil_type VARCHAR(50) NOT NULL,
  owner_user_id INT NULL,
  FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS soil_readings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  field_id INT NOT NULL,
  moisture FLOAT NULL,
  ph FLOAT NULL,
  ec FLOAT NULL,
  temperature FLOAT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (field_id) REFERENCES fields(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS crop_soil_requirements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  crop_id INT NOT NULL UNIQUE,
  min_ph FLOAT NULL,
  max_ph FLOAT NULL,
  min_moisture FLOAT NULL,
  max_moisture FLOAT NULL,
  max_ec FLOAT NULL,
  FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE CASCADE
);
