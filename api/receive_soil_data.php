<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

function parseGetFloat(string $key): ?float
{
    if (!isset($_GET[$key])) {
        return null;
    }
    $value = trim((string)$_GET[$key]);
    if ($value === '' || !is_numeric($value)) {
        return null;
    }
    return (float)$value;
}

function parseGetInt(string $key): int
{
    if (!isset($_GET[$key])) {
        return 0;
    }
    $value = trim((string)$_GET[$key]);
    if ($value === '' || !is_numeric($value)) {
        return 0;
    }
    return (int)$value;
}

$fieldId  = parseGetInt('field_id');
$moisture = parseGetFloat('moisture');
$ph       = parseGetFloat('ph');
$ec       = parseGetFloat('ec');
$temp     = parseGetFloat('temp');

if ($fieldId <= 0 || $moisture === null || $ph === null) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Missing or invalid parameters. Required: field_id, moisture, ph'
    ]);
    exit;
}

try {
    $check = $pdo->prepare('SELECT id FROM fields WHERE id = ?');
    $check->execute([$fieldId]);
    if (!$check->fetch()) {
        http_response_code(404);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Field not found'
        ]);
        exit;
    }

    $stmt = $pdo->prepare('
        INSERT INTO soil_readings (field_id, moisture, ph, ec, temperature, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ');
    $stmt->execute([$fieldId, $moisture, $ph, $ec, $temp]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Database error'
    ]);
    exit;
}

echo json_encode([
    'status'  => 'success',
    'message' => 'Soil reading stored',
    'fieldId' => $fieldId
]);
