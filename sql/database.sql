-- Farmers DSS – full schema (includes IoT soil tables)
-- Import via phpMyAdmin into database `farmer_dss`

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS farmer_dss CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE farmer_dss;

DROP TABLE IF EXISTS soil_readings;
DROP TABLE IF EXISTS crop_soil_requirements;
DROP TABLE IF EXISTS fields;
DROP TABLE IF EXISTS fertilizer_rules;
DROP TABLE IF EXISTS crop_guides;
DROP TABLE IF EXISTS market_prices;
DROP TABLE IF EXISTS crops;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  full_name VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('Admin', 'Farmer') NOT NULL DEFAULT 'Farmer',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE crops (
  id INT AUTO_INCREMENT PRIMARY KEY,
  crop_name VARCHAR(100) NOT NULL,
  best_soils VARCHAR(255) NOT NULL,
  best_seasons VARCHAR(100) NOT NULL,
  best_water VARCHAR(100) NOT NULL,
  budget_level ENUM('Low', 'Medium', 'High') NOT NULL DEFAULT 'Medium',
  market_demand ENUM('High', 'Medium', 'Low') NOT NULL DEFAULT 'Medium',
  duration VARCHAR(50) NOT NULL,
  profit_score INT NOT NULL DEFAULT 70
);

CREATE TABLE crop_guides (
  id INT AUTO_INCREMENT PRIMARY KEY,
  crop_id INT NOT NULL,
  guide_text TEXT NOT NULL,
  FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE CASCADE
);

CREATE TABLE fertilizer_rules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  crop_id INT NOT NULL,
  fertilizer_type VARCHAR(50) NOT NULL,
  kg_per_acre FLOAT NOT NULL,
  price_per_kg FLOAT NOT NULL,
  schedule_text VARCHAR(255) NOT NULL,
  FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE CASCADE
);

CREATE TABLE market_prices (
  id INT AUTO_INCREMENT PRIMARY KEY,
  crop_name VARCHAR(100) NOT NULL,
  price_per_kg FLOAT NOT NULL,
  trend ENUM('up', 'down', 'stable') NOT NULL DEFAULT 'stable',
  demand ENUM('High', 'Medium', 'Low') NOT NULL DEFAULT 'Medium'
);

CREATE TABLE fields (
  id INT AUTO_INCREMENT PRIMARY KEY,
  field_name VARCHAR(100) NOT NULL,
  district VARCHAR(100) NOT NULL,
  soil_type VARCHAR(50) NOT NULL,
  owner_user_id INT NULL,
  FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE soil_readings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  field_id INT NOT NULL,
  moisture FLOAT NULL,
  ph FLOAT NULL,
  ec FLOAT NULL,
  temperature FLOAT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (field_id) REFERENCES fields(id) ON DELETE CASCADE
);

CREATE TABLE crop_soil_requirements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  crop_id INT NOT NULL UNIQUE,
  min_ph FLOAT NULL,
  max_ph FLOAT NULL,
  min_moisture FLOAT NULL,
  max_moisture FLOAT NULL,
  max_ec FLOAT NULL,
  FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE CASCADE
);

SET FOREIGN_KEY_CHECKS = 1;

-- Demo users: admin@123 (Admin) / password123 (Farmer)
INSERT INTO users (email, full_name, password_hash, role) VALUES
('admin@gmail.com', 'System Admin', '$2y$10$csv2QCtBQKpGX8QVSA24XOvEdfU5mE2gBJcDPj9dvgfyRxYCdtNTW', 'Admin'),
('farmer@farmer-dss.com', 'Demo Farmer', '$2y$10$1TlxV39mMVJv2UKbYcEewekjy4Ka.uBvmngBRvfd9wX3V2.00EEpy', 'Farmer');

INSERT INTO crops (crop_name, best_soils, best_seasons, best_water, budget_level, market_demand, duration, profit_score) VALUES
('Paddy', 'Alluvial,Clay', 'Yala,Maha', 'Irrigation,Rainfed', 'Medium', 'High', '110 - 130 days', 88),
('Maize', 'Alluvial,Sandy', 'Yala,Dry', 'Irrigation,Rainfed', 'Medium', 'High', '90 - 110 days', 82),
('Onion', 'Sandy,Alluvial', 'Yala,Dry', 'Irrigation,Groundwater', 'High', 'High', '80 - 100 days', 85),
('Chili', 'Sandy,Laterite', 'Yala,Dry', 'Irrigation', 'High', 'Medium', '120 - 150 days', 78),
('Groundnut', 'Sandy,Laterite', 'Yala,Maha', 'Rainfed', 'Low', 'Medium', '100 - 120 days', 75),
('Green Gram', 'Alluvial,Sandy', 'Yala,Maha', 'Rainfed', 'Low', 'Medium', '60 - 70 days', 72);

INSERT INTO crop_guides (crop_id, guide_text) VALUES
(1, 'Prepare land with fine tilth and maintain 2-3 cm standing water during early growth.'),
(1, 'Apply basal fertilizer before transplanting; split nitrogen doses at tillering and panicle initiation.'),
(1, 'Monitor for blast and stem borer; harvest when 80% grains turn golden yellow.'),
(2, 'Plant at 75 cm x 25 cm spacing after ensuring good soil moisture.'),
(2, 'Apply compost at land preparation and top-dress with urea at knee-high stage.'),
(2, 'Harvest when husks turn dry and kernels are hard.'),
(3, 'Use raised beds with good drainage; transplant 6-week-old seedlings.'),
(3, 'Apply phosphorus at planting and nitrogen in split doses.'),
(3, 'Harvest when 70% tops fall and necks soften.'),
(4, 'Raise seedlings in nurseries; transplant during evening hours.'),
(4, 'Stake plants and apply potassium during flowering.'),
(4, 'Pick red ripe fruits in 3-4 harvests.'),
(5, 'Sow on ridges after pre-monsoon rains; ensure well-drained sandy loam.'),
(5, 'Inoculate seeds with rhizobium where available.'),
(5, 'Harvest when leaves yellow and pods mature.'),
(6, 'Sow after good rainfall; avoid waterlogging.'),
(6, 'Light irrigation at flowering improves pod set.'),
(6, 'Harvest when pods turn black and dry.');

INSERT INTO fertilizer_rules (crop_id, fertilizer_type, kg_per_acre, price_per_kg, schedule_text) VALUES
(1, 'Urea', 120, 320, 'Basal + tillering + panicle initiation'),
(1, 'MOP', 50, 360, 'Basal application'),
(1, 'TSP', 60, 420, 'Basal application'),
(1, 'Compost', 2000, 35, 'During land preparation'),
(2, 'Urea', 100, 320, 'Basal + knee-high stage'),
(2, 'MOP', 40, 360, 'Basal application'),
(2, 'TSP', 50, 420, 'Basal application'),
(2, 'Compost', 1500, 35, 'During land preparation'),
(3, 'Urea', 80, 320, 'Split doses at 30 and 45 days'),
(3, 'MOP', 60, 360, 'Basal application'),
(3, 'TSP', 70, 420, 'Basal application'),
(3, 'Compost', 1800, 35, 'Before transplanting'),
(4, 'Urea', 60, 320, 'At flowering'),
(4, 'MOP', 50, 360, 'Basal + flowering'),
(4, 'TSP', 40, 420, 'Basal application'),
(4, 'Compost', 1200, 35, 'Before transplanting'),
(5, 'Urea', 20, 320, 'Optional top-dress at flowering'),
(5, 'MOP', 30, 360, 'Basal application'),
(5, 'TSP', 40, 420, 'Basal application'),
(5, 'Compost', 1000, 35, 'During land preparation'),
(6, 'Urea', 15, 320, 'Light dose at flowering'),
(6, 'MOP', 20, 360, 'Basal application'),
(6, 'TSP', 25, 420, 'Basal application'),
(6, 'Compost', 800, 35, 'During land preparation');

INSERT INTO market_prices (crop_name, price_per_kg, trend, demand) VALUES
('Paddy', 95, 'stable', 'High'),
('Maize', 110, 'up', 'High'),
('Onion', 280, 'up', 'High'),
('Chili', 450, 'down', 'Medium'),
('Groundnut', 320, 'stable', 'Medium'),
('Green Gram', 240, 'up', 'Medium');

INSERT INTO crop_soil_requirements (crop_id, min_ph, max_ph, min_moisture, max_moisture, max_ec) VALUES
(1, 5.5, 7.0, 60, 90, 2.5),
(2, 5.8, 7.5, 40, 70, 2.0),
(3, 6.0, 7.0, 35, 65, 1.8),
(4, 5.5, 6.8, 30, 60, 1.5),
(5, 5.5, 7.0, 25, 55, 1.2),
(6, 6.0, 7.5, 30, 60, 1.5);

INSERT INTO fields (field_name, district, soil_type, owner_user_id) VALUES
('North Paddy Plot', 'Trincomalee', 'Alluvial', 2),
('East Maize Block', 'Trincomalee', 'Sandy', 2),
('Demo Vegetable Patch', 'Anuradhapura', 'Sandy', NULL);

INSERT INTO soil_readings (field_id, moisture, ph, ec, temperature, created_at) VALUES
(1, 72.5, 6.4, 1.1, 28.2, NOW() - INTERVAL 2 HOUR),
(2, 45.0, 6.8, 0.9, 29.0, NOW() - INTERVAL 1 HOUR),
(3, 38.0, 6.2, 1.4, 27.5, NOW() - INTERVAL 30 MINUTE);
