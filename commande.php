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



// Récupérer les paramètres de filtrage
$status_filter = $_GET['status'] ?? 'all';
$client_filter = $_GET['client'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Préparer la requête pour récupérer les commandes
$query = "
    SELECT o.*, c.name as client_name, c.email as client_email, c.phone as client_phone,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
    FROM orders o
    LEFT JOIN clients c ON o.client_id = c.id
    WHERE 1=1
";

$params = [];

// Appliquer les filtres
if ($status_filter != 'all') {
    $query .= " AND o.status = ?";
    $params[] = $status_filter;
}

if (!empty($client_filter)) {
    $query .= " AND c.name LIKE ?";
    $params[] = "%$client_filter%";
}

if (!empty($date_from)) {
    $query .= " AND DATE(o.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND DATE(o.created_at) <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY o.deadline ASC, o.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Récupérer tous les clients pour le filtre
$clients = $pdo->query("SELECT id, name FROM clients ORDER BY name")->fetchAll();

// Traitement de la mise à jour du statut
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];
    
    $update_stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $update_stmt->execute([$new_status, $order_id]);
    
    // Redirection pour éviter la resoumission du formulaire
    header("Location: commande.php?success=1");
    exit();
}

// Traitement pour ajouter un produit à une commande
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_product'])) {
    $order_id = $_POST['order_id'];
    $product_id = $_POST['product_id'];
    $quantity = $_POST['quantity'];
    
    // Récupérer le prix du produit
    $product_stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
    $product_stmt->execute([$product_id]);
    $product = $product_stmt->fetch();
    
    if ($product) {
        $price = $product['price'];
        $subtotal = $price * $quantity;
        
        // Ajouter l'article à la commande
        $insert_stmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $insert_stmt->execute([$order_id, $product_id, $quantity, $price, $subtotal]);
        
        // Recalculer le total de la commande
        $total_stmt = $pdo->prepare("
            UPDATE orders SET total = (
                SELECT SUM(subtotal) FROM order_items WHERE order_id = ?
            ) WHERE id = ?
        ");
        $total_stmt->execute([$order_id, $order_id]);
        
        header("Location: commande.php?action=product_added&order_id=" . $order_id);
        exit();
    }
}

// Traitement pour ajouter un article personnalisé
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_custom_item'])) {
    $order_id = $_POST['order_id'];
    $description = $_POST['description'];
    $quantity = $_POST['quantity'];
    $price = $_POST['price'];
    $subtotal = $price * $quantity;
    
    $insert_stmt = $pdo->prepare("
        INSERT INTO order_items (order_id, description, quantity, price, subtotal) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $insert_stmt->execute([$order_id, $description, $quantity, $price, $subtotal]);
    
    // Recalculer le total
    $total_stmt = $pdo->prepare("
        UPDATE orders SET total = (
            SELECT SUM(subtotal) FROM order_items WHERE order_id = ?
        ) WHERE id = ?
    ");
    $total_stmt->execute([$order_id, $order_id]);
    
    header("Location: commande.php?action=custom_added&order_id=" . $order_id);
    exit();
}

// Traitement pour supprimer un article
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_item'])) {
    $item_id = $_POST['item_id'];
    $order_id = $_POST['order_id'];
    
    // Récupérer l'order_id avant suppression
    $stmt = $pdo->prepare("SELECT order_id FROM order_items WHERE id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();
    
    if ($item) {
        $order_id = $item['order_id'];
        
        // Supprimer l'article
        $delete_stmt = $pdo->prepare("DELETE FROM order_items WHERE id = ?");
        $delete_stmt->execute([$item_id]);
        
        // Recalculer le total
        $total_stmt = $pdo->prepare("
            UPDATE orders SET total = COALESCE((
                SELECT SUM(subtotal) FROM order_items WHERE order_id = ?
            ), 0) WHERE id = ?
        ");
        $total_stmt->execute([$order_id, $order_id]);
        
        header("Location: commande.php?action=item_deleted&order_id=" . $order_id);
        exit();
    }
}

// Fonction pour obtenir le texte du statut en français
function getStatusText($status) {
    $statusMap = [
        'pending' => 'En attente',
        'in_production' => 'En production',
        'ready' => 'Prêt',
        'delivered' => 'Livré',
        'cancelled' => 'Annulé'
    ];
    
    return $statusMap[$status] ?? $status;
}

// Fonction pour obtenir la classe CSS du statut
function getStatusClass($status) {
    switch ($status) {
        case 'pending':
            return 'status-pending';
        case 'in_production':
            return 'status-in-progress';
        case 'ready':
            return 'status-ready';
        case 'delivered':
            return 'status-delivered';
        case 'cancelled':
            return 'status-cancelled';
        default:
            return 'status-pending';
    }
}

// Fonction pour obtenir les éléments d'une commande
function getOrderItems($pdo, $order_id) {
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name as product_name, p.description as product_desc 
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    return $stmt->fetchAll();
}

// Fonction pour obtenir tous les produits
function getAllProducts($pdo) {
    return $pdo->query("SELECT * FROM products ORDER BY name")->fetchAll();
}

// Fonction pour vérifier si une échéance est dépassée
function isDeadlinePassed($deadline) {
    if (!$deadline) {
        return false;
    }
    
    $deadlineDate = strtotime($deadline);
    $today = strtotime(date('Y-m-d'));
    
    return $deadlineDate < $today;
}

// Fonction pour formater une date
function formatDate($date) {
    if (!$date) {
        return 'Non définie';
    }
    
    return date('d/m/Y', strtotime($date));
}

// Fonction pour calculer les jours restants
function getDaysRemaining($deadline) {
    if (!$deadline) {
        return null;
    }
    
    $deadlineDate = strtotime($deadline);
    $today = strtotime(date('Y-m-d'));
    $diffTime = $deadlineDate - $today;
    $daysRemaining = floor($diffTime / (60 * 60 * 24));
    return $daysRemaining;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Commandes Clients - Imprimerie Admin</title>
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

        /* Sidebar */
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

        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .filter-title {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
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
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 5px;
        }

        .form-control {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--secondary);
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: var(--gray);
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #27ae60;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 0.85rem;
        }

        /* Orders List */
        .orders-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .order-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: transform 0.3s;
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .order-id {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
        }

        .order-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-in-progress {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-ready {
            background: #d4edda;
            color: #155724;
        }

        .status-delivered {
            background: #cce5ff;
            color: #004085;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .order-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 5px;
        }

        .info-value {
            font-weight: 500;
        }

        .deadline-warning {
            color: var(--danger) !important;
            font-weight: bold !important;
        }

        .deadline-urgent {
            color: var(--warning) !important;
            font-weight: bold !important;
        }

        .order-items {
            margin-bottom: 20px;
        }

        .items-title {
            font-size: 1rem;
            color: var(--primary);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .items-table th {
            background: #f8f9fa;
            padding: 10px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--primary);
            border-bottom: 2px solid #dee2e6;
        }

        .items-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }

        .items-table tr:last-child td {
            border-bottom: none;
        }

        .order-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .status-form {
            display: flex;
            align-items: center;
            gap: 10px;
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
        }

        .modal-header h3 {
            color: var(--primary);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--gray);
        }

        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }

        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .tab.active {
            border-bottom-color: var(--secondary);
            color: var(--secondary);
            font-weight: 600;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
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
            
            .sidebar-menu li a {
                padding: 15px 10px;
                justify-content: center;
            }
            
            .sidebar-menu i {
                margin-right: 0;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .filter-form {
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
            
            .order-info {
                grid-template-columns: 1fr;
            }
            
            .order-actions {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
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
                    <a href="commande_client.php">
                        <i class="fas fa-home"></i>
                        <span>Accueil</span>
                    </a>
                </li>
                <li class="active">
                    <a href="#">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Commandes Clients</span>
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
                        <span>Gestion Clients</span>
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
                <h1>Commandes Clients</h1>
                <small>Gestion des commandes à traiter</small>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Statut de la commande mis à jour avec succès !
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['action'])): ?>
                <?php if ($_GET['action'] == 'product_added'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Produit ajouté à la commande avec succès !
                </div>
                <?php elseif ($_GET['action'] == 'custom_added'): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    Article personnalisé ajouté à la commande avec succès !
                </div>
                <?php elseif ($_GET['action'] == 'item_deleted'): ?>
                <div class="alert alert-info">
                    <i class="fas fa-trash-alt"></i>
                    Article supprimé de la commande avec succès !
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-title">
                    <i class="fas fa-filter"></i>
                    <span>Filtrer les commandes</span>
                </div>
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label for="status">Statut</label>
                        <select name="status" id="status" class="form-control">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>Tous les statuts</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>En attente</option>
                            <option value="in_production" <?php echo $status_filter == 'in_production' ? 'selected' : ''; ?>>En production</option>
                            <option value="ready" <?php echo $status_filter == 'ready' ? 'selected' : ''; ?>>Prêt</option>
                            <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>Livré</option>
                            <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Annulé</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="client">Client</label>
                        <select name="client" id="client" class="form-control">
                            <option value="">Tous les clients</option>
                            <?php foreach ($clients as $client): ?>
                            <option value="<?php echo $client['name']; ?>" <?php echo $client_filter == $client['name'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($client['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="date_from">Date de début</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="form-group">
                        <label for="date_to">Date de fin</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filtrer
                        </button>
                        <a href="commande_client.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Réinitialiser
                        </a>
                        <span style="margin-left: auto; color: var(--gray);">
                            <?php echo count($orders); ?> commande(s) trouvée(s)
                        </span>
                    </div>
                </form>
            </div>

            <!-- Orders List -->
            <div class="orders-container">
                <?php if (empty($orders)): ?>
                <div style="text-align: center; padding: 50px; background: white; border-radius: 10px;">
                    <i class="fas fa-inbox fa-3x" style="color: #ddd; margin-bottom: 20px;"></i>
                    <h3 style="color: var(--gray);">Aucune commande trouvée</h3>
                    <p>Modifiez vos critères de recherche ou créez une nouvelle commande.</p>
                </div>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <?php $order_items = getOrderItems($pdo, $order['id']); ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div>
                                <span class="order-id">#CMD-<?php echo str_pad($order['id'], 5, '0', STR_PAD_LEFT); ?></span>
                                <span style="color: var(--gray); margin-left: 10px;"><?php echo $order['item_count']; ?> article(s)</span>
                            </div>
                            <span class="order-status <?php echo getStatusClass($order['status']); ?>">
                                <?php echo getStatusText($order['status']); ?>
                            </span>
                        </div>

                        <div class="order-info">
                            <div class="info-item">
                                <span class="info-label">Client</span>
                                <span class="info-value"><?php echo htmlspecialchars($order['client_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Téléphone</span>
                                <span class="info-value"><?php echo $order['client_phone'] ?: 'Non renseigné'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email</span>
                                <span class="info-value"><?php echo $order['client_email'] ?: 'Non renseigné'; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Date création</span>
                                <span class="info-value"><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Échéance</span>
                                <?php
                                $deadlineClass = '';
                                $deadlineText = formatDate($order['deadline']);
                                
                                if ($order['deadline']) {
                                    $daysRemaining = getDaysRemaining($order['deadline']);
                                    if ($daysRemaining < 0) {
                                        $deadlineClass = 'deadline-warning';
                                        $deadlineText .= ' <i class="fas fa-clock"></i> (en retard)';
                                    } elseif ($daysRemaining <= 3) {
                                        $deadlineClass = 'deadline-urgent';
                                        $deadlineText .= ' <i class="fas fa-exclamation-triangle"></i> (' . $daysRemaining . ' jour(s))';
                                    } else {
                                        $deadlineText .= ' (' . $daysRemaining . ' jour(s))';
                                    }
                                }
                                ?>
                                <span class="info-value <?php echo $deadlineClass; ?>">
                                    <?php echo $deadlineText; ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total</span>
                                <span class="info-value" style="font-weight: bold; color: var(--primary);">
                                    <?php echo number_format($order['total'], 2, ',', ' '); ?> DA
                                </span>
                            </div>
                        </div>

                        <?php if (!empty($order_items)): ?>
                        <div class="order-items">
                            <div class="items-title">
                                <i class="fas fa-list"></i>
                                <span>Détail de la commande</span>
                            </div>
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>Produit / Description</th>
                                        <th>Quantité</th>
                                        <th>Prix unitaire</th>
                                        <th>Sous-total</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order_items as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $item['product_name'] ?: 'Article personnalisé'; ?></strong>
                                            <?php if ($item['description'] && !$item['product_name']): ?>
                                            <div style="font-size: 0.85rem; color: var(--gray); margin-top: 5px;">
                                                <?php echo nl2br(htmlspecialchars($item['description'])); ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td><?php echo number_format($item['price'], 2, ',', ' '); ?> DA</td>
                                        <td><?php echo number_format($item['subtotal'], 2, ',', ' '); ?> DA</td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <button type="submit" name="delete_item" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer cet article ?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                        <div class="order-actions">
                            <form method="POST" class="status-form">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <select name="status" class="form-control" style="width: auto;">
                                    <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>En attente</option>
                                    <option value="in_production" <?php echo $order['status'] == 'in_production' ? 'selected' : ''; ?>>En production</option>
                                    <option value="ready" <?php echo $order['status'] == 'ready' ? 'selected' : ''; ?>>Prêt</option>
                                    <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Livré</option>
                                    <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Annulé</option>
                                </select>
                                <button type="submit" name="update_status" class="btn btn-sm btn-primary">
                                    <i class="fas fa-save"></i> Mettre à jour
                                </button>
                            </form>
                            <div>
                                <button class="btn btn-sm btn-secondary" onclick="showOrderDetails(<?php echo $order['id']; ?>)">
                                    <i class="fas fa-eye"></i> Voir détails
                                </button>
                                <button class="btn btn-sm btn-success" onclick="showAddProductModal(<?php echo $order['id']; ?>)">
                                    <i class="fas fa-plus"></i> Ajouter produit
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="showAddCustomModal(<?php echo $order['id']; ?>)">
                                    <i class="fas fa-edit"></i> Article perso
                                </button>
                                <a href="facture.php?order_id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-file-invoice"></i> Facture
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div id="orderDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Détails complets de la commande</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div id="orderDetailsContent">
                <!-- Détails seront chargés dynamiquement -->
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div id="addProductModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Ajouter un produit à la commande</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" id="addProductForm">
                <input type="hidden" name="order_id" id="addProductOrderId">
                <div class="form-group">
                    <label for="product_id">Produit</label>
                    <select name="product_id" id="product_id" class="form-control" required>
                        <option value="">Sélectionner un produit</option>
                        <?php 
                        $products = getAllProducts($pdo);
                        foreach ($products as $product): 
                        ?>
                        <option value="<?php echo $product['id']; ?>">
                            <?php echo htmlspecialchars($product['name']); ?> - 
                            <?php echo number_format($product['price'], 2, ',', ' '); ?> DA
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="quantity">Quantité</label>
                    <input type="number" name="quantity" id="quantity" class="form-control" min="1" value="1" required>
                </div>
                <div style="margin-top: 20px; text-align: right;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
                    <button type="submit" name="add_product" class="btn btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Custom Item Modal -->
    <div id="addCustomModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Ajouter un article personnalisé</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" id="addCustomForm">
                <input type="hidden" name="order_id" id="addCustomOrderId">
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description" class="form-control" rows="3" required placeholder="Description de l'article..."></textarea>
                </div>
                <div class="form-group">
                    <label for="custom_quantity">Quantité</label>
                    <input type="number" name="quantity" id="custom_quantity" class="form-control" min="1" value="1" required>
                </div>
                <div class="form-group">
                    <label for="price">Prix unitaire (DA)</label>
                    <input type="number" name="price" id="price" class="form-control" min="0" step="0.01" required>
                </div>
                <div style="margin-top: 20px; text-align: right;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Annuler</button>
                    <button type="submit" name="add_custom_item" class="btn btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Afficher les détails d'une commande
        function showOrderDetails(orderId) {
            // Créer une requête AJAX pour obtenir les détails
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'get_order_details.php?order_id=' + orderId, true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    document.getElementById('orderDetailsContent').innerHTML = xhr.responseText;
                    document.getElementById('orderDetailsModal').style.display = 'flex';
                } else {
                    document.getElementById('orderDetailsContent').innerHTML = 
                        '<div class="alert alert-danger">Erreur lors du chargement des détails.</div>';
                    document.getElementById('orderDetailsModal').style.display = 'flex';
                }
            };
            xhr.send();
        }

        // Afficher la modal pour ajouter un produit
        function showAddProductModal(orderId) {
            document.getElementById('addProductOrderId').value = orderId;
            document.getElementById('addProductModal').style.display = 'flex';
        }

        // Afficher la modal pour ajouter un article personnalisé
        function showAddCustomModal(orderId) {
            document.getElementById('addCustomOrderId').value = orderId;
            document.getElementById('addCustomModal').style.display = 'flex';
        }

        // Fermer toutes les modals
        function closeModal() {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.style.display = 'none';
            });
        }

        // Fermer la modal en cliquant à l'extérieur
        window.onclick = function(event) {
            document.querySelectorAll('.modal').forEach(modal => {
                if (event.target === modal) {
                    closeModal();
                }
            });
        }

        // Annuler une commande (via changement de statut)
        function cancelOrder(orderId) {
            if (confirm('Êtes-vous sûr de vouloir annuler cette commande ?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const orderIdInput = document.createElement('input');
                orderIdInput.type = 'hidden';
                orderIdInput.name = 'order_id';
                orderIdInput.value = orderId;
                
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'status';
                statusInput.value = 'cancelled';
                
                const submitInput = document.createElement('input');
                submitInput.type = 'hidden';
                submitInput.name = 'update_status';
                submitInput.value = '1';
                
                form.appendChild(orderIdInput);
                form.appendChild(statusInput);
                form.appendChild(submitInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>