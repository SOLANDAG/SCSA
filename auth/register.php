<?php

declare(strict_types=1);

require_once '../config.php';

/*
 * A user who is already logged in should not
 * return to the registration page.
 */
if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (
        $fullName === '' ||
        $email === '' ||
        $password === '' ||
        $confirmPassword === ''
    ) {
        $error = 'Please complete all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must contain at least 8 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        /*
         * Check whether the email address is already registered.
         */
        $checkStatement = $pdo->prepare(
            'SELECT id
             FROM users
             WHERE email = ?'
        );

        $checkStatement->execute([$email]);

        if ($checkStatement->fetch()) {
            $error = 'An account with that email already exists.';
        } else {
            /*
             * Securely hash the password before saving it.
             */
            $hashedPassword = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            /*
             * Create the user's account.
             */
            $insertStatement = $pdo->prepare(
                'INSERT INTO users (
                    full_name,
                    email,
                    password
                )
                VALUES (?, ?, ?)'
            );

            $insertStatement->execute([
                $fullName,
                $email,
                $hashedPassword
            ]);

            /*
             * Get the new account ID and automatically
             * log in the newly registered user.
             */
            $userId = (int) $pdo->lastInsertId();

            session_regenerate_id(true);

            $_SESSION['user_id'] = $userId;
            $_SESSION['full_name'] = $fullName;

            /*
             * Send the newly registered user
             * directly to the dashboard.
             */
            header('Location: ../dashboard/dashboard.php');
            exit;
        }
    }
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

    <title>Register | Medicine Tracker</title>

    <link
        rel="stylesheet" href="../assets/css/style.css"
    >
</head>

<body class="register-bg">

    <a
        href="../index.php" class="auth-back-link">
        ᓚᘏᗢ Back to home
    </a>

    <main class="auth-container register-layout">

        <section class="auth-visual">
            <img
                src="../assets/images/register.png"
                alt="Medicine and healthcare illustration"
            >
        </section>

        <section class="auth-box register-box">
            <h2>Create Account</h2>

            <p class="auth-subtitle">
                Start organizing your daily medicines and vitamins
                in one convenient place.
            </p>

            <?php if ($error !== ''): ?>
                <p class="message error">
                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>
            <?php endif; ?>

            <form method="POST" action="">
                <label for="full_name">
                    Full name
                </label>

                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    placeholder="Enter your full name"
                    value="<?= htmlspecialchars(
                        $_POST['full_name'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    autocomplete="name"
                    required
                >

                <label for="email">
                    Email address
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    value="<?= htmlspecialchars(
                        $_POST['email'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    autocomplete="email"
                    required
                >

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Minimum of 8 characters"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

                <label for="confirm_password">
                    Confirm password
                </label>

                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Re-enter your password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

                <button
                    type="submit"
                    class="main-btn"
                >
                    Register
                </button>

                <p class="switch">
                    Already have an account?
                    <a href="login.php">Log in</a>
                </p>
            </form>
        </section>

    </main>

</body>
</html>
