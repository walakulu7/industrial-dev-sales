-- =====================================================
-- TEXTILE MANUFACTURING & SALES MANAGEMENT SYSTEM
-- Updated for: textile_erp
-- =====================================================

-- 1. Reset Database
DROP DATABASE IF EXISTS textile_erp;
CREATE DATABASE textile_erp
CHARACTER
SET utf8mb4
COLLATE utf8mb4_unicode_ci;
USE textile_erp;

-- =====================================================
-- CORE CONFIGURATION TABLES
-- =====================================================

-- Branches/Locations
CREATE TABLE branches (
    branch_id INT PRIMARY KEY AUTO_INCREMENT,
    branch_code VARCHAR
(20) UNIQUE NOT NULL,
    branch_name VARCHAR
(100) NOT NULL,
    branch_type ENUM
('main_office', 'sub_office', 'sales_center', 'production_center', 'warehouse') NOT NULL,
    location VARCHAR
(200),
    address TEXT,
    contact_phone VARCHAR
(20),
    status ENUM
('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Roles
CREATE TABLE user_roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR
(50) UNIQUE NOT NULL,
    description TEXT
);

-- Users
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR
(50) UNIQUE NOT NULL,
    password_hash VARCHAR
(255) NOT NULL,
    full_name VARCHAR
(100) NOT NULL,
    email VARCHAR
(100),
    role_id INT NOT NULL,
    branch_id INT,
    status ENUM
('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY
(role_id) REFERENCES user_roles
(role_id),
    FOREIGN KEY
(branch_id) REFERENCES branches
(branch_id)
);

-- =====================================================
-- INVENTORY MANAGEMENT
-- =====================================================

-- Product Categories
CREATE TABLE product_categories (
    category_id INT PRIMARY KEY AUTO_INCREMENT,
    category_name VARCHAR
(100) NOT NULL,
    category_type ENUM
('yarn', 'finished_product', 'raw_material', 'accessory') NOT NULL
);

-- Products/Items
CREATE TABLE products (
    product_id INT PRIMARY KEY AUTO_INCREMENT,
    product_code VARCHAR
(50) UNIQUE NOT NULL,
    product_name VARCHAR
(200) NOT NULL,
    category_id INT NOT NULL,
    unit_of_measure VARCHAR
(20) NOT NULL, -- e.g., KG, MTR, PCS
    standard_cost DECIMAL
(15,2) DEFAULT 0.00,
    standard_price DECIMAL
(15,2) DEFAULT 0.00,
    reorder_level DECIMAL
(15,3) DEFAULT 10.000,
    status ENUM
('active', 'discontinued') DEFAULT 'active',
    FOREIGN KEY
(category_id) REFERENCES product_categories
(category_id)
);

-- Warehouses (Specific storage locations within a branch)
CREATE TABLE warehouses (
    warehouse_id INT PRIMARY KEY AUTO_INCREMENT,
    warehouse_name VARCHAR
(100) NOT NULL,
    branch_id INT NOT NULL,
    warehouse_type ENUM
('yarn', 'finished_product', 'raw_material', 'general') NOT NULL,
    FOREIGN KEY
(branch_id) REFERENCES branches
(branch_id)
);

-- Stock/Inventory
CREATE TABLE inventory (
    inventory_id INT PRIMARY KEY AUTO_INCREMENT,
    warehouse_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity_on_hand DECIMAL
(15,3) DEFAULT 0.000,
    quantity_reserved DECIMAL
(15,3) DEFAULT 0.000,
    quantity_available DECIMAL
(15,3) GENERATED ALWAYS AS
(quantity_on_hand - quantity_reserved) STORED,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON
UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY
(warehouse_id) REFERENCES warehouses
(warehouse_id),
    FOREIGN KEY
(product_id) REFERENCES products
(product_id),
    UNIQUE KEY unique_stock
(warehouse_id, product_id)
);

-- Stock Transactions (History)
CREATE TABLE stock_transactions (
    transaction_id INT PRIMARY KEY AUTO_INCREMENT,
    transaction_date DATE NOT NULL,
    warehouse_id INT NOT NULL,
    product_id INT NOT NULL,
    transaction_type ENUM
('receipt', 'issue', 'transfer', 'production_input', 'production_output', 'sale') NOT NULL,
    quantity DECIMAL
(15,3) NOT NULL,
    reference_id INT, -- Links to invoice_id or production_order_id
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY
(warehouse_id) REFERENCES warehouses
(warehouse_id),
    FOREIGN KEY
(product_id) REFERENCES products
(product_id)
);

-- =====================================================
-- PRODUCTION MANAGEMENT
-- =====================================================

-- Production Centers
CREATE TABLE production_centers (
    production_center_id INT PRIMARY KEY AUTO_INCREMENT,
    center_name VARCHAR
(100) NOT NULL,
    branch_id INT NOT NULL,
    FOREIGN KEY
(branch_id) REFERENCES branches
(branch_id)
);

-- Production Orders
CREATE TABLE production_orders (
    order_id INT PRIMARY KEY AUTO_INCREMENT,
    order_number VARCHAR
(50) UNIQUE NOT NULL,
    order_date DATE NOT NULL,
    production_center_id INT NOT NULL,
    product_id INT NOT NULL, -- What we are making
    planned_quantity DECIMAL
(15,3) NOT NULL,
    actual_quantity DECIMAL
(15,3) DEFAULT 0,
    status ENUM
('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY
(production_center_id) REFERENCES production_centers
(production_center_id),
    FOREIGN KEY
(product_id) REFERENCES products
(product_id),
    FOREIGN KEY
(created_by) REFERENCES users
(user_id)
);

-- Production Outputs (Daily Logs)
CREATE TABLE production_outputs (
    output_id INT PRIMARY KEY AUTO_INCREMENT,
    order_id INT NOT NULL,
    output_date DATE NOT NULL,
    quantity_produced DECIMAL
(15,3) NOT NULL,
    warehouse_id INT NOT NULL, -- Where the finished goods were sent
    FOREIGN KEY
(order_id) REFERENCES production_orders
(order_id),
    FOREIGN KEY
(warehouse_id) REFERENCES warehouses
(warehouse_id)
);

-- =====================================================
-- SALES & FINANCE
-- =====================================================

-- Customers
CREATE TABLE customers (
    customer_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_name VARCHAR
(200) NOT NULL,
    phone VARCHAR
(20),
    address TEXT,
    credit_limit DECIMAL
(15,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Sales Invoices
CREATE TABLE sales_invoices (
    invoice_id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_number VARCHAR
(50) UNIQUE NOT NULL,
    invoice_date DATE NOT NULL,
    branch_id INT NOT NULL, -- Issuing office
    customer_id INT NOT NULL,
    payment_method ENUM
('cash', 'credit', 'cheque') NOT NULL,
    total_amount DECIMAL
(15,2) NOT NULL,
    paid_amount DECIMAL
(15,2) DEFAULT 0.00,
    balance_amount DECIMAL
(15,2) GENERATED ALWAYS AS
(total_amount - paid_amount) STORED,
    status ENUM
('pending', 'paid', 'cancelled') DEFAULT 'pending',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY
(branch_id) REFERENCES branches
(branch_id),
    FOREIGN KEY
(customer_id) REFERENCES customers
(customer_id),
    FOREIGN KEY
(created_by) REFERENCES users
(user_id)
);

-- Invoice Items
CREATE TABLE invoice_details (
    detail_id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity DECIMAL
(15,3) NOT NULL,
    unit_price DECIMAL
(15,2) NOT NULL,
    line_total DECIMAL
(15,2) NOT NULL,
    FOREIGN KEY
(invoice_id) REFERENCES sales_invoices
(invoice_id) ON
DELETE CASCADE,
    FOREIGN KEY (product_id)
REFERENCES products
(product_id)
);

-- Credit Sales Tracking
CREATE TABLE credit_sales (
    credit_id INT PRIMARY KEY AUTO_INCREMENT,
    invoice_id INT NOT NULL,
    customer_id INT NOT NULL,
    due_date DATE NOT NULL,
    total_amount DECIMAL
(15,2) NOT NULL,
    paid_amount DECIMAL
(15,2) DEFAULT 0,
    status ENUM
('pending', 'partial', 'paid', 'overdue') DEFAULT 'pending',
    FOREIGN KEY
(invoice_id) REFERENCES sales_invoices
(invoice_id),
    FOREIGN KEY
(customer_id) REFERENCES customers
(customer_id)
);

-- =====================================================
-- VIEWS (For Dashboard & Reports)
-- =====================================================

-- View: Stock Levels with Names
CREATE OR REPLACE VIEW v_current_stock AS
SELECT
    i.inventory_id,
    w.warehouse_name,
    b.branch_name,
    p.product_name,
    p.product_code,
    c.category_name,
    i.quantity_available,
    p.unit_of_measure,
    CASE 
        WHEN i.quantity_available <= p.reorder_level THEN 'Low Stock'
        ELSE 'Adequate'
    END AS stock_status
FROM inventory i
    JOIN warehouses w ON i.warehouse_id = w.warehouse_id
    JOIN branches b ON w.branch_id = b.branch_id
    JOIN products p ON i.product_id = p.product_id
    JOIN product_categories c ON p.category_id = c.category_id;

-- =====================================================
-- SEED DATA (Default Values)
-- =====================================================

-- 1. Roles
INSERT INTO user_roles
    (role_name, description)
VALUES
    ('Administrator', 'Full System Access'),
    ('Provincial Director', 'Oversees Operations'),
    ('Accountant', 'Finance Module'),
    ('Office Officer', 'Sales & Inventory'),
    ('Sales Assistant', 'Basic Sales');

-- 2. Branches
INSERT INTO branches
    (branch_code, branch_name, branch_type, location)
VALUES
    ('MAIN', 'Viskampiyasa Head Office', 'main_office', 'Viskampiyasa'),
    ('POL', 'Polonnaruwa Office', 'sub_office', 'Polonnaruwa');

-- 3. Users (Password is '123')
INSERT INTO users
    (username, password_hash, full_name, role_id, branch_id)
VALUES
    ('admin', '$2b$10$nxW2/3r.g.r.g.r.g.r.g.r.g.r.g.r.g.r.g.r.g.r.g.r.g.r.g.r', 'System Admin', 1, 1),
    ('director', '$2b$10$nxW2/3r.g.r.g.r.g.r.g.r.g.r.g.r.g.r.g.r.g.r.g.r.g.r.g.r', 'Director', 2, 1);

-- 4. Categories
INSERT INTO product_categories
    (category_name, category_type)
VALUES
    ('Cotton Yarn', 'yarn'),
    ('Polyester Yarn', 'yarn'),
    ('Finished Fabric', 'finished_product'),
    ('Garments', 'finished_product');

-- 5. Warehouses
INSERT INTO warehouses
    (warehouse_name, branch_id, warehouse_type)
VALUES
    ('Viskampiyasa Yarn Store', 1, 'yarn'),
    ('Viskampiyasa Showroom', 1, 'finished_product'),
    ('Polonnaruwa Yarn Store', 2, 'yarn');

-- 6. Sample Products
INSERT INTO products
    (product_code, product_name, category_id, unit_of_measure, standard_cost, standard_price)
VALUES
    ('YRN-001', 'Cotton Yarn 40s', 1, 'KG', 2500.00, 3000.00),
    ('YRN-002', 'Poly Yarn 150D', 2, 'KG', 1800.00, 2200.00),
    ('FGD-001', 'Blue Bedsheet (King)', 3, 'PCS', 1500.00, 2500.00),
    ('FGD-002', 'School Uniform Fabric', 3, 'MTR', 450.00, 650.00);

-- 7. Initial Stock
INSERT INTO inventory
    (warehouse_id, product_id, quantity_on_hand)
VALUES
    (1, 1, 500.000),
    -- 500KG Cotton Yarn at Viskampiyasa
    (2, 3, 100.000);
-- 100 Bedsheets at Viskampiyasa Showroom


-- Add missing columns to support the new Customer Page
ALTER TABLE customers
ADD COLUMN customer_code VARCHAR
(50) UNIQUE AFTER customer_id,
ADD COLUMN customer_type ENUM
('retail', 'wholesale', 'distributor', 'manufacturer') DEFAULT 'retail' AFTER customer_name,
ADD COLUMN contact_person VARCHAR
(100) AFTER customer_type,
ADD COLUMN email VARCHAR
(100) AFTER phone,
ADD COLUMN city VARCHAR
(100) AFTER address,
ADD COLUMN status ENUM
('active', 'inactive') DEFAULT 'active';

-- Generate codes for any existing customers (to prevent errors)
UPDATE customers SET customer_code = CONCAT('CUST-', LPAD(customer_id, 4, '0')) WHERE customer_code IS NULL;

CREATE TABLE
IF NOT EXISTS production_logs
(
    production_id INT PRIMARY KEY AUTO_INCREMENT,
    production_date DATE NOT NULL,
    center_id INT NOT NULL,
    input_product_id INT NOT NULL,
    input_qty DECIMAL
(15,3) NOT NULL,
    output_product_id INT NOT NULL,
    output_qty DECIMAL
(15,3) NOT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY
(center_id) REFERENCES production_centers
(production_center_id),
    FOREIGN KEY
(input_product_id) REFERENCES products
(product_id),
    FOREIGN KEY
(output_product_id) REFERENCES products
(product_id)
);

-- Insert Production Centers
INSERT INTO production_centers (center_name, branch_id) VALUES
('Anuradhapura Weaving Unit', 1),
('Anuradhapura Spinning Unit', 1),
('Polonnaruwa Knitting Factory', 2),
('Viskampiyasa Dyeing Plant', 1);

-- 1. Clear the table
TRUNCATE TABLE credit_sales;

-- 2. Insert every invoice that has a balance > 0
INSERT INTO credit_sales (invoice_id, customer_id, due_date, total_amount, paid_amount, status)
SELECT 
    invoice_id, 
    customer_id, 
    DATE_ADD(invoice_date, INTERVAL 30 DAY), 
    total_amount, 
    COALESCE(paid_amount, 0), 
    'pending'
FROM sales_invoices
WHERE total_amount > COALESCE(paid_amount, 0);

CREATE TABLE credit_payments (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    credit_id INT NOT NULL,
    payment_date DATETIME NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_reference VARCHAR(100),
    amount DECIMAL(15,2) NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (credit_id) REFERENCES credit_sales(credit_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);