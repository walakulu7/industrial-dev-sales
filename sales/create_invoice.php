<?php

/**
 * Create Invoice Page
 * Generate new sales invoice
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
require_permission('sales', 'create');

$database = new Database();
$db = $database->getConnection();

$page_title = 'Create Invoice';

// Get branches
$branches_query = "SELECT * FROM branches WHERE branch_type IN ('main_office', 'sub_office', 'sales_center') AND status = 'active'";
$branches = $db->query($branches_query)->fetchAll();

// Get customers
$customers_query = "SELECT * FROM customers WHERE status = 'active' ORDER BY customer_name";
$customers = $db->query($customers_query)->fetchAll();

// Get products with stock
$products_query = "SELECT p.*, pc.category_name, 
                   SUM(i.quantity_available) as total_available
                   FROM products p
                   INNER JOIN product_categories pc ON p.category_id = pc.category_id
                   LEFT JOIN inventory i ON p.product_id = i.product_id
                   WHERE p.status = 'active'
                   GROUP BY p.product_id
                   HAVING total_available > 0
                   ORDER BY p.product_name";
$products = $db->query($products_query)->fetchAll();

// Get warehouses
$warehouses_query = "SELECT * FROM warehouses WHERE status = 'active' ORDER BY warehouse_name";
$warehouses = $db->query($warehouses_query)->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-file-invoice"></i> Create New Invoice</h2>
        <a href="invoices.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Invoices
        </a>
    </div>

    <form id="invoiceForm" method="POST" action="process_invoice.php">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <div class="row">
            <div class="col-lg-8">
                <!-- Invoice Details Card -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> Invoice Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Branch/Office <span class="text-danger">*</span></label>
                                <select class="form-select" name="branch_id" required>
                                    <option value="">Select Branch</option>
                                    <?php foreach ($branches as $branch): ?>
                                        <option value="<?php echo $branch['branch_id']; ?>"
                                            <?php echo ($_SESSION['branch_id'] == $branch['branch_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($branch['branch_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Invoice Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="invoice_date"
                                    value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Customer <span class="text-danger">*</span></label>
                                <select class="form-select" name="customer_id" id="customerSelect" required>
                                    <option value="">Select Customer</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?php echo $customer['customer_id']; ?>"
                                            data-credit-limit="<?php echo $customer['credit_limit']; ?>"
                                            data-credit-days="<?php echo $customer['credit_days']; ?>">
                                            <?php echo htmlspecialchars($customer['customer_name']); ?>
                                            (<?php echo $customer['customer_code']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                                <select class="form-select" name="payment_method" id="paymentMethod" required>
                                    <option value="cash">Cash</option>
                                    <option value="credit">Credit</option>
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="cheque">Cheque</option>
                                </select>
                            </div>

                            <div class="col-md-6" id="creditDaysField" style="display: none;">
                                <label class="form-label">Credit Days</label>
                                <input type="number" class="form-control" name="credit_days" id="creditDays" min="0" value="0">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="2"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Items Card -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-shopping-cart"></i> Invoice Items</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="itemsTable">
                                <thead>
                                    <tr>
                                        <th width="25%">Product</th>
                                        <th width="20%">Warehouse</th>
                                        <th width="12%">Quantity</th>
                                        <th width="13%">Unit Price</th>
                                        <th width="10%">Discount %</th>
                                        <th width="15%">Total</th>
                                        <th width="5%">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="itemsTableBody">
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">
                                            Click "Add Item" to add products
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <button type="button" class="btn btn-success" onclick="addItem()">
                            <i class="fas fa-plus"></i> Add Item
                        </button>
                    </div>
                </div>
            </div>

            <!-- Summary Sidebar -->
            <div class="col-lg-4">
                <div class="card sticky-top" style="top: 20px;">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-calculator"></i> Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <strong id="subtotalDisplay">LKR 0.00</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Discount:</span>
                            <strong id="discountDisplay" class="text-danger">LKR 0.00</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Tax (0%):</span>
                            <strong id="taxDisplay">LKR 0.00</strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between mb-3">
                            <h5>Total:</h5>
                            <h5 id="totalDisplay" class="text-primary">LKR 0.00</h5>
                        </div>

                        <input type="hidden" name="subtotal" id="subtotalInput" value="0">
                        <input type="hidden" name="discount_amount" id="discountInput" value="0">
                        <input type="hidden" name="tax_amount" id="taxInput" value="0">
                        <input type="hidden" name="total_amount" id="totalInput" value="0">

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Generate Invoice
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    let itemCounter = 0;
    let products = <?php echo json_encode($products); ?>;
    let warehouses = <?php echo json_encode($warehouses); ?>;

    // Payment method change
    document.getElementById('paymentMethod').addEventListener('change', function() {
        const creditField = document.getElementById('creditDaysField');
        if (this.value === 'credit') {
            creditField.style.display = 'block';
            const selected = document.getElementById('customerSelect').selectedOptions[0];
            if (selected) {
                document.getElementById('creditDays').value = selected.dataset.creditDays || 0;
            }
        } else {
            creditField.style.display = 'none';
            document.getElementById('creditDays').value = 0;
        }
    });

    // Customer selection
    document.getElementById('customerSelect').addEventListener('change', function() {
        const paymentMethod = document.getElementById('paymentMethod').value;
        if (paymentMethod === 'credit') {
            const selected = this.selectedOptions[0];
            document.getElementById('creditDays').value = selected.dataset.creditDays || 0;
        }
    });

    function addItem() {
        itemCounter++;
        const tbody = document.getElementById('itemsTableBody');

        // Remove empty message if exists
        if (tbody.rows.length === 1 && tbody.rows[0].cells.length === 1) {
            tbody.innerHTML = '';
        }

        const row = tbody.insertRow();
        row.id = 'item_' + itemCounter;

        let productsOptions = '<option value="">Select Product</option>';
        products.forEach(product => {
            productsOptions += `<option value="${product.product_id}" 
                            data-price="${product.standard_price}" 
                            data-available="${product.total_available}">
                            ${product.product_name} (Available: ${product.total_available})
                            </option>`;
        });

        let warehousesOptions = '<option value="">Select Warehouse</option>';
        warehouses.forEach(warehouse => {
            warehousesOptions += `<option value="${warehouse.warehouse_id}">${warehouse.warehouse_name}</option>`;
        });

        row.innerHTML = `
        <td>
            <select class="form-select form-select-sm" name="items[${itemCounter}][product_id]" 
                    onchange="updateItemPrice(${itemCounter})" required>
                ${productsOptions}
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm" name="items[${itemCounter}][warehouse_id]" required>
                ${warehousesOptions}
            </select>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm" 
                   name="items[${itemCounter}][quantity]" step="0.001" min="0.001" 
                   onchange="calculateItemTotal(${itemCounter})" required>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm" 
                   name="items[${itemCounter}][unit_price]" step="0.01" min="0" 
                   onchange="calculateItemTotal(${itemCounter})" required>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm" 
                   name="items[${itemCounter}][discount_percent]" step="0.01" min="0" max="100" value="0"
                   onchange="calculateItemTotal(${itemCounter})">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm" 
                   name="items[${itemCounter}][line_total]" readonly>
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeItem(${itemCounter})">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    }

    function updateItemPrice(itemId) {
        const row = document.getElementById('item_' + itemId);
        const productSelect = row.querySelector('[name*="[product_id]"]');
        const priceInput = row.querySelector('[name*="[unit_price]"]');

        const selectedOption = productSelect.selectedOptions[0];
        if (selectedOption) {
            priceInput.value = selectedOption.dataset.price || 0;
            calculateItemTotal(itemId);
        }
    }

    function calculateItemTotal(itemId) {
        const row = document.getElementById('item_' + itemId);
        const quantity = parseFloat(row.querySelector('[name*="[quantity]"]').value) || 0;
        const unitPrice = parseFloat(row.querySelector('[name*="[unit_price]"]').value) || 0;
        const discountPercent = parseFloat(row.querySelector('[name*="[discount_percent]"]').value) || 0;

        const subtotal = quantity * unitPrice;
        const discountAmount = subtotal * (discountPercent / 100);
        const total = subtotal - discountAmount;

        row.querySelector('[name*="[line_total]"]').value = total.toFixed(2);

        calculateInvoiceTotal();
    }

    function removeItem(itemId) {
        const row = document.getElementById('item_' + itemId);
        row.remove();

        const tbody = document.getElementById('itemsTableBody');
        if (tbody.rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">Click "Add Item" to add products</td></tr>';
        }

        calculateInvoiceTotal();
    }

    function calculateInvoiceTotal() {
        let subtotal = 0;
        let discount = 0;

        const tbody = document.getElementById('itemsTableBody');
        for (let row of tbody.rows) {
            if (row.cells.length > 1) {
                const quantity = parseFloat(row.querySelector('[name*="[quantity]"]').value) || 0;
                const unitPrice = parseFloat(row.querySelector('[name*="[unit_price]"]').value) || 0;
                const discountPercent = parseFloat(row.querySelector('[name*="[discount_percent]"]').value) || 0;

                const lineSubtotal = quantity * unitPrice;
                const lineDiscount = lineSubtotal * (discountPercent / 100);

                subtotal += lineSubtotal;
                discount += lineDiscount;
            }
        }

        const tax = 0; // Can be calculated based on requirements
        const total = subtotal - discount + tax;

        document.getElementById('subtotalInput').value = subtotal.toFixed(2);
        document.getElementById('discountInput').value = discount.toFixed(2);
        document.getElementById('taxInput').value = tax.toFixed(2);
        document.getElementById('totalInput').value = total.toFixed(2);

        document.getElementById('subtotalDisplay').textContent = 'LKR ' + subtotal.toFixed(2);
        document.getElementById('discountDisplay').textContent = 'LKR ' + discount.toFixed(2);
        document.getElementById('taxDisplay').textContent = 'LKR ' + tax.toFixed(2);
        document.getElementById('totalDisplay').textContent = 'LKR ' + total.toFixed(2);
    }

    // Form validation
    document.getElementById('invoiceForm').addEventListener('submit', function(e) {
        const tbody = document.getElementById('itemsTableBody');
        if (tbody.rows.length === 0 || (tbody.rows.length === 1 && tbody.rows[0].cells.length === 1)) {
            e.preventDefault();
            alert('Please add at least one item to the invoice');
            return false;
        }

        const total = parseFloat(document.getElementById('totalInput').value);
        if (total <= 0) {
            e.preventDefault();
            alert('Invoice total must be greater than zero');
            return false;
        }
    });

    // Add first item by default
    window.onload = function() {
        addItem();
    };
</script>

<?php include '../includes/footer.php'; ?>