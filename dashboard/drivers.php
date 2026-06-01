<?php
require_once 'includes/auth.php';
require_once '../api/config/db.php';

$db  = getDB();
$msg = '';

// ── Handle form submissions ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $stmt = $db->prepare("
            INSERT INTO drivers (full_name, phone, email, license_number, address)
            VALUES (?, ?, ?, ?, ?)
        ");
        try {
            $stmt->execute([
                trim($_POST['full_name']),
                trim($_POST['phone']),
                trim($_POST['email']),
                trim($_POST['license_number']),
                trim($_POST['address']),
            ]);
            $newDriverId = (int)$db->lastInsertId();

            // Assign vehicle to this driver if selected
            if (!empty($_POST['vehicle_id'])) {
                $db->prepare("UPDATE vehicles SET driver_id = ? WHERE id = ?")
                   ->execute([$newDriverId, (int)$_POST['vehicle_id']]);
            }

            $msg = ['type' => 'success', 'text' => 'Driver registered successfully.'];
        } catch (PDOException $e) {
            $msg = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
        }
    }

    if ($action === 'delete') {
        $db->prepare("DELETE FROM drivers WHERE id = ?")->execute([(int)$_POST['id']]);
        $msg = ['type' => 'success', 'text' => 'Driver deleted.'];
    }

    if ($action === 'edit') {
        $driverId = (int)$_POST['id'];
        $stmt = $db->prepare("
            UPDATE drivers SET full_name=?, phone=?, email=?, license_number=?, address=?
            WHERE id=?
        ");
        try {
            $stmt->execute([
                trim($_POST['full_name']),
                trim($_POST['phone']),
                trim($_POST['email']),
                trim($_POST['license_number']),
                trim($_POST['address']),
                $driverId,
            ]);

            // Unassign driver from any vehicle they were previously assigned to
            $db->prepare("UPDATE vehicles SET driver_id = NULL WHERE driver_id = ?")
               ->execute([$driverId]);

            // Assign to new vehicle if selected
            if (!empty($_POST['vehicle_id'])) {
                $db->prepare("UPDATE vehicles SET driver_id = ? WHERE id = ?")
                   ->execute([$driverId, (int)$_POST['vehicle_id']]);
            }

            $msg = ['type' => 'success', 'text' => 'Driver updated successfully.'];
        } catch (PDOException $e) {
            $msg = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
        }
    }
}

// Fetch for edit
$editDriver       = null;
$editDriverVehicle = null;
if (isset($_GET['edit'])) {
    $s = $db->prepare("SELECT * FROM drivers WHERE id = ?");
    $s->execute([(int)$_GET['edit']]);
    $editDriver = $s->fetch();

    if ($editDriver) {
        // Find which vehicle is currently assigned to this driver
        $sv = $db->prepare("SELECT id FROM vehicles WHERE driver_id = ? LIMIT 1");
        $sv->execute([$editDriver['id']]);
        $editDriverVehicle = $sv->fetchColumn();
    }
}

// All vehicles with their device info for the dropdown
$vehicles = $db->query("
    SELECT v.id, v.plate_number, v.make, v.model,
           d.device_code,
           dr.full_name AS current_driver
    FROM vehicles v
    LEFT JOIN devices d  ON d.vehicle_id = v.id
    LEFT JOIN drivers dr ON dr.id = v.driver_id
    ORDER BY v.plate_number
")->fetchAll();

// List all drivers with their assigned vehicle and device
$drivers = $db->query("
    SELECT dr.*,
           v.plate_number, v.make, v.model,
           d.device_code
    FROM drivers dr
    LEFT JOIN vehicles v ON v.driver_id = dr.id
    LEFT JOIN devices d  ON d.vehicle_id = v.id
    ORDER BY dr.created_at DESC
")->fetchAll();

$pageTitle = 'Drivers';
require_once 'includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg['type'] ?>"><?= htmlspecialchars($msg['text']) ?></div>
<?php endif; ?>

<!-- Add / Edit Form -->
<div class="card" style="margin-bottom:24px">
    <div class="card-header">
        <h2><?= $editDriver ? 'Edit Driver' : 'Register New Driver' ?></h2>
        <?php if ($editDriver): ?>
        <a href="drivers.php" class="btn btn-ghost btn-sm">Cancel</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="<?= $editDriver ? 'edit' : 'add' ?>">
            <?php if ($editDriver): ?>
            <input type="hidden" name="id" value="<?= $editDriver['id'] ?>">
            <?php endif; ?>
            <div class="form-grid">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" name="full_name" required
                           value="<?= htmlspecialchars($editDriver['full_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Phone *</label>
                    <input type="text" name="phone" required placeholder="+250780000000"
                           value="<?= htmlspecialchars($editDriver['phone'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email"
                           value="<?= htmlspecialchars($editDriver['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>License Number *</label>
                    <input type="text" name="license_number" required
                           value="<?= htmlspecialchars($editDriver['license_number'] ?? '') ?>">
                </div>
                <div class="form-group full">
                    <label>Address</label>
                    <input type="text" name="address"
                           value="<?= htmlspecialchars($editDriver['address'] ?? '') ?>">
                </div>

                <!-- Vehicle / Device Assignment -->
                <div class="form-group full">
                    <label>Assign Vehicle & Device</label>
                    <select name="vehicle_id">
                        <option value="">— No vehicle assigned —</option>
                        <?php foreach ($vehicles as $v):
                            $selected = ($editDriverVehicle == $v['id']) ? 'selected' : '';
                            $label    = htmlspecialchars($v['plate_number']);
                            if ($v['make'])        $label .= ' — ' . htmlspecialchars($v['make'] . ' ' . $v['model']);
                            if ($v['device_code']) $label .= ' 📡 ' . htmlspecialchars($v['device_code']);

                            // Show if already taken by another driver
                            $takenNote = '';
                            if ($v['current_driver'] && $editDriver && $v['current_driver'] !== $editDriver['full_name']) {
                                $takenNote = ' (currently: ' . htmlspecialchars($v['current_driver']) . ')';
                            } elseif ($v['current_driver'] && !$editDriver) {
                                $takenNote = ' (currently: ' . htmlspecialchars($v['current_driver']) . ')';
                            }
                        ?>
                        <option value="<?= $v['id'] ?>" <?= $selected ?>>
                            <?= $label . $takenNote ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color:var(--text-muted);margin-top:4px;display:block">
                        The device assigned to the selected vehicle will automatically be linked to this driver.
                        Vehicles with 📡 already have a device installed.
                    </small>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $editDriver ? '💾 Save Changes' : '➕ Register Driver' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Drivers Table -->
<div class="card">
    <div class="card-header">
        <h2>All Drivers (<?= count($drivers) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Full Name</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>License</th>
                    <th>Vehicle</th>
                    <th>Device</th>
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($drivers)): ?>
                <tr><td colspan="9" class="empty">No drivers registered yet.</td></tr>
                <?php else: ?>
                <?php foreach ($drivers as $d): ?>
                <tr>
                    <td><?= $d['id'] ?></td>
                    <td><?= htmlspecialchars($d['full_name']) ?></td>
                    <td><?= htmlspecialchars($d['phone']) ?></td>
                    <td><?= htmlspecialchars($d['email'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($d['license_number']) ?></td>
                    <td>
                        <?php if ($d['plate_number']): ?>
                            <span style="font-weight:600"><?= htmlspecialchars($d['plate_number']) ?></span>
                            <span style="color:var(--text-muted);font-size:12px">
                                <?= htmlspecialchars($d['make'] . ' ' . $d['model']) ?>
                            </span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td>
                        <?php if ($d['device_code']): ?>
                            <span class="badge badge-active">📡 <?= htmlspecialchars($d['device_code']) ?></span>
                        <?php else: ?>
                            <span style="color:var(--text-muted);font-size:12px">None</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('M d, Y', strtotime($d['created_at'])) ?></td>
                    <td>
                        <a href="drivers.php?edit=<?= $d['id'] ?>" class="btn btn-ghost btn-sm">✏️ Edit</a>
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
