<?php
require_once __DIR__ . '/includes/db.php';
requireLogin();

// --- Action Handlers ---
$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_price') {
        requireAdmin();
        $id     = (int)$_POST['id'];
        $price  = (float)$_POST['price'];
        $trend  = in_array($_POST['trend'],  ['up','down','stable'])         ? $_POST['trend']  : 'stable';
        $demand = in_array($_POST['demand'], ['High','Medium','Low'])         ? $_POST['demand'] : 'Medium';
        
        $stmt = $pdo->prepare('UPDATE market_prices SET price_per_kg = ?, trend = ?, demand = ? WHERE id = ?');
        $stmt->execute([$price, $trend, $demand, $id]);
        $message = "Price updated successfully.";
    } elseif ($_POST['action'] === 'add_crop') {
        requireAdmin();
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare('INSERT INTO crops (crop_name, best_soils, best_seasons, best_water, budget_level, market_demand, duration, profit_score) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                trim($_POST['crop_name']),
                implode(',', $_POST['best_soils'] ?? []),
                implode(',', $_POST['best_seasons'] ?? []),
                implode(',', $_POST['best_water'] ?? []),
                $_POST['budget_level'],
                $_POST['market_demand'],
                trim($_POST['duration']),
                (int)$_POST['profit_score']
            ]);
            
            $cropId = (int) $pdo->lastInsertId();

            $marketStmt = $pdo->prepare('INSERT INTO market_prices (crop_name, price_per_kg, trend, demand) VALUES (?, ?, ?, ?)');
            $marketStmt->execute([
                trim($_POST['crop_name']),
                (float)$_POST['price'],
                $_POST['trend'],
                $_POST['market_demand']
            ]);

            $guideSteps = array_filter(array_map('trim', explode("\n", $_POST['guide'] ?? '')));
            $guideStmt = $pdo->prepare('INSERT INTO crop_guides (crop_id, guide_text) VALUES (?, ?)');
            foreach ($guideSteps as $step) {
                if (!empty($step)) {
                    $guideStmt->execute([$cropId, $step]);
                }
            }

            $fertStmt = $pdo->prepare('INSERT INTO fertilizer_rules (crop_id, fertilizer_type, kg_per_acre, price_per_kg, schedule_text) VALUES (?, ?, ?, ?, ?)');
            foreach (['Urea', 'MOP', 'TSP', 'Compost'] as $ftype) {
                $kg = (float)($_POST["fertilizer_" . strtolower($ftype) . "_kg"] ?? 0);
                if ($kg > 0) {
                    $fertStmt->execute([
                        $cropId,
                        $ftype,
                        $kg,
                        (float)($_POST["fertilizer_" . strtolower($ftype) . "_price"] ?? 0),
                        trim($_POST["fertilizer_" . strtolower($ftype) . "_schedule"] ?? '')
                    ]);
                }
            }

            $minPh = ($_POST['min_ph'] ?? '') !== '' ? (float)$_POST['min_ph'] : null;
            $maxPh = ($_POST['max_ph'] ?? '') !== '' ? (float)$_POST['max_ph'] : null;
            $minMoisture = ($_POST['min_moisture'] ?? '') !== '' ? (float)$_POST['min_moisture'] : null;
            $maxMoisture = ($_POST['max_moisture'] ?? '') !== '' ? (float)$_POST['max_moisture'] : null;
            $maxEc = ($_POST['max_ec'] ?? '') !== '' ? (float)$_POST['max_ec'] : null;
            if ($minPh !== null || $maxPh !== null || $minMoisture !== null || $maxMoisture !== null || $maxEc !== null) {
                $soilReqStmt = $pdo->prepare('INSERT INTO crop_soil_requirements (crop_id, min_ph, max_ph, min_moisture, max_moisture, max_ec) VALUES (?, ?, ?, ?, ?, ?)');
                $soilReqStmt->execute([$cropId, $minPh, $maxPh, $minMoisture, $maxMoisture, $maxEc]);
            }

            $pdo->commit();
            $message = "Crop added successfully.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error adding crop: " . $e->getMessage();
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'add_field') {
        requireAdmin();
        $stmt = $pdo->prepare('INSERT INTO fields (field_name, district, soil_type, owner_user_id) VALUES (?, ?, ?, ?)');
        $ownerId = !empty($_POST['owner_user_id']) ? (int)$_POST['owner_user_id'] : null;
        $stmt->execute([
            trim($_POST['field_name']),
            trim($_POST['district']),
            $_POST['soil_type'],
            $ownerId
        ]);
        $message = "Field added successfully.";
    } elseif ($_POST['action'] === 'add_soil_reading') {
        requireAdmin();
        $fieldId = (int)$_POST['field_id'];
        $moisture = ($_POST['moisture'] ?? '') !== '' ? (float)$_POST['moisture'] : null;
        $ph = ($_POST['ph'] ?? '') !== '' ? (float)$_POST['ph'] : null;
        $ec = ($_POST['ec'] ?? '') !== '' ? (float)$_POST['ec'] : null;
        $temp = ($_POST['temp'] ?? '') !== '' ? (float)$_POST['temp'] : null;
        if ($fieldId <= 0 || $moisture === null || $ph === null) {
            $message = "Field, moisture, and pH are required for soil readings.";
            $messageType = 'error';
        } else {
            $stmt = $pdo->prepare('INSERT INTO soil_readings (field_id, moisture, ph, ec, temperature, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$fieldId, $moisture, $ph, $ec, $temp]);
            $message = "Soil reading saved.";
        }
    } elseif ($_POST['action'] === 'update_soil_requirements') {
        requireAdmin();
        $cropId = (int)$_POST['crop_id'];
        $minPh = ($_POST['min_ph'] ?? '') !== '' ? (float)$_POST['min_ph'] : null;
        $maxPh = ($_POST['max_ph'] ?? '') !== '' ? (float)$_POST['max_ph'] : null;
        $minMoisture = ($_POST['min_moisture'] ?? '') !== '' ? (float)$_POST['min_moisture'] : null;
        $maxMoisture = ($_POST['max_moisture'] ?? '') !== '' ? (float)$_POST['max_moisture'] : null;
        $maxEc = ($_POST['max_ec'] ?? '') !== '' ? (float)$_POST['max_ec'] : null;
        $exists = $pdo->prepare('SELECT id FROM crop_soil_requirements WHERE crop_id = ?');
        $exists->execute([$cropId]);
        if ($exists->fetch()) {
            $stmt = $pdo->prepare('UPDATE crop_soil_requirements SET min_ph = ?, max_ph = ?, min_moisture = ?, max_moisture = ?, max_ec = ? WHERE crop_id = ?');
            $stmt->execute([$minPh, $maxPh, $minMoisture, $maxMoisture, $maxEc, $cropId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO crop_soil_requirements (crop_id, min_ph, max_ph, min_moisture, max_moisture, max_ec) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$cropId, $minPh, $maxPh, $minMoisture, $maxMoisture, $maxEc]);
        }
        $message = "Soil requirements updated.";
    }
}

// --- Data Loading ---
function csv_to_array($value) { return array_map('trim', explode(',', $value)); }

$cropRows = $pdo->query('SELECT * FROM crops ORDER BY id')->fetchAll();
$guideRows = $pdo->query('SELECT crop_id, guide_text FROM crop_guides ORDER BY id')->fetchAll();
$fertilizerRows = $pdo->query('SELECT c.crop_name, f.fertilizer_type, f.kg_per_acre, f.price_per_kg, f.schedule_text FROM fertilizer_rules f JOIN crops c ON c.id = f.crop_id ORDER BY c.id, f.id')->fetchAll();
$marketRows = $pdo->query('SELECT * FROM market_prices ORDER BY id')->fetchAll();

$fieldRows = [];
$soilReqRows = [];
$latestSoilByField = [];
$fieldCount = 0;
try {
    if (isAdmin()) {
        $fieldRows = $pdo->query('SELECT f.*, u.full_name AS owner_name FROM fields f LEFT JOIN users u ON u.id = f.owner_user_id ORDER BY f.id')->fetchAll();
    } else {
        $fieldStmt = $pdo->prepare('SELECT f.*, u.full_name AS owner_name FROM fields f LEFT JOIN users u ON u.id = f.owner_user_id WHERE f.owner_user_id = ? OR f.owner_user_id IS NULL ORDER BY f.id');
        $fieldStmt->execute([$_SESSION['user_id']]);
        $fieldRows = $fieldStmt->fetchAll();
    }
    $fieldCount = count($fieldRows);
    $soilReqRows = $pdo->query('SELECT * FROM crop_soil_requirements')->fetchAll();
    $readingRows = $pdo->query('
        SELECT sr.* FROM soil_readings sr
        INNER JOIN (
            SELECT field_id, MAX(id) AS max_id FROM soil_readings GROUP BY field_id
        ) latest ON sr.id = latest.max_id
    ')->fetchAll();
    foreach ($readingRows as $reading) {
        $latestSoilByField[(int)$reading['field_id']] = $reading;
    }
} catch (PDOException $e) {
    // Tables may not exist until migration is run
}

$requirementsByCropId = [];
foreach ($soilReqRows as $req) {
    $requirementsByCropId[(int)$req['crop_id']] = $req;
}

$recentReadings = [];
try {
    $recentReadings = $pdo->query('
        SELECT sr.*, f.field_name FROM soil_readings sr
        JOIN fields f ON f.id = sr.field_id
        ORDER BY sr.created_at DESC LIMIT 10
    ')->fetchAll();
} catch (PDOException $e) {
    // ignore
}

$allUsers = [];
if (isAdmin()) {
    try {
        $allUsers = $pdo->query('SELECT id, full_name, email FROM users WHERE role = \'Farmer\' ORDER BY full_name')->fetchAll();
    } catch (PDOException $e) {
        // ignore
    }
}

$guidesByCropId = [];
foreach ($guideRows as $guide) {
    $guidesByCropId[(int) $guide['crop_id']][] = $guide['guide_text'];
}

$fertilizerRules = [];
foreach ($fertilizerRows as $row) {
    $cropName = $row['crop_name'];
    $fertilizerRules[$cropName][] = [
        'type' => $row['fertilizer_type'],
        'kgPerAcre' => (float) $row['kg_per_acre'],
        'pricePerKg' => (float) $row['price_per_kg'],
        'schedule' => $row['schedule_text'],
    ];
}

$crops = [];
foreach ($cropRows as $crop) {
    $cropId = (int) $crop['id'];
    $crops[] = [
        'id' => $cropId,
        'crop' => $crop['crop_name'],
        'bestSoils' => csv_to_array($crop['best_soils']),
        'bestSeasons' => csv_to_array($crop['best_seasons']),
        'bestWater' => csv_to_array($crop['best_water']),
        'budget' => $crop['budget_level'],
        'demand' => $crop['market_demand'],
        'duration' => $crop['duration'],
        'profit' => (int) $crop['profit_score'],
        'guide' => $guidesByCropId[$cropId] ?? [],
    ];
}

// --- Recommendation Logic ---
$recommendedCrops = [];
$showRecommendations = false;
$postDistrict = $_POST['district'] ?? 'Trincomalee';
$postSoil = $_POST['soil_type'] ?? '';
$postSeason = $_POST['season_type'] ?? '';
$postWater = $_POST['water_source'] ?? '';
$postBudget = $_POST['budget_level'] ?? '';
$postDemand = $_POST['market_demand'] ?? '';
$postFieldId = isset($_POST['field_id']) && $_POST['field_id'] !== '' ? (int)$_POST['field_id'] : 0;

$soilReading = null;
$selectedField = null;
if ($postFieldId > 0) {
    foreach ($fieldRows as $field) {
        if ((int)$field['id'] === $postFieldId) {
            $selectedField = $field;
            break;
        }
    }
    $soilReading = $latestSoilByField[$postFieldId] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'recommend') {
    $showRecommendations = true;
    if ($postFieldId > 0 && !$soilReading) {
        $stmt = $pdo->prepare('SELECT * FROM soil_readings WHERE field_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$postFieldId]);
        $soilReading = $stmt->fetch() ?: null;
    }

    foreach ($crops as $rule) {
        $score = 40;
        $reasons = [];
        $cropId = $rule['id'];
        
        if (in_array($postSoil, $rule['bestSoils'])) { $score += 15; $reasons[] = $rule['crop'] . " suits " . strtolower($postSoil) . " soil."; }
        if (in_array($postSeason, $rule['bestSeasons'])) { $score += 15; $reasons[] = $postSeason . " season is suitable."; }
        if (in_array($postWater, $rule['bestWater'])) { $score += 15; $reasons[] = strtolower($postWater) . " water source supports " . $rule['crop'] . "."; }
        if ($rule['budget'] === $postBudget) { $score += 8; $reasons[] = strtolower($postBudget) . " budget matches."; }
        if ($rule['demand'] === $postDemand) { $score += 7; $reasons[] = "Market demand is " . strtolower($postDemand) . "."; }

        if ($soilReading && isset($requirementsByCropId[$cropId])) {
            $req = $requirementsByCropId[$cropId];

            if ($req['min_ph'] !== null && $req['max_ph'] !== null && $soilReading['ph'] !== null) {
                if ($soilReading['ph'] >= $req['min_ph'] && $soilReading['ph'] <= $req['max_ph']) {
                    $score += 10;
                    $reasons[] = "Soil pH (" . $soilReading['ph'] . ") is within the ideal range for " . $rule['crop'] . ".";
                } else {
                    $score -= 10;
                    $reasons[] = "Soil pH (" . $soilReading['ph'] . ") is outside the ideal range for " . $rule['crop'] . ".";
                }
            }

            if ($req['min_moisture'] !== null && $req['max_moisture'] !== null && $soilReading['moisture'] !== null) {
                if ($soilReading['moisture'] >= $req['min_moisture'] && $soilReading['moisture'] <= $req['max_moisture']) {
                    $score += 8;
                    $reasons[] = "Soil moisture (" . $soilReading['moisture'] . "%) suits " . $rule['crop'] . ".";
                } else {
                    $score -= 8;
                    $reasons[] = "Soil moisture (" . $soilReading['moisture'] . "%) is not ideal for " . $rule['crop'] . ".";
                }
            }

            if ($req['max_ec'] !== null && $soilReading['ec'] !== null) {
                if ($soilReading['ec'] <= $req['max_ec']) {
                    $score += 5;
                    $reasons[] = "Soil salinity (EC " . $soilReading['ec'] . ") is acceptable.";
                } else {
                    $score -= 5;
                    $reasons[] = "Soil salinity (EC " . $soilReading['ec'] . ") is too high for " . $rule['crop'] . ".";
                }
            }
        }
        
        if ($score > 100) $score = 100;
        if ($score < 0) $score = 0;
        
        $rec = $rule;
        $rec['score'] = $score;
        $rec['reasons'] = $reasons;
        $recommendedCrops[] = $rec;
    }
    
    usort($recommendedCrops, function($a, $b) { return $b['score'] - $a['score']; });
} else {
    // Default fallback
    foreach ($crops as $rule) {
        $rec = $rule;
        $rec['score'] = 92;
        $rec['reasons'] = ["Select your inputs and click generate to see custom reasons."];
        $recommendedCrops[] = $rec;
    }
}
$topCrop = $recommendedCrops[0] ?? null;

// --- Market Profit Ranking ---
$rankedMarkets = [];
foreach ($crops as $c) {
    $match = null;
    foreach ($marketRows as $m) {
        if (strpos($m['crop_name'], $c['crop']) !== false || strpos($c['crop'], $m['crop_name']) !== false) {
            $match = $m; break;
        }
    }
    $boost = ($match && $match['trend'] === 'up') ? 8 : 0;
    $c['finalScore'] = $c['profit'] + $boost;
    $rankedMarkets[] = $c;
}
usort($rankedMarkets, function($a, $b) { return $b['finalScore'] - $a['finalScore']; });
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Farmers DSS</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png" />
    <link rel="apple-touch-icon" href="assets/images/logo.png" />
    <link rel="stylesheet" href="assets/css/styles.css?v=20230624" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        // Pass essential data to minimal JS
        var fertilizerRules = <?php echo json_encode($fertilizerRules); ?>;
        var cropGuides = <?php echo json_encode($crops); ?>;
        var START_PAGE = <?php echo json_encode((isset($_POST['action']) && $_POST['action'] === 'recommend') ? 'crop-advisory' : ((isset($_POST['action']) && in_array($_POST['action'], ['update_price', 'add_crop', 'add_field', 'add_soil_reading', 'update_soil_requirements'], true)) ? 'admin-panel' : 'dashboard')); ?>;
        var PHP_MESSAGE = <?php echo json_encode($message); ?>;
        var fieldOptions = <?php echo json_encode(array_map(function($f) {
            return ['id' => (int)$f['id'], 'name' => $f['field_name'], 'district' => $f['district'], 'soil_type' => $f['soil_type']];
        }, $fieldRows)); ?>;
    </script>
  </head>
  <body>
    <div class="app-shell">
      <header class="topbar">
        <a class="brand" href="#dashboard" aria-label="Farmers DSS home" onclick="showPage('dashboard'); return false;">
          <img src="assets/images/logo.png" alt="Farmers DSS Logo" class="brand-mark brand-logo" />
          <span>
            <span class="brand-label">Farmers DSS</span>
            <span class="brand-subtitle">Smart decisions for every harvest</span>
          </span>
        </a>

        <nav class="topnav" aria-label="Primary navigation">
          <a href="#dashboard" onclick="showPage('dashboard'); return false;" class="active">Dashboard</a>
          <a href="#crop-advisory" onclick="showPage('crop-advisory'); return false;">Crop Advisor</a>
          <a href="#calculator" onclick="showPage('calculator'); return false;">Calculator</a>
          <a href="#market-watch" onclick="showPage('market-watch'); return false;">Market</a>
          <a href="#how-it-works" onclick="showPage('how-it-works'); return false;">How It Works</a>
          <?php if (isAdmin()): ?>
          <a href="#admin-panel" onclick="showPage('admin-panel'); return false;">Admin Panel</a>
          <?php endif; ?>
        </nav>

        <div class="topbar-actions">
          <button class="icon-btn" id="warning-button" aria-label="View alerts">
            !
            <span class="badge" id="alert-count">0</span>
          </button>
          <div class="profile" id="profile-section">
            <div class="avatar" id="profile-avatar"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></div>
            <div class="profile-meta">
              <p id="profile-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></p>
              <span id="profile-role"><?php echo htmlspecialchars($_SESSION['role']); ?></span>
            </div>
          </div>
          <a href="logout.php" class="outline-button" style="text-decoration:none;">Logout</a>
        </div>
      </header>

      <main class="pages">
        <section id="dashboard" class="page active">
          <!-- ── HERO BANNER ─────────────────────────── -->
          <div class="dashboard-hero">
            <div class="dashboard-hero-content">
              <p class="eyebrow">Sustainable Farming</p>
              <h2>Grow the Future with<br>Sustainable Agriculture</h2>
              <p>Smart crop decisions, real-time weather & market insights — right at your fingertips.</p>
              <button class="hero-cta-btn" onclick="showPage('crop-advisory')">
                Start Your Season &#8594;
              </button>
            </div>
          </div>

          <!-- ── STATS ROW ──────────────────────────── -->
          <div class="stats-row">
            <div class="stat-item">
              <span class="stat-num">100<span class="stat-suffix">%</span></span>
              <div class="stat-label">Customer Satisfaction</div>
            </div>
            <div class="stat-item">
              <span class="stat-num">20<span class="stat-suffix">+</span></span>
              <div class="stat-label">Years of Experience</div>
            </div>
            <div class="stat-item">
              <span class="stat-num"><?php echo count($crops); ?><span class="stat-suffix">+</span></span>
              <div class="stat-label">Crops Tracked</div>
            </div>
          </div>

          <!-- ── INNOVATING SECTION ─────────────────── -->
          <div class="page-header">
            <div>
              <p class="eyebrow">Innovating the Future</p>
              <h1>of Agriculture</h1>
            </div>
            <div class="status-pill">
              <span class="status-dot"></span>
              <span id="status-pill-text">System Online</span>
            </div>
          </div>

          <!-- ── FARM PHOTO GRID ────────────────────── -->
          <div class="farm-photo-grid">
            <img src="assets/images/paddy_hero.png"  alt="Paddy field overview" />
            <img src="assets/images/farm_grid1.png"  alt="Rice seedlings" />
            <img src="assets/images/farm_grid2.png"  alt="Planting rice" />
          </div>


          <div class="dashboard-grid">
            <article class="panel weather-card">
              <div class="weather-top">
                <div>
                  <p class="label">Live Weather (<span id="wd-district-label">Trincomalee</span>)</p>
                  <h2 id="temp-value">--&deg;C</h2>
                  <p class="weather-copy" id="weather-copy">Loading live weather data...</p>
                </div>
                <div class="weather-icon" aria-hidden="true">
                  <div class="sun"></div>
                  <div class="cloud"></div>
                </div>
              </div>
              <div class="advice-strip">
                <strong id="weather-risk">Loading...</strong>
                <span>Delay fertilizer application before heavy rain.</span>
              </div>
            </article>

            <article class="panel decision-card">
              <div class="card-header">
                <p class="label">Today's DSS Advice</p>
                <span>Auto summary</span>
              </div>
              <h2 id="daily-advice-title">
                <?php echo $topCrop ? htmlspecialchars($topCrop['crop']) : 'Select inputs'; ?> is currently the safest match
              </h2>
              <p id="daily-advice-text">
                <?php if($showRecommendations): ?>
                  Based on <?php echo htmlspecialchars($postSeason); ?> season, <?php echo htmlspecialchars(strtolower($postSoil)); ?> soil, <?php echo htmlspecialchars(strtolower($postWater)); ?> water, and <?php echo htmlspecialchars(strtolower($postDemand)); ?> market demand<?php if ($soilReading): ?> — adjusted using live soil data (pH <?php echo htmlspecialchars((string)$soilReading['ph']); ?>, moisture <?php echo htmlspecialchars((string)$soilReading['moisture']); ?>%)<?php endif; ?>.
                <?php else: ?>
                  Go to the Crop Advisor tab to generate your custom recommendation based on your land and season.
                <?php endif; ?>
              </p>
              <div class="score-row">
                <span>Recommendation confidence</span>
                <strong id="daily-score"><?php echo $topCrop ? $topCrop['score'] : '--'; ?>%</strong>
              </div>
            </article>
          </div>

          <div class="quick-row">
            <article class="metric-card">
              <p class="label">Decision Alerts</p>
              <strong id="warning-alerts">0 Critical Alerts</strong>
              <span>Rain and pest monitoring</span>
            </article>
            <article class="metric-card">
              <p class="label">Field Summary</p>
              <strong id="field-summary-value"><?php echo $fieldCount; ?> Active Field<?php echo $fieldCount === 1 ? '' : 's'; ?></strong>
              <span><?php echo $fieldCount > 0 ? 'IoT-ready soil monitoring enabled' : 'Add fields in Admin Panel'; ?></span>
            </article>
            <article class="metric-card">
              <p class="label">System Logs</p>
              <strong id="system-logs-value">7 New Entries</strong>
              <span>Updated market and weather data</span>
            </article>
          </div>

          <div class="section-title" style="margin-top: 2rem;">
            <div>
              <p class="eyebrow">Live Weather</p>
              <h2 style="margin:0; font-size:1.4rem; font-weight:700;">All Districts Overview</h2>
            </div>
            <span style="font-size:0.82rem; color:var(--muted);">Powered by Open-Meteo · Updates on load</span>
          </div>
          <div id="district-weather-grid" class="district-weather-grid">
            <div class="dwc-loading">Fetching live weather for all districts...</div>
          </div>
        </section>

        <!-- CROP ADVISOR -->
        <section id="crop-advisory" class="page">
          <div class="section-title">
            <p class="eyebrow">Advisory</p>
            <h1>Crop Advisory Engine</h1>
          </div>

          <div class="grid-two advisory-layout">
            <article class="panel advisory-inputs">
              <div class="card-header">
                <p class="label">Input Panel</p>
                <span>Change values and generate</span>
              </div>

              <form action="index.php" method="POST">
                <input type="hidden" name="action" value="recommend">
                <div class="form-grid">
                    <div class="form-group">
                    <label for="field-id">Farm Field (optional)</label>
                    <select id="field-id" name="field_id">
                        <option value="">Manual inputs only</option>
                        <?php foreach ($fieldRows as $field): ?>
                        <option value="<?php echo (int)$field['id']; ?>" <?php if ($postFieldId === (int)$field['id']) echo 'selected'; ?>><?php echo htmlspecialchars($field['field_name'] . ' (' . $field['district'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                    </div>
                    <div class="form-group">
                    <label for="district">District</label>
                    <select id="district" name="district">
                        <option value="Trincomalee" <?php if($postDistrict=='Trincomalee') echo 'selected'; ?>>Trincomalee</option>
                        <option value="Anuradhapura" <?php if($postDistrict=='Anuradhapura') echo 'selected'; ?>>Anuradhapura</option>
                        <option value="Jaffna" <?php if($postDistrict=='Jaffna') echo 'selected'; ?>>Jaffna</option>
                        <option value="Kandy" <?php if($postDistrict=='Kandy') echo 'selected'; ?>>Kandy</option>
                    </select>
                    </div>
                    <div class="form-group">
                    <label for="soil-type">Soil Type</label>
                    <select id="soil-type" name="soil_type">
                        <option value="Alluvial" <?php if($postSoil=='Alluvial') echo 'selected'; ?>>Alluvial</option>
                        <option value="Laterite" <?php if($postSoil=='Laterite') echo 'selected'; ?>>Laterite</option>
                        <option value="Sandy" <?php if($postSoil=='Sandy') echo 'selected'; ?>>Sandy</option>
                        <option value="Clay" <?php if($postSoil=='Clay') echo 'selected'; ?>>Clay</option>
                    </select>
                    </div>
                    <div class="form-group">
                    <label for="season-type">Current Season</label>
                    <select id="season-type" name="season_type">
                        <option value="Yala" <?php if($postSeason=='Yala') echo 'selected'; ?>>Yala</option>
                        <option value="Maha" <?php if($postSeason=='Maha') echo 'selected'; ?>>Maha</option>
                        <option value="Dry" <?php if($postSeason=='Dry') echo 'selected'; ?>>Dry</option>
                    </select>
                    </div>
                    <div class="form-group">
                    <label for="water-source">Water Source</label>
                    <select id="water-source" name="water_source">
                        <option value="Irrigation" <?php if($postWater=='Irrigation') echo 'selected'; ?>>Irrigation</option>
                        <option value="Rainfed" <?php if($postWater=='Rainfed') echo 'selected'; ?>>Rainfed</option>
                        <option value="Groundwater" <?php if($postWater=='Groundwater') echo 'selected'; ?>>Groundwater</option>
                    </select>
                    </div>
                    <div class="form-group">
                    <label for="budget-level">Budget Level</label>
                    <select id="budget-level" name="budget_level">
                        <option value="Medium" <?php if($postBudget=='Medium') echo 'selected'; ?>>Medium</option>
                        <option value="Low" <?php if($postBudget=='Low') echo 'selected'; ?>>Low</option>
                        <option value="High" <?php if($postBudget=='High') echo 'selected'; ?>>High</option>
                    </select>
                    </div>
                    <div class="form-group">
                    <label for="market-demand">Market Demand</label>
                    <select id="market-demand" name="market_demand">
                        <option value="High" <?php if($postDemand=='High') echo 'selected'; ?>>High</option>
                        <option value="Medium" <?php if($postDemand=='Medium') echo 'selected'; ?>>Medium</option>
                        <option value="Low" <?php if($postDemand=='Low') echo 'selected'; ?>>Low</option>
                    </select>
                    </div>
                </div>

                <button class="primary-button" type="submit" id="generate-button">
                    Generate Recommendations
                </button>
              </form>
              <?php if ($soilReading && $selectedField): ?>
              <div class="soil-reading-strip" style="margin-top:1rem; padding:12px; background:var(--surface-soft); border-radius:8px; border:1px solid var(--border); font-size:0.9rem;">
                <strong>Latest soil data — <?php echo htmlspecialchars($selectedField['field_name']); ?></strong>
                <p style="margin:6px 0 0;">pH: <?php echo htmlspecialchars((string)$soilReading['ph']); ?> · Moisture: <?php echo htmlspecialchars((string)$soilReading['moisture']); ?>% · EC: <?php echo htmlspecialchars((string)($soilReading['ec'] ?? '—')); ?> · Temp: <?php echo htmlspecialchars((string)($soilReading['temperature'] ?? '—')); ?>°C</p>
                <span style="color:var(--muted); font-size:0.8rem;">Recorded <?php echo htmlspecialchars($soilReading['created_at']); ?></span>
              </div>
              <?php elseif ($postFieldId > 0 && $selectedField): ?>
              <div class="soil-reading-strip" style="margin-top:1rem; padding:12px; background:rgba(239,68,68,0.08); border-radius:8px; border:1px solid rgba(239,68,68,0.2); font-size:0.9rem;">
                <strong>No soil readings yet for <?php echo htmlspecialchars($selectedField['field_name']); ?></strong>
                <p style="margin:6px 0 0;">Use the IoT endpoint or Admin Panel to add readings.</p>
              </div>
              <?php endif; ?>
            </article>

            <article class="panel advisory-results">
              <div class="card-header">
                <p class="label">Best Match Crops</p>
                <span id="recommendation-summary"><?php echo $topCrop ? htmlspecialchars($topCrop['crop']) . " is the highest scoring option" : "Top choices for current inputs"; ?></span>
              </div>
              <div class="match-cards" id="crop-results">
                <?php $i = 0; foreach ($recommendedCrops as $item): if ($i++ >= 3) break; ?>
                    <article class="crop-card" tabindex="0" onclick="openModal('<?php echo htmlspecialchars($item['crop']); ?>')">
                    <div class="crop-card-top">
                        <h3><?php echo htmlspecialchars($item['crop']); ?></h3>
                        <span class="score-badge"><?php echo $item['score']; ?>%</span>
                    </div>
                    <p>Expected duration: <?php echo htmlspecialchars($item['duration']); ?>. Profit score: <?php echo $item['profit']; ?>/100.</p>
                    <div class="crop-tags">
                        <span><?php echo htmlspecialchars(implode(" / ", $item['bestSoils'])); ?></span>
                        <span><?php echo htmlspecialchars(implode(" / ", $item['bestSeasons'])); ?></span>
                        <span><?php echo htmlspecialchars(implode(" / ", $item['bestWater'])); ?></span>
                    </div>
                    <button class="outline-button" type="button">View Growth Guide</button>
                    </article>
                <?php endforeach; ?>
              </div>
            </article>
          </div>

          <article class="panel explanation-panel">
            <div class="card-header">
              <p class="label">Why This Recommendation?</p>
              <span>Explainable DSS output</span>
            </div>
            <ul class="reason-list" id="reason-list">
                <?php if ($showRecommendations && $topCrop): ?>
                    <li>District selected: <?php echo htmlspecialchars($postDistrict); ?><?php if ($selectedField): ?> · Field: <?php echo htmlspecialchars($selectedField['field_name']); ?><?php endif; ?>.</li>
                    <li>The system compared soil type, season, water source, budget, demand<?php if ($soilReading): ?>, and live soil parameters (pH, moisture, EC)<?php endif; ?>.</li>
                    <?php foreach ($topCrop['reasons'] as $r): ?>
                        <li><?php echo htmlspecialchars($r); ?></li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>Generate a recommendation to see detailed reasons here.</li>
                <?php endif; ?>
            </ul>
          </article>
        </section>

        <!-- CALCULATOR -->
        <section id="calculator" class="page">
          <div class="section-title">
            <p class="eyebrow">Calculator</p>
            <h1>Input & Fertilizer Calculator</h1>
          </div>

          <div class="calculator-top">
            <div class="panel input-field">
              <label for="target-crop">Target Crop</label>
              <select id="target-crop">
                <?php foreach ($crops as $c): ?>
                    <option value="<?php echo htmlspecialchars($c['crop']); ?>"><?php echo htmlspecialchars($c['crop']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="panel input-field">
              <label for="land-size">Land Size in Acres</label>
              <input id="land-size" type="number" min="0.25" step="0.25" value="2.5" />
            </div>
          </div>

          <article class="panel results-card">
            <div class="card-header">
              <p class="label">Required Fertilizer Dosage</p>
              <span id="calculator-note">Calculated for 2.5 acres</span>
            </div>
            <div class="results-grid">
              <div class="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Type</th>
                      <th>Kilograms</th>
                      <th>Application Schedule</th>
                    </tr>
                  </thead>
                  <tbody id="fertilizer-body">
                    <!-- Javascript populates this based on user typing without reloading -->
                  </tbody>
                </table>
              </div>
              <aside class="side-callout">
                <strong id="cost-estimate">Estimated cost: LKR 0</strong>
                <p id="fertilizer-tip">Apply during early morning for maximum absorption.</p>
              </aside>
            </div>
          </article>
        </section>

        <!-- MARKET -->
        <section id="market-watch" class="page">
          <div class="section-title">
            <p class="eyebrow">Market</p>
            <h1>Live Crop Market Watch</h1>
          </div>

          <div class="grid-two market-layout">
            <article class="panel pricing-panel">
              <div class="card-header">
                <p class="label">Local Market Prices</p>
                <span>Current LKR/kg and weekly trends</span>
              </div>
              <div class="table-wrap">
                <table class="price-table">
                  <thead>
                    <tr>
                      <th>Crop</th>
                      <th>Price</th>
                      <th>Trend</th>
                      <th>Demand</th>
                    </tr>
                  </thead>
                  <tbody id="market-body">
                    <?php foreach ($marketRows as $m): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($m['crop_name']); ?></td>
                            <td>LKR <?php echo htmlspecialchars((string)$m['price_per_kg']); ?></td>
                            <td><span class="trend <?php echo htmlspecialchars($m['trend']); ?>"><?php echo ucfirst($m['trend']); ?></span></td>
                            <td><?php echo htmlspecialchars($m['demand']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </article>

            <article class="panel trend-panel">
              <div class="card-header">
                <p class="label">Profit Opportunity</p>
                <span>Simple DSS ranking</span>
              </div>
              <div class="profit-list" id="profit-list">
                <?php $i = 0; foreach ($rankedMarkets as $rm): if ($i++ >= 3) break; ?>
                    <article class="profit-card">
                        <div class="profit-card-top">
                            <h3><?php echo $i; ?>. <?php echo htmlspecialchars($rm['crop']); ?></h3>
                            <span class="score-badge"><?php echo $rm['finalScore']; ?>/100</span>
                        </div>
                        <p>Good selling potential based on crop profit score and current price trend.</p>
                    </article>
                <?php endforeach; ?>
              </div>
            </article>
          </div>
        </section>

        <!-- ADMIN PANEL -->
        <?php if (isAdmin()): ?>
        <section id="admin-panel" class="page">
          <div class="section-title">
            <p class="eyebrow">Admin</p>
            <h1>Manage Market Prices</h1>
          </div>

          <article class="panel">
            <div class="card-header">
              <p class="label">Live Market Prices</p>
              <span>Update prices, demand, and trends</span>
            </div>
            <div class="table-wrap">
              <table class="price-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Crop</th>
                    <th>Price per kg (LKR)</th>
                    <th>Trend</th>
                    <th>Demand</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="admin-market-body">
                    <?php foreach ($marketRows as $m): ?>
                    <tr>
                        <form method="POST" action="index.php">
                            <input type="hidden" name="action" value="update_price">
                            <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                            <td><?php echo $m['id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($m['crop_name']); ?></strong></td>
                            <td><input type="number" name="price" value="<?php echo htmlspecialchars((string)$m['price_per_kg']); ?>" class="admin-input-small" required /></td>
                            <td>
                                <select name="trend" class="admin-input-small">
                                    <option value="up" <?php if($m['trend']=='up') echo 'selected'; ?>>Up</option>
                                    <option value="down" <?php if($m['trend']=='down') echo 'selected'; ?>>Down</option>
                                    <option value="stable" <?php if($m['trend']=='stable') echo 'selected'; ?>>Stable</option>
                                </select>
                            </td>
                            <td>
                                <select name="demand" class="admin-input-small">
                                    <option value="High" <?php if($m['demand']=='High') echo 'selected'; ?>>High</option>
                                    <option value="Medium" <?php if($m['demand']=='Medium') echo 'selected'; ?>>Medium</option>
                                    <option value="Low" <?php if($m['demand']=='Low') echo 'selected'; ?>>Low</option>
                                </select>
                            </td>
                            <td><button type="submit" class="outline-button">Save</button></td>
                        </form>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </article>

          <article class="panel" style="margin-top: 2rem;">
            <div class="card-header">
              <p class="label">Add New Crop Details</p>
              <span>Add a new crop with recommendations, guide, fertilizer, and market details</span>
            </div>
            
            <form action="index.php" method="POST" class="admin-add-form">
              <input type="hidden" name="action" value="add_crop">
              <div class="form-grid">
                <div class="form-group">
                  <label for="add-crop-name">Crop Name</label>
                  <input type="text" id="add-crop-name" name="crop_name" placeholder="e.g. Carrot" required />
                </div>
                <div class="form-group">
                  <label for="add-crop-duration">Expected Duration</label>
                  <input type="text" id="add-crop-duration" name="duration" placeholder="e.g. 80 - 100 days" required />
                </div>
                <div class="form-group">
                  <label for="add-crop-profit">Profit Score (0-100)</label>
                  <input type="number" id="add-crop-profit" name="profit_score" min="0" max="100" placeholder="e.g. 85" required />
                </div>
                <div class="form-group">
                  <label for="add-crop-price">Initial Market Price per kg (LKR)</label>
                  <input type="number" id="add-crop-price" name="price" min="1" placeholder="e.g. 350" required />
                </div>
                <div class="form-group">
                  <label for="add-crop-trend">Market Price Trend</label>
                  <select id="add-crop-trend" name="trend">
                    <option value="stable">Stable</option>
                    <option value="up">Up</option>
                    <option value="down">Down</option>
                  </select>
                </div>
                <div class="form-group">
                  <label for="add-crop-budget">Budget Level</label>
                  <select id="add-crop-budget" name="budget_level">
                    <option value="Low">Low</option>
                    <option value="Medium" selected>Medium</option>
                    <option value="High">High</option>
                  </select>
                </div>
                <div class="form-group">
                  <label for="add-crop-demand">Market Demand</label>
                  <select id="add-crop-demand" name="market_demand">
                    <option value="High" selected>High</option>
                    <option value="Medium">Medium</option>
                    <option value="Low">Low</option>
                  </select>
                </div>
              </div>

              <div class="form-section-title" style="margin-top: 1.5rem; font-weight: 700; color: var(--accent); border-bottom: 1px solid var(--border); padding-bottom: 5px;">Suitability Options</div>
              <div class="checkbox-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-top: 1rem;">
                <div class="checkbox-group">
                  <span class="checkbox-group-label" style="display: block; font-weight: 600; margin-bottom: 8px;">Best Soil Types</span>
                  <div class="checkbox-options" style="display: flex; flex-direction: column; gap: 8px;">
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; font-weight: normal;"><input type="checkbox" name="best_soils[]" value="Alluvial" style="width: auto; min-height: auto;" /> Alluvial</label>
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; font-weight: normal;"><input type="checkbox" name="best_soils[]" value="Laterite" style="width: auto; min-height: auto;" /> Laterite</label>
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; font-weight: normal;"><input type="checkbox" name="best_soils[]" value="Sandy" style="width: auto; min-height: auto;" /> Sandy</label>
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; font-weight: normal;"><input type="checkbox" name="best_soils[]" value="Clay" style="width: auto; min-height: auto;" /> Clay</label>
                  </div>
                </div>
                
                <div class="checkbox-group">
                  <span class="checkbox-group-label" style="display: block; font-weight: 600; margin-bottom: 8px;">Best Seasons</span>
                  <div class="checkbox-options" style="display: flex; flex-direction: column; gap: 8px;">
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; font-weight: normal;"><input type="checkbox" name="best_seasons[]" value="Yala" style="width: auto; min-height: auto;" /> Yala</label>
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; font-weight: normal;"><input type="checkbox" name="best_seasons[]" value="Maha" style="width: auto; min-height: auto;" /> Maha</label>
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; font-weight: normal;"><input type="checkbox" name="best_seasons[]" value="Dry" style="width: auto; min-height: auto;" /> Dry</label>
                  </div>
                </div>
                
                <div class="checkbox-group">
                  <span class="checkbox-group-label" style="display: block; font-weight: 600; margin-bottom: 8px;">Best Water Sources</span>
                  <div class="checkbox-options" style="display: flex; flex-direction: column; gap: 8px;">
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; font-weight: normal;"><input type="checkbox" name="best_water[]" value="Irrigation" style="width: auto; min-height: auto;" /> Irrigation</label>
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; font-weight: normal;"><input type="checkbox" name="best_water[]" value="Rainfed" style="width: auto; min-height: auto;" /> Rainfed</label>
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; font-weight: normal;"><input type="checkbox" name="best_water[]" value="Groundwater" style="width: auto; min-height: auto;" /> Groundwater</label>
                  </div>
                </div>
              </div>

              <div class="form-section-title" style="margin-top: 1.5rem; font-weight: 700; color: var(--accent); border-bottom: 1px solid var(--border); padding-bottom: 5px;">Growth Guide Details</div>
              <div class="form-group full-width" style="margin-top: 1rem;">
                <label for="add-crop-guide">Growth Guide Steps (one step per line)</label>
                <textarea id="add-crop-guide" name="guide" placeholder="Step 1: Keep soil moist during germination.&#10;Step 2: Apply nitrogen split doses.&#10;Step 3: Harvest when bulbs mature." rows="4" style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 5px; outline: none; font-family: inherit;" required></textarea>
              </div>

              <div class="form-section-title" style="margin-top: 1.5rem; font-weight: 700; color: var(--accent); border-bottom: 1px solid var(--border); padding-bottom: 5px;">Fertilizer Dosage & Costs</div>
              <div class="fertilizer-rules-inputs" style="display: grid; gap: 12px; margin-top: 1rem;">
                <div class="fertilizer-row-input" style="display: grid; grid-template-columns: 100px 1fr 1fr 2fr; gap: 10px; align-items: center;">
                  <span class="fertilizer-name-badge" style="font-weight: bold; background: var(--surface-soft); padding: 8px; border-radius: 4px; border: 1px solid var(--border); text-align: center;">Urea</span>
                  <input type="number" name="fertilizer_urea_kg" placeholder="Kg per acre" min="0" step="0.1" />
                  <input type="number" name="fertilizer_urea_price" placeholder="Price per kg (LKR)" min="0" step="1" value="320" />
                  <input type="text" name="fertilizer_urea_schedule" placeholder="Application schedule details" />
                </div>
                <div class="fertilizer-row-input" style="display: grid; grid-template-columns: 100px 1fr 1fr 2fr; gap: 10px; align-items: center;">
                  <span class="fertilizer-name-badge" style="font-weight: bold; background: var(--surface-soft); padding: 8px; border-radius: 4px; border: 1px solid var(--border); text-align: center;">MOP</span>
                  <input type="number" name="fertilizer_mop_kg" placeholder="Kg per acre" min="0" step="0.1" />
                  <input type="number" name="fertilizer_mop_price" placeholder="Price per kg (LKR)" min="0" step="1" value="360" />
                  <input type="text" name="fertilizer_mop_schedule" placeholder="Application schedule details" />
                </div>
                <div class="fertilizer-row-input" style="display: grid; grid-template-columns: 100px 1fr 1fr 2fr; gap: 10px; align-items: center;">
                  <span class="fertilizer-name-badge" style="font-weight: bold; background: var(--surface-soft); padding: 8px; border-radius: 4px; border: 1px solid var(--border); text-align: center;">TSP</span>
                  <input type="number" name="fertilizer_tsp_kg" placeholder="Kg per acre" min="0" step="0.1" />
                  <input type="number" name="fertilizer_tsp_price" placeholder="Price per kg (LKR)" min="0" step="1" value="420" />
                  <input type="text" name="fertilizer_tsp_schedule" placeholder="Application schedule details" />
                </div>
                <div class="fertilizer-row-input" style="display: grid; grid-template-columns: 100px 1fr 1fr 2fr; gap: 10px; align-items: center;">
                  <span class="fertilizer-name-badge" style="font-weight: bold; background: var(--surface-soft); padding: 8px; border-radius: 4px; border: 1px solid var(--border); text-align: center;">Compost</span>
                  <input type="number" name="fertilizer_compost_kg" placeholder="Kg per acre" min="0" step="0.1" />
                  <input type="number" name="fertilizer_compost_price" placeholder="Price per kg (LKR)" min="0" step="1" value="35" />
                  <input type="text" name="fertilizer_compost_schedule" placeholder="Application schedule details" />
                </div>
              </div>

              <div class="form-section-title" style="margin-top: 1.5rem; font-weight: 700; color: var(--accent); border-bottom: 1px solid var(--border); padding-bottom: 5px;">Soil Parameter Requirements (IoT DSS)</div>
              <div class="form-grid" style="margin-top: 1rem;">
                <div class="form-group">
                  <label for="add-min-ph">Min pH</label>
                  <input type="number" id="add-min-ph" name="min_ph" step="0.1" min="0" max="14" placeholder="e.g. 5.5" />
                </div>
                <div class="form-group">
                  <label for="add-max-ph">Max pH</label>
                  <input type="number" id="add-max-ph" name="max_ph" step="0.1" min="0" max="14" placeholder="e.g. 7.0" />
                </div>
                <div class="form-group">
                  <label for="add-min-moisture">Min Moisture %</label>
                  <input type="number" id="add-min-moisture" name="min_moisture" step="0.1" min="0" placeholder="e.g. 40" />
                </div>
                <div class="form-group">
                  <label for="add-max-moisture">Max Moisture %</label>
                  <input type="number" id="add-max-moisture" name="max_moisture" step="0.1" min="0" placeholder="e.g. 70" />
                </div>
                <div class="form-group">
                  <label for="add-max-ec">Max EC (salinity)</label>
                  <input type="number" id="add-max-ec" name="max_ec" step="0.1" min="0" placeholder="e.g. 2.0" />
                </div>
              </div>

              <button type="submit" class="primary-button" style="margin-top: 1.5rem;">Add Crop & Details</button>
            </form>
          </article>

          <article class="panel" style="margin-top: 2rem;">
            <div class="card-header">
              <p class="label">Crop Soil Requirements</p>
              <span>Ideal pH, moisture, and EC ranges per crop</span>
            </div>
            <div class="table-wrap">
              <table class="price-table">
                <thead>
                  <tr>
                    <th>Crop</th>
                    <th>Min pH</th>
                    <th>Max pH</th>
                    <th>Min Moisture</th>
                    <th>Max Moisture</th>
                    <th>Max EC</th>
                    <th>Save</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($cropRows as $crop): $req = $requirementsByCropId[(int)$crop['id']] ?? null; ?>
                  <tr>
                    <form method="POST" action="index.php">
                      <input type="hidden" name="action" value="update_soil_requirements">
                      <input type="hidden" name="crop_id" value="<?php echo (int)$crop['id']; ?>">
                      <td><strong><?php echo htmlspecialchars($crop['crop_name']); ?></strong></td>
                      <td><input type="number" name="min_ph" step="0.1" class="admin-input-small" value="<?php echo $req ? htmlspecialchars((string)$req['min_ph']) : ''; ?>" /></td>
                      <td><input type="number" name="max_ph" step="0.1" class="admin-input-small" value="<?php echo $req ? htmlspecialchars((string)$req['max_ph']) : ''; ?>" /></td>
                      <td><input type="number" name="min_moisture" step="0.1" class="admin-input-small" value="<?php echo $req ? htmlspecialchars((string)$req['min_moisture']) : ''; ?>" /></td>
                      <td><input type="number" name="max_moisture" step="0.1" class="admin-input-small" value="<?php echo $req ? htmlspecialchars((string)$req['max_moisture']) : ''; ?>" /></td>
                      <td><input type="number" name="max_ec" step="0.1" class="admin-input-small" value="<?php echo $req ? htmlspecialchars((string)$req['max_ec']) : ''; ?>" /></td>
                      <td><button type="submit" class="outline-button">Save</button></td>
                    </form>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </article>

          <article class="panel" style="margin-top: 2rem;">
            <div class="card-header">
              <p class="label">Farm Fields</p>
              <span>Locations linked to IoT soil readings</span>
            </div>
            <div class="table-wrap" style="margin-bottom: 1.5rem;">
              <table class="price-table">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Field Name</th>
                    <th>District</th>
                    <th>Soil Type</th>
                    <th>Owner</th>
                    <th>Latest Reading</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($fieldRows)): ?>
                  <tr><td colspan="6">No fields yet. Add one below.</td></tr>
                  <?php else: foreach ($fieldRows as $field):
                    $lr = $latestSoilByField[(int)$field['id']] ?? null;
                  ?>
                  <tr>
                    <td><?php echo (int)$field['id']; ?></td>
                    <td><?php echo htmlspecialchars($field['field_name']); ?></td>
                    <td><?php echo htmlspecialchars($field['district']); ?></td>
                    <td><?php echo htmlspecialchars($field['soil_type']); ?></td>
                    <td><?php echo htmlspecialchars($field['owner_name'] ?? 'Shared'); ?></td>
                    <td><?php echo $lr ? 'pH ' . htmlspecialchars((string)$lr['ph']) . ', ' . htmlspecialchars((string)$lr['moisture']) . '%' : '—'; ?></td>
                  </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
            <form action="index.php" method="POST" class="admin-add-form">
              <input type="hidden" name="action" value="add_field">
              <div class="form-grid">
                <div class="form-group">
                  <label for="add-field-name">Field Name</label>
                  <input type="text" id="add-field-name" name="field_name" placeholder="e.g. North Paddy Plot" required />
                </div>
                <div class="form-group">
                  <label for="add-field-district">District</label>
                  <select id="add-field-district" name="district">
                    <option value="Trincomalee">Trincomalee</option>
                    <option value="Anuradhapura">Anuradhapura</option>
                    <option value="Jaffna">Jaffna</option>
                    <option value="Kandy">Kandy</option>
                  </select>
                </div>
                <div class="form-group">
                  <label for="add-field-soil">Soil Type</label>
                  <select id="add-field-soil" name="soil_type">
                    <option value="Alluvial">Alluvial</option>
                    <option value="Laterite">Laterite</option>
                    <option value="Sandy">Sandy</option>
                    <option value="Clay">Clay</option>
                  </select>
                </div>
                <div class="form-group">
                  <label for="add-field-owner">Owner (optional)</label>
                  <select id="add-field-owner" name="owner_user_id">
                    <option value="">Shared / Demo</option>
                    <?php foreach ($allUsers as $u): ?>
                    <option value="<?php echo (int)$u['id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <button type="submit" class="primary-button" style="margin-top: 1rem;">Add Field</button>
            </form>
          </article>

          <article class="panel" style="margin-top: 2rem;">
            <div class="card-header">
              <p class="label">Soil Readings</p>
              <span>Manual entry or via IoT endpoint <code>api/receive_soil_data.php</code></span>
            </div>
            <form action="index.php" method="POST" class="admin-add-form" style="margin-bottom: 1.5rem;">
              <input type="hidden" name="action" value="add_soil_reading">
              <div class="form-grid">
                <div class="form-group">
                  <label for="reading-field-id">Field</label>
                  <select id="reading-field-id" name="field_id" required>
                    <option value="">Select field</option>
                    <?php foreach ($fieldRows as $field): ?>
                    <option value="<?php echo (int)$field['id']; ?>"><?php echo htmlspecialchars($field['field_name']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label for="reading-moisture">Moisture %</label>
                  <input type="number" id="reading-moisture" name="moisture" step="0.1" required />
                </div>
                <div class="form-group">
                  <label for="reading-ph">pH</label>
                  <input type="number" id="reading-ph" name="ph" step="0.1" min="0" max="14" required />
                </div>
                <div class="form-group">
                  <label for="reading-ec">EC</label>
                  <input type="number" id="reading-ec" name="ec" step="0.1" />
                </div>
                <div class="form-group">
                  <label for="reading-temp">Temperature °C</label>
                  <input type="number" id="reading-temp" name="temp" step="0.1" />
                </div>
              </div>
              <button type="submit" class="primary-button" style="margin-top: 1rem;">Save Reading</button>
            </form>
            <div class="table-wrap">
              <table class="price-table">
                <thead>
                  <tr>
                    <th>Field</th>
                    <th>Moisture</th>
                    <th>pH</th>
                    <th>EC</th>
                    <th>Temp</th>
                    <th>Recorded</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($recentReadings)): ?>
                  <tr><td colspan="6">No readings yet.</td></tr>
                  <?php else: foreach ($recentReadings as $r): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($r['field_name']); ?></td>
                    <td><?php echo htmlspecialchars((string)$r['moisture']); ?>%</td>
                    <td><?php echo htmlspecialchars((string)$r['ph']); ?></td>
                    <td><?php echo htmlspecialchars((string)($r['ec'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars((string)($r['temperature'] ?? '—')); ?>°C</td>
                    <td><?php echo htmlspecialchars($r['created_at']); ?></td>
                  </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>
            <p style="margin-top:1rem; font-size:0.85rem; color:var(--muted);">
              IoT test URL: <code>api/receive_soil_data.php?field_id=1&amp;moisture=22.8&amp;ph=6.5&amp;ec=0.9&amp;temp=28.4</code>
            </p>
          </article>
        </section>
        <?php endif; ?>

        <!-- HOW IT WORKS -->
        <section id="how-it-works" class="page">
          <div class="section-title">
            <div>
              <p class="eyebrow">Guide</p>
              <h1>How It Works</h1>
            </div>
            <span style="font-size:0.82rem; color:var(--muted);">Your step-by-step DSS walkthrough</span>
          </div>

          <!-- Hero strip -->
          <div class="hiw-hero">
            <div class="hiw-hero-text">
              <h2>Smart farming decisions, <span class="hiw-accent">powered by data</span></h2>
              <p>Farmers DSS combines real-time weather, crop science, and market intelligence into one easy-to-use platform — helping you decide <em>what to grow, when to grow it, and how much to spend</em>.</p>
            </div>
            <div class="hiw-hero-badge">
              <div class="hiw-badge-ring">
                <span class="hiw-badge-icon">🌾</span>
              </div>
              <p>Decision Support System</p>
            </div>
          </div>

          <!-- Steps -->
          <div class="hiw-steps-title">
            <p class="eyebrow">The Process</p>
            <h2>5 Simple Steps</h2>
          </div>
          <div class="hiw-steps">
            <div class="hiw-step">
              <div class="hiw-step-num">1</div>
              <div class="hiw-step-body">
                <h3>Log In to Your Account</h3>
                <p>Create a free Farmer account or sign in with your credentials. Admins have extra controls for managing market data and crops.</p>
              </div>
            </div>
            <div class="hiw-step">
              <div class="hiw-step-num">2</div>
              <div class="hiw-step-body">
                <h3>Check Live Weather on the Dashboard</h3>
                <p>The Dashboard fetches real-time weather for all 4 districts via Open-Meteo. If rain is forecast, the system flags a critical alert automatically.</p>
              </div>
            </div>
            <div class="hiw-step">
              <div class="hiw-step-num">3</div>
              <div class="hiw-step-body">
                <h3>Generate a Crop Recommendation</h3>
                <p>Go to <strong>Crop Advisor</strong>, select your district, soil type, season, water source, budget, and expected market demand — then click <em>Generate Recommendations</em>. The engine scores every crop against your inputs and ranks the top matches.</p>
              </div>
            </div>
            <div class="hiw-step">
              <div class="hiw-step-num">4</div>
              <div class="hiw-step-body">
                <h3>Calculate Your Fertilizer &amp; Costs</h3>
                <p>Head to the <strong>Calculator</strong> tab, pick your crop and land size. The system instantly shows required fertilizer quantities (Urea, MOP, TSP, Compost) and estimates the total cost in LKR.</p>
              </div>
            </div>
            <div class="hiw-step">
              <div class="hiw-step-num">5</div>
              <div class="hiw-step-body">
                <h3>Track the Market &amp; Sell Smart</h3>
                <p>The <strong>Market Watch</strong> tab shows live LKR/kg prices, weekly trends, and a DSS profit-opportunity ranking — so you know the best time to sell your harvest.</p>
              </div>
            </div>
          </div>

          <!-- Feature cards -->
          <div class="hiw-features-title" style="margin-top:2.5rem;">
            <p class="eyebrow">Features</p>
            <h2>What Each Section Does</h2>
          </div>
          <div class="hiw-features">
            <article class="hiw-feature-card">
              <div class="hiw-feature-icon" style="background:#edf7ed; color:#1f5f30;">📊</div>
              <h3>Dashboard</h3>
              <p>Live weather for every district, today's top crop recommendation, system alerts, and a full weather overview grid — all in one glance.</p>
            </article>
            <article class="hiw-feature-card">
              <div class="hiw-feature-icon" style="background:#fef3c7; color:#92400e;">🌱</div>
              <h3>Crop Advisor</h3>
              <p>AI-style rule-based recommendation engine. Scores each crop out of 100 based on soil, season, water, budget, and demand. Tap any card to read the full growth guide.</p>
            </article>
            <article class="hiw-feature-card">
              <div class="hiw-feature-icon" style="background:#ede9fe; color:#4c1d95;">🧮</div>
              <h3>Calculator</h3>
              <p>Instant fertilizer dosage calculator. Change crop or land size and the table updates live — no page reload. Includes estimated LKR cost.</p>
            </article>
            <article class="hiw-feature-card">
              <div class="hiw-feature-icon" style="background:#fee2e2; color:#b91c1c;">📈</div>
              <h3>Market Watch</h3>
              <p>Up/Down/Stable price trends, current LKR/kg rates, and a profit-opportunity ranking that factors in both crop profit score and market trend.</p>
            </article>
            <article class="hiw-feature-card">
              <div class="hiw-feature-icon" style="background:#dbeafe; color:#1e40af;">⚠️</div>
              <h3>Decision Alerts</h3>
              <p>The alert badge counts districts with high rain risk. Click the <strong>!</strong> button to view district-by-district warnings and recommended actions.</p>
            </article>
            <article class="hiw-feature-card">
              <div class="hiw-feature-icon" style="background:#f0fdf4; color:#166534;">🔐</div>
              <h3>Admin Panel</h3>
              <p>Admins can update live market prices, trends, and demand levels, and add entirely new crops complete with guides, fertilizer rules, and market data.</p>
            </article>
          </div>

          <!-- CTA -->
          <div class="hiw-cta">
            <h2>Ready to make smarter farming decisions?</h2>
            <p>Go to the Crop Advisor and generate your first recommendation in under 30 seconds.</p>
            <button class="primary-button hiw-cta-btn" onclick="showPage('crop-advisory')">
              Open Crop Advisor →
            </button>
          </div>
        </section>
      </main>

      <!-- Footer -->
      <footer class="site-footer">
        <div class="footer-inner">
          <div class="footer-brand">
            <img src="assets/images/logo.png" alt="Farmers DSS Logo" class="footer-logo" />
            <div>
              <span class="footer-brand-name">Farmers DSS</span>
              <span class="footer-tagline">Smart decisions for every harvest</span>
            </div>
          </div>

          <div class="footer-links">
            <span class="footer-section-label">Navigate</span>
            <a href="#dashboard"    onclick="showPage('dashboard');    return false;">Dashboard</a>
            <a href="#crop-advisory" onclick="showPage('crop-advisory'); return false;">Crop Advisor</a>
            <a href="#calculator"   onclick="showPage('calculator');   return false;">Calculator</a>
            <a href="#market-watch" onclick="showPage('market-watch'); return false;">Market Watch</a>
            <a href="#how-it-works" onclick="showPage('how-it-works'); return false;">How It Works</a>
          </div>

          <div class="footer-contact">
            <span class="footer-section-label">Contact Us</span>
            <div class="footer-socials">
              <!-- Facebook -->
              <a href="https://facebook.com" target="_blank" rel="noopener" class="social-btn social-fb" aria-label="Facebook">
                <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
              </a>
              <!-- Instagram -->
              <a href="https://instagram.com" target="_blank" rel="noopener" class="social-btn social-ig" aria-label="Instagram">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
              </a>
              <!-- Twitter / X -->
              <a href="https://twitter.com" target="_blank" rel="noopener" class="social-btn social-x" aria-label="Twitter / X">
                <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
              </a>
              <!-- LinkedIn -->
              <a href="https://linkedin.com" target="_blank" rel="noopener" class="social-btn social-li" aria-label="LinkedIn">
                <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
              </a>
              <!-- WhatsApp -->
              <a href="https://wa.me/94000000000" target="_blank" rel="noopener" class="social-btn social-wa" aria-label="WhatsApp">
                <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M11.93 0C5.354 0 0 5.354 0 11.93c0 2.1.549 4.07 1.508 5.778L0 24l6.444-1.688A11.9 11.9 0 0 0 11.93 23.86C18.508 23.86 24 18.508 24 11.93 24 5.354 18.508 0 11.93 0zm0 21.785a9.837 9.837 0 0 1-5.015-1.374l-.36-.214-3.727.977.995-3.635-.235-.374A9.854 9.854 0 0 1 2.143 11.93c0-5.4 4.394-9.793 9.787-9.793 5.4 0 9.788 4.394 9.788 9.793 0 5.394-4.388 9.855-9.788 9.855z"/></svg>
              </a>
              <!-- Email -->
              <a href="mailto:contact@farmersdss.com" class="social-btn social-email" aria-label="Email us">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
              </a>
              <!-- GitHub -->
              <a href="https://github.com" target="_blank" rel="noopener" class="social-btn social-gh" aria-label="GitHub">
                <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M12 0C5.374 0 0 5.373 0 12c0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23A11.509 11.509 0 0 1 12 5.803c1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576C20.566 21.797 24 17.3 24 12c0-6.627-5.373-12-12-12z"/></svg>
              </a>
            </div>
            <p class="footer-email-text">contact@farmersdss.com</p>
          </div>
        </div>

        <div class="footer-bottom">
          <span>&copy; <?php echo date('Y'); ?> Farmers DSS &mdash; Built for Sri Lankan farmers.</span>
          <span>Powered by <a href="https://open-meteo.com" target="_blank" rel="noopener">Open-Meteo</a> weather API.</span>
        </div>
      </footer>

      <div class="toast" id="toast" aria-live="polite"></div>

      <!-- Alert Popup Modal -->
      <div class="modal-overlay" id="alert-modal-overlay" aria-hidden="true">
        <div class="modal-card alert-modal-card" role="dialog" aria-modal="true" aria-labelledby="alert-modal-title">
          <button class="modal-close" id="alert-modal-close" aria-label="Close alerts">&#x2715;</button>
          <div class="modal-header">
            <div class="modal-crop-icon alert-icon">&#x26A0;</div>
            <div>
              <p class="eyebrow">Active Alerts</p>
              <h2 id="alert-modal-title">Decision Alerts</h2>
            </div>
          </div>
          <div class="modal-body" id="alert-modal-body"></div>
          <div class="modal-footer">
            <button class="outline-button" id="alert-modal-close-btn">Dismiss</button>
          </div>
        </div>
      </div>

      <div class="modal-overlay" id="modal-overlay" aria-hidden="true">
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="modal-title">
          <button class="modal-close" id="modal-close" aria-label="Close modal">x</button>
          <div class="modal-header">
            <div class="modal-crop-icon" id="modal-icon"></div>
            <div>
              <p class="eyebrow">Growth Guide</p>
              <h2 id="modal-title">Crop Growth Guide</h2>
            </div>
          </div>
          <div class="modal-body">
            <p id="modal-description"></p>
            <ul id="modal-list"></ul>
          </div>
          <div class="modal-footer">
            <button class="outline-button" id="modal-close-btn">Close</button>
          </div>
        </div>
      </div>
    </div>

    <script src="assets/js/script.js"></script>
  </body>
</html>
