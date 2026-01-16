<?php

/**
 * Dashboard - Main Entry Point
 * Displays system statistics and quick links
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

require_login();

$database = new Database();
$db = $database->getConnection();

$page_title = 'Dashboard';

// Get dashboard statistics
$stats = [
    'today_sales' => 0,
    'today_invoices' => 0,
    'pending_credit' => 0,
    'low_stock_items' => 0,
    'active_production' => 0,
    'monthly_sales' => 0
];

try {
    // Today's sales
    $query = "SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total 
              FROM sales_invoices 
              WHERE invoice_date = CURDATE() AND status != 'cancelled'";
    $stmt = $db->query($query);
    $result = $stmt->fetch();
    $stats['today_invoices'] = $result['count'];
    $stats['today_sales'] = $result['total'];

    // Pending credit
    $query = "SELECT COALESCE(SUM(balance_amount), 0) as total 
              FROM credit_sales 
              WHERE status IN ('pending', 'partial', 'overdue')";
    $stmt = $db->query($query);
    $stats['pending_credit'] = $stmt->fetch()['total'];

    // Low stock items
    $query = "SELECT COUNT(*) as count 
              FROM inventory i 
              INNER JOIN products p ON i.product_id = p.product_id 
              WHERE i.quantity_available <= p.reorder_level";
    $stmt = $db->query($query);
    $stats['low_stock_items'] = $stmt->fetch()['count'];

    // Active production orders
    $query = "SELECT COUNT(*) as count 
              FROM production_orders 
              WHERE status IN ('pending', 'in_progress')";
    $stmt = $db->query($query);
    $stats['active_production'] = $stmt->fetch()['count'];

    // Monthly sales
    $query = "SELECT COALESCE(SUM(total_amount), 0) as total 
              FROM sales_invoices 
              WHERE YEAR(invoice_date) = YEAR(CURDATE()) 
              AND MONTH(invoice_date) = MONTH(CURDATE()) 
              AND status != 'cancelled'";
    $stmt = $db->query($query);
    $stats['monthly_sales'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
        <div class="text-muted">
            <i class="fas fa-calendar"></i> <?php echo date('l, F j, Y'); ?>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4 col-lg-2">
            <div class="card stat-card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">Today's Sales</h6>
                            <h4 class="mb-0"><?php echo format_currency($stats['today_sales']); ?></h4>
                            <small><?php echo $stats['today_invoices']; ?> invoices</small>
                        </div>
                        <i class="fas fa-file-invoice-dollar fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 col-lg-2">
            <div class="card stat-card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">Monthly Sales</h6>
                            <h4 class="mb-0"><?php echo format_currency($stats['monthly_sales']); ?></h4>
                            <small>This month</small>
                        </div>
                        <i class="fas fa-chart-line fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 col-lg-2">
            <div class="card stat-card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">Pending Credit</h6>
                            <h4 class="mb-0"><?php echo format_currency($stats['pending_credit']); ?></h4>
                            <small>Outstanding</small>
                        </div>
                        <i class="fas fa-credit-card fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 col-lg-2">
            <div class="card stat-card bg-danger text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">Low Stock</h6>
                            <h4 class="mb-0"><?php echo $stats['low_stock_items']; ?></h4>
                            <small>Items below reorder</small>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 col-lg-2">
            <div class="card stat-card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">Production</h6>
                            <h4 class="mb-0"><?php echo $stats['active_production']; ?></h4>
                            <small>Active orders</small>
                        </div>
                        <i class="fas fa-industry fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 col-lg-2">
            <div class="card stat-card bg-secondary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">Quick Action</h6>
                            <?php if (has_permission('sales', 'create')): ?>
                                <a href="sales/create_invoice.php" class="btn btn-light btn-sm mt-2">
                                    <i class="fas fa-plus"></i> New Invoice
                                </a>
                            <?php endif; ?>
                        </div>
                        <i class="fas fa-bolt fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="row g-3 mb-4">
        <?php if (has_permission('sales', 'read')): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-shopping-cart"></i> Sales
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if (has_permission('sales', 'create')): ?>
                                <a href="sales/create_invoice.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-plus"></i> New Invoice
                                </a>
                            <?php endif; ?>
                            <a href="sales/invoices.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-list"></i> View Invoices
                            </a>
                            <a href="customers/list.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-users"></i> Customers
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (has_permission('inventory', 'read')): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <i class="fas fa-warehouse"></i> Inventory
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="inventory/stock_levels.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-boxes"></i> Stock Levels
                            </a>
                            <a href="inventory/transactions.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-exchange-alt"></i> Transactions
                            </a>
                            <?php if (has_permission('inventory', 'create')): ?>
                                <a href="inventory/transfer.php" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-truck"></i> Stock Transfer
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (has_permission('production', 'read')): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-header bg-info text-white">
                        <i class="fas fa-industry"></i> Production
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="production/orders.php" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-clipboard-list"></i> Orders
                            </a>
                            <?php if (has_permission('production', 'create')): ?>
                                <a href="production/create_order.php" class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-plus"></i> New Order
                                </a>
                                <a href="production/outputs.php" class="btn btn-outline-info btn-sm">
                                    <i class="fas fa-boxes"></i> Record Output
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (has_permission('accounting', 'read') || has_permission('credit', 'read')): ?>
            <div class="col-md-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-header bg-warning text-white">
                        <i class="fas fa-calculator"></i> Accounting
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if (has_permission('credit', 'read')): ?>
                                <a href="accounting/credit_sales.php" class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-credit-card"></i> Credit Sales
                                </a>
                            <?php endif; ?>
                            <?php if (has_permission('accounting', 'read')): ?>
                                <a href="accounting/reports.php" class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-file-invoice"></i> Reports
                                </a>
                                <a href="accounting/assets.php" class="btn btn-outline-warning btn-sm">
                                    <i class="fas fa-building"></i> Fixed Assets
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>