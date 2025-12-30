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

// Fetch statistics from database
function getDashboardStats($pdo) {
    $stats = [];
    
    // Total pending orders
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE status IN ('pending', 'in_production')");
    $stats['pending_orders'] = $stmt->fetch()['count'];
    
    // Total completed orders
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE status IN ('ready', 'delivered')");
    $stats['completed_orders'] = $stmt->fetch()['count'];
    
    // Monthly revenue
    $currentMonth = date('Y-m');
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as revenue 
        FROM invoices 
        WHERE status = 'paid' 
        AND DATE_FORMAT(created_at, '%Y-%m') = ?
    ");
    $stmt->execute([$currentMonth]);
    $stats['monthly_revenue'] = $stmt->fetch()['revenue'];
    
    // Urgent issues (orders with deadlines in next 3 days)
    $threeDaysLater = date('Y-m-d', strtotime('+3 days'));
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM orders 
        WHERE deadline <= ? 
        AND status IN ('pending', 'in_production')
        AND deadline IS NOT NULL
    ");
    $stmt->execute([$threeDaysLater]);
    $stats['urgent_issues'] = $stmt->fetch()['count'];
    
    // Devis count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM quotes WHERE status = 'pending'");
    $stats['pending_quotes'] = $stmt->fetch()['count'];
    
    // Dépenses count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM general_depenses");
    $stats['depenses'] = $stmt->fetch()['count'];
    
    // Factures count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM invoices WHERE status = 'unpaid'");
    $stats['unpaid_invoices'] = $stmt->fetch()['count'];
    
    // Employés count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees");
    $stats['employees'] = $stmt->fetch()['count'];
    
    // Ventes count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM invoices WHERE status = 'paid'");
    $stats['sales'] = $stmt->fetch()['count'];
    
    return $stats;
}

// Get all data
if ($isLoggedIn) {
    $stats = getDashboardStats($pdo);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimerie Admin - Tableau de Bord</title>
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

        /* Stats Cards Section */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s;
            cursor: pointer;
            border: 2px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: var(--secondary);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
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

        /* Navigation Cards Section */
        .navigation-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            margin-top: 40px;
        }

        .nav-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: var(--shadow);
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: var(--dark);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 15px;
            border: 2px solid transparent;
        }

        .nav-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
            border-color: var(--secondary);
        }

        .nav-card-icon {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            margin-bottom: 10px;
        }

        .nav-card h3 {
            font-size: 1.3rem;
            margin-bottom: 8px;
        }

        .nav-card p {
            color: var(--gray);
            font-size: 0.9rem;
            line-height: 1.4;
        }

        /* Colors for navigation cards */
        .nav-card-commandes .nav-card-icon { background: linear-gradient(135deg, #3498db, #2980b9); }
        .nav-card-devis .nav-card-icon { background: linear-gradient(135deg, #2ecc71, #27ae60); }
        .nav-card-depenses .nav-card-icon { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .nav-card-factures .nav-card-icon { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .nav-card-employes .nav-card-icon { background: linear-gradient(135deg, #f39c12, #d35400); }
        .nav-card-ventes .nav-card-icon { background: linear-gradient(135deg, #1abc9c, #16a085); }

        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, var(--primary), #1a252f);
            border-radius: 15px;
            padding: 40px;
            color: white;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .welcome-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            transform: translate(100px, -100px);
        }

        .welcome-section::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 50%;
            transform: translate(-50px, 50px);
        }

        .welcome-section h2 {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .welcome-section p {
            font-size: 1rem;
            opacity: 0.9;
            max-width: 600px;
        }

        /* Stats summary */
        .stats-summary {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }

        .stats-summary h3 {
            font-size: 1.5rem;
            margin-bottom: 20px;
            color: var(--primary);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .summary-item {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid var(--secondary);
        }

        .summary-item h4 {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .summary-item p {
            color: var(--gray);
            font-size: 0.9rem;
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
            
            .navigation-cards {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
            
            .welcome-section {
                padding: 30px 20px;
            }
            
            .stats-cards,
            .navigation-cards,
            .summary-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 576px) {
            .stats-cards,
            .navigation-cards,
            .summary-grid {
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
            
            .welcome-section h2 {
                font-size: 1.5rem;
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
        .nav-card-probleme .nav-card-icon { background: linear-gradient(135deg, #e74c3c, #c0392b); }
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
                <li class="active">
                    <a href="#">
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
                <h1>Tableau de Bord</h1>
                <small>Dernière mise à jour: <?php echo date('d/m/Y H:i'); ?></small>
            </div>
            <div class="header-right">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Rechercher...">
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
                <!-- Welcome Section -->
                <div class="welcome-section">
                    <h2>Bienvenue, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Administrateur'); ?>!</h2>
                    <p>Bienvenue sur votre tableau de bord. Gérez votre imprimerie efficacement avec tous les outils à votre disposition.</p>
                </div>

                <!-- Quick Stats -->
                <div class="stats-summary">
                    <h3>Aperçu Rapide</h3>
                    <div class="summary-grid">
                        <div class="summary-item">
                            <h4><?php echo $stats['pending_orders']; ?></h4>
                            <p>Commandes en attente</p>
                        </div>
                        <div class="summary-item">
                            <h4><?php echo number_format($stats['monthly_revenue'], 0, ',', ' '); ?> DA</h4>
                            <p>Revenu mensuel</p>
                        </div>
                        <div class="summary-item">
                            <h4><?php echo $stats['pending_quotes']; ?></h4>
                            <p>Devis en attente</p>
                        </div>
                        <div class="summary-item">
                            <h4><?php echo $stats['urgent_issues']; ?></h4>
                            <p>Problèmes urgents</p>
                        </div>
                    </div>
                </div>

                <!-- Navigation Cards -->
                <h2 style="margin: 30px 0 20px 0; color: var(--primary);">Accès Rapide</h2>
                <div class="navigation-cards">
                    <!-- Commandes Card -->
                    <a href="commande.php" class="nav-card nav-card-commandes">
                        <div class="nav-card-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <h3>Commandes</h3>
                        <p>Gérez toutes vos commandes clients et suivez leur progression</p>
                        <small style="color: var(--secondary); font-weight: 500;">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $stats['pending_orders']; ?> en attente
                        </small>
                    </a>

                    <!-- Devis Card -->
                    <a href="devis.php" class="nav-card nav-card-devis">
                        <div class="nav-card-icon">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <h3>Devis</h3>
                        <p>Créez et gérez les devis pour vos clients potentiels</p>
                        <small style="color: var(--secondary); font-weight: 500;">
                            <i class="fas fa-clock"></i> <?php echo $stats['pending_quotes']; ?> en attente
                        </small>
                    </a>

                    <!-- Dépenses Card -->
                    <a href="depenses.php" class="nav-card nav-card-depenses">
                        <div class="nav-card-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <h3>Dépenses</h3>
                        <p>Suivez toutes les dépenses de l'entreprise</p>
                        <small style="color: var(--secondary); font-weight: 500;">
                            <i class="fas fa-list"></i> <?php echo $stats['depenses']; ?> dépenses enregistrées
                        </small>
                    </a>

                    <!-- Factures Card -->
                    <a href="facture.php" class="nav-card nav-card-factures">
                        <div class="nav-card-icon">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                        <h3>Facturation</h3>
                        <p>Générez et suivez les factures de vos clients</p>
                        <small style="color: var(--secondary); font-weight: 500;">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo $stats['unpaid_invoices']; ?> factures impayées
                        </small>
                    </a>

                    <!-- Employés Card -->
                    <a href="employees.php" class="nav-card nav-card-employes">
                        <div class="nav-card-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <h3>Employés</h3>
                        <p>Gérez votre équipe et leurs informations</p>
                        <small style="color: var(--secondary); font-weight: 500;">
                            <i class="fas fa-users"></i> <?php echo $stats['employees']; ?> employés
                        </small>
                    </a>

                    <!-- Ventes Card -->
                    <a href="ventes.php" class="nav-card nav-card-ventes">
                        <div class="nav-card-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <h3>Ventes</h3>
                        <p>Analysez vos performances de vente et tendances</p>
                        <small style="color: var(--secondary); font-weight: 500;">
                            <i class="fas fa-check-circle"></i> <?php echo $stats['sales']; ?> ventes complétées
                        </small>
                    </a>
                     
                    <a href="probleme.php" class="nav-card nav-card-probleme">
                       <div class="nav-card-icon">
                         <i class="fas fa-exclamation-triangle"></i>
                      </div>
                      <h3>Problèmes</h3>
                       <p>Détectez et gérez tous les problèmes urgents</p>
                       <small style="color: var(--warning); font-weight: 500;">
                           <i class="fas fa-exclamation-circle"></i> <?php echo $stats['urgent_issues'] ?? 0; ?> problèmes détectés
                       </small>
                    </a>
                     <a href="dettes.php" class="nav-card nav-card-probleme">
    <div class="nav-card-icon">
        <i class="fas fa-money-check-alt"></i>
    </div>
    <h3>Dettes</h3>
    <p>Gérez les dettes clients et suivez les paiements</p>
    <small style="color: var(--warning); font-weight: 500;">
        
    </small>
</a>
                </div>

            <?php else: ?>
                <!-- Not logged in message -->
                <div style="text-align: center; padding: 100px;">
                    <div style="background: white; padding: 40px; border-radius: 15px; box-shadow: var(--shadow); max-width: 500px; margin: 0 auto;">
                        <div style="font-size: 4rem; color: var(--accent); margin-bottom: 20px;">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h2 style="color: var(--primary); margin-bottom: 15px;">Accès Restreint</h2>
                        <p style="color: var(--gray); margin-bottom: 30px;">Veuillez vous connecter pour accéder au tableau de bord</p>
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

        // Add hover effects to nav cards
        document.querySelectorAll('.nav-card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-8px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Add click animation to nav cards
        document.querySelectorAll('.nav-card').forEach(card => {
            card.addEventListener('click', function(e) {
                // Add click animation
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            });
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

        // Add animation to welcome section
        document.addEventListener('DOMContentLoaded', function() {
            const welcomeSection = document.querySelector('.welcome-section');
            if (welcomeSection) {
                welcomeSection.style.opacity = '0';
                welcomeSection.style.transform = 'translateY(-20px)';
                
                setTimeout(() => {
                    welcomeSection.style.transition = 'all 0.8s ease';
                    welcomeSection.style.opacity = '1';
                    welcomeSection.style.transform = 'translateY(0)';
                }, 300);
            }
            
            // Add staggered animation to nav cards
            const navCards = document.querySelectorAll('.nav-card');
            navCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 500 + (index * 100));
            });
        });
    </script>
</body>
</html>