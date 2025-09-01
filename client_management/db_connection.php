<?php
// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$database = "mydb";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4 to support full Unicode including emojis
$conn->set_charset("utf8mb4");

// Error reporting (only for development, remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to sanitize input data
function sanitize_input($data) {
    global $conn;
    return $conn->real_escape_string(trim($data));
}

// Function to close the database connection
function close_db_connection() {
    global $conn;
    if (isset($conn)) {
        $conn->close();
    }
}

// Register shutdown function to close connection when script ends
register_shutdown_function('close_db_connection');
?>