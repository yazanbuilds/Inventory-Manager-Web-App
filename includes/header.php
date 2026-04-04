<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Abaya Wholesale Manager' ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css"
        rel="stylesheet" />

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>

<body>

    <!-- Mobile Top Navigation Bar -->
    <nav class="navbar navbar-light bg-white fixed-top d-lg-none shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand text-dark" href="dashboard.php">
                <i class="bi bi-shop text-primary"></i> Abaya Wholesale
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
    </nav>

    <!-- Mobile Offcanvas Sidebar -->
    <div class="offcanvas offcanvas-start bg-white d-lg-none" tabindex="-1" id="mobileSidebar">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title text-dark">
                <i class="bi bi-shop text-primary"></i> Menu
            </h5>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="dashboard.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="products.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : '' ?>">
                        <i class="bi bi-box-seam"></i> Products
                    </a>
                </li>
                <li class="nav-item">
                    <a href="vendors.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'vendors.php' ? 'active' : '' ?>">
                        <i class="bi bi-people"></i> Vendors
                    </a>
                </li>
                <li class="nav-item">
                    <a href="sales.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'active' : '' ?>">
                        <i class="bi bi-cart-plus"></i> Create Sale
                    </a>
                </li>
                <li class="nav-item">
                    <a href="invoices.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'invoices.php' ? 'active' : '' ?>">
                        <i class="bi bi-receipt"></i> Invoices
                    </a>
                </li>
                <li class="nav-item">
                    <a href="payments.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : '' ?>">
                        <i class="bi bi-cash-coin"></i> Payments
                    </a>
                </li>
                <li class="nav-item">
                    <a href="reports.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
                        <i class="bi bi-graph-up"></i> Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a href="settings.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>">
                        <i class="bi bi-gear"></i> Settings
                    </a>
                </li>

                <li class="nav-item">
                    <a href="logout.php" class="nav-link">
                        <i class="bi bi-box-arrow-right text-danger"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Desktop Sidebar (Hidden on Mobile) -->
    <div class="sidebar bg-white d-none d-lg-block">
        <div class="p-3">
            <h4 class="mb-4 text-dark fw-bold">
                <i class="bi bi-shop text-primary"></i> Abaya<span class="text-primary">Manager</span>
            </h4>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="dashboard.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="products.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : '' ?>">
                        <i class="bi bi-box-seam"></i> Products
                    </a>
                </li>
                <li class="nav-item">
                    <a href="vendors.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'vendors.php' ? 'active' : '' ?>">
                        <i class="bi bi-people"></i> Vendors
                    </a>
                </li>
                <li class="nav-item">
                    <a href="sales.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'sales.php' ? 'active' : '' ?>">
                        <i class="bi bi-cart-plus"></i> Create Sale
                    </a>
                </li>
                <li class="nav-item">
                    <a href="invoices.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'invoices.php' ? 'active' : '' ?>">
                        <i class="bi bi-receipt"></i> Invoices
                    </a>
                </li>
                <li class="nav-item">
                    <a href="payments.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : '' ?>">
                        <i class="bi bi-cash-coin"></i> Payments
                    </a>
                </li>
                <li class="nav-item">
                    <a href="reports.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
                        <i class="bi bi-graph-up"></i> Reports
                    </a>
                </li>
                <li class="nav-item">
                    <a href="settings.php"
                        class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>">
                        <i class="bi bi-gear"></i> Settings
                    </a>
                </li>

                <li class="nav-item">
                    <a href="logout.php" class="nav-link text-danger">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">