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
$search = $_GET['search'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$clientId = $_GET['client_id'] ?? '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;

// Calculate offset for pagination
$offset = ($page - 1) * $perPage;

// Fetch all clients for filter dropdown
function getClients($pdo) {
    $stmt = $pdo->query("SELECT id, name FROM clients ORDER BY name");
    return $stmt->fetchAll();
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

// Format currency
function formatCurrency($amount) {
    if ($amount === null || $amount === '') return '0,00 DA';
    return number_format($amount, 2, ',', ' ') . ' DA';
}

// Format date
function formatDate($dateString) {
    if (!$dateString) return '';
    return date('d/m/Y', strtotime($dateString));
}

// Get devis with filters and pagination
function getDevisList($pdo, $filterStatus, $search, $startDate, $endDate, $clientId, $offset, $perPage) {
    $query = "
        SELECT q.*, c.name as client_name, c.email, c.phone, c.address
        FROM quotes q
        LEFT JOIN clients c ON q.client_id = c.id
        WHERE 1=1
    ";
    
    $countQuery = "
        SELECT COUNT(*) as total_count
        FROM quotes q
        LEFT JOIN clients c ON q.client_id = c.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($filterStatus !== 'all') {
        $query .= " AND q.status = :status";
        $countQuery .= " AND q.status = :status";
        $params[':status'] = $filterStatus;
    }
    
    if (!empty($search)) {
        $query .= " AND (
            c.name LIKE :search OR 
            c.email LIKE :search OR 
            c.phone LIKE :search OR
            q.id LIKE :search
        )";
        $countQuery .= " AND (
            c.name LIKE :search OR 
            c.email LIKE :search OR 
            c.phone LIKE :search OR
            q.id LIKE :search
        )";
        $params[':search'] = "%$search%";
    }
    
    if (!empty($startDate)) {
        $query .= " AND DATE(q.created_at) >= :start_date";
        $countQuery .= " AND DATE(q.created_at) >= :start_date";
        $params[':start_date'] = $startDate;
    }
    
    if (!empty($endDate)) {
        $query .= " AND DATE(q.created_at) <= :end_date";
        $countQuery .= " AND DATE(q.created_at) <= :end_date";
        $params[':end_date'] = $endDate;
    }
    
    if (!empty($clientId) && is_numeric($clientId)) {
        $query .= " AND q.client_id = :client_id";
        $countQuery .= " AND q.client_id = :client_id";
        $params[':client_id'] = $clientId;
    }
    
    $query .= " ORDER BY q.created_at DESC LIMIT :offset, :perPage";
    
    // Get total count
    $stmt = $pdo->prepare($countQuery);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $totalCount = $stmt->fetch()['total_count'];
    
    // Get paginated results
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
    $stmt->execute();
    
    $results = $stmt->fetchAll();
    
    return [
        'devis' => $results,
        'total' => $totalCount,
        'pages' => ceil($totalCount / $perPage)
    ];
}

// Get devis items
function getDevisItems($pdo, $devisId) {
    $stmt = $pdo->prepare("
        SELECT qi.*, p.name as product_name
        FROM quote_items qi
        LEFT JOIN products p ON qi.product_id = p.id
        WHERE qi.quote_id = ?
        ORDER BY qi.id
    ");
    $stmt->execute([$devisId]);
    return $stmt->fetchAll();
}

// Handle status update
if (isset($_POST['update_status']) && isset($_POST['devis_id']) && isset($_POST['status'])) {
    try {
        $stmt = $pdo->prepare("UPDATE quotes SET status = ? WHERE id = ?");
        $stmt->execute([$_POST['status'], $_POST['devis_id']]);
        
        $successMessage = "Statut mis à jour avec succès!";
        
    } catch (Exception $e) {
        $errorMessage = "Erreur lors de la mise à jour: " . $e->getMessage();
    }
}

// Handle delete devis
if (isset($_GET['delete_devis']) && isset($_GET['id'])) {
    try {
        // First, delete the quote items
        $stmt = $pdo->prepare("DELETE FROM quote_items WHERE quote_id = ?");
        $stmt->execute([$_GET['id']]);
        
        // Then delete the quote
        $stmt = $pdo->prepare("DELETE FROM quotes WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        
        $successMessage = "Devis supprimé avec succès!";
        
    } catch (Exception $e) {
        $errorMessage = "Erreur lors de la suppression: " . $e->getMessage();
    }
}

// Get all data
if ($isLoggedIn) {
    $clients = getClients($pdo);
    $devisData = getDevisList($pdo, $filterStatus, $search, $startDate, $endDate, $clientId, $offset, $perPage);
    $devisList = $devisData['devis'];
    $totalDevis = $devisData['total'];
    $totalPages = $devisData['pages'];
    
    // Add sample data if no devis exist (for testing)
    if ($totalDevis == 0) {
        // Check if there are clients to create sample devis
        if (count($clients) > 0) {
            // You can uncomment this to create sample devis for testing
            /*
            try {
                $sampleClientId = $clients[0]['id'];
                $pdo->beginTransaction();
                
                // Insert sample quote
                $stmt = $pdo->prepare("
                    INSERT INTO quotes (client_id, status, total, created_at) 
                    VALUES (?, 'pending', 10000, NOW())
                ");
                $stmt->execute([$sampleClientId]);
                $quoteId = $pdo->lastInsertId();
                
                // Insert sample quote item
                $stmt = $pdo->prepare("
                    INSERT INTO quote_items (quote_id, description, quantity, price, subtotal)
                    VALUES (?, 'Exemple d\'article', 2, 5000, 10000)
                ");
                $stmt->execute([$quoteId]);
                
                $pdo->commit();
                
                // Refresh data
                $devisData = getDevisList($pdo, $filterStatus, $search, $startDate, $endDate, $clientId, $offset, $perPage);
                $devisList = $devisData['devis'];
                $totalDevis = $devisData['total'];
                $totalPages = $devisData['pages'];
                
            } catch (Exception $e) {
                $pdo->rollBack();
            }
            */
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimerie - Liste des Devis</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
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

        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .summary-card h3 {
            font-size: 1.5rem;
            margin-bottom: 5px;
            color: var(--primary);
        }

        .summary-card p {
            color: var(--gray);
            font-size: 0.9rem;
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
            background: #f8f9fa;
        }

        tr:hover {
            background: #f8f9fa;
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

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            padding: 20px 0;
        }

        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            text-decoration: none;
            color: var(--dark);
        }

        .pagination a:hover {
            background: var(--secondary);
            color: white;
            border-color: var(--secondary);
        }

        .pagination .active {
            background: var(--secondary);
            color: white;
            border-color: var(--secondary);
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

        .devis-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .devis-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }

        .devis-info h4 {
            margin-bottom: 10px;
            color: var(--primary);
        }

        .devis-info p {
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .items-table {
            width: 100%;
            margin: 20px 0;
        }

        .items-table th {
            background: #f8f9fa;
            padding: 10px;
        }

        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .total-row {
            font-weight: bold;
            font-size: 1.1rem;
            background: #f8f9fa;
        }

        /* Status Form */
        .status-form {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }

        .status-form select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #e0e0e0;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
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
            
            .devis-details {
                grid-template-columns: 1fr;
            }
            
            .table-section {
                overflow-x: auto;
            }
            
            table {
                min-width: 800px;
            }
        }

        @media (max-width: 576px) {
            .header {
                flex-direction: column;
                gap: 15px;
            }
            
            .filter-row {
                flex-direction: column;
            }
            
            .section-header {
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
                <li>
                    <a href="devis.php">
                        <i class="fas fa-file-contract"></i>
                        <span>Devis</span>
                    </a>
                </li>
                <li class="active">
                    <a href="#">
                        <i class="fas fa-list"></i>
                        <span>Liste Devis</span>
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
                <h1>Liste des Devis</h1>
                <small>Gestion complète des devis</small>
            </div>
            <div class="header-right">
                <button class="btn btn-primary" onclick="window.location.href='devis.php'">
                    <i class="fas fa-plus"></i> Nouveau Devis
                </button>
                <button class="btn btn-success" onclick="exportDevis()">
                    <i class="fas fa-file-excel"></i> Exporter
                </button>
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

            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card">
                    <h3><?php echo $totalDevis; ?></h3>
                    <p>Total des devis</p>
                </div>
                
                <div class="summary-card">
                    <h3>
                        <?php 
                        $totalValue = 0;
                        foreach ($devisList as $devis) {
                            $totalValue += $devis['total'] ?? 0;
                        }
                        echo formatCurrency($totalValue);
                        ?>
                    </h3>
                    <p>Valeur totale</p>
                </div>
                
                <div class="summary-card">
                    <h3>
                        <?php 
                        $convertedCount = 0;
                        foreach ($devisList as $devis) {
                            if ($devis['status'] === 'converted') {
                                $convertedCount++;
                            }
                        }
                        echo $convertedCount;
                        ?>
                    </h3>
                    <p>Devis convertis</p>
                </div>
                
                <div class="summary-card">
                    <h3>
                        <?php 
                        $pendingCount = 0;
                        foreach ($devisList as $devis) {
                            if ($devis['status'] === 'pending') {
                                $pendingCount++;
                            }
                        }
                        echo $pendingCount;
                        ?>
                    </h3>
                    <p>En attente</p>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="status">Statut</label>
                            <select name="status" id="status">
                                <option value="all" <?php echo $filterStatus == 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                                <option value="pending" <?php echo $filterStatus == 'pending' ? 'selected' : ''; ?>>En attente</option>
                                <option value="accepted" <?php echo $filterStatus == 'accepted' ? 'selected' : ''; ?>>Acceptés</option>
                                <option value="rejected" <?php echo $filterStatus == 'rejected' ? 'selected' : ''; ?>>Rejetés</option>
                                <option value="converted" <?php echo $filterStatus == 'converted' ? 'selected' : ''; ?>>Convertis</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="client_id">Client</label>
                            <select name="client_id" id="client_id">
                                <option value="">Tous les clients</option>
                                <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>" <?php echo $clientId == $client['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="start_date">Date de début</label>
                            <input type="date" name="start_date" id="start_date" value="<?php echo $startDate; ?>">
                        </div>
                        
                        <div class="filter-group">
                            <label for="end_date">Date de fin</label>
                            <input type="date" name="end_date" id="end_date" value="<?php echo $endDate; ?>">
                        </div>
                    </div>
                    
                    <div class="filter-row" style="margin-top: 15px;">
                        <div class="filter-group">
                            <label for="search">Recherche</label>
                            <input type="text" name="search" id="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Rechercher par client, email, téléphone...">
                        </div>
                        
                        <div class="filter-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Appliquer
                            </button>
                            <button type="button" class="btn" onclick="resetFilters()">
                                <i class="fas fa-redo"></i> Réinitialiser
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Devis Table -->
            <div class="table-section">
                <div class="section-header">
                    <h3>Liste des devis (<?php echo $totalDevis; ?> résultats)</h3>
                    <div>
                        <button class="btn btn-primary" onclick="printAllDevis()">
                            <i class="fas fa-print"></i> Imprimer
                        </button>
                    </div>
                </div>
                
                <?php if (empty($devisList)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-contract"></i>
                    <h3>Aucun devis trouvé</h3>
                    <p>Créez votre premier devis en cliquant sur le bouton ci-dessous</p>
                    <button class="btn btn-primary" onclick="window.location.href='devis.php'" style="margin-top: 20px;">
                        <i class="fas fa-plus"></i> Créer un devis
                    </button>
                </div>
                <?php else: ?>
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
                        <?php foreach ($devisList as $devis): ?>
                        <tr>
                            <td><strong>#DEV-<?php echo $devis['id']; ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($devis['client_name'] ?? 'N/A'); ?></strong><br>
                                <?php if (!empty($devis['email'])): ?>
                                <small><?php echo htmlspecialchars($devis['email']); ?></small><br>
                                <?php endif; ?>
                                <?php if (!empty($devis['phone'])): ?>
                                <small><?php echo htmlspecialchars($devis['phone']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatDate($devis['created_at']); ?></td>
                            <td><strong><?php echo formatCurrency($devis['total']); ?></strong></td>
                            <td>
                                <span class="status <?php echo getDevisStatusClass($devis['status']); ?>">
                                    <?php echo getDevisStatusText($devis['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                    <button class="btn btn-primary btn-small" onclick="viewDevisDetails(<?php echo $devis['id']; ?>)">
                                        <i class="fas fa-eye"></i> Détails
                                    </button>
                                    <button class="btn btn-info btn-small" onclick="printDevis(<?php echo $devis['id']; ?>)">
                                        <i class="fas fa-print"></i>
                                    </button>
                                    <button class="btn btn-warning btn-small" onclick="openStatusModal(<?php echo $devis['id']; ?>, '<?php echo $devis['status']; ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-small" onclick="confirmDelete(<?php echo $devis['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                        <i class="fas fa-angle-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <?php else: ?>
            <div style="text-align: center; padding: 100px;">
                <h2>Veuillez vous connecter</h2>
                <button class="btn btn-primary" onclick="location.href='login.php'">Se connecter</button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- View Devis Modal -->
    <div id="viewDevisModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Détails du Devis <span id="modalDevisNumber"></span></h3>
                <button class="close-modal" onclick="closeViewModal()">&times;</button>
            </div>
            
            <div id="modalLoading" style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin fa-2x" style="color: var(--secondary);"></i>
                <p>Chargement des détails...</p>
            </div>
            
            <div id="modalContent" style="display: none;">
                <div class="devis-details">
                    <div class="devis-info">
                        <h4>Informations client</h4>
                        <p><strong>Nom:</strong> <span id="modalClientName"></span></p>
                        <p><strong>Email:</strong> <span id="modalClientEmail"></span></p>
                        <p><strong>Téléphone:</strong> <span id="modalClientPhone"></span></p>
                        <p><strong>Adresse:</strong> <span id="modalClientAddress"></span></p>
                    </div>
                    
                    <div class="devis-info">
                        <h4>Informations devis</h4>
                        <p><strong>Date:</strong> <span id="modalDevisDate"></span></p>
                        <p><strong>Statut:</strong> <span id="modalDevisStatus"></span></p>
                        <p><strong>Total:</strong> <span id="modalDevisTotal"></span></p>
                    </div>
                </div>
                
                <h4>Articles</h4>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Quantité</th>
                            <th>Prix unitaire</th>
                            <th>Sous-total</th>
                        </tr>
                    </thead>
                    <tbody id="modalItemsTable">
                        <!-- Items will be loaded here -->
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="3" style="text-align: right;"><strong>Total:</strong></td>
                            <td id="modalTotalAmount"></td>
                        </tr>
                    </tfoot>
                </table>
                
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn btn-info" onclick="printCurrentDevis()">
                        <i class="fas fa-print"></i> Imprimer
                    </button>
                    <button type="button" class="btn" onclick="closeViewModal()">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Modifier le statut</h3>
                <button class="close-modal" onclick="closeStatusModal()">&times;</button>
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" name="devis_id" id="statusDevisId">
                
                <div class="form-group">
                    <label for="newStatus">Nouveau statut</label>
                    <select name="status" id="newStatus" required>
                        <option value="pending">En attente</option>
                        <option value="accepted">Accepté</option>
                        <option value="rejected">Rejeté</option>
                        <option value="converted">Converti en commande</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="statusNotes">Notes (optionnel)</label>
                    <textarea id="statusNotes" name="notes" rows="3" placeholder="Notes sur le changement de statut..."></textarea>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn" onclick="closeStatusModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // View devis details
        async function viewDevisDetails(devisId) {
            try {
                const modal = document.getElementById('viewDevisModal');
                const loading = document.getElementById('modalLoading');
                const content = document.getElementById('modalContent');
                
                // Show modal and loading
                modal.style.display = 'flex';
                loading.style.display = 'block';
                content.style.display = 'none';
                
                // Fetch devis details
                const response = await fetch(`get_devis_details.php?id=${devisId}`);
                const data = await response.json();
                
                if (data.success) {
                    const devis = data.devis;
                    
                    // Update modal content
                    document.getElementById('modalDevisNumber').textContent = `#DEV-${devis.id}`;
                    document.getElementById('modalClientName').textContent = devis.client_name || 'N/A';
                    document.getElementById('modalClientEmail').textContent = devis.email || 'N/A';
                    document.getElementById('modalClientPhone').textContent = devis.phone || 'N/A';
                    document.getElementById('modalClientAddress').textContent = devis.address || 'N/A';
                    document.getElementById('modalDevisDate').textContent = formatDate(devis.created_at);
                    document.getElementById('modalDevisStatus').textContent = getStatusText(devis.status);
                    document.getElementById('modalDevisTotal').textContent = formatCurrency(devis.total);
                    
                    // Update items table
                    const itemsTable = document.getElementById('modalItemsTable');
                    itemsTable.innerHTML = '';
                    
                    let total = 0;
                    data.items.forEach(item => {
                        const subtotal = item.quantity * item.price;
                        total += subtotal;
                        
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${item.product_name || item.description || 'N/A'}</td>
                            <td>${item.quantity}</td>
                            <td>${formatCurrency(item.price)}</td>
                            <td>${formatCurrency(subtotal)}</td>
                        `;
                        itemsTable.appendChild(row);
                    });
                    
                    document.getElementById('modalTotalAmount').textContent = formatCurrency(total);
                    
                    // Hide loading, show content
                    loading.style.display = 'none';
                    content.style.display = 'block';
                    
                    // Store current devis ID for printing
                    window.currentDevisId = devisId;
                } else {
                    loading.innerHTML = `
                        <div class="alert alert-danger">
                            <p>Erreur: ${data.message}</p>
                            <button class="btn btn-small" onclick="closeViewModal()">Fermer</button>
                        </div>
                    `;
                }
            } catch (error) {
                const loading = document.getElementById('modalLoading');
                loading.innerHTML = `
                    <div class="alert alert-danger">
                        <p>Erreur de connexion: ${error.message}</p>
                        <button class="btn btn-small" onclick="closeViewModal()">Fermer</button>
                    </div>
                `;
            }
        }

        // Open status modal
        function openStatusModal(devisId, currentStatus) {
            document.getElementById('statusDevisId').value = devisId;
            document.getElementById('newStatus').value = currentStatus;
            document.getElementById('statusModal').style.display = 'flex';
        }

        // Close modals
        function closeViewModal() {
            document.getElementById('viewDevisModal').style.display = 'none';
            // Reset modal content for next use
            document.getElementById('modalLoading').style.display = 'block';
            document.getElementById('modalContent').style.display = 'none';
            document.getElementById('modalLoading').innerHTML = `
                <i class="fas fa-spinner fa-spin fa-2x" style="color: var(--secondary);"></i>
                <p>Chargement des détails...</p>
            `;
        }

        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
        }

        // Print current devis
        function printCurrentDevis() {
            if (window.currentDevisId) {
                printDevis(window.currentDevisId);
            }
        }

        // Print devis
        function printDevis(devisId) {
            window.open(`print_devis.php?id=${devisId}`, '_blank');
        }

        // Print all filtered devis
        function printAllDevis() {
            const params = new URLSearchParams(window.location.search);
            window.open(`print_all_devis.php?${params.toString()}`, '_blank');
        }

        // Export devis
        function exportDevis() {
            const params = new URLSearchParams(window.location.search);
            window.open(`export_devis.php?${params.toString()}`, '_blank');
        }

        // Confirm delete
        function confirmDelete(devisId) {
            if (confirm('Êtes-vous sûr de vouloir supprimer ce devis? Cette action est irréversible.')) {
                window.location.href = `?delete_devis=1&id=${devisId}`;
            }
        }

        // Reset filters
        function resetFilters() {
            window.location.href = window.location.pathname;
        }

        // Helper functions
        function formatCurrency(amount) {
            if (!amount && amount !== 0) return '0,00 DA';
            return new Intl.NumberFormat('fr-DZ', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount) + ' DA';
        }

        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR');
        }

        function getStatusText(status) {
            const statusMap = {
                'pending': 'En attente',
                'accepted': 'Accepté',
                'rejected': 'Rejeté',
                'converted': 'Converti en commande'
            };
            return statusMap[status] || status;
        }

        // Close modals when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    if (this.id === 'viewDevisModal') {
                        closeViewModal();
                    } else if (this.id === 'statusModal') {
                        closeStatusModal();
                    }
                }
            });
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
        });
    </script>
</body>
</html>