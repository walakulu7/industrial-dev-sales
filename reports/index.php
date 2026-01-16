<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
require_permission('reports', 'read');

$page_title = 'Reports';

include '../includes/header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4"><i class="fas fa-chart-bar"></i> Reports & Analytics</h2>

    <div class="row g-4">
        <!-- Sales Reports -->
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 hover-shadow">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-shopping-cart"></i> Sales Reports</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="sales_report.php?type=daily" class="list-group-item list-group-item-action">
                            <i class="fas fa-calendar-day"></i> Daily Sales Report
                        </a>
                        <a href="sales_report.php?type=monthly" class="list-group-item list-group-item-action">
                            <i class="fas fa-calendar-alt"></i> Monthly Sales Report
                        </a>
                        <a href="sales_report.php?type=branch" class="list-group-item list-group-item-action">
                            <i class="fas fa-building"></i> Sales by Branch
                        </a>
                        <a href="sales_report.php?type=product" class="list-group-item list-group-item-action">
                            <i class="fas fa-box"></i> Sales by Product
                        </a>
                        <a href="sales_report.php?type=customer" class="list-group-item list-group-item-action">
                            <i class="fas fa-user"></i> Sales by Customer
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory Reports -->
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 hover-shadow">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-warehouse"></i> Inventory Reports</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="inventory_report.php?type=stock_levels" class="list-group-item list-group-item-action">
                            <i class="fas fa-boxes"></i> Current Stock Levels
                        </a>
                        <a href="inventory_report.php?type=low_stock" class="list-group-item list-group-item-action">
                            <i class="fas fa-exclamation-triangle"></i> Low Stock Items
                        </a>
                        <a href="inventory_report.php?type=stock_movement" class="list-group-item list-group-item-action">
                            <i class="fas fa-exchange-alt"></i> Stock Movement
                        </a>
                        <a href="inventory_report.php?type=warehouse" class="list-group-item list-group-item-action">
                            <i class="fas fa-building"></i> Stock by Warehouse
                        </a>
                        <a href="inventory_report.php?type=valuation" class="list-group-item list-group-item-action">
                            <i class="fas fa-dollar-sign"></i> Stock Valuation
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Production Reports -->
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 hover-shadow">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-industry"></i> Production Reports</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="production_report.php?type=summary" class="list-group-item list-group-item-action">
                            <i class="fas fa-chart-line"></i> Production Summary
                        </a>
                        <a href="production_report.php?type=by_center" class="list-group-item list-group-item-action">
                            <i class="fas fa-building"></i> By Production Center
                        </a>
                        <a href="production_report.php?type=efficiency" class="list-group-item list-group-item-action">
                            <i class="fas fa-tachometer-alt"></i> Efficiency Report
                        </a>
                        <a href="production_report.php?type=by_product" class="list-group-item list-group-item-action">
                            <i class="fas fa-box"></i> By Product
                        </a>
                        <a href="production_report.php?type=defects" class="list-group-item list-group-item-action">
                            <i class="fas fa-times-circle"></i> Defects Analysis
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Reports -->
        <div class="col-md-6 col-lg-3">
            <div class="card h-100 hover-shadow">
                <div class="card-header bg-warning text-white">
                    <h5 class="mb-0"><i class="fas fa-calculator"></i> Financial Reports</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="financial_report.php?type=profit_loss" class="list-group-item list-group-item-action">
                            <i class="fas fa-file-invoice-dollar"></i> Profit & Loss
                        </a>
                        <a href="financial_report.php?type=credit_aging" class="list-group-item list-group-item-action">
                            <i class="fas fa-clock"></i> Credit Aging Report
                        </a>
                        <a href="financial_report.php?type=customer_ledger" class="list-group-item list-group-item-action">
                            <i class="fas fa-book"></i> Customer Ledger
                        </a>
                        <a href="financial_report.php?type=assets" class="list-group-item list-group-item-action">
                            <i class="fas fa-building"></i> Fixed Assets Register
                        </a>
                        <a href="financial_report.php?type=trial_balance" class="list-group-item list-group-item-action">
                            <i class="fas fa-balance-scale"></i> Trial Balance
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .hover-shadow {
        transition: all 0.3s;
    }

    .hover-shadow:hover {
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        transform: translateY(-5px);
    }
</style>

<?php include '../includes/footer.php'; ?>