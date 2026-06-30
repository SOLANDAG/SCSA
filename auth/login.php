<?php

declare(strict_types=1);

require_once '../config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ../dashboard/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $statement = $pdo->prepare(
            'SELECT id, full_name, email, password
             FROM users
             WHERE email = ?'
        );

        $statement->execute([$email]);
        $user = $statement->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['full_name'] = $user['full_name'];

            header('Location: ../dashboard/dashboard.php');
            exit;
        }

        $error = 'Incorrect email or password.';
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

    <title>Login | Medicine Tracker</title>

    <link
    rel="stylesheet" href="../assets/css/style.css"
    >
</head>

<body class="login-bg">

    <a href="../index.php" class="auth-back-link">
        ᓚᘏᗢ Back to home
    </a>

    <main class="auth-container login-layout">

        <section class="auth-box login-box">
            <h2>Welcome Back</h2>

            <p class="auth-subtitle">
                Manage your medicine schedules, quantities,
                and medication history.
            </p>

            <?php if ($error !== ''): ?>
                <p class="message error">
                    <?= htmlspecialchars($error) ?>
                </p>
            <?php endif; ?>

            <form method="POST">
                <label for="email">Email address</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    autocomplete="email"
                    required
                >

                <label for="password">Password</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >

                <button type="submit" class="main-btn">
                    Log in
                </button>

                <p class="switch">
                    Do not have an account?
                    <a href="register.php">Create one</a>
                </p>
            </form>
        </section>

        <section class="auth-visual">
            <img
                src="../assets/images/login.png"
                alt="Medicine and healthcare illustration"
            >
        </section>

    </main>

</body>
</html>
