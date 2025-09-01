<?php

// Step 1: Start the session
session_start();

// Step 2: Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    // If the admin is not logged in, redirect to the login page
    header("Location: ../index.php"); // Adjust the path if needed
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

// Handle filtering
$filter_column = isset($_POST['filter_column']) ? $_POST['filter_column'] : '';
$filter_value = isset($_POST['filter_value']) ? $_POST['filter_value'] : '';

// Handle form actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add'])) {
        $college_name = $_POST['college_name'];
        $country = $_POST['country'];

        // Insert new college
        $sql = "INSERT INTO colleges (college_name, country) VALUES ('$college_name', '$country')";
        if ($conn->query($sql)) {
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }

    if (isset($_POST['edit'])) {
        $college_id = $_POST['college_id'];
        $college_name = $_POST['college_name'];
        $country = $_POST['country'];

        // Update college
        $sql = "UPDATE colleges SET college_name='$college_name', country='$country' WHERE college_id='$college_id'";
        if ($conn->query($sql)) {
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }

    if (isset($_POST['delete'])) {
        $college_id = $_POST['college_id'];
        $sql = "DELETE FROM colleges WHERE college_id='$college_id'";
        if ($conn->query($sql)) {
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            echo "Error: " . $sql . "<br>" . $conn->error;
        }
    }

    // Handle clear filter
    if (isset($_POST['clear_filter'])) {
        $filter_column = '';
        $filter_value = '';
    }
}

// Base SQL query for colleges
$sql = "SELECT * FROM colleges";

// Add filtering to the query
if (!empty($filter_column) && !empty($filter_value)) {
    $sql .= " WHERE $filter_column LIKE '%$filter_value%'";
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
    <title>College Management</title>
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

            // Always show a text input for filtering
            filterValueContainer.innerHTML = `
                <input type="text" name="filter_value" class="form-control" placeholder="Enter search value" required>
            `;
        }

        function toggleEditForm(collegeId) {
            const editForm = document.getElementById(`edit-form-${collegeId}`);
            if (editForm.style.display === 'none' || editForm.style.display === '') {
                editForm.style.display = 'block';
            } else {
                editForm.style.display = 'none';
            }
        }

        function clearFilter() {
            // Reset the filter inputs
            document.getElementById("filter_column").value = "college_name";
            document.getElementById("filter_value_container").innerHTML = `
                <input type="text" name="filter_value" class="form-control" placeholder="Enter search value" required>
            `;

            // Submit the form to reload the page without filters
            document.forms[1].submit();
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
    
        <h2>Add New College</h2>
        <form method="POST" class="mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <input type="text" name="college_name" class="form-control" placeholder="College Name" required>
                </div>
                <div class="col-md-4">
                    <input type="text" name="country" class="form-control" placeholder="Country" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" name="add" class="btn btn-primary w-100">Add College</button>
                </div>
            </div>
        </form>

        <!-- Filter Form -->
        <h2>Filter Colleges</h2>
        <form method="POST" class="mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    
                    <select name="filter_column" id="filter_column" class="form-control" required onchange="toggleFilterInput()">
                        <option value="college_name">College Name</option>
                        <option value="country">Country</option>
                    </select>
                </div>
                <div class="col-md-4" id="filter_value_container">
                    <input type="text" name="filter_value" class="form-control" placeholder="Enter search value" required>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100">Filter</button>
                </div>
                <div class="col-md-2">
                    <button type="button" onclick="clearFilter()" class="btn btn-danger w-100">Clear Filter</button>
                </div>
            </div>
        </form>

        <!-- Colleges List -->
        <h2>Colleges List</h2>
        <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Sl No</th>
                    <th>ID</th>
                    <th>College Name</th>
                    <th>Country</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sl_no = 1;
                while ($row = $result->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo $sl_no++; ?></td>
                        <td><?php echo $row['college_id']; ?></td>
                        <td><?php echo $row['college_name']; ?></td>
                        <td><?php echo $row['country']; ?></td>
                        <td>
                            <!-- Edit Button -->
                            <button onclick="toggleEditForm(<?php echo $row['college_id']; ?>)" class="btn btn-warning btn-sm">Edit</button>
                            <!-- Delete Form -->
                            <form method="POST" style="display:inline-block;">
                                <input type="hidden" name="college_id" value="<?php echo $row['college_id']; ?>">
                                <button type="submit" name="delete" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?');">Delete</button>
                            </form>
                            <!-- Edit Form -->
                            <div id="edit-form-<?php echo $row['college_id']; ?>" class="edit-form mt-3">
                                <form method="POST">
                                    <input type="hidden" name="college_id" value="<?php echo $row['college_id']; ?>">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label>College Name:</label>
                                            <input type="text" name="college_name" class="form-control" value="<?php echo $row['college_name']; ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label>Country:</label>
                                            <input type="text" name="country" class="form-control" value="<?php echo $row['country']; ?>" required>
                                        </div>
                                        <div class="col-md-4">
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