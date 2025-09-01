<?php
// Step 1: Start the session
session_start();

// Step 2: Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "mydb";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Define country conversion rates
$country_rates = [
    'canada' => 60,
    'aus' => 50,
    'uk' => 100,
    'europe' => 97,
    'uae' => 27,
    'not' => 1,
    'india' => 1,
    'france' => 96,
    'australia' => 50,
];

// Fetch dropdown data
$clients_result = $conn->query("SELECT client_id, client_name FROM client");
$team_leads_result = $conn->query("SELECT team_lead_id, name FROM teamlead");
$experts_result = $conn->query("SELECT expert_id, expert_name FROM expert");
$months_result = $conn->query("SELECT DISTINCT DATE_FORMAT(task_date, '%Y-%m') AS month FROM task ORDER BY month DESC");

// Handle task addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add'])) {
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
    $team_lead_name = $conn->query("SELECT name FROM teamlead WHERE team_lead_id = $team_lead_id")->fetch_assoc()['name'];

    // Fetch expert names
    $expert_name_1 = $expert_id_1 ? $conn->query("SELECT expert_name FROM expert WHERE expert_id = $expert_id_1")->fetch_assoc()['expert_name'] : NULL;
    $expert_name_2 = $expert_id_2 ? $conn->query("SELECT expert_name FROM expert WHERE expert_id = $expert_id_2")->fetch_assoc()['expert_name'] : NULL;
    $expert_name_3 = $expert_id_3 ? $conn->query("SELECT expert_name FROM expert WHERE expert_id = $expert_id_3")->fetch_assoc()['expert_name'] : NULL;

    // Calculate total cost
    $total_cost = $tl_price + $expert_price1 + $expert_price2 + $expert_price3;

    // Insert task into the database
    $stmt = $conn->prepare("INSERT INTO task (task_name, description, team_lead_id, assigned_team_lead_name, expert_id_1, assigned_expert_1, expert_id_2, assigned_expert_2, expert_id_3, assigned_expert_3, client_id, price, tl_price, expert_price1, expert_price2, expert_price3, task_date, due_date, status, word_count, issue, incomplete_information, total_cost) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssisssssssiiiiiisssisii", $task_name, $description, $team_lead_id, $team_lead_name, $expert_id_1, $expert_name_1, $expert_id_2, $expert_name_2, $expert_id_3, $expert_name_3, $client_id, $price, $tl_price, $expert_price1, $expert_price2, $expert_price3, $task_date, $due_date, $status, $word_count, $issue, $incomplete_information, $total_cost);
    $stmt->execute();

    // Update dues for the client
    $conn->query("UPDATE client SET due_payment = due_payment + $price WHERE client_id = $client_id");

    // Update dues for the team lead (excluding expert prices)
    $conn->query("UPDATE teamlead SET dues = dues + $tl_price WHERE team_lead_id = $team_lead_id");

    // Update dues for experts (only if expert is assigned)
    if ($expert_id_1) {
        $conn->query("UPDATE expert SET dues = dues + $expert_price1 WHERE expert_id = $expert_id_1");
    }
    if ($expert_id_2) {
        $conn->query("UPDATE expert SET dues = dues + $expert_price2 WHERE expert_id = $expert_id_2");
    }
    if ($expert_id_3) {
        $conn->query("UPDATE expert SET dues = dues + $expert_price3 WHERE expert_id = $expert_id_3");
    }

    header("Location: visualization.php");
    exit();
}

// Initialize variables for financial reports
$activities_result = null;
$total_profit_loss = 0;
$total_revenue = 0;
$revenue_details = [];
$total_expenditure = 0;
$expenditure_details = [];
$total_income = 0;
$income_details = [];
$total_dues = 0;
$dues_details = [];
$total_writer_tl_dues = 0;
$writer_tl_dues_details = [];
$client_month_data = [];
$entity_name = '';
$entity_id = '';

// Handle bill generation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['generate_bill'])) {
    $role = $_POST['role'];
    $time_period = isset($_POST['time_period']) ? $_POST['time_period'] : 'monthly';
    
    // Handle month selection based on role
    if ($role === 'client' || $role === 'teamlead' || $role === 'expert') {
        $client_months = isset($_POST['months']) ? $_POST['months'] : [];
        if (empty($client_months)) {
            die("Please select at least one month.");
        }
    } else {
        $month = isset($_POST['month']) ? $_POST['month'] : date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            die("Invalid month format. Use YYYY-MM.");
        }
    }

    if ($role === 'monthly_return') {
        $client_payments_sql = "
            SELECT 
                'Client Payment' AS activity_type,
                c.client_name AS description,
                acp.payment_date AS date,
                acp.payment_in_inr AS amount,
                '' AS task_status
            FROM adminclientpayment acp
            JOIN client c ON acp.client_id = c.client_id
            WHERE DATE_FORMAT(acp.payment_date, '%Y-%m') = '$month'
        ";

        $team_lead_payments_sql = "
            SELECT 
                'Team Lead Payment' AS activity_type,
                tl.name AS description,
                atp.payment_date AS date,
                -atp.amount_paid AS amount,
                '' AS task_status
            FROM admintlpayment atp
            JOIN teamlead tl ON atp.tl_id = tl.team_lead_id
            WHERE DATE_FORMAT(atp.payment_date, '%Y-%m') = '$month'
        ";

        $expert_payments_sql = "
            SELECT 
                'Expert Payment' AS activity_type,
                e.expert_name AS description,
                tep.payment_date AS date,
                -tep.amount_paid AS amount,
                '' AS task_status
            FROM tlexpertpayment tep
            JOIN expert e ON tep.expert_id = e.expert_id
            WHERE DATE_FORMAT(tep.payment_date, '%Y-%m') = '$month'
        ";

        $sql = "
            $client_payments_sql
            UNION ALL
            $team_lead_payments_sql
            UNION ALL
            $expert_payments_sql
            ORDER BY date
        ";

        $activities_result = $conn->query($sql);
        if (!$activities_result) {
            die("Query failed: " . $conn->error);
        }

        $total_profit_loss = 0;
        while ($row = $activities_result->fetch_assoc()) {
            $total_profit_loss += $row['amount'];
        }
        $total_profit_loss = number_format($total_profit_loss, 2, '.', '');
    } 
    elseif ($role === 'revenue') {
        $revenue_sql = "SELECT 
                            t.task_id,
                            t.task_name,
                            cl.client_name,
                            c.country,
                            t.price AS task_price,
                            t.task_date
                        FROM task t
                        JOIN client cl ON t.client_id = cl.client_id
                        LEFT JOIN colleges c ON cl.college_id = c.college_id 
                            OR LOWER(TRIM(cl.college_name)) = LOWER(TRIM(c.college_name))
                        WHERE 1=1";
        
        if ($time_period === 'monthly') {
            $revenue_sql .= " AND DATE_FORMAT(t.task_date, '%Y-%m') = '$month'";
        }
        
        $revenue_result = $conn->query($revenue_sql);
        if (!$revenue_result) {
            die("Query failed: " . $conn->error);
        }
        
        $total_revenue = 0;
        $revenue_details = [];
        while ($row = $revenue_result->fetch_assoc()) {
            $country = isset($row['country']) ? trim($row['country']) : 'not';
            $country_lower = strtolower($country);
            $rate = $country_rates[$country_lower] ?? 1;
            $converted_amount = $row['task_price'] * $rate;
            $total_revenue += $converted_amount;
            
            $revenue_details[] = [
                'task_id' => $row['task_id'],
                'task_name' => $row['task_name'],
                'client_name' => $row['client_name'],
                'country' => $country ? $country : 'Not Specified',
                'task_price' => $row['task_price'],
                'converted_amount' => $converted_amount,
                'rate' => $rate,
                'task_date' => $row['task_date']
            ];
        }
    }
    elseif ($role === 'expenditure') {
        $expenditure_sql = "SELECT 
                                t.name AS name,
                                'Team Lead' AS role,
                                COUNT(atp.transaction_id) AS transaction_count,
                                SUM(atp.amount_paid) AS total_payment
                            FROM admintlpayment atp
                            JOIN teamlead t ON atp.tl_id = t.team_lead_id
                            WHERE 1=1";
        
        if ($time_period === 'monthly') {
            $expenditure_sql .= " AND DATE_FORMAT(atp.payment_date, '%Y-%m') = '$month'";
        }
        
        $expenditure_sql .= " 
                            GROUP BY t.team_lead_id, t.name
                            
                            UNION ALL
                            
                            SELECT 
                                e.expert_name AS name,
                                'Expert' AS role,
                                COUNT(tep.transaction_id) AS transaction_count,
                                SUM(tep.amount_paid) AS total_payment
                            FROM tlexpertpayment tep
                            JOIN expert e ON tep.expert_id = e.expert_id
                            WHERE 1=1";
        
        if ($time_period === 'monthly') {
            $expenditure_sql .= " AND DATE_FORMAT(tep.payment_date, '%Y-%m') = '$month'";
        }
        
        $expenditure_sql .= " 
                            GROUP BY e.expert_id, e.expert_name
                            ORDER BY name";
        
        $expenditure_result = $conn->query($expenditure_sql);
        if (!$expenditure_result) {
            die("Query failed: " . $conn->error);
        }
        
        $total_expenditure = 0;
        $expenditure_details = [];
        while ($row = $expenditure_result->fetch_assoc()) {
            $total_expenditure += $row['total_payment'];
            $expenditure_details[] = $row;
        }
    }
    elseif ($role === 'income') {
        $income_sql = "SELECT 
                            acp.client_id,
                            c.client_name,
                            SUM(acp.payment_in_inr) AS total_payment,
                            COUNT(acp.transaction_id) AS transaction_count
                        FROM adminclientpayment acp
                        JOIN client c ON acp.client_id = c.client_id
                        WHERE 1=1";
        
        if ($time_period === 'monthly') {
            $income_sql .= " AND DATE_FORMAT(acp.payment_date, '%Y-%m') = '$month'";
        }
        
        $income_sql .= " GROUP BY acp.client_id, c.client_name";
        
        $income_result = $conn->query($income_sql);
        if (!$income_result) {
            die("Query failed: " . $conn->error);
        }
        
        $total_income = 0;
        $income_details = [];
        while ($row = $income_result->fetch_assoc()) {
            $total_income += $row['total_payment'];
            $income_details[] = $row;
        }
    }
    elseif ($role === 'client_dues') {
        $dues_sql = "SELECT 
                        c.client_id,
                        c.client_name,
                        col.country,
                        c.due_payment
                    FROM client c
                    LEFT JOIN colleges col ON c.college_id = col.college_id 
                        OR LOWER(TRIM(c.college_name)) = LOWER(TRIM(col.college_name))
                    WHERE c.due_payment > 0";
        
        $dues_result = $conn->query($dues_sql);
        if (!$dues_result) {
            die("Query failed: " . $conn->error);
        }
        
        $total_dues = 0;
        $dues_details = [];
        while ($row = $dues_result->fetch_assoc()) {
            $country = isset($row['country']) ? trim($row['country']) : 'not';
            $country_lower = strtolower($country);
            $rate = $country_rates[$country_lower] ?? 1;
            $converted_amount = $row['due_payment'] * $rate;
            $total_dues += $converted_amount;
            
            $dues_details[] = [
                'client_id' => $row['client_id'],
                'client_name' => $row['client_name'],
                'country' => $country ? $country : 'Not Specified',
                'due_payment' => $row['due_payment'],
                'converted_amount' => $converted_amount,
                'rate' => $rate
            ];
        }
    }
    elseif ($role === 'writer_tl_dues') {
        $writer_tl_dues_sql = "SELECT 
                                    name,
                                    'Team Lead' AS role,
                                    dues AS total_dues
                                FROM teamlead
                                WHERE dues > 0 AND team_lead_id != 33
                                
                                UNION ALL
                                
                                SELECT 
                                    expert_name AS name,
                                    'Expert' AS role,
                                    dues AS total_dues
                                FROM expert
                                WHERE dues > 0
                                ORDER BY name";
        
        $writer_tl_dues_result = $conn->query($writer_tl_dues_sql);
        if (!$writer_tl_dues_result) {
            die("Query failed: " . $conn->error);
        }
        
        $total_writer_tl_dues = 0;
        $writer_tl_dues_details = [];
        while ($row = $writer_tl_dues_result->fetch_assoc()) {
            $total_writer_tl_dues += $row['total_dues'];
            $writer_tl_dues_details[] = $row;
        }
    }
    elseif (in_array($role, ['client', 'teamlead', 'expert'])) {
        $entity_id = isset($_POST['entity_id']) ? $_POST['entity_id'] : null;
        if (!$entity_id) {
            die("Entity ID is required.");
        }

        // Fetch entity details based on role
        $entity_name = '';
        switch ($role) {
            case 'client':
                $sql = "SELECT client_id, client_name FROM client WHERE client_id = $entity_id";
                $entity_result = $conn->query($sql);
                if (!$entity_result) die("Query failed: " . $conn->error);
                $entity_row = $entity_result->fetch_assoc();
                $entity_name = $entity_row['client_name'];
                $entity_id = $entity_row['client_id'];
                break;

            case 'teamlead':
                $sql = "SELECT team_lead_id, name FROM teamlead WHERE team_lead_id = $entity_id";
                $entity_result = $conn->query($sql);
                if (!$entity_result) die("Query failed: " . $conn->error);
                $entity_row = $entity_result->fetch_assoc();
                $entity_name = $entity_row['name'];
                $entity_id = $entity_row['team_lead_id'];
                break;

            case 'expert':
                $sql = "SELECT expert_id, expert_name FROM expert WHERE expert_id = $entity_id";
                $entity_result = $conn->query($sql);
                if (!$entity_result) die("Query failed: " . $conn->error);
                $entity_row = $entity_result->fetch_assoc();
                $entity_name = $entity_row['expert_name'];
                $entity_id = $entity_row['expert_id'];
                break;
        }

        // Process each selected month
        foreach ($client_months as $selected_month) {
            // Calculate previous month dues
            $previous_month = date('Y-m', strtotime($selected_month . '-01 -1 month'));
            $previous_month_name = date('F Y', strtotime($previous_month . '-01'));

            // Get initial dues
            $initial_dues = 0;
            switch ($role) {
                case 'client':
                    $sql = "SELECT initial_dues FROM client WHERE client_id = $entity_id";
                    break;
                case 'teamlead':
                    $sql = "SELECT initial_due FROM teamlead WHERE team_lead_id = $entity_id";
                    break;
                case 'expert':
                    $sql = "SELECT initial_dues FROM expert WHERE expert_id = $entity_id";
                    break;
            }
            $result = $conn->query($sql);
            if (!$result) die("Query failed: " . $conn->error);
            $row = $result->fetch_assoc();
            $initial_dues = $row[$role === 'teamlead' ? 'initial_due' : 'initial_dues'];

            // Get total price from task table till the previous month
            $total_price = 0;
            switch ($role) {
                case 'client':
                    $sql = "SELECT COALESCE(SUM(price), 0) AS total_price 
                            FROM task 
                            WHERE client_id = $entity_id AND DATE_FORMAT(task_date, '%Y-%m') <= '$previous_month'";
                    break;
                case 'teamlead':
                    $sql = "SELECT COALESCE(SUM(tl_price), 0) AS total_price 
                            FROM task 
                            WHERE team_lead_id = $entity_id AND DATE_FORMAT(task_date, '%Y-%m') <= '$previous_month'";
                    break;
                case 'expert':
                    $sql = "SELECT COALESCE(SUM(
                        CASE 
                            WHEN expert_id_1 = $entity_id THEN expert_price1
                            WHEN expert_id_2 = $entity_id THEN expert_price2
                            WHEN expert_id_3 = $entity_id THEN expert_price3
                            ELSE 0 
                        END
                    ), 0) AS total_price 
                    FROM task 
                    WHERE (expert_id_1 = $entity_id OR expert_id_2 = $entity_id OR expert_id_3 = $entity_id) 
                    AND DATE_FORMAT(task_date, '%Y-%m') <= '$previous_month'";
                    break;
            }
            $result = $conn->query($sql);
            if (!$result) die("Query failed: " . $conn->error);
            $row = $result->fetch_assoc();
            $total_price = $row['total_price'];

            // Get total amount_paid till the previous month
            $total_paid = 0;
            switch ($role) {
                case 'client':
                    $sql = "SELECT COALESCE(SUM(amount_paid), 0) AS total_paid 
                            FROM adminclientpayment 
                            WHERE client_id = $entity_id AND DATE_FORMAT(payment_date, '%Y-%m') <= '$previous_month'";
                    break;
                case 'teamlead':
                    $sql = "SELECT COALESCE(SUM(amount_paid), 0) AS total_paid 
                            FROM admintlpayment 
                            WHERE tl_id = $entity_id AND DATE_FORMAT(payment_date, '%Y-%m') <= '$previous_month'";
                    break;
                case 'expert':
                    $sql = "SELECT COALESCE(SUM(amount_paid), 0) AS total_paid 
                            FROM tlexpertpayment 
                            WHERE expert_id = $entity_id AND DATE_FORMAT(payment_date, '%Y-%m') <= '$previous_month'";
                    break;
            }
            $result = $conn->query($sql);
            if (!$result) die("Query failed: " . $conn->error);
            $row = $result->fetch_assoc();
            $total_paid = $row['total_paid'];

            // Calculate dues till the previous month
            $dues_till_previous_month = ($initial_dues + $total_price) - $total_paid;

            // Build SQL query based on role
            switch ($role) {
                case 'client':
                    $sql = "
                        (SELECT 
                            'Task' AS activity_type,
                            ta.task_name AS description,
                            ta.task_date AS date,
                            ta.price AS amount,
                            t.name AS assigned_to,
                            ta.tl_price,
                            ta.assigned_expert_1 AS expert_name_1,
                            ta.expert_price1,
                            ta.assigned_expert_2 AS expert_name_2,
                            ta.expert_price2,
                            ta.assigned_expert_3 AS expert_name_3,
                            ta.expert_price3,
                            ta.total_cost,
                            NULL AS inr_amount,
                            ta.status AS task_status,
                            ta.incomplete_information,
                            NULL AS discount
                        FROM task ta
                        LEFT JOIN teamlead t ON ta.team_lead_id = t.team_lead_id
                        WHERE ta.client_id = $entity_id AND DATE_FORMAT(ta.task_date, '%Y-%m') = '$selected_month')
                        
                        UNION ALL
                        
                        (SELECT 
                            'Payment' AS activity_type,
                            CONCAT('<strong>Payment Received:</strong> ', acp.description) AS description,
                            acp.payment_date AS date,
                            -acp.amount_paid AS amount,
                            NULL AS assigned_to,
                            NULL AS tl_price,
                            NULL AS expert_name_1,
                            NULL AS expert_price1,
                            NULL AS expert_name_2,
                            NULL AS expert_price2,
                            NULL AS expert_name_3,
                            NULL AS expert_price3,
                            NULL AS total_cost,
                            acp.payment_in_inr AS inr_amount,
                            '' AS task_status,
                            0 AS incomplete_information,
                            acp.discount
                        FROM adminclientpayment acp
                        WHERE acp.client_id = $entity_id AND DATE_FORMAT(acp.payment_date, '%Y-%m') = '$selected_month')
                        
                        ORDER BY date
                    ";
                    break;

                case 'teamlead':
                    $sql = "
                        SELECT 
                            'Task' AS activity_type,
                            task_name AS description,
                            task_date AS date,
                            tl_price AS amount,
                            ta.status AS task_status,
                            ta.expert_id_1,
                            ta.expert_price1,
                            ta.expert_id_2,
                            ta.expert_price2,
                            ta.expert_id_3,
                            ta.expert_price3,
                            ta.total_cost AS task_price,
                            ta.price AS client_price,
                            c.client_name AS client_name,
                            ta.incomplete_information
                        FROM task ta
                        LEFT JOIN client c ON ta.client_id = c.client_id
                        WHERE ta.team_lead_id = $entity_id AND DATE_FORMAT(ta.task_date, '%Y-%m') = '$selected_month'
                        UNION ALL
                        SELECT 
                            'Payment' AS activity_type,
                            'Payment Received' AS description,
                            payment_date AS date,
                            -amount_paid AS amount,
                            '' AS task_status,
                            NULL AS expert_id_1,
                            NULL AS expert_price1,
                            NULL AS expert_id_2,
                            NULL AS expert_price2,
                            NULL AS expert_id_3,
                            NULL AS expert_price3,
                            NULL AS task_price,
                            NULL AS client_price,
                            '' AS client_name,
                            0 AS incomplete_information
                        FROM admintlpayment
                        WHERE tl_id = $entity_id AND DATE_FORMAT(payment_date, '%Y-%m') = '$selected_month'
                        ORDER BY date
                    ";
                    break;

                case 'expert':
                    $team_lead_id = isset($_POST['team_lead_id']) ? $_POST['team_lead_id'] : null;
                    
                    $task_team_lead_condition = $team_lead_id ? "AND ta.team_lead_id = $team_lead_id" : "";
                    $payment_team_lead_condition = $team_lead_id ? "AND tep.team_lead_id = $team_lead_id" : "";

                    $sql = "
                        SELECT 
                            'Task' AS activity_type,
                            task_name AS description,
                            task_date AS date,
                            (CASE 
                                WHEN expert_id_1 = $entity_id THEN expert_price1
                                WHEN expert_id_2 = $entity_id THEN expert_price2
                                WHEN expert_id_3 = $entity_id THEN expert_price3
                                ELSE 0
                            END) AS amount,
                            t.name AS team_lead_name,
                            c.client_name AS client_name,
                            ta.status AS task_status,
                            ta.tl_price,
                            ta.total_cost AS task_price,
                            ta.price AS client_price,
                            ta.incomplete_information
                        FROM task ta
                        LEFT JOIN teamlead t ON ta.team_lead_id = t.team_lead_id
                        LEFT JOIN client c ON ta.client_id = c.client_id
                        WHERE (expert_id_1 = $entity_id OR expert_id_2 = $entity_id OR expert_id_3 = $entity_id)
                        AND DATE_FORMAT(ta.task_date, '%Y-%m') = '$selected_month'
                        $task_team_lead_condition
                        UNION ALL
                        SELECT 
                            'Payment' AS activity_type,
                            'Payment Received' AS description,
                            payment_date AS date,
                            -amount_paid AS amount,
                            t.name AS team_lead_name,
                            NULL AS client_name,
                            '' AS task_status,
                            NULL AS tl_price,
                            NULL AS task_price,
                            NULL AS client_price,
                            0 AS incomplete_information
                        FROM tlexpertpayment tep
                        LEFT JOIN teamlead t ON tep.team_lead_id = t.team_lead_id
                        WHERE expert_id = $entity_id 
                        AND DATE_FORMAT(payment_date, '%Y-%m') = '$selected_month'
                        $payment_team_lead_condition
                        ORDER BY date
                    ";
                    break;
            }

            $activities_result = $conn->query($sql);
            if (!$activities_result) {
                die("Query failed: " . $conn->error);
            }

            // Calculate total dues at the end of the month
            $total_dues = $dues_till_previous_month;
            while ($row = $activities_result->fetch_assoc()) {
                $total_dues += $row['amount'];
            }

            // Store month data
            $client_month_data[$selected_month] = [
                'activities_result' => $activities_result,
                'dues_till_previous_month' => $dues_till_previous_month,
                'total_dues' => $total_dues,
                'previous_month_name' => $previous_month_name
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill Visualization</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Multi-select CSS -->
    <link rel="stylesheet" href="https://unpkg.com/multiple-select@1.5.2/dist/multiple-select.min.css">
    <!-- html2canvas library for JPEG download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <!-- SheetJS library for Excel download -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
        }
        
        body {
            padding-top: 70px;
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

        .bill-table {
            margin-top: 20px;
        }
        .bill-table th, .bill-table td {
            padding: 10px;
            border: 1px solid #ddd;
        }
        .bill-table th {
            background-color: #f8f9fa;
        }
        .entity-details {
            margin-bottom: 20px;
            font-size: 18px;
            font-weight: bold;
        }
        #bill-content {
            background-color: white;
            padding: 20px;
            border: 1px solid #ddd;
        }
        #entity_id:disabled {
            background-color: #e9ecef;
        }
        .failed-row {
            background-color: #ff7575;
        }
        .incomplete-row {
            background-color: rgb(243, 199, 54);
        }
        #team_lead_dropdown {
            display: none;
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
        .incomplete-info-checkbox {
            margin: 15px 0;
        }
        .time-period-radio {
            margin-top: 10px;
        }
        .month-selector {
            display: none;
        }
        .month-selector.active {
            display: block;
        }
        .multi-select-container {
            width: 100%;
        }
        .month-section {
            margin-bottom: 40px;
            border-bottom: 2px solid #ddd;
            padding-bottom: 20px;
        }
        .month-section:last-child {
            border-bottom: none;
        }
        .month-checkboxes {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
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
        <h2>Generate Bill</h2>
        <form method="POST" class="mb-4">
            <div class="row g-3">
                <!-- Role Dropdown -->
                <div class="col-md-3">
                    <label for="role" class="form-label">Role:</label>
                    <select name="role" id="role" class="form-control" required onchange="updateEntityDropdown()">
                        <option value="client">Client</option>
                        <option value="teamlead">TeamLead</option>
                        <option value="expert">Expert</option>
                        <option value="monthly_return">Monthly Return</option>
                        <option value="revenue">Revenue</option>
                        <option value="expenditure">Expenditure</option>
                        <option value="income">Income</option>
                        <option value="client_dues">Client Dues</option>
                        <option value="writer_tl_dues">Writer/TL Dues</option>
                    </select>
                </div>

                <!-- Entity Dropdown with Search -->
                <div class="col-md-3" id="entity_dropdown_container">
                    <label class="form-label">Select:</label>
                    <div class="dropdown-container">
                        <input type="text" id="entity-search" class="form-control" placeholder="Search...">
                        <ul class="dropdown-list" id="entity-list">
                            <?php
                            $clients_result->data_seek(0);
                            while ($row = $clients_result->fetch_assoc()) {
                                echo "<li data-value='{$row['client_id']}'>{$row['client_name']}</li>";
                            }
                            ?>
                        </ul>
                        <input type="hidden" name="entity_id" id="entity_id">
                    </div>
                </div>

                <!-- Team Lead Dropdown (for Expert role) -->
                <div class="col-md-3" id="team_lead_dropdown">
                    <label class="form-label">Team Lead:</label>
                    <select name="team_lead_id" id="team_lead_id" class="form-control">
                        <option value="">All Team Leads</option>
                        <?php
                        $team_leads_result->data_seek(0);
                        while ($row = $team_leads_result->fetch_assoc()) {
                            echo "<option value='{$row['team_lead_id']}'>{$row['name']}</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Single Month Input -->
                <div class="col-md-3 month-selector" id="single_month_container">
                    <label for="month" class="form-label">Month:</label>
                    <input type="month" name="month" id="month" class="form-control" value="<?php echo date('Y-m'); ?>">
                </div>

                <!-- Multiple Month Selector -->
                <div class="col-md-3 month-selector" id="multiple_month_container" style="display: none;">
                    <label class="form-label">Months:</label>
                    <div class="month-checkboxes">
                        <?php 
                        // Get earliest month from tasks
                        $min_date_result = $conn->query("SELECT MIN(task_date) AS min_date FROM task");
                        $min_date = $min_date_result->fetch_assoc()['min_date'];
                        $start = new DateTime($min_date ? $min_date : 'now');
                        $start->modify('first day of this month');
                        
                        // Get current month
                        $end = new DateTime();
                        $end->modify('first day of this month');
                        
                        // Generate all months between start and end
                        $months = [];
                        while ($start <= $end) {
                            $month = $start->format('Y-m');
                            $months[] = $month;
                            $start->modify('+1 month');
                        }
                        
                        // Reverse to show latest first
                        $months = array_reverse($months);
                        
                        foreach ($months as $m) {
                            $month_name = date('F Y', strtotime($m . '-01'));
                            echo "<div class='form-check'>
                                    <input class='form-check-input' type='checkbox' name='months[]' value='$m' id='month_$m'>
                                    <label class='form-check-label' for='month_$m'>$month_name</label>
                                  </div>";
                        }
                        ?>
                    </div>
                </div>

                <!-- Time Period Radio -->
                <div class="col-md-3 time-period-radio" id="time_period_container" style="display: none;">
                    <label class="form-label">Time Period:</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="time_period" id="monthly_radio" value="monthly" checked>
                        <label class="form-check-label" for="monthly_radio">Monthly</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="time_period" id="all_time_radio" value="all_time">
                        <label class="form-check-label" for="all_time_radio">All Time</label>
                    </div>
                </div>

                <!-- Generate Bill Button -->
                <div class="col-md-3">
                    <button type="submit" name="generate_bill" class="btn btn-primary w-100 mt-4">Generate Bill</button>
                </div>
            </div>
        </form>

        <?php if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['generate_bill'])): ?>
            <div id="bill-content">
                
                <?php if (in_array($role, ['client', 'teamlead', 'expert'])): ?>
                    <div class="entity-details">
                        <p><?php echo ucfirst($role); ?> Name: <?php echo $entity_name; ?></p>
                        <p><?php echo ucfirst($role); ?> ID: <?php echo $entity_id; ?></p>
                    </div>
                    
                    <?php foreach ($client_months as $selected_month): 
                        $month_data = $client_month_data[$selected_month];
                        $activities_result = $month_data['activities_result'];
                        $dues_till_previous_month = $month_data['dues_till_previous_month'];
                        $total_dues = $month_data['total_dues'];
                        $previous_month_name = $month_data['previous_month_name'];
                    ?>
                        <div class="month-section">
                            <h3>Bill for <?php echo date('F Y', strtotime($selected_month . '-01')); ?></h3>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <?php if ($role === 'client'): ?>
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Activity</th>
                                                <th>Description</th>
                                                <th>Amount</th>
                                                <th>Assigned To (Team Lead)</th>
                                                <th>Team Lead Price</th>
                                                <th>Expert 1</th>
                                                <th>Expert 2</th>
                                                <th>Expert 3</th>
                                                <th>Total Cost</th>
                                                <th>Payment in INR</th>
                                                <th>Task Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Dues till previous month -->
                                            <tr>
                                                <td><?php echo $previous_month_name; ?></td>
                                                <td>Dues Till Previous Month</td>
                                                <td>Initial Dues + Task Price - Payments (Till <?php echo $previous_month_name; ?>)</td>
                                                <td><?php echo $dues_till_previous_month; ?></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            </tr>

                                            <?php
                                            $activities_result->data_seek(0);
                                            while ($row = $activities_result->fetch_assoc()) { 
                                                $description = $row['description'];
                                                if ($row['activity_type'] === 'Payment') {
                                                    $discount = $row['discount'];
                                                    $country = isset($row['country']) ? strtolower(trim($row['country'])) : 'not';
                                                    $rate = $country_rates[$country] ?? 1;
                                                    
                                                    if ($discount > 0) {
                                                        $discount_inr = $discount;
                                                        $description = "Discount: " . number_format($discount_inr, 2) . "";
                                                    } else {
                                                        $description = "Payment Done";
                                                    }
                                                }
                                            ?>
                                                <tr class="<?php echo ($row['task_status'] === 'failed') ? 'failed-row' : ''; ?> <?php echo ($row['incomplete_information'] == 1) ? 'incomplete-row' : ''; ?>">
                                                    <td><?php echo date('d/m/Y', strtotime($row['date'])); ?></td>
                                                    <td><?php echo $row['activity_type']; ?></td>
                                                    <td><?php echo $description; ?></td>
                                                    <td><?php echo number_format($row['amount'], 2, '.', ''); ?></td>
                                                    <td><?php echo $row['assigned_to']; ?></td>
                                                    <td>
                                                        <?php if ($row['activity_type'] === 'Task') {
                                                            echo number_format($row['tl_price'], 2);
                                                        } ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($row['activity_type'] === 'Task' && $row['expert_name_1']) {
                                                            echo $row['expert_name_1'] . ' (' . number_format($row['expert_price1'], 2) . ')';
                                                        } ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($row['activity_type'] === 'Task' && $row['expert_name_2']) {
                                                            echo $row['expert_name_2'] . ' (' . number_format($row['expert_price2'], 2) . ')';
                                                        } ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($row['activity_type'] === 'Task' && $row['expert_name_3']) {
                                                            echo $row['expert_name_3'] . ' (' . number_format($row['expert_price3'], 2) . ')';
                                                        } ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($row['activity_type'] === 'Task') {
                                                            echo number_format($row['total_cost'], 2);
                                                        } ?>
                                                    </td>
                                                    <td><?php echo $row['inr_amount']; ?></td>
                                                    <td><?php echo $row['task_status']; ?></td>
                                                </tr>
                                            <?php } ?>

                                            <!-- Final Dues -->
                                            <tr>
                                                <td></td>
                                                <td>Final Dues</td>
                                                <td>Dues till previous month + Current month transactions (<?php echo date('F Y', strtotime($selected_month . '-01')); ?>)</td>
                                                <td><?php echo $total_dues; ?></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            </tr>
                                        </tbody>
                                    <?php elseif ($role === 'teamlead'): ?>
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Activity</th>
                                                <th>Description</th>
                                                <th>Amount (TL Price)</th>
                                                <th>Expert 1</th>
                                                <th>Expert 2</th>
                                                <th>Expert 3</th>
                                                <th>Task Price</th>
                                                <th>Client Price</th>
                                                <th>Client Name</th>
                                                <th>Task Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><?php echo $previous_month_name; ?></td>
                                                <td>Dues Till Previous Month</td>
                                                <td>Initial Dues + Task Price - Payments (Till <?php echo $previous_month_name; ?>)</td>
                                                <td><?php echo $dues_till_previous_month; ?></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            </tr>

                                            <?php
                                            $activities_result->data_seek(0);
                                            while ($row = $activities_result->fetch_assoc()) { 
                                            ?>
                                                <tr class="<?php echo ($row['task_status'] === 'failed') ? 'failed-row' : ''; ?> <?php echo ($row['incomplete_information'] == 1) ? 'incomplete-row' : ''; ?>">
                                                    <td><?php echo date('d/m/Y', strtotime($row['date'])); ?></td>
                                                    <td><?php echo $row['activity_type']; ?></td>
                                                    <td><?php echo $row['description']; ?></td>
                                                    <td><?php echo number_format($row['amount'], 2, '.', ''); ?></td>
                                                    <td>
                                                        <?php 
                                                        if ($row['expert_id_1']) {
                                                            $expert = $conn->query("SELECT expert_name FROM expert WHERE expert_id = ".$row['expert_id_1'])->fetch_assoc();
                                                            echo $expert['expert_name']." (".$row['expert_price1'].")";
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        if ($row['expert_id_2']) {
                                                            $expert = $conn->query("SELECT expert_name FROM expert WHERE expert_id = ".$row['expert_id_2'])->fetch_assoc();
                                                            echo $expert['expert_name']." (".$row['expert_price2'].")";
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        if ($row['expert_id_3']) {
                                                            $expert = $conn->query("SELECT expert_name FROM expert WHERE expert_id = ".$row['expert_id_3'])->fetch_assoc();
                                                            echo $expert['expert_name']." (".$row['expert_price3'].")";
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><?php echo $row['task_price'] ? number_format($row['task_price'], 2) : ''; ?></td>
                                                    <td><?php echo $row['client_price'] ? number_format($row['client_price'], 2) : ''; ?></td>
                                                    <td><?php echo $row['client_name']; ?></td>
                                                    <td><?php echo $row['task_status']; ?></td>
                                                </tr>
                                            <?php } ?>

                                            <tr>
                                                <td></td>
                                                <td>Final Dues</td>
                                                <td>Dues till previous month + Current month transactions (<?php echo date('F Y', strtotime($selected_month . '-01')); ?>)</td>
                                                <td><?php echo $total_dues; ?></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            </tr>
                                        </tbody>
                                    <?php elseif ($role === 'expert'): ?>
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Activity</th>
                                                <th>Description</th>
                                                <th>Amount</th>
                                                <th>Team Lead Price</th>
                                                <th>Task Price</th>
                                                <th>Client Price</th>
                                                <th>Team Lead Name</th>
                                                <th>Client Name</th>
                                                <th>Task Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><?php echo $previous_month_name; ?></td>
                                                <td>Dues Till Previous Month</td>
                                                <td>Initial Dues + Task Price - Payments (Till <?php echo $previous_month_name; ?>)</td>
                                                <td><?php echo $dues_till_previous_month; ?></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            </tr>

                                            <?php
                                            $activities_result->data_seek(0);
                                            while ($row = $activities_result->fetch_assoc()) { 
                                            ?>
                                                <tr class="<?php echo ($row['task_status'] === 'failed') ? 'failed-row' : ''; ?> <?php echo ($row['incomplete_information'] == 1) ? 'incomplete-row' : ''; ?>">
                                                    <td><?php echo date('d/m/Y', strtotime($row['date'])); ?></td>
                                                    <td><?php echo $row['activity_type']; ?></td>
                                                    <td><?php echo $row['description']; ?></td>
                                                    <td><?php echo number_format($row['amount'], 2); ?></td>
                                                    <td><?php echo $row['tl_price'] ? number_format($row['tl_price'], 2) : ''; ?></td>
                                                    <td><?php echo $row['task_price'] ? number_format($row['task_price'], 2) : ''; ?></td>
                                                    <td><?php echo $row['client_price'] ? number_format($row['client_price'], 2) : ''; ?></td>
                                                    <td><?php echo $row['team_lead_name']; ?></td>
                                                    <td><?php echo $row['client_name']; ?></td>
                                                    <td><?php echo $row['task_status']; ?></td>
                                                </tr>
                                            <?php } ?>

                                            <tr>
                                                <td></td>
                                                <td>Final Dues</td>
                                                <td>Dues till previous month + Current month transactions (<?php echo date('F Y', strtotime($selected_month . '-01')); ?>)</td>
                                                <td><?php echo $total_dues; ?></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                                <td></td>
                                            </tr>
                                        </tbody>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php elseif ($role === 'monthly_return'): ?>
                    <h2>Monthly Return for <?php echo date('F Y', strtotime($month . '-01')); ?></h2>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Activity</th>
                                    <th>Description</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $activities_result->data_seek(0);
                                while ($row = $activities_result->fetch_assoc()) { 
                                    $description = $row['description'];
                                ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($row['date'])); ?></td>
                                        <td><?php echo $row['activity_type']; ?></td>
                                        <td><?php echo $description; ?></td>
                                        <td><?php echo number_format($row['amount'], 2, '.', ''); ?></td>
                                    </tr>
                                <?php } ?>
                                <tr class="table-success">
                                    <td colspan="3" class="text-end"><strong>Total Profit/Loss:</strong></td>
                                    <td><strong><?php echo number_format($total_profit_loss, 2); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($role === 'revenue'): ?>
                    <h2>Revenue Report <?php echo $time_period === 'monthly' ? 'for ' . date('F Y', strtotime($month . '-01')) : '(All Time)'; ?></h2>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Task Date</th>
                                    <th>Task ID</th>
                                    <th>Task Name</th>
                                    <th>Client Name</th>
                                    <th>Country</th>
                                    <th>Price (Original)</th>
                                    <th>Conversion Rate</th>
                                    <th>Converted Amount (INR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                usort($revenue_details, function($a, $b) {
                                    return strtotime($a['task_date']) - strtotime($b['task_date']);
                                });
                                
                                foreach ($revenue_details as $detail) { ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($detail['task_date'])); ?></td>
                                        <td><?php echo $detail['task_id']; ?></td>
                                        <td><?php echo $detail['task_name']; ?></td>
                                        <td><?php echo $detail['client_name']; ?></td>
                                        <td><?php echo $detail['country']; ?></td>
                                        <td><?php echo number_format($detail['task_price'], 2); ?></td>
                                        <td><?php echo $detail['rate']; ?></td>
                                        <td><?php echo number_format($detail['converted_amount'], 2); ?></td>
                                    </tr>
                                <?php } ?>
                                <tr class="table-success">
                                    <td colspan="7" class="text-end"><strong>Total Revenue:</strong></td>
                                    <td><strong><?php echo number_format($total_revenue, 2); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h3 class="mt-4">Discounts Offered</h3>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Payment Date</th>
                                    <th>Client Name</th>
                                    <th>Country</th>
                                    <th>Discount (Original)</th>
                                    <th>Conversion Rate</th>
                                    <th>Converted Discount (INR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $discount_sql = "SELECT 
                                                    acp.payment_date,
                                                    c.client_name,
                                                    col.country,
                                                    acp.discount
                                                FROM adminclientpayment acp
                                                JOIN client c ON acp.client_id = c.client_id
                                                LEFT JOIN colleges col ON c.college_id = col.college_id 
                                                    OR LOWER(TRIM(c.college_name)) = LOWER(TRIM(col.college_name))
                                                WHERE acp.discount > 0";
                                
                                if ($time_period === 'monthly') {
                                    $discount_sql .= " AND DATE_FORMAT(acp.payment_date, '%Y-%m') = '$month'";
                                }
                                
                                $discount_result = $conn->query($discount_sql);
                                $total_discount = 0;
                                $discount_details = [];
                                
                                while ($row = $discount_result->fetch_assoc()) {
                                    $country = isset($row['country']) ? trim($row['country']) : 'not';
                                    $country_lower = strtolower($country);
                                    $rate = $country_rates[$country_lower] ?? 1;
                                    $converted_discount = $row['discount'] * $rate;
                                    $total_discount += $converted_discount;
                                    
                                    $discount_details[] = [
                                        'payment_date' => $row['payment_date'],
                                        'client_name' => $row['client_name'],
                                        'country' => $country ? $country : 'Not Specified',
                                        'discount' => $row['discount'],
                                        'rate' => $rate,
                                        'converted_discount' => $converted_discount
                                    ];
                                }
                                
                                usort($discount_details, function($a, $b) {
                                    return strtotime($a['payment_date']) - strtotime($b['payment_date']);
                                });
                                
                                foreach ($discount_details as $discount) { ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($discount['payment_date'])); ?></td>
                                        <td><?php echo $discount['client_name']; ?></td>
                                        <td><?php echo $discount['country']; ?></td>
                                        <td><?php echo number_format($discount['discount'], 2); ?></td>
                                        <td><?php echo $discount['rate']; ?></td>
                                        <td><?php echo number_format($discount['converted_discount'], 2); ?></td>
                                    </tr>
                                <?php } ?>
                                <tr class="table-danger">
                                    <td colspan="5" class="text-end"><strong>Total Discounts:</strong></td>
                                    <td><strong><?php echo number_format($total_discount, 2); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-responsive mt-4">
                        <table class="table table-bordered">
                            <tr class="table-primary">
                                <td class="text-end"><strong>Total Revenue:</strong></td>
                                <td><?php echo number_format($total_revenue, 2); ?></td>
                            </tr>
                            <tr class="table-primary">
                                <td class="text-end"><strong>Total Discounts:</strong></td>
                                <td><?php echo number_format($total_discount, 2); ?></td>
                            </tr>
                            <tr class="table-success">
                                <td class="text-end"><strong>Final Revenue:</strong></td>
                                <td><strong><?php echo number_format($total_revenue - $total_discount, 2); ?></strong></td>
                            </tr>
                        </table>
                    </div>
                <?php elseif ($role === 'expenditure'): ?>
                    <h2>Expenditure Report <?php echo $time_period === 'monthly' ? 'for ' . date('F Y', strtotime($month . '-01')) : '(All Time)'; ?></h2>
                    <h4>Total Expenditure: <?php echo number_format($total_expenditure, 2); ?></h4>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Transaction Count</th>
                                    <th>Total Payment (INR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expenditure_details as $detail) { ?>
                                    <tr>
                                        <td><?php echo $detail['name']; ?></td>
                                        <td><?php echo $detail['role']; ?></td>
                                        <td><?php echo $detail['transaction_count']; ?></td>
                                        <td><?php echo number_format($detail['total_payment'], 2); ?></td>
                                    </tr>
                                <?php } ?>
                                <tr class="table-success">
                                    <td colspan="3" class="text-end"><strong>Total Expenditure:</strong></td>
                                    <td><strong><?php echo number_format($total_expenditure, 2); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($role === 'income'): ?>
                    <h2>Income Report <?php echo $time_period === 'monthly' ? 'for ' . date('F Y', strtotime($month . '-01')) : '(All Time)'; ?></h2>
                    <h4>Total Income: <?php echo number_format($total_income, 2); ?></h4>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Client Name</th>
                                    <th>Transaction Count</th>
                                    <th>Total Payment (INR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($income_details as $detail) { ?>
                                    <tr>
                                        <td><?php echo $detail['client_name']; ?></td>
                                        <td><?php echo $detail['transaction_count']; ?></td>
                                        <td><?php echo number_format($detail['total_payment'], 2); ?></td>
                                    </tr>
                                <?php } ?>
                                <tr class="table-success">
                                    <td colspan="2" class="text-end"><strong>Total Income:</strong></td>
                                    <td><strong><?php echo number_format($total_income, 2); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($role === 'client_dues'): ?>
                    <h2>Client Dues Report (All Time)</h2>
                    <h4>Total Client Dues: <?php echo number_format($total_dues, 2); ?></h4>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Client Name</th>
                                    <th>Country</th>
                                    <th>Due Payment (Original)</th>
                                    <th>Conversion Rate</th>
                                    <th>Converted Amount (INR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dues_details as $detail) { ?>
                                    <tr>
                                        <td><?php echo $detail['client_name']; ?></td>
                                        <td><?php echo $detail['country']; ?></td>
                                        <td><?php echo number_format($detail['due_payment'], 2); ?></td>
                                        <td><?php echo $detail['rate']; ?></td>
                                        <td><?php echo number_format($detail['converted_amount'], 2); ?></td>
                                    </tr>
                                <?php } ?>
                                <tr class="table-success">
                                    <td colspan="4" class="text-end"><strong>Total Client Dues:</strong></td>
                                    <td><strong><?php echo number_format($total_dues, 2); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($role === 'writer_tl_dues'): ?>
                    <h2>Writer/Team Lead Dues Report (All Time)</h2>
                    <h4>Total Dues: <?php echo number_format($total_writer_tl_dues, 2); ?></h4>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Total Dues (INR)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($writer_tl_dues_details as $detail) { ?>
                                    <tr>
                                        <td><?php echo $detail['name']; ?></td>
                                        <td><?php echo $detail['role']; ?></td>
                                        <td><?php echo number_format($detail['total_dues'], 2); ?></td>
                                    </tr>
                                <?php } ?>
                                <tr class="table-success">
                                    <td colspan="2" class="text-end"><strong>Total Dues:</strong></td>
                                    <td><strong><?php echo number_format($total_writer_tl_dues, 2); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <div class="mt-4">
                <button onclick="downloadAsJPEG()" class="btn btn-warning">Download as JPEG</button>
                <button onclick="downloadAsExcel()" class="btn btn-success">Download as Excel</button>
            </div>
        <?php endif; ?>
        <br>

        <!-- Add Task Form -->
        <h2>Add Task</h2>
        <form method="post" class="mb-4">
            <!-- Incomplete Information Checkbox -->
            <div class="row mb-3 incomplete-info-checkbox">
                <div class="col-md-12">
                    <div class="form-check">
                        <input type="checkbox" name="incomplete_information" class="form-check-input" id="incomplete_information">
                        <label class="form-check-label" for="incomplete_information">Incomplete Information</label>
                    </div>
                </div>
            </div>

            <!-- Client Dropdown with Search -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Client:</label>
                    <div class="dropdown-container">
                        <input type="text" id="task-client-search" class="form-control" placeholder="Search client...">
                        <ul class="dropdown-list" id="task-client-list">
                            <?php
                            $clients_result->data_seek(0);
                            while ($row = $clients_result->fetch_assoc()) {
                                echo "<li data-value='{$row['client_id']}'>{$row['client_name']}</li>";
                            }
                            ?>
                        </ul>
                        <input type="hidden" name="client_id" id="task_client_id">
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
                    <select name="status" class="form-control" required>
                        <option value="in progress">In Progress</option>
                        <option value="submitted">Submitted</option>
                        <option value="passed">Passed</option>
                        <option value="failed">Failed</option>
                        <option value="submitted late">Submitted Late</option>
                    </select>
                </div>
                <div class="col-md-6">     
                    <label class="form-label">Issue:</label>     
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

            <!-- Team Assignment Section with Searchable Dropdowns -->
            <div class="card mb-3">
                <div class="card-body">
                    <table class="table">
                        <tbody>
                            <!-- Team Lead -->
                            <tr>
                                <td>Team Lead</td>
                                <td>
                                    <div class="dropdown-container">
                                        <input type="text" id="task-team-lead-search" class="form-control" placeholder="Search team lead...">
                                        <ul class="dropdown-list" id="task-team-lead-list">
                                            <?php
                                            $team_leads_result->data_seek(0);
                                            while ($row = $team_leads_result->fetch_assoc()) {
                                                echo "<li data-value='{$row['team_lead_id']}'>{$row['name']}</li>";
                                            }
                                            ?>
                                        </ul>
                                        <input type="hidden" name="team_lead_id" id="task_team_lead_id">
                                    </div>
                                </td>
                                <td>
                                    <input type="number" name="tl_price" class="form-control" required>
                                </td>
                            </tr>

                            <!-- Expert 1 -->
                            <tr>
                                <td>Expert 1</td>
                                <td>
                                    <div class="dropdown-container">
                                        <input type="text" id="task-expert1-search" class="form-control" placeholder="Search expert...">
                                        <ul class="dropdown-list" id="task-expert1-list">
                                            <?php
                                            $experts_result->data_seek(0);
                                            while ($row = $experts_result->fetch_assoc()) {
                                                echo "<li data-value='{$row['expert_id']}'>{$row['expert_name']}</li>";
                                            }
                                            ?>
                                        </ul>
                                        <input type="hidden" name="expert_id_1" id="task_expert1_id">
                                    </div>
                                </td>
                                <td>
                                    <input type="number" name="expert_price1" class="form-control">
                                </td>
                            </tr>

                            <!-- Expert 2 -->
                            <tr>
                                <td>Expert 2</td>
                                <td>
                                    <div class="dropdown-container">
                                        <input type="text" id="task-expert2-search" class="form-control" placeholder="Search expert...">
                                        <ul class="dropdown-list" id="task-expert2-list">
                                            <?php
                                            $experts_result->data_seek(0);
                                            while ($row = $experts_result->fetch_assoc()) {
                                                echo "<li data-value='{$row['expert_id']}'>{$row['expert_name']}</li>";
                                            }
                                            ?>
                                        </ul>
                                        <input type="hidden" name="expert_id_2" id="task_expert2_id">
                                    </div>
                                </td>
                                <td>
                                    <input type="number" name="expert_price2" class="form-control">
                                </td>
                            </tr>

                            <!-- Expert 3 -->
                            <tr>
                                <td>Expert 3</td>
                                <td>
                                    <div class="dropdown-container">
                                        <input type="text" id="task-expert3-search" class="form-control" placeholder="Search expert...">
                                        <ul class="dropdown-list" id="task-expert3-list">
                                            <?php
                                            $experts_result->data_seek(0);
                                            while ($row = $experts_result->fetch_assoc()) {
                                                echo "<li data-value='{$row['expert_id']}'>{$row['expert_name']}</li>";
                                            }
                                            ?>
                                        </ul>
                                        <input type="hidden" name="expert_id_3" id="task_expert3_id">
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

            <!-- Submit Button -->
            <div class="row">
                <div class="col-md-12">
                    <button type="submit" name="add" class="btn btn-primary w-100">Add Task</button>
                </div>
            </div>
        </form>

        <!-- Copy Task Details Button -->
        <div class="col-md-12 mt-3">
            <button type="button" onclick="copyTaskDetails()" class="btn btn-info w-100">Copy Task Details</button>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Multi-select JS -->
    <script src="https://unpkg.com/multiple-select@1.5.2/dist/multiple-select.min.js"></script>
    <script>
        // Initialize all dropdowns
        function initDropdowns() {
            document.querySelectorAll('.dropdown-container').forEach(dropdown => {
                const input = dropdown.querySelector('input[type="text"]');
                const list = dropdown.querySelector('.dropdown-list');
                const hiddenInput = dropdown.querySelector('input[type="hidden"]');

                input.addEventListener('focus', () => list.style.display = 'block');
                input.addEventListener('input', () => {
                    const searchTerm = input.value.toLowerCase();
                    const items = list.querySelectorAll('li');
                    items.forEach(item => {
                        const text = item.textContent.toLowerCase();
                        item.style.display = text.includes(searchTerm) ? '' : 'none';
                    });
                });

                list.addEventListener('click', (e) => {
                    if (e.target.tagName === 'LI') {
                        input.value = e.target.textContent;
                        hiddenInput.value = e.target.getAttribute('data-value');
                        list.style.display = 'none';
                    }
                });

                document.addEventListener('click', (e) => {
                    if (!dropdown.contains(e.target)) {
                        list.style.display = 'none';
                    }
                });
            });
        }

        // Update role change handler
        function handleRoleChange() {
            const role = document.getElementById('role').value;
            const teamLeadDiv = document.getElementById('team_lead_dropdown');
            const entityDropdownContainer = document.getElementById('entity_dropdown_container');
            const singleMonth = document.getElementById('single_month_container');
            const multiMonth = document.getElementById('multiple_month_container');
            const timePeriodContainer = document.getElementById('time_period_container');
            
            // Show/hide team lead dropdown for expert role
            if (role === 'expert') {
                teamLeadDiv.style.display = 'block';
            } else {
                teamLeadDiv.style.display = 'none';
            }
            
            // Show/hide entity dropdown for financial reports
            if (['revenue', 'expenditure', 'income', 'client_dues', 'writer_tl_dues'].includes(role)) {
                entityDropdownContainer.style.display = 'none';
                
                if (['client_dues', 'writer_tl_dues'].includes(role)) {
                    singleMonth.style.display = 'none';
                    multiMonth.style.display = 'none';
                    timePeriodContainer.style.display = 'none';
                } else {
                    singleMonth.style.display = 'block';
                    multiMonth.style.display = 'none';
                    timePeriodContainer.style.display = 'block';
                }
            } else {
                entityDropdownContainer.style.display = 'block';
                timePeriodContainer.style.display = 'none';
                
                // Show multi-month selector for client, teamlead, and expert
                if (['client', 'teamlead', 'expert'].includes(role)) {
                    singleMonth.style.display = 'none';
                    multiMonth.style.display = 'block';
                } else {
                    singleMonth.style.display = 'block';
                    multiMonth.style.display = 'none';
                }
            }
        }

        // Update entity dropdown
        function updateEntityDropdown() {
            handleRoleChange();
            const role = document.getElementById('role').value;
            const entityList = document.getElementById('entity-list');
            
            // Clear existing options
            entityList.innerHTML = '';

            // Populate based on role
            switch(role) {
                case 'client':
                    <?php
                    $clients_result->data_seek(0);
                    while ($row = $clients_result->fetch_assoc()) {
                        echo "entityList.innerHTML += '<li data-value=\'{$row['client_id']}\'>{$row['client_name']}</li>';";
                    }
                    ?>
                    break;
                case 'teamlead':
                    <?php
                    $team_leads_result->data_seek(0);
                    while ($row = $team_leads_result->fetch_assoc()) {
                        echo "entityList.innerHTML += '<li data-value=\'{$row['team_lead_id']}\'>{$row['name']}</li>';";
                    }
                    ?>
                    break;
                case 'expert':
                    <?php
                    $experts_result->data_seek(0);
                    while ($row = $experts_result->fetch_assoc()) {
                        echo "entityList.innerHTML += '<li data-value=\'{$row['expert_id']}\'>{$row['expert_name']}</li>';";
                    }
                    ?>
                    break;
            }

            // Reinitialize dropdown functionality
            initDropdowns();
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', () => {
            initDropdowns();
            handleRoleChange();
            document.getElementById('role').addEventListener('change', handleRoleChange);
        });

        // Download functions
        function downloadAsJPEG() {
            html2canvas(document.querySelector("#bill-content")).then(canvas => {
                const link = document.createElement('a');
                link.download = 'bill.jpeg';
                link.href = canvas.toDataURL('image/jpeg');
                link.click();
            });
        }

        function downloadAsExcel() {
            const wb = XLSX.utils.book_new();
            const tables = document.querySelectorAll("#bill-content table");
            
            tables.forEach((table, index) => {
                const ws = XLSX.utils.table_to_sheet(table);
                let sheetName;
                if (index === 0) sheetName =  "Sheet " + (index + 1);
                else if (index === 1) sheetName =  "Sheet " + (index + 1);
                else if (index === 2) sheetName =  "Sheet " + (index + 1);
                else sheetName = "Sheet " + (index + 1);
                
                XLSX.utils.book_append_sheet(wb, ws, sheetName);
            });
            
            XLSX.writeFile(wb, "report.xlsx");
        }

        function copyTaskDetails() {
            const taskName = document.querySelector('input[name="task_name"]').value;
            const description = document.querySelector('textarea[name="description"]').value;
            const clientName = document.getElementById('task-client-search').value;
            const teamLeadName = document.getElementById('task-team-lead-search').value;
            const taskDate = document.querySelector('input[name="task_date"]').value;
            const dueDate = document.querySelector('input[name="due_date"]').value;
            
            const expert1Name = document.getElementById('task-expert1-search').value;
            const expert2Name = document.getElementById('task-expert2-search').value;
            const expert3Name = document.getElementById('task-expert3-search').value;
            
            let expertsText = [];
            if (expert1Name) expertsText.push(expert1Name);
            if (expert2Name) expertsText.push(expert2Name);
            if (expert3Name) expertsText.push(expert3Name);
            expertsText = expertsText.length > 0 ? expertsText.join(', ') : 'None';
            
            const detailsText = `Code: ${clientName}
Task Title: ${taskName}
Description: ${description}
Assigned to: ${teamLeadName}
Deadline: ${dueDate}
Issue date: ${taskDate}
Expert assigned: ${expertsText}`;

            navigator.clipboard.writeText(detailsText)
            .then(() => {
                alert('Task details copied to clipboard!');
            })
            .catch(err => {
                console.error('Failed to copy:', err);
                alert('Failed to copy. Please try again.');
            });
        }
    </script>
    <?php include 'footer.php'; ?>
</body>
</html>

<?php $conn->close(); ?>