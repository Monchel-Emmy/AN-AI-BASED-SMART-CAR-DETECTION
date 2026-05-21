<?php
require_once 'includes/auth.php';
require_once '../api/config/db.php';

$db = getDB();

// Filters
$severity  = $_GET['severity']  ?? '';
$dateFrom  = $_GET['date_from'] ?? '';
$dateTo    = $_GET['date_to']   ?? '';

$where  = [];
$params = [];

if ($severity) {
    $where[]  = "a.severity = ?";
    $params[] = $severity;
}
if ($dateFrom) {
    $where[]  = "DATE(a.created_at) >= ?";
    $params[] = $dateFrom;
}
if ($dateTo) {
    $where[]  = "DATE(a.created_at) <= ?";
    $params[] = $dateTo;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare("
    SELECT a.*,
           d.device_code,
           v.plate_number, v.make, v.model, v.color,
           dr.full_name AS driver_name, dr.phone AS driver_phone
    FROM accidents a
    JOIN devices d   ON a.device_id  = d.id
    LEFT JOIN vehicles v   ON d.vehicle_id = v.id
    LEFT JOIN drivers dr   ON v.driver_id  = dr.id
    {$whereSQL}
    ORDER BY a.created_at DESC
");
$stmt->execute($params);
$accidents = $stmt->fetchAll();

$pageTitle = 'Accidents';
require_once 'includes/header.php';
?>

<!-- Image Modal -->
<div class="modal-overlay" id="imgModal">
    <div class="modal">
        <button class="modal-close" onclick="closeModal()">✕</button>
        <h3 style="font-size:15px;margin-bottom:4px">Accident Image</h3>
        <img id="modalImg" src="" alt="Accident image">
    </div>
</div>

<!-- Filters -->
<div class="card" style="margin-bottom:20px">
    <div class="card-body" style="padding:16px 20px">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
            <div class="form-group" style="min-width:140px">
                <label>Severity</label>
                <select name="severity">
                    <option value="">All</option>
                    <?php foreach (['urgent','medium','normal'] as $s): ?>
                    <option value="<?= $s ?>" <?= $severity === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="min-width:140px">
                <label>From Date</label>
                <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
            </div>
            <div class="form-group" style="min-width:140px">
                <label>To Date</label>
                <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
            </div>
            <div style="display:flex;gap:8px;padding-bottom:1px">
                <button type="submit" class="btn btn-primary btn-sm">🔍 Filter</button>
                <a href="accidents.php" class="btn btn-ghost btn-sm">Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Accidents Table -->
<div class="card">
    <div class="card-header">
        <h2>Accident Records (<?= count($accidents) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date / Time</th>
                    <th>Device</th>
                    <th>Plate</th>
                    <th>Driver</th>
                    <th>Phone</th>
                    <th>Severity</th>
                    <th>Accel (X/Y/Z)</th>
                    <th>Gyro (X/Y/Z)</th>
                    <th>Location</th>
                    <th>Image</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($accidents)): ?>
                <tr><td colspan="11" class="empty">No accidents found.</td></tr>
                <?php else: ?>
                <?php foreach ($accidents as $a): ?>
                <tr>
                    <td><?= $a['id'] ?></td>
                    <td style="white-space:nowrap"><?= date('M d Y, H:i', strtotime($a['created_at'])) ?></td>
                    <td><?= htmlspecialchars($a['device_code']) ?></td>
                    <td><?= htmlspecialchars($a['plate_number'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($a['driver_name'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($a['driver_phone'] ?? '—') ?></td>
                    <td><span class="badge badge-<?= $a['severity'] ?>"><?= strtoupper($a['severity']) ?></span></td>
                    <td style="font-size:12px;white-space:nowrap">
                        <?= number_format($a['accel_x'],2) ?> /
                        <?= number_format($a['accel_y'],2) ?> /
                        <?= number_format($a['accel_z'],2) ?>
                    </td>
                    <td style="font-size:12px;white-space:nowrap">
                        <?= number_format($a['gyro_x'],3) ?> /
                        <?= number_format($a['gyro_y'],3) ?> /
                        <?= number_format($a['gyro_z'],3) ?>
                    </td>
                    <td>
                        <?php if ($a['latitude'] && $a['longitude']): ?>
                        <a href="https://maps.google.com/?q=<?= $a['latitude'] ?>,<?= $a['longitude'] ?>"
                           target="_blank" class="map-link">📍 Map</a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td>
                        <?php if ($a['image_path']): ?>
                        <img src="/accident/web/<?= htmlspecialchars($a['image_path']) ?>"
                             class="img-thumb"
                             onclick="openImage('/accident/web/<?= htmlspecialchars($a['image_path']) ?>')"
                             alt="accident">
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
