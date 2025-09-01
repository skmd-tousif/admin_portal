<?php
ob_start('ob_gzhandler');
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$database = "mydb";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Cache dropdown data in session for 1 hour
if (!isset($_SESSION['cached_dropdowns']) || $_SESSION['cache_expiry'] < time()) {
    $_SESSION['cached_dropdowns'] = [
        'team_leads' => $conn->query("SELECT team_lead_id, name FROM teamlead")->fetch_all(MYSQLI_ASSOC),
        'experts' => $conn->query("SELECT expert_id, expert_name FROM expert")->fetch_all(MYSQLI_ASSOC),
        'clients' => $conn->query("SELECT client_id, client_name FROM client")->fetch_all(MYSQLI_ASSOC)
    ];
    $_SESSION['cache_expiry'] = time() + 3600;
}

$team_leads_data = $_SESSION['cached_dropdowns']['team_leads'];
$experts_data = $_SESSION['cached_dropdowns']['experts'];
$clients_data = $_SESSION['cached_dropdowns']['clients'];

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add'])) {
        $task_name = $_POST['task_name'];
        $description = !empty($_POST['description']) ? $_POST['description'] : NULL;
        $team_lead_id = $_POST['team_lead_id'];
        $expert_id_1 = !empty($_POST['expert_id_1']) ? $_POST['expert_id_1'] : NULL;
        $expert_id_2 = !empty($_POST['expert_id_2']) ? $_POST['expert_id_2'] : NULL;
        $expert_id_3 = !empty($_POST['expert_id_3']) ? $_POST['expert_id_3'] : NULL;
        $client_id = $_POST['client_id'];
        $price = $_POST['price'];
        $tl_price = $_POST['tl_price'];
        $expert_price1 = !empty($_POST['expert_price1']) ? $_POST['expert_price1'] : 0;
        $expert_price2 = !empty($_POST['expert_price2']) ? $_POST['expert_price2'] : 0;
        $expert_price3 = !empty($_POST['expert_price3']) ? $_POST['expert_price3'] : 0;
        $task_date = $_POST['task_date'];
        $due_date = $_POST['due_date'];
        $status = $_POST['status'];
        $word_count = $_POST['word_count'];
        $issue = isset($_POST['issue']) ? implode(",", $_POST['issue']) : NULL;
        $incomplete_information = isset($_POST['incomplete_information']) ? 1 : 0;

        // Fetch team lead name
        $team_lead_name = '';
        foreach ($team_leads_data as $lead) {
            if ($lead['team_lead_id'] == $team_lead_id) {
                $team_lead_name = $lead['name'];
                break;
            }
        }

        // Fetch expert names
        $expert_name_1 = NULL;
        $expert_name_2 = NULL;
        $expert_name_3 = NULL;
        
        foreach ($experts_data as $expert) {
            if ($expert['expert_id'] == $expert_id_1) $expert_name_1 = $expert['expert_name'];
            if ($expert['expert_id'] == $expert_id_2) $expert_name_2 = $expert['expert_name'];
            if ($expert['expert_id'] == $expert_id_3) $expert_name_3 = $expert['expert_name'];
        }

        // Insert task
        $stmt = $conn->prepare("INSERT INTO task (task_name, description, team_lead_id, assigned_team_lead_name, expert_id_1, assigned_expert_1, expert_id_2, assigned_expert_2, expert_id_3, assigned_expert_3, client_id, price, tl_price, expert_price1, expert_price2, expert_price3, task_date, due_date, status, word_count, issue, total_cost, incomplete_information) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $total_cost = $tl_price + $expert_price1 + $expert_price2 + $expert_price3;
        $stmt->bind_param("ssisssssssiiiiiisssisii", $task_name, $description, $team_lead_id, $team_lead_name, $expert_id_1, $expert_name_1, $expert_id_2, $expert_name_2, $expert_id_3, $expert_name_3, $client_id, $price, $tl_price, $expert_price1, $expert_price2, $expert_price3, $task_date, $due_date, $status, $word_count, $issue, $total_cost, $incomplete_information);
        $stmt->execute();

        // Update dues
        $conn->query("UPDATE client SET due_payment = due_payment + $price WHERE client_id = $client_id");
        $conn->query("UPDATE teamlead SET dues = dues + $tl_price WHERE team_lead_id = $team_lead_id");
        if ($expert_id_1) $conn->query("UPDATE expert SET dues = dues + $expert_price1 WHERE expert_id = $expert_id_1");
        if ($expert_id_2) $conn->query("UPDATE expert SET dues = dues + $expert_price2 WHERE expert_id = $expert_id_2");
        if ($expert_id_3) $conn->query("UPDATE expert SET dues = dues + $expert_price3 WHERE expert_id = $expert_id_3");

        header("Location: task.php");
        exit();
    }

    // Handle editing a task
    if (isset($_POST['edit'])) {
        $task_id = $_POST['task_id'];
        $task_name = $_POST['task_name'];
        $description = !empty($_POST['description']) ? $_POST['description'] : NULL;
        $team_lead_id = $_POST['team_lead_id'];
        $expert_id_1 = !empty($_POST['expert_id_1']) ? $_POST['expert_id_1'] : NULL;
        $expert_id_2 = !empty($_POST['expert_id_2']) ? $_POST['expert_id_2'] : NULL;
        $expert_id_3 = !empty($_POST['expert_id_3']) ? $_POST['expert_id_3'] : NULL;
        $client_id = $_POST['client_id'];
        $price = $_POST['price'];
        $tl_price = $_POST['tl_price'];
        $expert_price1 = !empty($_POST['expert_price1']) ? $_POST['expert_price1'] : 0;
        $expert_price2 = !empty($_POST['expert_price2']) ? $_POST['expert_price2'] : 0;
        $expert_price3 = !empty($_POST['expert_price3']) ? $_POST['expert_price3'] : 0;
        $task_date = $_POST['task_date'];
        $due_date = $_POST['due_date'];
        $status = $_POST['status'];
        $word_count = $_POST['word_count'];
        $issue = isset($_POST['issue']) ? implode(",", $_POST['issue']) : NULL;
        $incomplete_information = isset($_POST['incomplete_information']) ? 1 : 0;

        // Fetch team lead name
        $team_lead_name = '';
        foreach ($team_leads_data as $lead) {
            if ($lead['team_lead_id'] == $team_lead_id) {
                $team_lead_name = $lead['name'];
                break;
            }
        }

        // Fetch expert names
        $expert_name_1 = NULL;
        $expert_name_2 = NULL;
        $expert_name_3 = NULL;
        
        foreach ($experts_data as $expert) {
            if ($expert['expert_id'] == $expert_id_1) $expert_name_1 = $expert['expert_name'];
            if ($expert['expert_id'] == $expert_id_2) $expert_name_2 = $expert['expert_name'];
            if ($expert['expert_id'] == $expert_id_3) $expert_name_3 = $expert['expert_name'];
        }

        // Update task
        $stmt = $conn->prepare("UPDATE task SET task_name=?, description=?, team_lead_id=?, assigned_team_lead_name=?, expert_id_1=?, assigned_expert_1=?, expert_id_2=?, assigned_expert_2=?, expert_id_3=?, assigned_expert_3=?, client_id=?, price=?, tl_price=?, expert_price1=?, expert_price2=?, expert_price3=?, task_date=?, due_date=?, status=?, word_count=?, issue=?, total_cost=?, incomplete_information=? WHERE task_id=?");
        $total_cost = $tl_price + $expert_price1 + $expert_price2 + $expert_price3;
        $stmt->bind_param("ssisssssssiiiiiisssisiii", $task_name, $description, $team_lead_id, $team_lead_name, $expert_id_1, $expert_name_1, $expert_id_2, $expert_name_2, $expert_id_3, $expert_name_3, $client_id, $price, $tl_price, $expert_price1, $expert_price2, $expert_price3, $task_date, $due_date, $status, $word_count, $issue, $total_cost, $incomplete_information, $task_id);
        $stmt->execute();

        $redirect_url = "task.php";
        $params = [];
        if (!empty($_POST['filter_column']) && !empty($_POST['filter_value'])) {
            $params[] = "filter_column=" . urlencode($_POST['filter_column']) . "&filter_value=" . urlencode($_POST['filter_value']);
        }
        if (!empty($_POST['filter_month'])) {
            $params[] = "filter_month=" . urlencode($_POST['filter_month']);
        }
        if (!empty($_POST['sort'])) {
            $params[] = "sort=" . urlencode($_POST['sort']);
        }
        if (!empty($_POST['page'])) {
            $params[] = "page=" . urlencode($_POST['page']);
        }
        if (!empty($params)) {
            $redirect_url .= "?" . implode("&", $params);
        }
        header("Location: " . $redirect_url);
        exit();
    }

    // Handle deleting a task
    if (isset($_POST['delete'])) {
        $task_id = $_POST['task_id'];
        $conn->query("DELETE FROM task WHERE task_id = $task_id");

        $redirect_url = "task.php";
        $params = [];
        if (!empty($_POST['filter_column']) && !empty($_POST['filter_value'])) {
            $params[] = "filter_column=" . urlencode($_POST['filter_column']) . "&filter_value=" . urlencode($_POST['filter_value']);
        }
        if (!empty($_POST['filter_month'])) {
            $params[] = "filter_month=" . urlencode($_POST['filter_month']);
        }
        if (!empty($_POST['sort'])) {
            $params[] = "sort=" . urlencode($_POST['sort']);
        }
        if (!empty($_POST['page'])) {
            $params[] = "page=" . urlencode($_POST['page']);
        }
        if (!empty($params)) {
            $redirect_url .= "?" . implode("&", $params);
        }
        header("Location: " . $redirect_url);
        exit();
    }
}

// Handle filtering
$filter_column = isset($_POST['filter_column']) ? $_POST['filter_column'] : (isset($_GET['filter_column']) ? $_GET['filter_column'] : '');
$filter_value = isset($_POST['filter_value']) ? $_POST['filter_value'] : (isset($_GET['filter_value']) ? $_GET['filter_value'] : '');
$filter_month = isset($_POST['filter_month']) ? $_POST['filter_month'] : (isset($_GET['filter_month']) ? $_GET['filter_month'] : '');

// Handle sorting
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'default';
$next_order = $sort_order === 'asc' ? 'desc' : 'asc';

// Pagination settings
$items_per_page = 50;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Base SQL query for tasks
$sql = "SELECT * FROM task";
$where_clauses = [];
$bind_types = '';
$bind_params = [];

// Add filtering to the query
if (!empty($filter_column) && !empty($filter_value)) {
    if (in_array($filter_column, ['client_id', 'team_lead_id', 'expert_id_1', 'expert_id_2', 'expert_id_3', 'price'])) {
        $where_clauses[] = "$filter_column = ?";
        $bind_types .= 'i';
        $bind_params[] = $filter_value;
    } elseif ($filter_column === 'status') {
        $where_clauses[] = "$filter_column = ?";
        $bind_types .= 's';
        $bind_params[] = $filter_value;
    } else {
        $where_clauses[] = "$filter_column LIKE ?";
        $bind_types .= 's';
        $bind_params[] = "%$filter_value%";
    }
}

// Add month filtering to the query
if (!empty($filter_month)) {
    $where_clauses[] = "MONTH(task_date) = ? AND YEAR(task_date) = ?";
    $bind_types .= 'ii';
    list($month, $year) = explode('-', $filter_month);
    $bind_params[] = (int)$month;
    $bind_params[] = (int)$year;
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

// Add pagination
$sql .= " LIMIT $items_per_page OFFSET $offset";

// Execute query
if (!empty($where_clauses)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($bind_types, ...$bind_params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) AS total FROM task";
if (!empty($where_clauses)) {
    $count_sql .= " WHERE " . implode(" AND ", $where_clauses);
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($bind_types, ...$bind_params);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_items = $count_result->fetch_assoc()['total'];
} else {
    $total_items = $conn->query($count_sql)->fetch_assoc()['total'];
}

$total_pages = ceil($total_items / $items_per_page);

// Create arrays for quick lookup
$team_lead_names = [];
foreach ($team_leads_data as $row) {
    $team_lead_names[$row['team_lead_id']] = $row['name'];
}

$expert_names = [];
foreach ($experts_data as $row) {
    $expert_names[$row['expert_id']] = $row['expert_name'];
}

$client_names = [];
foreach ($clients_data as $row) {
    $client_names[$row['client_id']] = $row['client_name'];
}

// Get display value for filter if it's an ID field
$filter_value_display = '';
if (!empty($filter_column) && !empty($filter_value)) {
    if ($filter_column === 'team_lead_id') {
        $filter_value_display = $team_lead_names[$filter_value] ?? $filter_value;
    } elseif (in_array($filter_column, ['expert_id_1', 'expert_id_2', 'expert_id_3'])) {
        $filter_value_display = $expert_names[$filter_value] ?? $filter_value;
    } elseif ($filter_column === 'client_id') {
        $filter_value_display = $client_names[$filter_value] ?? $filter_value;
    } else {
        $filter_value_display = $filter_value;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
        }
        
        body {
            padding-top: 70px;
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            background-color: var(--secondary-color) !important;
        }
        
        .nav-logo {
            height: 30px;
            margin-left: 15px;
        }
        
        .action-btns .btn {
            margin-left: 10px;
        }
        
        @media (max-width: 992px) {
            .action-btns {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid rgba(255,255,255,0.1);
            }
        }
        
        .edit-form {
            display: none;
            margin-top: 15px;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            background-color: #fff;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .failed-row {
            background-color: #ff7575;
        }
        
        .incomplete-row {
            background-color: #f3c736;
        }
        
        .dropdown-container {
            position: relative;
        }
        
        .dropdown-list, .filter-dropdown-list {
            position: absolute;
            z-index: 1000;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            background-color: #fff;
            display: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        
        .dropdown-list li, .filter-dropdown-list li {
            padding: 8px 12px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .dropdown-list li:hover, .filter-dropdown-list li:hover {
            background-color: #e9ecef;
        }
        
        .loading {
            padding: 10px;
            text-align: center;
            font-style: italic;
            color: #6c757d;
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #258cd1;
            border-color: #258cd1;
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }
        
        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            border-color: var(--warning-color);
            color: #212529;
        }
        
        .btn-info {
            background-color: var(--info-color);
            border-color: var(--info-color);
        }
        
        .section-title {
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
            margin-bottom: 20px;
            color: var(--secondary-color);
        }
        
        .table th {
            background-color: var(--secondary-color);
            color: white;
            vertical-align: middle;
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .pagination .page-link {
            color: var(--primary-color);
        }
        
        .pagination .page-link {
            min-width: 40px;
            text-align: center;
        }
        
        .form-control:focus, .select2-container--default .select2-selection--single:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        
        .select2-container--default .select2-selection--single {
            height: 38px;
            padding: 6px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px;
        }
        
        .filter-container {
            background-color: #e9ecef;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .filter-row {
            margin-bottom: 15px;
        }
        
        .export-section {
            background-color: #e9ecef;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="bg.jpg" alt="Logo" height="40" class="d-inline-block align-top me-2">
                PACE_DB
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="task.php">Tasks</a></li>
                    <li class="nav-item"><a class="nav-link" href="client.php">Clients</a></li>
                    <li class="nav-item"><a class="nav-link" href="visualization.php">Generate Sheets</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin_client_payment.php">A-Client Payments</a></li>
                    <li class="nav-item"><a class="nav-link" href="teamlead.php">Team Leads</a></li>
                    <li class="nav-item"><a class="nav-link" href="admin_tl_payment.php">A-TL Payments</a></li>
                    <li class="nav-item"><a class="nav-link" href="expert.php">Experts</a></li>
                    <li class="nav-item"><a class="nav-link" href="tl_expert_payment.php">TL-Expert Payments</a></li>
                    <li class="nav-item"><a class="nav-link" href="colleges.php">Colleges</a></li>
                </ul>
                
                <div class="d-flex">
                    <a href="index.php" class="btn btn-outline-light me-2">
                        <i class="fas fa-home me-1"></i> Home
                    </a>
                    <a href="logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h2 class="section-title">Add New Task</h2>
        <form method="post" class="mb-4">
            <!-- First row -->
            <div class="row mb-3">
                <div class="col-md-12">
                    <div class="form-check">
                        <input type="checkbox" name="incomplete_information" class="form-check-input" id="incomplete_information">
                        <label class="form-check-label" for="incomplete_information">Incomplete Information</label>
                    </div>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="client_id" class="form-label">Client:</label>
                    <div class="dropdown-container">
                        <input type="text" id="client-search" class="form-control" placeholder="Search client...">
                        <ul class="dropdown-list" id="client-list">
                            <?php foreach ($clients_data as $row) { 
                                echo "<li data-value='{$row['client_id']}'>{$row['client_name']}</li>"; 
                            } ?>
                        </ul>
                        <input type="hidden" name="client_id" id="client-id">
                    </div>
                </div>
                <div class="col-md-4">
                    <label for="task_name" class="form-label">Task Name:</label>
                    <input type="text" name="task_name" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label for="price" class="form-label">Price:</label>
                    <input type="number" name="price" class="form-control" required>
                </div>
            </div>

            <!-- Second row -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="word_count" class="form-label">Word Count:</label>
                    <input type="number" name="word_count" class="form-control">
                </div>
                <div class="col-md-8">
                    <label for="description" class="form-label">Description:</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
            </div>

            <!-- Third row -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="task_date" class="form-label">Date:</label>
                    <input type="date" name="task_date" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label for="due_date" class="form-label">Due Date:</label>
                    <input type="date" name="due_date" class="form-control" required>
                </div>
            </div>

            <!-- Fourth row -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="status" class="form-label">Status:</label>
                    <select name="status" class="form-control select2" required>
                        <option value="in progress">In Progress</option>
                        <option value="submitted">Submitted</option>
                        <option value="passed">Passed</option>
                        <option value="failed">Failed</option>
                        <option value="submitted late">Submitted Late</option>
                    </select>
                </div>
                <div class="col-md-6">     
                    <label for="issue" class="form-label">Issue:</label>     
                    <div class="form-check">         
                        <input type="checkbox" name="issue[]" value="Low marks"> Low marks<br>         
                        <input type="checkbox" name="issue[]" value="Brief not followed"> Brief not followed<br>         
                        <input type="checkbox" name="issue[]" value="Word count lower"> Word count lower<br>         
                        <input type="checkbox" name="issue[]" value="Wordcount higher"> Wordcount higher<br>         
                        <input type="checkbox" name="issue[]" value="Referencing irrelevant"> Referencing irrelevant<br>         
                        <input type="checkbox" name="issue[]" value="AI used"> AI used<br>         
                        <input type="checkbox" name="issue[]" value="Plagiarism"> Plagiarism<br>         
                        <input type="checkbox" name="issue[]" value="Poor quality"> Poor quality<br>         
                        <input type="checkbox" name="issue[]" value="Money Less Taken"> Money Less Taken<br>
                    </div> 
                </div>
            </div>

            <!-- Team and experts section -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">Team Assignment</h5>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Role</th>
                                <th>Person</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Team Lead</td>
                                <td>
                                    <div class="dropdown-container">
                                        <input type="text" id="team-lead-search" class="form-control" placeholder="Search team lead...">
                                        <ul class="dropdown-list" id="team-lead-list">
                                            <?php foreach ($team_leads_data as $row) { 
                                                echo "<li data-value='{$row['team_lead_id']}'>{$row['name']}</li>"; 
                                            } ?>
                                        </ul>
                                        <input type="hidden" name="team_lead_id" id="team-lead-id">
                                    </div>
                                </td>
                                <td>
                                    <input type="number" name="tl_price" class="form-control" required>
                                </td>
                            </tr>
                            <tr>
                                <td>Expert 1</td>
                                <td>
                                    <div class="dropdown-container">
                                        <input type="text" id="expert1-search" class="form-control" placeholder="Search expert...">
                                        <ul class="dropdown-list" id="expert1-list">
                                            <?php foreach ($experts_data as $row) { 
                                                echo "<li data-value='{$row['expert_id']}'>{$row['expert_name']}</li>"; 
                                            } ?>
                                        </ul>
                                        <input type="hidden" name="expert_id_1" id="expert1-id">
                                    </div>
                                </td>
                                <td>
                                    <input type="number" name="expert_price1" class="form-control">
                                </td>
                            </tr>
                            <tr>
                                <td>Expert 2</td>
                                <td>
                                    <div class="dropdown-container">
                                        <input type="text" id="expert2-search" class="form-control" placeholder="Search expert...">
                                        <ul class="dropdown-list" id="expert2-list">
                                            <?php foreach ($experts_data as $row) { 
                                                echo "<li data-value='{$row['expert_id']}'>{$row['expert_name']}</li>"; 
                                            } ?>
                                        </ul>
                                        <input type="hidden" name="expert_id_2" id="expert2-id">
                                    </div>
                                </td>
                                <td>
                                    <input type="number" name="expert_price2" class="form-control">
                                </td>
                            </tr>
                            <tr>
                                <td>Expert 3</td>
                                <td>
                                    <div class="dropdown-container">
                                        <input type="text" id="expert3-search" class="form-control" placeholder="Search expert...">
                                        <ul class="dropdown-list" id="expert3-list">
                                            <?php foreach ($experts_data as $row) { 
                                                echo "<li data-value='{$row['expert_id']}'>{$row['expert_name']}</li>"; 
                                            } ?>
                                        </ul>
                                        <input type="hidden" name="expert_id_3" id="expert3-id">
                                    </div>
                                </td>
                                <td>
                                    <input type="number" name="expert_price3" class="form-control">
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <button type="submit" name="add" class="btn btn-primary w-100">
                        <i class="fas fa-plus-circle me-2"></i>Add Task
                    </button>
                </div>
            </div>
        </form>

        <div class="col-md-12 mt-3 mb-4">
            <button type="button" id="copyDetailsBtn" class="btn btn-info w-100">
                <i class="fas fa-copy me-2"></i>Copy Task Details
            </button>
        </div>

        <!-- Filter Form -->
        <div class="filter-container">
            <h2 class="section-title">Filter Tasks</h2>
            <form method="get" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <select name="filter_column" id="filter_column" class="form-control select2" onchange="updateFilterInput()">
                            <option value="">Select Filter Column</option>
                            <option value="task_name" <?= $filter_column == 'task_name' ? 'selected' : '' ?>>Task Name</option>
                            <option value="team_lead_id" <?= $filter_column == 'team_lead_id' ? 'selected' : '' ?>>Team Lead</option>
                            <option value="expert_id_1" <?= $filter_column == 'expert_id_1' ? 'selected' : '' ?>>Expert 1</option>
                            <option value="expert_id_2" <?= $filter_column == 'expert_id_2' ? 'selected' : '' ?>>Expert 2</option>
                            <option value="expert_id_3" <?= $filter_column == 'expert_id_3' ? 'selected' : '' ?>>Expert 3</option>
                            <option value="client_id" <?= $filter_column == 'client_id' ? 'selected' : '' ?>>Client</option>
                            <option value="price" <?= $filter_column == 'price' ? 'selected' : '' ?>>Price</option>
                            <option value="task_date" <?= $filter_column == 'task_date' ? 'selected' : '' ?>>Task Date</option>
                            <option value="due_date" <?= $filter_column == 'due_date' ? 'selected' : '' ?>>Due Date</option>
                            <option value="status" <?= $filter_column == 'status' ? 'selected' : '' ?>>Status</option>
                        </select>
                    </div>
                    <div class="col-md-3" id="filter_input_container">
                        <?php if (!empty($filter_column)): ?>
                            <?php if (in_array($filter_column, ['team_lead_id', 'expert_id_1', 'expert_id_2', 'expert_id_3', 'client_id'])): ?>
                                <div class="filter-dropdown-container">
                                    <input type="text" name="filter_value_display" class="form-control" 
                                           value="<?= htmlspecialchars($filter_value_display ?? '') ?>" 
                                           placeholder="Search..." 
                                           id="filter-search">
                                    <ul class="filter-dropdown-list" id="filter-list">
                                        <!-- Will be populated dynamically -->
                                    </ul>
                                    <input type="hidden" name="filter_value" id="filter-value" 
                                           value="<?= htmlspecialchars($filter_value) ?>">
                                </div>
                            <?php elseif ($filter_column === 'status'): ?>
                                <select name="filter_value" class="form-control">
                                    <option value="in progress" <?= $filter_value == 'in progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="submitted" <?= $filter_value == 'submitted' ? 'selected' : '' ?>>Submitted</option>
                                    <option value="passed" <?= $filter_value == 'passed' ? 'selected' : '' ?>>Passed</option>
                                    <option value="failed" <?= $filter_value == 'failed' ? 'selected' : '' ?>>Failed</option>
                                    <option value="submitted late" <?= $filter_value == 'submitted late' ? 'selected' : '' ?>>Submitted Late</option>
                                </select>
                            <?php else: ?>
                                <input type="text" name="filter_value" class="form-control" 
                                       value="<?= htmlspecialchars($filter_value) ?>" 
                                       placeholder="Enter value">
                            <?php endif; ?>
                        <?php else: ?>
                            <input type="text" name="filter_value" class="form-control" placeholder="Select a filter column first" disabled>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <select name="filter_month" id="filter_month" class="form-control">
                            <option value="">Filter by Month</option>
                            <?php
                            for ($i = 0; $i < 12; $i++) {
                                $month = date('m', strtotime("-$i months"));
                                $year = date('Y', strtotime("-$i months"));
                                $monthName = date('F Y', strtotime("-$i months"));
                                $value = $month . '-' . $year;
                                $selected = ($value == $filter_month) ? 'selected' : '';
                                echo "<option value='$value' $selected>$monthName</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-secondary w-100">
                            <i class="fas fa-filter me-2"></i>Apply Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Sorting Buttons -->
        <div class="filter-container">
            <h2 class="section-title">Sort Tasks</h2>
            <form method="get" class="mb-4">
                <input type="hidden" name="filter_column" value="<?= htmlspecialchars($filter_column) ?>">
                <input type="hidden" name="filter_value" value="<?= htmlspecialchars($filter_value) ?>">
                <input type="hidden" name="filter_month" value="<?= htmlspecialchars($filter_month) ?>">
                <div class="row g-3">
                    <div class="col-md-4">
                        <button type="submit" name="sort" value="asc" class="btn btn-secondary w-100">
                            <i class="fas fa-sort-amount-up me-2"></i>Sort by Price (Asc)
                        </button>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" name="sort" value="desc" class="btn btn-secondary w-100">
                            <i class="fas fa-sort-amount-down me-2"></i>Sort by Price (Desc)
                        </button>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" name="sort" value="default" class="btn btn-secondary w-100">
                            <i class="fas fa-times me-2"></i>No Sorting
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Export Section -->
        <div class="export-section">
            <h2 class="section-title">Export Data</h2>
            <form method="post" action="task_list_export.php">
                <input type="hidden" name="filter_column" value="<?= htmlspecialchars($filter_column) ?>">
                <input type="hidden" name="filter_value" value="<?= htmlspecialchars($filter_value) ?>">
                <input type="hidden" name="filter_month" value="<?= htmlspecialchars($filter_month) ?>">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_order) ?>">
                <button type="submit" class="btn btn-success w-100">
                    <i class="fas fa-file-excel me-2"></i>Download as Excel
                </button>
            </form>
        </div>

        <!-- Tasks List -->
        <h2 class="section-title">Tasks List</h2>
        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th>SL No</th>
                            <th>Task Name</th>
                            <th>Description</th>
                            <th>Tl</th>
                            <th>Expert1</th>
                            <th>Expert2</th>
                            <th>Expert3</th>
                            <th>Client</th>
                            <th>Price</th>
                            <th>Tl Price</th>
                            <th>Expert1 Price</th>
                            <th>Expert2 Price</th>
                            <th>Expert3 Price</th>
                            <th>Total Cost</th>
                            <th>Task Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Word Count</th>
                            <th>Issue</th>
                            <th>Incomplete Info</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $serial_no = ($current_page - 1) * $items_per_page + 1;
                        while ($row = $result->fetch_assoc()) { 
                            $rowClass = '';
                            if ($row['incomplete_information'] == 1) {
                                $rowClass = 'incomplete-row';
                            } elseif ($row['status'] === 'failed') {
                                $rowClass = 'failed-row';
                            }
                        ?>
                            <tr class="<?= $rowClass ?>">
                                <td><?= $serial_no++ ?></td>
                                <td><?= htmlspecialchars($row['task_name']) ?></td>
                                <td><?= htmlspecialchars($row['description'] ?? 'No description') ?></td>
                                <td><?= htmlspecialchars($row['assigned_team_lead_name']) ?></td>
                                <td><?= htmlspecialchars($row['assigned_expert_1'] ?? 'None') ?></td>
                                <td><?= htmlspecialchars($row['assigned_expert_2'] ?? 'None') ?></td>
                                <td><?= htmlspecialchars($row['assigned_expert_3'] ?? 'None') ?></td>
                                <td><?= htmlspecialchars($client_names[$row['client_id']] ?? 'Unknown') ?></td>
                                <td><?= $row['price'] ?></td>
                                <td><?= $row['tl_price'] ?></td>
                                <td><?= $row['expert_price1'] ?></td>
                                <td><?= $row['expert_price2'] ?></td>
                                <td><?= $row['expert_price3'] ?></td>
                                <td><?= $row['total_cost'] ?></td>
                                <td><?= $row['task_date'] ? date('d/m/Y', strtotime($row['task_date'])) : '' ?></td>
                                <td><?= $row['due_date'] ? date('d/m/Y', strtotime($row['due_date'])) : '' ?></td>
                                <td><?= $row['status'] ?></td>
                                <td><?= $row['word_count'] ?></td>
                                <td><?= htmlspecialchars($row['issue']) ?></td>
                                <td><?= $row['incomplete_information'] ? 'Yes' : 'No' ?></td>
                                <td class="text-nowrap">
                                    <!-- Edit Button -->
                                    <button onclick="toggleEditForm(<?= $row['task_id'] ?>)" class="btn btn-warning btn-sm">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <!-- Delete Form -->
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="task_id" value="<?= $row['task_id'] ?>">
                                        <input type="hidden" name="filter_column" value="<?= htmlspecialchars($filter_column) ?>">
                                        <input type="hidden" name="filter_value" value="<?= htmlspecialchars($filter_value) ?>">
                                        <input type="hidden" name="filter_month" value="<?= htmlspecialchars($filter_month) ?>">
                                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_order) ?>">
                                        <input type="hidden" name="page" value="<?= $current_page ?>">
                                        <button type="submit" name="delete" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this task?');">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                    <!-- Edit Form Container -->
                                    <div id="edit-form-<?= $row['task_id'] ?>" class="edit-form">
                                        <form method="post">
                                            <input type="hidden" name="task_id" value="<?= $row['task_id'] ?>">
                                            <input type="hidden" name="filter_column" value="<?= htmlspecialchars($filter_column) ?>">
                                            <input type="hidden" name="filter_value" value="<?= htmlspecialchars($filter_value) ?>">
                                            <input type="hidden" name="filter_month" value="<?= htmlspecialchars($filter_month) ?>">
                                            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_order) ?>">
                                            <input type="hidden" name="page" value="<?= $current_page ?>">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label>Client:</label>
                                                    <select name="client_id" class="form-control form-control-sm select2" required>
                                                        <?php foreach ($clients_data as $client) { 
                                                            $selected = ($client['client_id'] == $row['client_id']) ? 'selected' : '';
                                                            echo "<option value='{$client['client_id']}' $selected>{$client['client_name']}</option>"; 
                                                        } ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Task Name:</label>
                                                    <input type="text" name="task_name" class="form-control form-control-sm" value="<?= $row['task_name'] ?>" required>
                                                </div>
                                                <div class="col-md-12">
                                                    <label>Description:</label>
                                                    <textarea name="description" class="form-control form-control-sm" rows="2"><?= $row['description'] ?></textarea>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Team Lead:</label>
                                                    <select name="team_lead_id" class="form-control form-control-sm select2" required>
                                                        <?php foreach ($team_leads_data as $lead) { 
                                                            $selected = ($lead['team_lead_id'] == $row['team_lead_id']) ? 'selected' : '';
                                                            echo "<option value='{$lead['team_lead_id']}' $selected>{$lead['name']}</option>"; 
                                                        } ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Expert 1 (Optional):</label>
                                                    <select name="expert_id_1" class="form-control form-control-sm select2">
                                                        <option value="">None</option>
                                                        <?php foreach ($experts_data as $expert) { 
                                                            $selected = ($expert['expert_id'] == $row['expert_id_1']) ? 'selected' : '';
                                                            echo "<option value='{$expert['expert_id']}' $selected>{$expert['expert_name']}</option>"; 
                                                        } ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Expert 2 (Optional):</label>
                                                    <select name="expert_id_2" class="form-control form-control-sm select2">
                                                        <option value="">None</option>
                                                        <?php foreach ($experts_data as $expert) { 
                                                            $selected = ($expert['expert_id'] == $row['expert_id_2']) ? 'selected' : '';
                                                            echo "<option value='{$expert['expert_id']}' $selected>{$expert['expert_name']}</option>"; 
                                                        } ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Expert 3 (Optional):</label>
                                                    <select name="expert_id_3" class="form-control form-control-sm select2">
                                                        <option value="">None</option>
                                                        <?php foreach ($experts_data as $expert) { 
                                                            $selected = ($expert['expert_id'] == $row['expert_id_3']) ? 'selected' : '';
                                                            echo "<option value='{$expert['expert_id']}' $selected>{$expert['expert_name']}</option>"; 
                                                        } ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Price:</label>
                                                    <input type="number" name="price" class="form-control form-control-sm" value="<?= $row['price'] ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Team Lead Price:</label>
                                                    <input type="number" name="tl_price" class="form-control form-control-sm" value="<?= $row['tl_price'] ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Expert 1 Price:</label>
                                                    <input type="number" name="expert_price1" class="form-control form-control-sm" value="<?= $row['expert_price1'] ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Expert 2 Price:</label>
                                                    <input type="number" name="expert_price2" class="form-control form-control-sm" value="<?= $row['expert_price2'] ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Expert 3 Price:</label>
                                                    <input type="number" name="expert_price3" class="form-control form-control-sm" value="<?= $row['expert_price3'] ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Task Date:</label>
                                                    <input type="date" name="task_date" class="form-control form-control-sm" value="<?= $row['task_date'] ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Due Date:</label>
                                                    <input type="date" name="due_date" class="form-control form-control-sm" value="<?= $row['due_date'] ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Status:</label>
                                                    <select name="status" class="form-control form-control-sm select2" required>
                                                        <option value="in progress" <?= $row['status'] == 'in progress' ? 'selected' : '' ?>>In Progress</option>
                                                        <option value="submitted" <?= $row['status'] == 'submitted' ? 'selected' : '' ?>>Submitted</option>
                                                        <option value="passed" <?= $row['status'] == 'passed' ? 'selected' : '' ?>>Passed</option>
                                                        <option value="failed" <?= $row['status'] == 'failed' ? 'selected' : '' ?>>Failed</option>
                                                        <option value="submitted late" <?= $row['status'] == 'submitted late' ? 'selected' : '' ?>>Submitted Late</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Word Count:</label>
                                                    <input type="number" name="word_count" class="form-control form-control-sm" value="<?= $row['word_count'] ?>">
                                                </div>
                                                <div class="col-md-6">     
                                                    <label>Issue:</label>     
                                                    <div class="form-check">         
                                                        <input type="checkbox" name="issue[]" value="Low marks" <?= strpos($row['issue'], 'Low marks') !== false ? 'checked' : '' ?>> Low marks<br>         
                                                        <input type="checkbox" name="issue[]" value="Brief not followed" <?= strpos($row['issue'], 'Brief not followed') !== false ? 'checked' : '' ?>> Brief not followed<br>         
                                                        <input type="checkbox" name="issue[]" value="Word count lower" <?= strpos($row['issue'], 'Word count lower') !== false ? 'checked' : '' ?>> Word count lower<br>         
                                                        <input type="checkbox" name="issue[]" value="Wordcount higher" <?= strpos($row['issue'], 'Wordcount higher') !== false ? 'checked' : '' ?>> Wordcount higher<br>         
                                                        <input type="checkbox" name="issue[]" value="Referencing irrelevant" <?= strpos($row['issue'], 'Referencing irrelevant') !== false ? 'checked' : '' ?>> Referencing irrelevant<br>         
                                                        <input type="checkbox" name="issue[]" value="AI used" <?= strpos($row['issue'], 'AI used') !== false ? 'checked' : '' ?>> AI used<br>         
                                                        <input type="checkbox" name="issue[]" value="Plagiarism" <?= strpos($row['issue'], 'Plagiarism') !== false ? 'checked' : '' ?>> Plagiarism<br>         
                                                        <input type="checkbox" name="issue[]" value="Poor quality" <?= strpos($row['issue'], 'Poor quality') !== false ? 'checked' : '' ?>> Poor quality<br>         
                                                        <input type="checkbox" name="issue[]" value="Money Less Taken" <?= strpos($row['issue'], 'Money Less Taken') !== false ? 'checked' : '' ?>> Money Less Taken<br>
                                                    </div> 
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Incomplete Information:</label>
                                                    <div class="form-check">
                                                        <input type="checkbox" name="incomplete_information" class="form-check-input" id="incomplete_information_edit" <?= $row['incomplete_information'] ? 'checked' : '' ?>>
                                                        <label class="form-check-label" for="incomplete_information_edit">Incomplete Information</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-12">
                                                    <button type="submit" name="edit" class="btn btn-success btn-sm w-100">
                                                        <i class="fas fa-save me-2"></i>Save Changes
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- Improved Pagination -->
            <nav>
                <ul class="pagination justify-content-center">
                    <?php if ($current_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1<?= !empty($filter_column) ? "&filter_column=$filter_column&filter_value=$filter_value" : '' ?><?= !empty($filter_month) ? "&filter_month=$filter_month" : '' ?><?= !empty($sort_order) && $sort_order != 'default' ? "&sort=$sort_order" : '' ?>">
                                First
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $current_page - 1 ?><?= !empty($filter_column) ? "&filter_column=$filter_column&filter_value=$filter_value" : '' ?><?= !empty($filter_month) ? "&filter_month=$filter_month" : '' ?><?= !empty($sort_order) && $sort_order != 'default' ? "&sort=$sort_order" : '' ?>">
                                Previous
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- Always show first page -->
                    <li class="page-item <?= 1 == $current_page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=1<?= !empty($filter_column) ? "&filter_column=$filter_column&filter_value=$filter_value" : '' ?><?= !empty($filter_month) ? "&filter_month=$filter_month" : '' ?><?= !empty($sort_order) && $sort_order != 'default' ? "&sort=$sort_order" : '' ?>">
                            1
                        </a>
                    </li>

                    <?php if ($current_page > 3): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>

                    <!-- Show current page -->
                    <?php if ($current_page > 1 && $current_page <= $total_pages): ?>
                        <?php if ($current_page > 2): ?>
                            <li class="page-item active">
                                <a class="page-link" href="?page=<?= $current_page ?><?= !empty($filter_column) ? "&filter_column=$filter_column&filter_value=$filter_value" : '' ?><?= !empty($filter_month) ? "&filter_month=$filter_month" : '' ?><?= !empty($sort_order) && $sort_order != 'default' ? "&sort=$sort_order" : '' ?>">
                                    <?= $current_page ?>
                                </a>
                            </li>
                        <?php endif; ?>

                        <!-- Show next page if exists -->
                        <?php if ($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $current_page + 1 ?><?= !empty($filter_column) ? "&filter_column=$filter_column&filter_value=$filter_value" : '' ?><?= !empty($filter_month) ? "&filter_month=$filter_month" : '' ?><?= !empty($sort_order) && $sort_order != 'default' ? "&sort=$sort_order" : '' ?>">
                                    <?= $current_page + 1 ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($total_pages > 1): ?>
                        <?php if ($total_pages - $current_page > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>

                        <!-- Show second last page if needed -->
                        <?php if ($total_pages > 1 && $current_page < $total_pages - 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $total_pages - 1 ?><?= !empty($filter_column) ? "&filter_column=$filter_column&filter_value=$filter_value" : '' ?><?= !empty($filter_month) ? "&filter_month=$filter_month" : '' ?><?= !empty($sort_order) && $sort_order != 'default' ? "&sort=$sort_order" : '' ?>">
                                    <?= $total_pages - 1 ?>
                                </a>
                            </li>
                        <?php endif; ?>

                        <!-- Always show last page -->
                        <li class="page-item <?= $total_pages == $current_page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($filter_column) ? "&filter_column=$filter_column&filter_value=$filter_value" : '' ?><?= !empty($filter_month) ? "&filter_month=$filter_month" : '' ?><?= !empty($sort_order) && $sort_order != 'default' ? "&sort=$sort_order" : '' ?>">
                                <?= $total_pages ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $current_page + 1 ?><?= !empty($filter_column) ? "&filter_column=$filter_column&filter_value=$filter_value" : '' ?><?= !empty($filter_month) ? "&filter_month=$filter_month" : '' ?><?= !empty($sort_order) && $sort_order != 'default' ? "&sort=$sort_order" : '' ?>">
                                Next
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($filter_column) ? "&filter_column=$filter_column&filter_value=$filter_value" : '' ?><?= !empty($filter_month) ? "&filter_month=$filter_month" : '' ?><?= !empty($sort_order) && $sort_order != 'default' ? "&sort=$sort_order" : '' ?>">
                                Last
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>

                <!-- Direct page jump form -->
                <div class="d-flex justify-content-center mt-2">
                    <form method="get" class="form-inline">
                        <input type="hidden" name="filter_column" value="<?= htmlspecialchars($filter_column) ?>">
                        <input type="hidden" name="filter_value" value="<?= htmlspecialchars($filter_value) ?>">
                        <input type="hidden" name="filter_month" value="<?= htmlspecialchars($filter_month) ?>">
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_order) ?>">
                        <div class="input-group">
                            <input type="number" name="page" class="form-control" min="1" max="<?= $total_pages ?>" value="<?= $current_page ?>" style="width: 100px;">
                            <button type="submit" class="btn btn-primary">Go</button>
                        </div>
                    </form>
                </div>
            </nav>
        <?php else: ?>
            <div class="alert alert-info text-center">
                No tasks found matching your criteria.
            </div>
        <?php endif; ?>

        <br>
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Home
        </a>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Initialize Select2
        $(document).ready(function() {
            $('.select2').select2();
        });

        // Initialize dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            function initDropdown(dropdownContainer) {
                const input = dropdownContainer.querySelector('input[type="text"]');
                const list = dropdownContainer.querySelector('.dropdown-list');
                const hiddenInput = dropdownContainer.querySelector('input[type="hidden"]');

                input.addEventListener('focus', function() {
                    list.style.display = 'block';
                });

                input.addEventListener('input', function() {
                    const searchTerm = input.value.toLowerCase();
                    const items = list.querySelectorAll('li');
                    items.forEach(item => {
                        const text = item.textContent.toLowerCase();
                        item.style.display = text.includes(searchTerm) ? '' : 'none';
                    });
                });

                list.addEventListener('click', function(e) {
                    if (e.target.tagName === 'LI') {
                        input.value = e.target.textContent;
                        hiddenInput.value = e.target.getAttribute('data-value');
                        list.style.display = 'none';
                    }
                });

                document.addEventListener('click', function(e) {
                    if (!dropdownContainer.contains(e.target)) {
                        list.style.display = 'none';
                    }
                });
            }

            // Initialize all dropdown containers
            document.querySelectorAll('.dropdown-container').forEach(initDropdown);
            
            // Initialize filter dropdown if present
            const filterDropdownContainer = document.querySelector('.filter-dropdown-container');
            if (filterDropdownContainer) {
                initFilterDropdown();
            }
        });

        function toggleEditForm(taskId) {
            const editForm = document.getElementById(`edit-form-${taskId}`);
            editForm.style.display = editForm.style.display === 'none' || editForm.style.display === '' ? 'block' : 'none';
        }

        function initFilterDropdown() {
            const container = document.querySelector('.filter-dropdown-container');
            if (!container) return;

            const input = container.querySelector('input');
            const list = container.querySelector('.filter-dropdown-list');
            const hiddenInput = container.querySelector('input[type="hidden"]');
            const filterColumn = document.getElementById('filter_column').value;

            // Populate list based on filter column
            let items = [];
            switch(filterColumn) {
                case 'team_lead_id':
                    items = <?= json_encode($team_leads_data) ?>;
                    break;
                case 'expert_id_1':
                case 'expert_id_2':
                case 'expert_id_3':
                    items = <?= json_encode($experts_data) ?>;
                    break;
                case 'client_id':
                    items = <?= json_encode($clients_data) ?>;
                    break;
            }

            // Populate dropdown list
            list.innerHTML = '';
            items.forEach(item => {
                const li = document.createElement('li');
                li.textContent = filterColumn === 'team_lead_id' ? item.name : 
                                  filterColumn.startsWith('expert') ? item.expert_name : 
                                  item.client_name;
                li.dataset.value = item[Object.keys(item)[0]];
                list.appendChild(li);
            });

            // Event listeners
            input.addEventListener('focus', function() {
                list.style.display = 'block';
            });

            input.addEventListener('input', function() {
                const searchTerm = input.value.toLowerCase();
                const items = list.querySelectorAll('li');
                items.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    item.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });

            list.addEventListener('click', function(e) {
                if (e.target.tagName === 'LI') {
                    input.value = e.target.textContent;
                    hiddenInput.value = e.target.dataset.value;
                    list.style.display = 'none';
                }
            });

            document.addEventListener('click', function(e) {
                if (!container.contains(e.target)) {
                    list.style.display = 'none';
                }
            });
        }

        function updateFilterInput() {
            const filterColumn = document.getElementById('filter_column').value;
            const container = document.getElementById('filter_input_container');
            const specialFilters = ['team_lead_id', 'expert_id_1', 'expert_id_2', 'expert_id_3', 'client_id', 'status'];
            
            if (specialFilters.includes(filterColumn)) {
                let html = '';
                
                if (filterColumn === 'status') {
                    html = `
                        <select name="filter_value" class="form-control">
                            <option value="in progress">In Progress</option>
                            <option value="submitted">Submitted</option>
                            <option value="passed">Passed</option>
                            <option value="failed">Failed</option>
                            <option value="submitted late">Submitted Late</option>
                        </select>
                    `;
                } else {
                    html = `
                        <div class="filter-dropdown-container">
                            <input type="text" name="filter_value_display" class="form-control" 
                                   placeholder="Search..." id="filter-search">
                            <ul class="filter-dropdown-list" id="filter-list"></ul>
                            <input type="hidden" name="filter_value" id="filter-value">
                        </div>
                    `;
                }
                
                container.innerHTML = html;
                
                // Initialize the dropdown if needed
                if (filterColumn !== 'status') {
                    setTimeout(initFilterDropdown, 100);
                }
            } else {
                if (filterColumn === 'price') {
                    container.innerHTML = `<input type="number" name="filter_value" class="form-control" placeholder="Enter price">`;
                } else if (filterColumn === 'task_date' || filterColumn === 'due_date') {
                    container.innerHTML = `<input type="date" name="filter_value" class="form-control">`;
                } else if (filterColumn === '') {
                    container.innerHTML = `<input type="text" name="filter_value" class="form-control" placeholder="Select a filter column first" disabled>`;
                } else {
                    container.innerHTML = `<input type="text" name="filter_value" class="form-control" placeholder="Enter search value">`;
                }
            }
        }

        document.getElementById('copyDetailsBtn').addEventListener('click', function() {
            const taskName = document.querySelector('input[name="task_name"]').value;
            const description = document.querySelector('textarea[name="description"]').value;
            const clientName = document.getElementById('client-search').value;
            const teamLeadName = document.getElementById('team-lead-search').value;
            const taskDate = document.querySelector('input[name="task_date"]').value;
            const dueDate = document.querySelector('input[name="due_date"]').value;
            
            // Get expert names
            const expert1Name = document.getElementById('expert1-search').value;
            const expert2Name = document.getElementById('expert2-search').value;
            const expert3Name = document.getElementById('expert3-search').value;
            
            // Format experts list
            let expertsText = '';
            if (expert1Name) expertsText += expert1Name;
            if (expert2Name) expertsText += expertsText ? ', ' + expert2Name : expert2Name;
            if (expert3Name) expertsText += expertsText ? ', ' + expert3Name : expert3Name;
            expertsText = expertsText || 'None';
            
            // Format the text
            const detailsText = `Code: ${clientName}
Task Title: ${taskName}
Description: ${description}
Assigned to: ${teamLeadName}
Deadline: ${dueDate}
Issue date: ${taskDate}
Expert assigned: ${expertsText}`;

            // Copy to clipboard
            navigator.clipboard.writeText(detailsText)
            .then(() => {
                alert('Task details copied to clipboard!');
            })
            .catch(err => {
                console.error('Failed to copy:', err);
                alert('Failed to copy. Please try again.');
            });
        });

        // Initialize the edit forms as hidden on page load
        document.querySelectorAll('.edit-form').forEach(form => {
            form.style.display = 'none';
        });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>