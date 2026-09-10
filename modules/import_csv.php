<?php
session_start();
date_default_timezone_set('Asia/Kolkata');
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];
    
    if ($_FILES['csv_file']['size'] > 0) {
        $handle = fopen($file, "r");
        $row_count = 0;
        
        // Skip Header Row
        fgetcsv($handle);

        $pdo->beginTransaction();
        try {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Expected CSV Format: Description, Category, Type (income/expense), Amount
                if (isset($data[0], $data[1], $data[2], $data[3])) {
                    $description = trim($data[0]);
                    $category = trim($data[1]);
                    $type = strtolower(trim($data[2])) === 'income' ? 'income' : 'expense';
                    $amount = floatval($data[3]);

                    if (!empty($description) && $amount > 0) {
                        $stmt = $pdo->prepare("INSERT INTO transactions (user_id, description, category, type, amount) VALUES (:user_id, :desc, :cat, :type, :amt)");
                        $stmt->execute([
                            ':user_id' => $user_id,
                            ':desc' => $description,
                            ':cat' => $category,
                            ':type' => $type,
                            ':amt' => $amount
                        ]);
                        $row_count++;
                    }
                }
            }
            fclose($handle);
            $pdo->commit();

            $_SESSION['msg'] = "Successfully imported $row_count historical transactions!";
            $_SESSION['msg_type'] = "success";
            header("Location: transactions.php");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "Error importing file: " . $e->getMessage();
            $msg_type = "danger";
        }
    } else {
        $msg = "Please upload a valid CSV file.";
        $msg_type = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Import CSV - XPenz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fa-solid fa-file-csv me-2 text-primary"></i>Bulk Transaction Import</h2>
        <a href="transactions.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-1"></i> Back to Transactions</a>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
            <?= $msg ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card card-custom p-4">
                <h5 class="text-white mb-3"><i class="fa-solid fa-upload text-success me-2"></i>Upload CSV File</h5>
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label text-subtle">Select CSV File</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>
                    
                    <div class="alert alert-info py-2 fs-6">
                        <small><strong>Note:</strong> CSV File column structure must be:</small><br>
                        <code>Description, Category, Type (income/expense), Amount</code>
                    </div>

                    <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-cloud-arrow-up me-1"></i> Start Import</button>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>