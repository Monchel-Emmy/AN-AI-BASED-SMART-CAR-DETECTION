<?php
/**
 * Dashboard — Overview
 */
require_once 'includes/auth.php';
require_once '../api/config/db.php';

$db = getDB();

// Stats
$totalDrivers   = $db->query("SELECT COUNT(*) FROM drivers")->fetchColumn();
$totalVehicles  = $db->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
$totalDevices   = $db->query("SELECT COUNT(*) FROM devices")->fetchColumn();
$totalAccidents = $db->query("SELECT COUNT(*) FROM accidents")->fetchColumn();
$urgentToday    = $db->query("SELECT COUNT(*) FROM accidents WHERE severity='urgent' AND DATE(created_at)=CURDATE()")->fetchColumn();

// Recent accidents
$recent = $db->query("
    SELECT a.*, d.device_code,
           v.plate_number, v.make, v.model,
           dr.full_name AS driver_name, dr.phone AS driver_phone
    FROM accidents a
    JOIN devices d  ON a.device_id  = d.id
    LEFT JOIN vehicles v  ON d.vehicle_id = v.id
    LEFT JOIN drivers dr  ON v.driver_id  = dr.id
    ORDER BY a.created_at DESC
    LIMIT 8
")->fetchAll();

$pageTitle = 'Dashboard';
require_once 'includes/header.php';
?>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon drivers"><i class="icon">👤</i></div>
        <div class="stat-info">
            <span class="stat-number"><?= $totalDrivers ?></span>
            <span class="stat-label">Drivers</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon vehicles"><i class="icon">🚗</i></div>
        <div class="stat-info">
            <span class="stat-number"><?= $totalVehicles ?></span>
            <span class="stat-label">Vehicles</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon devices"><i class="icon">📡</i></div>
        <div class="stat-info">
            <span class="stat-number"><?= $totalDevices ?></span>
            <span class="stat-label">Devices</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon accidents"><i class="icon">⚠️</i></div>
        <div class="stat-info">
            <span class="stat-number"><?= $totalAccidents ?></span>
            <span class="stat-label">Total Accidents</span>
        </div>
    </div>
    <div class="stat-card urgent">
        <div class="stat-icon urgent-icon"><i class="icon">🚨</i></div>
        <div class="stat-info">
            <span class="stat-number"><?= $urgentToday ?></span>
            <span class="stat-label">Urgent Today</span>
        </div>
    </div>
</div>

<!-- Recent Accidents -->
<div class="card">
    <div class="card-header">
        <h2>Recent Accidents</h2>
        <a href="accidents.php" class="btn btn-sm">View All</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Time</th>
                    <th>Device</th>
                    <th>Plate</th>
                    <th>Driver</th>
                    <th>Severity</th>
                    <th>Location</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent)): ?>
                <tr><td colspan="7" class="empty">No accidents recorded yet.</td></tr>
                <?php else: ?>
                <?php foreach ($recent as $a): ?>
                <tr>
                    <td><?= $a['id'] ?></td>
                    <td><?= date('M d, H:i', strtotime($a['created_at'])) ?></td>
                    <td><?= htmlspecialchars($a['device_code']) ?></td>
                    <td><?= htmlspecialchars($a['plate_number'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($a['driver_name'] ?? '—') ?></td>
                    <td><span class="badge badge-<?= $a['severity'] ?>"><?= strtoupper($a['severity']) ?></span></td>
                    <td>
                        <?php if ($a['latitude'] && $a['longitude']): ?>
                        <a href="https://maps.google.com/?q=<?= $a['latitude'] ?>,<?= $a['longitude'] ?>" target="_blank" class="map-link">📍 View</a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
