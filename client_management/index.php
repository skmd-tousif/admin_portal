<?php
// Start session and check admin authentication
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: ../index.php");
    exit();
}

// Navigation items
$navItems = [
    'Tasks' => 'task.php',
    'Clients' => 'client.php',
    'Generate Sheets' => 'visualization.php',
    'A-Client Payments' => 'admin_client_payment.php',
    'Team Leads' => 'teamlead.php',
    'A-TL Payments' => 'admin_tl_payment.php',
    'Experts' => 'expert.php',
    'TL-Expert Payments' => 'tl_expert_payment.php',
    'Colleges' => 'colleges.php'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PACE_DB Admin Portal</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e293b;
            --accent-color: #3b82f6;
            --light-bg: #f8fafc;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            min-height: 100vh;
        }

        .navbar {
            background: var(--secondary-color) !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            padding: 0.75rem 1rem;
        }

        .navbar-brand {
            font-weight: 600;
            color: white !important;
            letter-spacing: -0.5px;
        }

        .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .nav-link:hover {
            color: white !important;
            background: rgba(255, 255, 255, 0.1);
        }

        .dashboard-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .dashboard-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .card-icon {
            width: 48px;
            height: 48px;
            background: var(--primary-color);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .welcome-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .welcome-header::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.05) 50%, transparent 50%);
        }

        .mobile-menu {
            display: none;
        }

        @media (max-width: 992px) {
            .navbar-nav {
                padding-top: 1rem;
            }

            .nav-link {
                margin: 0.25rem 0;
            }

            .mobile-menu {
                display: block;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: white;
                z-index: 1000;
                box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.1);
                padding: 0.5rem;
            }

            .mobile-menu .nav-link {
                color: var(--secondary-color) !important;
                text-align: center;
                font-size: 0.8rem;
                padding: 0.5rem !important;
            }

            .mobile-menu .nav-link i {
                display: block;
                font-size: 1.2rem;
                margin-bottom: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-tachometer-alt me-2"></i>PACE_DB ADMIN
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <?php foreach ($navItems as $label => $link): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $link ?>">
                                <i class="fas fa-arrow-right me-2"></i><?= $label ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="d-flex align-items-center">
                    <a href="export_db.php" class="btn btn-light btn-sm me-2">
                        <i class="fas fa-download"></i>
                        <span class="d-none d-md-inline">Export DB</span>
                    </a>
                    <a href="logout.php" class="btn btn-danger btn-sm">
                        <i class="fas fa-sign-out-alt"></i>
                        <span class="d-none d-md-inline">Logout</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container pt-5 mt-4">
        <div class="welcome-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-2">Welcome back, Admin</h1>
                    <p class="mb-0 opacity-75">Manage your PACE_DB system efficiently</p>
                </div>
                <i class="fas fa-user-shield fa-3x opacity-25"></i>
            </div>
        </div>

        <!-- Quick Access Grid -->
        <div class="row g-4">
            <?php
            $quickLinks = [
                ['Tasks', 'tasks', 'task.php', 'primary'],
                ['Clients', 'users', 'client.php', 'success'],
                ['Generate Sheets', 'chart-bar', 'visualization.php', 'info'],
                ['A-Client Payments', 'money-bill-wave', 'admin_client_payment.php', 'warning'],
                ['Team Leads', 'user-tie', 'teamlead.php', 'danger'],
                ['A-TL Payments', 'hand-holding-usd', 'admin_tl_payment.php', 'secondary'],
                ['Experts', 'user-graduate', 'expert.php', 'dark'],
                ['TL-Expert Payments', 'money-check-alt', 'tl_expert_payment.php', 'primary'],
                ['Colleges', 'university', 'colleges.php', 'success'],
            ];
            foreach ($quickLinks as $link):
            ?>
            <div class="col-12 col-md-6 col-xl-4">
                <a href="<?= $link[2] ?>" class="text-decoration-none">
                    <div class="dashboard-card p-3">
                        <div class="d-flex align-items-center">
                            <div class="card-icon bg-<?= $link[3] ?>">
                                <i class="fas fa-<?= $link[1] ?>"></i>
                            </div>
                            <div class="ms-3">
                                <h5 class="mb-0"><?= $link[0] ?></h5>
                                <small class="text-muted">Click to manage</small>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Mobile Bottom Menu -->
    <nav class="mobile-menu d-lg-none">
        <div class="row text-center">
            <?php $mobileLinks = array_slice($navItems, 0, 3); ?>
            <?php foreach ($mobileLinks as $label => $link): ?>
                <div class="col">
                    <a href="<?= $link ?>" class="nav-link">
                        <i class="fas fa-<?= strtolower(str_replace(' ', '-', $label)) ?>"></i>
                        <span><?= $label ?></span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </nav>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'footer.php'; ?>
</body>
</html>