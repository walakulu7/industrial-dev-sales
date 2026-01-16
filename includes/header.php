<?php
if (!function_exists('display_flash_message')) {
    function display_flash_message($type)
    {
        if (isset($_SESSION['flash_' . $type])) {
            $message = $_SESSION['flash_' . $type];
            unset($_SESSION['flash_' . $type]);
            $alertClass = $type === 'error' ? 'danger' : $type;
            return '<div class="alert alert-' . htmlspecialchars($alertClass) . ' alert-dismissible fade show" role="alert">' . htmlspecialchars($message) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        }
        return '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Dashboard'; ?> - <?php echo APP_NAME; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --sidebar-width: 250px;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-menu {
            padding: 10px 0;
        }

        .sidebar-menu a {
            display: block;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }

        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.1);
            padding-left: 30px;
        }

        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.2);
            border-left: 4px solid white;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh;
        }

        .top-navbar {
            background: white;
            padding: 15px 20px;
            margin: -20px -20px 20px -20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stat-card {
            transition: transform 0.3s;
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .card {
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .badge {
            padding: 0.35em 0.65em;
        }

        .btn {
            border-radius: 5px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .sticky-top {
            position: sticky;
            top: 20px;
            z-index: 100;
        }
    </style>
</head>

<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-industry"></i> TMS</h4>
            <small>Textile Management System</small>
        </div>

        <div class="sidebar-menu">
            <a href="<?php echo BASE_URL; ?>/dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>

            <?php if (has_permission('sales', 'read')): ?>
                <a href="<?php echo BASE_URL; ?>/sales/invoices.php">
                    <i class="fas fa-file-invoice"></i> Sales & Invoices
                </a>
            <?php endif; ?>

            <?php if (has_permission('inventory', 'read')): ?>
                <a href="<?php echo BASE_URL; ?>/inventory/stock_levels.php">
                    <i class="fas fa-warehouse"></i> Inventory
                </a>
            <?php endif; ?>

            <?php if (has_permission('production', 'read')): ?>
                <a href="<?php echo BASE_URL; ?>/production/orders.php">
                    <i class="fas fa-industry"></i> Production
                </a>
            <?php endif; ?>

            <?php if (has_permission('customers', 'read')): ?>
                <a href="<?php echo BASE_URL; ?>/customers/list.php">
                    <i class="fas fa-users"></i> Customers
                </a>
            <?php endif; ?>

            <?php if (has_permission('credit', 'read')): ?>
                <a href="<?php echo BASE_URL; ?>/accounting/credit_sales.php">
                    <i class="fas fa-credit-card"></i> Credit Sales
                </a>
            <?php endif; ?>

            <?php if (has_permission('accounting', 'read')): ?>
                <a href="<?php echo BASE_URL; ?>/accounting/reports.php">
                    <i class="fas fa-calculator"></i> Accounting
                </a>
            <?php endif; ?>

            <?php if (has_permission('reports', 'read')): ?>
                <a href="<?php echo BASE_URL; ?>/reports/index.php">
                    <i class="fas fa-chart-bar"></i> Reports
                </a>
            <?php endif; ?>

            <?php if (has_permission('all', 'read')): ?>
                <a href="<?php echo BASE_URL; ?>/admin/users.php">
                    <i class="fas fa-users-cog"></i> User Management
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/products.php">
                    <i class="fas fa-box"></i> Products
                </a>
                <a href="<?php echo BASE_URL; ?>/admin/branches.php">
                    <i class="fas fa-building"></i> Branches
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <button class="btn btn-link d-md-none" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    <span class="text-muted">
                        <i class="fas fa-building"></i> <?php echo $_SESSION['branch_name'] ?? 'All Branches'; ?>
                    </span>
                </div>
                <div class="dropdown">
                    <button class="btn btn-link dropdown-toggle text-decoration-none" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> <?php echo $_SESSION['full_name']; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text"><strong><?php echo $_SESSION['role_name']; ?></strong></span></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/profile.php"><i class="fas fa-user"></i> Profile</a></li>
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/change_password.php"><i class="fas fa-key"></i> Change Password</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <?php
        // Display flash messages
        echo display_flash_message('success');
        echo display_flash_message('error');
        echo display_flash_message('info');
        echo display_flash_message('warning');
        ?>