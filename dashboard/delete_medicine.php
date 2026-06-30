<?php
declare(strict_types=1);

require_once '../config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$medicineId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($medicineId <= 0) {
    header('Location: medicine.php?message=invalid');
    exit;
}

try {
    $statement = $pdo->prepare(
        'DELETE FROM medicines
         WHERE id = ?
         AND user_id = ?'
    );

    $statement->execute([$medicineId, $userId]);

    header(
        'Location: medicine.php?message=' .
        ($statement->rowCount() > 0 ? 'deleted' : 'not_found')
    );
    exit;
} catch (PDOException $exception) {
    exit(
        'Delete error: ' .
        htmlspecialchars(
            $exception->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        )
    );
}
