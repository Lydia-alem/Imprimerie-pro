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

// Check if user is logged in (basic authentication - you should enhance this)
$isLoggedIn = true; // For demo purposes

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
    
    return $stats;
}

// Fetch recent orders
function getRecentOrders($pdo, $limit = 5) {
    $stmt = $pdo->prepare("
        SELECT o.*, c.name as client_name 
        FROM orders o
        LEFT JOIN clients c ON o.client_id = c.id
        ORDER BY o.created_at DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// Fetch order items for a specific order
function getOrderItems($pdo, $orderId) {
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name as product_name 
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

// Fetch orders chart data (last 30 days)
function getOrdersChartData($pdo) {
    $labels = [];
    $data = [];
    
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('d M', strtotime("-$i days"));
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM orders 
            WHERE DATE(created_at) = ?
        ");
        $stmt->execute([$date]);
        $result = $stmt->fetch();
        $data[] = $result['count'];
    }
    
    return ['labels' => $labels, 'data' => $data];
}

// Fetch products chart data
function getProductsChartData($pdo) {
    $stmt = $pdo->query("
        SELECT 
            p.category,
            COUNT(oi.id) as count
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE p.category IS NOT NULL
        GROUP BY p.category
        ORDER BY count DESC
        LIMIT 5
    ");
    
    $categories = [];
    $counts = [];
    
    while ($row = $stmt->fetch()) {
        $categories[] = $row['category'] ?: 'Autres';
        $counts[] = $row['count'];
    }
    
    return ['categories' => $categories, 'counts' => $counts];
}

// Get status badge class
function getStatusClass($status) {
    switch ($status) {
        case 'pending':
            return 'status-pending';
        case 'in_production':
            return 'status-in-progress';
        case 'ready':
        case 'delivered':
            return 'status-completed';
        case 'cancelled':
            return 'status-cancelled';
        default:
            return 'status-pending';
    }
}

// Get status text in French
function getStatusText($status) {
    $statusMap = [
        'pending' => 'En attente',
        'in_production' => 'En cours',
        'ready' => 'Prêt',
        'delivered' => 'Livré',
        'cancelled' => 'Annulé'
    ];
    
    return $statusMap[$status] ?? $status;
}

// Get all data
if ($isLoggedIn) {
    $stats = getDashboardStats($pdo);
    $recentOrders = getRecentOrders($pdo);
    $ordersChartData = getOrdersChartData($pdo);
    $productsChartData = getProductsChartData($pdo);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimerie Admin - Tableau de Bord</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            width: 45px;
            height: 45px;
            background: white;
            border-radius: 10px;
            padding: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
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
            margin-left: 250px; /* Match sidebar width */
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
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
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

        .card-1 .stat-icon {
            background: var(--secondary);
        }

        .card-2 .stat-icon {
            background: var(--success);
        }

        .card-3 .stat-icon {
            background: var(--warning);
        }

        .card-4 .stat-icon {
            background: var(--danger);
        }

        .charts-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .chart-header h3 {
            font-size: 1.2rem;
            color: var(--primary);
        }

        .chart-container {
            height: 250px;
            position: relative;
        }

        .recent-orders {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .orders-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .orders-header h3 {
            font-size: 1.2rem;
            color: var(--primary);
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
        }

        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-in-progress {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary {
            background: var(--secondary);
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-view {
            background: transparent;
            color: var(--secondary);
            border: 1px solid var(--secondary);
        }

        .btn-view:hover {
            background: var(--secondary);
            color: white;
        }

        .order-id {
            color: var(--secondary);
            font-weight: 600;
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

        /* Responsive */
        @media (max-width: 992px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
            
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
                margin: 0;
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
            
            .stats-cards {
                grid-template-columns: 1fr 1fr;
            }
            
            .search-bar {
                display: none;
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
            
            .header-left, .header-right {
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
            <i class="fas fa-print fa-2x"></i>
            <h2>Imprimerie Pro</h2>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li class="active">
                    <a href="#">
                        <i class="fas fa-home"></i>
                        <span>Tableau de Bord</span>
                    </a>
                </li>
                <li>
                    <a href="ajustestock.php">
                        <i class="fas fa-box"></i>
                        <span>Stock</span>
                    </a>
                </li>
                <li>
                    <a href="gestion.php">
                        <i class="fas fa-users"></i>
                        <span>Gestion</span>
                    </a>
                </li>
                <li>
                    <a href="employees.php">
                        <i class="fas fa-user-tie"></i>
                        <span>Employés</span>
                    </a>
                </li>
                <li>
                    <a href="facture.php">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>Facturation</span>
                    </a>
                </li>
                <li>
                    <a href="profile.php">
                        <i class="fas fa-user"></i>
                        <span>Mon Profil</span>
                    </a>
                </li>
            </ul>
        </div>
        <div class="sidebar-footer">
            <div class="user-info">
                <img src="https://i.pravatar.cc/150?img=12" alt="Admin" class="user-avatar">
                <div class="user-details">
                    <h4>Administrateur</h4>
                    <span>Super Admin</span>
                </div>
            </div>
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
                    <img src="https://i.pravatar.cc/150?img=12" alt="Admin">
                    <span>Admin</span>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <?php if ($isLoggedIn): ?>
            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card card-1">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['pending_orders']; ?></h3>
                        <p>Commandes en cours</p>
                    </div>
                </div>
                <div class="stat-card card-2">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['completed_orders']; ?></h3>
                        <p>Commandes terminées</p>
                    </div>
                </div>
                <div class="stat-card card-3">
                    <div class="stat-icon">
                        <i class="fas fa-euro-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3>€<?php echo number_format($stats['monthly_revenue'], 2, ',', ' '); ?></h3>
                        <p>Revenu ce mois</p>
                    </div>
                </div>
                <div class="stat-card card-4">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $stats['urgent_issues']; ?></h3>
                        <p>Problèmes urgents</p>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-section">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Commandes des 7 derniers jours</h3>
                        <button class="btn btn-primary">Voir rapport</button>
                    </div>
                    <div class="chart-container">
                        <canvas id="ordersChart"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Types de produits</h3>
                        <button class="btn btn-primary">Voir détails</button>
                    </div>
                    <div class="chart-container">
                        <canvas id="productsChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent Orders -->
            <div class="recent-orders">
                <div class="orders-header">
                    <h3>Commandes récentes</h3>
                    <button class="btn btn-view">Voir toutes</button>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>ID Commande</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Échéance</th>
                            <th>Total</th>
                            <th>Statut</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                        <tr>
                            <td class="order-id">#IMP-<?php echo $order['id']; ?></td>
                            <td><?php echo htmlspecialchars($order['client_name'] ?? 'Client inconnu'); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                            <td><?php echo $order['deadline'] ? date('d/m/Y', strtotime($order['deadline'])) : 'Non définie'; ?></td>
                            <td>€<?php echo number_format($order['total'], 2, ',', ' '); ?></td>
                            <td><span class="status <?php echo getStatusClass($order['status']); ?>"><?php echo getStatusText($order['status']); ?></span></td>
                            <td><button class="btn btn-view" onclick="showOrderDetails(<?php echo $order['id']; ?>)">Détails</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php else: ?>
            <div style="text-align: center; padding: 100px;">
                <h2>Veuillez vous connecter</h2>
                <p>Ce tableau de bord nécessite une authentification.</p>
                <button class="btn btn-primary" onclick="location.href='login.php'">Se connecter</button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="orderModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 30px; border-radius: 10px; max-width: 800px; width: 90%; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 id="modalTitle">Détails de la commande</h3>
                <button onclick="closeOrderModal()" style="background: none; border: none; font-size: 24px; cursor: pointer;">&times;</button>
            </div>
            <div id="orderDetailsContent">
                <!-- Order details will be loaded here -->
            </div>
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

        // Orders Chart
        const ordersCtx = document.getElementById('ordersChart')?.getContext('2d');
        if (ordersCtx) {
            const ordersChart = new Chart(ordersCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($ordersChartData['labels'] ?? []); ?>,
                    datasets: [{
                        label: 'Commandes',
                        data: <?php echo json_encode($ordersChartData['data'] ?? []); ?>,
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Products Chart
        const productsCtx = document.getElementById('productsChart')?.getContext('2d');
        if (productsCtx) {
            const productsChart = new Chart(productsCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($productsChartData['categories'] ?? []); ?>,
                    datasets: [{
                        data: <?php echo json_encode($productsChartData['counts'] ?? []); ?>,
                        backgroundColor: [
                            '#3498db',
                            '#2ecc71',
                            '#f39c12',
                            '#e74c3c',
                            '#95a5a6',
                            '#9b59b6'
                        ],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Show order details modal
        function showOrderDetails(orderId) {
            fetch('get_order_details.php?order_id=' + orderId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('orderDetailsContent').innerHTML = data;
                    document.getElementById('orderModal').style.display = 'flex';
                })
                .catch(error => {
                    document.getElementById('orderDetailsContent').innerHTML = 
                        '<p>Erreur lors du chargement des détails de la commande.</p>';
                    document.getElementById('orderModal').style.display = 'flex';
                });
        }

        // Close order modal
        function closeOrderModal() {
            document.getElementById('orderModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('orderModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeOrderModal();
            }
        });
    </script>
</body>
</html>