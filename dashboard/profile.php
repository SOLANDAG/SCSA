<?php

declare(strict_types=1);

require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$currentPage = basename($_SERVER['PHP_SELF']);

$profileMessage = '';
$profileMessageType = '';

$passwordMessage = '';
$passwordMessageType = '';

$allowedCategories = [
    '',
    'Student',
    'Worker',
    'Student and Worker',
    'Retired',
    'Other'
];

$allowedAccessibilityOptions = [
    '',
    'None',
    'Senior citizen',
    'Person with disability',
    'Mobility assistance',
    'Visual assistance',
    'Hearing assistance',
    'Other',
    'Prefer not to say'
];

/*
 * Load the current user.
 */
try {
    $userStatement = $pdo->prepare(
        'SELECT
            id,
            full_name,
            email,
            password,
            birth_date,
            user_category,
            accessibility_needs,
            created_at
         FROM users
         WHERE id = ?
         LIMIT 1'
    );

    $userStatement->execute([$userId]);
    $user = $userStatement->fetch();
} catch (PDOException $exception) {
    $user = false;
}

if (!$user) {
    session_destroy();

    header('Location: ../auth/login.php');
    exit;
}

/*
 * Update profile information.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['form_action'] ?? '') === 'update_profile'
) {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birthDate = trim($_POST['birth_date'] ?? '');
    $userCategory = trim($_POST['user_category'] ?? '');
    $accessibilityNeeds = trim(
        $_POST['accessibility_needs'] ?? ''
    );

    if ($fullName === '' || $email === '') {
        $profileMessage =
            'Full name and email are required.';

        $profileMessageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $profileMessage =
            'Please enter a valid email address.';

        $profileMessageType = 'error';
    } elseif (!in_array(
        $userCategory,
        $allowedCategories,
        true
    )) {
        $profileMessage =
            'Please select a valid user category.';

        $profileMessageType = 'error';
    } elseif (!in_array(
        $accessibilityNeeds,
        $allowedAccessibilityOptions,
        true
    )) {
        $profileMessage =
            'Please select a valid support option.';

        $profileMessageType = 'error';
    } elseif (
        $birthDate !== '' &&
        strtotime($birthDate) === false
    ) {
        $profileMessage =
            'Please enter a valid birth date.';

        $profileMessageType = 'error';
    } elseif (
        $birthDate !== '' &&
        new DateTimeImmutable($birthDate) >
        new DateTimeImmutable('today')
    ) {
        $profileMessage =
            'Birth date cannot be in the future.';

        $profileMessageType = 'error';
    } else {
        try {
            $emailCheckStatement = $pdo->prepare(
                'SELECT id
                 FROM users
                 WHERE email = ?
                 AND id <> ?
                 LIMIT 1'
            );

            $emailCheckStatement->execute([
                $email,
                $userId
            ]);

            if ($emailCheckStatement->fetch()) {
                $profileMessage =
                    'That email address is already in use.';

                $profileMessageType = 'error';
            } else {
                $updateProfileStatement = $pdo->prepare(
                    'UPDATE users
                     SET
                        full_name = :full_name,
                        email = :email,
                        birth_date = :birth_date,
                        user_category = :user_category,
                        accessibility_needs =
                            :accessibility_needs
                     WHERE id = :id'
                );

                $updateProfileStatement->execute([
                    'full_name' => $fullName,
                    'email' => $email,
                    'birth_date' =>
                        $birthDate !== ''
                            ? $birthDate
                            : null,
                    'user_category' =>
                        $userCategory !== ''
                            ? $userCategory
                            : null,
                    'accessibility_needs' =>
                        $accessibilityNeeds !== ''
                            ? $accessibilityNeeds
                            : null,
                    'id' => $userId
                ]);

                $_SESSION['full_name'] = $fullName;
                $_SESSION['email'] = $email;

                $profileMessage =
                    'Profile updated successfully.';

                $profileMessageType = 'success';

                $user['full_name'] = $fullName;
                $user['email'] = $email;
                $user['birth_date'] =
                    $birthDate !== ''
                        ? $birthDate
                        : null;
                $user['user_category'] =
                    $userCategory !== ''
                        ? $userCategory
                        : null;
                $user['accessibility_needs'] =
                    $accessibilityNeeds !== ''
                        ? $accessibilityNeeds
                        : null;
            }
        } catch (PDOException $exception) {
            $profileMessage =
                'Unable to update the profile. Please try again.';

            $profileMessageType = 'error';
        }
    }
}

/*
 * Change the account password.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['form_action'] ?? '') === 'change_password'
) {
    $currentPassword =
        (string) ($_POST['current_password'] ?? '');

    $newPassword =
        (string) ($_POST['new_password'] ?? '');

    $confirmPassword =
        (string) ($_POST['confirm_password'] ?? '');

    if (
        $currentPassword === '' ||
        $newPassword === '' ||
        $confirmPassword === ''
    ) {
        $passwordMessage =
            'Please complete all password fields.';

        $passwordMessageType = 'error';
    } elseif (!password_verify(
        $currentPassword,
        (string) $user['password']
    )) {
        $passwordMessage =
            'The current password is incorrect.';

        $passwordMessageType = 'error';
    } elseif (strlen($newPassword) < 8) {
        $passwordMessage =
            'The new password must contain at least 8 characters.';

        $passwordMessageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $passwordMessage =
            'The new passwords do not match.';

        $passwordMessageType = 'error';
    } elseif (password_verify(
        $newPassword,
        (string) $user['password']
    )) {
        $passwordMessage =
            'The new password must be different from the current password.';

        $passwordMessageType = 'error';
    } else {
        try {
            $newPasswordHash = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            $passwordStatement = $pdo->prepare(
                'UPDATE users
                 SET password = ?
                 WHERE id = ?'
            );

            $passwordStatement->execute([
                $newPasswordHash,
                $userId
            ]);

            $user['password'] = $newPasswordHash;

            $passwordMessage =
                'Password updated successfully.';

            $passwordMessageType = 'success';
        } catch (PDOException $exception) {
            $passwordMessage =
                'Unable to update the password. Please try again.';

            $passwordMessageType = 'error';
        }
    }
}

/*
 * Account summary.
 */
$totalItems = 0;
$totalTaken = 0;
$totalLowStock = 0;

try {
    $profileStatsStatement = $pdo->prepare(
        'SELECT
            (
                SELECT COUNT(*)
                FROM medicines
                WHERE user_id = ?
            ) AS total_items,

            (
                SELECT COUNT(*)
                FROM medication_history
                WHERE user_id = ?
                AND status = "Taken"
            ) AS total_taken,

            (
                SELECT COUNT(*)
                FROM medicines
                WHERE user_id = ?
                AND quantity <= low_stock_level
            ) AS total_low_stock'
    );

    $profileStatsStatement->execute([
        $userId,
        $userId,
        $userId
    ]);

    $profileStats =
        $profileStatsStatement->fetch();

    if ($profileStats) {
        $totalItems =
            (int) ($profileStats['total_items'] ?? 0);

        $totalTaken =
            (int) ($profileStats['total_taken'] ?? 0);

        $totalLowStock =
            (int) (
                $profileStats['total_low_stock']
                ?? 0
            );
    }
} catch (PDOException $exception) {
    $totalItems = 0;
    $totalTaken = 0;
    $totalLowStock = 0;
}

$calculatedAge = null;

if (!empty($user['birth_date'])) {
    try {
        $birthDateObject =
            new DateTimeImmutable(
                (string) $user['birth_date']
            );

        $calculatedAge =
            $birthDateObject
                ->diff(new DateTimeImmutable('today'))
                ->y;
    } catch (Exception $exception) {
        $calculatedAge = null;
    }
}

$fullName =
    (string) ($user['full_name'] ?? 'User');

$todayName = date('l');
$todayDate = date('F d, Y');

$memberSince = !empty($user['created_at'])
    ? date(
        'F Y',
        strtotime((string) $user['created_at'])
    )
    : 'Unknown';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Profile | Medicine Tracker</title>

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
        href="/SCSA_GROUP5/assets/css/dashboard.css?v=12"
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

                <h2>Profile</h2>
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

            <section class="profile-page-heading">

                <div>
                    <p class="card-label">
                        Account and personal details
                    </p>

                    <h1>Your Profile</h1>

                    <p>
                        Update your account information, optional
                        personal details, and password.
                    </p>
                </div>

            </section>

            <section class="profile-layout">

                <div class="profile-left-column">

                    <article class="dashboard-card">

                        <div class="profile-card-heading">
                            <p class="card-label">
                                Personal information
                            </p>

                            <h2>Edit Profile</h2>

                            <p>
                                Birth date and support information
                                are optional.
                            </p>
                        </div>

                        <?php if (
                            $profileMessage !== ''
                        ): ?>
                            <div
                                class="profile-message <?= htmlspecialchars(
                                    $profileMessageType
                                ) ?>"
                            >
                                <?= htmlspecialchars(
                                    $profileMessage
                                ) ?>
                            </div>
                        <?php endif; ?>

                        <form
                            method="POST"
                            action=""
                            class="profile-form"
                        >

                            <input
                                type="hidden"
                                name="form_action"
                                value="update_profile"
                            >

                            <div class="profile-form-grid">

                                <div class="profile-field">
                                    <label for="full_name">
                                        Full name
                                    </label>

                                    <input
                                        type="text"
                                        id="full_name"
                                        name="full_name"
                                        value="<?= htmlspecialchars(
                                            (string) (
                                                $user['full_name']
                                                ?? ''
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        required
                                    >
                                </div>

                                <div class="profile-field">
                                    <label for="email">
                                        Email address
                                    </label>

                                    <input
                                        type="email"
                                        id="email"
                                        name="email"
                                        value="<?= htmlspecialchars(
                                            (string) (
                                                $user['email']
                                                ?? ''
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        required
                                    >
                                </div>

                                <div class="profile-field">
                                    <label for="birth_date">
                                        Birth date
                                        <span>Optional</span>
                                    </label>

                                    <input
                                        type="date"
                                        id="birth_date"
                                        name="birth_date"
                                        max="<?= date('Y-m-d') ?>"
                                        value="<?= htmlspecialchars(
                                            (string) (
                                                $user['birth_date']
                                                ?? ''
                                            )
                                        ) ?>"
                                    >
                                </div>

                                <div class="profile-field">
                                    <label>
                                        Calculated age
                                    </label>

                                    <div class="profile-age-note">
                                        <?= $calculatedAge !== null
                                            ? $calculatedAge .
                                                ' years old'
                                            : 'Add a birth date to calculate age' ?>
                                    </div>
                                </div>

                                <div class="profile-field">
                                    <label for="user_category">
                                        User category
                                        <span>Optional</span>
                                    </label>

                                    <select
                                        id="user_category"
                                        name="user_category"
                                    >
                                        <option value="">
                                            Select a category
                                        </option>

                                        <?php foreach (
                                            array_slice(
                                                $allowedCategories,
                                                1
                                            ) as $category
                                        ): ?>
                                            <option
                                                value="<?= htmlspecialchars(
                                                    $category
                                                ) ?>"
                                                <?= (
                                                    $user[
                                                        'user_category'
                                                    ]
                                                    ?? ''
                                                ) === $category
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                <?= htmlspecialchars(
                                                    $category
                                                ) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="profile-field">
                                    <label for="accessibility_needs">
                                        Accessibility or support needs
                                        <span>Optional</span>
                                    </label>

                                    <select
                                        id="accessibility_needs"
                                        name="accessibility_needs"
                                    >
                                        <option value="">
                                            Select an option
                                        </option>

                                        <?php foreach (
                                            array_slice(
                                                $allowedAccessibilityOptions,
                                                1
                                            ) as $supportOption
                                        ): ?>
                                            <option
                                                value="<?= htmlspecialchars(
                                                    $supportOption
                                                ) ?>"
                                                <?= (
                                                    $user[
                                                        'accessibility_needs'
                                                    ]
                                                    ?? ''
                                                ) === $supportOption
                                                    ? 'selected'
                                                    : '' ?>
                                            >
                                                <?= htmlspecialchars(
                                                    $supportOption
                                                ) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                            </div>

                            <div class="profile-actions">

                                <button
                                    type="submit"
                                    class="dashboard-button primary profile-submit"
                                >
                                    Save Profile Changes
                                </button>

                            </div>

                        </form>

                    </article>

                    <article class="dashboard-card">

                        <div class="profile-card-heading">
                            <p class="card-label">
                                Account security
                            </p>

                            <h2>Change Password</h2>

                            <p>
                                Verify your current password before
                                creating a new one.
                            </p>
                        </div>

                        <?php if (
                            $passwordMessage !== ''
                        ): ?>
                            <div
                                class="profile-message <?= htmlspecialchars(
                                    $passwordMessageType
                                ) ?>"
                            >
                                <?= htmlspecialchars(
                                    $passwordMessage
                                ) ?>
                            </div>
                        <?php endif; ?>

                        <form
                            method="POST"
                            action=""
                            class="profile-form"
                            autocomplete="off"
                        >

                            <input
                                type="hidden"
                                name="form_action"
                                value="change_password"
                            >

                            <div class="profile-form-grid">

                                <div class="profile-field full-width">
                                    <label for="current_password">
                                        Current password
                                    </label>

                                    <input
                                        type="password"
                                        id="current_password"
                                        name="current_password"
                                        autocomplete="current-password"
                                        required
                                    >
                                </div>

                                <div class="profile-field">
                                    <label for="new_password">
                                        New password
                                    </label>

                                    <input
                                        type="password"
                                        id="new_password"
                                        name="new_password"
                                        minlength="8"
                                        autocomplete="new-password"
                                        required
                                    >
                                </div>

                                <div class="profile-field">
                                    <label for="confirm_password">
                                        Confirm new password
                                    </label>

                                    <input
                                        type="password"
                                        id="confirm_password"
                                        name="confirm_password"
                                        minlength="8"
                                        autocomplete="new-password"
                                        required
                                    >
                                </div>

                            </div>

                            <div class="profile-actions">

                                <button
                                    type="submit"
                                    class="dashboard-button primary profile-submit"
                                >
                                    Update Password
                                </button>

                            </div>

                        </form>

                    </article>

                </div>

                <aside class="profile-right-column">

                    <article
                        class="dashboard-card profile-summary-card"
                    >

                        <div class="profile-large-avatar">
                            <?= htmlspecialchars(
                                strtoupper(
                                    substr($fullName, 0, 1)
                                )
                            ) ?>
                        </div>

                        <h2>
                            <?= htmlspecialchars($fullName) ?>
                        </h2>

                        <p>
                            <?= htmlspecialchars(
                                (string) $user['email']
                            ) ?>
                        </p>

                        <span class="profile-member-since">
                            Member since
                            <?= htmlspecialchars($memberSince) ?>
                        </span>

                        <div class="profile-stat-list">

                            <div>
                                <span>
                                    Medicines and vitamins
                                </span>

                                <strong><?= $totalItems ?></strong>
                            </div>

                            <div>
                                <span>
                                    Recorded doses taken
                                </span>

                                <strong><?= $totalTaken ?></strong>
                            </div>

                            <div>
                                <span>
                                    Low-stock items
                                </span>

                                <strong><?= $totalLowStock ?></strong>
                            </div>

                        </div>

                    </article>

                    <article class="profile-privacy-note">

                        <h3>Privacy reminder</h3>

                        <p>
                            Birth date, user category, and accessibility
                            information are optional. Users may leave
                            them blank or choose “Prefer not to say.”
                        </p>

                    </article>

                </aside>

            </section>

        </div>

    </main>

</div>

</body>
</html>
