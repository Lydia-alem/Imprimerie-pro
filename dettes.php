<?php
// Database configuration
define('DB_HOST', '127.0.0.1:3306');
define('DB_NAME', 'imprimerie');
define('DB_USER', 'root'); // Change this to your MySQL username
define('DB_PASS', 'admine'); // Change this to your MySQL password

// Start session for user authentication
session_start();

// Database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if user is logged in
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// Function to get all client debts
function getClientDebts($pdo) {
    $debts = [];
    
    // Get all clients with their unpaid invoices
    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.name,
            c.email,
            c.phone,
            COALESCE(SUM(i.total), 0) as total_invoiced,
            COALESCE(SUM(p.amount), 0) as total_paid,
            (COALESCE(SUM(i.total), 0) - COALESCE(SUM(p.amount), 0)) as balance_due,
            COUNT(DISTINCT i.id) as unpaid_invoice_count
        FROM clients c
        LEFT JOIN invoices i ON c.id = i.client_id 
        LEFT JOIN payments p ON i.id = p.invoice_id
        WHERE i.status != 'paid' OR i.status IS NULL
        GROUP BY c.id
        HAVING balance_due > 0
        ORDER BY balance_due DESC
    ");
    
    $stmt->execute();
    $debts = $stmt->fetchAll();
    
    return $debts;
}

// Function to get debt summary
function getDebtSummary($pdo) {
    $summary = [];
    
    // Total debt
    $stmt = $pdo->query("
        SELECT 
            COALESCE(SUM(i.total), 0) as total_invoiced,
            COALESCE(SUM(p.amount), 0) as total_paid,
            (COALESCE(SUM(i.total), 0) - COALESCE(SUM(p.amount), 0)) as total_debt
        FROM invoices i
        LEFT JOIN payments p ON i.id = p.invoice_id
        WHERE i.status != 'paid'
    ");
    $summary['total'] = $stmt->fetch();
    
    // Clients with debt count
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT c.id) as client_count
        FROM clients c
        JOIN invoices i ON c.id = i.client_id
        LEFT JOIN payments p ON i.id = p.invoice_id
        WHERE i.status != 'paid'
        GROUP BY c.id
        HAVING (COALESCE(SUM(i.total), 0) - COALESCE(SUM(p.amount), 0)) > 0
    ");
    $summary['clients_with_debt'] = $stmt->rowCount();
    
    // Average debt per client
    if ($summary['clients_with_debt'] > 0) {
        $summary['average_debt'] = $summary['total']['total_debt'] / $summary['clients_with_debt'];
    } else {
        $summary['average_debt'] = 0;
    }
    
    // Debt by status
    $stmt = $pdo->query("
        SELECT 
            i.status,
            COUNT(*) as invoice_count,
            SUM(i.total) as total_amount
        FROM invoices i
        WHERE i.status != 'paid'
        GROUP BY i.status
    ");
    $summary['by_status'] = $stmt->fetchAll();
    
    return $summary;
}

// Function to get overdue debts
function getOverdueDebts($pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            c.name as client_name,
            i.id as invoice_id,
            i.total,
            i.created_at as invoice_date,
            DATE_ADD(i.created_at, INTERVAL 30 DAY) as due_date,
            DATEDIFF(NOW(), DATE_ADD(i.created_at, INTERVAL 30 DAY)) as days_overdue,
            COALESCE(SUM(p.amount), 0) as paid_amount,
            (i.total - COALESCE(SUM(p.amount), 0)) as remaining_amount
        FROM invoices i
        JOIN clients c ON i.client_id = c.id
        LEFT JOIN payments p ON i.id = p.invoice_id
        WHERE i.status != 'paid'
        AND DATE_ADD(i.created_at, INTERVAL 30 DAY) < CURDATE()
        GROUP BY i.id
        HAVING remaining_amount > 0
        ORDER BY days_overdue DESC
    ");
    
    $stmt->execute();
    return $stmt->fetchAll();
}

// Get all data
if ($isLoggedIn) {
    $debts = getClientDebts($pdo);
    $summary = getDebtSummary($pdo);
    $overdueDebts = getOverdueDebts($pdo);
    $totalDebt = $summary['total']['total_debt'];
    $clientCount = $summary['clients_with_debt'];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimerie Admin - Gestion des Dettes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --gray: #95a5a6;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: var(--dark);
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Toggle Button for Mobile */
        .sidebar-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 5px;
            width: 40px;
            height: 40px;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: var(--shadow);
        }

        /* Updated Sidebar Styles */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, var(--primary) 0%, #1a252f 100%);
            color: white;
            transition: all 0.3s ease;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 25px 20px;
            background: rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }

        .sidebar-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--accent);
        }

        .sidebar-header img {
            width: 210px;
            height: 80px;
            object-fit: cover;
        }

        .sidebar-header h2 {
            font-size: 1.4rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            background: linear-gradient(90deg, white, #ecf0f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .sidebar-menu {
            padding: 20px 0;
            flex: 1;
        }

        .sidebar-menu ul {
            list-style: none;
            padding: 0 10px;
        }

        .sidebar-menu li {
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 500;
            width: 100%;
            transition: all 0.3s ease;
        }

        .sidebar-menu li:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateX(5px);
        }

        .sidebar-menu li:hover a {
            color: white;
        }

        .sidebar-menu li.active {
            background: linear-gradient(90deg, var(--secondary), #2980b9);
            box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
        }

        .sidebar-menu li.active a {
            color: white;
            font-weight: 600;
        }

        .sidebar-menu li.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: var(--accent);
        }

        .sidebar-menu i {
            width: 24px;
            font-size: 1.1rem;
            text-align: center;
            margin-right: 12px;
            transition: all 0.3s ease;
        }

        .sidebar-menu li:hover i {
            transform: scale(1.1);
        }

        .sidebar-menu li.active i {
            color: white;
        }

        /* Sidebar Footer */
        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .user-details h4 {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .user-details span {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.6);
        }

        /* Update main content for sidebar */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            margin-left: 250px;
            transition: margin-left 0.3s ease;
        }

        /* Header Styles */
        .header {
            background: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
        }

        .header-left h1 {
            font-size: 1.8rem;
            color: var(--primary);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .search-bar {
            position: relative;
        }

        .search-bar input {
            padding: 10px 15px 10px 40px;
            border: 1px solid var(--gray);
            border-radius: 30px;
            width: 300px;
            outline: none;
            transition: all 0.3s;
        }

        .search-bar input:focus {
            border-color: var(--secondary);
        }

        .search-bar i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .user-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* Content Styles */
        .content {
            padding: 30px;
            flex: 1;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-header h1 {
            font-size: 2rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-header h1 i {
            color: var(--accent);
        }

        .page-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: var(--secondary);
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #27ae60;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }

        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
        }

        .stat-info h3 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .total-debt .stat-icon { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .clients-debt .stat-icon { background: linear-gradient(135deg, #3498db, #2980b9); }
        .average-debt .stat-icon { background: linear-gradient(135deg, #f39c12, #d35400); }
        .overdue-debt .stat-icon { background: linear-gradient(135deg, #9b59b6, #8e44ad); }

        /* Tables */
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            overflow-x: auto;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h3 {
            font-size: 1.3rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(90deg, var(--primary), #1a252f);
            color: white;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        th:first-child {
            border-top-left-radius: 8px;
        }

        th:last-child {
            border-top-right-radius: 8px;
        }

        tbody tr {
            border-bottom: 1px solid #eee;
            transition: all 0.3s;
        }

        tbody tr:hover {
            background: #f8f9fa;
            transform: translateX(5px);
        }

        td {
            padding: 15px;
            font-size: 0.95rem;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-unpaid { background: #ffebee; color: #c62828; }
        .status-partial { background: #fff3e0; color: #ef6c00; }
        .status-overdue { background: #fce4ec; color: #ad1457; }

        .amount {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .client-info {
            display: flex;
            flex-direction: column;
        }

        .client-name {
            font-weight: 600;
            margin-bottom: 3px;
        }

        .client-email {
            font-size: 0.85rem;
            color: var(--gray);
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            width: 35px;
            height: 35px;
            border-radius: 6px;
            border: none;
            background: #f8f9fa;
            color: var(--dark);
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-btn:hover {
            background: var(--secondary);
            color: white;
            transform: scale(1.1);
        }

        /* No Data Message */
        .no-data {
            text-align: center;
            padding: 50px;
            color: var(--gray);
        }

        .no-data i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ddd;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
            font-size: 0.9rem;
        }

        .form-control {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
                transform: translateX(0);
            }
            
            .sidebar-header h2,
            .sidebar-menu span,
            .user-details {
                display: none;
            }
            
            .sidebar-header {
                padding: 20px 10px;
                justify-content: center;
            }
            
            .sidebar-header img {
                width: 50px;
                height: 50px;
            }
            
            .sidebar-menu li {
                padding: 0;
                text-align: center;
            }
            
            .sidebar-menu li a {
                padding: 15px 10px;
                justify-content: center;
            }
            
            .sidebar-menu i {
                margin-right: 0;
                font-size: 1.2rem;
            }
            
            .sidebar-menu li.active::before {
                width: 100%;
                height: 3px;
                top: auto;
                bottom: 0;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .sidebar-footer {
                padding: 15px 5px;
                text-align: center;
            }
            
            .user-avatar {
                width: 35px;
                height: 35px;
            }
            
            .search-bar input {
                width: 200px;
            }
            
            .stats-cards {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 250px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar-toggle {
                display: block;
            }
            
            .search-bar {
                display: none;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .page-actions {
                width: 100%;
                justify-content: flex-start;
            }
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 576px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                padding: 15px;
            }
            
            .header-left,
            .header-right {
                width: 100%;
            }
            
            .content {
                padding: 15px;
            }
            
            table {
                font-size: 0.85rem;
            }
            
            th, td {
                padding: 10px;
            }
        }

        /* Scrollbar Styling for Sidebar */
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body>
    <!-- Sidebar Toggle Button -->
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="REM.jpg" alt="Logo Imprimerie">
        </div>
        
        <!-- Sidebar Menu Section -->
        <div class="sidebar-menu">
            <ul>
                <li>
                    <a href="index.php">
                        <i class="fas fa-home"></i>
                        <span>Tableau de Bord</span>
                    </a>
                </li>
                <li>
                    <a href="probleme.php">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Problèmes Urgents</span>
                    </a>
                </li>
                <li>
                    <a href="commande.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Commandes</span>
                    </a>
                </li>
                <li>
                    <a href="devis.php">
                        <i class="fas fa-file-invoice"></i>
                        <span>Devis</span>
                    </a>
                </li>
                <li>
                    <a href="depenses.php">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Dépenses</span>
                    </a>
                </li>
                <li>
                    <a href="ajustestock.php">
                        <i class="fas fa-box"></i>
                        <span>Stock</span>
                    </a>
                </li>
                <li>
                    <a href="facture.php">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>Facturation</span>
                    </a>
                </li>
                <li>
                    <a href="employees.php">
                        <i class="fas fa-user-tie"></i>
                        <span>Employés</span>
                    </a>
                </li>
                <li class="active">
                    <a href="dettes.php">
                        <i class="fas fa-money-check-alt"></i>
                        <span>Dettes Clients</span>
                    </a>
                </li>
                <li>
                    <a href="gestion.php">
                        <i class="fas fa-cogs"></i>
                        <span>Gestion</span>
                    </a>
                </li>
                <li>
                    <a href="ventes.php">
                        <i class="fas fa-chart-line"></i>
                        <span>Ventes</span>
                    </a>
                </li>
                <li>
                    <a href="profile.php">
                        <i class="fas fa-user"></i>
                        <span>Mon Profil</span>
                    </a>
                </li>
                <li>
                    <a href="logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Déconnexion</span>
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <h1>Gestion des Dettes</h1>
                <small>Suivi des montants dus par les clients</small>
            </div>
            <div class="header-right">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Rechercher un client..." id="searchInput">
                </div>
                <div class="user-profile">
                    <img src="" alt="Admin">
                    <span>Admin</span>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <?php if ($isLoggedIn): ?>
                <!-- Page Header -->
                <div class="page-header">
                    <h1>
                        <i class="fas fa-money-check-alt"></i>
                        Dettes Clients
                    </h1>
                    
                </div>

                <!-- Stats Cards -->
                <div class="stats-cards">
                    <div class="stat-card total-debt">
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($totalDebt, 0, ',', ' '); ?> DA</h3>
                            <p>Dette Totale</p>
                        </div>
                    </div>
                    
                    <div class="stat-card clients-debt">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $clientCount; ?></h3>
                            <p>Clients avec Dette</p>
                        </div>
                    </div>
                    
                    <div class="stat-card average-debt">
                        <div class="stat-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($summary['average_debt'], 0, ',', ' '); ?> DA</h3>
                            <p>Dette Moyenne</p>
                        </div>
                    </div>
                    
                    <div class="stat-card overdue-debt">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo count($overdueDebts); ?></h3>
                            <p>Dettes Échues</p>
                        </div>
                    </div>
                </div>

                <!-- Filter Section -->
                <div class="filter-section">
                    <h3 style="margin-bottom: 20px; color: var(--primary);">
                        <i class="fas fa-filter"></i> Filtres
                    </h3>
                    <form class="filter-form" id="filterForm">
                        <div class="form-group">
                            <label for="status">Statut</label>
                            <select id="status" class="form-control">
                                <option value="all">Tous les statuts</option>
                                <option value="unpaid">Non payé</option>
                                <option value="partial">Partiellement payé</option>
                                <option value="overdue">Échu</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="minAmount">Montant minimum (DA)</label>
                            <input type="number" id="minAmount" class="form-control" placeholder="0">
                        </div>
                        
                        <div class="form-group">
                            <label for="maxAmount">Montant maximum (DA)</label>
                            <input type="number" id="maxAmount" class="form-control" placeholder="1000000">
                        </div>
                        
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-primary" onclick="applyFilters()">
                                <i class="fas fa-search"></i> Appliquer
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Clients with Debts Table -->
                <div class="table-container">
                    <div class="table-header">
                        <h3>
                            <i class="fas fa-list"></i>
                            Liste des Dettes Clients
                        </h3>
                        <div>
                            <span class="badge badge-warning">
                                <?php echo count($debts); ?> clients trouvés
                            </span>
                        </div>
                    </div>
                    
                    <?php if (count($debts) > 0): ?>
                        <table id="debtsTable">
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Factures impayées</th>
                                    <th>Total facturé</th>
                                    <th>Total payé</th>
                                    <th>Solde dû</th>
                                    <th>Statut</th>
                                    
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($debts as $debt): ?>
                                    <?php 
                                    $status = 'unpaid';
                                    if ($debt['balance_due'] < $debt['total_invoiced'] && $debt['balance_due'] > 0) {
                                        $status = 'partial';
                                    }
                                    
                                    // Check if any invoice is overdue
                                    $isOverdue = false;
                                    foreach ($overdueDebts as $overdue) {
                                        if ($overdue['client_name'] == $debt['name']) {
                                            $isOverdue = true;
                                            $status = 'overdue';
                                            break;
                                        }
                                    }
                                    ?>
                                    <tr class="debt-row" 
                                        data-status="<?php echo $status; ?>"
                                        data-amount="<?php echo $debt['balance_due']; ?>"
                                        data-client="<?php echo strtolower($debt['name']); ?>">
                                        <td>
                                            <div class="client-info">
                                                <span class="client-name"><?php echo htmlspecialchars($debt['name']); ?></span>
                                                <span class="client-email"><?php echo htmlspecialchars($debt['email']); ?></span>
                                                <small style="color: var(--gray);"><?php echo htmlspecialchars($debt['phone']); ?></small>
                                            </div>
                                        </td>
                                        <td><?php echo $debt['unpaid_invoice_count']; ?></td>
                                        <td class="amount"><?php echo number_format($debt['total_invoiced'], 0, ',', ' '); ?> DA</td>
                                        <td class="amount" style="color: var(--success);"><?php echo number_format($debt['total_paid'], 0, ',', ' '); ?> DA</td>
                                        <td class="amount" style="color: var(--danger); font-weight: 700;">
                                            <?php echo number_format($debt['balance_due'], 0, ',', ' '); ?> DA
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $status; ?>">
                                                <?php 
                                                if ($status == 'unpaid') echo 'Non payé';
                                                elseif ($status == 'partial') echo 'Partiel';
                                                else echo 'Échu';
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            
                                           
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fas fa-smile-beam"></i>
                            <h3>Aucune dette trouvée</h3>
                            <p>Tous vos clients sont à jour dans leurs paiements!</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Overdue Debts Table -->
                <?php if (count($overdueDebts) > 0): ?>
                    <div class="table-container">
                        <div class="table-header">
                            <h3 style="color: var(--danger);">
                                <i class="fas fa-exclamation-triangle"></i>
                                Dettes Échues (Retard)
                            </h3>
                            <div>
                                <span class="badge badge-danger">
                                    <?php echo count($overdueDebts); ?> factures échues
                                </span>
                            </div>
                        </div>
                        
                        <table>
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Facture #</th>
                                    <th>Date échéance</th>
                                    <th>Jours de retard</th>
                                    <th>Montant total</th>
                                    <th>Montant payé</th>
                                    <th>Solde restant</th>
                                    <th>Priorité</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($overdueDebts as $overdue): ?>
                                    <?php 
                                    $priority = 'Moyenne';
                                    $priorityColor = 'warning';
                                    if ($overdue['days_overdue'] > 60) {
                                        $priority = 'Haute';
                                        $priorityColor = 'danger';
                                    } elseif ($overdue['days_overdue'] > 30) {
                                        $priority = 'Moyenne';
                                        $priorityColor = 'warning';
                                    } else {
                                        $priority = 'Basse';
                                        $priorityColor = 'info';
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($overdue['client_name']); ?></td>
                                        <td>FAC-<?php echo str_pad($overdue['invoice_id'], 5, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($overdue['due_date'])); ?></td>
                                        <td>
                                            <span style="color: var(--danger); font-weight: 600;">
                                                <?php echo $overdue['days_overdue']; ?> jours
                                            </span>
                                        </td>
                                        <td class="amount"><?php echo number_format($overdue['total'], 0, ',', ' '); ?> DA</td>
                                        <td class="amount" style="color: var(--success);"><?php echo number_format($overdue['paid_amount'], 0, ',', ' '); ?> DA</td>
                                        <td class="amount" style="color: var(--danger); font-weight: 700;">
                                            <?php echo number_format($overdue['remaining_amount'], 0, ',', ' '); ?> DA
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php echo $priorityColor; ?>">
                                                <?php echo $priority; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- Debt Summary by Status -->
                <?php if (!empty($summary['by_status'])): ?>
                    <div class="table-container">
                        <div class="table-header">
                            <h3>
                                <i class="fas fa-chart-pie"></i>
                                Répartition des Dettes par Statut
                            </h3>
                        </div>
                        
                        <table>
                            <thead>
                                <tr>
                                    <th>Statut</th>
                                    <th>Nombre de factures</th>
                                    <th>Montant total</th>
                                    <th>Pourcentage</th>
                                    <th>Tendance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $totalAmount = $summary['total']['total_invoiced'];
                                foreach ($summary['by_status'] as $status): 
                                    $percentage = $totalAmount > 0 ? ($status['total_amount'] / $totalAmount) * 100 : 0;
                                ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            if ($status['status'] == 'unpaid') echo 'Non payé';
                                            elseif ($status['status'] == 'partial') echo 'Partiellement payé';
                                            else echo ucfirst($status['status']);
                                            ?>
                                        </td>
                                        <td><?php echo $status['invoice_count']; ?></td>
                                        <td class="amount"><?php echo number_format($status['total_amount'], 0, ',', ' '); ?> DA</td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <div style="flex: 1; background: #eee; height: 10px; border-radius: 5px;">
                                                    <div style="width: <?php echo $percentage; ?>%; background: var(--secondary); height: 100%; border-radius: 5px;"></div>
                                                </div>
                                                <span style="min-width: 50px;"><?php echo number_format($percentage, 1); ?>%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($percentage > 50): ?>
                                                <span style="color: var(--danger);">
                                                    <i class="fas fa-arrow-up"></i> Élevé
                                                </span>
                                            <?php elseif ($percentage > 20): ?>
                                                <span style="color: var(--warning);">
                                                    <i class="fas fa-minus"></i> Moyen
                                                </span>
                                            <?php else: ?>
                                                <span style="color: var(--success);">
                                                    <i class="fas fa-arrow-down"></i> Bas
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <!-- Not logged in message -->
                <div style="text-align: center; padding: 100px;">
                    <div style="background: white; padding: 40px; border-radius: 15px; box-shadow: var(--shadow); max-width: 500px; margin: 0 auto;">
                        <div style="font-size: 4rem; color: var(--accent); margin-bottom: 20px;">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h2 style="color: var(--primary); margin-bottom: 15px;">Accès Restreint</h2>
                        <p style="color: var(--gray); margin-bottom: 30px;">Veuillez vous connecter pour accéder à la gestion des dettes</p>
                        <button onclick="location.href='index.php'" style="
                            background: var(--secondary);
                            color: white;
                            border: none;
                            padding: 12px 30px;
                            border-radius: 8px;
                            font-size: 1rem;
                            font-weight: 600;
                            cursor: pointer;
                            transition: all 0.3s;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            gap: 10px;
                            margin: 0 auto;
                        ">
                            <i class="fas fa-sign-in-alt"></i>
                            Se connecter
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Sidebar toggle function
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.debt-row');
            
            rows.forEach(row => {
                const clientName = row.getAttribute('data-client');
                if (clientName.includes(searchTerm) || searchTerm === '') {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Filter functionality
        function applyFilters() {
            const statusFilter = document.getElementById('status').value;
            const minAmount = parseFloat(document.getElementById('minAmount').value) || 0;
            const maxAmount = parseFloat(document.getElementById('maxAmount').value) || Infinity;
            
            const rows = document.querySelectorAll('.debt-row');
            
            rows.forEach(row => {
                const status = row.getAttribute('data-status');
                const amount = parseFloat(row.getAttribute('data-amount'));
                
                const statusMatch = statusFilter === 'all' || status === statusFilter;
                const amountMatch = amount >= minAmount && amount <= maxAmount;
                
                if (statusMatch && amountMatch) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Action functions
        function viewClientDetails(clientId) {
            alert('Voir les détails du client ID: ' + clientId);
            // Implement modal or redirect to client details page
        }

        function recordPayment(clientId) {
            const amount = prompt('Entrez le montant du paiement (DA):');
            if (amount && !isNaN(amount) && parseFloat(amount) > 0) {
                if (confirm(`Enregistrer un paiement de ${amount} DA?`)) {
                    // AJAX call to record payment
                    fetch('record_payment.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            clientId: clientId,
                            amount: amount,
                            date: new Date().toISOString().split('T')[0]
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Paiement enregistré avec succès!');
                            location.reload();
                        } else {
                            alert('Erreur: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Erreur lors de l\'enregistrement du paiement');
                    });
                }
            }
        }

        function contactClient(clientId) {
            alert('Contacter le client ID: ' + clientId);
            // Implement contact functionality
        }

        function viewHistory(clientId) {
            window.open('client_history.php?id=' + clientId, '_blank');
        }

        function printDebtReport() {
            window.print();
        }

        function sendPaymentReminders() {
            if (confirm('Envoyer des rappels de paiement à tous les clients avec des dettes?')) {
                fetch('send_reminders.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message || 'Rappels envoyés avec succès!');
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Erreur lors de l\'envoi des rappels');
                });
            }
        }

        function exportToExcel() {
            // Create a simple CSV export
            let csvContent = "data:text/csv;charset=utf-8,";
            csvContent += "Client,Email,Téléphone,Factures impayées,Total facturé,Total payé,Solde dû,Statut\n";
            
            document.querySelectorAll('.debt-row').forEach(row => {
                const cells = row.querySelectorAll('td');
                const clientInfo = row.querySelector('.client-info');
                const clientName = clientInfo.querySelector('.client-name').textContent;
                const clientEmail = clientInfo.querySelector('.client-email').textContent;
                const clientPhone = clientInfo.querySelector('small').textContent;
                const unpaidInvoices = cells[1].textContent;
                const totalInvoiced = cells[2].textContent.replace(' DA', '');
                const totalPaid = cells[3].textContent.replace(' DA', '');
                const balanceDue = cells[4].textContent.replace(' DA', '');
                const status = cells[5].querySelector('.status-badge').textContent;
                
                csvContent += `"${clientName}","${clientEmail}","${clientPhone}","${unpaidInvoices}","${totalInvoiced}","${totalPaid}","${balanceDue}","${status}"\n`;
            });
            
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "dettes_clients_" + new Date().toISOString().split('T')[0] + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Sort table by column
        function sortTable(columnIndex) {
            const table = document.getElementById('debtsTable');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            const isAscending = table.getAttribute('data-sort-dir') !== 'asc';
            
            rows.sort((a, b) => {
                let aValue = a.cells[columnIndex].textContent;
                let bValue = b.cells[columnIndex].textContent;
                
                // Handle numeric values
                if (columnIndex === 1 || columnIndex === 2 || columnIndex === 3 || columnIndex === 4) {
                    aValue = parseFloat(aValue.replace(/[^\d.]/g, ''));
                    bValue = parseFloat(bValue.replace(/[^\d.]/g, ''));
                }
                
                if (isAscending) {
                    return aValue > bValue ? 1 : -1;
                } else {
                    return aValue < bValue ? 1 : -1;
                }
            });
            
            // Clear and re-add sorted rows
            rows.forEach(row => tbody.appendChild(row));
            
            // Update sort direction
            table.setAttribute('data-sort-dir', isAscending ? 'asc' : 'desc');
        }

        // Add click handlers to table headers
        document.querySelectorAll('#debtsTable th').forEach((th, index) => {
            th.style.cursor = 'pointer';
            th.addEventListener('click', () => sortTable(index));
        });

        // Auto-hide sidebar on mobile when clicking outside
        if (window.innerWidth <= 768) {
            document.addEventListener('click', function(e) {
                const sidebar = document.getElementById('sidebar');
                const isClickInsideSidebar = sidebar.contains(e.target);
                const isClickOnToggle = e.target.closest('.sidebar-toggle');
                
                if (!isClickInsideSidebar && !isClickOnToggle) {
                    sidebar.classList.remove('active');
                }
            });
        }

        // Add keyboard shortcut for sidebar toggle (Ctrl+B)
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'b') {
                e.preventDefault();
                toggleSidebar();
            }
        });

        // Add animation to stats cards
        document.addEventListener('DOMContentLoaded', function() {
            const statsCards = document.querySelectorAll('.stat-card');
            statsCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 300 + (index * 100));
            });
        });
    </script>
</body>
</html>