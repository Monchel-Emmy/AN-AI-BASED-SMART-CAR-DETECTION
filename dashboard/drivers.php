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
                (int)$_POST['id'],
            ]);
            $msg = ['type' => 'success', 'text' => 'Driver updated successfully.'];
        } catch (PDOException $e) {
            $msg = ['type' => 'error', 'text' => 'Error: ' . $e->getMessage()];
        }
    }
}

// Fetch for edit
$editDriver = null;
if (isset($_GET['edit'])) {
    $editDriver = $db->prepare("SELECT * FROM drivers WHERE id = ?");
    $editDriver->execute([(int)$_GET['edit']]);
    $editDriver = $editDriver->fetch();
}

// List all drivers
$drivers = $db->query("SELECT * FROM drivers ORDER BY created_at DESC")->fetchAll();

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
                    <th>Registered</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($drivers)): ?>
                <tr><td colspan="7" class="empty">No drivers registered yet.</td></tr>
                <?php else: ?>
                <?php foreach ($drivers as $d): ?>
                <tr>
                    <td><?= $d['id'] ?></td>
                    <td><?= htmlspecialchars($d['full_name']) ?></td>
                    <td><?= htmlspecialchars($d['phone']) ?></td>
                    <td><?= htmlspecialchars($d['email'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($d['license_number']) ?></td>
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
