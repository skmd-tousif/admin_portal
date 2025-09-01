<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

// Step 1: Start the session
session_start();

// Step 2: Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../login.php");
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mydb";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all clients for the dropdown
$clients_dropdown_query = "SELECT DISTINCT client_id, client_name FROM client";
$clients_dropdown_result = $conn->query($clients_dropdown_query);

if (!$clients_dropdown_result) {
    die("Error fetching clients: " . $conn->error);
}

// Fetch all colleges for the dropdown
$colleges_dropdown_query = "SELECT DISTINCT college_name, country FROM colleges";
$colleges_dropdown_result = $conn->query($colleges_dropdown_query);

if (!$colleges_dropdown_result) {
    die("Error fetching colleges: " . $conn->error);
}

// Function to calculate dues for a client
function calculateDues($client_id, $conn) {
    $sql = "SELECT initial_dues FROM client WHERE client_id = $client_id";
    $result = $conn->query($sql);
    if (!$result) {
        die("Error fetching initial dues: " . $conn->error);
    }
    $row = $result->fetch_assoc();
    $initial_dues = $row['initial_dues'];

    $sql = "SELECT COALESCE(SUM(price), 0) AS total_price FROM task WHERE client_id = $client_id";
    $result = $conn->query($sql);
    if (!$result) {
        die("Error fetching total price: " . $conn->error);
    }
    $row = $result->fetch_assoc();
    $total_price = $row['total_price'];

    $sql = "SELECT COALESCE(SUM(amount_paid), 0) AS total_paid FROM adminclientpayment WHERE client_id = $client_id";
    $result = $conn->query($sql);
    if (!$result) {
        die("Error fetching total paid: " . $conn->error);
    }
    $row = $result->fetch_assoc();
    $total_paid = $row['total_paid'];

    $due_payment = ($initial_dues + $total_price) - $total_paid;

    $sql = "UPDATE client SET due_payment = $due_payment WHERE client_id = $client_id";
    if (!$conn->query($sql)) {
        die("Error updating due payment: " . $conn->error);
    }
}

// Recalculate dues for all clients
$recalculate_dues_query = "SELECT DISTINCT client_id FROM client";
$recalculate_dues_result = $conn->query($recalculate_dues_query);

if (!$recalculate_dues_result) {
    die("Error fetching client IDs: " . $conn->error);
}

while ($row = $recalculate_dues_result->fetch_assoc()) {
    calculateDues($row['client_id'], $conn);
}

// Handle form actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add'])) {
        $client_name = $_POST['client_name'];
        $college_name = $_POST['college_name'];
        $reffered_by = $_POST['reffered_by'];
        $reffered_by_client_id = !empty($_POST['reffered_by_client_id']) ? $_POST['reffered_by_client_id'] : NULL;
        $initial_dues = $_POST['initial_dues'];
        $login_id = $_POST['login_id'];
        $login_password = $_POST['login_password'];
        $label = isset($_POST['label']) ? implode(",", $_POST['label']) : NULL;

        $check_sql = "SELECT client_id FROM client WHERE client_name = '$client_name'";
        $check_result = $conn->query($check_sql);
        if ($check_result->num_rows > 0) {
            echo "<script>alert('Client name already exists. Please choose a unique name.');</script>";
        } else {
            $sql = "INSERT INTO client (client_name, college_name, reffered_by, reffered_by_client_id, initial_dues, login_id, login_password, label) 
                    VALUES ('$client_name', '$college_name', '$reffered_by', " . ($reffered_by_client_id === NULL ? "NULL" : "'$reffered_by_client_id'") . ", '$initial_dues', '$login_id', '$login_password', '$label')";
            if ($conn->query($sql) === TRUE) {
                $client_id = $conn->insert_id;
                calculateDues($client_id, $conn);
                
                // Redirect with current parameters
                $params = [];
                if (isset($_POST['page'])) $params['page'] = $_POST['page'];
                if (isset($_POST['filter_column'])) $params['filter_column'] = $_POST['filter_column'];
                if (isset($_POST['filter_value'])) $params['filter_value'] = $_POST['filter_value'];
                if (isset($_POST['sort'])) $params['sort'] = $_POST['sort'];
                
                $query_string = http_build_query($params);
                header("Location: " . $_SERVER['PHP_SELF'] . '?' . $query_string);
                exit();
            } else {
                echo "Error: " . $sql . "<br>" . $conn->error;
            }
        }
    }

    if (isset($_POST['edit'])) {
        $client_id = $_POST['client_id'];
        $client_name = $_POST['client_name'];
        $college_name = $_POST['college_name'];
        $reffered_by = $_POST['reffered_by'];
        $reffered_by_client_id = !empty($_POST['reffered_by_client_id']) ? $_POST['reffered_by_client_id'] : NULL;
        $initial_dues = $_POST['initial_dues'];
        $login_id = $_POST['login_id'];
        $login_password = $_POST['login_password'];
        $label = isset($_POST['label']) ? implode(",", $_POST['label']) : NULL;

        $sql = "UPDATE client SET client_name='$client_name', college_name='$college_name', reffered_by='$reffered_by', 
                reffered_by_client_id=" . ($reffered_by_client_id === NULL ? "NULL" : "'$reffered_by_client_id'") . ", 
                initial_dues='$initial_dues', login_id='$login_id', login_password='$login_password', label='$label' 
                WHERE client_id='$client_id'";
        if ($conn->query($sql) === TRUE) {
            calculateDues($client_id, $conn);
            
            // Redirect with current parameters
            $params = [];
            if (isset($_POST['page'])) $params['page'] = $_POST['page'];
            if (isset($_POST['filter_column'])) $params['filter_column'] = $_POST['filter_column'];
            if (isset($_POST['filter_value'])) $params['filter_value'] = $_POST['filter_value'];
            if (isset($_POST['sort'])) $params['sort'] = $_POST['sort'];
            
            $query_string = http_build_query($params);
            header("Location: " . $_SERVER['PHP_SELF'] . '?' . $query_string);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }

    if (isset($_POST['delete'])) {
        $client_id = $_POST['client_id'];
        $sql = "DELETE FROM client WHERE client_id='$client_id'";
        if ($conn->query($sql) === TRUE) {
            // Redirect with current parameters
            $params = [];
            if (isset($_POST['page'])) $params['page'] = $_POST['page'];
            if (isset($_POST['filter_column'])) $params['filter_column'] = $_POST['filter_column'];
            if (isset($_POST['filter_value'])) $params['filter_value'] = $_POST['filter_value'];
            if (isset($_POST['sort'])) $params['sort'] = $_POST['sort'];
            
            $query_string = http_build_query($params);
            header("Location: " . $_SERVER['PHP_SELF'] . '?' . $query_string);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }
}

// Handle filtering - change to GET for pagination
$filter_column = isset($_GET['filter_column']) ? $_GET['filter_column'] : '';
$filter_value = isset($_GET['filter_value']) ? $_GET['filter_value'] : '';

// Pagination setup
$rows_per_page = 100;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $rows_per_page;

// Base SQL query
$base_sql = "SELECT DISTINCT c.*, cl.country 
             FROM client c
             LEFT JOIN colleges cl ON c.college_name = cl.college_name";

$where_clause = '';
if (!empty($filter_column) && !empty($filter_value)) {
    if ($filter_column === "college_name") {
        $where_clause = " WHERE c.college_name LIKE '%$filter_value%'";
    } else {
        $where_clause = " WHERE $filter_column LIKE '%$filter_value%'";
    }
}

// Count total rows for pagination
$count_sql = "SELECT COUNT(DISTINCT c.client_id) AS total 
              FROM client c
              LEFT JOIN colleges cl ON c.college_name = cl.college_name
              $where_clause";

$count_result = $conn->query($count_sql);
if (!$count_result) {
    die("Count SQL Error: " . $conn->error);
}
$count_row = $count_result->fetch_assoc();
$total_rows = $count_row['total'];
$total_pages = ceil($total_rows / $rows_per_page);

// Main query with pagination
$sql = $base_sql . $where_clause;

// Handle sorting
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : '';
if ($sort_order === 'asc') {
    $sql .= " ORDER BY due_payment ASC";
} elseif ($sort_order === 'desc') {
    $sql .= " ORDER BY due_payment DESC";
}

// Add pagination limits
$sql .= " LIMIT $offset, $rows_per_page";

$result = $conn->query($sql);
if (!$result) {
    die("SQL Error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Management</title>
    <!-- Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
        }
        
        body {
            padding-top: 70px; /* This gives space for the fixed navbar */
            background-color: #f8f9fa;
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

        .edit-form { display: none; }
        .dropdown-container { position: relative; }
        .dropdown-container input[type="text"] {
            width: 100%; padding: 5px; margin-bottom: 5px;
            border: 1px solid #ccc; border-radius: 4px;
        }
        .dropdown-container ul {
            max-height: 200px; overflow-y: auto; border: 1px solid #ccc;
            margin: 0; padding: 0; list-style: none; position: absolute;
            width: 100%; background: white; z-index: 1000; display: none; /* Initialize as hidden */
        }
        .dropdown-container ul li { padding: 5px; cursor: pointer; }
        .dropdown-container ul li:hover { background-color: #f0f0f0; }
        .pagination .page-item.active .page-link {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        .pagination .page-link {
            color: var(--secondary-color);
        }
        .pagination .page-item.disabled .page-link {
            color: #6c757d;
        }
    </style>
    <script>
        function toggleEditForm(clientId) {
            const editForm = document.getElementById('edit-form-' + clientId);
            if (editForm.style.display === 'table-row') {
                editForm.style.display = 'none';
            } else {
                document.querySelectorAll('.edit-form').forEach(form => form.style.display = 'none');
                editForm.style.display = 'table-row';
            }
        }

        function toggleDropdown(input) {
            const dropdown = input.nextElementSibling;
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block'; // Toggle visibility
        }

        document.addEventListener('input', function(event) {
            if (event.target.classList.contains('search-input')) {
                const input = event.target;
                const dropdown = input.nextElementSibling;
                const options = dropdown.querySelectorAll('li');
                const filter = input.value.toLowerCase();
                options.forEach(option => {
                    option.style.display = option.textContent.toLowerCase().includes(filter) ? '' : 'none';
                });
            }
        });

        document.addEventListener('click', function(event) {
            if (!event.target.classList.contains('search-input')) {
                document.querySelectorAll('.dropdown-container ul').forEach(dropdown => {
                    dropdown.style.display = 'none'; // Hide all dropdowns when clicking outside
                });
            }
            if (event.target.tagName === 'LI') {
                const input = event.target.closest('.dropdown-container').querySelector('.search-input');
                input.value = event.target.textContent;
                event.target.closest('ul').style.display = 'none'; // Hide dropdown after selection
            }
        });
    </script>
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
                <!-- Main Navigation Links -->
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="task.php">Tasks</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="client.php">Clients</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="visualization.php">Generate Sheets</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_client_payment.php">A-Client Payments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="teamlead.php">Team Leads</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_tl_payment.php">A-TL Payments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="expert.php">Experts</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="tl_expert_payment.php">TL-Expert Payments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="colleges.php">Colleges</a>
                    </li>
                </ul>
                
                <!-- Right-aligned items -->
                <div class="d-flex">
                    <!-- Back to Home Button -->
                    <a href="index.php" class="btn btn-outline-light me-2">
                        <i class="fas fa-home me-1"></i> Home
                    </a>
                    
                    <!-- Logout Button -->
                    <a href="logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container mt-5">
        <h2>Add New Client</h2>
        <form method="POST" class="mb-4">
            <!-- Add hidden fields for current state -->
            <input type="hidden" name="page" value="<?= $page ?>">
            <input type="hidden" name="filter_column" value="<?= htmlspecialchars($filter_column) ?>">
            <input type="hidden" name="filter_value" value="<?= htmlspecialchars($filter_value) ?>">
            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_order) ?>">
            
            <div class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="client_name" class="form-control" placeholder="Client Name" required>
                </div>
                <div class="col-md-3">
                    <div class="dropdown-container">
                        <input type="text" name="college_name" placeholder="Search college..." class="search-input" onfocus="toggleDropdown(this)" required>
                        <ul>
                            <?php $colleges_dropdown_result->data_seek(0);
                            while ($row = $colleges_dropdown_result->fetch_assoc()) { ?>
                                <li><?php echo $row['college_name']; ?></li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="dropdown-container">
                        <input type="text" name="reffered_by" placeholder="Search referred by..." class="search-input" onfocus="toggleDropdown(this)">
                        <ul>
                            <?php $clients_dropdown_result->data_seek(0);
                            while ($row = $clients_dropdown_result->fetch_assoc()) { ?>
                                <li><?php echo $row['client_name']; ?></li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="dropdown-container">
                        <input type="text" name="reffered_by_client_id" placeholder="Search referred by client ID..." class="search-input" onfocus="toggleDropdown(this)">
                        <ul>
                            <?php $clients_dropdown_result->data_seek(0);
                            while ($row = $clients_dropdown_result->fetch_assoc()) { ?>
                                <li><?php echo $row['client_name']; ?></li>
                            <?php } ?>
                        </ul>
                    </div>
                </div>
                <div class="col-md-2">
                    <input type="number" name="initial_dues" class="form-control" placeholder="Initial Dues" required>
                </div>
                <div class="col-md-3">
                    <input type="text" name="login_id" class="form-control" placeholder="Login ID" required>
                </div>
                <div class="col-md-3">
                    <input type="text" name="login_password" class="form-control" placeholder="Login Password" required>
                </div>
                <div class="col-md-12">
                    <label>Label:</label>
                    <div class="form-check">
                        <input type="checkbox" name="label[]" value="Dormant client"> Dormant client<br>
                        <input type="checkbox" name="label[]" value="Potential client"> Potential client<br>
                        <input type="checkbox" name="label[]" value="Edits"> Edits<br>
                        <input type="checkbox" name="label[]" value="Level 1 dues"> Level 1 dues<br>
                        <input type="checkbox" name="label[]" value="Level 2 dues"> Level 2 dues<br>
                        <input type="checkbox" name="label[]" value="Level 3 dues"> Level 3 dues<br>
                        <input type="checkbox" name="label[]" value="Red dues"> Red dues<br>
                        <input type="checkbox" name="label[]" value="Lost clients"> Lost clients<br>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" name="add" class="btn btn-primary w-100">Add Client</button>
                </div>
            </div>
        </form>

        <h2>Filter Clients</h2>
        <form method="GET" class="mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <select name="filter_column" id="filter_column" class="form-control" required>
                        <option value="client_name" <?= $filter_column == 'client_name' ? 'selected' : '' ?>>Client Name</option>
                        <option value="college_name" <?= $filter_column == 'college_name' ? 'selected' : '' ?>>College Name</option>
                        <option value="reffered_by" <?= $filter_column == 'reffered_by' ? 'selected' : '' ?>>Referred By</option>
                        <option value="due_payment" <?= $filter_column == 'due_payment' ? 'selected' : '' ?>>Due Payment</option>
                        <option value="initial_dues" <?= $filter_column == 'initial_dues' ? 'selected' : '' ?>>Initial Dues</option>
                        <option value="login_id" <?= $filter_column == 'login_id' ? 'selected' : '' ?>>Login ID</option>
                        <option value="label" <?= $filter_column == 'label' ? 'selected' : '' ?>>Label</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <input type="text" name="filter_value" class="form-control" placeholder="Enter search value" 
                           value="<?= htmlspecialchars($filter_value) ?>" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100">Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="client.php" class="btn btn-outline-secondary w-100">Clear Filters</a>
                </div>
            </div>
        </form>

        <h2>Sort Clients by Due Payment</h2>
        <form method="GET" class="mb-4">
            <!-- Preserve filter parameters -->
            <input type="hidden" name="filter_column" value="<?= htmlspecialchars($filter_column) ?>">
            <input type="hidden" name="filter_value" value="<?= htmlspecialchars($filter_value) ?>">
            <input type="hidden" name="page" value="<?= $page ?>">
            
            <div class="row g-3">
                <div class="col-md-4">
                    <button type="submit" name="sort" value="asc" class="btn btn-secondary w-100">Sort Ascending</button>
                </div>
                <div class="col-md-4">
                    <button type="submit" name="sort" value="desc" class="btn btn-secondary w-100">Sort Descending</button>
                </div>
                <div class="col-md-4">
                    <button type="submit" name="sort" value="" class="btn btn-secondary w-100">No Sort</button>
                </div>
            </div>
        </form>

        <h2>Clients List (Showing <?= $offset + 1 ?> - <?= min($offset + $rows_per_page, $total_rows) ?> of <?= $total_rows ?>)</h2>
        <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Serial No</th>
                    <th>Name</th>
                    <th>College</th>
                    <th>Country</th>
                    <th>Referred By</th>
                    <th>Due</th>
                    <th>Login ID</th>
                    <th>Login Password</th>
                    <th>Label</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $serial_no = $offset + 1;
                while ($row = $result->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo $serial_no++; ?></td>
                        <td><?php echo $row['client_name']; ?></td>
                        <td><?php echo $row['college_name']; ?></td>
                        <td><?php echo $row['country']; ?></td>
                        <td><?php echo $row['reffered_by']; ?></td>
                        <td><?php echo $row['due_payment']; ?></td>
                        <td><?php echo $row['login_id']; ?></td>
                        <td><?php echo $row['login_password']; ?></td>
                        <td><?php echo $row['label']; ?></td>
                        <td>
                            <button onclick="toggleEditForm(<?php echo $row['client_id']; ?>)" class="btn btn-warning btn-sm">Edit</button>
                            <form method="POST" style="display:inline-block;">
                                <input type="hidden" name="client_id" value="<?php echo $row['client_id']; ?>">
                                <!-- Preserve current state -->
                                <input type="hidden" name="page" value="<?= $page ?>">
                                <input type="hidden" name="filter_column" value="<?= htmlspecialchars($filter_column) ?>">
                                <input type="hidden" name="filter_value" value="<?= htmlspecialchars($filter_value) ?>">
                                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_order) ?>">
                                <button type="submit" name="delete" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?');">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <tr id="edit-form-<?php echo $row['client_id']; ?>" class="edit-form">
                        <td colspan="10">
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="client_id" value="<?php echo $row['client_id']; ?>">
                                <!-- Preserve current state -->
                                <input type="hidden" name="page" value="<?= $page ?>">
                                <input type="hidden" name="filter_column" value="<?= htmlspecialchars($filter_column) ?>">
                                <input type="hidden" name="filter_value" value="<?= htmlspecialchars($filter_value) ?>">
                                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_order) ?>">
                                
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label>Client Name</label>
                                        <input type="text" name="client_name" class="form-control" value="<?php echo $row['client_name']; ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label>College Name</label>
                                        <div class="dropdown-container">
                                            <input type="text" name="college_name" placeholder="Search college..." class="search-input" 
                                                value="<?php echo $row['college_name']; ?>" 
                                                onfocus="toggleDropdown(this)" required>
                                            <ul>
                                                <?php $colleges_dropdown_result->data_seek(0);
                                                while ($college_row = $colleges_dropdown_result->fetch_assoc()) { ?>
                                                    <li><?php echo $college_row['college_name']; ?></li>
                                                <?php } ?>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label>Referred By</label>
                                        <div class="dropdown-container">
                                            <input type="text" name="reffered_by" placeholder="Search referred by..." class="search-input" 
                                                value="<?php echo $row['reffered_by']; ?>" 
                                                onfocus="toggleDropdown(this)">
                                            <ul>
                                                <?php $clients_dropdown_result->data_seek(0);
                                                while ($client_row = $clients_dropdown_result->fetch_assoc()) { ?>
                                                    <li><?php echo $client_row['client_name']; ?></li>
                                                <?php } ?>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label>Referred by client id</label>
                                        <div class="dropdown-container">
                                            <input type="text" name="reffered_by_client_id" placeholder="Search referred by client ID..." 
                                                class="search-input" value="<?php echo $row['reffered_by_client_id']; ?>" 
                                                onfocus="toggleDropdown(this)">
                                            <ul>
                                                <?php $clients_dropdown_result->data_seek(0);
                                                while ($client_row = $clients_dropdown_result->fetch_assoc()) { ?>
                                                    <li><?php echo $client_row['client_name']; ?></li>
                                                <?php } ?>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label>Initial Dues</label>
                                        <input type="number" name="initial_dues" class="form-control" value="<?php echo $row['initial_dues']; ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label>Login ID</label>
                                        <input type="text" name="login_id" class="form-control" value="<?php echo $row['login_id']; ?>" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label>Login Password</label>
                                        <input type="text" name="login_password" class="form-control" value="<?php echo $row['login_password']; ?>" required>
                                    </div>
                                    <div class="col-md-12">
                                        <label>Label:</label>
                                        <div class="form-check">
                                            <?php $label = $row['label'] ?? ''; ?>
                                            <input type="checkbox" name="label[]" value="Dormant client" <?php echo strpos($label, 'Dormant client') !== false ? 'checked' : ''; ?>> Dormant client<br>
                                            <input type="checkbox" name="label[]" value="Potential client" <?php echo strpos($label, 'Potential client') !== false ? 'checked' : ''; ?>> Potential client<br>
                                            <input type="checkbox" name="label[]" value="Edits" <?php echo strpos($label, 'Edits') !== false ? 'checked' : ''; ?>> Edits<br>
                                            <input type="checkbox" name="label[]" value="Level 1 dues" <?php echo strpos($label, 'Level 1 dues') !== false ? 'checked' : ''; ?>> Level 1 dues<br>
                                            <input type="checkbox" name="label[]" value="Level 2 dues" <?php echo strpos($label, 'Level 2 dues') !== false ? 'checked' : ''; ?>> Level 2 dues<br>
                                            <input type="checkbox" name="label[]" value="Level 3 dues" <?php echo strpos($label, 'Level 3 dues') !== false ? 'checked' : ''; ?>> Level 3 dues<br>
                                            <input type="checkbox" name="label[]" value="Red dues" <?php echo strpos($label, 'Red dues') !== false ? 'checked' : ''; ?>> Red dues<br>
                                            <input type="checkbox" name="label[]" value="Lost clients" <?php echo strpos($label, 'Lost clients') !== false ? 'checked' : ''; ?>> Lost clients<br>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <button type="submit" name="edit" class="btn btn-success w-100">Save</button>
                                    </div>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
        
        <!-- Pagination -->
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php
                // Preserve filter and sort parameters
                $query_params = [];
                if (!empty($filter_column)) $query_params['filter_column'] = $filter_column;
                if (!empty($filter_value)) $query_params['filter_value'] = $filter_value;
                if (!empty($sort_order)) $query_params['sort'] = $sort_order;
                
                // Previous button
                if ($page > 1) {
                    $prev_page = $page - 1;
                    $query_params['page'] = $prev_page;
                    $query_string = http_build_query($query_params);
                    echo '<li class="page-item"><a class="page-link" href="?'.$query_string.'">Previous</a></li>';
                } else {
                    echo '<li class="page-item disabled"><a class="page-link" href="#">Previous</a></li>';
                }
                
                // Page numbers
                $max_visible_pages = 5;
                $start_page = max(1, $page - floor($max_visible_pages / 2));
                $end_page = min($total_pages, $start_page + $max_visible_pages - 1);
                
                if ($start_page > 1) {
                    $query_params['page'] = 1;
                    $query_string = http_build_query($query_params);
                    echo '<li class="page-item"><a class="page-link" href="?'.$query_string.'">1</a></li>';
                    if ($start_page > 2) {
                        echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                    }
                }
                
                for ($i = $start_page; $i <= $end_page; $i++) {
                    $active = $i == $page ? 'active' : '';
                    $query_params['page'] = $i;
                    $query_string = http_build_query($query_params);
                    echo '<li class="page-item '.$active.'"><a class="page-link" href="?'.$query_string.'">'.$i.'</a></li>';
                }
                
                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
                    }
                    $query_params['page'] = $total_pages;
                    $query_string = http_build_query($query_params);
                    echo '<li class="page-item"><a class="page-link" href="?'.$query_string.'">'.$total_pages.'</a></li>';
                }
                
                // Next button
                if ($page < $total_pages) {
                    $next_page = $page + 1;
                    $query_params['page'] = $next_page;
                    $query_string = http_build_query($query_params);
                    echo '<li class="page-item"><a class="page-link" href="?'.$query_string.'">Next</a></li>';
                } else {
                    echo '<li class="page-item disabled"><a class="page-link" href="#">Next</a></li>';
                }
                ?>
            </ul>
        </nav>
        <!-- Page Jump Form -->
        <div class="d-flex justify-content-center mt-3">
            <form method="GET" class="form-inline">
                <!-- Preserve existing parameters -->
                <input type="hidden" name="filter_column" value="<?= htmlspecialchars($filter_column) ?>">
                <input type="hidden" name="filter_value" value="<?= htmlspecialchars($filter_value) ?>">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_order) ?>">
                
                <div class="input-group">
                    <span class="input-group-text">Go to Page</span>
                    <input type="number" name="page" class="form-control" min="1" max="<?= $total_pages ?>" 
                           value="<?= $page ?>" style="width: 80px;">
                    <span class="input-group-text">of <?= $total_pages ?></span>
                    <button type="submit" class="btn btn-primary">Go</button>
                </div>
            </form>
        </div>
        
        <br>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
    <?php include 'footer.php'; ?>
</body>
</html>

<?php $conn->close(); ?>