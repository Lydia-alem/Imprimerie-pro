<?php
// Database configuration
define('DB_HOST', '127.0.0.1:3306');
define('DB_NAME', 'imprimerie');
define('DB_USER', 'root');
define('DB_PASS', 'admine');

// Start session
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

// For demo purposes - authentication
$isLoggedIn = true;

// Get filter parameters
$filterType = $_GET['type'] ?? 'all';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Fetch sales data
function getSalesStats($pdo, $startDate, $endDate) {
    $stats = [];
    
    // Total sales
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as total 
        FROM invoices 
        WHERE DATE(created_at) BETWEEN ? AND ?
        AND status IN ('paid', 'partial')
    ");
    $stmt->execute([$startDate, $endDate]);
    $stats['total_sales'] = $stmt->fetch()['total'];
    
    // Today's sales
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as total 
        FROM invoices 
        WHERE DATE(created_at) = ?
        AND status IN ('paid', 'partial')
    ");
    $stmt->execute([$today]);
    $stats['today_sales'] = $stmt->fetch()['total'];
    
    // Total invoices
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM invoices 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $stats['total_invoices'] = $stmt->fetch()['count'];
    
    // Average invoice value
    $stats['avg_invoice'] = $stats['total_invoices'] > 0 ? 
        $stats['total_sales'] / $stats['total_invoices'] : 0;
    
    return $stats;
}

// Fetch invoices based on filter
function getInvoices($pdo, $filterType, $startDate, $endDate, $limit = 50) {
    $query = "
        SELECT i.*, c.name as client_name, 
               COUNT(p.id) as payment_count,
               COALESCE(SUM(p.amount), 0) as paid_amount,
               (i.total - COALESCE(SUM(p.amount), 0)) as remaining_amount
        FROM invoices i
        LEFT JOIN clients c ON i.client_id = c.id
        LEFT JOIN payments p ON i.id = p.invoice_id
        WHERE DATE(i.created_at) BETWEEN :start_date AND :end_date
    ";
    
    $params = [
        ':start_date' => $startDate,
        ':end_date' => $endDate
    ];
    
    switch ($filterType) {
        case 'paid':
            $query .= " AND i.status = 'paid'";
            break;
        case 'unpaid':
            $query .= " AND i.status = 'unpaid'";
            break;
        case 'partial':
            $query .= " AND i.status = 'partial'";
            break;
    }
    
    $query .= " GROUP BY i.id ORDER BY i.created_at DESC LIMIT :limit";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
    $stmt->bindValue(':end_date', $endDate, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

// Fetch top clients
function getTopClients($pdo, $startDate, $endDate, $limit = 5) {
    $stmt = $pdo->prepare("
        SELECT c.id, c.name, 
               COUNT(i.id) as invoice_count,
               COALESCE(SUM(i.total), 0) as total_spent
        FROM clients c
        LEFT JOIN invoices i ON c.id = i.client_id
        WHERE DATE(i.created_at) BETWEEN ? AND ?
        GROUP BY c.id
        ORDER BY total_spent DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $startDate, PDO::PARAM_STR);
    $stmt->bindValue(2, $endDate, PDO::PARAM_STR);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

// Get sales chart data
function getSalesChartData($pdo, $startDate, $endDate) {
    $labels = [];
    $data = [];
    
    $date1 = new DateTime($startDate);
    $date2 = new DateTime($endDate);
    $interval = $date1->diff($date2);
    $days = $interval->days;
    
    // Limit to 30 points max
    $step = max(1, floor($days / 30));
    
    for ($i = $days; $i >= 0; $i -= $step) {
        $date = date('Y-m-d', strtotime("-$i days", strtotime($endDate)));
        $labels[] = date('d M', strtotime($date));
        
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total), 0) as total 
            FROM invoices 
            WHERE DATE(created_at) = ?
            AND status IN ('paid', 'partial')
        ");
        $stmt->execute([$date]);
        $result = $stmt->fetch();
        $data[] = $result['total'];
    }
    
    return ['labels' => $labels, 'data' => $data];
}

// Get status badge class
function getInvoiceStatusClass($status) {
    switch ($status) {
        case 'paid':
            return 'status-completed';
        case 'unpaid':
            return 'status-cancelled';
        case 'partial':
            return 'status-in-progress';
        default:
            return 'status-pending';
    }
}

// Get status text in French
function getInvoiceStatusText($status) {
    $statusMap = [
        'paid' => 'Payée',
        'unpaid' => 'Impayée',
        'partial' => 'Partiellement payée'
    ];
    
    return $statusMap[$status] ?? $status;
}

// Format currency
function formatCurrency($amount) {
    return number_format($amount, 2, ',', ' ') . ' DA';
}

// Get all data
if ($isLoggedIn) {
    $salesStats = getSalesStats($pdo, $startDate, $endDate);
    $invoices = getInvoices($pdo, $filterType, $startDate, $endDate);
    $topClients = getTopClients($pdo, $startDate, $endDate);
    $salesChartData = getSalesChartData($pdo, $startDate, $endDate);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimerie - Gestion des Ventes</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --success: #27ae60;
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

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, var(--primary) 0%, #1a252f 100%);
            color: white;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
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
        }

        .sidebar-header i {
            font-size: 2rem;
            color: white;
        }

        .sidebar-header h2 {
            font-size: 1.4rem;
            font-weight: 700;
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
        }

        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-size: 0.95rem;
        }

        .sidebar-menu li:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .sidebar-menu li.active {
            background: linear-gradient(90deg, var(--secondary), #2980b9);
        }

        .sidebar-menu li.active a {
            color: white;
            font-weight: 600;
        }

        .sidebar-menu i {
            width: 24px;
            margin-right: 12px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            margin-left: 250px;
        }

        /* Header */
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

        /* Content */
        .content {
            padding: 30px;
            flex: 1;
        }

        /* Filters Section */
        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }

        .filter-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 500;
        }

        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
        }

        /* Stats Cards - Horizontal Layout */
        .sales-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .sales-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s;
        }

        .sales-card:hover {
            transform: translateY(-5px);
        }

        .sales-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .sales-info h3 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .sales-info p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .card-1 .sales-icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .card-2 .sales-icon { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .card-3 .sales-icon { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .card-4 .sales-icon { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }

        /* Quick Action Cards */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-card {
            background: white;
            border-radius: 10px;
            padding: 25px 20px;
            text-align: center;
            box-shadow: var(--shadow);
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .action-card:hover {
            transform: translateY(-5px);
            border-color: var(--secondary);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }

        .action-card i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--secondary);
        }

        .action-card h4 {
            font-size: 1rem;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .action-card .price {
            font-size: 0.9rem;
            color: var(--success);
            font-weight: 600;
        }

        /* Charts Section */
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

        /* Table Section */
        .table-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--shadow);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h3 {
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
            display: inline-block;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .status-in-progress {
            background: #d1ecf1;
            color: #0c5460;
        }

        /* Buttons */
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

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #219653;
        }

        .btn-small {
            padding: 5px 10px;
            font-size: 0.8rem;
        }

        /* Responsive */
        @media (max-width: 1200px) {
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
            
            .sidebar-menu i {
                margin-right: 0;
                font-size: 1.2rem;
            }
            
            .main-content {
                margin-left: 70px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 250px;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .charts-section {
                grid-template-columns: 1fr;
            }
            
            .sales-cards {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 576px) {
            .sales-cards {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-print fa-2x"></i>
            <h2>Imprimerie Pro</h2>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li>
                    <a href="index.php">
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
                <li class="active">
                    <a href="#">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Ventes</span>
                    </a>
                </li>
                <li>
                    <a href="gestion.php">
                        <i class="fas fa-users"></i>
                        <span>Gestion</span>
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
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <h1>Gestion des Ventes</h1>
                <small>Suivi et analyse des ventes</small>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <?php if ($isLoggedIn): ?>
            
            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="type">Type de facture</label>
                            <select name="type" id="type">
                                <option value="all" <?php echo $filterType == 'all' ? 'selected' : ''; ?>>Toutes les factures</option>
                                <option value="paid" <?php echo $filterType == 'paid' ? 'selected' : ''; ?>>Payées</option>
                                <option value="unpaid" <?php echo $filterType == 'unpaid' ? 'selected' : ''; ?>>Impayées</option>
                                <option value="partial" <?php echo $filterType == 'partial' ? 'selected' : ''; ?>>Partiellement payées</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="start_date">Date de début</label>
                            <input type="date" name="start_date" id="start_date" 
                                   value="<?php echo $startDate; ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="end_date">Date de fin</label>
                            <input type="date" name="end_date" id="end_date" 
                                   value="<?php echo $endDate; ?>">
                        </div>
                        
                        <div class="filter-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filtrer
                            </button>
                            <button type="button" class="btn" onclick="resetFilters()">
                                <i class="fas fa-redo"></i> Réinitialiser
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Stats Cards -->
            <div class="sales-cards">
                <div class="sales-card card-1">
                    <div class="sales-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="sales-info">
                        <h3><?php echo formatCurrency($salesStats['total_sales']); ?></h3>
                        <p>Ventes totales</p>
                    </div>
                </div>
                
                <div class="sales-card card-2">
                    <div class="sales-icon">
                        <i class="fas fa-sun"></i>
                    </div>
                    <div class="sales-info">
                        <h3><?php echo formatCurrency($salesStats['today_sales']); ?></h3>
                        <p>Ventes aujourd'hui</p>
                    </div>
                </div>
                
                <div class="sales-card card-3">
                    <div class="sales-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="sales-info">
                        <h3><?php echo $salesStats['total_invoices']; ?></h3>
                        <p>Factures</p>
                    </div>
                </div>
                
                <div class="sales-card card-4">
                    <div class="sales-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="sales-info">
                        <h3><?php echo formatCurrency($salesStats['avg_invoice']); ?></h3>
                        <p>Moyenne par facture</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions - Cards like in the photo -->
            <div class="quick-actions">
                <div class="action-card" onclick="location.href='facture.php?type=new'">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <h4>Facture</h4>
                    <div class="price">Nouvelle facture</div>
                </div>
                
                <div class="action-card" onclick="location.href='devis.php'">
                    <i class="fas fa-file-contract"></i>
                    <h4>Devis</h4>
                    <div class="price">Créer un devis</div>
                </div>
                
                <div class="action-card" onclick="location.href='commande.php'">
                    <i class="fas fa-shopping-cart"></i>
                    <h4>Commande</h4>
                    <div class="price">Nouvelle commande</div>
                </div>
                
                <div class="action-card" onclick="location.href='bon_livraison.php'">
                    <i class="fas fa-truck"></i>
                    <h4>Bon de Livraison</h4>
                    <div class="price">Créer un BL</div>
                </div>
                
                <div class="action-card" onclick="location.href='bon_reception.php'">
                    <i class="fas fa-clipboard-check"></i>
                    <h4>Bon de Réception</h4>
                    <div class="price">Enregistrer réception</div>
                </div>
                
                <div class="action-card" onclick="location.href='vente_comptoir.php'">
                    <i class="fas fa-cash-register"></i>
                    <h4>Vente au comptoir</h4>
                    <div class="price">Vente directe</div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-section">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Évolution des ventes</h3>
                        <button class="btn btn-primary btn-small">Exporter</button>
                    </div>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Top clients</h3>
                    </div>
                    <div class="chart-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Client</th>
                                    <th>Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topClients as $client): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($client['name']); ?></td>
                                    <td><?php echo formatCurrency($client['total_spent']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Invoices Table -->
            <div class="table-section">
                <div class="section-header">
                    <h3>Factures récentes</h3>
                    <div>
                        <button class="btn btn-primary" onclick="location.href='facture.php?type=new'">
                            <i class="fas fa-plus"></i> Nouvelle facture
                        </button>
                        <button class="btn btn-success" onclick="exportInvoices()">
                            <i class="fas fa-file-excel"></i> Exporter
                        </button>
                    </div>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>N° Facture</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Payé</th>
                            <th>Reste</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td>#INV-<?php echo $invoice['id']; ?></td>
                            <td><?php echo htmlspecialchars($invoice['client_name'] ?? 'Client inconnu'); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($invoice['created_at'])); ?></td>
                            <td><?php echo formatCurrency($invoice['total']); ?></td>
                            <td><?php echo formatCurrency($invoice['paid_amount']); ?></td>
                            <td><?php echo formatCurrency($invoice['remaining_amount']); ?></td>
                            <td>
                                <span class="status <?php echo getInvoiceStatusClass($invoice['status']); ?>">
                                    <?php echo getInvoiceStatusText($invoice['status']); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-primary btn-small" onclick="viewInvoice(<?php echo $invoice['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-success btn-small" onclick="addPayment(<?php echo $invoice['id']; ?>)">
                                    <i class="fas fa-money-bill"></i>
                                </button>
                                <button class="btn btn-small" onclick="printInvoice(<?php echo $invoice['id']; ?>)">
                                    <i class="fas fa-print"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php else: ?>
            <div style="text-align: center; padding: 100px;">
                <h2>Veuillez vous connecter</h2>
                <button class="btn btn-primary" onclick="location.href='login.php'">Se connecter</button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Sales Chart
        const salesCtx = document.getElementById('salesChart')?.getContext('2d');
        if (salesCtx) {
            const salesChart = new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($salesChartData['labels'] ?? []); ?>,
                    datasets: [{
                        label: 'Ventes (DA)',
                        data: <?php echo json_encode($salesChartData['data'] ?? []); ?>,
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
                                callback: function(value) {
                                    return value.toLocaleString('fr-FR') + ' DA';
                                }
                            }
                        }
                    }
                }
            });
        }

        // Reset filters
        function resetFilters() {
            window.location.href = window.location.pathname;
        }

        // View invoice
        function viewInvoice(invoiceId) {
            window.open('view_invoice.php?id=' + invoiceId, '_blank');
        }

        // Add payment
        function addPayment(invoiceId) {
            const amount = prompt('Montant du paiement:');
            if (amount && !isNaN(amount) && amount > 0) {
                // In a real application, you would make an AJAX call here
                alert('Paiement de ' + amount + ' DA enregistré pour la facture #' + invoiceId);
                window.location.reload();
            }
        }

        // Print invoice
        function printInvoice(invoiceId) {
            window.open('print_invoice.php?id=' + invoiceId, '_blank');
        }

        // Export invoices
        function exportInvoices() {
            const params = new URLSearchParams(window.location.search);
            window.open('export_invoices.php?' + params.toString(), '_blank');
        }

        // Set default dates for filter
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const firstDay = new Date(new Date().getFullYear(), new Date().getMonth(), 1)
                .toISOString().split('T')[0];
            
            if (!document.getElementById('start_date').value) {
                document.getElementById('start_date').value = firstDay;
            }
            
            if (!document.getElementById('end_date').value) {
                document.getElementById('end_date').value = today;
            }
        });
    </script>
</body>
</html>