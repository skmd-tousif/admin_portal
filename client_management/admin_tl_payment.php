<?php
// Step 1: Start the session
session_start();

// Step 2: Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    // If the admin is not logged in, redirect to the login page
    header("Location: ../index.php"); // Adjust the path if needed
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

// Function to calculate dues for a Team Lead (TL)
function calculateTLDues($tl_id, $conn) {
    // Get initial dues from teamlead table
    $sql = "SELECT initial_due FROM teamlead WHERE team_lead_id = $tl_id";
    $result = $conn->query($sql);
    if (!$result) {
        die("Query failed: " . $conn->error);
    }
    $row = $result->fetch_assoc();
    $initial_due = $row['initial_due'];

    // Get total earnings (tl_price) from task table
    $sql = "SELECT COALESCE(SUM(tl_price), 0) AS total_earnings FROM task WHERE team_lead_id = $tl_id";
    $result = $conn->query($sql);
    if (!$result) {
        die("Query failed: " . $conn->error);
    }
    $row = $result->fetch_assoc();
    $total_earnings = $row['total_earnings'];

    // Get total payments made to the TL from admintlpayment table
    $sql = "SELECT COALESCE(SUM(amount_paid), 0) AS total_paid FROM admintlpayment WHERE tl_id = $tl_id";
    $result = $conn->query($sql);
    if (!$result) {
        die("Query failed: " . $conn->error);
    }
    $row = $result->fetch_assoc();
    $total_paid = $row['total_paid'];

    // Calculate current dues
    $dues = ($initial_due + $total_earnings) - $total_paid;

    // Update dues in teamlead table
    $sql = "UPDATE teamlead SET dues = $dues WHERE team_lead_id = $tl_id";
    if (!$conn->query($sql)) {
        die("Update failed: " . $conn->error);
    }
}

// Handle form actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_payment'])) {
        $tl_id = $_POST['tl_id'];
        $payment_date = $_POST['payment_date'];
        $amount_paid = $_POST['amount_paid'];
        $description = $_POST['description']; // New field for description

        // Fetch Team Lead's name from teamlead table
        $sql = "SELECT name FROM teamlead WHERE team_lead_id = $tl_id";
        $result = $conn->query($sql);
        if (!$result) {
            die("Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $tl_name = $row['name'];

        // Insert payment into admintlpayment table, including description
        $sql = "INSERT INTO admintlpayment (payment_date, amount_paid, tl_id, tl_name, description) 
                VALUES ('$payment_date', '$amount_paid', '$tl_id', '$tl_name', '$description')";
        if ($conn->query($sql) === TRUE) {
            // Recalculate dues for the TL
            calculateTLDues($tl_id, $conn);

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
        $description = $_POST['description']; // New field for description
        $tl_id = $_POST['tl_id'];

        // Fetch Team Lead's name from teamlead table
        $sql = "SELECT name FROM teamlead WHERE team_lead_id = $tl_id";
        $result = $conn->query($sql);
        if (!$result) {
            die("Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $tl_name = $row['name'];

        // Update payment in admintlpayment table, including description
        $sql = "UPDATE admintlpayment 
                SET payment_date = '$payment_date', amount_paid = '$amount_paid', tl_id = '$tl_id', tl_name = '$tl_name', description = '$description' 
                WHERE transaction_id = $transaction_id";
        if ($conn->query($sql) === TRUE) {
            // Recalculate dues for the TL
            calculateTLDues($tl_id, $conn);

            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }

    if (isset($_POST['delete_payment'])) {
        $transaction_id = $_POST['transaction_id'];

        // Get tl_id before deleting the payment
        $sql = "SELECT tl_id FROM admintlpayment WHERE transaction_id = $transaction_id";
        $result = $conn->query($sql);
        if (!$result) {
            die("Query failed: " . $conn->error);
        }
        $row = $result->fetch_assoc();
        $tl_id = $row['tl_id'];

        // Delete payment from admintlpayment table
        $sql = "DELETE FROM admintlpayment WHERE transaction_id = $transaction_id";
        if ($conn->query($sql) === TRUE) {
            // Recalculate dues for the TL
            calculateTLDues($tl_id, $conn);

            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }
}

// Fetch all Team Leads (TLs) for the dropdown
$sql = "SELECT team_lead_id, name FROM teamlead";
$tls_result = $conn->query($sql);
if (!$tls_result) {
    die("Query failed: " . $conn->error);
}

// Handle filtering by payment month
$filter_month = isset($_GET['filter_month']) ? $_GET['filter_month'] : '';

// Fetch all payments to TLs
$sql = "SELECT * FROM admintlpayment";

// Add filtering by payment month if selected
if (!empty($filter_month)) {
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
    <title>Admin TL Payment Management</title>
    <!-- Font Awesome for icons -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
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
        
        <h2>Add New Payment to Team Lead</h2>
        <form method="POST" class="mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="tl_id" class="form-label">Team Lead:</label>
                    <div class="dropdown-container">
                        <input type="text" id="tl-search" class="form-control" placeholder="Search team lead...">
                        <ul class="dropdown-list" id="tl-list">
                            <?php while ($row = $tls_result->fetch_assoc()) { ?>
                                <li data-value="<?php echo $row['team_lead_id']; ?>"><?php echo $row['name']; ?></li>
                            <?php } ?>
                        </ul>
                        <input type="hidden" name="tl_id" id="tl-id">
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="payment_date" class="form-label">Payment Date:</label>
                    <input type="date" name="payment_date" class="form-control" required>
                </div>
                <div class="col-md-3">
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
                <div class="col-md-4">
                    <label for="filter_month" class="form-label">Select Month:</label>
                    <input type="month" name="filter_month" id="filter_month" class="form-control" value="<?php echo $filter_month; ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100 mt-4">Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="admin_tl_payment.php" class="btn btn-warning w-100 mt-4">Clear Filter</a>
                </div>
            </div>
        </form>

        <!-- Payments List -->
        <h2>Payments to Team Leads</h2>
        <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>SL No</th>
                    <th>Transaction ID</th>
                    <th>Payment Date</th>
                    <th>Amount Paid</th>
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
                        <td><?php echo $row['tl_name']; ?></td>
                        <td><?php echo $row['tl_id']; ?></td>
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
                                            <label>Team Lead:</label>
                                            <div class="dropdown-container">
                                                <input type="text" id="tl-search-edit-<?php echo $row['transaction_id']; ?>" class="form-control" placeholder="Search team lead..." value="<?php echo $row['tl_name']; ?>">
                                                <ul class="dropdown-list" id="tl-list-edit-<?php echo $row['transaction_id']; ?>">
                                                    <?php
                                                    $tls_result->data_seek(0); // Reset pointer to the beginning
                                                    while ($tl = $tls_result->fetch_assoc()) {
                                                        echo "<li data-value='{$tl['team_lead_id']}'>{$tl['name']}</li>";
                                                    }
                                                    ?>
                                                </ul>
                                                <input type="hidden" name="tl_id" id="tl-id-edit-<?php echo $row['transaction_id']; ?>" value="<?php echo $row['tl_id']; ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
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

    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Initialize Select2 for all select elements
        $(document).ready(function() {
            $('.select2').select2();
        });

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

        function toggleEditForm(transactionId) {
            const editForm = document.getElementById(`edit-form-${transactionId}`);
            if (editForm.style.display === 'none' || editForm.style.display === '') {
                editForm.style.display = 'block';
            } else {
                editForm.style.display = 'none';
            }
        }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>

<?php $conn->close(); ?>