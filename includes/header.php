<?php
// ... existing PHP code from your file ...
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xpenz — Smart Expense Tracker</title>
    
    <!-- Google Fonts & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- External Theme Switcher Engine -->
    <script src="assets/js/theme.js"></script>
</head>
<body>

    <nav class="navbar navbar-expand-lg custom-navbar sticky-top py-3">
        <div class="container">
            <a class="navbar-brand fw-bold fs-3 text-white d-flex align-items-center" href="home.php">
                <span class="bg-primary text-white rounded-3 px-2 py-1 me-2 fs-4"><i class="fa-solid fa-chart-line"></i></span>
                Xpenz
            </a>
            <div class="d-flex align-items-center gap-3">
                <!-- Dark / Light Theme Toggle Button -->
                <button id="themeToggleBtn" class="btn btn-outline-secondary btn-sm rounded-pill px-3 d-flex align-items-center gap-1">
                    <i class="fas fa-sun" id="themeIcon"></i>
                    <span id="themeText">Light</span>
                </button>

                <span class="badge bg-dark border border-secondary text-light px-3 py-2 rounded-pill d-none d-md-inline">
                    <i class="fa-solid fa-users me-1 text-info"></i> Family Workspace
                </span>
                
                <!-- Profile Dropdown Menu with Settings Added -->
                <div class="dropdown">
                    <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold dropdown-toggle" style="width: 40px; height: 40px; cursor: pointer;" id="profileDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= isset($initials) ? htmlspecialchars($initials) : 'VP' ?>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end card-custom p-2 shadow-lg border-secondary" aria-labelledby="profileDropdown" style="min-width: 260px; background-color: var(--card-bg) !important;">
                        <li class="px-3 py-2 border-bottom border-secondary mb-2 text-center">
                            <span class="badge bg-primary rounded-circle p-3 fs-4 avatar-badge mb-1" style="width: 50px; height: 50px; display: inline-flex; align-items: center; justify-content: center;"><?= isset($initials) ? htmlspecialchars($initials) : 'VP' ?></span>
                            <span class="fw-bold d-block text-white mt-1"><?= isset($user_name) ? htmlspecialchars($user_name) : 'Vyom Patel' ?></span>
                            <small class="text-subtle"><?= isset($user['email']) ? htmlspecialchars($user['email']) : 'vyompatel996@gmail.com' ?></small>
                        </li>
                        <li><a class="dropdown-item rounded-2 py-2 mt-1 text-white" href="modules/settings.php"><i class="fa-solid fa-user-pen me-2 text-primary"></i> Edit Profile & Budget</a></li>
                        <li><a class="dropdown-item rounded-2 py-2 mt-1 text-white" href="modules/settings.php"><i class="fa-solid fa-key me-2 text-warning"></i> Change Password</a></li>
                        <li><a class="dropdown-item rounded-2 py-2 mt-1 text-white" href="modules/settings.php"><i class="fa-solid fa-gear me-2 text-info"></i> Settings</a></li>
                        <li><hr class="dropdown-divider border-secondary"></li>
                        <li><a class="dropdown-item rounded-2 py-2 text-danger" href="auth/logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a></li>
                    </ul>
                </div>

            </div>
        </div>
    </nav>
    
    <div class="container my-4">