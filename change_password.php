<?php

/**
 * Change Password Page
 * Allow users to change their password
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

require_login();

$database = new Database();
$db = $database->getConnection();

$page_title = 'Change Password';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $errors = [];

    // Validation
    if (empty($old_password)) {
        $errors[] = 'Current password is required';
    }

    if (empty($new_password)) {
        $errors[] = 'New password is required';
    } elseif (strlen($new_password) < 6) {
        $errors[] = 'New password must be at least 6 characters';
    }

    if ($new_password !== $confirm_password) {
        $errors[] = 'New passwords do not match';
    }

    if (empty($errors)) {
        try {
            // Get current password
            $query = "SELECT password_hash FROM users WHERE user_id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->execute([':user_id' => $_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($old_password, $user['password_hash'])) {
                $errors[] = 'Current password is incorrect';
            } else {
                // Update password
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query = "UPDATE users SET password_hash = :password WHERE user_id = :user_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute([
                    ':password' => $new_hash,
                    ':user_id' => $_SESSION['user_id']
                ]);

                // Log audit
                log_audit($_SESSION['user_id'], 'change_password', 'users', $_SESSION['user_id']);

                redirect_with_message('profile.php', 'Password changed successfully', 'success');
            }
        } catch (Exception $e) {
            error_log("Change Password Error: " . $e->getMessage());
            $errors[] = 'An error occurred. Please try again.';
        }
    }

    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
    }
}

include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-key"></i> Change Password</h2>
        <a href="profile.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Profile
        </a>
    </div>

    <div class="row">
        <div class="col-lg-6 mx-auto">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Change Your Password</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Password must be at least 6 characters long.
                    </div>

                    <form method="POST" onsubmit="return validatePassword()">
                        <div class="mb-3">
                            <label class="form-label">Current Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" name="old_password" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="new_password"
                                name="new_password" minlength="6" required>
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_password"
                                name="confirm_password" minlength="6" required>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function validatePassword() {
        const newPass = document.getElementById('new_password').value;
        const confirmPass = document.getElementById('confirm_password').value;

        if (newPass !== confirmPass) {
            alert('New passwords do not match!');
            return false;
        }

        if (newPass.length < 6) {
            alert('Password must be at least 6 characters long!');
            return false;
        }

        return true;
    }
</script>

<?php include 'includes/footer.php'; ?>