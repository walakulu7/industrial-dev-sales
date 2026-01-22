<?php
require_once '../config/database.php';
require_once '../config/config.php';
require_once '../includes/functions.php';

/**
 * Check authentication - wrapper for require_login()
 * Alias for require_login() for backwards compatibility
 */
function checkAuth()
{
    return require_login();
}

/**
 * Check user permission - wrapper for has_permission()
 * @param string $permission Permission name (can be single or simplified)
 * @return bool
 */
function hasPermission($permission)
{
    // Check if permission exists in user's permissions
    if (!isset($_SESSION['permissions'])) {
        return false;
    }

    $permissions = json_decode($_SESSION['permissions'], true);

    // Administrator has all permissions
    if (isset($permissions['modules']) && in_array('all', $permissions['modules'])) {
        return true;
    }

    // Check in modules array
    if (isset($permissions['modules']) && in_array($permission, $permissions['modules'])) {
        return true;
    }

    // Check in permissions array
    if (isset($permissions['permissions']) && in_array($permission, $permissions['permissions'])) {
        return true;
    }

    return false;
}

/**
 * Set flash message for display after redirect
 * @param string $message Message text
 * @param string $type Type of message (success, error, info, warning)
 */
function setFlashMessage($message, $type = 'success')
{
    $_SESSION[$type] = $message;
}

/**
 * Display flash message with HTML
 * @return string HTML of flash messages
 */
function displayFlashMessage()
{
    $html = '';
    $types = ['success', 'error', 'info', 'warning'];
    
    foreach ($types as $type) {
        if (isset($_SESSION[$type])) {
            $message = $_SESSION[$type];
            $class_map = [
                'success' => 'alert-success',
                'error' => 'alert-danger',
                'info' => 'alert-info',
                'warning' => 'alert-warning'
            ];
            $class = $class_map[$type] ?? 'alert-info';
            $icon_map = [
                'success' => 'fa-check-circle',
                'error' => 'fa-exclamation-circle',
                'info' => 'fa-info-circle',
                'warning' => 'fa-exclamation-triangle'
            ];
            $icon = $icon_map[$type] ?? 'fa-info-circle';
            
            $html .= '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">';
            $html .= '<i class="fas ' . $icon . ' me-2"></i>';
            $html .= htmlspecialchars($message);
            $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            $html .= '</div>';
            
            unset($_SESSION[$type]);
        }
    }
    
    return $html;
}

/**
 * Redirect to URL
 * @param string $path Path or URL to redirect to
 */
function redirect($path)
{
    header("Location: " . $path);
    exit();
}

// Check authentication
checkAuth();

// Check permission
if (!hasPermission('accounting') && !hasPermission('all')) {
    redirect('dashboard.php');
}

$database = new Database();
$db = $database->getConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        try {
            $query = "INSERT INTO fixed_assets (
                asset_code, asset_name, asset_category, branch_id,
                purchase_date, purchase_value, salvage_value, useful_life_years,
                depreciation_method, location, serial_number, supplier, notes
            ) VALUES (
                :code, :name, :category, :branch_id,
                :purchase_date, :purchase_value, :salvage_value, :useful_life,
                :depreciation_method, :location, :serial_number, :supplier, :notes
            )";

            $stmt = $db->prepare($query);
            $stmt->execute([
                ':code' => $_POST['asset_code'],
                ':name' => $_POST['asset_name'],
                ':category' => $_POST['asset_category'],
                ':branch_id' => $_POST['branch_id'],
                ':purchase_date' => $_POST['purchase_date'],
                ':purchase_value' => $_POST['purchase_value'],
                ':salvage_value' => $_POST['salvage_value'] ?? 0,
                ':useful_life' => $_POST['useful_life_years'],
                ':depreciation_method' => $_POST['depreciation_method'],
                ':location' => $_POST['location'] ?? null,
                ':serial_number' => $_POST['serial_number'] ?? null,
                ':supplier' => $_POST['supplier'] ?? null,
                ':notes' => $_POST['notes'] ?? null
            ]);

            setFlashMessage('Fixed asset added successfully', 'success');
            redirect('assets.php');
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'edit') {
        try {
            $query = "UPDATE fixed_assets SET 
                asset_code = :code,
                asset_name = :name,
                asset_category = :category,
                branch_id = :branch_id,
                purchase_date = :purchase_date,
                purchase_value = :purchase_value,
                salvage_value = :salvage_value,
                useful_life_years = :useful_life,
                depreciation_method = :depreciation_method,
                location = :location,
                serial_number = :serial_number,
                supplier = :supplier,
                notes = :notes,
                status = :status
            WHERE asset_id = :asset_id";

            $stmt = $db->prepare($query);
            $stmt->execute([
                ':code' => $_POST['asset_code'],
                ':name' => $_POST['asset_name'],
                ':category' => $_POST['asset_category'],
                ':branch_id' => $_POST['branch_id'],
                ':purchase_date' => $_POST['purchase_date'],
                ':purchase_value' => $_POST['purchase_value'],
                ':salvage_value' => $_POST['salvage_value'] ?? 0,
                ':useful_life' => $_POST['useful_life_years'],
                ':depreciation_method' => $_POST['depreciation_method'],
                ':location' => $_POST['location'] ?? null,
                ':serial_number' => $_POST['serial_number'] ?? null,
                ':supplier' => $_POST['supplier'] ?? null,
                ':notes' => $_POST['notes'] ?? null,
                ':status' => $_POST['status'],
                ':asset_id' => $_POST['asset_id']
            ]);

            setFlashMessage('Fixed asset updated successfully', 'success');
            redirect('assets.php');
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } elseif ($action === 'dispose') {
        try {
            $query = "UPDATE fixed_assets SET 
                status = 'disposed',
                disposal_date = :disposal_date,
                disposal_value = :disposal_value
            WHERE asset_id = :asset_id";

            $stmt = $db->prepare($query);
            $stmt->execute([
                ':disposal_date' => $_POST['disposal_date'],
                ':disposal_value' => $_POST['disposal_value'],
                ':asset_id' => $_POST['asset_id']
            ]);

            setFlashMessage('Asset disposal recorded successfully', 'success');
            redirect('assets.php');
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Get filter parameters
$filter_branch = $_GET['branch_id'] ?? '';
$filter_category = $_GET['category'] ?? '';
$filter_status = $_GET['status'] ?? 'active';

// Build query
$query = "SELECT 
    fa.*,
    b.branch_name,
    (SELECT SUM(depreciation_amount) 
     FROM depreciation_records 
     WHERE asset_id = fa.asset_id) as total_depreciation
FROM fixed_assets fa
JOIN branches b ON fa.branch_id = b.branch_id
WHERE 1=1";

$params = [];

if ($filter_branch) {
    $query .= " AND fa.branch_id = :branch_id";
    $params[':branch_id'] = $filter_branch;
}

if ($filter_category) {
    $query .= " AND fa.asset_category = :category";
    $params[':category'] = $filter_category;
}

if ($filter_status) {
    $query .= " AND fa.status = :status";
    $params[':status'] = $filter_status;
}

$query .= " ORDER BY fa.purchase_date DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get branches for dropdown
$branches_query = "SELECT * FROM branches WHERE status = 'active' ORDER BY branch_name";
$branches_stmt = $db->prepare($branches_query);
$branches_stmt->execute();
$branches = $branches_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary statistics
$total_assets = count($assets);
$total_value = array_sum(array_column($assets, 'purchase_value'));
$total_accumulated_depreciation = array_sum(array_column($assets, 'accumulated_depreciation'));
$total_book_value = $total_value - $total_accumulated_depreciation;

// Get asset for editing if ID provided
$edit_asset = null;
if (isset($_GET['edit'])) {
    $query = "SELECT * FROM fixed_assets WHERE asset_id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $_GET['edit']]);
    $edit_asset = $stmt->fetch(PDO::FETCH_ASSOC);
}

$page_title = 'Fixed Assets Register';
include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="fas fa-warehouse me-2"></i>Fixed Assets Register</h2>
        </div>
        <div class="col-md-6 text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assetModal" onclick="resetForm()">
                <i class="fas fa-plus me-1"></i>Add Asset
            </button>
            <button type="button" class="btn btn-warning" onclick="calculateDepreciation()">
                <i class="fas fa-calculator me-1"></i>Calculate Depreciation
            </button>
        </div>
    </div>

    <?php displayFlashMessage(); ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h6 class="card-title">Total Assets</h6>
                    <h3><?php echo number_format($total_assets); ?></h3>
                    <small>Registered assets</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h6 class="card-title">Purchase Value</h6>
                    <h3>Rs. <?php echo number_format($total_value, 2); ?></h3>
                    <small>Original cost</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h6 class="card-title">Accumulated Depreciation</h6>
                    <h3>Rs. <?php echo number_format($total_accumulated_depreciation, 2); ?></h3>
                    <small>Total depreciation</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="card-title">Net Book Value</h6>
                    <h3>Rs. <?php echo number_format($total_book_value, 2); ?></h3>
                    <small>Current value</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Branch</label>
                    <select class="form-select" name="branch_id">
                        <option value="">All Branches</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['branch_id']; ?>"
                                <?php echo $filter_branch == $branch['branch_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch['branch_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select class="form-select" name="category">
                        <option value="">All Categories</option>
                        <option value="building" <?php echo $filter_category === 'building' ? 'selected' : ''; ?>>Building</option>
                        <option value="machinery" <?php echo $filter_category === 'machinery' ? 'selected' : ''; ?>>Machinery</option>
                        <option value="vehicle" <?php echo $filter_category === 'vehicle' ? 'selected' : ''; ?>>Vehicle</option>
                        <option value="furniture" <?php echo $filter_category === 'furniture' ? 'selected' : ''; ?>>Furniture</option>
                        <option value="computer" <?php echo $filter_category === 'computer' ? 'selected' : ''; ?>>Computer</option>
                        <option value="other" <?php echo $filter_category === 'other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="disposed" <?php echo $filter_status === 'disposed' ? 'selected' : ''; ?>>Disposed</option>
                        <option value="sold" <?php echo $filter_status === 'sold' ? 'selected' : ''; ?>>Sold</option>
                        <option value="written_off" <?php echo $filter_status === 'written_off' ? 'selected' : ''; ?>>Written Off</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Assets Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Fixed Assets List</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="assetsTable">
                    <thead>
                        <tr>
                            <th>Asset Code</th>
                            <th>Asset Name</th>
                            <th>Category</th>
                            <th>Branch</th>
                            <th>Purchase Date</th>
                            <th>Purchase Value</th>
                            <th>Accumulated Dep.</th>
                            <th>Book Value</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assets as $asset): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($asset['asset_code']); ?></strong></td>
                                <td>
                                    <?php echo htmlspecialchars($asset['asset_name']); ?>
                                    <?php if ($asset['serial_number']): ?>
                                        <br><small class="text-muted">SN: <?php echo htmlspecialchars($asset['serial_number']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $category_icons = [
                                        'building' => 'fa-building',
                                        'machinery' => 'fa-cogs',
                                        'vehicle' => 'fa-car',
                                        'furniture' => 'fa-couch',
                                        'computer' => 'fa-laptop',
                                        'other' => 'fa-box'
                                    ];
                                    $icon = $category_icons[$asset['asset_category']] ?? 'fa-box';
                                    ?>
                                    <i class="fas <?php echo $icon; ?> me-1"></i>
                                    <?php echo ucfirst($asset['asset_category']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($asset['branch_name']); ?></td>
                                <td><?php echo date('d M Y', strtotime($asset['purchase_date'])); ?></td>
                                <td>Rs. <?php echo number_format($asset['purchase_value'], 2); ?></td>
                                <td>Rs. <?php echo number_format($asset['accumulated_depreciation'], 2); ?></td>
                                <td>
                                    <strong>Rs. <?php echo number_format($asset['book_value'], 2); ?></strong>
                                    <?php
                                    $age_years = (strtotime('now') - strtotime($asset['purchase_date'])) / (365 * 24 * 60 * 60);
                                    $depreciation_pct = $asset['purchase_value'] > 0
                                        ? ($asset['accumulated_depreciation'] / $asset['purchase_value']) * 100
                                        : 0;
                                    ?>
                                    <br><small class="text-muted"><?php echo number_format($depreciation_pct, 1); ?>% depreciated</small>
                                </td>
                                <td>
                                    <?php
                                    $status_badges = [
                                        'active' => 'success',
                                        'disposed' => 'warning',
                                        'sold' => 'info',
                                        'written_off' => 'danger'
                                    ];
                                    $badge_color = $status_badges[$asset['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $badge_color; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $asset['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button class="btn btn-sm btn-info" onclick="viewAsset(<?php echo htmlspecialchars(json_encode($asset)); ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-primary" onclick="editAsset(<?php echo htmlspecialchars(json_encode($asset)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($asset['status'] === 'active'): ?>
                                            <button class="btn btn-sm btn-warning" onclick="disposeAsset(<?php echo $asset['asset_id']; ?>, '<?php echo htmlspecialchars($asset['asset_name']); ?>')">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Asset Modal -->
<div class="modal fade" id="assetModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Fixed Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="assetForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="asset_id" id="asset_id">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Asset Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="asset_code" id="asset_code" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Asset Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="asset_name" id="asset_name" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" name="asset_category" id="asset_category" required>
                                <option value="">Select Category</option>
                                <option value="building">Building</option>
                                <option value="machinery">Machinery</option>
                                <option value="vehicle">Vehicle</option>
                                <option value="furniture">Furniture</option>
                                <option value="computer">Computer Equipment</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Branch <span class="text-danger">*</span></label>
                            <select class="form-select" name="branch_id" id="branch_id" required>
                                <option value="">Select Branch</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo $branch['branch_id']; ?>">
                                        <?php echo htmlspecialchars($branch['branch_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Purchase Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="purchase_date" id="purchase_date" required max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Purchase Value (Rs.) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="purchase_value" id="purchase_value" step="0.01" min="0" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Salvage Value (Rs.)</label>
                            <input type="number" class="form-control" name="salvage_value" id="salvage_value" step="0.01" min="0" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Useful Life (Years) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="useful_life_years" id="useful_life_years" min="1" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Depreciation Method <span class="text-danger">*</span></label>
                            <select class="form-select" name="depreciation_method" id="depreciation_method" required>
                                <option value="straight_line">Straight Line</option>
                                <option value="declining_balance">Declining Balance</option>
                                <option value="units_of_production">Units of Production</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="status">
                                <option value="active">Active</option>
                                <option value="disposed">Disposed</option>
                                <option value="sold">Sold</option>
                                <option value="written_off">Written Off</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" class="form-control" name="location" id="location">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Serial Number</label>
                            <input type="text" class="form-control" name="serial_number" id="serial_number">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Supplier</label>
                        <input type="text" class="form-control" name="supplier" id="supplier">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save Asset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Asset Modal -->
<div class="modal fade" id="viewAssetModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Asset Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="assetDetailsContent">
                <!-- Content will be populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Dispose Asset Modal -->
<div class="modal fade" id="disposeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Dispose Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="dispose">
                <input type="hidden" name="asset_id" id="dispose_asset_id">
                <div class="modal-body">
                    <p>Dispose asset: <strong id="dispose_asset_name"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Disposal Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="disposal_date" required max="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Disposal Value (Rs.)</label>
                        <input type="number" class="form-control" name="disposal_value" step="0.01" min="0" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Dispose Asset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        $('#assetsTable').DataTable({
            order: [
                [4, 'desc']
            ],
            pageLength: 25
        });
    });

    function resetForm() {
        document.getElementById('assetForm').reset();
        document.getElementById('formAction').value = 'add';
        document.getElementById('asset_id').value = '';
        document.getElementById('modalTitle').textContent = 'Add Fixed Asset';
        document.getElementById('status').closest('.row').style.display = 'none';
    }

    function editAsset(asset) {
        document.getElementById('formAction').value = 'edit';
        document.getElementById('asset_id').value = asset.asset_id;
        document.getElementById('asset_code').value = asset.asset_code;
        document.getElementById('asset_name').value = asset.asset_name;
        document.getElementById('asset_category').value = asset.asset_category;
        document.getElementById('branch_id').value = asset.branch_id;
        document.getElementById('purchase_date').value = asset.purchase_date;
        document.getElementById('purchase_value').value = asset.purchase_value;
        document.getElementById('salvage_value').value = asset.salvage_value;
        document.getElementById('useful_life_years').value = asset.useful_life_years;
        document.getElementById('depreciation_method').value = asset.depreciation_method;
        document.getElementById('location').value = asset.location || '';
        document.getElementById('serial_number').value = asset.serial_number || '';
        document.getElementById('supplier').value = asset.supplier || '';
        document.getElementById('notes').value = asset.notes || '';
        document.getElementById('status').value = asset.status;
        document.getElementById('modalTitle').textContent = 'Edit Fixed Asset';
        document.getElementById('status').closest('.row').style.display = 'flex';

        var modal = new bootstrap.Modal(document.getElementById('assetModal'));
        modal.show();
    }

    function viewAsset(asset) {
        const depreciationPct = asset.purchase_value > 0 ?
            (asset.accumulated_depreciation / asset.purchase_value * 100).toFixed(1) :
            0;

        const content = `
        <div class="row">
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr><th>Asset Code:</th><td>${asset.asset_code}</td></tr>
                    <tr><th>Asset Name:</th><td>${asset.asset_name}</td></tr>
                    <tr><th>Category:</th><td>${asset.asset_category}</td></tr>
                    <tr><th>Branch:</th><td>${asset.branch_name}</td></tr>
                    <tr><th>Location:</th><td>${asset.location || 'N/A'}</td></tr>
                    <tr><th>Serial Number:</th><td>${asset.serial_number || 'N/A'}</td></tr>
                    <tr><th>Supplier:</th><td>${asset.supplier || 'N/A'}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr><th>Purchase Date:</th><td>${new Date(asset.purchase_date).toLocaleDateString()}</td></tr>
                    <tr><th>Purchase Value:</th><td>Rs. ${parseFloat(asset.purchase_value).toLocaleString()}</td></tr>
                    <tr><th>Salvage Value:</th><td>Rs. ${parseFloat(asset.salvage_value).toLocaleString()}</td></tr>
                    <tr><th>Useful Life:</th><td>${asset.useful_life_years} years</td></tr>
                    <tr><th>Depreciation Method:</th><td>${asset.depreciation_method.replace('_', ' ')}</td></tr>
                    <tr><th>Accumulated Depreciation:</th><td>Rs. ${parseFloat(asset.accumulated_depreciation).toLocaleString()}</td></tr>
                    <tr><th>Book Value:</th><td><strong>Rs. ${parseFloat(asset.book_value).toLocaleString()}</strong></td></tr>
                    <tr><th>Depreciation:</th><td><span class="badge bg-info">${depreciationPct}%</span></td></tr>
                    <tr><th>Status:</th><td><span class="badge bg-success">${asset.status}</span></td></tr>
                </table>
            </div>
        </div>
        ${asset.notes ? `<hr><h6>Notes:</h6><p>${asset.notes}</p>` : ''}
    `;

        document.getElementById('assetDetailsContent').innerHTML = content;
        var modal = new bootstrap.Modal(document.getElementById('viewAssetModal'));
        modal.show();
    }

    function disposeAsset(assetId, assetName) {
        document.getElementById('dispose_asset_id').value = assetId;
        document.getElementById('dispose_asset_name').textContent = assetName;
        var modal = new bootstrap.Modal(document.getElementById('disposeModal'));
        modal.show();
    }

    function calculateDepreciation() {
        if (confirm('Calculate depreciation for the current month?\n\nThis will process depreciation for all active assets.')) {
            window.location.href = 'calculate_depreciation.php';
        }
    }
</script>

<?php include '../includes/footer.php'; ?>