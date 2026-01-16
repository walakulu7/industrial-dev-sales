<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();
require_permission('credit', 'create');

$database = new Database();
$db = $database->getConnection();

$page_title = 'Record Payment';

$credit_id = isset($_GET['credit_id']) ? (int)$_GET['credit_id'] : 0;

// Get credit sale details
$credit_sale = null;
if ($credit_id) {
    $query = "SELECT cs.*, c.customer_name, c.customer_code, si.invoice_number
              FROM credit_sales cs
              INNER JOIN customers c ON cs.customer_id = c.customer_id
              INNER JOIN sales_invoices si ON cs.invoice_id = si.invoice_id
              WHERE cs.credit_id = :credit_id";

    $stmt = $db->prepare($query);
    $stmt->execute([':credit_id' => $credit_id]);
    $credit_sale = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $credit_id = (int)$_POST['credit_id'];
        $payment_amount = (float)$_POST['payment_amount'];
        $payment_date = $_POST['payment_date'];
        $payment_method = $_POST['payment_method'];
        $payment_reference = sanitize_input($_POST['payment_reference'] ?? '');
        $notes = sanitize_input($_POST['notes'] ?? '');

        // Insert payment
        $insert_payment = $db->prepare("INSERT INTO credit_payments 
                                       (credit_id, payment_date, payment_method, payment_reference, amount, notes, created_by)
                                       VALUES 
                                       (:credit_id, :payment_date, :payment_method, :payment_reference, :amount, :notes, :created_by)");
        $insert_payment->execute([
            ':credit_id' => $credit_id,
            ':payment_date' => $payment_date,
            ':payment_method' => $payment_method,
            ':payment_reference' => $payment_reference,
            ':amount' => $payment_amount,
            ':notes' => $notes,
            ':created_by' => $_SESSION['user_id']
        ]);

        // Update credit sale
        $update_credit = $db->prepare("UPDATE credit_sales 
                                       SET paid_amount = paid_amount + :amount,
                                           status = CASE 
                                               WHEN (paid_amount + :amount) >= total_amount THEN 'paid'
                                               WHEN (paid_amount + :amount) > 0 THEN 'partial'
                                               ELSE status
                                           END
                                       WHERE credit_id = :credit_id");
        $update_credit->execute([':amount' => $payment_amount, ':credit_id' => $credit_id]);

        // Update invoice
        $get_invoice = $db->prepare("SELECT invoice_id FROM credit_sales WHERE credit_id = :credit_id");
        $get_invoice->execute([':credit_id' => $credit_id]);
        $invoice = $get_invoice->fetch();

        $update_invoice = $db->prepare("UPDATE sales_invoices 
                                        SET paid_amount = paid_amount + :amount,
                                            status = CASE 
                                                WHEN (paid_amount + :amount) >= total_amount THEN 'paid'
                                                WHEN (paid_amount + :amount) > 0 THEN 'partial'
                                                ELSE status
                                            END
                                        WHERE invoice_id = :invoice_id");
        $update_invoice->execute([':amount' => $payment_amount, ':invoice_id' => $invoice['invoice_id']]);

        $db->commit();

        $_SESSION['success'] = 'Payment recorded successfully';
        header("Location: credit_sales.php");
        exit();
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
}

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-dollar-sign"></i> Record Payment</h2>
        <a href="credit_sales.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Credit Sales
        </a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?php echo $_SESSION['error'];
            unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($credit_sale): ?>
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <!-- Credit Sale Info -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Credit Sale Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Invoice #:</strong> <?php echo htmlspecialchars($credit_sale['invoice_number']); ?></p>
                                <p><strong>Customer:</strong> <?php echo htmlspecialchars($credit_sale['customer_name']); ?></p>
                                <p><strong>Invoice Date:</strong> <?php echo format_date($credit_sale['invoice_date']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Total Amount:</strong> <?php echo format_currency($credit_sale['total_amount']); ?></p>
                                <p><strong>Paid Amount:</strong> <?php echo format_currency($credit_sale['paid_amount']); ?></p>
                                <p><strong>Balance:</strong> <span class="text-danger fw-bold"><?php echo format_currency($credit_sale['balance_amount']); ?></span></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Form -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Payment Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="credit_id" value="<?php echo $credit_id; ?>">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="payment_date"
                                        value="<?php echo date('Y-m-d'); ?>" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Payment Amount <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="payment_amount"
                                        step="0.01" min="0.01" max="<?php echo $credit_sale['balance_amount']; ?>"
                                        value="<?php echo $credit_sale['balance_amount']; ?>" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Payment Method <span class="text-danger">*</span></label>
                                    <select class="form-select" name="payment_method" required>
                                        <option value="cash">Cash</option>
                                        <option value="bank_transfer">Bank Transfer</option>
                                        <option value="cheque">Cheque</option>
                                        <option value="card">Card</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Reference Number</label>
                                    <input type="text" class="form-control" name="payment_reference"
                                        placeholder="Cheque #, Transaction ID, etc.">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" name="notes" rows="3"></textarea>
                                </div>

                                <div class="col-12">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check"></i> Record Payment
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-warning">
            Credit sale not found or invalid.
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>