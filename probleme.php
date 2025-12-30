<?php
// Database configuration
define('DB_HOST', '127.0.0.1:3306');
define('DB_NAME', 'imprimerie');
define('DB_USER', 'root');
define('DB_PASS', 'admine');

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

// Get urgent issues
function getUrgentIssues($pdo) {
    $issues = [
        'commandes_urgentes' => [],
        'commandes_en_retard' => [],
        'factures_impayees' => [],
        'stocks_critiques' => [],
        'devis_attente' => [],
        'tous_les_problemes' => []
    ];
    
    // 1. Commandes avec échéance dans ≤ 3 jours
    $threeDaysLater = date('Y-m-d', strtotime('+3 days'));
    $stmt = $pdo->prepare("
        SELECT o.*, c.name as client_name 
        FROM orders o
        LEFT JOIN clients c ON o.client_id = c.id
        WHERE deadline <= ? 
        AND status IN ('pending', 'in_production')
        AND deadline IS NOT NULL
        ORDER BY deadline ASC
    ");
    $stmt->execute([$threeDaysLater]);
    $issues['commandes_urgentes'] = $stmt->fetchAll();
    
    // 2. Commandes en retard (échéance passée)
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT o.*, c.name as client_name 
        FROM orders o
        LEFT JOIN clients c ON o.client_id = c.id
        WHERE deadline < ? 
        AND status IN ('pending', 'in_production')
        AND deadline IS NOT NULL
        ORDER BY deadline ASC
    ");
    $stmt->execute([$today]);
    $issues['commandes_en_retard'] = $stmt->fetchAll();
    
    // 3. Factures impayées
    $stmt = $pdo->prepare("
        SELECT i.*, c.name as client_name,
               DATEDIFF(CURDATE(), i.created_at) as jours_impayes
        FROM invoices i
        LEFT JOIN clients c ON i.client_id = c.id
        WHERE i.status IN ('unpaid', 'partial')
        ORDER BY i.created_at ASC
    ");
    $stmt->execute();
    $issues['factures_impayees'] = $stmt->fetchAll();
    
    // 4. Stocks critiques
    $stmt = $pdo->query("
        SELECT s.*, 
               (s.low_stock_limit - s.quantity) as deficit
        FROM stock s
        WHERE s.quantity <= s.low_stock_limit
        ORDER BY s.quantity ASC
    ");
    $issues['stocks_critiques'] = $stmt->fetchAll();
    
    // 5. Devis en attente depuis plus de 7 jours
    $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
    $stmt = $pdo->prepare("
        SELECT q.*, c.name as client_name,
               DATEDIFF(CURDATE(), q.created_at) as jours_attente
        FROM quotes q
        LEFT JOIN clients c ON q.client_id = c.id
        WHERE q.status = 'pending'
        AND q.created_at <= ?
        ORDER BY q.created_at ASC
    ");
    $stmt->execute([$sevenDaysAgo]);
    $issues['devis_attente'] = $stmt->fetchAll();
    
    // Combine all issues for summary
    $issues['tous_les_problemes'] = array_merge(
        array_map(function($item) { 
            $item['type'] = 'commande_urgente'; 
            $item['priorite'] = 'haute';
            return $item; 
        }, $issues['commandes_urgentes']),
        array_map(function($item) { 
            $item['type'] = 'commande_retard'; 
            $item['priorite'] = 'critique';
            return $item; 
        }, $issues['commandes_en_retard']),
        array_map(function($item) { 
            $item['type'] = 'facture_impayee'; 
            $item['priorite'] = 'moyenne';
            return $item; 
        }, $issues['factures_impayees']),
        array_map(function($item) { 
            $item['type'] = 'stock_critique'; 
            $item['priorite'] = 'haute';
            return $item; 
        }, $issues['stocks_critiques']),
        array_map(function($item) { 
            $item['type'] = 'devis_attente'; 
            $item['priorite'] = 'basse';
            return $item; 
        }, $issues['devis_attente'])
    );
    
    return $issues;
}

// Get issue priority text
function getPriorityText($priority) {
    $priorityMap = [
        'critique' => 'Critique',
        'haute' => 'Haute',
        'moyenne' => 'Moyenne',
        'basse' => 'Basse'
    ];
    
    return $priorityMap[$priority] ?? $priority;
}

// Get issue type text
function getIssueTypeText($type) {
    $typeMap = [
        'commande_urgente' => 'Commande urgente',
        'commande_retard' => 'Commande en retard',
        'facture_impayee' => 'Facture impayée',
        'stock_critique' => 'Stock critique',
        'devis_attente' => 'Devis en attente'
    ];
    
    return $typeMap[$type] ?? $type;
}

// Get issue icon
function getIssueIcon($type) {
    $iconMap = [
        'commande_urgente' => 'fas fa-clock',
        'commande_retard' => 'fas fa-exclamation-triangle',
        'facture_impayee' => 'fas fa-money-bill-wave',
        'stock_critique' => 'fas fa-box',
        'devis_attente' => 'fas fa-file-invoice'
    ];
    
    return $iconMap[$type] ?? 'fas fa-exclamation-circle';
}

// Get issue color
function getIssueColor($type) {
    $colorMap = [
        'commande_urgente' => '#f39c12',
        'commande_retard' => '#e74c3c',
        'facture_impayee' => '#3498db',
        'stock_critique' => '#9b59b6',
        'devis_attente' => '#95a5a6'
    ];
    
    return $colorMap[$type] ?? '#34495e';
}

// Get priority color
function getPriorityColor($priority) {
    $colorMap = [
        'critique' => '#e74c3c',
        'haute' => '#f39c12',
        'moyenne' => '#3498db',
        'basse' => '#95a5a6'
    ];
    
    return $colorMap[$priority] ?? '#34495e';
}

// Calculate total issues count
function calculateTotalIssues($issues) {
    $total = 0;
    foreach ($issues as $key => $issueList) {
        if ($key !== 'tous_les_problemes') {
            $total += count($issueList);
        }
    }
    return $total;
}

// Get all data
if ($isLoggedIn) {
    $issues = getUrgentIssues($pdo);
    $totalIssues = calculateTotalIssues($issues);
    $criticalIssues = count($issues['commandes_en_retard']) + count($issues['stocks_critiques']);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Problèmes Urgents - Imprimerie Admin</title>
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

        /* Sidebar Styles */
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
            background: linear-gradient(90deg, var(--accent), #c0392b);
            box-shadow: 0 4px 12px rgba(231, 76, 60, 0.3);
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
            background: var(--warning);
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

        /* Main Content */
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

        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }

        .summary-card-icon {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
        }

        .summary-card-info h3 {
            font-size: 2rem;
            margin-bottom: 5px;
        }

        .summary-card-info p {
            color: var(--gray);
            font-size: 0.9rem;
            font-weight: 500;
        }

        .summary-card-total .summary-card-icon { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .summary-card-critical .summary-card-icon { background: linear-gradient(135deg, #f39c12, #d35400); }
        .summary-card-urgent .summary-card-icon { background: linear-gradient(135deg, #3498db, #2980b9); }
        .summary-card-warning .summary-card-icon { background: linear-gradient(135deg, #9b59b6, #8e44ad); }

        /* Issues Container */
        .issues-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .issues-section {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .issues-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .issues-header h3 {
            font-size: 1.2rem;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .issues-count {
            background: var(--accent);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .issues-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .issue-item {
            padding: 15px 20px;
            border-bottom: 1px solid #f5f5f5;
            transition: all 0.3s ease;
        }

        .issue-item:hover {
            background: #f8f9fa;
        }

        .issue-item:last-child {
            border-bottom: none;
        }

        .issue-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .issue-type {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .issue-type i {
            font-size: 0.9rem;
        }

        .issue-priority {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
        }

        .issue-details {
            margin-top: 10px;
        }

        .issue-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }

        .issue-info {
            display: flex;
            justify-content: space-between;
            font-size: 0.85rem;
            color: var(--gray);
            margin-top: 5px;
        }

        .issue-date {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .issue-actions {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--secondary);
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background: #d68910;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        /* All Issues Table */
        .all-issues-table {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: var(--shadow);
            margin-top: 30px;
        }

        .all-issues-table h3 {
            font-size: 1.3rem;
            margin-bottom: 20px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            color: var(--gray);
            font-weight: 500;
            font-size: 0.9rem;
        }

        .issue-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .issue-cell i {
            font-size: 1rem;
        }

        .priority-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            text-align: center;
            min-width: 70px;
        }

        .priority-critique { background: var(--danger); }
        .priority-haute { background: var(--warning); }
        .priority-moyenne { background: var(--secondary); }
        .priority-basse { background: var(--gray); }

        /* Scrollbar Styling */
        .sidebar::-webkit-scrollbar,
        .issues-list::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar::-webkit-scrollbar-track,
        .issues-list::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        .sidebar::-webkit-scrollbar-thumb,
        .issues-list::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover,
        .issues-list::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar-header h2,
            .sidebar-menu span {
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
            
            .main-content {
                margin-left: 70px;
            }
            
            .issues-container {
                grid-template-columns: 1fr;
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
            
            .summary-cards {
                grid-template-columns: 1fr 1fr;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
        }

        @media (max-width: 576px) {
            .summary-cards {
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
                    <a href="dashboard.php">
                        <i class="fas fa-home"></i>
                        <span>Tableau de Bord</span>
                    </a>
                </li>
                <li class="active">
                    <a href="#">
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
                <h1>Problèmes Urgents</h1>
                <small>Détection automatique des problèmes nécessitant une attention immédiate</small>
            </div>
            <div class="header-right">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Rechercher un problème...">
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
                <!-- Summary Cards -->
                <div class="summary-cards">
                    <div class="summary-card summary-card-total" onclick="scrollToSection('all-issues')">
                        <div class="summary-card-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="summary-card-info">
                            <h3><?php echo $totalIssues; ?></h3>
                            <p>Total des problèmes</p>
                        </div>
                    </div>

                    <div class="summary-card summary-card-critical" onclick="scrollToSection('commandes-retard')">
                        <div class="summary-card-icon">
                            <i class="fas fa-skull-crossbones"></i>
                        </div>
                        <div class="summary-card-info">
                            <h3><?php echo $criticalIssues; ?></h3>
                            <p>Problèmes critiques</p>
                        </div>
                    </div>

                    <div class="summary-card summary-card-urgent" onclick="scrollToSection('commandes-urgentes')">
                        <div class="summary-card-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="summary-card-info">
                            <h3><?php echo count($issues['commandes_urgentes']); ?></h3>
                            <p>Commandes urgentes</p>
                        </div>
                    </div>

                    <div class="summary-card summary-card-warning" onclick="scrollToSection('factures-impayees')">
                        <div class="summary-card-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="summary-card-info">
                            <h3><?php echo count($issues['factures_impayees']); ?></h3>
                            <p>Factures impayées</p>
                        </div>
                    </div>
                </div>

                <!-- Issues Container -->
                <div class="issues-container">
                    <!-- Commandes en retard -->
                    <div class="issues-section" id="commandes-retard">
                        <div class="issues-header">
                            <h3>
                                <i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i>
                                Commandes en retard
                            </h3>
                            <span class="issues-count" style="background: var(--danger);">
                                <?php echo count($issues['commandes_en_retard']); ?>
                            </span>
                        </div>
                        <div class="issues-list">
                            <?php if (count($issues['commandes_en_retard']) > 0): ?>
                                <?php foreach ($issues['commandes_en_retard'] as $order): ?>
                                    <?php 
                                    $daysLate = date_diff(date_create($order['deadline']), date_create(date('Y-m-d')))->days;
                                    ?>
                                    <div class="issue-item">
                                        <div class="issue-header">
                                            <div class="issue-type" style="color: var(--danger);">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                Commande en retard
                                            </div>
                                            <span class="issue-priority" style="background: var(--danger);">
                                                Critique
                                            </span>
                                        </div>
                                        <div class="issue-details">
                                            <div class="issue-title">
                                                Commande #IMP-<?php echo $order['id']; ?> - <?php echo htmlspecialchars($order['client_name']); ?>
                                            </div>
                                            <div class="issue-info">
                                                <span>Échéance: <?php echo date('d/m/Y', strtotime($order['deadline'])); ?></span>
                                                <span class="issue-date">
                                                    <i class="far fa-calendar-times"></i> En retard de <?php echo $daysLate; ?> jour(s)
                                                </span>
                                            </div>
                                        </div>
                                        <div class="issue-actions">
                                            <button class="btn btn-primary" onclick="viewOrder(<?php echo $order['id']; ?>)">
                                                <i class="fas fa-eye"></i> Voir
                                            </button>
                                            <button class="btn btn-warning" onclick="updateOrderStatus(<?php echo $order['id']; ?>)">
                                                <i class="fas fa-edit"></i> Mettre à jour
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="issue-item" style="text-align: center; padding: 30px; color: var(--success);">
                                    <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                    <p>Aucune commande en retard</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Commandes urgentes -->
                    <div class="issues-section" id="commandes-urgentes">
                        <div class="issues-header">
                            <h3>
                                <i class="fas fa-clock" style="color: var(--warning);"></i>
                                Commandes urgentes
                            </h3>
                            <span class="issues-count" style="background: var(--warning);">
                                <?php echo count($issues['commandes_urgentes']); ?>
                            </span>
                        </div>
                        <div class="issues-list">
                            <?php if (count($issues['commandes_urgentes']) > 0): ?>
                                <?php foreach ($issues['commandes_urgentes'] as $order): ?>
                                    <?php 
                                    $daysLeft = date_diff(date_create(date('Y-m-d')), date_create($order['deadline']))->days;
                                    ?>
                                    <div class="issue-item">
                                        <div class="issue-header">
                                            <div class="issue-type" style="color: var(--warning);">
                                                <i class="fas fa-clock"></i>
                                                Échéance proche
                                            </div>
                                            <span class="issue-priority" style="background: var(--warning);">
                                                Haute
                                            </span>
                                        </div>
                                        <div class="issue-details">
                                            <div class="issue-title">
                                                Commande #IMP-<?php echo $order['id']; ?> - <?php echo htmlspecialchars($order['client_name']); ?>
                                            </div>
                                            <div class="issue-info">
                                                <span>Échéance: <?php echo date('d/m/Y', strtotime($order['deadline'])); ?></span>
                                                <span class="issue-date">
                                                    <i class="far fa-clock"></i> <?php echo $daysLeft; ?> jour(s) restant(s)
                                                </span>
                                            </div>
                                        </div>
                                        <div class="issue-actions">
                                            <button class="btn btn-primary" onclick="viewOrder(<?php echo $order['id']; ?>)">
                                                <i class="fas fa-eye"></i> Voir
                                            </button>
                                            <button class="btn btn-warning" onclick="updateOrderStatus(<?php echo $order['id']; ?>)">
                                                <i class="fas fa-bolt"></i> Accélérer
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="issue-item" style="text-align: center; padding: 30px; color: var(--success);">
                                    <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                    <p>Aucune commande urgente</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="issues-container">
                    <!-- Factures impayées -->
                    <div class="issues-section" id="factures-impayees">
                        <div class="issues-header">
                            <h3>
                                <i class="fas fa-money-bill-wave" style="color: var(--secondary);"></i>
                                Factures impayées
                            </h3>
                            <span class="issues-count" style="background: var(--secondary);">
                                <?php echo count($issues['factures_impayees']); ?>
                            </span>
                        </div>
                        <div class="issues-list">
                            <?php if (count($issues['factures_impayees']) > 0): ?>
                                <?php foreach ($issues['factures_impayees'] as $invoice): ?>
                                    <div class="issue-item">
                                        <div class="issue-header">
                                            <div class="issue-type" style="color: var(--secondary);">
                                                <i class="fas fa-money-bill-wave"></i>
                                                Facture impayée
                                            </div>
                                            <span class="issue-priority" style="background: var(--secondary);">
                                                Moyenne
                                            </span>
                                        </div>
                                        <div class="issue-details">
                                            <div class="issue-title">
                                                Facture #<?php echo $invoice['id']; ?> - <?php echo htmlspecialchars($invoice['client_name']); ?>
                                            </div>
                                            <div class="issue-info">
                                                <span>Montant: <?php echo number_format($invoice['total'], 2, ',', ' '); ?> DA</span>
                                                <span class="issue-date">
                                                    <i class="far fa-calendar"></i> <?php echo $invoice['jours_impayes']; ?> jour(s) impayé(s)
                                                </span>
                                            </div>
                                            <div class="issue-info">
                                                <span>Statut: 
                                                    <span style="color: <?php echo $invoice['status'] == 'partial' ? '#f39c12' : '#e74c3c'; ?>; font-weight: 600;">
                                                        <?php echo $invoice['status'] == 'partial' ? 'Partiel' : 'Non payé'; ?>
                                                    </span>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="issue-actions">
                                            <button class="btn btn-primary" onclick="viewInvoice(<?php echo $invoice['id']; ?>)">
                                                <i class="fas fa-eye"></i> Voir
                                            </button>
                                            <button class="btn btn-success" onclick="addPayment(<?php echo $invoice['id']; ?>)">
                                                <i class="fas fa-money-check"></i> Payer
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="issue-item" style="text-align: center; padding: 30px; color: var(--success);">
                                    <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                    <p>Aucune facture impayée</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Stocks critiques -->
                    <div class="issues-section" id="stocks-critiques">
                        <div class="issues-header">
                            <h3>
                                <i class="fas fa-box" style="color: #9b59b6;"></i>
                                Stocks critiques
                            </h3>
                            <span class="issues-count" style="background: #9b59b6;">
                                <?php echo count($issues['stocks_critiques']); ?>
                            </span>
                        </div>
                        <div class="issues-list">
                            <?php if (count($issues['stocks_critiques']) > 0): ?>
                                <?php foreach ($issues['stocks_critiques'] as $stock): ?>
                                    <div class="issue-item">
                                        <div class="issue-header">
                                            <div class="issue-type" style="color: #9b59b6;">
                                                <i class="fas fa-box"></i>
                                                Stock critique
                                            </div>
                                            <span class="issue-priority" style="background: #9b59b6;">
                                                Haute
                                            </span>
                                        </div>
                                        <div class="issue-details">
                                            <div class="issue-title">
                                                <?php echo htmlspecialchars($stock['item_name']); ?>
                                            </div>
                                            <div class="issue-info">
                                                <span>Quantité: <?php echo $stock['quantity']; ?> <?php echo $stock['unit']; ?></span>
                                                <span>Limite: <?php echo $stock['low_stock_limit']; ?> <?php echo $stock['unit']; ?></span>
                                            </div>
                                            <div class="issue-info">
                                                <span>Déficit: 
                                                    <span style="color: var(--danger); font-weight: 600;">
                                                        <?php echo $stock['deficit']; ?> <?php echo $stock['unit']; ?>
                                                    </span>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="issue-actions">
                                            <button class="btn btn-primary" onclick="viewStock(<?php echo $stock['id']; ?>)">
                                                <i class="fas fa-eye"></i> Voir
                                            </button>
                                            <button class="btn btn-warning" onclick="replenishStock(<?php echo $stock['id']; ?>)">
                                                <i class="fas fa-plus-circle"></i> Réapprovisionner
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="issue-item" style="text-align: center; padding: 30px; color: var(--success);">
                                    <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                    <p>Aucun stock critique</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- All Issues Table -->
                <div class="all-issues-table" id="all-issues">
                    <h3>
                        <i class="fas fa-list"></i>
                        Tous les problèmes (<?php echo count($issues['tous_les_problemes']); ?>)
                    </h3>
                    <?php if (count($issues['tous_les_problemes']) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Priorité</th>
                                    <th>Date</th>
                                    <th>Client</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($issues['tous_les_problemes'] as $issue): ?>
                                    <tr>
                                        <td>
                                            <div class="issue-cell" style="color: <?php echo getIssueColor($issue['type']); ?>;">
                                                <i class="<?php echo getIssueIcon($issue['type']); ?>"></i>
                                                <?php echo getIssueTypeText($issue['type']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($issue['type'] == 'commande_urgente' || $issue['type'] == 'commande_retard'): ?>
                                                Commande #IMP-<?php echo $issue['id']; ?> - <?php echo htmlspecialchars($issue['client_name']); ?>
                                            <?php elseif ($issue['type'] == 'facture_impayee'): ?>
                                                Facture #<?php echo $issue['id']; ?> - <?php echo number_format($issue['total'], 2, ',', ' '); ?> DA
                                            <?php elseif ($issue['type'] == 'stock_critique'): ?>
                                                <?php echo htmlspecialchars($issue['item_name']); ?> - <?php echo $issue['quantity']; ?>/<?php echo $issue['low_stock_limit']; ?> <?php echo $issue['unit']; ?>
                                            <?php elseif ($issue['type'] == 'devis_attente'): ?>
                                                Devis #<?php echo $issue['id']; ?> - <?php echo htmlspecialchars($issue['client_name']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="priority-badge priority-<?php echo $issue['priorite']; ?>">
                                                <?php echo getPriorityText($issue['priorite']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($issue['type'] == 'commande_urgente' || $issue['type'] == 'commande_retard') {
                                                echo date('d/m/Y', strtotime($issue['deadline']));
                                            } elseif (isset($issue['created_at'])) {
                                                echo date('d/m/Y', strtotime($issue['created_at']));
                                            } elseif (isset($issue['updated_at'])) {
                                                echo date('d/m/Y', strtotime($issue['updated_at']));
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($issue['client_name'] ?? 'N/A'); ?>
                                        </td>
                                        <td>
                                            <?php if ($issue['type'] == 'commande_urgente' || $issue['type'] == 'commande_retard'): ?>
                                                <button class="btn btn-primary" onclick="viewOrder(<?php echo $issue['id']; ?>)" style="padding: 5px 10px; font-size: 0.8rem;">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            <?php elseif ($issue['type'] == 'facture_impayee'): ?>
                                                <button class="btn btn-primary" onclick="viewInvoice(<?php echo $issue['id']; ?>)" style="padding: 5px 10px; font-size: 0.8rem;">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            <?php elseif ($issue['type'] == 'stock_critique'): ?>
                                                <button class="btn btn-primary" onclick="viewStock(<?php echo $issue['id']; ?>)" style="padding: 5px 10px; font-size: 0.8rem;">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 50px; color: var(--success);">
                            <i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 15px;"></i>
                            <h4 style="color: var(--success);">Félicitations !</h4>
                            <p>Aucun problème détecté dans le système</p>
                        </div>
                    <?php endif; ?>
                </div>

            <?php else: ?>
                <!-- Not logged in message -->
                <div style="text-align: center; padding: 100px;">
                    <div style="background: white; padding: 40px; border-radius: 15px; box-shadow: var(--shadow); max-width: 500px; margin: 0 auto;">
                        <div style="font-size: 4rem; color: var(--accent); margin-bottom: 20px;">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h2 style="color: var(--primary); margin-bottom: 15px;">Accès Restreint</h2>
                        <p style="color: var(--gray); margin-bottom: 30px;">Veuillez vous connecter pour accéder à cette page</p>
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

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.sidebar-toggle');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(e.target) && e.target !== toggleBtn && !toggleBtn.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });

        // Close sidebar after clicking a link on mobile
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    document.getElementById('sidebar').classList.remove('active');
                }
            });
        });

        // Scroll to section
        function scrollToSection(sectionId) {
            const element = document.getElementById(sectionId);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth' });
            }
        }

        // View order details
        function viewOrder(orderId) {
            window.location.href = `commande.php?view=${orderId}`;
        }

        // Update order status
        function updateOrderStatus(orderId) {
            if (confirm('Mettre à jour le statut de cette commande ?')) {
                // This would normally redirect to an update page or make an AJAX call
                window.location.href = `commande.php?edit=${orderId}`;
            }
        }

        // View invoice
        function viewInvoice(invoiceId) {
            window.location.href = `facture.php?view=${invoiceId}`;
        }

        // Add payment to invoice
        function addPayment(invoiceId) {
            window.location.href = `facture.php?pay=${invoiceId}`;
        }

        // View stock
        function viewStock(stockId) {
            window.location.href = `ajustestock.php?view=${stockId}`;
        }

        // Replenish stock
        function replenishStock(stockId) {
            window.location.href = `ajustestock.php?add=${stockId}`;
        }

        // Auto-refresh issues every 5 minutes
        setInterval(function() {
            if (confirm('Actualiser la liste des problèmes ?')) {
                location.reload();
            }
        }, 300000); // 5 minutes

        // Highlight critical issues
        document.addEventListener('DOMContentLoaded', function() {
            const criticalItems = document.querySelectorAll('[style*="background: var(--danger)"]');
            criticalItems.forEach(item => {
                if (item.classList.contains('issue-priority')) {
                    const issueItem = item.closest('.issue-item');
                    if (issueItem) {
                        issueItem.style.animation = 'pulse 2s infinite';
                    }
                }
            });

            // Add pulse animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes pulse {
                    0% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.4); }
                    70% { box-shadow: 0 0 0 10px rgba(231, 76, 60, 0); }
                    100% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0); }
                }
            `;
            document.head.appendChild(style);
        });

        // Search functionality
        const searchInput = document.querySelector('.search-bar input');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const allItems = document.querySelectorAll('.issue-item, table tbody tr');
                
                allItems.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        item.style.display = '';
                        if (searchTerm) {
                            item.style.backgroundColor = '#fffde7';
                        } else {
                            item.style.backgroundColor = '';
                        }
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }
    </script>
</body>
</html>