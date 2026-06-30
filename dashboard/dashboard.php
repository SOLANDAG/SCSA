<?php

declare(strict_types=1);

require_once '../config.php';

/*
 * Prevent users from opening the dashboard
 * without logging in.
 */
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$fullName = $_SESSION['full_name'] ?? 'User';

/*
 * Default dashboard values.
 *
 * These remain at zero when there are no medicines yet.
 */
$totalMedicines = 0;
$scheduledToday = 0;
$takenToday = 0;
$lowStock = 0;
$todayMedicines = [];

$todayName = date('l');
$todayDate = date('F d, Y');

/*
 * Count all medicines belonging to the logged-in user.
 */
try {
    $totalStatement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM medicines
         WHERE user_id = ?'
    );

    $totalStatement->execute([$userId]);
    $totalMedicines = (int) $totalStatement->fetchColumn();
} catch (PDOException $exception) {
    $totalMedicines = 0;
}

/*
 * Count medicines with low stock.
 */
try {
    $lowStockStatement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM medicines
         WHERE user_id = ?
         AND quantity <= low_stock_level'
    );

    $lowStockStatement->execute([$userId]);
    $lowStock = (int) $lowStockStatement->fetchColumn();
} catch (PDOException $exception) {
    $lowStock = 0;
}

/*
 * Get medicines scheduled today.
 *
 * This supports schedule_days values such as:
 * Daily
 * Monday
 * Monday, Wednesday, Friday
 */
try {
    $todayStatement = $pdo->prepare(
        'SELECT
            id,
            medicine_name,
            medicine_type,
            dosage,
            quantity,
            low_stock_level,
            schedule_time,
            schedule_days
         FROM medicines
         WHERE user_id = ?
         AND (
             schedule_days = "Daily"
             OR schedule_days LIKE ?
         )
         ORDER BY schedule_time ASC'
    );

    $todayStatement->execute([
        $userId,
        '%' . $todayName . '%'
    ]);

    $todayMedicines = $todayStatement->fetchAll();
    $scheduledToday = count($todayMedicines);
} catch (PDOException $exception) {
    $todayMedicines = [];
    $scheduledToday = 0;
}

/*
 * Count medicines marked as taken today.
 */
try {
    $takenStatement = $pdo->prepare(
        'SELECT COUNT(*)
         FROM medication_history
         WHERE user_id = ?
         AND status = "Taken"
         AND DATE(created_at) = CURDATE()'
    );

    $takenStatement->execute([$userId]);
    $takenToday = (int) $takenStatement->fetchColumn();
} catch (PDOException $exception) {
    $takenToday = 0;
}

/*
 * Calculate how many scheduled medicines
 * are still pending today.
 */
$pendingToday = max(
    $scheduledToday - $takenToday,
    0
);

/*
 * Used to highlight the active sidebar page.
 */
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Dashboard | Medicine Tracker</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="/SCSA_GROUP5/assets/css/dashboard.css?v=1"
    >
</head>

<body class="dashboard-body">

    <div class="dashboard-page">

        <!-- SIDEBAR -->
        <aside class="dashboard-sidebar">

            <div class="sidebar-brand">
                <div class="brand-icon">
                    +
                </div>

                <div>
                    <h1>Medicine</h1>
                    <p>Tracker</p>
                </div>
            </div>

            <nav class="sidebar-navigation">

                <a
                    href="dashboard.php"
                    class="<?= $currentPage === 'dashboard.php'
                        ? 'active'
                        : '' ?>"
                >
                    <span class="nav-icon">⌂</span>
                    <span>Dashboard</span>
                </a>

                <a href="medicines.php">
                    <span class="nav-icon">✚</span>
                    <span>My Medicines & Vitamins</span>
                </a>

                <a href="add_medicine.php">
                    <span class="nav-icon">＋</span>
                    <span>Add Medicine or Vitamin</span>
                </a>

                <a href="history.php">
                    <span class="nav-icon">◷</span>
                    <span>History</span>
                </a>

                <a href="profile.php">
                    <span class="nav-icon">♙</span>
                    <span>Profile</span>
                </a>

            </nav>

            <div class="sidebar-bottom">
                <a
                    href="../auth/logout.php"
                    class="logout-link"
                >
                    <span class="nav-icon">↪</span>
                    <span>Log out</span>
                </a>
            </div>

        </aside>

        <!-- MAIN DASHBOARD AREA -->
        <main class="dashboard-main">

            <!-- TOP BAR -->
            <header class="dashboard-topbar">

                <div>
                    <p class="topbar-label">
                        Medicine Tracker
                    </p>

                    <h2>Dashboard</h2>
                </div>

                <div class="topbar-right">

                    <div class="date-display">
                        <span><?= htmlspecialchars($todayName) ?></span>

                        <strong>
                            <?= htmlspecialchars($todayDate) ?>
                        </strong>
                    </div>

                    <div class="user-avatar">
                        <?= htmlspecialchars(
                            strtoupper(
                                substr($fullName, 0, 1)
                            )
                        ) ?>
                    </div>

                </div>

            </header>

            <!-- DASHBOARD CONTENT -->
            <div class="dashboard-content">

                <!-- WELCOME CARD -->
                <section class="welcome-card">

                    <div class="welcome-content">
                        <p class="welcome-label">
                            Welcome back
                        </p>

                        <h1>
                            Hello,
                            <?= htmlspecialchars($fullName) ?>!
                        </h1>

                        <p>
                            Keep track of your medicines, vitamins,
                            schedules, and remaining supplies.
                        </p>

                        <div class="welcome-actions">
                            <a
                                href="add_medicine.php"
                                class="dashboard-button primary"
                            >
                                Add Medicine or Vitamin
                            </a>

                            <a
                                href="medicines.php"
                                class="dashboard-button secondary"
                            >
                                View Medicine or Vitamin
                            </a>
                        </div>
                    </div>

                    <div
                        class="welcome-decoration"
                        aria-hidden="true"
                    >
                        <div class="medicine-bubble large">
                            +
                        </div>

                        <div class="medicine-bubble small">
                            Rx
                        </div>
                    </div>

                </section>

                <!-- STATISTICS -->
                <section class="statistics-grid">

                    <article class="stat-card total-card">
                        <div class="stat-icon">✚</div>

                        <div>
                            <p>Total medicines</p>
                            <h3><?= $totalMedicines ?></h3>
                        </div>
                    </article>

                    <article class="stat-card schedule-card">
                        <div class="stat-icon">◷</div>

                        <div>
                            <p>Scheduled today</p>
                            <h3><?= $scheduledToday ?></h3>
                        </div>
                    </article>

                    <article class="stat-card taken-card">
                        <div class="stat-icon">✓</div>

                        <div>
                            <p>Taken today</p>
                            <h3><?= $takenToday ?></h3>
                        </div>
                    </article>

                    <article class="stat-card stock-card">
                        <div class="stat-icon">!</div>

                        <div>
                            <p>Low stock</p>
                            <h3><?= $lowStock ?></h3>
                        </div>
                    </article>

                </section>

                <!-- TWO-COLUMN CONTENT -->
                <section class="dashboard-grid">

                    <!-- LEFT COLUMN -->
                    <div class="dashboard-left-column">

                        <article class="dashboard-card schedule-panel">

                            <div class="card-heading">
                                <div>
                                    <p class="card-label">
                                        <?= htmlspecialchars($todayName) ?>
                                    </p>

                                    <h2>Today's Schedule</h2>
                                </div>

                                <span class="schedule-count">
                                    <?= $scheduledToday ?> scheduled
                                </span>
                            </div>

                            <?php if (empty($todayMedicines)): ?>

                                <div class="empty-state">
                                    <div class="empty-icon">＋</div>

                                    <h3>No medicines scheduled</h3>

                                    <p>
                                        Add your first medicine and
                                        choose its schedule.
                                    </p>

                                    <a
                                        href="add_medicine.php"
                                        class="dashboard-button primary"
                                    >
                                        Add Medicine or Vitamin
                                    </a>
                                </div>

                            <?php else: ?>

                                <div class="medicine-list">

                                    <?php foreach ($todayMedicines as $medicine): ?>

                                        <?php
                                        $medicineName =
                                            $medicine['medicine_name']
                                            ?? 'Medicine';

                                        $medicineInitial =
                                            strtoupper(
                                                substr(
                                                    $medicineName,
                                                    0,
                                                    1
                                                )
                                            );

                                        $scheduleTime =
                                            $medicine['schedule_time']
                                            ?? '';

                                        $formattedTime =
                                            $scheduleTime !== ''
                                            ? date(
                                                'g:i A',
                                                strtotime($scheduleTime)
                                            )
                                            : 'No time';

                                        $quantity =
                                            (int) (
                                                $medicine['quantity']
                                                ?? 0
                                            );

                                        $lowStockLevel =
                                            (int) (
                                                $medicine['low_stock_level']
                                                ?? 0
                                            );

                                        $isLowStock =
                                            $quantity <= $lowStockLevel;
                                        ?>

                                        <div class="medicine-item">

                                            <div class="medicine-avatar">
                                                <?= htmlspecialchars(
                                                    $medicineInitial
                                                ) ?>
                                            </div>

                                            <div class="medicine-details">
                                                <h3>
                                                    <?= htmlspecialchars(
                                                        $medicineName
                                                    ) ?>
                                                </h3>

                                                <p>
                                                    <?= htmlspecialchars(
                                                        $medicine['dosage']
                                                        ?? 'No dosage'
                                                    ) ?>

                                                    <span>•</span>

                                                    <?= htmlspecialchars(
                                                        $formattedTime
                                                    ) ?>
                                                </p>
                                            </div>

                                            <div class="medicine-quantity">
                                                <span
                                                    class="<?= $isLowStock
                                                        ? 'stock-warning'
                                                        : 'stock-normal' ?>"
                                                >
                                                    <?= $quantity ?>
                                                    left
                                                </span>
                                            </div>

                                            <a
                                                href="mark_status.php?id=<?= (int) $medicine['id'] ?>&status=Taken"
                                                class="take-button"
                                            >
                                                Mark taken
                                            </a>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            <?php endif; ?>

                        </article>

                    </div>

                    <!-- RIGHT COLUMN -->
                    <div class="dashboard-right-column">

                        <article class="dashboard-card summary-card">

                            <div class="card-heading">
                                <div>
                                    <p class="card-label">
                                        Live update
                                    </p>

                                    <h2>Today</h2>
                                </div>
                            </div>

                            <div class="live-clock">
                                <span id="live-time">
                                    --:--:--
                                </span>

                                <p>
                                    <?= htmlspecialchars($todayDate) ?>
                                </p>
                            </div>

                            <div class="summary-list">

                                <div>
                                    <span>Scheduled</span>
                                    <strong><?= $scheduledToday ?></strong>
                                </div>

                                <div>
                                    <span>Taken</span>
                                    <strong><?= $takenToday ?></strong>
                                </div>

                                <div>
                                    <span>Pending</span>
                                    <strong><?= $pendingToday ?></strong>
                                </div>

                            </div>

                        </article>

                        <article class="dashboard-card progress-card">

                            <div class="card-heading">
                                <div>
                                    <p class="card-label">
                                        Daily progress
                                    </p>

                                    <h2>Completion</h2>
                                </div>
                            </div>

                            <?php
                            $completionRate = $scheduledToday > 0
                                ? min(
                                    (int) round(
                                        ($takenToday / $scheduledToday)
                                        * 100
                                    ),
                                    100
                                )
                                : 0;
                            ?>

                            <div class="progress-number">
                                <?= $completionRate ?>%
                            </div>

                            <div class="progress-track">
                                <div
                                    class="progress-fill"
                                    style="width: <?= $completionRate ?>%;"
                                ></div>
                            </div>

                            <p class="progress-description">
                                <?= $takenToday ?> of
                                <?= $scheduledToday ?>
                                scheduled medicines taken.
                            </p>

                        </article>

                        <article class="dashboard-card quick-actions-card">

                            <div class="card-heading">
                                <div>
                                    <p class="card-label">
                                        Shortcuts
                                    </p>

                                    <h2>Quick Actions</h2>
                                </div>
                            </div>

                            <div class="quick-action-list">

                                <a href="add_medicine.php">
                                    <span>＋</span>
                                    Add medicine
                                </a>

                                <a href="medicines.php">
                                    <span>✚</span>
                                    Manage medicines
                                </a>

                                <a href="history.php">
                                    <span>◷</span>
                                    View history
                                </a>

                            </div>

                        </article>

                    </div>

                </section>

            </div>

        </main>

    </div>

    <script>
        function updateClock() {
            const now = new Date();

            const time = now.toLocaleTimeString(
                [],
                {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                }
            );

            const clock = document.getElementById('live-time');

            if (clock) {
                clock.textContent = time;
            }
        }

        updateClock();
        setInterval(updateClock, 1000);
    </script>

</body>
</html>