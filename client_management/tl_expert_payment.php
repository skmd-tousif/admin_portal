<?php

// Step 1: Start the session
session_start();

// Step 2: Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    // If the admin is not logged in, redirect to the login page
    header("Location: ../task.php"); // Adjust the path if needed
    exit(); // Stop further execution of the script
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mydb";

// Connect to database
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to calculate dues for an Expert
function calculateExpertDues($expert_id, $conn) {
    // Get initial dues
    $sql = "SELECT initial_dues FROM expert WHERE expert_id = $expert_id";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $initial_dues = $row['initial_dues'];

    // Get total earnings from task table
    $sql = "SELECT 
                COALESCE(SUM(CASE WHEN expert_id_1 = $expert_id THEN expert_price1 ELSE 0 END), 0) +
                COALESCE(SUM(CASE WHEN expert_id_2 = $expert_id THEN expert_price2 ELSE 0 END), 0) +
                COALESCE(SUM(CASE WHEN expert_id_3 = $expert_id THEN expert_price3 ELSE 0 END), 0) AS total_earnings
            FROM task
            WHERE expert_id_1 = $expert_id OR expert_id_2 = $expert_id OR expert_id_3 = $expert_id";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $total_earnings = $row['total_earnings'];

    // Get total payments from tlexpertpayment table
    $sql = "SELECT COALESCE(SUM(amount_paid), 0) AS total_paid 
            FROM tlexpertpayment 
            WHERE expert_id = $expert_id";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $total_paid = $row['total_paid'];

    // Calculate dues
    $dues = ($initial_dues + $total_earnings) - $total_paid;

    // Update dues in expert table
    $sql = "UPDATE expert SET dues = $dues WHERE expert_id = $expert_id";
    $conn->query($sql);
}

// Handle form actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_payment'])) {
        $expert_id = $_POST['expert_id'];
        $payment_date = $_POST['payment_date'];
        $amount_paid = $_POST['amount_paid'];
        $team_lead_id = $_POST['team_lead_id'];
        $description = $_POST['description'];

        // Fetch Expert's name from expert table
        $sql = "SELECT expert_name FROM expert WHERE expert_id = $expert_id";
        $result = $conn->query($sql);
        if (!$result) {
            die("Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $expert_name = $row['expert_name'];

        // Fetch Team Lead's name from teamlead table
        $sql = "SELECT name FROM teamlead WHERE team_lead_id = $team_lead_id";
        $result = $conn->query($sql);
        if (!$result) {
            die("Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $team_lead_name = $row['name'];

        // Insert payment into tlexpertpayment table
        $sql = "INSERT INTO tlexpertpayment (payment_date, amount_paid, expert_id, expert_name, team_lead_id, team_lead_name, description) 
                VALUES ('$payment_date', '$amount_paid', '$expert_id', '$expert_name', '$team_lead_id', '$team_lead_name', '$description')";
        if ($conn->query($sql) === TRUE) {
            // Recalculate dues for the Expert
            calculateExpertDues($expert_id, $conn);

            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }

    if (isset($_POST['edit_payment'])) {
        $transaction_id = $_POST['transaction_id'];
        $payment_date = $_POST['payment_date'];
        $amount_paid = $_POST['amount_paid'];
        $expert_id = $_POST['expert_id'];
        $team_lead_id = $_POST['team_lead_id'];
        $description = $_POST['description'];

        // Fetch Expert's name from expert table
        $sql = "SELECT expert_name FROM expert WHERE expert_id = $expert_id";
        $result = $conn->query($sql);
        if (!$result) {
            die("Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $expert_name = $row['expert_name'];

        // Fetch Team Lead's name from teamlead table
        $sql = "SELECT name FROM teamlead WHERE team_lead_id = $team_lead_id";
        $result = $conn->query($sql);
        if (!$result) {
            die("Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $team_lead_name = $row['name'];

        // Update payment in tlexpertpayment table
        $sql = "UPDATE tlexpertpayment 
                SET payment_date = '$payment_date', amount_paid = '$amount_paid', expert_id = '$expert_id', expert_name = '$expert_name', team_lead_id = '$team_lead_id', team_lead_name = '$team_lead_name', description = '$description' 
                WHERE transaction_id = $transaction_id";
        if ($conn->query($sql) === TRUE) {
            // Recalculate dues for the Expert
            calculateExpertDues($expert_id, $conn);

            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }

    if (isset($_POST['delete_payment'])) {
        $transaction_id = $_POST['transaction_id'];

        // Get expert_id and amount_paid before deleting the payment
        $sql = "SELECT expert_id, amount_paid FROM tlexpertpayment WHERE transaction_id = $transaction_id";
        $result = $conn->query($sql);
        if (!$result) {
            die("Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $expert_id = $row['expert_id'];
        $amount_paid = $row['amount_paid'];

        // Delete payment from tlexpertpayment table
        $sql = "DELETE FROM tlexpertpayment WHERE transaction_id = $transaction_id";
        if ($conn->query($sql) === TRUE) {
            // Add the deleted payment amount back to the expert's dues
            $sql = "UPDATE expert SET dues = dues + $amount_paid WHERE expert_id = $expert_id";
            if ($conn->query($sql) === TRUE) {
                // Recalculate dues for the Expert
                calculateExpertDues($expert_id, $conn);

                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                echo "Error: " . $sql . "<br>" . $conn->error;
            }
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }
}

// Fetch all Experts for the dropdown
$sql = "SELECT expert_id, expert_name FROM expert";
$experts_result = $conn->query($sql);
if (!$experts_result) {
    die("Query failed: " . $conn->error);
}

// Fetch all Team Leads for the dropdown
$sql = "SELECT team_lead_id, name FROM teamlead";
$tls_result = $conn->query($sql);
if (!$tls_result) {
    die("Query failed: " . $conn->error);
}

// Fetch all payments to Experts
$sql = "SELECT * FROM tlexpertpayment";
if (isset($_GET['filter_month'])) {
    $filter_month = $_GET['filter_month'];
    $sql .= " WHERE DATE_FORMAT(payment_date, '%Y-%m') = '$filter_month'";
}
$payments_result = $conn->query($sql);
if (!$payments_result) {
    die("Query failed: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TL Expert Payment Management</title>
    <!-- Font Awesome for icons -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap CSS -->
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

        .edit-form {
            display: none;
            margin-top: 10px;
            padding: 10px;
            border: 1px solid #ccc;
            background-color: #f9f9f9;
        }
        .dropdown-container {
            position: relative;
        }
        .dropdown-list {
            position: absolute;
            z-index: 1000;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ccc;
            background-color: #fff;
            display: none;
        }
        .dropdown-list li {
            padding: 8px;
            cursor: pointer;
        }
        .dropdown-list li:hover {
            background-color: #f1f1f1;
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
       
        <h2>Add New Payment to Expert</h2>
        <form method="POST" class="mb-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="expert_id" class="form-label">Expert:</label>
                    <div class="dropdown-container">
                        <input type="text" id="expert-search" class="form-control" placeholder="Search expert...">
                        <ul class="dropdown-list" id="expert-list">
                            <?php
                            $experts_result->data_seek(0); // Reset pointer to beginning
                            while ($row = $experts_result->fetch_assoc()) { ?>
                                <li data-value="<?php echo $row['expert_id']; ?>"><?php echo $row['expert_name']; ?></li>
                            <?php } ?>
                        </ul>
                        <input type="hidden" name="expert_id" id="expert-id">
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="team_lead_id" class="form-label">Team Lead:</label>
                    <div class="dropdown-container">
                        <input type="text" id="tl-search" class="form-control" placeholder="Search team lead...">
                        <ul class="dropdown-list" id="tl-list">
                            <?php
                            $tls_result->data_seek(0); // Reset pointer to beginning
                            while ($row = $tls_result->fetch_assoc()) { ?>
                                <li data-value="<?php echo $row['team_lead_id']; ?>"><?php echo $row['name']; ?></li>
                            <?php } ?>
                        </ul>
                        <input type="hidden" name="team_lead_id" id="tl-id">
                    </div>
                </div>
                <div class="col-md-2">
                    <label for="payment_date" class="form-label">Payment Date:</label>
                    <input type="date" name="payment_date" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label for="amount_paid" class="form-label">Amount Paid:</label>
                    <input type="number" name="amount_paid" class="form-control" required>
                </div>
                <div class="col-md-2">
                    <label for="description" class="form-label">Description:</label>
                    <input type="text" name="description" class="form-control">
                </div>
                <div class="col-md-2">
                    <button type="submit" name="add_payment" class="btn btn-primary w-100 mt-4">Add Payment</button>
                </div>
            </div>
        </form>

        <!-- Filter by Payment Month -->
        <h2>Filter by Payment Month</h2>
        <form method="GET" class="mb-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="filter_month" class="form-label">Select Month:</label>
                    <input type="month" name="filter_month" class="form-control" value="<?php echo isset($_GET['filter_month']) ? $_GET['filter_month'] : ''; ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100 mt-4">Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="tl_expert_payment.php" class="btn btn-secondary w-100 mt-4">Clear Filter</a>
                </div>
            </div>
        </form>

        <!-- Payments List -->
        <h2>Payments to Experts</h2>
        <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>SL No</th>
                    <th>Transaction ID</th>
                    <th>Payment Date</th>
                    <th>Amount Paid</th>
                    <th>Expert Name</th>
                    <th>Expert ID</th>
                    <th>Team Lead Name</th>
                    <th>Team Lead ID</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $serial_no = 1; // Initialize serial number counter
                while ($row = $payments_result->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo $serial_no++; ?></td>
                        <td><?php echo $row['transaction_id']; ?></td>
                        <td><?php echo date('d/m/Y', strtotime($row['payment_date'])); ?></td>
                        <td><?php echo $row['amount_paid']; ?></td>
                        <td><?php echo $row['expert_name']; ?></td>
                        <td><?php echo $row['expert_id']; ?></td>
                        <td><?php echo $row['team_lead_name']; ?></td>
                        <td><?php echo $row['team_lead_id']; ?></td>
                        <td><?php echo $row['description']; ?></td>
                        <td>
                            <!-- Edit Button -->
                            <button onclick="toggleEditForm(<?php echo $row['transaction_id']; ?>)" class="btn btn-warning btn-sm">Edit</button>
                            <!-- Delete Form -->
                            <form method="POST" style="display:inline-block;">
                                <input type="hidden" name="transaction_id" value="<?php echo $row['transaction_id']; ?>">
                                <button type="submit" name="delete_payment" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?');">Delete</button>
                            </form>
                            <!-- Edit Form -->
                            <div id="edit-form-<?php echo $row['transaction_id']; ?>" class="edit-form mt-3">
                                <form method="POST">
                                    <input type="hidden" name="transaction_id" value="<?php echo $row['transaction_id']; ?>">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label>Payment Date:</label>
                                            <input type="date" name="payment_date" class="form-control" value="<?php echo $row['payment_date']; ?>" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label>Amount Paid:</label>
                                            <input type="number" name="amount_paid" class="form-control" value="<?php echo $row['amount_paid']; ?>" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label>Expert:</label>
                                            <div class="dropdown-container">
                                                <input type="text" id="expert-search-edit-<?php echo $row['transaction_id']; ?>" class="form-control" placeholder="Search expert..." value="<?php echo $row['expert_name']; ?>">
                                                <ul class="dropdown-list" id="expert-list-edit-<?php echo $row['transaction_id']; ?>">
                                                    <?php
                                                    $experts_result->data_seek(0); // Reset pointer to the beginning
                                                    while ($expert = $experts_result->fetch_assoc()) {
                                                        echo "<li data-value='{$expert['expert_id']}'>{$expert['expert_name']}</li>";
                                                    }
                                                    ?>
                                                </ul>
                                                <input type="hidden" name="expert_id" id="expert-id-edit-<?php echo $row['transaction_id']; ?>" value="<?php echo $row['expert_id']; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label>Team Lead:</label>
                                            <div class="dropdown-container">
                                                <input type="text" id="tl-search-edit-<?php echo $row['transaction_id']; ?>" class="form-control" placeholder="Search team lead..." value="<?php echo $row['team_lead_name']; ?>">
                                                <ul class="dropdown-list" id="tl-list-edit-<?php echo $row['transaction_id']; ?>">
                                                    <?php
                                                    $tls_result->data_seek(0); // Reset pointer to the beginning
                                                    while ($tl = $tls_result->fetch_assoc()) {
                                                        echo "<li data-value='{$tl['team_lead_id']}'>{$tl['name']}</li>";
                                                    }
                                                    ?>
                                                </ul>
                                                <input type="hidden" name="team_lead_id" id="tl-id-edit-<?php echo $row['transaction_id']; ?>" value="<?php echo $row['team_lead_id']; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label>Description:</label>
                                            <input type="text" name="description" class="form-control" value="<?php echo $row['description']; ?>">
                                        </div>
                                        <div class="col-md-1">
                                            <button type="submit" name="edit_payment" class="btn btn-success w-100 mt-4">Save</button>
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
        <br>
    </div>

    <!-- JavaScript for dropdown functionality and edit form toggle -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function toggleEditForm(transactionId) {
            const editForm = document.getElementById(`edit-form-${transactionId}`);
            if (editForm.style.display === 'none' || editForm.style.display === '') {
                editForm.style.display = 'block';
            } else {
                editForm.style.display = 'none';
            }
        }

        // Search functionality for dropdowns
        document.addEventListener('DOMContentLoaded', function() {
            const dropdowns = document.querySelectorAll('.dropdown-container');
            dropdowns.forEach(dropdown => {
                const input = dropdown.querySelector('input[type="text"]');
                const list = dropdown.querySelector('.dropdown-list');
                const hiddenInput = dropdown.querySelector('input[type="hidden"]');

                // Show dropdown list when input is focused
                input.addEventListener('focus', function() {
                    list.style.display = 'block';
                });

                input.addEventListener('input', function() {
                    const searchTerm = input.value.toLowerCase();
                    const items = list.querySelectorAll('li');
                    items.forEach(item => {
                        const text = item.textContent.toLowerCase();
                        if (text.includes(searchTerm)) {
                            item.style.display = '';
                        } else {
                            item.style.display = 'none';
                        }
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
                    if (!dropdown.contains(e.target)) {
                        list.style.display = 'none';
                    }
                });
            });
        });
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>

<?php $conn->close(); ?>