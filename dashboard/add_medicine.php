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

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $medicineType = trim($_POST['medicine_type'] ?? '');
    $medicineName = trim($_POST['medicine_name'] ?? '');
    $dosage = trim($_POST['dosage'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $quantity = filter_input(
        INPUT_POST,
        'quantity',
        FILTER_VALIDATE_INT
    );

    $quantityUnit = trim($_POST['quantity_unit'] ?? '');

    $lowStockLevel = filter_input(
        INPUT_POST,
        'low_stock_level',
        FILTER_VALIDATE_INT
    );

    $scheduleTime = trim($_POST['schedule_time'] ?? '');

    $scheduleDaysInput = $_POST['schedule_days'] ?? [];
    $scheduleDays = '';

    if (!is_array($scheduleDaysInput)) {
        $scheduleDaysInput = [];
    }

    $scheduleDaysInput = array_values(
        array_unique(
            array_filter(
                array_map(
                    static fn ($day): string =>
                        trim((string) $day),
                    $scheduleDaysInput
                )
            )
        )
    );

    /*
     * Daily already covers every day of the week.
     * Keep it exclusive even if JavaScript is bypassed.
     */
    if (in_array('Daily', $scheduleDaysInput, true)) {
        $scheduleDaysInput = ['Daily'];
    }

    $scheduleDays = implode(', ', $scheduleDaysInput);

    $allowedTypes = [
        'Medicine',
        'Vitamin'
    ];

    $allowedUnits = [
        'Tablet',
        'Capsule',
        'Sachet',
        'Bottle',
        'Milliliter',
        'Piece',
        'Other'
    ];

    $allowedDays = [
        'Daily',
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
        'Sunday'
    ];

    if (
        $medicineType === '' ||
        $medicineName === '' ||
        $dosage === '' ||
        $quantityUnit === '' ||
        $scheduleTime === '' ||
        $scheduleDays === ''
    ) {
        $error = 'Please complete all required fields.';
    } elseif (!in_array($medicineType, $allowedTypes, true)) {
        $error = 'Please select a valid item type.';
    } elseif (!in_array($quantityUnit, $allowedUnits, true)) {
        $error = 'Please select a valid quantity unit.';
    } elseif ($quantity === false || $quantity === null || $quantity < 0) {
        $error = 'Quantity must be zero or greater.';
    } elseif (
        $lowStockLevel === false ||
        $lowStockLevel === null ||
        $lowStockLevel < 0
    ) {
        $error = 'Low-stock level must be zero or greater.';
    } else {
        $selectedDays = array_map(
            static fn ($day): string => trim((string) $day),
            $scheduleDaysInput
        );

        foreach ($selectedDays as $selectedDay) {
            if (!in_array($selectedDay, $allowedDays, true)) {
                $error = 'One or more selected schedule days are invalid.';
                break;
            }
        }
    }

    if ($error === '') {
        try {
            $insertStatement = $pdo->prepare(
                'INSERT INTO medicines (
                    user_id,
                    medicine_name,
                    medicine_type,
                    dosage,
                    description,
                    quantity,
                    quantity_unit,
                    low_stock_level,
                    schedule_time,
                    schedule_days
                )
                VALUES (
                    :user_id,
                    :medicine_name,
                    :medicine_type,
                    :dosage,
                    :description,
                    :quantity,
                    :quantity_unit,
                    :low_stock_level,
                    :schedule_time,
                    :schedule_days
                )'
            );

            $insertStatement->execute([
                'user_id' => $userId,
                'medicine_name' => $medicineName,
                'medicine_type' => $medicineType,
                'dosage' => $dosage,
                'description' => $description !== ''
                    ? $description
                    : null,
                'quantity' => $quantity,
                'quantity_unit' => $quantityUnit,
                'low_stock_level' => $lowStockLevel,
                'schedule_time' => $scheduleTime,
                'schedule_days' => $scheduleDays
            ]);

            header(
                'Location: add_medicine.php?message=added'
            );
            exit;
        } catch (PDOException $exception) {
            $error = 'Unable to add the item. Please try again.';
        }
    }
}

$todayName = date('l');
$todayDate = date('F d, Y');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Add Medicine or Vitamin | Medicine Tracker</title>

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
        href="/SCSA_GROUP5/assets/css/dashboard.css?v=17"
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
                    <span>My Medicines & Vitamins</span>
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

                    <h2>Add Medicine or Vitamin</h2>
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

                <section class="dashboard-card form-card">

                    <div class="form-heading">
                        <p class="card-label">
                            New health item
                        </p>

                        <h1>Add Medicine or Vitamin</h1>

                        <p>
                            Enter the item’s details, available
                            quantity, and schedule.
                        </p>
                    </div>

                    <?php if ($error !== ''): ?>
                        <div class="dashboard-message error-message">
                            <?= htmlspecialchars(
                                $error,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </div>
                    <?php endif; ?>

                    <form
                        method="POST"
                        action=""
                        class="medicine-form"
                    >

                        <div class="form-section">
                            <h2>Basic Information</h2>

                            <div class="form-grid two-columns">

                                <div class="form-group">
                                    <label for="medicine_type">
                                        Item type
                                    </label>

                                    <select
                                        id="medicine_type"
                                        name="medicine_type"
                                        required
                                    >
                                        <option value="">
                                            Select a type
                                        </option>

                                        <option
                                            value="Medicine"
                                            <?= (
                                                $_POST['medicine_type']
                                                ?? ''
                                            ) === 'Medicine'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Medicine
                                        </option>

                                        <option
                                            value="Vitamin"
                                            <?= (
                                                $_POST['medicine_type']
                                                ?? ''
                                            ) === 'Vitamin'
                                                ? 'selected'
                                                : '' ?>
                                        >
                                            Vitamin
                                        </option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="medicine_name">
                                        Name
                                    </label>

                                    <input
                                        type="text"
                                        id="medicine_name"
                                        name="medicine_name"
                                        placeholder="Example: Vitamin C"
                                        value="<?= htmlspecialchars(
                                            $_POST['medicine_name']
                                            ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        required
                                    >
                                </div>

                            </div>

                            <div class="form-grid two-columns">

                                <div class="form-group">
                                    <label for="dosage">
                                        Dosage
                                    </label>

                                    <input
                                        type="text"
                                        id="dosage"
                                        name="dosage"
                                        placeholder="Example: 500 mg or 1 tablet"
                                        value="<?= htmlspecialchars(
                                            $_POST['dosage'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        required
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="description">
                                        Description
                                        <span>Optional</span>
                                    </label>

                                    <input
                                        type="text"
                                        id="description"
                                        name="description"
                                        placeholder="Example: Take after meals"
                                        value="<?= htmlspecialchars(
                                            $_POST['description']
                                            ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >
                                </div>

                            </div>
                        </div>

                        <div class="form-section">
                            <h2>Quantity and Stock</h2>

                            <div class="form-grid three-columns">

                                <div class="form-group">
                                    <label for="quantity">
                                        Current quantity
                                    </label>

                                    <input
                                        type="number"
                                        id="quantity"
                                        name="quantity"
                                        min="0"
                                        placeholder="30"
                                        value="<?= htmlspecialchars(
                                            $_POST['quantity'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        required
                                    >
                                </div>

                                <div class="form-group">
                                    <label for="quantity_unit">
                                        Quantity unit
                                    </label>

                                    <select
                                        id="quantity_unit"
                                        name="quantity_unit"
                                        required
                                    >
                                        <option value="">
                                            Select a unit
                                        </option>

                                        <?php
                                        $units = [
                                            'Tablet',
                                            'Capsule',
                                            'Sachet',
                                            'Bottle',
                                            'Milliliter',
                                            'Piece',
                                            'Other'
                                        ];

                                        foreach ($units as $unit):
                                        ?>
                                            <option
                                                value="<?= htmlspecialchars(
                                                    $unit
                                                ) ?>"
                                                <?= (
                                                    $_POST['quantity_unit']
                                                    ?? ''
                                                ) === $unit
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                <?= htmlspecialchars($unit) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="low_stock_level">
                                        Low-stock warning
                                    </label>

                                    <input
                                        type="number"
                                        id="low_stock_level"
                                        name="low_stock_level"
                                        min="0"
                                        placeholder="5"
                                        value="<?= htmlspecialchars(
                                            $_POST['low_stock_level']
                                            ?? '5',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        required
                                    >
                                </div>

                            </div>

                            <p class="field-help">
                                Example: Quantity 30, Unit Tablet,
                                Low-stock warning 5.
                            </p>
                        </div>

                        <div class="form-section">
                            <h2>Schedule</h2>

                            <div class="form-group schedule-time-group">
                                <label for="schedule_time">
                                    Schedule time
                                </label>

                                <input
                                    type="time"
                                    id="schedule_time"
                                    name="schedule_time"
                                    value="<?= htmlspecialchars(
                                        $_POST['schedule_time'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    required
                                >
                            </div>

                            <fieldset class="schedule-days-fieldset">
                                <legend>Schedule days</legend>

                                <div class="schedule-options">

                                    <?php
                                    $days = [
                                        'Daily',
                                        'Monday',
                                        'Tuesday',
                                        'Wednesday',
                                        'Thursday',
                                        'Friday',
                                        'Saturday',
                                        'Sunday'
                                    ];

                                    $previousDays =
                                        $_POST['schedule_days'] ?? [];

                                    foreach ($days as $day):
                                    ?>
                                        <label class="day-option">
                                            <input
                                                type="checkbox"
                                                name="schedule_days[]"
                                                value="<?= htmlspecialchars(
                                                    $day
                                                ) ?>"
                                                class="schedule-day-checkbox <?= $day === 'Daily'
                                                    ? 'daily-checkbox'
                                                    : 'weekday-checkbox' ?>"
                                                <?= in_array(
                                                    $day,
                                                    $previousDays,
                                                    true
                                                )
                                                    ? 'checked'
                                                    : '' ?>
                                            >

                                            <span>
                                                <?= htmlspecialchars($day) ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>

                                </div>

                                <p class="field-help schedule-days-help">
                                    Select Daily for every day, or choose
                                    individual weekdays.
                                </p>
                            </fieldset>
                        </div>

                        <div class="form-actions">

                            <a
                                href="dashboard.php"
                                class="dashboard-button secondary"
                            >
                                Cancel
                            </a>

                            <button
                                type="submit"
                                class="dashboard-button primary form-submit"
                            >
                                Save Medicine or Vitamin
                            </button>

                        </div>

                    </form>

                </section>

            </div>

        </main>

    </div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const dailyCheckbox = document.querySelector(
        '.daily-checkbox'
    );

    const weekdayCheckboxes = Array.from(
        document.querySelectorAll('.weekday-checkbox')
    );

    if (!dailyCheckbox || weekdayCheckboxes.length === 0) {
        return;
    }

    function updateScheduleDayState() {
        const dailySelected = dailyCheckbox.checked;

        weekdayCheckboxes.forEach(function (checkbox) {
            if (dailySelected) {
                checkbox.checked = false;
            }

            checkbox.disabled = dailySelected;

            const option = checkbox.closest('.day-option');

            if (option) {
                option.classList.toggle(
                    'disabled',
                    dailySelected
                );
            }
        });
    }

    dailyCheckbox.addEventListener('change', function () {
        updateScheduleDayState();
    });

    weekdayCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
            if (checkbox.checked) {
                dailyCheckbox.checked = false;
            }

            updateScheduleDayState();
        });
    });

    updateScheduleDayState();
});
</script>

</body>
</html>