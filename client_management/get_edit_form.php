<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Enable output compression
ob_start('ob_gzhandler');

// Start the session
session_start();

// Function to send JSON error response
function sendErrorResponse($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit();
}

// Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    error_log("Admin not logged in - session admin_id not set");
    sendErrorResponse('Not authorized', 403);
}

// Validate task_id
if (!isset($_GET['task_id']) || !is_numeric($_GET['task_id'])) {
    error_log("Invalid task_id provided: " . ($_GET['task_id'] ?? 'not set'));
    sendErrorResponse('Invalid task ID', 400);
}

$task_id = intval($_GET['task_id']);

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "mydb";

try {
    $conn = new mysqli($servername, $username, $password, $database);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    sendErrorResponse('Database connection failed', 500);
}

// Cache dropdown data in session for 1 hour
if (!isset($_SESSION['cached_dropdowns']) || !isset($_SESSION['cache_expiry']) || $_SESSION['cache_expiry'] < time()) {
    try {
        // Fetch team leads
        $team_leads_result = $conn->query("SELECT team_lead_id, name FROM teamlead");
        if (!$team_leads_result) {
            throw new Exception("Failed to fetch team leads: " . $conn->error);
        }
        $team_leads_data = $team_leads_result->fetch_all(MYSQLI_ASSOC);
        
        // Fetch experts
        $experts_result = $conn->query("SELECT expert_id, expert_name FROM expert");
        if (!$experts_result) {
            throw new Exception("Failed to fetch experts: " . $conn->error);
        }
        $experts_data = $experts_result->fetch_all(MYSQLI_ASSOC);
        
        // Fetch clients
        $clients_result = $conn->query("SELECT client_id, client_name FROM client");
        if (!$clients_result) {
            throw new Exception("Failed to fetch clients: " . $conn->error);
        }
        $clients_data = $clients_result->fetch_all(MYSQLI_ASSOC);
        
        $_SESSION['cached_dropdowns'] = [
            'team_leads' => $team_leads_data,
            'experts' => $experts_data,
            'clients' => $clients_data
        ];
        $_SESSION['cache_expiry'] = time() + 3600;
    } catch (Exception $e) {
        error_log("Error fetching dropdown data: " . $e->getMessage());
        sendErrorResponse('Error loading form data', 500);
    }
}

$team_leads_data = $_SESSION['cached_dropdowns']['team_leads'];
$experts_data = $_SESSION['cached_dropdowns']['experts'];
$clients_data = $_SESSION['cached_dropdowns']['clients'];

// Fetch task data
try {
    $stmt = $conn->prepare("SELECT * FROM task WHERE task_id = ?");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("i", $task_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result || $result->num_rows === 0) {
        error_log("Task not found for task_id: " . $task_id);
        sendErrorResponse('Task not found', 404);
    }
    
    $row = $result->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching task data: " . $e->getMessage());
    sendErrorResponse('Error fetching task data', 500);
}

// Helper for checkbox
function checked($haystack, $needle) {
    return (strpos($haystack, $needle) !== false) ? 'checked' : '';
}

// Set content type to HTML
header('Content-Type: text/html; charset=UTF-8');
?>

<!-- Include Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<form method="post">
    <input type="hidden" name="task_id" value="<?php echo htmlspecialchars($row['task_id']); ?>">
    <input type="hidden" name="filter_column" value="<?php echo htmlspecialchars($_GET['filter_column'] ?? ''); ?>">
    <input type="hidden" name="filter_value" value="<?php echo htmlspecialchars($_GET['filter_value'] ?? ''); ?>">
    <input type="hidden" name="filter_month" value="<?php echo htmlspecialchars($_GET['filter_month'] ?? ''); ?>">
    <div class="row g-3">
        <!-- Client -->
        <div class="col-md-6">
            <label>Client:</label>
            <select name="client_id" class="form-control form-control-lg select2" required>
                <option value="">Select Client</option>
                <?php foreach ($clients_data as $client): ?>
                <option value="<?php echo htmlspecialchars($client['client_id']); ?>" 
                    <?php echo ($client['client_id'] == $row['client_id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($client['client_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Task Name -->
        <div class="col-md-6">
            <label>Task Name:</label>
            <input type="text" name="task_name" class="form-control form-control-lg"
                   value="<?php echo htmlspecialchars($row['task_name']); ?>" required>
        </div>
        
        <!-- Description -->
        <div class="col-md-6">
            <label>Description:</label>
            <textarea name="description" class="form-control form-control-lg" rows="3"><?php echo htmlspecialchars($row['description'] ?? ''); ?></textarea>
        </div>
        
        <!-- Team Lead -->
        <div class="col-md-6">
            <label>Team Lead:</label>
            <select name="team_lead_id" class="form-control form-control-lg select2" required>
                <option value="">Select Team Lead</option>
                <?php foreach ($team_leads_data as $lead): ?>
                <option value="<?php echo htmlspecialchars($lead['team_lead_id']); ?>" 
                    <?php echo ($lead['team_lead_id'] == $row['team_lead_id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($lead['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Experts -->
        <?php for ($i = 1; $i <= 3; $i++): ?>
        <div class="col-md-6">
            <label>Expert <?php echo $i; ?> (Optional):</label>
            <select name="expert_id_<?php echo $i; ?>" class="form-control form-control-lg select2">
                <option value="">None</option>
                <?php foreach ($experts_data as $expert): ?>
                <option value="<?php echo htmlspecialchars($expert['expert_id']); ?>" 
                    <?php echo (isset($row["expert_id_$i"]) && $expert['expert_id'] == $row["expert_id_$i"]) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($expert['expert_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endfor; ?>
        
        <!-- Price -->
        <div class="col-md-6">
            <label>Price:</label>
            <input type="number" name="price" class="form-control form-control-lg" step="0.01"
                   value="<?php echo htmlspecialchars($row['price'] ?? ''); ?>" required>
        </div>
        
        <!-- Team Lead Price -->
        <div class="col-md-6">
            <label>Team Lead Price:</label>
            <input type="number" name="tl_price" class="form-control form-control-lg" step="0.01"
                   value="<?php echo htmlspecialchars($row['tl_price'] ?? ''); ?>" required>
        </div>
        
        <!-- Expert Prices -->
        <?php for ($i = 1; $i <= 3; $i++): ?>
        <div class="col-md-6">
            <label>Expert <?php echo $i; ?> Price (Optional):</label>
            <input type="number" name="expert_price<?php echo $i; ?>" class="form-control form-control-lg" step="0.01"
                   value="<?php echo htmlspecialchars($row["expert_price$i"] ?? ''); ?>">
        </div>
        <?php endfor; ?>
        
        <!-- Task Date -->
        <div class="col-md-6">
            <label>Task Date:</label>
            <input type="date" name="task_date" class="form-control form-control-lg"
                   value="<?php echo htmlspecialchars($row['task_date'] ?? ''); ?>" required>
        </div>
        
        <!-- Due Date -->
        <div class="col-md-6">
            <label>Due Date:</label>
            <input type="date" name="due_date" class="form-control form-control-lg"
                   value="<?php echo htmlspecialchars($row['due_date'] ?? ''); ?>" required>
        </div>
        
        <!-- Status -->
        <div class="col-md-6">
            <label>Status:</label>
            <select name="status" class="form-control form-control-lg select2" required>
                <option value="">Select Status</option>
                <?php 
                $statuses = ['in progress', 'submitted', 'passed', 'failed', 'submitted late']; 
                foreach ($statuses as $status): 
                ?>
                <option value="<?php echo htmlspecialchars($status); ?>" 
                    <?php echo (isset($row['status']) && $row['status'] == $status) ? 'selected' : ''; ?>>
                    <?php echo ucfirst($status); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Word Count -->
        <div class="col-md-6">
            <label>Word Count:</label>
            <input type="number" name="word_count" class="form-control form-control-lg"
                   value="<?php echo htmlspecialchars($row['word_count'] ?? ''); ?>">
        </div>
        
        <!-- Issue -->
        <div class="col-md-6">
            <label>Issue:</label>
            <div>
                <?php
                $issues = [
                    "Low marks", "Brief not followed", "Word count lower", "Wordcount higher",
                    "Referencing irrelevant", "AI used", "Plagiarism", "Poor quality", "Money Less Taken"
                ];
                $row_issues = $row['issue'] ?? '';
                foreach ($issues as $issue) {
                    $isChecked = checked($row_issues, $issue);
                    echo "<input type=\"checkbox\" name=\"issue[]\" value=\"" . htmlspecialchars($issue) . "\" $isChecked> " . htmlspecialchars($issue) . "<br>";
                }
                ?>
            </div>
        </div>
        
        <!-- Incomplete Information -->
        <div class="col-md-6">
            <label>Incomplete Information:</label>
            <div class="form-check">
                <input type="checkbox" name="incomplete_information" class="form-check-input" id="incomplete_information_edit"
                    <?php echo (isset($row['incomplete_information']) && $row['incomplete_information']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="incomplete_information_edit">Incomplete Information</label>
            </div>
        </div>
        
        <!-- Save Button -->
        <div class="col-md-12">
            <button type="submit" name="edit" class="btn btn-success btn-lg w-100">Save</button>
        </div>
    </div>
</form>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2').select2({
        width: '100%',
        placeholder: 'Select an option'
    });
});
</script>

<?php
$conn->close();
ob_end_flush();
?>