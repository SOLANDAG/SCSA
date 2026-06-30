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

$todayName = date('l');
$todayDate = date('F d, Y');
$search = trim($_GET['search'] ?? '');
$typeFilter = trim($_GET['type'] ?? 'All');
$allowedFilters = ['All', 'Medicine', 'Vitamin'];

if (!in_array($typeFilter, $allowedFilters, true)) {
    $typeFilter = 'All';
}

$totalItems = 0;
$totalMedicines = 0;
$totalVitamins = 0;
$totalLowStock = 0;

try {
    $summaryStatement = $pdo->prepare(
        'SELECT
            COUNT(*) AS total_items,
            SUM(CASE WHEN medicine_type = "Medicine" THEN 1 ELSE 0 END) AS total_medicines,
            SUM(CASE WHEN medicine_type = "Vitamin" THEN 1 ELSE 0 END) AS total_vitamins,
            SUM(CASE WHEN quantity <= low_stock_level THEN 1 ELSE 0 END) AS total_low_stock
         FROM medicines
         WHERE user_id = ?'
    );

    $summaryStatement->execute([$userId]);
    $summary = $summaryStatement->fetch();

    if ($summary) {
        $totalItems = (int) ($summary['total_items'] ?? 0);
        $totalMedicines = (int) ($summary['total_medicines'] ?? 0);
        $totalVitamins = (int) ($summary['total_vitamins'] ?? 0);
        $totalLowStock = (int) ($summary['total_low_stock'] ?? 0);
    }
} catch (PDOException $exception) {
    $totalItems = 0;
    $totalMedicines = 0;
    $totalVitamins = 0;
    $totalLowStock = 0;
}

$items = [];

try {
    $conditions = ['user_id = :user_id'];
    $parameters = ['user_id' => $userId];

    if ($typeFilter !== 'All') {
        $conditions[] = 'medicine_type = :medicine_type';
        $parameters['medicine_type'] = $typeFilter;
    }

    if ($search !== '') {
        /*
         * Use a different placeholder for every LIKE condition.
         * PDO native prepared statements cannot reuse one named
         * placeholder several times in the same query.
         */
        $conditions[] = '(
            medicine_name LIKE :search_name
            OR dosage LIKE :search_dosage
            OR description LIKE :search_description
            OR schedule_days LIKE :search_schedule
        )';

        $searchValue = '%' . $search . '%';

        $parameters['search_name'] = $searchValue;
        $parameters['search_dosage'] = $searchValue;
        $parameters['search_description'] = $searchValue;
        $parameters['search_schedule'] = $searchValue;
    }

    $itemStatement = $pdo->prepare(
        'SELECT
            id,
            medicine_name,
            medicine_type,
            dosage,
            description,
            quantity,
            quantity_unit,
            low_stock_level,
            schedule_time,
            schedule_days
         FROM medicines
         WHERE ' . implode(' AND ', $conditions) . '
         ORDER BY medicine_type ASC, medicine_name ASC'
    );

    $itemStatement->execute($parameters);
    $items = $itemStatement->fetchAll();
} catch (PDOException $exception) {
    $items = [];
}

$todayStatuses = [];

try {
    $statusStatement = $pdo->prepare(
        'SELECT medicine_id, status
         FROM medication_history
         WHERE user_id = ?
         AND DATE(confirmed_at) = CURDATE()
         ORDER BY id ASC'
    );

    $statusStatement->execute([$userId]);

    foreach ($statusStatement->fetchAll() as $statusRow) {
        $todayStatuses[(int) $statusRow['medicine_id']] = $statusRow['status'];
    }
} catch (PDOException $exception) {
    $todayStatuses = [];
}

function isScheduledToday(string $scheduleDays, string $todayName): bool
{
    $days = array_filter(array_map('trim', explode(',', $scheduleDays)));

    return in_array('Daily', $days, true)
        || in_array($todayName, $days, true);
}

function formatScheduleDays(string $scheduleDays): string
{
    $days = array_filter(array_map('trim', explode(',', $scheduleDays)));

    if (in_array('Daily', $days, true)) {
        return 'Daily';
    }

    return count($days) > 0 ? implode(', ', $days) : 'No schedule days';
}

$message = trim($_GET['message'] ?? '');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Medicines &amp; Vitamins | Medicine Tracker</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/SCSA_GROUP5/assets/css/dashboard.css?v=11">
</head>

<body class="dashboard-body">
<div class="dashboard-page">

    <aside class="dashboard-sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">+</div>
            <div>
                <h1>Medicine &amp;</h1>
                <p>Vitamin Tracker</p>
            </div>
        </div>

        <nav class="sidebar-navigation">
            <a href="dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                <span class="nav-icon">⌂</span><span>Dashboard</span>
            </a>
            <a href="medicine.php" class="<?= $currentPage === 'medicine.php' ? 'active' : '' ?>">
                <span class="nav-icon">✚</span><span>My Medicines &amp; Vitamins</span>
            </a>
            <a href="add_medicine.php" class="<?= $currentPage === 'add_medicine.php' ? 'active' : '' ?>">
                <span class="nav-icon">＋</span><span>Add Medicine or Vitamin</span>
            </a>
            <a href="history.php" class="<?= $currentPage === 'history.php' ? 'active' : '' ?>">
                <span class="nav-icon">◷</span><span>History</span>
            </a>
            <a href="profile.php" class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>">
                <span class="nav-icon">♙</span><span>Profile</span>
            </a>
        </nav>

        <div class="sidebar-bottom">
            <a href="../auth/logout.php" class="logout-link">
                <span class="nav-icon">↪</span><span>Log out</span>
            </a>
        </div>
    </aside>

    <main class="dashboard-main">
        <header class="dashboard-topbar">
            <div>
                <p class="topbar-label">Medicine Tracker</p>
                <h2>My Medicines &amp; Vitamins</h2>
            </div>

            <div class="topbar-right">
                <div class="date-display">
                    <span><?= htmlspecialchars($todayName) ?></span>
                    <strong><?= htmlspecialchars($todayDate) ?></strong>
                </div>
                <div class="user-avatar">
                    <?= htmlspecialchars(strtoupper(substr($fullName, 0, 1))) ?>
                </div>
            </div>
        </header>

        <div class="dashboard-content">

            <?php if ($message === 'deleted'): ?>
                <div class="dashboard-message success-message">Medicine or vitamin deleted successfully.</div>
            <?php elseif ($message === 'updated'): ?>
                <div class="dashboard-message success-message">Medicine or vitamin updated successfully.</div>
            <?php elseif ($message === 'status_updated'): ?>
                <div class="dashboard-message success-message">Today’s status was updated successfully.</div>
            <?php endif; ?>

            <section class="medicine-page-heading">
                <div>
                    <p class="card-label">Complete health inventory</p>
                    <h1>Your Medicines &amp; Vitamins</h1>
                    <p>Review dosages, schedules, supplies, instructions, and today’s status.</p>
                </div>

                <a href="add_medicine.php" class="dashboard-button primary">＋ Add Medicine or Vitamin</a>
            </section>

            <section class="medicine-summary-grid">
                <article class="medicine-summary-card">
                    <span class="summary-icon total">✚</span>
                    <div><p>Total items</p><strong><?= $totalItems ?></strong></div>
                </article>
                <article class="medicine-summary-card">
                    <span class="summary-icon medicine">Rx</span>
                    <div><p>Medicines</p><strong><?= $totalMedicines ?></strong></div>
                </article>
                <article class="medicine-summary-card">
                    <span class="summary-icon vitamin">V</span>
                    <div><p>Vitamins</p><strong><?= $totalVitamins ?></strong></div>
                </article>
                <article class="medicine-summary-card">
                    <span class="summary-icon warning">!</span>
                    <div><p>Low stock</p><strong><?= $totalLowStock ?></strong></div>
                </article>
            </section>

            <section class="dashboard-card medicine-tools-card">
                <form method="GET" action="" class="medicine-filter-form">
                    <div class="medicine-search-wrap">
                        <span>⌕</span>
                        <input
                            type="search"
                            name="search"
                            placeholder="Search medicine, vitamin, dosage, or schedule..."
                            value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="medicine-type-filters">
                        <?php foreach ($allowedFilters as $filter): ?>
                            <label class="medicine-filter-option <?= $typeFilter === $filter ? 'active' : '' ?>">
                                <input
                                    type="radio"
                                    name="type"
                                    value="<?= htmlspecialchars($filter) ?>"
                                    <?= $typeFilter === $filter ? 'checked' : '' ?>
                                    onchange="this.form.submit();"
                                >
                                <span><?= htmlspecialchars($filter) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <button type="submit" class="dashboard-button primary medicine-search-button">Search</button>

                    <?php if ($search !== '' || $typeFilter !== 'All'): ?>
                        <a href="medicine.php" class="dashboard-button secondary">Clear</a>
                    <?php endif; ?>
                </form>
            </section>

            <?php if (empty($items)): ?>
                <section class="dashboard-card empty-state medicine-empty-state">
                    <div class="empty-icon">＋</div>
                    <h3><?= $search !== '' || $typeFilter !== 'All' ? 'No matching items found' : 'No medicines or vitamins yet' ?></h3>
                    <p><?= $search !== '' || $typeFilter !== 'All' ? 'Try another search or clear the current filter.' : 'Add your first medicine or vitamin to start tracking its schedule and supply.' ?></p>
                    <a href="<?= $search !== '' || $typeFilter !== 'All' ? 'medicine.php' : 'add_medicine.php' ?>" class="dashboard-button primary">
                        <?= $search !== '' || $typeFilter !== 'All' ? 'Clear filters' : 'Add Medicine or Vitamin' ?>
                    </a>
                </section>
            <?php else: ?>
                <section class="medicine-card-grid">
                    <?php foreach ($items as $item): ?>
                        <?php
                        $itemId = (int) $item['id'];
                        $itemName = $item['medicine_name'] ?? 'Unnamed item';
                        $itemType = $item['medicine_type'] ?? 'Medicine';
                        $quantity = (int) ($item['quantity'] ?? 0);
                        $lowStockLevel = (int) ($item['low_stock_level'] ?? 0);
                        $isLowStock = $quantity <= $lowStockLevel;
                        $scheduleDays = (string) ($item['schedule_days'] ?? '');
                        $scheduledToday = isScheduledToday($scheduleDays, $todayName);
                        $todayStatus = $todayStatuses[$itemId] ?? '';
                        $isTakenToday = $todayStatus === 'Taken';
                        $scheduleTime = (string) ($item['schedule_time'] ?? '');
                        $formattedTime = $scheduleTime !== '' ? date('g:i A', strtotime($scheduleTime)) : 'No time';
                        $quantityUnit = trim((string) ($item['quantity_unit'] ?? 'item'));
                        $displayUnit = $quantity === 1
                            ? $quantityUnit
                            : (str_ends_with(strtolower($quantityUnit), 's') ? $quantityUnit : $quantityUnit . 's');

                        if ($isTakenToday) {
                            $statusClass = 'taken';
                            $statusText = '✓ Taken today';
                        } elseif ($scheduledToday) {
                            $statusClass = 'pending';
                            $statusText = 'Pending today';
                        } else {
                            $statusClass = 'not-scheduled';
                            $statusText = 'Not scheduled today';
                        }
                        ?>

                        <article class="medicine-management-card">
                            <div class="medicine-card-top">
                                <div class="medicine-card-identity">
                                    <div class="medicine-card-avatar">
                                        <?= htmlspecialchars(strtoupper(substr($itemName, 0, 1))) ?>
                                    </div>

                                    <div>
                                        <div class="medicine-card-badges">
                                            <span class="item-type-badge <?= strtolower($itemType) ?>">
                                                <?= htmlspecialchars($itemType) ?>
                                            </span>
                                            <?php if ($isLowStock): ?>
                                                <span class="low-stock-badge">Low stock</span>
                                            <?php endif; ?>
                                        </div>

                                        <h2><?= htmlspecialchars($itemName) ?></h2>
                                        <p class="medicine-dosage"><?= htmlspecialchars($item['dosage'] ?? 'No dosage provided') ?></p>
                                    </div>
                                </div>

                                <span class="today-status-badge <?= $statusClass ?>">
                                    <?= htmlspecialchars($statusText) ?>
                                </span>
                            </div>

                            <div class="medicine-description">
                                <span>Instructions</span>
                                <p><?= htmlspecialchars($item['description'] ?: 'No additional instructions provided.') ?></p>
                            </div>

                            <div class="medicine-information-grid">
                                <div class="medicine-information-item">
                                    <span class="information-icon">◷</span>
                                    <div><small>Schedule time</small><strong><?= htmlspecialchars($formattedTime) ?></strong></div>
                                </div>
                                <div class="medicine-information-item">
                                    <span class="information-icon">▣</span>
                                    <div><small>Schedule days</small><strong><?= htmlspecialchars(formatScheduleDays($scheduleDays)) ?></strong></div>
                                </div>
                                <div class="medicine-information-item">
                                    <span class="information-icon">#</span>
                                    <div><small>Remaining supply</small><strong class="<?= $isLowStock ? 'information-warning' : '' ?>"><?= $quantity ?> <?= htmlspecialchars($displayUnit) ?></strong></div>
                                </div>
                                <div class="medicine-information-item">
                                    <span class="information-icon">!</span>
                                    <div><small>Low-stock warning</small><strong><?= $lowStockLevel ?> <?= htmlspecialchars($displayUnit) ?></strong></div>
                                </div>
                            </div>

                            <div class="medicine-card-actions">
                                <a href="edit_medicine.php?id=<?= $itemId ?>" class="medicine-action-button edit">Edit</a>
                                <a
                                    href="delete_medicine.php?id=<?= $itemId ?>"
                                    class="medicine-action-button delete"
                                    onclick="return confirm('Delete this medicine or vitamin? This cannot be undone.');"
                                >Delete</a>

                                <?php if ($isTakenToday): ?>
                                    <button type="button" class="medicine-action-button taken" disabled>✓ Taken</button>
                                <?php elseif ($scheduledToday): ?>
                                    <a
                                        href="mark_status.php?id=<?= $itemId ?>&status=Taken&return=medicine.php"
                                        class="medicine-action-button mark"
                                        onclick="return confirm('Mark this medicine or vitamin as taken?');"
                                    >Mark Taken</a>
                                <?php else: ?>
                                    <button type="button" class="medicine-action-button unavailable" disabled>Not Today</button>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
        </div>
    </main>
</div>
</body>
</html>
