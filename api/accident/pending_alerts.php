<?php
/**
 * GET /api/accident/pending_alerts.php
 * Returns accidents from the last 60 seconds that need a WhatsApp alert
 * fired from the browser (because server-side curl is blocked on localhost).
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/whatsapp.php';

$pdo = getDB();

// Get urgent/medium accidents from last 60 seconds not yet browser-alerted
$stmt = $pdo->query("
    SELECT a.*,
           v.plate_number,
           dr.full_name, dr.phone
    FROM accidents a
    JOIN devices d   ON a.device_id  = d.id
    LEFT JOIN vehicles v  ON d.vehicle_id = v.id
    LEFT JOIN drivers dr  ON v.driver_id  = dr.id
    WHERE a.severity IN ('urgent', 'medium')
      AND a.browser_alerted = 0
      AND a.created_at >= NOW() - INTERVAL 60 SECOND
    ORDER BY a.created_at DESC
    LIMIT 5
");
$accidents = $stmt->fetchAll();

$alerts = [];
foreach ($accidents as $a) {
    $accidentData = [
        'severity'   => $a['severity'],
        'latitude'   => $a['latitude'],
        'longitude'  => $a['longitude'],
        'accel_x'    => $a['accel_x'],
        'accel_y'    => $a['accel_y'],
        'accel_z'    => $a['accel_z'],
        'gyro_x'     => $a['gyro_x'],
        'gyro_y'     => $a['gyro_y'],
        'gyro_z'     => $a['gyro_z'],
        'created_at' => $a['created_at'],
    ];
    $driver  = ['full_name' => $a['full_name'],    'phone' => $a['phone']];
    $vehicle = ['plate_number' => $a['plate_number']];

    $alerts[] = [
        'accident_id'  => $a['id'],
        'severity'     => $a['severity'],
        'whatsapp_url' => buildWhatsAppUrl(buildAccidentMessage($accidentData, $driver, $vehicle)),
    ];

    // Mark as browser-alerted so we don't send twice
    $pdo->prepare("UPDATE accidents SET browser_alerted = 1 WHERE id = ?")
        ->execute([$a['id']]);
}

echo json_encode(['alerts' => $alerts]);
