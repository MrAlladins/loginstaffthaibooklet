<?php
// includes/header.php - Header for all pages
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thaibooklet Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Add Chart.js in the head section -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php if (isset($_SESSION['staff_id']) || isset($_SESSION['user_id'])): ?>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">Thaibooklet</a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mr-auto">
                    <?php if (isset($_SESSION['user_id']) && function_exists('isCompanyAdmin') && isCompanyAdmin($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="company_dashboard.php">Overview</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="staff_management.php">Staff</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="coupon_statistics.php">Statistics</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="salesboost.php">Sales Boost</a>
                    </li>
                    <?php elseif (isset($_SESSION['staff_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="scan_coupon.php">Scan Coupons</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <?php if (isset($_SESSION['user_id']) && isset($_SESSION['user_name'])): ?>
                                <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            <?php elseif (isset($_SESSION['staff_id']) && isset($_SESSION['staff_shop_name'])): ?>
                                <?php echo htmlspecialchars($_SESSION['staff_role_name'] . ' (' . $_SESSION['staff_shop_name'] . ')'); ?>
                            <?php else: ?>
                                Menu
                            <?php endif; ?>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="navbarDropdown">
                            <?php if (isset($_SESSION['staff_id'])): ?>
                            <a class="dropdown-item" href="scan_history.php">Scan History</a>
                            <div class="dropdown-divider"></div>
                            <?php endif; ?>
                            <a class="dropdown-item" href="logout.php">Log Out</a>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <div class="content mt-4">
        <div class="container">
