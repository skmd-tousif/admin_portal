<?php
// Step 1: Start the session
session_start();

// Step 2: Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    // If the admin is not logged in, redirect to the login page
    header("Location: ../task.php"); // Adjust the path if needed
    exit(); // Stop further execution of the script
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mydb";

// Connect to database
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Helper function to update team lead dues
function updateTeamLeadDues($conn, $team_lead_id = null) {
    // If a specific team lead ID is provided, update only that record
    // Otherwise, update all team leads
    $where_clause = $team_lead_id ? "WHERE tl.team_lead_id = $team_lead_id" : "";
    
    $update_sql = "
        UPDATE teamlead tl
        LEFT JOIN (
            SELECT 
                team_lead_id,
                COALESCE(SUM(tl_price), 0) AS total_tasks
            FROM task
            GROUP BY team_lead_id
        ) t ON tl.team_lead_id = t.team_lead_id
        LEFT JOIN (
            SELECT 
                tl_id,
                COALESCE(SUM(amount_paid), 0) AS total_payments
            FROM admintlpayment
            GROUP BY tl_id
        ) atp ON tl.team_lead_id = atp.tl_id
        SET tl.dues = COALESCE(tl.initial_due, 0) + 
                     COALESCE(t.total_tasks, 0) - 
                     COALESCE(atp.total_payments, 0)
        $where_clause
    ";
    
    if ($conn->query($update_sql) !== TRUE) {
        echo "Error updating dues: " . $conn->error;
    }
}

// First, add the dues column if it doesn't exist
$check_column_sql = "
    SELECT COLUMN_NAME 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = '$dbname' 
    AND TABLE_NAME = 'teamlead' 
    AND COLUMN_NAME = 'dues'
";

$check_result = $conn->query($check_column_sql);
if ($check_result->num_rows == 0) {
    // Column doesn't exist, add it
    $add_column_sql = "ALTER TABLE teamlead ADD COLUMN dues DECIMAL(10,2) DEFAULT 0";
    if ($conn->query($add_column_sql) !== TRUE) {
        echo "Error adding dues column: " . $conn->error;
    } else {
        // Initialize dues for all team leads
        updateTeamLeadDues($conn);
    }
}

// Handle form actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add'])) {
        $name = $_POST['name'];
        $mobile_no = $_POST['mobile_no'];
        $initial_due = $_POST['initial_due'];

        // Insert new team lead with initial due
        $sql = "INSERT INTO teamlead (name, mobile_no, initial_due, dues) 
                VALUES ('$name', '$mobile_no', '$initial_due', '$initial_due')";
        if ($conn->query($sql) === TRUE) {
            // Get the ID of the newly inserted team lead
            $team_lead_id = $conn->insert_id;
            // Update dues for the new team lead
            updateTeamLeadDues($conn, $team_lead_id);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }

    if (isset($_POST['edit'])) {
        $team_lead_id = $_POST['team_lead_id'];
        $name = $_POST['name'];
        $mobile_no = $_POST['mobile_no'];
        $initial_due = $_POST['initial_due'];

        // Update team lead
        $sql = "UPDATE teamlead SET name='$name', mobile_no='$mobile_no', initial_due='$initial_due' 
                WHERE team_lead_id='$team_lead_id'";
        if ($conn->query($sql) === TRUE) {
            // Update dues for this team lead
            updateTeamLeadDues($conn, $team_lead_id);
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }

    if (isset($_POST['delete'])) {
        $team_lead_id = $_POST['team_lead_id'];
        $sql = "DELETE FROM teamlead WHERE team_lead_id='$team_lead_id'";
        if ($conn->query($sql) === TRUE) {
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }
}

// Update all team lead dues to ensure they're current
updateTeamLeadDues($conn);

// Handle sorting
$sort_order = isset($_GET['sort']) ? $_GET['sort'] : 'default'; // Default is no sorting

// Base SQL query - now using the stored dues value
$sql = "SELECT team_lead_id, name, mobile_no, initial_due, dues FROM teamlead";

// Add sorting to the query
if ($sort_order === 'asc') {
    $sql .= " ORDER BY dues ASC";
} elseif ($sort_order === 'desc') {
    $sql .= " ORDER BY dues DESC";
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
    <title>TeamLead Management</title>
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
        
        <h2>Add New Team Lead</h2>
        <form method="POST" class="mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="name" class="form-control" placeholder="Team Lead Name" required>
                </div>
                <div class="col-md-3">
                    <input type="number" name="mobile_no" class="form-control" placeholder="Mobile Number" required>
                </div>
                <div class="col-md-3">
                    <input type="number" name="initial_due" class="form-control" placeholder="Initial Due" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" name="add" class="btn btn-primary w-100">Add Team Lead</button>
                </div>
            </div>
        </form>

        <!-- Sorting Buttons -->
        <h2>Sort Team Leads by Total Dues</h2>
        <form method="GET" class="mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <button type="submit" name="sort" value="asc" class="btn btn-secondary w-100">Sort by Dues (Ascending)</button>
                </div>
                <div class="col-md-4">
                    <button type="submit" name="sort" value="desc" class="btn btn-secondary w-100">Sort by Dues (Descending)</button>
                </div>
                <div class="col-md-4">
                    <button type="submit" name="sort" value="default" class="btn btn-secondary w-100">No Sorting (Default Order)</button>
                </div>
            </div>
        </form>

        <h2>Team Leads List</h2>
        <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Sl No</th>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Mobile</th>
                    <th>Initial Due</th>
                    <th>Total Due</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $counter = 1;
                while ($row = $result->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo $counter++; ?></td>
                        <td><?php echo $row['team_lead_id']; ?></td>
                        <td><?php echo $row['name']; ?></td>
                        <td><?php echo $row['mobile_no']; ?></td>
                        <td><?php echo $row['initial_due']; ?></td>
                        <td><?php echo $row['dues']; ?></td>
                        <td>
                            <!-- Edit Button -->
                            <button onclick="toggleEditForm(<?php echo $row['team_lead_id']; ?>)" class="btn btn-warning btn-sm">Edit</button>
                            <!-- Delete Form -->
                            <form method="POST" style="display:inline-block;">
                                <input type="hidden" name="team_lead_id" value="<?php echo $row['team_lead_id']; ?>">
                                <button type="submit" name="delete" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?');">Delete</button>
                            </form>
                            <!-- Edit Form -->
                            <div id="edit-form-<?php echo $row['team_lead_id']; ?>" class="edit-form mt-3">
                                <form method="POST">
                                    <input type="hidden" name="team_lead_id" value="<?php echo $row['team_lead_id']; ?>">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label>Team Lead Name</label>
                                            <input type="text" name="name" class="form-control" value="<?php echo $row['name']; ?>" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label>Mobile Number</label>
                                            <input type="number" name="mobile_no" class="form-control" value="<?php echo $row['mobile_no']; ?>" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label>Initial Due</label>
                                            <input type="number" name="initial_due" class="form-control" value="<?php echo $row['initial_due']; ?>" required>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" name="edit" class="btn btn-success w-100">Save</button>
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

    <script>
        function toggleEditForm(teamLeadId) {
            const editForm = document.getElementById(`edit-form-${teamLeadId}`);
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