<?php
session_start();

// Check if the admin is logged in
if (isset($_SESSION['admin_id'])) {
    header("Location: client_management/index.php");
    exit();
}

// Display error message if exists
$error = '';
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Portal | Secure Access</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- More relaxed CSP policy -->
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' https:; script-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; style-src 'self' https://cdn.jsdelivr.net https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com data:; img-src 'self' data: https:;">
    <style>
        /* Your existing CSS styles here */
        :root {
            --primary-color: #4f46e5;
            --primary-dark: #4338ca;
            --primary-light: #6366f1;
            --accent-color: #10b981;
            --error-color: #ef4444;
            --text-light: #f8fafc;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --bg-light: #f1f5f9;
            --bg-dark: #020617;
            --border-color: #e2e8f0;
            --transition-speed: 0.3s;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --radius-sm: 0.25rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%);
            color: var(--text-light);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 1rem;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            position: relative;
            overflow: hidden;
        }
        
        .login-wrapper {
            width: 100%;
            max-width: 28rem;
            position: relative;
            z-index: 10;
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            padding: 2.5rem;
            width: 100%;
            position: relative;
            z-index: 20;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 0.5rem;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
        }
        
        .brand-header {
            text-align: center;
            margin-bottom: 2.5rem;
            position: relative;
        }
        
        .brand-logo {
            width: 4rem;
            height: 4rem;
            margin: 0 auto 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border-radius: var(--radius-lg);
            color: white;
            font-size: 1.75rem;
            font-weight: 700;
            box-shadow: var(--shadow-md);
        }
        
        h2 {
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-size: 1.5rem;
            letter-spacing: -0.025em;
        }
        
        .brand-subtitle {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-bottom: 0;
            font-weight: 500;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-label {
            display: block;
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        
        .form-control {
            width: 100%;
            height: 3rem;
            padding: 0.75rem 1rem;
            font-size: 0.9375rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            background-color: white;
            transition: all var(--transition-speed);
            box-shadow: var(--shadow-sm);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
            outline: none;
        }
        
        .input-group {
            position: relative;
            display: flex;
        }
        
        .input-group .form-control {
            flex: 1;
            border-top-right-radius: 0;
            border-bottom-right-radius: 0;
        }
        
        .toggle-password-btn {
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            border-left: none;
            border-top-right-radius: var(--radius-md);
            border-bottom-right-radius: var(--radius-md);
            padding: 0 1rem;
            cursor: pointer;
            transition: all var(--transition-speed);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
        }
        
        .toggle-password-btn:hover {
            background: #e2e8f0;
            color: var(--text-dark);
        }
        
        .btn-login {
            width: 100%;
            padding: 0.875rem;
            font-size: 0.9375rem;
            font-weight: 600;
            color: white;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: all var(--transition-speed);
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-login:active {
            transform: translateY(0);
            box-shadow: var(--shadow-md);
        }
        
        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none !important;
        }
        
        /* Background animation elements */
        .bg-animation {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
            overflow: hidden;
        }
        
        .bg-circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            filter: blur(60px);
        }
        
        .bg-circle:nth-child(1) {
            width: 30vw;
            height: 30vw;
            top: -10vw;
            left: -10vw;
            animation: float 15s infinite ease-in-out;
        }
        
        .bg-circle:nth-child(2) {
            width: 40vw;
            height: 40vw;
            bottom: -15vw;
            right: -15vw;
            animation: float 18s infinite ease-in-out reverse;
        }
        
        .bg-circle:nth-child(3) {
            width: 25vw;
            height: 25vw;
            top: 40%;
            right: 20%;
            animation: float 12s infinite ease-in-out;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translate(0, 0);
            }
            50% {
                transform: translate(5vw, 5vh);
            }
        }
        
        /* Loading spinner */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s ease-in-out infinite;
        }
        
        /* Error message styling */
        .error-message {
            color: var(--error-color);
            font-size: 0.8125rem;
            margin-top: 0.25rem;
            display: none;
        }
        
        /* Input error state */
        .form-control.error {
            border-color: var(--error-color);
        }
        
        .form-control.error:focus {
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15);
        }
        
        /* Animation for form entry */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-container {
            animation: fadeInUp 0.6s ease-out forwards;
        }
        
        /* Accessibility focus styles */
        .form-control:focus-visible, 
        .btn-login:focus-visible,
        .toggle-password-btn:focus-visible {
            outline: 2px solid var(--primary-color);
            outline-offset: 2px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 640px) {
            .login-container {
                padding: 2rem 1.5rem;
            }
            
            .brand-logo {
                width: 3.5rem;
                height: 3.5rem;
                font-size: 1.5rem;
            }
            
            h2 {
                font-size: 1.375rem;
            }
        }
    </style>
</head>
<body>
    <!-- Background animation elements -->
    <div class="bg-animation">
        <div class="bg-circle"></div>
        <div class="bg-circle"></div>
        <div class="bg-circle"></div>
    </div>
    
    <div class="login-wrapper">
        <div class="login-container">
            <div class="brand-header">
                <div class="brand-logo">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                </div>
                <h2>Admin Portal</h2>
                <p class="brand-subtitle">Secure access to management dashboard</p>
            </div>
            
            <form action="authenticate.php" method="POST" id="loginForm">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required autocomplete="username" placeholder="Enter admin username">
                    <div class="error-message" id="username-error">Please enter a valid username</div>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
                        <button type="button" class="toggle-password-btn" id="togglePassword" aria-label="Toggle password visibility">
                            <i class="bi bi-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                    <div class="error-message" id="password-error">Please enter your password</div>
                </div>
                
                <button type="submit" class="btn-login" id="loginBtn">
                    <span class="btn-text">Sign In</span>
                </button>

                <div class="text-center mt-3">
                    <a href="forgot_password.php" class="text-decoration-none" style="color: var(--primary-color);">Forgot Password?</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Elements
            const loginForm = document.getElementById('loginForm');
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            const togglePasswordBtn = document.getElementById('togglePassword');
            const toggleIcon = document.getElementById('toggleIcon');
            const loginBtn = document.getElementById('loginBtn');
            const usernameError = document.getElementById('username-error');
            const passwordError = document.getElementById('password-error');
            
            // Toggle password visibility
            togglePasswordBtn.addEventListener('click', function() {
                const passwordField = document.getElementById('password');
                const icon = document.getElementById('toggleIcon');
                
                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                    togglePasswordBtn.setAttribute('aria-pressed', 'true');
                } else {
                    passwordField.type = 'password';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                    togglePasswordBtn.setAttribute('aria-pressed', 'false');
                }
            });
            
            // Form submission
            loginForm.addEventListener('submit', function(e) {
                // Validate form
                let isValid = true;
                
                if (usernameInput.value.trim() === '') {
                    usernameInput.classList.add('error');
                    usernameError.style.display = 'block';
                    isValid = false;
                } else {
                    usernameInput.classList.remove('error');
                    usernameError.style.display = 'none';
                }
                
                if (passwordInput.value.trim() === '') {
                    passwordInput.classList.add('error');
                    passwordError.style.display = 'block';
                    isValid = false;
                } else {
                    passwordInput.classList.remove('error');
                    passwordError.style.display = 'none';
                }
                
                if (!isValid) {
                    e.preventDefault();
                    return;
                }
                
                // Show loading state
                loginBtn.innerHTML = '<span class="spinner"></span><span class="btn-text">Authenticating...</span>';
                loginBtn.disabled = true;
                
                // The form will now submit normally to authenticate.php
            });
            
            // Input validation on blur
            usernameInput.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.classList.add('error');
                    usernameError.style.display = 'block';
                } else {
                    this.classList.remove('error');
                    usernameError.style.display = 'none';
                }
            });
            
            passwordInput.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.classList.add('error');
                    passwordError.style.display = 'block';
                } else {
                    this.classList.remove('error');
                    passwordError.style.display = 'none';
                }
            });
            
            // Add subtle hover effect to login container
            const loginContainer = document.querySelector('.login-container');
            
            loginContainer.addEventListener('mouseenter', () => {
                loginContainer.style.transform = 'translateY(-2px)';
                loginContainer.style.boxShadow = '0 25px 50px -12px rgba(0, 0, 0, 0.15)';
            });
            
            loginContainer.addEventListener('mouseleave', () => {
                loginContainer.style.transform = '';
                loginContainer.style.boxShadow = 'var(--shadow-xl)';
            });

            <?php if (!empty($error)): ?>
                alert('<?php echo addslashes($error); ?>');
            <?php endif; ?>
        });
    </script>
</body>
</html>