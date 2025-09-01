<?php
// Start the session
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "mydb";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get filter parameters from POST
$filter_column = isset($_POST['filter_column']) ? $_POST['filter_column'] : '';
$filter_value = isset($_POST['filter_value']) ? $_POST['filter_value'] : '';
$filter_month = isset($_POST['filter_month']) ? $_POST['filter_month'] : '';
$sort_order = isset($_POST['sort']) ? $_POST['sort'] : 'default';

// Base SQL query
$sql = "SELECT * FROM task";
$where_clauses = [];

// Add filtering to the query
if (!empty($filter_column) && !empty($filter_value)) {
    $where_clauses[] = "$filter_column LIKE ?";
}

// Add month filtering to the query
if (!empty($filter_month)) {
    $where_clauses[] = "MONTH(task_date) = ? AND YEAR(task_date) = ?";
    list($month, $year) = explode('-', $filter_month);
}

// Combine where clauses
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

// Add sorting to the query
if ($sort_order === 'asc') {
    $sql .= " ORDER BY price ASC";
} elseif ($sort_order === 'desc') {
    $sql .= " ORDER BY price DESC";
}

// Execute query
if (!empty($where_clauses)) {
    $stmt = $conn->prepare($sql);
    
    // Bind parameters based on which filters are present
    if (!empty($filter_column) && !empty($filter_value) && !empty($filter_month)) {
        $search_param = "%" . $filter_value . "%";
        $stmt->bind_param("sis", $search_param, $month, $year);
    } elseif (!empty($filter_column) && !empty($filter_value)) {
        $search_param = "%" . $filter_value . "%";
        $stmt->bind_param("s", $search_param);
    } elseif (!empty($filter_month)) {
        $stmt->bind_param("is", $month, $year);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

// Fetch team leads, experts, and clients for display
$team_leads_display = $conn->query("SELECT team_lead_id, name FROM teamlead");
$experts_display = $conn->query("SELECT expert_id, expert_name FROM expert");
$clients_display = $conn->query("SELECT client_id, client_name FROM client");

// Create associative arrays for quick lookup
$team_lead_names = [];
while ($row = $team_leads_display->fetch_assoc()) {
    $team_lead_names[$row['team_lead_id']] = $row['name'];
}

$expert_names = [];
while ($row = $experts_display->fetch_assoc()) {
    $expert_names[$row['expert_id']] = $row['expert_name'];
}

$client_names = [];
while ($row = $clients_display->fetch_assoc()) {
    $client_names[$row['client_id']] = $row['client_name'];
}

// Set headers for Excel download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=tasks_export_" . date('Y-m-d') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

// Start Excel content
echo "<table border='1'>";
echo "<tr>";
echo "<th>SL No</th>";
echo "<th>Task Name</th>";
echo "<th>Description</th>";
echo "<th>Tl</th>";
echo "<th>Expert1</th>";
echo "<th>Expert2</th>";
echo "<th>Expert3</th>";
echo "<th>Client</th>";
echo "<th>Price</th>";
echo "<th>Tl Price</th>";
echo "<th>Expert1 Price</th>";
echo "<th>Expert2 Price</th>";
echo "<th>Expert3 Price</th>";
echo "<th>Total Cost</th>";
echo "<th>Task Date</th>";
echo "<th>Due Date</th>";
echo "<th>Status</th>";
echo "<th>Word Count</th>";
echo "<th>Issue</th>";
echo "<th>Incomplete Info</th>";
echo "</tr>";

$serial_no = 1;
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $serial_no++ . "</td>";
    echo "<td>" . htmlspecialchars($row['task_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['description'] ?? 'No description') . "</td>";
    echo "<td>" . htmlspecialchars($row['assigned_team_lead_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['assigned_expert_1'] ?? 'None') . "</td>";
    echo "<td>" . htmlspecialchars($row['assigned_expert_2'] ?? 'None') . "</td>";
    echo "<td>" . htmlspecialchars($row['assigned_expert_3'] ?? 'None') . "</td>";
    echo "<td>" . htmlspecialchars($client_names[$row['client_id']] . " (ID: " . $row['client_id'] . ")") . "</td>";
    echo "<td>" . $row['price'] . "</td>";
    echo "<td>" . $row['tl_price'] . "</td>";
    echo "<td>" . $row['expert_price1'] . "</td>";
    echo "<td>" . $row['expert_price2'] . "</td>";
    echo "<td>" . $row['expert_price3'] . "</td>";
    echo "<td>" . $row['total_cost'] . "</td>";
    echo "<td>" . ($row['task_date'] ? date('d/m/Y', strtotime($row['task_date'])) : '') . "</td>";
    echo "<td>" . ($row['due_date'] ? date('d/m/Y', strtotime($row['due_date'])) : '') . "</td>";
    echo "<td>" . htmlspecialchars($row['status']) . "</td>";
    echo "<td>" . $row['word_count'] . "</td>";
    echo "<td>" . htmlspecialchars($row['issue']) . "</td>";
    echo "<td>" . ($row['incomplete_information'] ? 'Yes' : 'No') . "</td>";
    echo "</tr>";
}

echo "</table>";
exit();
?>