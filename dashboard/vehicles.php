<?php
require_once 'includes/auth.php';
require_once '../api/config/db.php';

$db  = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $stmt = $db->prepare("
            INSERT INTO vehicles (plate_number, make, model, year, color, driver_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        try {
            $stmt->execute([
                strtoupper(trim($_POST['plate_number'])),
                trim($_POST['make']),
                trim($_POST['model']),
                $_POST['year'] ?: null,
                trim($_POST['color']),
                $_POST['driver_id'] ?: null,
            ]);
            $msg = ['type' => 'success', 'text' => 'Vehicle registered successfully.'];
        } catch (PDOException $e) {
            $msg = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
        }
    }

    if ($action === 'delete') {
        $db->prepare("DELETE FROM vehicles WHERE id = ?")->execute([(int)$_POST['id']]);
        $msg = ['type' => 'success', 'text' => 'Vehicle deleted.'];
    }

    if ($action === 'edit') {
        $stmt = $db->prepare("
            UPDATE vehicles SET plate_number=?, make=?, model=?, year=?, color=?, driver_id=?
            WHERE id=?
        ");
        try {
            $stmt->execute([
                strtoupper(trim($_POST['plate_number'])),
                trim($_POST['make']),
                trim($_POST['model']),
                $_POST['year'] ?: null,
                trim($_POST['color']),
                $_POST['driver_id'] ?: null,
                (int)$_POST['id'],
            ]);
            $msg = ['type' => 'success', 'text' => 'Vehicle updated successfully.'];
        } catch (PDOException $e) {
            $msg = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
        }
    }
}

$editVehicle = null;
if (isset($_GET['edit'])) {
    $s = $db->prepare("SELECT * FROM vehicles WHERE id = ?");
    $s->execute([(int)$_GET['edit']]);
    $editVehicle = $s->fetch();
}

$vehicles = $db->query("
    SELECT v.*, d.full_name AS driver_name
    FROM vehicles v
    LEFT JOIN drivers d ON v.driver_id = d.id
    ORDER BY v.created_at DESC
")->fetchAll();

$drivers = $db->query("SELECT id, full_name FROM drivers ORDER BY full_name")->fetchAll();

$pageTitle = 'Vehicles';
require_once 'includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-<?= $msg['type'] ?>"><?= htmlspecialchars($msg['text']) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px">
    <div class="card-header">
        <h2><?= $editVehicle ? 'Edit Vehicle' : 'Register New Vehicle' ?></h2>
        <?php if ($editVehicle): ?>
        <a href="vehicles.php" class="btn btn-ghost btn-sm">Cancel</a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="action" value="<?= $editVehicle ? 'edit' : 'add' ?>">
            <?php if ($editVehicle): ?>
            <input type="hidden" name="id" value="<?= $editVehicle['id'] ?>">
            <?php endif; ?>
            <div class="form-grid">
                <div class="form-group">
                    <label>Plate Number *</label>
                    <input type="text" name="plate_number" required placeholder="RAB 001 A"
                           value="<?= htmlspecialchars($editVehicle['plate_number'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Make</label>
                    <input type="text" name="make" placeholder="Toyota"
                           value="<?= htmlspecialchars($editVehicle['make'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Model</label>
                    <input type="text" name="model" placeholder="Corolla"
                           value="<?= htmlspecialchars($editVehicle['model'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <input type="number" name="year" min="1990" max="2030" placeholder="2022"
                           value="<?= htmlspecialchars($editVehicle['year'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Color</label>
                    <input type="text" name="color" placeholder="White"
                           value="<?= htmlspecialchars($editVehicle['color'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Assign Driver</label>
                    <select name="driver_id">
                        <option value="">— No driver —</option>
                        <?php foreach ($drivers as $dr): ?>
                        <option value="<?= $dr['id'] ?>"
                            <?= ($editVehicle['driver_id'] ?? '') == $dr['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dr['full_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $editVehicle ? '💾 Save Changes' : '➕ Register Vehicle' ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>All Vehicles (<?= count($vehicles) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Plate</th>
                    <th>Make / Model</th>
                    <th>Year</th>
                    <th>Color</th>
                    <th>Driver</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($vehicles)): ?>
                <tr><td colspan="7" class="empty">No vehicles registered yet.</td></tr>
                <?php else: ?>
                <?php foreach ($vehicles as $v): ?>
                <tr>
                    <td><?= $v['id'] ?></td>
                    <td><strong><?= htmlspecialchars($v['plate_number']) ?></strong></td>
                    <td><?= htmlspecialchars(($v['make'] ?? '') . ' ' . ($v['model'] ?? '')) ?></td>
                    <td><?= $v['year'] ?? '—' ?></td>
                    <td><?= htmlspecialchars($v['color'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($v['driver_name'] ?? '—') ?></td>
                    <td>
                        <a href="vehicles.php?edit=<?= $v['id'] ?>" class="btn btn-ghost btn-sm">✏️ Edit</a>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $v['id'] ?>">
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
