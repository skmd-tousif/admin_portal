<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "mydb";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = '';
$error = '';
$valid_token = false;

// Check if token is provided and valid
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $sql = "SELECT admin_id FROM admin WHERE reset_token = ? AND reset_expires > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $valid_token = true;
        $admin = $result->fetch_assoc();
        $admin_id = $admin['admin_id'];
    } else {
        $error = "Invalid or expired reset token";
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Both password fields are required";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long";
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password and clear reset token
        $sql = "UPDATE admin SET password = ?, reset_token = NULL, reset_expires = NULL WHERE admin_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $hashed_password, $admin_id);
        
        if ($stmt->execute()) {
            $message = "Password has been reset successfully. You can now <a href='index.php'>login</a> with your new password.";
            $valid_token = false; // Token is now invalid
        } else {
            $error = "An error occurred while resetting your password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1rem;
        }
        
        .reset-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            padding: 2.5rem;
            width: 100%;
            max-width: 28rem;
            color: #0f172a;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            border: none;
        }
        
        .password-strength {
            height: 5px;
            margin-top: 5px;
            background: #e2e8f0;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .password-strength-bar {
            height: 100%;
            width: 0%;
            transition: width 0.3s;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <h2 class="text-center mb-4">Reset Password</h2>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php elseif ($valid_token): ?>
            <form method="POST" id="resetForm">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token']); ?>">
                
                <div class="mb-3">
                    <label for="password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="password-strength">
                        <div class="password-strength-bar" id="passwordStrengthBar"></div>
                    </div>
                    <small class="text-muted">Minimum 8 characters</small>
                </div>
                
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    <div class="text-danger" id="passwordMatchError" style="display: none;">Passwords do not match</div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100" id="resetButton">Reset Password</button>
            </form>
        <?php else: ?>
            <div class="alert alert-warning">Invalid password reset link. Please request a new one.</div>
            <div class="text-center mt-3">
                <a href="forgot_password.php" class="text-decoration-none">Request New Reset Link</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const passwordMatchError = document.getElementById('passwordMatchError');
            const passwordStrengthBar = document.getElementById('passwordStrengthBar');
            const resetForm = document.getElementById('resetForm');
            
            // Password strength indicator
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                // Length check
                if (password.length >= 8) strength += 25;
                if (password.length >= 12) strength += 25;
                
                // Complexity checks
                if (/[A-Z]/.test(password)) strength += 15;
                if (/[0-9]/.test(password)) strength += 15;
                if (/[^A-Za-z0-9]/.test(password)) strength += 20;
                
                // Update strength bar
                passwordStrengthBar.style.width = Math.min(strength, 100) + '%';
                
                // Color based on strength
                if (strength < 50) {
                    passwordStrengthBar.style.backgroundColor = '#ef4444'; // red
                } else if (strength < 75) {
                    passwordStrengthBar.style.backgroundColor = '#f59e0b'; // yellow
                } else {
                    passwordStrengthBar.style.backgroundColor = '#10b981'; // green
                }
            });
            
            // Password match check
            confirmPasswordInput.addEventListener('input', function() {
                if (passwordInput.value !== this.value && this.value.length > 0) {
                    passwordMatchError.style.display = 'block';
                } else {
                    passwordMatchError.style.display = 'none';
                }
            });
            
            // Form validation
            resetForm.addEventListener('submit', function(e) {
                if (passwordInput.value !== confirmPasswordInput.value) {
                    e.preventDefault();
                    passwordMatchError.style.display = 'block';
                    confirmPasswordInput.focus();
                }
                
                if (passwordInput.value.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long');
                    passwordInput.focus();
                }
            });
        });
    </script>
</body>
</html>