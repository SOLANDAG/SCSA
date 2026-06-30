<?php

declare(strict_types=1);

require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

$medicineId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

$status = trim($_GET['status'] ?? '');

$returnPage = basename($_GET['return'] ?? 'dashboard.php');

$allowedReturnPages = [
    'dashboard.php',
    'medicine.php',
    'history.php'
];

if (!in_array($returnPage, $allowedReturnPages, true)) {
    $returnPage = 'dashboard.php';
}

$allowedStatuses = [
    'Taken',
    'Missed',
    'Skipped'
];

if (
    $medicineId <= 0 ||
    !in_array($status, $allowedStatuses, true)
) {
    header('Location: ' . $returnPage . '?message=invalid');
    exit;
}

try {
    $pdo->beginTransaction();

    /*
     * Confirm that the selected medicine belongs
     * to the currently logged-in user.
     */
    $medicineStatement = $pdo->prepare(
        'SELECT
            id,
            quantity
         FROM medicines
         WHERE id = ?
         AND user_id = ?
         LIMIT 1'
    );

    $medicineStatement->execute([
        $medicineId,
        $userId
    ]);

    $medicine = $medicineStatement->fetch();

    if (!$medicine) {
        $pdo->rollBack();

        header(
            'Location: ' . $returnPage . '?message=not_found'
        );
        exit;
    }

    /*
     * Check whether this medicine already has
     * a status record for the current date.
     */
    $checkStatement = $pdo->prepare(
        'SELECT
            id,
            status
         FROM medication_history
         WHERE user_id = ?
         AND medicine_id = ?
         AND DATE(confirmed_at) = CURDATE()
         ORDER BY id DESC
         LIMIT 1'
    );

    $checkStatement->execute([
        $userId,
        $medicineId
    ]);

    $existingHistory = $checkStatement->fetch();

    if ($existingHistory) {
        $previousStatus = $existingHistory['status'];

        /*
         * Update today's existing record instead
         * of creating a duplicate.
         */
        $updateStatement = $pdo->prepare(
            'UPDATE medication_history
             SET
                status = ?,
                confirmed_at = CURRENT_TIMESTAMP
             WHERE id = ?
             AND user_id = ?'
        );

        $updateStatement->execute([
            $status,
            (int) $existingHistory['id'],
            $userId
        ]);

        /*
         * Restore one quantity when Taken is changed
         * to Missed or Skipped.
         */
        if (
            $previousStatus === 'Taken' &&
            $status !== 'Taken'
        ) {
            $restoreStatement = $pdo->prepare(
                'UPDATE medicines
                 SET quantity = quantity + 1
                 WHERE id = ?
                 AND user_id = ?'
            );

            $restoreStatement->execute([
                $medicineId,
                $userId
            ]);
        }

        /*
         * Subtract one quantity when Missed or
         * Skipped is changed to Taken.
         */
        if (
            $previousStatus !== 'Taken' &&
            $status === 'Taken'
        ) {
            $decreaseStatement = $pdo->prepare(
                'UPDATE medicines
                 SET quantity = GREATEST(
                     quantity - 1,
                     0
                 )
                 WHERE id = ?
                 AND user_id = ?'
            );

            $decreaseStatement->execute([
                $medicineId,
                $userId
            ]);
        }
    } else {
        /*
         * Create today's status record.
         */
        $insertStatement = $pdo->prepare(
            'INSERT INTO medication_history (
                user_id,
                medicine_id,
                status,
                confirmed_at
             )
             VALUES (
                ?,
                ?,
                ?,
                CURRENT_TIMESTAMP
             )'
        );

        $insertStatement->execute([
            $userId,
            $medicineId,
            $status
        ]);

        /*
         * Only Taken reduces the remaining quantity.
         */
        if ($status === 'Taken') {
            $decreaseStatement = $pdo->prepare(
                'UPDATE medicines
                 SET quantity = GREATEST(
                     quantity - 1,
                     0
                 )
                 WHERE id = ?
                 AND user_id = ?'
            );

            $decreaseStatement->execute([
                $medicineId,
                $userId
            ]);
        }
    }

    $pdo->commit();

    header(
        'Location: ' . $returnPage . '?message=status_updated'
    );
    exit;
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    exit(
        'Mark status error: ' .
        htmlspecialchars(
            $exception->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
    );
}
