<?php
// Start the session
session_start();

// Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    // If the admin is not logged in, redirect to the login page
    header("Location: ../index.php");
    exit();
}

// Database connection details
$servername = "localhost"; // Replace with your server name if different
$username = "root"; // Replace with your database username
$password = ""; // Replace with your database password
$dbname = "mydb";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Array of tables to export
$tables = array(
    "admin",
    "adminclientpayment",
    "admintlpayment",
    "client",
    "colleges",
    "expert",
    "task",
    "teamlead",
    "tlexpertpayment"
);

// Create a temporary directory
$tempDir = sys_get_temp_dir() . '/pace_export_' . uniqid();
if (!mkdir($tempDir)) {
    die("Failed to create temporary directory");
}

// Loop through tables and create CSV files
foreach ($tables as $table) {
    $filename = $tempDir . '/' . $table . '.csv';
    $file = fopen($filename, 'w');
    
    // Add a UTF-8 BOM
    fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Get column names
    $columns_result = $conn->query("SHOW COLUMNS FROM `$table`");
    $columns = array();
    while ($column = $columns_result->fetch_assoc()) {
        $columns[] = $column['Field'];
    }
    
    // Write column headers
    fputcsv($file, $columns);
    
    // Get data
    $result = $conn->query("SELECT * FROM `$table`");
    
    // Write data rows
    while ($row = $result->fetch_assoc()) {
        fputcsv($file, $row);
    }
    
    fclose($file);
}

// Close database connection
$conn->close();

// Create ZIP file
$zipFilename = 'PACE_DB_Export_' . date('Y-m-d') . '.zip';
$zip = new ZipArchive();
if ($zip->open($tempDir . '/' . $zipFilename, ZipArchive::CREATE) !== TRUE) {
    die("Cannot create ZIP file");
}

// Add files to ZIP
foreach ($tables as $table) {
    $csvFile = $table . '.csv';
    $zip->addFile($tempDir . '/' . $csvFile, $csvFile);
}

$zip->close();

// Set headers for download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
header('Content-Length: ' . filesize($tempDir . '/' . $zipFilename));

// Send the ZIP file
readfile($tempDir . '/' . $zipFilename);

// Clean up - delete temporary files
array_map('unlink', glob("$tempDir/*"));
rmdir($tempDir);

// End script
exit();
?>