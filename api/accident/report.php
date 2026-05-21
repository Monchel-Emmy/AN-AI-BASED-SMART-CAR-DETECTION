<?php
/**
 * POST /api/accident/report.php
 * ─────────────────────────────
 * Receives accident data from the ESP32 device.
 * Stores it in the database and sends WhatsApp alert if urgent/medium.
 *
 * Expected JSON body:
 * {
 *   "device_code": "DEV-001",
 *   "severity":    "urgent",        // urgent | medium | normal
 *   "latitude":    -1.9441,
 *   "longitude":   30.0619,
 *   "accel_x":     2.45,
 *   "accel_y":    -1.12,
 *   "accel_z":     9.81,
 *   "gyro_x":      0.03,
 *   "gyro_y":      0.01,
 *   "gyro_z":     -0.02,
 *   "image_base64": "..."           // optional: base64 encoded image
 * }
 *
 * Response (201):
 * {
 *   "success": true,
 *   "accident_id": 12,
 *   "message": "Accident recorded",
 *   "alert_sent": true
 * }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/whatsapp.php';
require_once __DIR__ . '/../config/helpers.php';

// ── Parse request body ────────────────────────────────────────────────────────
$body = getJsonBody();

// Required fields
$required = ['device_code', 'severity', 'accel_x', 'accel_y', 'accel_z',
             'gyro_x', 'gyro_y', 'gyro_z'];

$missing = validateRequired($body, $required);
if (!empty($missing)) {
    jsonResponse([
        'error'   => 'Missing required fields',
        'missing' => $missing
    ], 400);
}

// Validate severity value
$validSeverities = ['urgent', 'medium', 'normal'];
$severity = strtolower(clean($body['severity']));
if (!in_array($severity, $validSeverities)) {
    jsonResponse([
        'error'   => 'Invalid severity value',
        'allowed' => $validSeverities
    ], 400);
}

$deviceCode = clean($body['device_code']);
$latitude   = isset($body['latitude'])  ? (float)$body['latitude']  : null;
$longitude  = isset($body['longitude']) ? (float)$body['longitude'] : null;
$accelX     = (float)$body['accel_x'];
$accelY     = (float)$body['accel_y'];
$accelZ     = (float)$body['accel_z'];
$gyroX      = (float)$body['gyro_x'];
$gyroY      = (float)$body['gyro_y'];
$gyroZ      = (float)$body['gyro_z'];

// ── Handle image (optional) ───────────────────────────────────────────────────
$imagePath = null;
if (!empty($body['image_base64'])) {
    $imageData = base64_decode($body['image_base64']);
    if ($imageData !== false) {
        $uploadDir = __DIR__ . '/../../uploads/accidents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $filename  = 'accident_' . time() . '_' . uniqid() . '.jpg';
        $imagePath = 'uploads/accidents/' . $filename;
        file_put_contents($uploadDir . $filename, $imageData);
    }
}

// ── Look up device → vehicle → driver ────────────────────────────────────────
$pdo = getDB();

$stmt = $pdo->prepare("
    SELECT
        d.id          AS device_id,
        d.device_code,
        v.id          AS vehicle_id,
        v.plate_number,
        v.make,
        v.model,
        dr.id         AS driver_id,
        dr.full_name,
        dr.phone,
        dr.email
    FROM devices d
    LEFT JOIN vehicles v  ON v.id = d.vehicle_id
    LEFT JOIN drivers dr  ON dr.id = v.driver_id
    WHERE d.device_code = ?
    LIMIT 1
");
$stmt->execute([$deviceCode]);
$deviceInfo = $stmt->fetch();

if (!$deviceInfo) {
    jsonResponse([
        'error'       => 'Device not found',
        'device_code' => $deviceCode,
        'hint'        => 'Register this device in the dashboard first'
    ], 404);
}

// ── Insert accident record ────────────────────────────────────────────────────
$insert = $pdo->prepare("
    INSERT INTO accidents (
        device_id, severity,
        latitude, longitude,
        accel_x, accel_y, accel_z,
        gyro_x,  gyro_y,  gyro_z,
        image_path, created_at
    ) VALUES (
        :device_id, :severity,
        :latitude, :longitude,
        :accel_x, :accel_y, :accel_z,
        :gyro_x,  :gyro_y,  :gyro_z,
        :image_path, NOW()
    )
");

$insert->execute([
    ':device_id'  => $deviceInfo['device_id'],
    ':severity'   => $severity,
    ':latitude'   => $latitude,
    ':longitude'  => $longitude,
    ':accel_x'    => $accelX,
    ':accel_y'    => $accelY,
    ':accel_z'    => $accelZ,
    ':gyro_x'     => $gyroX,
    ':gyro_y'     => $gyroY,
    ':gyro_z'     => $gyroZ,
    ':image_path' => $imagePath,
]);

$accidentId = (int)$pdo->lastInsertId();

// ── Send WhatsApp alert (urgent and medium only) ──────────────────────────────
$alertSent    = false;
$whatsappUrl  = null;

if (in_array($severity, ['urgent', 'medium'])) {
    $accidentData = [
        'severity'   => $severity,
        'latitude'   => $latitude,
        'longitude'  => $longitude,
        'accel_x'    => $accelX,
        'accel_y'    => $accelY,
        'accel_z'    => $accelZ,
        'gyro_x'     => $gyroX,
        'gyro_y'     => $gyroY,
        'gyro_z'     => $gyroZ,
        'created_at' => date('Y-m-d H:i:s'),
    ];

    $driver  = ['full_name' => $deviceInfo['full_name'], 'phone' => $deviceInfo['phone']];
    $vehicle = ['plate_number' => $deviceInfo['plate_number']];

    $message     = buildAccidentMessage($accidentData, $driver, $vehicle);
    $alertSent   = sendWhatsApp($message);

    // If server-side curl failed (e.g. localhost), return URL for browser to fire
    if (!$alertSent) {
        $whatsappUrl = buildWhatsAppUrl($message);
    }
}

// ── Return response ───────────────────────────────────────────────────────────
jsonResponse([
    'success'       => true,
    'accident_id'   => $accidentId,
    'severity'      => $severity,
    'driver'        => $deviceInfo['full_name']    ?? 'Unknown',
    'plate'         => $deviceInfo['plate_number'] ?? 'Unknown',
    'alert_sent'    => $alertSent,
    'whatsapp_url'  => $whatsappUrl,   // non-null only when curl was blocked
    'message'       => "Accident recorded successfully"
], 201);
