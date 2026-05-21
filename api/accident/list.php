<?php
/**
 * GET /api/accident/list.php
 * ──────────────────────────
 * Returns recent accidents with driver and vehicle info.
 *
 * Query params (all optional):
 *   ?severity=urgent          filter by severity
 *   ?limit=20                 number of records (default 20, max 100)
 *   ?page=1                   pagination
 *   ?device_code=DEV-001      filter by device
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use GET.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

$pdo = getDB();

// ── Query params ──────────────────────────────────────────────
$severity   = isset($_GET['severity'])    ? clean($_GET['severity'])    : null;
$deviceCode = isset($_GET['device_code']) ? clean($_GET['device_code']) : null;
$limit      = min((int)($_GET['limit'] ?? 20), 100);
$page       = max((int)($_GET['page']  ?? 1), 1);
$offset     = ($page - 1) * $limit;

// ── Build query ───────────────────────────────────────────────
$where  = [];
$params = [];

if ($severity) {
    $where[]  = 'a.severity = :severity';
    $params[':severity'] = $severity;
}
if ($deviceCode) {
    $where[]  = 'd.device_code = :device_code';
    $params[':device_code'] = $deviceCode;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT
        a.id,
        a.severity,
        a.latitude,
        a.longitude,
        a.accel_x, a.accel_y, a.accel_z,
        a.gyro_x,  a.gyro_y,  a.gyro_z,
        a.image_path,
        a.created_at,
        d.device_code,
        v.plate_number,
        v.make,
        v.model,
        dr.full_name  AS driver_name,
        dr.phone      AS driver_phone
    FROM accidents a
    JOIN devices  d  ON d.id  = a.device_id
    LEFT JOIN vehicles v   ON v.id  = d.vehicle_id
    LEFT JOIN drivers  dr  ON dr.id = v.driver_id
    {$whereClause}
    ORDER BY a.created_at DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$accidents = $stmt->fetchAll();

// ── Total count ───────────────────────────────────────────────
$countSql  = "SELECT COUNT(*) FROM accidents a
              JOIN devices d ON d.id = a.device_id
              LEFT JOIN vehicles v ON v.id = d.vehicle_id
              {$whereClause}";
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $val) {
    $countStmt->bindValue($key, $val);
}
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();

jsonResponse([
    'success'    => true,
    'total'      => $total,
    'page'       => $page,
    'limit'      => $limit,
    'pages'      => (int)ceil($total / $limit),
    'accidents'  => $accidents
]);
