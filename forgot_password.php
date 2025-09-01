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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = "Email address is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        // Check if email exists
        $sql = "SELECT admin_id, admin_name, email FROM admin WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $admin = $result->fetch_assoc();
            
            // Generate reset token (valid for 1 hour)
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);
            
            // Store token in database
            $sql = "UPDATE admin SET reset_token = ?, reset_expires = ? WHERE admin_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $token, $expires, $admin['admin_id']);
            
            if ($stmt->execute()) {
                // Send email with reset link
                $reset_link = "https://admin.paceassignments.com/reset_password.php?token=$token";
                $to = $admin['email'];
                $subject = "Password Reset Request";
                $message = "Hello " . htmlspecialchars($admin['admin_name']) . ",\n\n";
                $message .= "You have requested to reset your password. Please click the following link to reset your password:\n\n";
                $message .= $reset_link . "\n\n";
                $message .= "This link will expire in 1 hour.\n\n";
                $message .= "If you didn't request this password reset, please ignore this email.\n\n";
                $message .= "Regards,\nAdmin Team";
                
                $headers = "From: no-reply@paceassignments.com\r\n";
                $headers .= "Reply-To: no-reply@paceassignments.com\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion();
                
                // Send email (in production, use a proper mail library like PHPMailer)
                if (mail($to, $subject, $message, $headers)) {
                    $message = "A password reset link has been sent to your email address. Please check your inbox (and spam folder).";
                } else {
                    $error = "Failed to send password reset email. Please try again later.";
                }
            } else {
                $error = "Error generating reset token. Please try again.";
            }
        } else {
            // Don't reveal whether email exists for security
            $message = "If an account exists with this email, a password reset link has been sent.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
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
        
        .alert-link {
            color: #0d6efd;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <h2 class="text-center mb-4">Forgot Password</h2>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <div class="text-center mt-3">
                <a href="index.php" class="btn btn-primary">Back to Login</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
            </form>
            
            <div class="text-center mt-3">
                <a href="index.php" class="text-decoration-none">Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>