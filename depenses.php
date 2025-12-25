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

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']) || true; // For demo purposes

// Get filter parameters
$filterCategory = $_GET['category'] ?? 'all';
$filterMonth = $_GET['month'] ?? date('Y-m');
$search = $_GET['search'] ?? '';

// Create simple expenses table if not exists
$pdo->exec("
    CREATE TABLE IF NOT EXISTS general_depenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        numero VARCHAR(50) UNIQUE,
        titre VARCHAR(200) NOT NULL,
        description TEXT,
        categorie VARCHAR(100) NOT NULL,
        montant DECIMAL(10,2) NOT NULL,
        date_depense DATE NOT NULL,
        mode_paiement ENUM('espèces', 'chèque', 'virement', 'carte', 'mobile') DEFAULT 'espèces',
        beneficiaire VARCHAR(200),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// Insert default categories if not exist
$defaultCategories = [
    'Loyer',
    'Électricité',
    'Eau',
    'Internet',
    'Téléphone',
    'Salaires',
    'Matériel Bureau',
    'Transport',
    'Marketing',
    'Maintenance',
    'Impôts',
    'Assurances',
    'Formation',
    'Logiciels',
    'Fournitures',
    'Nourriture',
    'Voyages',
    'Équipement',
    'Services',
    'Divers'
];

// Function to insert categories
foreach ($defaultCategories as $category) {
    // Check if category exists in expenses
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM general_depenses WHERE categorie = ? LIMIT 1");
    $stmt->execute([$category]);
    $result = $stmt->fetch();
    
    // We'll use a different approach - just ensure categories are available for dropdown
}

// Fetch expense statistics
function getExpenseStats($pdo, $month = null) {
    $stats = [];
    
    // Build query with optional month filter
    $monthCondition = $month ? "AND DATE_FORMAT(date_depense, '%Y-%m') = :month" : "";
    $params = [];
    
    if ($month) {
        $params[':month'] = $month;
    }
    
    // Total expenses
    $query = "SELECT COALESCE(SUM(montant), 0) as total, COUNT(*) as count FROM general_depenses WHERE 1=1 $monthCondition";
    $stmt = $pdo->prepare($query);
    if ($month) {
        $stmt->bindParam(':month', $month);
    }
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['total'] = $result['total'];
    $stats['count'] = $result['count'];
    
    // Average expense
    $stats['average'] = $stats['count'] > 0 ? $stats['total'] / $stats['count'] : 0;
    
    // Today's expenses
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(montant), 0) as total FROM general_depenses WHERE date_depense = ?");
    $stmt->execute([$today]);
    $stats['today'] = $stmt->fetch()['total'];
    
    // This month expenses (if not already filtered by month)
    if (!$month) {
        $currentMonth = date('Y-m');
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(montant), 0) as total FROM general_depenses WHERE DATE_FORMAT(date_depense, '%Y-%m') = ?");
        $stmt->execute([$currentMonth]);
        $stats['this_month'] = $stmt->fetch()['total'];
    } else {
        $stats['this_month'] = $stats['total'];
    }
    
    // Last month expenses
    $lastMonth = date('Y-m', strtotime('-1 month'));
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(montant), 0) as total FROM general_depenses WHERE DATE_FORMAT(date_depense, '%Y-%m') = ?");
    $stmt->execute([$lastMonth]);
    $stats['last_month'] = $stmt->fetch()['total'];
    
    // Top categories
    $categoryQuery = "SELECT categorie, SUM(montant) as total, COUNT(*) as count 
                     FROM general_depenses WHERE 1=1 $monthCondition 
                     GROUP BY categorie ORDER BY total DESC LIMIT 5";
    $stmt = $pdo->prepare($categoryQuery);
    if ($month) {
        $stmt->bindParam(':month', $month);
    }
    $stmt->execute();
    $stats['top_categories'] = $stmt->fetchAll();
    
    return $stats;
}

// Fetch all unique categories
function getAllCategories($pdo) {
    $stmt = $pdo->query("SELECT DISTINCT categorie FROM general_depenses ORDER BY categorie");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    
    // If no categories yet, return default ones
    if (empty($categories)) {
        global $defaultCategories;
        return $defaultCategories;
    }
    
    return $categories;
}

// Fetch months for filter
function getMonths($pdo) {
    $stmt = $pdo->query("SELECT DISTINCT DATE_FORMAT(date_depense, '%Y-%m') as month FROM general_depenses ORDER BY month DESC");
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

// Fetch expenses with filters
function getExpenses($pdo, $category = 'all', $month = null, $search = '', $limit = 100) {
    $query = "SELECT * FROM general_depenses WHERE 1=1";
    $params = [];
    
    if ($category !== 'all') {
        $query .= " AND categorie = :category";
        $params[':category'] = $category;
    }
    
    if ($month) {
        $query .= " AND DATE_FORMAT(date_depense, '%Y-%m') = :month";
        $params[':month'] = $month;
    }
    
    if (!empty($search)) {
        $query .= " AND (titre LIKE :search OR description LIKE :search OR beneficiaire LIKE :search OR numero LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $query .= " ORDER BY date_depense DESC, created_at DESC LIMIT :limit";
    
    $stmt = $pdo->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_STR);
    }
    
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

// Format currency
function formatCurrency($amount) {
    return number_format($amount, 2, ',', ' ') . ' DA';
}

// Generate expense number
function generateExpenseNumber($pdo) {
    $year = date('Y');
    $month = date('m');
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM general_depenses WHERE DATE_FORMAT(created_at, '%Y-%m') = ?");
    $stmt->execute([date('Y-m')]);
    $count = $stmt->fetch()['count'] + 1;
    
    return "DEP-$year$month-" . str_pad($count, 4, '0', STR_PAD_LEFT);
}

// Get all data
if ($isLoggedIn) {
    $expenseStats = getExpenseStats($pdo, $filterMonth !== 'all' ? $filterMonth : null);
    $categories = getAllCategories($pdo);
    $months = getMonths($pdo);
    $expenses = getExpenses($pdo, $filterCategory, ($filterMonth !== 'all' ? $filterMonth : null), $search);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_depense') {
        try {
            // Generate expense number
            $numero = generateExpenseNumber($pdo);
            
            $stmt = $pdo->prepare("
                INSERT INTO general_depenses (
                    numero, titre, description, categorie, montant,
                    date_depense, mode_paiement, beneficiaire, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $numero,
                $_POST['titre'],
                $_POST['description'],
                $_POST['categorie'],
                $_POST['montant'],
                $_POST['date_depense'],
                $_POST['mode_paiement'],
                $_POST['beneficiaire'],
                $_POST['notes']
            ]);
            
            $successMessage = "Dépense ajoutée avec succès! Numéro: $numero";
            
            // Refresh data
            $expenseStats = getExpenseStats($pdo, $filterMonth !== 'all' ? $filterMonth : null);
            $categories = getAllCategories($pdo);
            $months = getMonths($pdo);
            $expenses = getExpenses($pdo, $filterCategory, ($filterMonth !== 'all' ? $filterMonth : null), $search);
            
        } catch (Exception $e) {
            $errorMessage = "Erreur lors de l'ajout: " . $e->getMessage();
        }
    }
    elseif ($_POST['action'] === 'delete_depense' && isset($_POST['depense_id'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM general_depenses WHERE id = ?");
            $stmt->execute([$_POST['depense_id']]);
            
            $successMessage = "Dépense supprimée avec succès!";
            
            // Refresh data
            $expenseStats = getExpenseStats($pdo, $filterMonth !== 'all' ? $filterMonth : null);
            $expenses = getExpenses($pdo, $filterCategory, ($filterMonth !== 'all' ? $filterMonth : null), $search);
            
        } catch (Exception $e) {
            $errorMessage = "Erreur lors de la suppression: " . $e->getMessage();
        }
    }
    elseif ($_POST['action'] === 'add_category') {
        try {
            // We don't need to insert into a separate table anymore
            // Just refresh the categories list from existing expenses
            $categories = getAllCategories($pdo);
            $successMessage = "Catégorie disponible dans la liste maintenant!";
            
        } catch (Exception $e) {
            $errorMessage = "Erreur: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimerie - Gestion des Dépenses Générales</title>
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

        /* Search and Filters */
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

        .filter-group input,
        .filter-group select {
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .stat-card p {
            color: var(--gray);
            margin-bottom: 15px;
        }

        .stat-card .amount {
            font-size: 2rem;
            font-weight: bold;
            color: var(--success);
        }

        .stat-card-1 { border-left: 5px solid #f093fb; }
        .stat-card-2 { border-left: 5px solid #4facfe; }
        .stat-card-3 { border-left: 5px solid #43e97b; }
        .stat-card-4 { border-left: 5px solid #fa709a; }

        /* Quick Actions */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            font-size: 1.1rem;
            color: var(--dark);
            margin-bottom: 10px;
        }

        /* Summary Section */
        .summary-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }

        .summary-section h3 {
            font-size: 1.3rem;
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .categories-list {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }

        .category-item {
            background: #f8f9fa;
            padding: 10px 20px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .category-color {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: var(--secondary);
        }

        /* Table Section */
        .table-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h3 {
            font-size: 1.3rem;
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
            background-color: #f9f9f9;
        }

        /* Badges */
        .category-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
            background: #e9ecef;
            color: #495057;
        }

        .payment-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
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
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
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
            
            .filter-row,
            .form-row {
                flex-direction: column;
                gap: 10px;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .actions-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
            }
        }

        /* Amount styling */
        .amount-cell {
            font-weight: bold;
            font-size: 1.1rem;
        }

        .amount-large {
            color: var(--danger);
        }

        .amount-medium {
            color: var(--warning);
        }

        .amount-small {
            color: var(--success);
        }

        /* Date styling */
        .date-cell {
            font-size: 0.9rem;
            color: #666;
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
                <li>
                    <a href="facture.php">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>Facturation</span>
                    </a>
                </li>
                <li class="active">
                    <a href="#">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Dépenses</span>
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
                <h1>Gestion des Dépenses Générales</h1>
                <small>Suivi complet de toutes les sorties d'argent</small>
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

            <!-- Search and Filters -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="search">Rechercher</label>
                            <input type="text" id="search" name="search" 
                                   value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Numéro, titre, description...">
                        </div>
                        
                        <div class="filter-group">
                            <label for="category">Catégorie</label>
                            <select name="category" id="category">
                                <option value="all" <?php echo $filterCategory == 'all' ? 'selected' : ''; ?>>Toutes les catégories</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filterCategory == $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="month">Mois</label>
                            <select name="month" id="month">
                                <option value="all" <?php echo $filterMonth == 'all' ? 'selected' : ''; ?>>Tous les mois</option>
                                <?php foreach ($months as $m): ?>
                                <option value="<?php echo $m; ?>" <?php echo $filterMonth == $m ? 'selected' : ''; ?>>
                                    <?php echo date('F Y', strtotime($m . '-01')); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filtrer
                            </button>
                            <button type="button" class="btn" onclick="resetFilters()">
                                <i class="fas fa-redo"></i> Réinitialiser
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card stat-card-1">
                    <h3>Total Dépenses</h3>
                    <p>Montant total des dépenses</p>
                    <div class="amount"><?php echo formatCurrency($expenseStats['total']); ?></div>
                    <small><?php echo $expenseStats['count']; ?> dépenses</small>
                </div>
                
                <div class="stat-card stat-card-2">
                    <h3>Dépenses Aujourd'hui</h3>
                    <p>Sorties d'argent aujourd'hui</p>
                    <div class="amount"><?php echo formatCurrency($expenseStats['today']); ?></div>
                </div>
                
                <div class="stat-card stat-card-3">
                    <h3>Mois Actuel</h3>
                    <p>Dépenses ce mois-ci</p>
                    <div class="amount"><?php echo formatCurrency($expenseStats['this_month']); ?></div>
                    <?php if ($expenseStats['last_month'] > 0): ?>
                    <small><?php echo ($expenseStats['this_month'] > $expenseStats['last_month'] ? '+' : '-'); ?>
                        <?php echo number_format(abs(($expenseStats['this_month'] - $expenseStats['last_month']) / $expenseStats['last_month'] * 100), 1); ?>% vs mois dernier
                    </small>
                    <?php endif; ?>
                </div>
                
                <div class="stat-card stat-card-4">
                    <h3>Moyenne par Dépense</h3>
                    <p>Montant moyen par dépense</p>
                    <div class="amount"><?php echo formatCurrency($expenseStats['average']); ?></div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="actions-grid">
                <div class="action-card" onclick="openNewDepenseModal()">
                    <i class="fas fa-plus-circle"></i>
                    <h4>Nouvelle Dépense</h4>
                    <div class="subtitle">Ajouter une sortie</div>
                </div>
                
                <div class="action-card" onclick="openNewCategoryModal()">
                    <i class="fas fa-tag"></i>
                    <h4>Ajouter Catégorie</h4>
                    <div class="subtitle">Nouvelle catégorie</div>
                </div>
                
                <div class="action-card" onclick="exportToExcel()">
                    <i class="fas fa-file-excel"></i>
                    <h4>Exporter Excel</h4>
                    <div class="subtitle">Télécharger rapport</div>
                </div>
                
                <div class="action-card" onclick="printReport()">
                    <i class="fas fa-print"></i>
                    <h4>Imprimer Rapport</h4>
                    <div class="subtitle">Version imprimable</div>
                </div>
            </div>

            <!-- Top Categories Summary -->
            <div class="summary-section">
                <h3>Top Catégories de Dépenses</h3>
                <div class="categories-list">
                    <?php foreach ($expenseStats['top_categories'] as $cat): ?>
                    <div class="category-item">
                        <div class="category-color"></div>
                        <span><?php echo htmlspecialchars($cat['categorie']); ?></span>
                        <strong><?php echo formatCurrency($cat['total']); ?></strong>
                        <small>(<?php echo $cat['count']; ?>)</small>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($expenseStats['top_categories'])): ?>
                    <p style="color: #999; text-align: center; width: 100%;">Aucune dépense enregistrée</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Expenses Table -->
            <div class="table-section">
                <div class="section-header">
                    <h3>Liste des Dépenses (<?php echo count($expenses); ?>)</h3>
                    <div>
                        <button class="btn btn-primary" onclick="openNewDepenseModal()">
                            <i class="fas fa-plus"></i> Nouvelle dépense
                        </button>
                        <button class="btn btn-success" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i> Exporter
                        </button>
                    </div>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Numéro</th>
                            <th>Titre</th>
                            <th>Catégorie</th>
                            <th>Montant</th>
                            <th>Date</th>
                            <th>Paiement</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $exp): 
                            $amountClass = '';
                            if ($exp['montant'] > 10000) $amountClass = 'amount-large';
                            elseif ($exp['montant'] > 5000) $amountClass = 'amount-medium';
                            else $amountClass = 'amount-small';
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($exp['numero']); ?></strong>
                            </td>
                            <td>
                                <div><?php echo htmlspecialchars($exp['titre']); ?></div>
                                <?php if ($exp['description']): ?>
                                <small style="color: #666;"><?php echo htmlspecialchars(substr($exp['description'], 0, 50)); ?>...</small>
                                <?php endif; ?>
                                <?php if ($exp['beneficiaire']): ?>
                                <div><small>Bénéficiaire: <?php echo htmlspecialchars($exp['beneficiaire']); ?></small></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="category-badge">
                                    <?php echo htmlspecialchars($exp['categorie']); ?>
                                </span>
                            </td>
                            <td class="amount-cell <?php echo $amountClass; ?>">
                                <?php echo formatCurrency($exp['montant']); ?>
                            </td>
                            <td class="date-cell">
                                <?php echo date('d/m/Y', strtotime($exp['date_depense'])); ?>
                            </td>
                            <td>
                                <span class="payment-badge">
                                    <?php echo htmlspecialchars(ucfirst($exp['mode_paiement'])); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <button class="btn btn-info btn-small" onclick="viewDetails(<?php echo $exp['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-danger btn-small" onclick="confirmDelete(<?php echo $exp['id']; ?>, '<?php echo htmlspecialchars($exp['numero']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($expenses)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 40px;">
                                <i class="fas fa-money-bill-wave fa-3x" style="color: #ddd; margin-bottom: 15px;"></i>
                                <h4 style="color: #999;">Aucune dépense trouvée</h4>
                                <p style="color: #aaa;">Commencez par ajouter votre première dépense</p>
                                <button class="btn btn-primary" onclick="openNewDepenseModal()">
                                    <i class="fas fa-plus"></i> Ajouter une dépense
                                </button>
                            </td>
                        </tr>
                        <?php endif; ?>
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

    <!-- New Expense Modal -->
    <div id="newDepenseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Nouvelle Dépense</h3>
                <button class="close-modal" onclick="closeNewDepenseModal()">&times;</button>
            </div>
            
            <form id="depenseForm" method="POST" action="">
                <input type="hidden" name="action" value="add_depense">
                
                <div class="form-group">
                    <label for="titre">Titre de la dépense *</label>
                    <input type="text" id="titre" name="titre" required
                           placeholder="Ex: Paiement loyer décembre, Facture internet, etc.">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="montant">Montant (DA) *</label>
                        <input type="number" id="montant" name="montant" required
                               step="0.01" min="0" placeholder="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_depense">Date *</label>
                        <input type="date" id="date_depense" name="date_depense" required
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="categorie">Catégorie *</label>
                        <select id="categorie" name="categorie" required>
                            <option value="">Sélectionner une catégorie</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #666;">Ou tapez pour créer une nouvelle catégorie</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="mode_paiement">Mode de paiement *</label>
                        <select id="mode_paiement" name="mode_paiement" required>
                            <option value="espèces">Espèces</option>
                            <option value="chèque">Chèque</option>
                            <option value="virement">Virement bancaire</option>
                            <option value="carte">Carte bancaire</option>
                            <option value="mobile">Paiement mobile</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="beneficiaire">Bénéficiaire (optionnel)</label>
                    <input type="text" id="beneficiaire" name="beneficiaire"
                           placeholder="Nom de la personne/entreprise">
                </div>
                
                <div class="form-group">
                    <label for="description">Description (optionnel)</label>
                    <textarea id="description" name="description" rows="3"
                              placeholder="Description détaillée de la dépense..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes (optionnel)</label>
                    <textarea id="notes" name="notes" rows="2"
                              placeholder="Informations supplémentaires..."></textarea>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn" onclick="closeNewDepenseModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer la dépense
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- New Category Modal -->
    <div id="newCategoryModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>Ajouter une Catégorie</h3>
                <button class="close-modal" onclick="closeNewCategoryModal()">&times;</button>
            </div>
            
            <form id="categoryForm" method="POST" action="">
                <input type="hidden" name="action" value="add_category">
                
                <div class="form-group">
                    <label for="new_category">Nom de la nouvelle catégorie *</label>
                    <input type="text" id="new_category" name="new_category" required
                           placeholder="Ex: Loyer, Internet, Transport, etc.">
                </div>
                
                <div style="margin-bottom: 20px;">
                    <p style="color: #666; font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i> 
                        La catégorie sera disponible dans la liste dès que vous l'utiliserez dans une dépense.
                    </p>
                </div>
                
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn" onclick="closeNewCategoryModal()">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Ajouter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <div class="modal-header">
                <h3>Confirmer la suppression</h3>
                <button class="close-modal" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div style="padding: 20px;">
                <p id="deleteMessage">Êtes-vous sûr de vouloir supprimer cette dépense?</p>
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn" onclick="closeDeleteModal()">Annuler</button>
                    <form method="POST" action="" style="margin: 0;">
                        <input type="hidden" name="action" value="delete_depense">
                        <input type="hidden" name="depense_id" id="deleteDepenseId">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Supprimer
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Modal functions
        function openNewDepenseModal() {
            document.getElementById('newDepenseModal').style.display = 'flex';
        }

        function closeNewDepenseModal() {
            document.getElementById('newDepenseModal').style.display = 'none';
        }

        function openNewCategoryModal() {
            document.getElementById('newCategoryModal').style.display = 'flex';
        }

        function closeNewCategoryModal() {
            document.getElementById('newCategoryModal').style.display = 'none';
        }

        function openDeleteModal() {
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Reset filters
        function resetFilters() {
            window.location.href = window.location.pathname;
        }

        // View details (placeholder)
        function viewDetails(depenseId) {
            alert('Détails de la dépense #' + depenseId + '\n\nCette fonctionnalité affichera tous les détails de la dépense.');
        }

        // Confirm delete
        function confirmDelete(depenseId, numero) {
            document.getElementById('deleteMessage').textContent = 
                `Êtes-vous sûr de vouloir supprimer la dépense ${numero}? Cette action est irréversible.`;
            document.getElementById('deleteDepenseId').value = depenseId;
            openDeleteModal();
        }

        // Export to Excel (placeholder)
        function exportToExcel() {
            const params = new URLSearchParams(window.location.search);
            alert('Fonction d\'export Excel à implémenter.\nLes données filtrées seront exportées.');
            // window.open('export_depenses.php?' + params.toString(), '_blank');
        }

        // Print report
        function printReport() {
            window.print();
        }

        // Close modal when clicking outside
        document.getElementById('newDepenseModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeNewDepenseModal();
            }
        });

        document.getElementById('newCategoryModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeNewCategoryModal();
            }
        });

        document.getElementById('deleteModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // Auto-format amount input
        document.addEventListener('DOMContentLoaded', function() {
            const amountInput = document.getElementById('montant');
            if (amountInput) {
                amountInput.addEventListener('blur', function() {
                    this.value = parseFloat(this.value).toFixed(2);
                });
            }
            
            // Allow typing new categories in select
            const categorySelect = document.getElementById('categorie');
            if (categorySelect) {
                let typingTimer;
                const doneTypingInterval = 1000;
                
                categorySelect.addEventListener('input', function() {
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(() => {
                        const value = this.value.trim();
                        if (value && !Array.from(this.options).some(opt => opt.value === value)) {
                            // Create new option
                            const newOption = document.createElement('option');
                            newOption.value = value;
                            newOption.textContent = value + ' (Nouveau)';
                            this.appendChild(newOption);
                            this.value = value;
                            
                            // Show message
                            const message = document.createElement('small');
                            message.style.color = 'green';
                            message.style.display = 'block';
                            message.textContent = 'Nouvelle catégorie sera créée';
                            
                            const existingMsg = this.parentNode.querySelector('.new-category-msg');
                            if (existingMsg) {
                                existingMsg.remove();
                            }
                            
                            message.className = 'new-category-msg';
                            this.parentNode.appendChild(message);
                        }
                    }, doneTypingInterval);
                });
            }
            
            // Set today's date by default
            if (!document.getElementById('date_depense')?.value) {
                document.getElementById('date_depense').value = new Date().toISOString().split('T')[0];
            }
        });
    </script>
</body>
</html>