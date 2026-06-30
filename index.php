<?php

declare(strict_types=1);

require_once 'config.php';

$isLoggedIn = isset($_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Medicine Tracker</title>

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
        href="assets/css/style.css"
    >
</head>

<body class="landing-page">

    <header class="landing-header">
        <a href="index.php" class="brand">
            Medicine Tracker
        </a>

        <nav class="landing-nav">
            <a href="#about">About</a>
            <a href="#features">Features</a>

            <?php if ($isLoggedIn): ?>
                <a href="dashboard/dashboard.php">
                    Dashboard
                </a>

                <a
                    href="auth/logout.php"
                    class="nav-button"
                >
                    Log out
                </a>
            <?php else: ?>
                <a href="auth/login.php">
                    Log in
                </a>

                <a
                    href="auth/register.php"
                    class="nav-button"
                >
                    Register
                </a>
            <?php endif; ?>
        </nav>
    </header>

    <main>
        <section class="hero-section">
            <div class="hero-content">
                <p class="hero-label">
                    Better health starts with consistency
                </p>

                <h1>
                    Never miss your medicine or vitamin schedule.
                </h1>

                <p class="hero-description">
                    Medicine Tracker helps users organize their
                    medicine and vitamin schedules, monitor remaining
                    quantities, record completed doses, and receive
                    low-stock reminders.
                </p>

                <div class="hero-actions">
                    <?php if ($isLoggedIn): ?>
                        <a
                            href="dashboard/dashboard.php"
                            class="primary-button"
                        >
                            Open dashboard
                        </a>
                    <?php else: ?>
                        <a
                            href="auth/register.php"
                            class="primary-button"
                        >
                            Create an account
                        </a>

                        <a
                            href="auth/login.php"
                            class="secondary-button"
                        >
                            Log in
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="hero-card">
                <div class="demo-card-header">
                    <div>
                        <p>Today's schedule</p>
                        <h2>Your medicines</h2>
                    </div>

                    <span class="status-badge">
                        3 scheduled
                    </span>
                </div>

                <div class="demo-medicine">
                    <div class="medicine-icon">M</div>

                    <div>
                        <strong>Vitamin C</strong>
                        <p>1 tablet · 8:00 AM</p>
                    </div>

                    <span class="demo-status taken">
                        Taken
                    </span>
                </div>

                <div class="demo-medicine">
                    <div class="medicine-icon">A</div>

                    <div>
                        <strong>Amoxicillin</strong>
                        <p>500 mg · 1:00 PM</p>
                    </div>

                    <span class="demo-status pending">
                        Pending
                    </span>
                </div>

                <div class="demo-medicine">
                    <div class="medicine-icon">D</div>

                    <div>
                        <strong>Vitamin D</strong>
                        <p>1 capsule · 7:00 PM</p>
                    </div>

                    <span class="demo-status pending">
                        Pending
                    </span>
                </div>
            </div>
        </section>

        <section id="about" class="info-section">
            <div class="section-heading">
                <p class="section-label">
                    About the system
                </p>

                <h2>
                    A simple way to manage daily medication routines
                </h2>

                <p>
                    This healthcare application is designed for
                    individuals who need help organizing medicines
                    and vitamins, especially elderly users, patients
                    with regular prescriptions, caregivers, and
                    healthcare workers.
                </p>
            </div>

            <div class="info-grid">
                <article class="info-card">
                    <span class="feature-number">01</span>

                    <h3>Create schedules</h3>

                    <p>
                        Add medicine names, dosages, schedule times,
                        days, descriptions, and current quantities.
                    </p>
                </article>

                <article class="info-card">
                    <span class="feature-number">02</span>

                    <h3>Track intake</h3>

                    <p>
                        Confirm when a scheduled medicine has been
                        taken and keep a record of medication history.
                    </p>
                </article>

                <article class="info-card">
                    <span class="feature-number">03</span>

                    <h3>Monitor supplies</h3>

                    <p>
                        Track remaining quantities and receive a
                        warning when medicine stock is running low.
                    </p>
                </article>
            </div>
        </section>

        <section id="features" class="features-section">
            <div class="section-heading">
                <p class="section-label">
                    Main features
                </p>

                <h2>
                    Everything users need in one place
                </h2>
            </div>

            <div class="feature-list">
                <div class="feature-item">
                    <h3>
                        Medication and vitamin management
                    </h3>

                    <p>
                        Add, edit, view, and remove medicine or
                        vitamin entries.
                    </p>
                </div>

                <div class="feature-item">
                    <h3>
                        Daily schedule overview
                    </h3>

                    <p>
                        View all scheduled medicines from a simple
                        dashboard.
                    </p>
                </div>

                <div class="feature-item">
                    <h3>
                        Medication history
                    </h3>

                    <p>
                        Review records of medicines marked as taken,
                        missed, or skipped.
                    </p>
                </div>

                <div class="feature-item">
                    <h3>
                        Low-stock monitoring
                    </h3>

                    <p>
                        Know when medicine supplies need to be
                        replaced or refilled.
                    </p>
                </div>
            </div>
        </section>

        <section class="cta-section">
            <div>
                <p class="section-label">
                    Get started
                </p>

                <h2>
                    Manage your health routine more easily.
                </h2>
            </div>

            <div class="hero-actions">
                <?php if ($isLoggedIn): ?>
                    <a
                        href="dashboard/dashboard.php"
                        class="primary-button"
                    >
                        Go to dashboard
                    </a>
                <?php else: ?>
                    <a
                        href="auth/register.php"
                        class="primary-button"
                    >
                        Register
                    </a>

                    <a
                        href="auth/login.php"
                        class="secondary-button"
                    >
                        Log in
                    </a>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <footer class="landing-footer">
        <p>
            &copy; <?= date('Y') ?>
            Medicine Tracker — SCSA Group 5
        </p>
    </footer>

</body>
</html>
