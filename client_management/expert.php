<?php
// Step 1: Start the session
session_start();

// Step 2: Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    // If the admin is not logged in, redirect to the login page
    header("Location: ../index.php"); // Adjust the path if needed
    exit(); // Stop further execution of the script
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

// Function to calculate dues for ALL experts
function calculateAllExpertsDues($conn) {
    $sql = "
        UPDATE expert e
        JOIN (
            SELECT 
                e.expert_id,
                COALESCE(e.initial_dues, 0) 
                + COALESCE(SUM(CASE WHEN ta.expert_id_1 = e.expert_id THEN ta.expert_price1 ELSE 0 END), 0) 
                + COALESCE(SUM(CASE WHEN ta.expert_id_2 = e.expert_id THEN ta.expert_price2 ELSE 0 END), 0) 
                + COALESCE(SUM(CASE WHEN ta.expert_id_3 = e.expert_id THEN ta.expert_price3 ELSE 0 END), 0) 
                - COALESCE(payments.total_paid, 0) AS total_dues
            FROM expert e
            LEFT JOIN task ta ON e.expert_id IN (ta.expert_id_1, ta.expert_id_2, ta.expert_id_3)
            LEFT JOIN (
                SELECT expert_id, SUM(amount_paid) AS total_paid 
                FROM tlexpertpayment 
                GROUP BY expert_id
            ) payments ON e.expert_id = payments.expert_id
            GROUP BY e.expert_id
        ) AS calc ON e.expert_id = calc.expert_id
        SET e.dues = calc.total_dues
    ";

    if (!$conn->query($sql)) {
        die("Error updating dues for all experts: " . $conn->error);
    }
}

// Call dues calculation for ALL experts when the page loads
calculateAllExpertsDues($conn);

// Handle filtering
$filter_column = isset($_POST['filter_column']) ? $_POST['filter_column'] : '';
$filter_value = isset($_POST['filter_value']) ? $_POST['filter_value'] : '';

// Handle sorting
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'default'; // Default is no sorting
$next_order = $sort_order === 'asc' ? 'desc' : 'asc';

// Handle form actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add'])) {
        $expert_name = $_POST['expert_name'];
        $mobile_no = $_POST['mobile_no'];
        $initial_dues = $_POST['initial_dues'];
        $specialization = $_POST['specialization'] ?? null;

        // Insert new expert with initial dues and specialization
        $sql = "INSERT INTO expert (expert_name, mobile_no, initial_dues, specialization) 
                VALUES ('$expert_name', '$mobile_no', '$initial_dues', '$specialization')";
        if ($conn->query($sql) === TRUE) {
            // Recalculate dues for ALL experts after adding a new expert
            calculateAllExpertsDues($conn);

            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }

    if (isset($_POST['edit'])) {
        $expert_id = $_POST['expert_id'];
        $expert_name = $_POST['expert_name'];
        $mobile_no = $_POST['mobile_no'];
        $initial_dues = $_POST['initial_dues'];
        $specialization = $_POST['specialization'] ?? null;

        // Update expert
        $sql = "UPDATE expert 
                SET expert_name='$expert_name', mobile_no='$mobile_no', initial_dues='$initial_dues', specialization='$specialization' 
                WHERE expert_id='$expert_id'";
        if ($conn->query($sql) === TRUE) {
            // Recalculate dues for ALL experts after editing
            calculateAllExpertsDues($conn);

            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }

    if (isset($_POST['delete'])) {
        $expert_id = $_POST['expert_id'];
        $sql = "DELETE FROM expert WHERE expert_id='$expert_id'";
        if ($conn->query($sql) === TRUE) {
            // Recalculate dues for ALL experts after deletion
            calculateAllExpertsDues($conn);
            
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }
}

// Base SQL query now uses the calculated 'dues' column
$sql = "
    SELECT 
        e.expert_id, 
        e.expert_name, 
        e.mobile_no, 
        e.initial_dues,
        e.specialization,
        e.dues AS total_dues
    FROM expert e
";

// Add filtering to the query
if (!empty($filter_column) && !empty($filter_value)) {
    $sql .= " WHERE $filter_column LIKE '%$filter_value%'";
}

// Add sorting to the query
if ($sort_order === 'asc') {
    $sql .= " ORDER BY e.dues ASC";
} elseif ($sort_order === 'desc') {
    $sql .= " ORDER BY e.dues DESC";
}

// Execute query
$result = $conn->query($sql);

// Debugging - check for SQL errors
if (!$result) {
    die("SQL Error: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expert Management</title>
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
    </style>
    <script>
        function toggleFilterInput() {
            const filterColumn = document.getElementById("filter_column").value;
            const filterValueContainer = document.getElementById("filter_value_container");

            // Show text input for all columns
            filterValueContainer.innerHTML = `
                <input type="text" name="filter_value" class="form-control" placeholder="Enter search value" required>
            `;
        }

        function toggleEditForm(expertId) {
            const editForm = document.getElementById(`edit-form-${expertId}`);
            if (editForm.style.display === 'none' || editForm.style.display === '') {
                editForm.style.display = 'block';
            } else {
                editForm.style.display = 'none';
            }
        }
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
        
        <h2>Add New Expert</h2>
        <form method="POST" class="mb-4">
            <div class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="expert_name" class="form-control" placeholder="Expert Name" required>
                </div>
                <div class="col-md-2">
                    <input type="number" name="mobile_no" class="form-control" placeholder="Mobile Number" required>
                </div>
                <div class="col-md-2">
                    <input type="number" name="initial_dues" class="form-control" placeholder="Initial Dues" required>
                </div>
                <div class="col-md-2">
                    <input type="text" name="specialization" class="form-control" placeholder="Specialization">
                </div>
                <div class="col-md-2">
                    <button type="submit" name="add" class="btn btn-primary w-100">Add Expert</button>
                </div>
            </div>
        </form>

        <!-- Filter Form -->
        <h2>Filter Experts</h2>
        <form method="POST" class="mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="filter_column" class="form-label">Filter by:</label>
                    <select name="filter_column" id="filter_column" class="form-control" required onchange="toggleFilterInput()">
                        <option value="expert_name">Expert Name</option>
                        <option value="mobile_no">Mobile Number</option>
                        <option value="specialization">Specialization</option>
                    </select>
                </div>
                <div class="col-md-4" id="filter_value_container">
                    <input type="text" name="filter_value" class="form-control" placeholder="Enter search value" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100">Filter</button>
                </div>
            </div>
        </form>

        <!-- Sorting Buttons -->
        <h2>Sort Experts by Total Dues</h2>
        <form method="GET" class="mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <button type="submit" name="sort" value="asc" class="btn btn-secondary w-100">Sort Ascending</button>
                </div>
                <div class="col-md-4">
                    <button type="submit" name="sort" value="desc" class="btn btn-secondary w-100">Sort Descending</button>
                </div>
                <div class="col-md-4">
                    <button type="submit" name="sort" value="default" class="btn btn-secondary w-100">No Sorting</button>
                </div>
            </div>
        </form>

        <!-- Experts List -->
        <h2>Experts List</h2>
        <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Sl No</th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Initial Dues</th>
                    <th>Specialization</th>
                    <th>Total Dues</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sl_no = 1;
                while ($row = $result->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo $sl_no++; ?></td>
                        <td><?php echo $row['expert_id']; ?></td>
                        <td><?php echo $row['expert_name']; ?></td>
                        <td><?php echo $row['mobile_no']; ?></td>
                        <td><?php echo $row['initial_dues']; ?></td>
                        <td><?php echo $row['specialization']; ?></td>
                        <td><?php echo $row['total_dues']; ?></td>
                        <td>
                            <!-- Edit Button -->
                            <button onclick="toggleEditForm(<?php echo $row['expert_id']; ?>)" class="btn btn-warning btn-sm">Edit</button>
                            <!-- Delete Form -->
                            <form method="POST" style="display:inline-block;">
                                <input type="hidden" name="expert_id" value="<?php echo $row['expert_id']; ?>">
                                <button type="submit" name="delete" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?');">Delete</button>
                            </form>
                            <!-- Edit Form -->
                            <div id="edit-form-<?php echo $row['expert_id']; ?>" class="edit-form mt-3">
                                <form method="POST">
                                    <input type="hidden" name="expert_id" value="<?php echo $row['expert_id']; ?>">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label>Expert Name:</label>
                                            <input type="text" name="expert_name" class="form-control" value="<?php echo $row['expert_name']; ?>" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label>Mobile Number:</label>
                                            <input type="number" name="mobile_no" class="form-control" value="<?php echo $row['mobile_no']; ?>" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label>Initial Dues:</label>
                                            <input type="number" name="initial_dues" class="form-control" value="<?php echo $row['initial_dues']; ?>" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label>Specialization:</label>
                                            <input type="text" name="specialization" class="form-control" value="<?php echo $row['specialization']; ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" name="edit" class="btn btn-success w-100 mt-4">Save</button>
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
    <?php include 'footer.php'; ?>
</body>
</html>

<?php $conn->close(); ?>