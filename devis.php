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
$filterStatus = $_GET['status'] ?? 'all';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Fetch devis data
function getDevisStats($pdo, $startDate, $endDate) {
    $stats = [];
    
    // Total devis
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM quotes 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $stats['total_devis'] = $stmt->fetch()['count'];
    
    // Today's devis
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM quotes 
        WHERE DATE(created_at) = ?
    ");
    $stmt->execute([$today]);
    $stats['today_devis'] = $stmt->fetch()['count'];
    
    // Total value
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as total 
        FROM quotes 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $stats['total_value'] = $stmt->fetch()['total'];
    
    // Conversion rate
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'converted' THEN 1 END) as converted,
            COUNT(*) as total
        FROM quotes 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $result = $stmt->fetch();
    $stats['conversion_rate'] = $result['total'] > 0 ? 
        ($result['converted'] / $result['total']) * 100 : 0;
    
    return $stats;
}

// Fetch devis based on filter
function getDevis($pdo, $filterStatus, $startDate, $endDate, $limit = 50) {
    $query = "
        SELECT q.*, c.name as client_name, c.email, c.phone
        FROM quotes q
        LEFT JOIN clients c ON q.client_id = c.id
        WHERE DATE(q.created_at) BETWEEN :start_date AND :end_date
    ";
    
    $params = [
        ':start_date' => $startDate,
        ':end_date' => $endDate
    ];
    
    switch ($filterStatus) {
        case 'pending':
            $query .= " AND q.status = 'pending'";
            break;
        case 'accepted':
            $query .= " AND q.status = 'accepted'";
            break;
        case 'rejected':
            $query .= " AND q.status = 'rejected'";
            break;
        case 'converted':
            $query .= " AND q.status = 'converted'";
            break;
    }
    
    $query .= " ORDER BY q.created_at DESC LIMIT :limit";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':start_date', $startDate, PDO::PARAM_STR);
    $stmt->bindValue(':end_date', $endDate, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

// Fetch top clients by devis
function getTopClientsByDevis($pdo, $startDate, $endDate, $limit = 5) {
    $stmt = $pdo->prepare("
        SELECT c.id, c.name, 
               COUNT(q.id) as quote_count,
               COALESCE(SUM(q.total), 0) as total_value
        FROM clients c
        LEFT JOIN quotes q ON c.id = q.client_id
        WHERE DATE(q.created_at) BETWEEN ? AND ?
        GROUP BY c.id
        ORDER BY quote_count DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, $startDate, PDO::PARAM_STR);
    $stmt->bindValue(2, $endDate, PDO::PARAM_STR);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

// Get status chart data
function getStatusChartData($pdo, $startDate, $endDate) {
    $stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM quotes 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY status
    ");
    $stmt->execute([$startDate, $endDate]);
    
    $labels = [];
    $data = [];
    $colors = [];
    
    $statusColors = [
        'pending' => '#f39c12',
        'accepted' => '#27ae60',
        'rejected' => '#e74c3c',
        'converted' => '#3498db'
    ];
    
    $statusLabels = [
        'pending' => 'En attente',
        'accepted' => 'Accepté',
        'rejected' => 'Rejeté',
        'converted' => 'Converti'
    ];
    
    while ($row = $stmt->fetch()) {
        $labels[] = $statusLabels[$row['status']] ?? $row['status'];
        $data[] = $row['count'];
        $colors[] = $statusColors[$row['status']] ?? '#95a5a6';
    }
    
    return ['labels' => $labels, 'data' => $data, 'colors' => $colors];
}

// Get status badge class
function getDevisStatusClass($status) {
    switch ($status) {
        case 'pending':
            return 'status-pending';
        case 'accepted':
            return 'status-accepted';
        case 'rejected':
            return 'status-rejected';
        case 'converted':
            return 'status-converted';
        default:
            return 'status-pending';
    }
}

// Get status text in French
function getDevisStatusText($status) {
    $statusMap = [
        'pending' => 'En attente',
        'accepted' => 'Accepté',
        'rejected' => 'Rejeté',
        'converted' => 'Converti en commande'
    ];
    
    return $statusMap[$status] ?? $status;
}

// Format currency
function formatCurrency($amount) {
    return number_format($amount, 2, ',', ' ') . ' DA';
}

// Get clients for dropdown
function getClients($pdo) {
    $stmt = $pdo->query("SELECT id, name FROM clients ORDER BY name");
    return $stmt->fetchAll();
}

// Get products for dropdown
function getProducts($pdo) {
    $stmt = $pdo->query("SELECT id, name, price FROM products ORDER BY name");
    return $stmt->fetchAll();
}

// Get all data
if ($isLoggedIn) {
    $devisStats = getDevisStats($pdo, $startDate, $endDate);
    $devis = getDevis($pdo, $filterStatus, $startDate, $endDate);
    $topClients = getTopClientsByDevis($pdo, $startDate, $endDate);
    $statusChartData = getStatusChartData($pdo, $startDate, $endDate);
    $clients = getClients($pdo);
    $products = getProducts($pdo);
}

// Handle new devis form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_devis') {
    try {
        $pdo->beginTransaction();
        
        // Insert quote
        $stmt = $pdo->prepare("
            INSERT INTO quotes (client_id, status, total, created_at) 
            VALUES (?, 'pending', 0, NOW())
        ");
        $stmt->execute([
            $_POST['client_id']
        ]);
        $quoteId = $pdo->lastInsertId();
        
        // Insert quote items
        $total = 0;
        $items = json_decode($_POST['items'], true);
        
        foreach ($items as $item) {
            $subtotal = $item['quantity'] * $item['price'];
            $total += $subtotal;
            
            $stmt = $pdo->prepare("
                INSERT INTO quote_items (quote_id, product_id, description, quantity, price, subtotal)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $quoteId,
                $item['product_id'] ?: null,
                $item['description'],
                $item['quantity'],
                $item['price'],
                $subtotal
            ]);
        }
        
        // Update quote total
        $stmt = $pdo->prepare("UPDATE quotes SET total = ? WHERE id = ?");
        $stmt->execute([$total, $quoteId]);
        
        $pdo->commit();
        
        // Success message
        $successMessage = "Devis créé avec succès! Numéro: #DEV-$quoteId";
        
        // Refresh data
        $devis = getDevis($pdo, $filterStatus, $startDate, $endDate);
        $devisStats = getDevisStats($pdo, $startDate, $endDate);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $errorMessage = "Erreur lors de la création du devis: " . $e->getMessage();
    }
}

// Handle status update
if (isset($_GET['update_status']) && isset($_GET['quote_id']) && isset($_GET['status'])) {
    try {
        $stmt = $pdo->prepare("UPDATE quotes SET status = ? WHERE id = ?");
        $stmt->execute([$_GET['status'], $_GET['quote_id']]);
        
        // If converting to order
        if ($_GET['status'] === 'converted') {
            $quote = $pdo->prepare("SELECT * FROM quotes WHERE id = ?");
            $quote->execute([$_GET['quote_id']]);
            $quoteData = $quote->fetch();
            
            // Create order from quote
            $stmt = $pdo->prepare("
                INSERT INTO orders (client_id, quote_id, status, total, created_at)
                VALUES (?, ?, 'pending', ?, NOW())
            ");
            $stmt->execute([
                $quoteData['client_id'],
                $_GET['quote_id'],
                $quoteData['total']
            ]);
            $orderId = $pdo->lastInsertId();
            
            // Copy quote items to order items
            $items = $pdo->prepare("SELECT * FROM quote_items WHERE quote_id = ?");
            $items->execute([$_GET['quote_id']]);
            $quoteItems = $items->fetchAll();
            
            foreach ($quoteItems as $item) {
                $stmt = $pdo->prepare("
                    INSERT INTO order_items (order_id, product_id, description, quantity, price, subtotal)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $orderId,
                    $item['product_id'],
                    $item['description'],
                    $item['quantity'],
                    $item['price'],
                    $item['subtotal']
                ]);
            }
            
            $successMessage = "Devis converti en commande! Numéro de commande: #ORD-$orderId";
        } else {
            $successMessage = "Statut du devis mis à jour avec succès!";
        }
        
        // Refresh data
        $devis = getDevis($pdo, $filterStatus, $startDate, $endDate);
        
    } catch (Exception $e) {
        $errorMessage = "Erreur lors de la mise à jour: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimerie - Gestion des Devis</title>
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
            --info: #17a2b8;
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

        /* Messages */
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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

        /* Stats Cards */
        .devis-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .devis-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s;
        }

        .devis-card:hover {
            transform: translateY(-5px);
        }

        .devis-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .devis-info h3 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .devis-info p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .card-1 .devis-icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .card-2 .devis-icon { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .card-3 .devis-icon { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .card-4 .devis-icon { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }

        /* Quick Actions */
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

        .action-card .subtitle {
            font-size: 0.9rem;
            color: var(--success);
            font-weight: 600;
        }

        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
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

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-accepted {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .status-converted {
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
            display: inline-flex;
            align-items: center;
            gap: 5px;
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

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background: #e68900;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-info {
            background: var(--info);
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }

        .btn-small {
            padding: 5px 10px;
            font-size: 0.8rem;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 10px;
            max-width: 800px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .modal-header h3 {
            font-size: 1.5rem;
            color: var(--primary);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-row .form-group {
            flex: 1;
        }

        .items-table {
            width: 100%;
            margin-bottom: 20px;
        }

        .items-table th {
            background: #f8f9fa;
            padding: 10px;
        }

        .items-table td {
            padding: 10px;
            vertical-align: middle;
        }

        .total-row {
            font-weight: bold;
            font-size: 1.1rem;
            background: #f8f9fa;
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
            
            .devis-cards {
                grid-template-columns: 1fr 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr 1fr;
            }
            
            .form-row {
                flex-direction: column;
                gap: 10px;
            }
        }

        @media (max-width: 576px) {
            .devis-cards {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
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
                <li>
                    <a href="ventes.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Ventes</span>
                    </a>
                </li>
                <li class="active">
                    <a href="#">
                        <i class="fas fa-file-contract"></i>
                        <span>Devis</span>
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
                <h1>Gestion des Devis</h1>
                <small>Création et suivi des devis</small>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <?php if ($isLoggedIn): ?>
            
            <!-- Success/Error Messages -->
            <?php if (isset($successMessage)): ?>
            <div class="alert alert-success">
                <span><?php echo $successMessage; ?></span>
                <button onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
            <?php endif; ?>
            
            <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger">
                <span><?php echo $errorMessage; ?></span>
                <button onclick="this.parentElement.style.display='none'">&times;</button>
            </div>
            <?php endif; ?>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="status">Statut du devis</label>
                            <select name="status" id="status">
                                <option value="all" <?php echo $filterStatus == 'all' ? 'selected' : ''; ?>>Tous les devis</option>
                                <option value="pending" <?php echo $filterStatus == 'pending' ? 'selected' : ''; ?>>En attente</option>
                                <option value="accepted" <?php echo $filterStatus == 'accepted' ? 'selected' : ''; ?>>Acceptés</option>
                                <option value="rejected" <?php echo $filterStatus == 'rejected' ? 'selected' : ''; ?>>Rejetés</option>
                                <option value="converted" <?php echo $filterStatus == 'converted' ? 'selected' : ''; ?>>Convertis</option>
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
            <div class="devis-cards">
                <div class="devis-card card-1">
                    <div class="devis-icon">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <div class="devis-info">
                        <h3><?php echo $devisStats['total_devis']; ?></h3>
                        <p>Total des devis</p>
                    </div>
                </div>
                
                <div class="devis-card card-2">
                    <div class="devis-icon">
                        <i class="fas fa-sun"></i>
                    </div>
                    <div class="devis-info">
                        <h3><?php echo $devisStats['today_devis']; ?></h3>
                        <p>Devis aujourd'hui</p>
                    </div>
                </div>
                
                <div class="devis-card card-3">
                    <div class="devis-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="devis-info">
                        <h3><?php echo formatCurrency($devisStats['total_value']); ?></h3>
                        <p>Valeur totale</p>
                    </div>
                </div>
                
                <div class="devis-card card-4">
                    <div class="devis-icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="devis-info">
                        <h3><?php echo number_format($devisStats['conversion_rate'], 1); ?>%</h3>
                        <p>Taux de conversion</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <div class="action-card" onclick="openNewDevisModal()">
                    <i class="fas fa-plus-circle"></i>
                    <h4>Nouveau Devis</h4>
                    <div class="subtitle">Créer un devis</div>
                </div>
                
                <div class="action-card" onclick="window.open('liste_devis.php', '_blank')">
                    <i class="fas fa-list"></i>
                    <h4>Liste Devis</h4>
                    <div class="subtitle">Voir tous les devis</div>
                </div>
                
                <div class="action-card" onclick="window.open('print_devis_template.php', '_blank')">
                    <i class="fas fa-print"></i>
                    <h4>Imprimer</h4>
                    <div class="subtitle">Modèle d'impression</div>
                </div>
                
                <div class="action-card" onclick="exportDevis()">
                    <i class="fas fa-file-excel"></i>
                    <h4>Exporter</h4>
                    <div class="subtitle">Exporter en Excel</div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-section">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3>Statut des devis</h3>
                        <button class="btn btn-primary btn-small">Rafraîchir</button>
                    </div>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
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
                                    <th>Devis</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topClients as $client): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($client['name']); ?></td>
                                    <td><?php echo $client['quote_count']; ?></td>
                                    <td><?php echo formatCurrency($client['total_value']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Devis Table -->
            <div class="table-section">
                <div class="section-header">
                    <h3>Devis récents</h3>
                    <div>
                        <button class="btn btn-primary" onclick="openNewDevisModal()">
                            <i class="fas fa-plus"></i> Nouveau devis
                        </button>
                        <button class="btn btn-success" onclick="exportDevis()">
                            <i class="fas fa-file-excel"></i> Exporter
                        </button>
                    </div>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>N° Devis</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($devis as $devi): ?>
                        <tr>
                            <td>#DEV-<?php echo $devi['id']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($devi['client_name'] ?? 'Client inconnu'); ?><br>
                                <small><?php echo htmlspecialchars($devi['phone'] ?? ''); ?></small>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($devi['created_at'])); ?></td>
                            <td><?php echo formatCurrency($devi['total']); ?></td>
                            <td>
                                <span class="status <?php echo getDevisStatusClass($devi['status']); ?>">
                                    <?php echo getDevisStatusText($devi['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                    <button class="btn btn-primary btn-small" onclick="viewDevis(<?php echo $devi['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-success btn-small" onclick="updateStatus(<?php echo $devi['id']; ?>, 'accepted')">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn btn-warning btn-small" onclick="updateStatus(<?php echo $devi['id']; ?>, 'converted')">
                                        <i class="fas fa-exchange-alt"></i>
                                    </button>
                                    <button class="btn btn-info btn-small" onclick="printDevis(<?php echo $devi['id']; ?>)">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <button class="btn btn-danger btn-small" onclick="updateStatus(<?php echo $devi['id']; ?>, 'rejected')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
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

    <!-- New Devis Modal -->
    <div id="newDevisModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Nouveau Devis</h3>
                <button class="close-modal" onclick="closeNewDevisModal()">&times;</button>
            </div>
            
            <form id="devisForm" method="POST" action="">
                <input type="hidden" name="action" value="create_devis">
                <input type="hidden" name="items" id="itemsInput" value="[]">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="client">Client *</label>
                        <select id="client" name="client_id" required>
                            <option value="">Sélectionner un client</option>
                            <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="validity">Validité (jours)</label>
                        <input type="number" id="validity" name="validity" value="30" min="1">
                    </div>
                </div>
                
                <h4 style="margin: 20px 0 10px 0;">Articles</h4>
                
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Produit/Description</th>
                            <th width="100">Quantité</th>
                            <th width="150">Prix unitaire</th>
                            <th width="150">Sous-total</th>
                            <th width="50">Action</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTable">
                        <!-- Items will be added here dynamically -->
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="3" style="text-align: right;">Total:</td>
                            <td id="totalAmount">0.00 DA</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="productSelect">Ajouter un produit</label>
                        <select id="productSelect" onchange="addProductFromSelect()">
                            <option value="">Sélectionner un produit</option>
                            <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>" data-name="<?php echo htmlspecialchars($product['name']); ?>" data-price="<?php echo $product['price']; ?>">
                                <?php echo htmlspecialchars($product['name']); ?> - <?php echo formatCurrency($product['price']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group" style="align-self: flex-end;">
                        <button type="button" class="btn btn-primary" onclick="addEmptyItem()">
                            <i class="fas fa-plus"></i> Ajouter une ligne
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3" placeholder="Notes supplémentaires..."></textarea>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn" onclick="closeNewDevisModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer le devis
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Status Chart
        const statusCtx = document.getElementById('statusChart')?.getContext('2d');
        if (statusCtx) {
            const statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($statusChartData['labels'] ?? []); ?>,
                    datasets: [{
                        data: <?php echo json_encode($statusChartData['data'] ?? []); ?>,
                        backgroundColor: <?php echo json_encode($statusChartData['colors'] ?? []); ?>,
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

        // Items management
        let items = [];
        let itemCounter = 0;

        function addEmptyItem() {
            items.push({
                id: itemCounter++,
                product_id: '',
                description: '',
                quantity: 1,
                price: 0
            });
            updateItemsTable();
        }

        function addProductFromSelect() {
            const select = document.getElementById('productSelect');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption.value) {
                items.push({
                    id: itemCounter++,
                    product_id: selectedOption.value,
                    description: selectedOption.dataset.name,
                    quantity: 1,
                    price: parseFloat(selectedOption.dataset.price)
                });
                updateItemsTable();
                select.value = '';
            }
        }

        function removeItem(id) {
            items = items.filter(item => item.id !== id);
            updateItemsTable();
        }

        function updateItem(id, field, value) {
            const item = items.find(item => item.id === id);
            if (item) {
                item[field] = field === 'quantity' || field === 'price' ? parseFloat(value) : value;
                updateItemsTable();
            }
        }

        function updateItemsTable() {
            const table = document.getElementById('itemsTable');
            const totalElement = document.getElementById('totalAmount');
            const itemsInput = document.getElementById('itemsInput');
            
            table.innerHTML = '';
            let total = 0;
            
            items.forEach(item => {
                const subtotal = item.quantity * item.price;
                total += subtotal;
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>
                        <input type="text" 
                               value="${item.description || ''}" 
                               onchange="updateItem(${item.id}, 'description', this.value)"
                               placeholder="Description"
                               style="width: 100%; padding: 5px;">
                    </td>
                    <td>
                        <input type="number" 
                               value="${item.quantity}" 
                               onchange="updateItem(${item.id}, 'quantity', this.value)"
                               min="1"
                               step="1"
                               style="width: 100%; padding: 5px;">
                    </td>
                    <td>
                        <input type="number" 
                               value="${item.price}" 
                               onchange="updateItem(${item.id}, 'price', this.value)"
                               min="0"
                               step="0.01"
                               style="width: 100%; padding: 5px;">
                    </td>
                    <td>${subtotal.toFixed(2)} DA</td>
                    <td>
                        <button type="button" class="btn btn-danger btn-small" onclick="removeItem(${item.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                table.appendChild(row);
            });
            
            totalElement.textContent = total.toFixed(2) + ' DA';
            itemsInput.value = JSON.stringify(items.map(item => ({
                product_id: item.product_id,
                description: item.description,
                quantity: item.quantity,
                price: item.price
            })));
        }

        // Modal functions
        function openNewDevisModal() {
            document.getElementById('newDevisModal').style.display = 'flex';
            items = [];
            itemCounter = 0;
            updateItemsTable();
        }

        function closeNewDevisModal() {
            document.getElementById('newDevisModal').style.display = 'none';
        }

        // Reset filters
        function resetFilters() {
            window.location.href = window.location.pathname;
        }

        // View devis
        function viewDevis(devisId) {
            window.open('view_devis.php?id=' + devisId, '_blank');
        }

        // Update status
        function updateStatus(quoteId, status) {
            if (confirm('Êtes-vous sûr de vouloir modifier le statut de ce devis?')) {
                window.location.href = `?update_status=1&quote_id=${quoteId}&status=${status}`;
            }
        }

        // Print devis
        function printDevis(devisId) {
            window.open('print_devis.php?id=' + devisId, '_blank');
        }

        // Export devis
        function exportDevis() {
            const params = new URLSearchParams(window.location.search);
            window.open('export_devis.php?' + params.toString(), '_blank');
        }

        // Close modal when clicking outside
        document.getElementById('newDevisModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeNewDevisModal();
            }
        });

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
            
            // Add initial empty item
            addEmptyItem();
        });
    </script>
</body>
</html>