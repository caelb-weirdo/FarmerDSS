# Farmers DSS - Project Documentation

Welcome to the official documentation for the **Farmers Decision Support System (Farmers DSS)**. This document explains every detail of the system, including how each page works, the underlying logic, and the user roles.

---

## Table of Contents
1. [System Overview](#system-overview)
2. [User Roles](#user-roles)
3. [Page Guide](#page-guide)
   - [Login & Registration](#1-login--registration-auth)
   - [Dashboard](#2-dashboard)
   - [How It Works](#3-how-it-works)
   - [Crop Advisory Engine](#4-crop-advisory-engine)
   - [Fertilizer Calculator](#5-fertilizer-calculator)
   - [Market Watch](#6-market-watch)
   - [Admin Panel](#7-admin-panel)
4. [Technical Details](#technical-details)

---

## System Overview
Farmers DSS is a web-based platform built to help farmers in Sri Lanka make data-driven decisions. By combining real-time weather, crop science, and market intelligence, the system advises farmers on what to grow, when to grow it, and how much it will cost.

The project uses a traditional server-side PHP architecture with MySQL, meaning all heavy lifting (logic, scoring, querying) is done on the server before the page loads.

---

## User Roles
There are two types of users in the system:
- **Farmer**: The standard user. Has access to the Dashboard, Crop Advisory, Fertilizer Calculator, Market Watch, and How It Works pages.
- **Admin**: The system manager. Has access to all Farmer features, plus a dedicated **Admin Panel** tab where they can update market prices and add new crops to the system.

---

## Page Guide

### 1. Login & Registration (Auth)
**Files**: `login.php`, `register.php`

![Login Page](./ss/login.png)
*(Note: Please add actual screenshots to the `ss/` folder)*

- **How it works**: Users must create an account to access the system. Registration requires an email and password. Passwords are securely hashed using PHP's `password_hash()` (bcrypt).
- **Session Management**: Upon successful login, a PHP session is started, storing the `user_id` and `role`. If a user tries to access `index.php` without an active session, they are redirected to `login.php`.

### 2. Dashboard
**File**: `index.php` (Tab: Dashboard)

![Dashboard](./ss/dashboard.png)

- **How it works**: The central hub of the application. 
- **Key Elements**:
  - **Welcome Banner**: Displays a greeting with the current date.
  - **Live Weather**: Uses the Open-Meteo API to fetch real-time weather data for key agricultural districts (Trincomalee, Anuradhapura, Jaffna, Kandy). It displays temperature, rain probability, and a computed risk level.
  - **Daily DSS Advice**: A dynamically generated tip or alert based on the current weather or market conditions.
  - **Metric Cards**: Quick summaries of total crops tracked, active market alerts, and total users.

### 3. How It Works
**File**: `index.php` (Tab: How It Works)

![How It Works](./ss/how-it-works.png)

- **How it works**: An informational page explaining the value proposition of Farmers DSS. It uses a clean, Apple-style design with a dark background and mint-green accents.
- **Key Elements**:
  - Step-by-step timeline explaining the data gathering and recommendation process.
  - Feature cards detailing core benefits (Weather Sync, Cost Planning, Market Trends).

### 4. Crop Advisory Engine
**File**: `index.php` (Tab: Crop Advisory)

![Crop Advisory Engine](./ss/crop-advisory.png)

- **How it works**: The core decision-making tool. Farmers input their specific conditions, and the system recommends the best crops to grow.
- **Inputs**: Soil Type, Season (Maha/Yala), Water Source, Budget, Market Demand.
- **Logic**: When the user clicks "Generate Recommendations", the form submits via POST to `index.php`. The PHP backend scores every crop in the database against the user's inputs.
  - Soil match: +15 pts
  - Season match: +15 pts
  - Water match: +15 pts
  - Budget match: +8 pts
  - Demand match: +7 pts
- **Output**: The top 3 crops with the highest scores (capped at 100) are displayed, along with a button to view a step-by-step growth guide.

### 5. Fertilizer Calculator
**File**: `index.php` (Tab: Fertilizer)

![Fertilizer Calculator](./ss/fertilizer-calculator.png)

- **How it works**: A client-side tool (powered by `script.js`) to estimate fertilizer costs.
- **Logic**: The user selects a crop and inputs their land size (in acres). JavaScript instantly multiplies the land size by the required fertilizer kg/acre and current price/kg stored in the `window.fertilizerRules` global variable (which PHP populates on page load).
- **Output**: Total required kilograms and estimated cost in LKR, formatted nicely.

### 6. Market Watch
**File**: `index.php` (Tab: Market)

![Market Watch](./ss/market-watch.png)

- **How it works**: Displays the current market status of all tracked crops.
- **Logic**: PHP queries the `market_prices` table and renders an HTML table.
- **Key Elements**:
  - Displays Price per kg, Demand Level (High/Medium/Low), and Price Trend (Up/Down/Stable).
  - Visual badges make it easy to spot lucrative opportunities or price drops.

### 7. Admin Panel
**File**: `index.php` (Tab: Admin Panel) - *Only visible to Admin role*

![Admin Panel](./ss/admin-panel.png)

- **How it works**: Allows administrators to manage system data without touching the raw database.
- **Features**:
  - **Update Market Prices**: Admins can select a crop and update its current price, demand level, and trend. This immediately reflects on the Market Watch page.
  - **Add New Crop**: A comprehensive form to add a new crop to the advisory engine. Admins can set its ideal soil, season, water needs, and budget. This dynamically expands the capabilities of the Crop Advisory Engine.

---

## Technical Details
- **Frontend**: HTML5, Vanilla CSS (Apple-style dark theme with glassmorphism), Vanilla JavaScript.
- **Backend**: PHP 7+ (Procedural).
- **Database**: MySQL.
- **Security**: 
  - Passwords hashed with `bcrypt`.
  - Prepared statements (PDO) used for all database queries to prevent SQL injection.
  - Input validation and whitelisting (e.g., trend and demand values) to prevent XSS.
