<?php
require_once 'includes/auth.php';
require_once '../api/config/db.php';

$db  = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $stmt = $db->prepare("
            INSERT INTO devices (device_code, vehicle_id, status, notes)
            VALUES (?, ?, ?, ?)
        ");
        try {
            $stmt->execute([
                strtoupper(trim($_POST['device_code'])),
                $_POST['vehicle_id'] ?: null,
                $_POST['status'],
                trim($_POST['notes']),
            ]);
            $msg = ['type' => 'success', 'text' => 'Device registered successfully.'];
        } catch (PDOException $e) {
            $msg = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
        }
    }

    if ($action === 'delete') {
        $db->prepare("DELETE FROM devices WHERE id = ?")->execute([(int)$_POST['id']]);
        $msg = ['type' => 'success', 'text' => 'Device deleted.'];
    }

    if ($action === 'edit') {
        $stmt = $db->prepare("
            UPDATE devices SET device_code=?, vehicle_id=?, status=?, notes=?
            WHERE id=?
        ");
        try {
            $stmt->execute([
                strtoupper(trim($_POST['device_code'])),
                $_POST['vehicle_id'] ?: null,
                $_POST['status'],
                trim($_POST['notes']),
                (int)$_POST['id'],
            ]);
            $msg = ['type' => 'success', 'text' => 'Device updated successfully.'];
        } catch (PDOException $e) {
            $msg = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
        }
    }
}

$editDevice = null;
if (isset($_GET['edit'])) {
    $s = $db->prepare("SELECT * FROM devices WHERE id = ?");
    $s->execute([(int)$_GET['edit']]);
    $editDevice = $s->fetch();
}

$devices = $db->query("
    SELECT d.*, v.plate_number, v.make, v.model,
           dr.full_name AS driver_name
    FROM devices d
    LEFT JOIN vehicles v  ON d.vehicle_id = v.id
    LEFT JOIN drivers dr  ON v.driver_id  = dr.id
    ORDER BY d.created_at DESC
")->fetchAll();

$vehicles = $db->query("
    SELECT v.id, v.plate_number, v.make, v.model, d.full_name AS driver_name
    FROM vehicles v
    LEFT JOIN drivers d ON v.driver_id = d.id
    ORDER BY v.plate_number
")->fetchAll();

$pageTitle = 'Devices';
require_once 'includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg['type'] ?>"><?= htmlspecialchars($msg['text']) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px">
    <div class="card-header">
        <h2><?= $editDevice ? 'Edit Device' : 'Register New Device' ?></h2>
        <?php if ($editDevice): ?>
        <a href="devices.php" class="btn btn-ghost btn-sm">Cancel</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="<?= $editDevice ? 'edit' : 'add' ?>">
            <?php if ($editDevice): ?>
            <input type="hidden" name="id" value="<?= $editDevice['id'] ?>">
            <?php endif; ?>
            <div class="form-grid">
                <div class="form-group">
                    <label>Device Code *</label>
                    <input type="text" name="device_code" required placeholder="DEV-001"
                           value="<?= htmlspecialchars($editDevice['device_code'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Assign to Vehicle</label>
                    <select name="vehicle_id">
                        <option value="">— No vehicle —</option>
                        <?php foreach ($vehicles as $v): ?>
                        <option value="<?= $v['id'] ?>"
                            <?= ($editDevice['vehicle_id'] ?? '') == $v['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($v['plate_number'] . ' — ' . $v['make'] . ' ' . $v['model']) ?>
                            <?= $v['driver_name'] ? '(' . htmlspecialchars($v['driver_name']) . ')' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <?php foreach (['active', 'inactive', 'maintenance'] as $s): ?>
                        <option value="<?= $s ?>"
                            <?= ($editDevice['status'] ?? 'active') === $s ? 'selected' : '' ?>>
                            <?= ucfirst($s) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group full">
                    <label>Notes</label>
                    <input type="text" name="notes" placeholder="Optional notes"
                           value="<?= htmlspecialchars($editDevice['notes'] ?? '') ?>">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $editDevice ? '💾 Save Changes' : '➕ Register Device' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>All Devices (<?= count($devices) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Device Code</th>
                    <th>Vehicle</th>
                    <th>Driver</th>
                    <th>Status</th>
                    <th>Notes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($devices)): ?>
                <tr><td colspan="7" class="empty">No devices registered yet.</td></tr>
                <?php else: ?>
                <?php foreach ($devices as $d): ?>
                <tr>
                    <td><?= $d['id'] ?></td>
                    <td><strong><?= htmlspecialchars($d['device_code']) ?></strong></td>
                    <td><?= htmlspecialchars($d['plate_number'] ? $d['plate_number'] . ' — ' . $d['make'] . ' ' . $d['model'] : '—') ?></td>
                    <td><?= htmlspecialchars($d['driver_name'] ?? '—') ?></td>
                    <td><span class="badge badge-<?= $d['status'] ?>"><?= ucfirst($d['status']) ?></span></td>
                    <td><?= htmlspecialchars($d['notes'] ?? '—') ?></td>
                    <td>
                        <a href="devices.php?edit=<?= $d['id'] ?>" class="btn btn-ghost btn-sm">✏️ Edit</a>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $d['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm confirm-delete">🗑️ Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
