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

$today = new DateTimeImmutable('today');
$todayName = $today->format('l');
$todayDate = $today->format('F d, Y');

/*
 * Read the requested calendar month.
 * Expected format: YYYY-MM
 */
$monthInput = trim($_GET['month'] ?? $today->format('Y-m'));

if (!preg_match('/^\d{4}-\d{2}$/', $monthInput)) {
    $monthInput = $today->format('Y-m');
}

try {
    $monthStart = new DateTimeImmutable($monthInput . '-01');
} catch (Exception $exception) {
    $monthStart = new DateTimeImmutable($today->format('Y-m-01'));
}

$monthEnd = $monthStart->modify('last day of this month');
$previousMonth = $monthStart->modify('-1 month')->format('Y-m');
$nextMonth = $monthStart->modify('+1 month')->format('Y-m');

$calendarTitle = $monthStart->format('F Y');
$firstWeekday = (int) $monthStart->format('N'); // Monday = 1
$daysInMonth = (int) $monthEnd->format('j');

/*
 * Retrieve all medicines and vitamins belonging to the user.
 */
$medicines = [];

try {
    $medicineStatement = $pdo->prepare(
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
         WHERE user_id = ?
         ORDER BY schedule_time ASC, medicine_name ASC'
    );

    $medicineStatement->execute([$userId]);
    $medicines = $medicineStatement->fetchAll();
} catch (PDOException $exception) {
    $medicines = [];
}

/*
 * Retrieve all recorded statuses for the displayed month.
 */
$historyRecords = [];

try {
    $historyStatement = $pdo->prepare(
        'SELECT
            medicine_id,
            status,
            confirmed_at
         FROM medication_history
         WHERE user_id = ?
         AND confirmed_at >= ?
         AND confirmed_at < ?
         ORDER BY confirmed_at ASC, id ASC'
    );

    $historyStatement->execute([
        $userId,
        $monthStart->format('Y-m-d 00:00:00'),
        $monthStart->modify('+1 month')->format('Y-m-d 00:00:00')
    ]);

    foreach ($historyStatement->fetchAll() as $record) {
        $recordDate = date(
            'Y-m-d',
            strtotime((string) $record['confirmed_at'])
        );

        $historyRecords[$recordDate][
            (int) $record['medicine_id']
        ] = [
            'status' => (string) $record['status'],
            'confirmed_at' => (string) $record['confirmed_at']
        ];
    }
} catch (PDOException $exception) {
    $historyRecords = [];
}

/*
 * Determine whether a medicine is scheduled on a date.
 */
function medicineIsScheduledOnDate(
    string $scheduleDays,
    DateTimeImmutable $date
): bool {
    $days = array_values(
        array_filter(
            array_map(
                'trim',
                explode(',', $scheduleDays)
            )
        )
    );

    if (in_array('Daily', $days, true)) {
        return true;
    }

    return in_array($date->format('l'), $days, true);
}

/*
 * Friendly quantity-unit text.
 */
function quantityDisplayUnit(
    int $quantity,
    string $unit
): string {
    $unit = trim($unit);

    if ($unit === '') {
        $unit = 'item';
    }

    if ($quantity === 1) {
        return $unit;
    }

    return str_ends_with(strtolower($unit), 's')
        ? $unit
        : $unit . 's';
}

/*
 * Build all calendar and date-detail data in PHP.
 */
$calendarData = [];
$monthTaken = 0;
$monthMissed = 0;
$monthSkipped = 0;
$monthScheduled = 0;

for ($day = 1; $day <= $daysInMonth; $day++) {
    $date = $monthStart->setDate(
        (int) $monthStart->format('Y'),
        (int) $monthStart->format('m'),
        $day
    );

    $dateKey = $date->format('Y-m-d');
    $dateItems = [];

    foreach ($medicines as $medicine) {
        $scheduleDays = (string) (
            $medicine['schedule_days'] ?? ''
        );

        if (!medicineIsScheduledOnDate(
            $scheduleDays,
            $date
        )) {
            continue;
        }

        $medicineId = (int) $medicine['id'];
        $record = $historyRecords[$dateKey][$medicineId] ?? null;

        if ($record) {
            $status = (string) $record['status'];
            $confirmedAt = (string) $record['confirmed_at'];
        } elseif ($date < $today) {
            $status = 'No record';
            $confirmedAt = '';
        } else {
            $status = 'Pending';
            $confirmedAt = '';
        }

        if ($status === 'Taken') {
            $monthTaken++;
        } elseif ($status === 'Missed') {
            $monthMissed++;
        } elseif ($status === 'Skipped') {
            $monthSkipped++;
        }

        $monthScheduled++;

        $scheduleTime = (string) (
            $medicine['schedule_time'] ?? ''
        );

        $formattedTime = $scheduleTime !== ''
            ? date('g:i A', strtotime($scheduleTime))
            : 'No time';

        $quantity = (int) (
            $medicine['quantity'] ?? 0
        );

        $quantityUnit = quantityDisplayUnit(
            $quantity,
            (string) (
                $medicine['quantity_unit'] ?? 'item'
            )
        );

        $dateItems[] = [
            'id' => $medicineId,
            'name' => (string) (
                $medicine['medicine_name'] ?? 'Medicine'
            ),
            'type' => (string) (
                $medicine['medicine_type'] ?? 'Medicine'
            ),
            'dosage' => (string) (
                $medicine['dosage'] ?? 'No dosage'
            ),
            'description' => (string) (
                $medicine['description']
                ?: 'No additional instructions.'
            ),
            'time' => $formattedTime,
            'schedule_days' => $scheduleDays,
            'status' => $status,
            'confirmed_at' => $confirmedAt !== ''
                ? date(
                    'g:i A',
                    strtotime($confirmedAt)
                )
                : '',
            'quantity' => $quantity,
            'quantity_unit' => $quantityUnit
        ];
    }

    $calendarData[$dateKey] = $dateItems;
}

$monthPendingOrUnrecorded = max(
    $monthScheduled - $monthTaken - $monthMissed - $monthSkipped,
    0
);

/*
 * Actual recorded medication history.
 *
 * Unlike the calendar, this list only contains records
 * that were saved in medication_history.
 */
$historySearch = trim($_GET['history_search'] ?? '');
$historyStatus = trim($_GET['history_status'] ?? 'All');

$allowedHistoryStatuses = [
    'All',
    'Taken',
    'Missed',
    'Skipped'
];

if (!in_array(
    $historyStatus,
    $allowedHistoryStatuses,
    true
)) {
    $historyStatus = 'All';
}

$actualHistory = [];

try {
    $historyConditions = [
        'medication_history.user_id = :history_user_id'
    ];

    $historyParameters = [
        'history_user_id' => $userId
    ];

    if ($historyStatus !== 'All') {
        $historyConditions[] =
            'medication_history.status = :history_status';

        $historyParameters['history_status'] =
            $historyStatus;
    }

    if ($historySearch !== '') {
        $historyConditions[] = '(
            medicines.medicine_name LIKE :history_name
            OR medicines.dosage LIKE :history_dosage
            OR medicines.medicine_type LIKE :history_type
        )';

        $historySearchValue =
            '%' . $historySearch . '%';

        $historyParameters['history_name'] =
            $historySearchValue;

        $historyParameters['history_dosage'] =
            $historySearchValue;

        $historyParameters['history_type'] =
            $historySearchValue;
    }

    $actualHistoryStatement = $pdo->prepare(
        'SELECT
            medication_history.id,
            medication_history.status,
            medication_history.scheduled_at,
            medication_history.confirmed_at,
            medicines.medicine_name,
            medicines.medicine_type,
            medicines.dosage,
            medicines.schedule_time,
            medicines.schedule_days
         FROM medication_history
         INNER JOIN medicines
            ON medicines.id =
               medication_history.medicine_id
         WHERE ' .
            implode(' AND ', $historyConditions) .
        ' ORDER BY
            medication_history.confirmed_at DESC,
            medication_history.id DESC
          LIMIT 200'
    );

    $actualHistoryStatement->execute(
        $historyParameters
    );

    $actualHistory =
        $actualHistoryStatement->fetchAll();
} catch (PDOException $exception) {
    $actualHistory = [];
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>History | Medicine Tracker</title>

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
        href="/SCSA_GROUP5/assets/css/dashboard.css?v=16"
    >
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

            <a
                href="dashboard.php"
                class="<?= $currentPage === 'dashboard.php'
                    ? 'active'
                    : '' ?>"
            >
                <span class="nav-icon">⌂</span>
                <span>Dashboard</span>
            </a>

            <a
                href="medicine.php"
                class="<?= $currentPage === 'medicine.php'
                    ? 'active'
                    : '' ?>"
            >
                <span class="nav-icon">✚</span>
                <span>My Medicines &amp; Vitamins</span>
            </a>

            <a
                href="add_medicine.php"
                class="<?= $currentPage === 'add_medicine.php'
                    ? 'active'
                    : '' ?>"
            >
                <span class="nav-icon">＋</span>
                <span>Add Medicine or Vitamin</span>
            </a>

            <a
                href="history.php"
                class="<?= $currentPage === 'history.php'
                    ? 'active'
                    : '' ?>"
            >
                <span class="nav-icon">◷</span>
                <span>History</span>
            </a>

            <a
                href="profile.php"
                class="<?= $currentPage === 'profile.php'
                    ? 'active'
                    : '' ?>"
            >
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

    <main class="dashboard-main">

        <header class="dashboard-topbar">

            <div>
                <p class="topbar-label">
                    Medicine Tracker
                </p>

                <h2>Medication History</h2>
            </div>

            <div class="topbar-right">

                <div class="date-display">
                    <span>
                        <?= htmlspecialchars($todayName) ?>
                    </span>

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

        <div class="dashboard-content">

            <section class="history-page-heading">

                <div>
                    <p class="card-label">
                        Calendar overview
                    </p>

                    <h1>Medication History</h1>

                    <p>
                        Select a date to review scheduled medicines,
                        vitamins, dosage details, and recorded statuses.
                    </p>
                </div>

                <a
                    href="medicine.php"
                    class="dashboard-button secondary"
                >
                    View Medicines &amp; Vitamins
                </a>

            </section>

            <section class="history-summary-grid">

                <article class="history-summary-card">
                    <span class="history-summary-icon scheduled">◷</span>

                    <div>
                        <p>Scheduled entries</p>
                        <strong><?= $monthScheduled ?></strong>
                    </div>
                </article>

                <article class="history-summary-card">
                    <span class="history-summary-icon taken">✓</span>

                    <div>
                        <p>Taken</p>
                        <strong><?= $monthTaken ?></strong>
                    </div>
                </article>

                <article class="history-summary-card">
                    <span class="history-summary-icon missed">!</span>

                    <div>
                        <p>Missed</p>
                        <strong><?= $monthMissed ?></strong>
                    </div>
                </article>

                <article class="history-summary-card">
                    <span class="history-summary-icon pending">…</span>

                    <div>
                        <p>Pending / no record</p>
                        <strong><?= $monthPendingOrUnrecorded ?></strong>
                    </div>
                </article>

            </section>

            <section class="history-layout">

                <article class="dashboard-card calendar-card">

                    <div class="calendar-month-navigation">

                        <a
                            href="history.php?month=<?= htmlspecialchars(
                                $previousMonth
                            ) ?>"
                            class="calendar-navigation-button"
                            aria-label="Previous month"
                        >
                            ←
                        </a>

                        <div>
                            <p class="card-label">
                                Monthly calendar
                            </p>

                            <h2>
                                <?= htmlspecialchars($calendarTitle) ?>
                            </h2>
                        </div>

                        <a
                            href="history.php?month=<?= htmlspecialchars(
                                $nextMonth
                            ) ?>"
                            class="calendar-navigation-button"
                            aria-label="Next month"
                        >
                            →
                        </a>

                    </div>

                    <div class="calendar-weekdays">
                        <span>Mon</span>
                        <span>Tue</span>
                        <span>Wed</span>
                        <span>Thu</span>
                        <span>Fri</span>
                        <span>Sat</span>
                        <span>Sun</span>
                    </div>

                    <div class="medication-calendar">

                        <?php for (
                            $blank = 1;
                            $blank < $firstWeekday;
                            $blank++
                        ): ?>
                            <div
                                class="calendar-day calendar-day-empty"
                                aria-hidden="true"
                            ></div>
                        <?php endfor; ?>

                        <?php for (
                            $day = 1;
                            $day <= $daysInMonth;
                            $day++
                        ): ?>

                            <?php
                            $date = $monthStart->setDate(
                                (int) $monthStart->format('Y'),
                                (int) $monthStart->format('m'),
                                $day
                            );

                            $dateKey = $date->format('Y-m-d');
                            $dateItems = $calendarData[$dateKey] ?? [];

                            $isToday =
                                $dateKey === $today->format('Y-m-d');

                            $takenCount = 0;
                            $missedCount = 0;
                            $skippedCount = 0;

                            foreach ($dateItems as $dateItem) {
                                if ($dateItem['status'] === 'Taken') {
                                    $takenCount++;
                                } elseif (
                                    $dateItem['status'] === 'Missed'
                                ) {
                                    $missedCount++;
                                } elseif (
                                    $dateItem['status'] === 'Skipped'
                                ) {
                                    $skippedCount++;
                                }
                            }
                            ?>

                            <button
                                type="button"
                                class="calendar-day <?= $isToday
                                    ? 'today'
                                    : '' ?> <?= !empty($dateItems)
                                        ? 'has-items'
                                        : '' ?>"
                                data-date="<?= htmlspecialchars($dateKey) ?>"
                            >

                                <span class="calendar-day-number">
                                    <?= $day ?>
                                </span>

                                <div class="calendar-day-items">

                                    <?php foreach (
                                        array_slice($dateItems, 0, 3)
                                        as $dateItem
                                    ): ?>
                                        <?php
                                        $statusSlug = strtolower(
                                            str_replace(
                                                ' ',
                                                '-',
                                                $dateItem['status']
                                            )
                                        );
                                        ?>

                                        <span
                                            class="calendar-medicine-pill <?= htmlspecialchars(
                                                $statusSlug
                                            ) ?>"
                                        >
                                            <?= htmlspecialchars(
                                                $dateItem['name']
                                            ) ?>
                                        </span>
                                    <?php endforeach; ?>

                                    <?php if (
                                        count($dateItems) > 3
                                    ): ?>
                                        <span class="calendar-more-items">
                                            +<?= count($dateItems) - 3 ?>
                                            more
                                        </span>
                                    <?php endif; ?>

                                </div>

                                <?php if (!empty($dateItems)): ?>
                                    <div class="calendar-day-statuses">
                                        <?php if ($takenCount > 0): ?>
                                            <span class="taken">
                                                <?= $takenCount ?> taken
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($missedCount > 0): ?>
                                            <span class="missed">
                                                <?= $missedCount ?> missed
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($skippedCount > 0): ?>
                                            <span class="skipped">
                                                <?= $skippedCount ?> skipped
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                            </button>

                        <?php endfor; ?>

                    </div>

                    <div class="calendar-legend">

                        <span>
                            <i class="legend-dot taken"></i>
                            Taken
                        </span>

                        <span>
                            <i class="legend-dot missed"></i>
                            Missed
                        </span>

                        <span>
                            <i class="legend-dot skipped"></i>
                            Skipped
                        </span>

                        <span>
                            <i class="legend-dot pending"></i>
                            Pending / no record
                        </span>

                    </div>

                </article>

                <aside class="dashboard-card history-details-panel">

                    <div class="history-details-heading">
                        <p class="card-label">
                            Selected date
                        </p>

                        <h2 id="selected-date-title">
                            Select a calendar date
                        </h2>

                        <p id="selected-date-subtitle">
                            Medicine and vitamin details will appear here.
                        </p>
                    </div>

                    <div
                        id="selected-date-details"
                        class="history-date-details"
                    >
                        <div class="history-details-empty">
                            <div>◷</div>

                            <p>
                                Click any date in the calendar to view
                                its scheduled medicines and statuses.
                            </p>
                        </div>
                    </div>

                </aside>

            </section>


            <section class="dashboard-card actual-history-card">

                <div class="actual-history-heading">

                    <div>
                        <p class="card-label">
                            Recorded activity
                        </p>

                        <h2>Medication Activity History</h2>

                        <p>
                            This list shows the actual medicine and
                            vitamin statuses saved by the user.
                        </p>
                    </div>

                    <span class="actual-history-count">
                        <?= count($actualHistory) ?> records
                    </span>

                </div>

                <form
                    method="GET"
                    action="history.php"
                    class="actual-history-filters"
                >

                    <input
                        type="hidden"
                        name="month"
                        value="<?= htmlspecialchars(
                            $monthInput,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                    <div class="actual-history-search">

                        <span>⌕</span>

                        <input
                            type="search"
                            name="history_search"
                            placeholder="Search medicine, vitamin, dosage, or type..."
                            value="<?= htmlspecialchars(
                                $historySearch,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >

                    </div>

                    <select name="history_status">
                        <?php foreach (
                            $allowedHistoryStatuses as $statusOption
                        ): ?>
                            <option
                                value="<?= htmlspecialchars(
                                    $statusOption
                                ) ?>"
                                <?= $historyStatus === $statusOption
                                    ? 'selected'
                                    : '' ?>
                            >
                                <?= htmlspecialchars($statusOption) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button
                        type="submit"
                        class="dashboard-button primary history-filter-button"
                    >
                        Filter
                    </button>

                    <?php if (
                        $historySearch !== '' ||
                        $historyStatus !== 'All'
                    ): ?>
                        <a
                            href="history.php?month=<?= htmlspecialchars(
                                $monthInput
                            ) ?>"
                            class="dashboard-button secondary"
                        >
                            Clear
                        </a>
                    <?php endif; ?>

                </form>

                <?php if (empty($actualHistory)): ?>

                    <div class="actual-history-empty">

                        <div>◷</div>

                        <h3>No recorded medication history</h3>

                        <p>
                            Taken, Missed, and Skipped records will
                            appear here after a status is saved.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="actual-history-table-wrap">

                        <table class="actual-history-table">

                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Medicine or Vitamin</th>
                                    <th>Type</th>
                                    <th>Dosage</th>
                                    <th>Schedule</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach (
                                    $actualHistory as $historyItem
                                ): ?>

                                    <?php
                                    $confirmedAt =
                                        (string) (
                                            $historyItem[
                                                'confirmed_at'
                                            ]
                                            ?? ''
                                        );

                                    $recordDate =
                                        $confirmedAt !== ''
                                        ? date(
                                            'M d, Y',
                                            strtotime($confirmedAt)
                                        )
                                        : 'Unknown date';

                                    $recordTime =
                                        $confirmedAt !== ''
                                        ? date(
                                            'g:i A',
                                            strtotime($confirmedAt)
                                        )
                                        : '—';

                                    $scheduledTime =
                                        (string) (
                                            $historyItem[
                                                'schedule_time'
                                            ]
                                            ?? ''
                                        );

                                    $formattedScheduleTime =
                                        $scheduledTime !== ''
                                        ? date(
                                            'g:i A',
                                            strtotime(
                                                $scheduledTime
                                            )
                                        )
                                        : 'No time';

                                    $recordStatus =
                                        (string) (
                                            $historyItem['status']
                                            ?? 'Unknown'
                                        );

                                    $statusClass =
                                        strtolower($recordStatus);
                                    ?>

                                    <tr>

                                        <td>
                                            <strong>
                                                <?= htmlspecialchars(
                                                    $recordDate
                                                ) ?>
                                            </strong>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                $recordTime
                                            ) ?>
                                        </td>

                                        <td>
                                            <div class="history-table-item">

                                                <span>
                                                    <?= htmlspecialchars(
                                                        strtoupper(
                                                            substr(
                                                                (string) (
                                                                    $historyItem[
                                                                        'medicine_name'
                                                                    ]
                                                                    ?? 'M'
                                                                ),
                                                                0,
                                                                1
                                                            )
                                                        )
                                                    ) ?>
                                                </span>

                                                <strong>
                                                    <?= htmlspecialchars(
                                                        (string) (
                                                            $historyItem[
                                                                'medicine_name'
                                                            ]
                                                            ?? 'Medicine'
                                                        )
                                                    ) ?>
                                                </strong>

                                            </div>
                                        </td>

                                        <td>
                                            <span
                                                class="history-type-badge <?= strtolower(
                                                    (string) (
                                                        $historyItem[
                                                            'medicine_type'
                                                        ]
                                                        ?? 'Medicine'
                                                    )
                                                ) ?>"
                                            >
                                                <?= htmlspecialchars(
                                                    (string) (
                                                        $historyItem[
                                                            'medicine_type'
                                                        ]
                                                        ?? 'Medicine'
                                                    )
                                                ) ?>
                                            </span>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                (string) (
                                                    $historyItem[
                                                        'dosage'
                                                    ]
                                                    ?? 'No dosage'
                                                )
                                            ) ?>
                                        </td>

                                        <td>
                                            <div class="history-schedule-cell">
                                                <strong>
                                                    <?= htmlspecialchars(
                                                        $formattedScheduleTime
                                                    ) ?>
                                                </strong>

                                                <small>
                                                    <?= htmlspecialchars(
                                                        (string) (
                                                            $historyItem[
                                                                'schedule_days'
                                                            ]
                                                            ?? ''
                                                        )
                                                    ) ?>
                                                </small>
                                            </div>
                                        </td>

                                        <td>
                                            <span
                                                class="history-table-status <?= htmlspecialchars(
                                                    $statusClass
                                                ) ?>"
                                            >
                                                <?= htmlspecialchars(
                                                    $recordStatus
                                                ) ?>
                                            </span>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </section>

        </div>

    </main>

</div>

<script>
const calendarData = <?= json_encode(
    $calendarData,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_AMP |
    JSON_HEX_QUOT
) ?>;

const selectedDateTitle =
    document.getElementById('selected-date-title');

const selectedDateSubtitle =
    document.getElementById('selected-date-subtitle');

const selectedDateDetails =
    document.getElementById('selected-date-details');

const calendarButtons =
    document.querySelectorAll('.calendar-day[data-date]');

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function statusClass(status) {
    return String(status)
        .toLowerCase()
        .replaceAll(' ', '-');
}

function formatSelectedDate(dateString) {
    const date = new Date(dateString + 'T00:00:00');

    return date.toLocaleDateString(
        undefined,
        {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
            year: 'numeric'
        }
    );
}

function showDateDetails(dateString) {
    calendarButtons.forEach((button) => {
        button.classList.toggle(
            'selected',
            button.dataset.date === dateString
        );
    });

    const items = calendarData[dateString] || [];

    selectedDateTitle.textContent =
        formatSelectedDate(dateString);

    selectedDateSubtitle.textContent =
        items.length === 1
            ? '1 medicine or vitamin scheduled'
            : `${items.length} medicines or vitamins scheduled`;

    if (items.length === 0) {
        selectedDateDetails.innerHTML = `
            <div class="history-details-empty">
                <div>＋</div>
                <p>No medicines or vitamins are scheduled on this date.</p>
            </div>
        `;

        return;
    }

    selectedDateDetails.innerHTML = items
        .map((item) => {
            const confirmedLine = item.confirmed_at
                ? `
                    <span>
                        Confirmed at ${escapeHtml(item.confirmed_at)}
                    </span>
                `
                : '';

            return `
                <article class="history-detail-item">
                    <div class="history-detail-top">
                        <div class="history-detail-identity">
                            <span class="history-detail-avatar">
                                ${escapeHtml(item.name.charAt(0).toUpperCase())}
                            </span>

                            <div>
                                <small>${escapeHtml(item.type)}</small>
                                <h3>${escapeHtml(item.name)}</h3>
                                <p>${escapeHtml(item.dosage)}</p>
                            </div>
                        </div>

                        <span class="history-detail-status ${statusClass(item.status)}">
                            ${escapeHtml(item.status)}
                        </span>
                    </div>

                    <div class="history-detail-meta">
                        <span>
                            <strong>Time:</strong>
                            ${escapeHtml(item.time)}
                        </span>

                        <span>
                            <strong>Schedule:</strong>
                            ${escapeHtml(item.schedule_days)}
                        </span>

                        <span>
                            <strong>Supply:</strong>
                            ${escapeHtml(item.quantity)}
                            ${escapeHtml(item.quantity_unit)}
                        </span>

                        ${confirmedLine}
                    </div>

                    <div class="history-detail-instructions">
                        <small>Instructions</small>
                        <p>${escapeHtml(item.description)}</p>
                    </div>
                </article>
            `;
        })
        .join('');
}

calendarButtons.forEach((button) => {
    button.addEventListener('click', () => {
        showDateDetails(button.dataset.date);
    });
});

/*
 * Open today's details automatically when the
 * displayed month contains today.
 */
const todayButton = document.querySelector(
    '.calendar-day.today[data-date]'
);

if (todayButton) {
    showDateDetails(todayButton.dataset.date);
}
</script>

</body>
</html>
