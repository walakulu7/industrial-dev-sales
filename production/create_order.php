<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
require_permission('production', 'create');

$database = new Database();
$db = $database->getConnection();

$page_title = 'Create Production Order';

// Get production centers
$centers = $db->query("SELECT * FROM production_centers WHERE status = 'active' ORDER BY center_name")->fetchAll();

// Get products
$products = $db->query("SELECT p.*, pc.category_name 
                        FROM products p
                        INNER JOIN product_categories pc ON p.category_id = pc.category_id
                        WHERE p.status = 'active' AND pc.category_type IN ('finished_product', 'yarn')
                        ORDER BY p.product_name")->fetchAll();

// Get warehouses
$warehouses = $db->query("SELECT * FROM warehouses WHERE status = 'active' ORDER BY warehouse_name")->fetchAll();

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-plus-circle"></i> Create Production Order</h2>
        <a href="orders.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Orders
        </a>
    </div>

    <form id="productionOrderForm" method="POST" action="process_order.php">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <div class="row">
            <div class="col-lg-8">
                <!-- Order Details -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> Order Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Production Center <span class="text-danger">*</span></label>
                                <select class="form-select" name="production_center_id" id="centerSelect" required>
                                    <option value="">Select Center</option>
                                    <?php foreach ($centers as $center): ?>
                                        <option value="<?php echo $center['production_center_id']; ?>">
                                            <?php echo htmlspecialchars($center['center_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Production Line</label>
                                <select class="form-select" name="production_line_id" id="lineSelect">
                                    <option value="">Select Line (Optional)</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Order Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="order_date"
                                    value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Product <span class="text-danger">*</span></label>
                                <select class="form-select" name="product_id" id="productSelect" required>
                                    <option value="">Select Product</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?php echo $product['product_id']; ?>"
                                            data-uom="<?php echo $product['unit_of_measure']; ?>">
                                            <?php echo htmlspecialchars($product['product_code'] . ' - ' . $product['product_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Planned Quantity <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="planned_quantity"
                                        step="0.001" min="0.001" required>
                                    <span class="input-group-text" id="uomDisplay">-</span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date"
                                    value="<?php echo date('Y-m-d'); ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Expected End Date</label>
                                <input type="date" class="form-control" name="end_date">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Raw Materials / Inputs -->
                <div class="card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-cubes"></i> Raw Materials / Inputs</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="inputsTable">
                                <thead>
                                    <tr>
                                        <th width="35%">Material/Product</th>
                                        <th width="25%">Warehouse</th>
                                        <th width="15%">Required Qty</th>
                                        <th width="20%">Available</th>
                                        <th width="5%">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="inputsTableBody">
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">
                                            Click "Add Material" to add inputs
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <button type="button" class="btn btn-success" onclick="addInput()">
                            <i class="fas fa-plus"></i> Add Material
                        </button>
                    </div>
                </div>
            </div>

            <!-- Summary Sidebar -->
            <div class="col-lg-4">
                <div class="card sticky-top" style="top: 20px;">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-clipboard-check"></i> Order Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="text-muted">Production Center:</label>
                            <div id="summaryCenter" class="fw-bold">-</div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted">Product:</label>
                            <div id="summaryProduct" class="fw-bold">-</div>
                        </div>
                        <div class="mb-3">
                            <label class="text-muted">Planned Quantity:</label>
                            <div id="summaryQuantity" class="fw-bold">-</div>
                        </div>
                        <hr>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Create Order
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    let inputCounter = 0;
    let allProducts = <?php echo json_encode($products); ?>;
    let warehouses = <?php echo json_encode($warehouses); ?>;

    // Load production lines when center is selected
    document.getElementById('centerSelect').addEventListener('change', function() {
        const centerId = this.value;
        const lineSelect = document.getElementById('lineSelect');
        lineSelect.innerHTML = '<option value="">Loading...</option>';

        if (centerId) {
            fetch(`get_lines.php?center_id=${centerId}`)
                .then(response => response.json())
                .then(data => {
                    lineSelect.innerHTML = '<option value="">Select Line (Optional)</option>';
                    data.forEach(line => {
                        lineSelect.innerHTML += `<option value="${line.line_id}">${line.line_name}</option>`;
                    });
                });

            document.getElementById('summaryCenter').textContent = this.selectedOptions[0].text;
        } else {
            lineSelect.innerHTML = '<option value="">Select Line (Optional)</option>';
            document.getElementById('summaryCenter').textContent = '-';
        }
    });

    // Update UOM when product is selected
    document.getElementById('productSelect').addEventListener('change', function() {
        const selectedOption = this.selectedOptions[0];
        const uom = selectedOption.dataset.uom || '-';
        document.getElementById('uomDisplay').textContent = uom;
        document.getElementById('summaryProduct').textContent = selectedOption ? selectedOption.text : '-';
    });

    // Update summary quantity
    document.querySelector('[name="planned_quantity"]').addEventListener('input', function() {
        const qty = this.value || '-';
        const uom = document.getElementById('uomDisplay').textContent;
        document.getElementById('summaryQuantity').textContent = qty + ' ' + uom;
    });

    function addInput() {
        inputCounter++;
        const tbody = document.getElementById('inputsTableBody');

        if (tbody.rows.length === 1 && tbody.rows[0].cells.length === 1) {
            tbody.innerHTML = '';
        }

        const row = tbody.insertRow();
        row.id = 'input_' + inputCounter;

        let productsOptions = '<option value="">Select Material</option>';
        allProducts.forEach(product => {
            productsOptions += `<option value="${product.product_id}" 
                            data-uom="${product.unit_of_measure}">
                            ${product.product_code} - ${product.product_name}
                            </option>`;
        });

        let warehousesOptions = '<option value="">Select Warehouse</option>';
        warehouses.forEach(warehouse => {
            warehousesOptions += `<option value="${warehouse.warehouse_id}">${warehouse.warehouse_name}</option>`;
        });

        row.innerHTML = `
        <td>
            <select class="form-select form-select-sm" name="inputs[${inputCounter}][product_id]" 
                    onchange="checkInputStock(${inputCounter})" required>
                ${productsOptions}
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm" name="inputs[${inputCounter}][warehouse_id]" 
                    onchange="checkInputStock(${inputCounter})" required>
                ${warehousesOptions}
            </select>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm" 
                   name="inputs[${inputCounter}][quantity]" step="0.001" min="0.001" required>
        </td>
        <td>
            <span id="available_${inputCounter}" class="badge bg-secondary">-</span>
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeInput(${inputCounter})">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    }

    function removeInput(inputId) {
        document.getElementById('input_' + inputId).remove();

        const tbody = document.getElementById('inputsTableBody');
        if (tbody.rows.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Click "Add Material" to add inputs</td></tr>';
        }
    }

    function checkInputStock(inputId) {
        const row = document.getElementById('input_' + inputId);
        const productId = row.querySelector('[name*="[product_id]"]').value;
        const warehouseId = row.querySelector('[name*="[warehouse_id]"]').value;

        if (productId && warehouseId) {
            fetch(`../inventory/get_stock.php?warehouse_id=${warehouseId}&product_id=${productId}`)
                .then(response => response.json())
                .then(data => {
                    const badge = document.getElementById('available_' + inputId);
                    if (data.success) {
                        badge.textContent = parseFloat(data.quantity).toFixed(2);
                        badge.className = data.quantity > 0 ? 'badge bg-success' : 'badge bg-danger';
                    }
                });
        }
    }
</script>

<?php include '../includes/footer.php'; ?>