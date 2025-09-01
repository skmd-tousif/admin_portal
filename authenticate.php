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

// Get form data
$username = trim($_POST['username']);
$password = $_POST['password'];

// Input validation
if (empty($username) || empty($password)) {
    $_SESSION['error'] = "Username and password are required";
    header("Location: index.php");
    exit();
}

// Fetch admin from the database
$sql = "SELECT * FROM admin WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();

    // Verify password with password_hash()
    if (password_verify($password, $admin['password'])) {
        // Password is correct, start a session
        $_SESSION['admin_id'] = $admin['admin_id'];
        $_SESSION['username'] = $admin['username'];
        $_SESSION['admin_name'] = $admin['admin_name'];
        $_SESSION['last_login'] = time();
        
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        
        header("Location: client_management/index.php");
        exit();
    } else {
        $_SESSION['error'] = "Invalid username or password";
        header("Location: index.php");
        exit();
    }
} else {
    $_SESSION['error'] = "Invalid username or password";
    header("Location: index.php");
    exit();
}

$stmt->close();
$conn->close();
?>