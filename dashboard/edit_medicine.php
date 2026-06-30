<?php

declare(strict_types=1);

require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$fullName = $_SESSION['full_name'] ?? 'User';
$currentPage = basename($_SERVER['PHP_SELF']);
$medicineId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($medicineId <= 0) {
    header('Location: medicine.php?message=invalid');
    exit;
}

$allowedTypes = ['Medicine', 'Vitamin'];
$allowedUnits = ['Tablet', 'Capsule', 'Sachet', 'Bottle', 'Milliliter', 'Piece', 'Other'];
$allowedDays = ['Daily', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$error = '';

$stmt = $pdo->prepare('SELECT * FROM medicines WHERE id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$medicineId, $userId]);
$medicine = $stmt->fetch();

if (!$medicine) {
    header('Location: medicine.php?message=not_found');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $medicineType = trim($_POST['medicine_type'] ?? '');
    $medicineName = trim($_POST['medicine_name'] ?? '');
    $dosage = trim($_POST['dosage'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
    $quantityUnit = trim($_POST['quantity_unit'] ?? '');
    $lowStockLevel = filter_input(INPUT_POST, 'low_stock_level', FILTER_VALIDATE_INT);
    $scheduleTime = trim($_POST['schedule_time'] ?? '');
    $days = $_POST['schedule_days'] ?? [];
    $days = is_array($days) ? array_values(array_unique(array_map('trim', $days))) : [];

    if ($medicineType === '' || $medicineName === '' || $dosage === '' || $quantityUnit === '' || $scheduleTime === '' || !$days) {
        $error = 'Please complete all required fields.';
    } elseif (!in_array($medicineType, $allowedTypes, true) || !in_array($quantityUnit, $allowedUnits, true)) {
        $error = 'Please select valid values.';
    } elseif ($quantity === false || $quantity === null || $quantity < 0 || $lowStockLevel === false || $lowStockLevel === null || $lowStockLevel < 0) {
        $error = 'Quantity values must be zero or greater.';
    } else {
        foreach ($days as $day) {
            if (!in_array($day, $allowedDays, true)) {
                $error = 'One or more schedule days are invalid.';
                break;
            }
        }
    }

    if ($error === '') {
        if (in_array('Daily', $days, true)) {
            $days = ['Daily'];
        }

        $update = $pdo->prepare(
            'UPDATE medicines SET
                medicine_name = ?, medicine_type = ?, dosage = ?, description = ?,
                quantity = ?, quantity_unit = ?, low_stock_level = ?,
                schedule_time = ?, schedule_days = ?
             WHERE id = ? AND user_id = ?'
        );
        $update->execute([
            $medicineName, $medicineType, $dosage,
            $description !== '' ? $description : null,
            $quantity, $quantityUnit, $lowStockLevel,
            $scheduleTime, implode(', ', $days),
            $medicineId, $userId
        ]);

        header('Location: medicine.php?message=updated');
        exit;
    }

    $medicine = array_merge($medicine, [
        'medicine_type' => $medicineType,
        'medicine_name' => $medicineName,
        'dosage' => $dosage,
        'description' => $description,
        'quantity' => $quantity === false || $quantity === null ? '' : $quantity,
        'quantity_unit' => $quantityUnit,
        'low_stock_level' => $lowStockLevel === false || $lowStockLevel === null ? '' : $lowStockLevel,
        'schedule_time' => $scheduleTime,
        'schedule_days' => implode(', ', $days)
    ]);
}

$selectedDays = array_filter(array_map('trim', explode(',', (string)($medicine['schedule_days'] ?? ''))));
$todayName = date('l');
$todayDate = date('F d, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Medicine or Vitamin | Medicine Tracker</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/SCSA_GROUP5/assets/css/dashboard.css?v=9">
</head>
<body class="dashboard-body">
<div class="dashboard-page">
    <aside class="dashboard-sidebar">
        <div class="sidebar-brand"><div class="brand-icon">+</div><div><h1>Medicine &amp;</h1><p>Vitamin Tracker</p></div></div>
        <nav class="sidebar-navigation">
            <a href="dashboard.php"><span class="nav-icon">⌂</span><span>Dashboard</span></a>
            <a href="medicine.php" class="active"><span class="nav-icon">✚</span><span>My Medicines &amp; Vitamins</span></a>
            <a href="add_medicine.php"><span class="nav-icon">＋</span><span>Add Medicine or Vitamin</span></a>
            <a href="history.php"><span class="nav-icon">◷</span><span>History</span></a>
            <a href="profile.php"><span class="nav-icon">♙</span><span>Profile</span></a>
        </nav>
        <div class="sidebar-bottom"><a href="../auth/logout.php" class="logout-link"><span class="nav-icon">↪</span><span>Log out</span></a></div>
    </aside>
    <main class="dashboard-main">
        <header class="dashboard-topbar">
            <div><p class="topbar-label">Medicine Tracker</p><h2>Edit Medicine or Vitamin</h2></div>
            <div class="topbar-right"><div class="date-display"><span><?= htmlspecialchars($todayName) ?></span><strong><?= htmlspecialchars($todayDate) ?></strong></div><div class="user-avatar"><?= htmlspecialchars(strtoupper(substr($fullName, 0, 1))) ?></div></div>
        </header>
        <div class="dashboard-content">
            <section class="dashboard-card form-card">
                <div class="form-heading"><p class="card-label">Update health item</p><h1>Edit Medicine or Vitamin</h1><p>Change the item details, stock, and schedule.</p></div>
                <?php if ($error !== ''): ?><div class="dashboard-message error-message"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <form method="POST" class="medicine-form">
                    <div class="form-section"><h2>Basic Information</h2>
                        <div class="form-grid two-columns">
                            <div class="form-group"><label>Item type</label><select name="medicine_type" required><?php foreach ($allowedTypes as $type): ?><option value="<?= htmlspecialchars($type) ?>" <?= ($medicine['medicine_type'] ?? '') === $type ? 'selected' : '' ?>><?= htmlspecialchars($type) ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Name</label><input type="text" name="medicine_name" value="<?= htmlspecialchars((string)$medicine['medicine_name']) ?>" required></div>
                        </div>
                        <div class="form-grid two-columns">
                            <div class="form-group"><label>Dosage</label><input type="text" name="dosage" value="<?= htmlspecialchars((string)$medicine['dosage']) ?>" required></div>
                            <div class="form-group"><label>Description <span>Optional</span></label><input type="text" name="description" value="<?= htmlspecialchars((string)($medicine['description'] ?? '')) ?>"></div>
                        </div>
                    </div>
                    <div class="form-section"><h2>Quantity and Stock</h2>
                        <div class="form-grid three-columns">
                            <div class="form-group"><label>Current quantity</label><input type="number" min="0" name="quantity" value="<?= htmlspecialchars((string)$medicine['quantity']) ?>" required></div>
                            <div class="form-group"><label>Quantity unit</label><select name="quantity_unit" required><?php foreach ($allowedUnits as $unit): ?><option value="<?= htmlspecialchars($unit) ?>" <?= ($medicine['quantity_unit'] ?? '') === $unit ? 'selected' : '' ?>><?= htmlspecialchars($unit) ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Low-stock warning</label><input type="number" min="0" name="low_stock_level" value="<?= htmlspecialchars((string)$medicine['low_stock_level']) ?>" required></div>
                        </div>
                    </div>
                    <div class="form-section"><h2>Schedule</h2>
                        <div class="form-group schedule-time-group"><label>Schedule time</label><input type="time" name="schedule_time" value="<?= htmlspecialchars(substr((string)$medicine['schedule_time'], 0, 5)) ?>" required></div>
                        <fieldset class="schedule-days-fieldset"><legend>Schedule days</legend><div class="schedule-options">
                            <?php foreach ($allowedDays as $day): ?><label class="day-option"><input type="checkbox" name="schedule_days[]" value="<?= htmlspecialchars($day) ?>" <?= in_array($day, $selectedDays, true) ? 'checked' : '' ?>><span><?= htmlspecialchars($day) ?></span></label><?php endforeach; ?>
                        </div></fieldset>
                    </div>
                    <div class="form-actions"><a href="medicine.php" class="dashboard-button secondary">Cancel</a><button type="submit" class="dashboard-button primary form-submit">Save Changes</button></div>
                </form>
            </section>
        </div>
    </main>
</div>
</body>
</html>
