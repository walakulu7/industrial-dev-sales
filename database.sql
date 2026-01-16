-- =====================================================
-- TEXTILE MANUFACTURING & SALES MANAGEMENT SYSTEM
-- Complete Database Setup - Fixed & Ready
-- Version: 1.0.0
-- =====================================================

-- Drop and recreate database
DROP DATABASE IF EXISTS textile_management_system;
CREATE DATABASE textile_management_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE textile_management_system;

-- =====================================================
-- CORE CONFIGURATION TABLES
-- =====================================================

-- Branches/Locations
CREATE TABLE branches (
    branch_id INT PRIMARY KEY AUTO_INCREMENT,
    branch_code VARCHAR(20) UNIQUE NOT NULL,
    branch_name VARCHAR(100) NOT NULL,
    branch_type ENUM('main_office', 'sub_office', 'sales_center', 'production_center', 'warehouse') NOT NULL,
    location VARCHAR(200),
    address TEXT,
    contact_phone VARCHAR(20),
    contact_email VARCHAR(100),
    manager_name VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_branch_type (branch_type),
    INDEX idx_status (status)
);

-- User Roles & Permissions
CREATE TABLE user_roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    role_description TEXT,
    permissions JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role_id INT NOT NULL,
    branch_id INT,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES user_roles(role_id),
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
    INDEX idx_username (username),
    INDEX idx_role (role_id),
    INDEX idx_branch (branch_id)
);

-- =====================================================
-- INVENTORY MANAGEMENT
-- =====================================================

-- Product Categories
CREATE TABLE product_categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_code VARCHAR(20) UNIQUE NOT NULL,
    category_name VARCHAR(100) NOT NULL,
    category_type ENUM('yarn', 'finished_product', 'raw_material', 'accessory') NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products/Items
CREATE TABLE products (
    product_id INT PRIMARY KEY AUTO_INCREMENT,
    product_code VARCHAR(50) UNIQUE NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    category_id INT NOT NULL,
    unit_of_measure VARCHAR(20) NOT NULL,
    specifications JSON,
    reorder_level DECIMAL(15,3) DEFAULT 0,
    standard_cost DECIMAL(15,2),
    standard_price DECIMAL(15,2),
    status ENUM('active', 'discontinued') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES product_categories(category_id),
    INDEX idx_category (category_id),
    INDEX idx_code (product_code)
);

-- Warehouses
CREATE TABLE warehouses (
    warehouse_id INT PRIMARY KEY AUTO_INCREMENT,
    warehouse_code VARCHAR(20) UNIQUE NOT NULL,
    warehouse_name VARCHAR(100) NOT NULL,
    warehouse_type ENUM('yarn', 'finished_product', 'raw_material', 'general') NOT NULL,
    branch_id INT NOT NULL,
    capacity DECIMAL(15,3),
    address TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
    INDEX idx_type (warehouse_type),
    INDEX idx_branch (branch_id)
);

-- Stock/Inventory
CREATE TABLE inventory (
    inventory_id INT PRIMARY KEY AUTO_INCREMENT,
    warehouse_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity_on_hand DECIMAL(15,3) DEFAULT 0,
    quantity_reserved DECIMAL(15,3) DEFAULT 0,
    quantity_available DECIMAL(15,3) GENERATED ALWAYS AS (quantity_on_hand - quantity_reserved) STORED,
    last_stock_date DATE,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id),
    UNIQUE KEY unique_warehouse_product (warehouse_id, product_id),
    INDEX idx_product (product_id),
    INDEX idx_warehouse (warehouse_id)
);

-- Stock Transactions
CREATE TABLE stock_transactions (
    transaction_id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_date DATE NOT NULL,
    transaction_time TIME NOT NULL,
    warehouse_id INT NOT NULL,
    product_id INT NOT NULL,
    transaction_type ENUM('receipt', 'issue', 'transfer_in', 'transfer_out', 'adjustment', 'production_input', 'production_output') NOT NULL,
    quantity DECIMAL(15,3) NOT NULL,
    unit_cost DECIMAL(15,2),
    reference_type VARCHAR(50),
    reference_id INT,
    from_warehouse_id INT,
    to_warehouse_id INT,
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id),
    FOREIGN KEY (from_warehouse_id) REFERENCES warehouses(warehouse_id),
    FOREIGN KEY (to_warehouse_id) REFERENCES warehouses(warehouse_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    INDEX idx_date (transaction_date),
    INDEX idx_warehouse (warehouse_id),
    INDEX idx_product (product_id),
    INDEX idx_type (transaction_type),
    INDEX idx_reference (reference_type, reference_id)
);

-- =====================================================
-- PRODUCTION MANAGEMENT
-- =====================================================

-- Production Centers
CREATE TABLE production_centers (
    production_center_id INT PRIMARY KEY AUTO_INCREMENT,
    center_code VARCHAR(20) UNIQUE NOT NULL,
    center_name VARCHAR(100) NOT NULL,
    branch_id INT NOT NULL,
    capacity_per_day DECIMAL(15,3),
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
    INDEX idx_branch (branch_id)
);

-- Production Lines
CREATE TABLE production_lines (
    line_id INT PRIMARY KEY AUTO_INCREMENT,
    line_code VARCHAR(20) UNIQUE NOT NULL,
    line_name VARCHAR(100) NOT NULL,
    production_center_id INT NOT NULL,
    product_id INT,
    capacity_per_shift DECIMAL(15,3),
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (production_center_id) REFERENCES production_centers(production_center_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id),
    INDEX idx_center (production_center_id)
);

-- Production Orders
CREATE TABLE production_orders (
    order_id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    order_date DATE NOT NULL,
    production_center_id INT NOT NULL,
    production_line_id INT,
    product_id INT NOT NULL,
    planned_quantity DECIMAL(15,3) NOT NULL,
    actual_quantity DECIMAL(15,3) DEFAULT 0,
    start_date DATE,
    end_date DATE,
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (production_center_id) REFERENCES production_centers(production_center_id),
    FOREIGN KEY (production_line_id) REFERENCES production_lines(line_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    INDEX idx_order_number (order_number),
    INDEX idx_date (order_date),
    INDEX idx_status (status),
    INDEX idx_center (production_center_id)
);

-- Production Inputs (Raw Materials)
CREATE TABLE production_inputs (
    input_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    quantity_required DECIMAL(15,3) NOT NULL,
    quantity_issued DECIMAL(15,3) DEFAULT 0,
    unit_cost DECIMAL(15,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES production_orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id),
    INDEX idx_order (order_id)
);

-- Production Outputs
CREATE TABLE production_outputs (
    output_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    output_date DATE NOT NULL,
    quantity_produced DECIMAL(15,3) NOT NULL,
    quantity_good DECIMAL(15,3) NOT NULL,
    quantity_defective DECIMAL(15,3) DEFAULT 0,
    warehouse_id INT NOT NULL,
    shift VARCHAR(20),
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES production_orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    INDEX idx_order (order_id),
    INDEX idx_date (output_date)
);

-- =====================================================
-- SALES MANAGEMENT
-- =====================================================

-- Customers
CREATE TABLE customers (
    customer_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_code VARCHAR(50) UNIQUE NOT NULL,
    customer_name VARCHAR(200) NOT NULL,
    customer_type ENUM('retail', 'wholesale', 'distributor', 'manufacturer') NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    city VARCHAR(100),
    credit_limit DECIMAL(15,2) DEFAULT 0,
    credit_days INT DEFAULT 0,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (customer_code),
    INDEX idx_type (customer_type)
);

-- Sales Invoices
CREATE TABLE sales_invoices (
    invoice_id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    invoice_date DATE NOT NULL,
    invoice_time TIME NOT NULL,
    branch_id INT NOT NULL,
    customer_id INT NOT NULL,
    payment_method ENUM('cash', 'credit', 'bank_transfer', 'cheque') NOT NULL,
    credit_days INT DEFAULT 0,
    due_date DATE,
    subtotal DECIMAL(15,2) NOT NULL,
    discount_amount DECIMAL(15,2) DEFAULT 0,
    tax_amount DECIMAL(15,2) DEFAULT 0,
    total_amount DECIMAL(15,2) NOT NULL,
    paid_amount DECIMAL(15,2) DEFAULT 0,
    balance_amount DECIMAL(15,2) GENERATED ALWAYS AS (total_amount - paid_amount) STORED,
    status ENUM('pending', 'partial', 'paid', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    INDEX idx_invoice_number (invoice_number),
    INDEX idx_date (invoice_date),
    INDEX idx_customer (customer_id),
    INDEX idx_branch (branch_id),
    INDEX idx_status (status),
    INDEX idx_payment (payment_method)
);

-- Invoice Details
CREATE TABLE invoice_details (
    detail_id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    product_id INT NOT NULL,
    warehouse_id INT NOT NULL,
    quantity DECIMAL(15,3) NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    discount_percent DECIMAL(5,2) DEFAULT 0,
    discount_amount DECIMAL(15,2) DEFAULT 0,
    line_total DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES sales_invoices(invoice_id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(product_id),
    FOREIGN KEY (warehouse_id) REFERENCES warehouses(warehouse_id),
    INDEX idx_invoice (invoice_id),
    INDEX idx_product (product_id)
);

-- =====================================================
-- CREDIT MANAGEMENT - FIXED VERSION
-- =====================================================

-- Credit Sales Tracking (NO GENERATED overdue_days column)
CREATE TABLE credit_sales (
    credit_id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    customer_id INT NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL,
    paid_amount DECIMAL(15,2) DEFAULT 0,
    balance_amount DECIMAL(15,2) GENERATED ALWAYS AS (total_amount - paid_amount) STORED,
    status ENUM('pending', 'partial', 'paid', 'overdue', 'written_off') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES sales_invoices(invoice_id),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    UNIQUE KEY unique_invoice (invoice_id),
    INDEX idx_customer (customer_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date)
);

-- Credit Payments
CREATE TABLE credit_payments (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    credit_id INT NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash', 'bank_transfer', 'cheque', 'card') NOT NULL,
    payment_reference VARCHAR(100),
    amount DECIMAL(15,2) NOT NULL,
    notes TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (credit_id) REFERENCES credit_sales(credit_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    INDEX idx_credit (credit_id),
    INDEX idx_date (payment_date)
);

-- =====================================================
-- ACCOUNTING & FINANCIAL MANAGEMENT
-- =====================================================

-- Chart of Accounts
CREATE TABLE chart_of_accounts (
    account_id INT PRIMARY KEY AUTO_INCREMENT,
    account_code VARCHAR(20) UNIQUE NOT NULL,
    account_name VARCHAR(200) NOT NULL,
    account_type ENUM('asset', 'liability', 'equity', 'revenue', 'expense', 'cost_of_goods') NOT NULL,
    parent_account_id INT,
    level INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_account_id) REFERENCES chart_of_accounts(account_id),
    INDEX idx_type (account_type),
    INDEX idx_code (account_code)
);

-- Journal Entries
CREATE TABLE journal_entries (
    entry_id INT PRIMARY KEY AUTO_INCREMENT,
    entry_number VARCHAR(50) UNIQUE NOT NULL,
    entry_date DATE NOT NULL,
    entry_type ENUM('manual', 'sales', 'purchase', 'payment', 'receipt', 'adjustment') NOT NULL,
    reference_type VARCHAR(50),
    reference_id INT,
    description TEXT,
    total_debit DECIMAL(15,2) NOT NULL,
    total_credit DECIMAL(15,2) NOT NULL,
    status ENUM('draft', 'posted', 'void') DEFAULT 'draft',
    branch_id INT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    posted_at TIMESTAMP NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    INDEX idx_date (entry_date),
    INDEX idx_type (entry_type),
    INDEX idx_status (status),
    INDEX idx_reference (reference_type, reference_id)
);

-- Journal Entry Details
CREATE TABLE journal_entry_details (
    detail_id INT PRIMARY KEY AUTO_INCREMENT,
    entry_id INT NOT NULL,
    account_id INT NOT NULL,
    debit_amount DECIMAL(15,2) DEFAULT 0,
    credit_amount DECIMAL(15,2) DEFAULT 0,
    description TEXT,
    FOREIGN KEY (entry_id) REFERENCES journal_entries(entry_id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES chart_of_accounts(account_id),
    INDEX idx_entry (entry_id),
    INDEX idx_account (account_id)
);

-- =====================================================
-- FIXED ASSETS MANAGEMENT
-- =====================================================

-- Fixed Assets
CREATE TABLE fixed_assets (
    asset_id INT PRIMARY KEY AUTO_INCREMENT,
    asset_code VARCHAR(50) UNIQUE NOT NULL,
    asset_name VARCHAR(200) NOT NULL,
    asset_category ENUM('building', 'machinery', 'vehicle', 'furniture', 'computer', 'other') NOT NULL,
    branch_id INT NOT NULL,
    purchase_date DATE NOT NULL,
    purchase_value DECIMAL(15,2) NOT NULL,
    salvage_value DECIMAL(15,2) DEFAULT 0,
    useful_life_years INT NOT NULL,
    depreciation_method ENUM('straight_line', 'declining_balance', 'units_of_production') DEFAULT 'straight_line',
    accumulated_depreciation DECIMAL(15,2) DEFAULT 0,
    book_value DECIMAL(15,2) GENERATED ALWAYS AS (purchase_value - accumulated_depreciation) STORED,
    location VARCHAR(200),
    serial_number VARCHAR(100),
    supplier VARCHAR(200),
    status ENUM('active', 'disposed', 'sold', 'written_off') DEFAULT 'active',
    disposal_date DATE,
    disposal_value DECIMAL(15,2),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id),
    INDEX idx_code (asset_code),
    INDEX idx_branch (branch_id),
    INDEX idx_category (asset_category),
    INDEX idx_status (status)
);

-- Depreciation Records
CREATE TABLE depreciation_records (
    depreciation_id INT PRIMARY KEY AUTO_INCREMENT,
    asset_id INT NOT NULL,
    period_year INT NOT NULL,
    period_month INT NOT NULL,
    depreciation_amount DECIMAL(15,2) NOT NULL,
    accumulated_depreciation DECIMAL(15,2) NOT NULL,
    book_value DECIMAL(15,2) NOT NULL,
    entry_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (asset_id) REFERENCES fixed_assets(asset_id) ON DELETE CASCADE,
    FOREIGN KEY (entry_id) REFERENCES journal_entries(entry_id),
    UNIQUE KEY unique_asset_period (asset_id, period_year, period_month),
    INDEX idx_period (period_year, period_month)
);

-- =====================================================
-- SYSTEM AUDIT & LOGGING
-- =====================================================

-- Audit Log
CREATE TABLE audit_log (
    log_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    table_name VARCHAR(100),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    INDEX idx_user (user_id),
    INDEX idx_action (action_type),
    INDEX idx_table (table_name),
    INDEX idx_date (created_at)
);

-- =====================================================
-- REPORTS METADATA
-- =====================================================

-- Saved Reports
CREATE TABLE saved_reports (
    report_id INT PRIMARY KEY AUTO_INCREMENT,
    report_name VARCHAR(200) NOT NULL,
    report_type VARCHAR(50) NOT NULL,
    parameters JSON,
    created_by INT NOT NULL,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    INDEX idx_type (report_type),
    INDEX idx_user (created_by)
);

-- =====================================================
-- VIEWS FOR REPORTING
-- =====================================================

-- Current Stock Levels View
CREATE VIEW v_current_stock AS
SELECT 
    i.inventory_id,
    w.warehouse_code,
    w.warehouse_name,
    b.branch_name,
    p.product_code,
    p.product_name,
    pc.category_name,
    i.quantity_on_hand,
    i.quantity_reserved,
    i.quantity_available,
    p.reorder_level,
    CASE 
        WHEN i.quantity_available <= p.reorder_level THEN 'Low Stock'
        WHEN i.quantity_available = 0 THEN 'Out of Stock'
        ELSE 'Adequate'
    END AS stock_status,
    i.last_updated
FROM inventory i
INNER JOIN warehouses w ON i.warehouse_id = w.warehouse_id
INNER JOIN branches b ON w.branch_id = b.branch_id
INNER JOIN products p ON i.product_id = p.product_id
INNER JOIN product_categories pc ON p.category_id = pc.category_id;

-- Credit Sales Outstanding View (FIXED - calculate overdue_days in view)
CREATE VIEW v_credit_outstanding AS
SELECT 
    cs.credit_id,
    c.customer_code,
    c.customer_name,
    si.invoice_number,
    cs.invoice_date,
    cs.due_date,
    cs.total_amount,
    cs.paid_amount,
    cs.balance_amount,
    DATEDIFF(CURDATE(), cs.due_date) AS overdue_days,
    CASE 
        WHEN DATEDIFF(CURDATE(), cs.due_date) > 90 THEN 'Critical'
        WHEN DATEDIFF(CURDATE(), cs.due_date) > 60 THEN 'High Risk'
        WHEN DATEDIFF(CURDATE(), cs.due_date) > 30 THEN 'Overdue'
        ELSE 'Current'
    END AS aging_category,
    cs.status
FROM credit_sales cs
INNER JOIN customers c ON cs.customer_id = c.customer_id
INNER JOIN sales_invoices si ON cs.invoice_id = si.invoice_id
WHERE cs.status IN ('pending', 'partial', 'overdue');

-- Daily Sales Summary View
CREATE VIEW v_daily_sales_summary AS
SELECT 
    si.invoice_date,
    b.branch_name,
    COUNT(si.invoice_id) AS total_invoices,
    SUM(si.subtotal) AS gross_sales,
    SUM(si.discount_amount) AS total_discounts,
    SUM(si.total_amount) AS net_sales,
    SUM(CASE WHEN si.payment_method = 'cash' THEN si.total_amount ELSE 0 END) AS cash_sales,
    SUM(CASE WHEN si.payment_method = 'credit' THEN si.total_amount ELSE 0 END) AS credit_sales
FROM sales_invoices si
INNER JOIN branches b ON si.branch_id = b.branch_id
WHERE si.status != 'cancelled'
GROUP BY si.invoice_date, b.branch_id;

-- Production Efficiency View
CREATE VIEW v_production_efficiency AS
SELECT 
    po.order_number,
    pc.center_name,
    p.product_name,
    po.order_date,
    po.planned_quantity,
    po.actual_quantity,
    (po.actual_quantity / NULLIF(po.planned_quantity, 0) * 100) AS efficiency_percent,
    po.start_date,
    po.end_date,
    DATEDIFF(po.end_date, po.start_date) AS production_days,
    po.status
FROM production_orders po
INNER JOIN production_centers pc ON po.production_center_id = pc.production_center_id
INNER JOIN products p ON po.product_id = p.product_id;

-- =====================================================
-- INITIAL DATA INSERT
-- =====================================================

-- Insert Branches
INSERT INTO branches (branch_code, branch_name, branch_type, location, address, contact_phone, status) VALUES
('MAIN-VKP', 'Viskampiyasa Main Office', 'main_office', 'Viskampiyasa', 'Main Office, Viskampiyasa', '0112345678', 'active'),
('SUB-PLN', 'Polonnaruwa Sub Office', 'sub_office', 'Polonnaruwa', 'Sub Office, Polonnaruwa', '0272345678', 'active'),
('SC-VKP', 'Viskampiyasa Sales Center', 'sales_center', 'Viskampiyasa', 'Sales Center, Viskampiyasa', '0112345679', 'active'),
('SC-PLN', 'Polonnaruwa Sales Center', 'sales_center', 'Polonnaruwa', 'Sales Center, Polonnaruwa', '0272345679', 'active'),
('PC-ANP', 'Anuradhapura Production Center', 'production_center', 'Anuradhapura', 'Production Facility, Anuradhapura', '0252345678', 'active'),
('PC-PLN', 'Polonnaruwa Production Center', 'production_center', 'Polonnaruwa', 'Production Facility, Polonnaruwa', '0272345680', 'active'),
('WH-ANP-Y', 'Anuradhapura Yarn Warehouse', 'warehouse', 'Anuradhapura', 'Yarn Storage, Anuradhapura', '0252345679', 'active'),
('WH-PLN-Y', 'Polonnaruwa Yarn Warehouse', 'warehouse', 'Polonnaruwa', 'Yarn Storage, Polonnaruwa', '0272345681', 'active'),
('WH-VKP-FG', 'Viskampiyasa Finished Goods', 'warehouse', 'Viskampiyasa', 'Finished Goods Storage, Viskampiyasa', '0112345680', 'active'),
('WH-PLN-FG', 'Polonnaruwa Finished Goods', 'warehouse', 'Polonnaruwa', 'Finished Goods Storage, Polonnaruwa', '0272345682', 'active');

-- Insert User Roles with Permissions
INSERT INTO user_roles (role_name, role_description, permissions) VALUES
('Administrator', 'Full system access', 
'{"modules": ["all"], "permissions": ["create", "read", "update", "delete", "approve", "reports"]}'),

('Provincial Director', 'Provincial oversight and supervision',
'{"modules": ["sales", "inventory", "production", "reports", "accounting"], "permissions": ["read", "approve", "reports"]}'),

('Accountant', 'Financial management and reporting',
'{"modules": ["accounting", "sales", "credit", "assets", "reports"], "permissions": ["create", "read", "update", "reports"]}'),

('Production Manager', 'Production operations management',
'{"modules": ["production", "inventory", "reports"], "permissions": ["create", "read", "update", "reports"]}'),

('Office Officer', 'Branch operations and invoice generation',
'{"modules": ["sales", "inventory", "customers", "reports"], "permissions": ["create", "read", "update"]}'),

('Sales Assistant', 'Sales and customer service',
'{"modules": ["sales", "customers", "inventory"], "permissions": ["create", "read"]}'),

('Warehouse Manager', 'Inventory and stock management',
'{"modules": ["inventory", "stock_transactions", "reports"], "permissions": ["create", "read", "update", "reports"]}');

-- Insert Default Admin User (password: admin123)
INSERT INTO users (username, password_hash, full_name, email, role_id, branch_id, status) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@textile.lk', 1, 1, 'active');

-- Insert Product Categories
INSERT INTO product_categories (category_code, category_name, category_type, description) VALUES
('YARN-COT', 'Cotton Yarn', 'yarn', 'Various counts of cotton yarn'),
('YARN-POL', 'Polyester Yarn', 'yarn', 'Various counts of polyester yarn'),
('YARN-BLD', 'Blended Yarn', 'yarn', 'Cotton-polyester blended yarn'),
('FG-FABRIC', 'Finished Fabric', 'finished_product', 'Woven and knitted fabrics'),
('FG-GARMENT', 'Garments', 'finished_product', 'Finished garments'),
('RM-FIBER', 'Raw Fiber', 'raw_material', 'Cotton and synthetic fibers'),
('ACC-DYE', 'Dyes and Chemicals', 'accessory', 'Dyeing materials');

-- Insert Warehouses
INSERT INTO warehouses (warehouse_code, warehouse_name, warehouse_type, branch_id, capacity, status) VALUES
('WH-ANP-Y001', 'Anuradhapura Yarn Warehouse 1', 'yarn', 7, 50000.000, 'active'),
('WH-PLN-Y001', 'Polonnaruwa Yarn Warehouse 1', 'yarn', 8, 40000.000, 'active'),
('WH-VKP-FG001', 'Viskampiyasa Finished Goods 1', 'finished_product', 9, 30000.000, 'active'),
('WH-PLN-FG001', 'Polonnaruwa Finished Goods 1', 'finished_product', 10, 25000.000, 'active'),
('WH-ANP-RM001', 'Anuradhapura Raw Material', 'raw_material', 7, 20000.000, 'active'),
('WH-PLN-RM001', 'Polonnaruwa Raw Material', 'raw_material', 8, 15000.000, 'active');
-- Insert Sample Products
INSERT INTO products (product_code, product_name, category_id, unit_of_measure, specifications, reorder_level, standard_cost, standard_price, status) VALUES
('YARN-C20', 'Cotton Yarn 20s', 1, 'KG', '{"count": "20s", "material": "100% Cotton"}', 1000.000, 450.00, 550.00, 'active'),
('YARN-C30', 'Cotton Yarn 30s', 1, 'KG', '{"count": "30s", "material": "100% Cotton"}', 800.000, 520.00, 620.00, 'active'),
('YARN-P150', 'Polyester Yarn 150D', 2, 'KG', '{"denier": "150D", "material": "100% Polyester"}', 1200.000, 380.00, 480.00, 'active'),
('YARN-B2030', 'Blended Yarn 20/30', 3, 'KG', '{"blend": "65% Polyester 35% Cotton"}', 900.000, 420.00, 520.00, 'active'),
('FAB-COT-W', 'Cotton Woven Fabric', 4, 'MTR', '{"width": "60 inch", "weight": "180 GSM"}', 500.000, 280.00, 380.00, 'active'),
('FAB-POL-K', 'Polyester Knit Fabric', 4, 'MTR', '{"width": "72 inch", "weight": "200 GSM"}', 600.000, 320.00, 420.00, 'active'),
('GARM-TS-M', 'T-Shirt Medium', 5, 'PCS', '{"size": "M", "material": "Cotton Jersey"}', 200.000, 450.00, 750.00, 'active'),
('GARM-TS-L', 'T-Shirt Large', 5, 'PCS', '{"size": "L", "material": "Cotton Jersey"}', 200.000, 480.00, 800.00, 'active');
-- Insert Chart of Accounts
INSERT INTO chart_of_accounts (account_code, account_name, account_type, parent_account_id, level) VALUES
-- Assets
('1000', 'Assets', 'asset', NULL, 1),
('1100', 'Current Assets', 'asset', 1, 2),
('1110', 'Cash and Bank', 'asset', 2, 3),
('1120', 'Accounts Receivable', 'asset', 2, 3),
('1130', 'Inventory - Raw Materials', 'asset', 2, 3),
('1131', 'Inventory - Yarn', 'asset', 2, 3),
('1132', 'Inventory - Finished Goods', 'asset', 2, 3),
('1200', 'Fixed Assets', 'asset', 1, 2),
('1210', 'Machinery', 'asset', 8, 3),
('1220', 'Vehicles', 'asset', 8, 3),
('1230', 'Buildings', 'asset', 8, 3),
('1240', 'Accumulated Depreciation', 'asset', 8, 3),
-- Liabilities
('2000', 'Liabilities', 'liability', NULL, 1),
('2100', 'Current Liabilities', 'liability', 13, 2),
('2110', 'Accounts Payable', 'liability', 14, 3),
('2120', 'Short-term Loans', 'liability', 14, 3),
-- Equity
('3000', 'Equity', 'equity', NULL, 1),
('3100', 'Capital', 'equity', 17, 2),
('3200', 'Retained Earnings', 'equity', 17, 2),
-- Revenue
('4000', 'Revenue', 'revenue', NULL, 1),
('4100', 'Sales Revenue', 'revenue', 20, 2),
('4110', 'Cash Sales', 'revenue', 21, 3),
('4120', 'Credit Sales', 'revenue', 21, 3),
-- Cost of Goods Sold
('5000', 'Cost of Goods Sold', 'cost_of_goods', NULL, 1),
('5100', 'Direct Materials', 'cost_of_goods', 24, 2),
('5200', 'Direct Labor', 'cost_of_goods', 24, 2),
('5300', 'Manufacturing Overhead', 'cost_of_goods', 24, 2),
-- Expenses
('6000', 'Expenses', 'expense', NULL, 1),
('6100', 'Operating Expenses', 'expense', 28, 2),
('6110', 'Salaries and Wages', 'expense', 29, 3),
('6120', 'Rent', 'expense', 29, 3),
('6130', 'Utilities', 'expense', 29, 3),
('6140', 'Depreciation Expense', 'expense', 29, 3),
('6150', 'Transportation', 'expense', 29, 3),
('6160', 'Office Supplies', 'expense', 29, 3);
-- Insert Production Centers
INSERT INTO production_centers (center_code, center_name, branch_id, capacity_per_day, status) VALUES
('PC-ANP-001', 'Anuradhapura Spinning Unit', 5, 5000.000, 'active'),
('PC-ANP-002', 'Anuradhapura Weaving Unit', 5, 3000.000, 'active'),
('PC-PLN-001', 'Polonnaruwa Spinning Unit', 6, 4000.000, 'active'),
('PC-PLN-002', 'Polonnaruwa Knitting Unit', 6, 2500.000, 'active');
-- Insert Production Lines
INSERT INTO production_lines (line_code, line_name, production_center_id, capacity_per_shift, status) VALUES
('LINE-ANP-S1', 'Spinning Line 1', 1, 1500.000, 'active'),
('LINE-ANP-S2', 'Spinning Line 2', 1, 1500.000, 'active'),
('LINE-ANP-W1', 'Weaving Line 1', 2, 1000.000, 'active'),
('LINE-PLN-S1', 'Spinning Line 1', 3, 1200.000, 'active'),
('LINE-PLN-K1', 'Knitting Line 1', 4, 800.000, 'active');
-- Insert Sample Customers
INSERT INTO customers (customer_code, customer_name, customer_type, contact_person, phone, address, city, credit_limit, credit_days, status) VALUES
('CUST-001', 'Textile Traders (Pvt) Ltd', 'wholesale', 'Mr. Silva', '0771234567', 'No 123, Main Street', 'Colombo', 500000.00, 30, 'active'),
('CUST-002', 'Fashion House Lanka', 'retail', 'Ms. Fernando', '0772234567', 'No 45, Galle Road', 'Colombo', 200000.00, 15, 'active'),
('CUST-003', 'Lanka Garments Export', 'manufacturer', 'Mr. Perera', '0773234567', 'Industrial Zone', 'Katunayake', 1000000.00, 45, 'active'),
('CUST-004', 'Wholesale Fabric Center', 'distributor', 'Mrs. Jayawardena', '0774234567', 'Manning Market', 'Pettah', 750000.00, 30, 'active'),
('CUST-005', 'Walk-in Customer', 'retail', 'N/A', 'N/A', 'N/A', 'N/A', 0.00, 0, 'active');
-- Initialize Inventory with opening stock
INSERT INTO inventory (warehouse_id, product_id, quantity_on_hand, quantity_reserved, last_stock_date) VALUES
-- Yarn Warehouse Anuradhapura
(1, 1, 5000.000, 0.000, CURDATE()),
(1, 2, 3500.000, 0.000, CURDATE()),
(1, 3, 6000.000, 0.000, CURDATE()),
(1, 4, 4200.000, 0.000, CURDATE()),
-- Yarn Warehouse Polonnaruwa
(2, 1, 4000.000, 0.000, CURDATE()),
(2, 2, 3000.000, 0.000, CURDATE()),
(2, 3, 5500.000, 0.000, CURDATE()),
-- Finished Goods Viskampiyasa
(3, 5, 2000.000, 0.000, CURDATE()),
(3, 6, 2500.000, 0.000, CURDATE()),
(3, 7, 800.000, 0.000, CURDATE()),
(3, 8, 750.000, 0.000, CURDATE()),
-- Finished Goods Polonnaruwa
(4, 5, 1500.000, 0.000, CURDATE()),
(4, 6, 1800.000, 0.000, CURDATE()),
(4, 7, 600.000, 0.000, CURDATE()),
(4, 8, 550.000, 0.000, CURDATE());
-- =====================================================
-- STORED PROCEDURES
-- =====================================================
DELIMITER $$
-- Procedure to generate next invoice number
CREATE PROCEDURE sp_generate_invoice_number(
IN p_branch_id INT,
OUT p_invoice_number VARCHAR(50)
)
BEGIN
DECLARE v_branch_code VARCHAR(20);
DECLARE v_count INT;
DECLARE v_year VARCHAR(4);
DECLARE v_month VARCHAR(2);
SELECT branch_code INTO v_branch_code FROM branches WHERE branch_id = p_branch_id;
SET v_year = YEAR(CURDATE());
SET v_month = LPAD(MONTH(CURDATE()), 2, '0');

SELECT COUNT(*) + 1 INTO v_count 
FROM sales_invoices 
WHERE branch_id = p_branch_id 
AND YEAR(invoice_date) = v_year 
AND MONTH(invoice_date) = MONTH(CURDATE());

SET p_invoice_number = CONCAT(v_branch_code, '-INV-', v_year, v_month, '-', LPAD(v_count, 5, '0'));
END$$
-- Procedure to update inventory after sale
CREATE PROCEDURE sp_update_inventory_sale(
IN p_warehouse_id INT,
IN p_product_id INT,
IN p_quantity DECIMAL(15,3),
IN p_invoice_id INT,
IN p_user_id INT
)
BEGIN
DECLARE v_available DECIMAL(15,3);
-- Check available quantity
SELECT quantity_available INTO v_available
FROM inventory
WHERE warehouse_id = p_warehouse_id AND product_id = p_product_id;

IF v_available >= p_quantity THEN
    -- Update inventory
    UPDATE inventory 
    SET quantity_on_hand = quantity_on_hand - p_quantity,
        last_stock_date = CURDATE()
    WHERE warehouse_id = p_warehouse_id AND product_id = p_product_id;
    
    -- Record transaction
    INSERT INTO stock_transactions (
        transaction_date, transaction_time, warehouse_id, product_id,
        transaction_type, quantity, reference_type, reference_id, created_by
    ) VALUES (
        CURDATE(), CURTIME(), p_warehouse_id, p_product_id,
        'issue', p_quantity, 'sales_invoice', p_invoice_id, p_user_id
    );
ELSE
    SIGNAL SQLSTATE '45000' 
    SET MESSAGE_TEXT = 'Insufficient stock available';
END IF;
END$$
-- Procedure to record production output
CREATE PROCEDURE sp_record_production_output(
IN p_order_id INT,
IN p_quantity_produced DECIMAL(15,3),
IN p_quantity_good DECIMAL(15,3),
IN p_warehouse_id INT,
IN p_user_id INT
)
BEGIN
DECLARE v_product_id INT;
DECLARE v_quantity_defective DECIMAL(15,3);
SET v_quantity_defective = p_quantity_produced - p_quantity_good;

-- Get product from order
SELECT product_id INTO v_product_id FROM production_orders WHERE order_id = p_order_id;

-- Insert output record
INSERT INTO production_outputs (
    order_id, output_date, quantity_produced, quantity_good,
    quantity_defective, warehouse_id, created_by
) VALUES (
    p_order_id, CURDATE(), p_quantity_produced, p_quantity_good,
    v_quantity_defective, p_warehouse_id, p_user_id
);

-- Update production order
UPDATE production_orders 
SET actual_quantity = actual_quantity + p_quantity_good
WHERE order_id = p_order_id;

-- Update inventory
INSERT INTO inventory (warehouse_id, product_id, quantity_on_hand, last_stock_date)
VALUES (p_warehouse_id, v_product_id, p_quantity_good, CURDATE())
ON DUPLICATE KEY UPDATE 
    quantity_on_hand = quantity_on_hand + p_quantity_good,
    last_stock_date = CURDATE();

-- Record transaction
INSERT INTO stock_transactions (
    transaction_date, transaction_time, warehouse_id, product_id,
    transaction_type, quantity, reference_type, reference_id, created_by
) VALUES (
    CURDATE(), CURTIME(), p_warehouse_id, v_product_id,
    'production_output', p_quantity_good, 'production_order', p_order_id, p_user_id
);
END$$
-- Procedure to calculate depreciation
CREATE PROCEDURE sp_calculate_monthly_depreciation(
IN p_year INT,
IN p_month INT
)
BEGIN
DECLARE done INT DEFAULT FALSE;
DECLARE v_asset_id INT;
DECLARE v_purchase_value DECIMAL(15,2);
DECLARE v_salvage_value DECIMAL(15,2);
DECLARE v_useful_life INT;
DECLARE v_accumulated DECIMAL(15,2);
DECLARE v_monthly_depreciation DECIMAL(15,2);
DECLARE v_book_value DECIMAL(15,2);
DECLARE asset_cursor CURSOR FOR
    SELECT asset_id, purchase_value, salvage_value, useful_life_years, accumulated_depreciation
    FROM fixed_assets
    WHERE status = 'active'
    AND depreciation_method = 'straight_line'
    AND YEAR(purchase_date) * 12 + MONTH(purchase_date) <= p_year * 12 + p_month;

DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

OPEN asset_cursor;

read_loop: LOOP
    FETCH asset_cursor INTO v_asset_id, v_purchase_value, v_salvage_value, v_useful_life, v_accumulated;
    IF done THEN
        LEAVE read_loop;
    END IF;
    
    -- Calculate monthly depreciation
    SET v_monthly_depreciation = (v_purchase_value - v_salvage_value) / (v_useful_life * 12);
    SET v_accumulated = v_accumulated + v_monthly_depreciation;
    SET v_book_value = v_purchase_value - v_accumulated;
    
    -- Don't depreciate below salvage value
    IF v_book_value < v_salvage_value THEN
        SET v_monthly_depreciation = v_purchase_value - v_salvage_value - (v_accumulated - v_monthly_depreciation);
        SET v_accumulated = v_purchase_value - v_salvage_value;
        SET v_book_value = v_salvage_value;
    END IF;
    
    -- Insert depreciation record
    INSERT IGNORE INTO depreciation_records (
        asset_id, period_year, period_month, depreciation_amount,
        accumulated_depreciation, book_value
    ) VALUES (
        v_asset_id, p_year, p_month, v_monthly_depreciation,
        v_accumulated, v_book_value
    );
    
    -- Update asset
    UPDATE fixed_assets 
    SET accumulated_depreciation = v_accumulated
    WHERE asset_id = v_asset_id;
END LOOP;

CLOSE asset_cursor;
END$$
DELIMITER ;