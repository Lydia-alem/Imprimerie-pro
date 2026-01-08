<?php
// Configuration de la base de données
define('DB_HOST', '127.0.0.1:3306');
define('DB_NAME', 'imprimerie');
define('DB_USER', 'root');
define('DB_PASS', 'admine');

// Démarrer la session
session_start();

// Vérifier si l'utilisateur est connecté
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

// Connexion à la base de données
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

// Créer la table admin_debts si elle n'existe pas
function createDebtsTable($pdo) {
    try {
        $checkTable = $pdo->query("SHOW TABLES LIKE 'admin_debts'");
        if ($checkTable->rowCount() == 0) {
            $sql = "CREATE TABLE admin_debts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                debt_type ENUM('supplier', 'client', 'other') NOT NULL,
                name VARCHAR(200) NOT NULL,
                description TEXT,
                amount DECIMAL(10,2) NOT NULL,
                date_created DATE,
                due_date DATE NOT NULL,
                status ENUM('pending', 'partial', 'overdue', 'paid') DEFAULT 'pending',
                paid_amount DECIMAL(10,2) DEFAULT 0,
                contact_info VARCHAR(200),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            
            $pdo->exec($sql);
        }
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') === false) {
            error_log("Erreur création table: " . $e->getMessage());
        }
    }
}

// Appeler la fonction pour créer la table
createDebtsTable($pdo);

// Gérer les requêtes API (POST) - Doit retourner uniquement JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    // Vérifier l'authentification pour les requêtes POST
    if (!$isLoggedIn) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès non autorisé. Connexion requise.']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'add':
        case 'edit':
            // Valider et nettoyer les données
            $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
            $debt_type = isset($_POST['debt_type']) && in_array($_POST['debt_type'], ['supplier', 'client', 'other']) ? $_POST['debt_type'] : 'other';
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $amount = floatval($_POST['amount'] ?? 0);
            $date_created = $_POST['date_created'] ?? date('Y-m-d');
            $due_date = $_POST['due_date'] ?? date('Y-m-d');
            $status = isset($_POST['status']) && in_array($_POST['status'], ['pending', 'partial', 'overdue', 'paid']) ? $_POST['status'] : 'pending';
            $paid_amount = floatval($_POST['paid_amount'] ?? 0);
            $contact_info = trim($_POST['contact_info'] ?? '');

            // Validation des données
            if (empty($name)) {
                echo json_encode(['success' => false, 'message' => 'Le nom est requis']);
                exit;
            }

            if ($amount <= 0) {
                echo json_encode(['success' => false, 'message' => 'Le montant doit être supérieur à 0']);
                exit;
            }

            if ($paid_amount > $amount) {
                echo json_encode(['success' => false, 'message' => 'Le montant payé ne peut pas être supérieur au montant total']);
                exit;
            }

            try {
                if ($action === 'add') {
                    $sql = "INSERT INTO admin_debts (debt_type, name, description, amount, date_created, due_date, status, paid_amount, contact_info) 
                            VALUES (:debt_type, :name, :description, :amount, :date_created, :due_date, :status, :paid_amount, :contact_info)";
                    $stmt = $pdo->prepare($sql);
                    $success = $stmt->execute([
                        ':debt_type' => $debt_type,
                        ':name' => $name,
                        ':description' => $description,
                        ':amount' => $amount,
                        ':date_created' => $date_created,
                        ':due_date' => $due_date,
                        ':status' => $status,
                        ':paid_amount' => $paid_amount,
                        ':contact_info' => $contact_info
                    ]);
                    $message = $success ? 'Dette ajoutée avec succès' : 'Erreur lors de l\'ajout';
                } else {
                    if ($id <= 0) {
                        echo json_encode(['success' => false, 'message' => 'ID invalide']);
                        exit;
                    }

                    $sql = "UPDATE admin_debts SET 
                            debt_type = :debt_type,
                            name = :name,
                            description = :description,
                            amount = :amount,
                            date_created = :date_created,
                            due_date = :due_date,
                            status = :status,
                            paid_amount = :paid_amount,
                            contact_info = :contact_info
                            WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $success = $stmt->execute([
                        ':debt_type' => $debt_type,
                        ':name' => $name,
                        ':description' => $description,
                        ':amount' => $amount,
                        ':date_created' => $date_created,
                        ':due_date' => $due_date,
                        ':status' => $status,
                        ':paid_amount' => $paid_amount,
                        ':contact_info' => $contact_info,
                        ':id' => $id
                    ]);
                    $message = $success ? 'Dette modifiée avec succès' : 'Erreur lors de la modification';
                }

                echo json_encode(['success' => $success, 'message' => $message]);
                exit;

            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Erreur de base de données: ' . $e->getMessage()]);
                exit;
            }

        case 'delete':
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID invalide']);
                exit;
            }

            try {
                $sql = "DELETE FROM admin_debts WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $success = $stmt->execute([':id' => $id]);
                $message = $success ? 'Dette supprimée avec succès' : 'Erreur lors de la suppression';
                echo json_encode(['success' => $success, 'message' => $message]);
                exit;
            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
                exit;
            }

        case 'mark_paid':
            $id = intval($_POST['id'] ?? 0);
            $paidAmount = floatval($_POST['paid_amount'] ?? 0);

            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID invalide']);
                exit;
            }

            if ($paidAmount <= 0) {
                echo json_encode(['success' => false, 'message' => 'Montant invalide']);
                exit;
            }

            try {
                // Récupérer la dette actuelle
                $sql = "SELECT amount, paid_amount FROM admin_debts WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $id]);
                $debt = $stmt->fetch();

                if ($debt) {
                    $newPaidAmount = $debt['paid_amount'] + $paidAmount;
                    if ($newPaidAmount > $debt['amount']) {
                        echo json_encode(['success' => false, 'message' => 'Le montant payé ne peut pas dépasser le montant total']);
                        exit;
                    }

                    $newStatus = ($newPaidAmount >= $debt['amount']) ? 'paid' : 'partial';

                    $sql = "UPDATE admin_debts SET paid_amount = :paid_amount, status = :status WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $success = $stmt->execute([
                        ':paid_amount' => $newPaidAmount,
                        ':status' => $newStatus,
                        ':id' => $id
                    ]);
                    $message = $success ? 'Paiement enregistré avec succès' : 'Erreur lors de l\'enregistrement';
                } else {
                    $success = false;
                    $message = 'Dette non trouvée';
                }

                echo json_encode(['success' => $success, 'message' => $message]);
                exit;

            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
                exit;
            }

        case 'get_debt':
            // Récupérer une dette spécifique
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => 'ID invalide']);
                exit;
            }

            try {
                $sql = "SELECT * FROM admin_debts WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $id]);
                $debt = $stmt->fetch();

                if ($debt) {
                    echo json_encode(['success' => true, 'data' => $debt]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Dette non trouvée']);
                }
                exit;

            } catch (PDOException $e) {
                error_log("Database error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
                exit;
            }

        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
            exit;
    }
}

// Si ce n'est pas une requête POST, afficher la page HTML
// Récupérer toutes les dettes
function getAllDebts($pdo) {
    try {
        $sql = "SELECT * FROM admin_debts ORDER BY 
                CASE status 
                    WHEN 'overdue' THEN 1
                    WHEN 'pending' THEN 2
                    WHEN 'partial' THEN 3
                    WHEN 'paid' THEN 4
                END,
                due_date ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return [];
    }
}

// Récupérer les statistiques des dettes
function getDebtStats($pdo) {
    try {
        $sql = "SELECT 
                    COALESCE(SUM(amount - paid_amount), 0) as total_debt,
                    COALESCE(SUM(CASE WHEN debt_type = 'supplier' THEN amount - paid_amount ELSE 0 END), 0) as suppliers_debt,
                    COALESCE(SUM(CASE WHEN debt_type = 'client' THEN amount - paid_amount ELSE 0 END), 0) as clients_debt,
                    COALESCE(SUM(CASE WHEN due_date < CURDATE() AND status != 'paid' THEN amount - paid_amount ELSE 0 END), 0) as overdue_debt,
                    COUNT(*) as total_count
                FROM admin_debts
                WHERE status != 'paid'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return [
            'total_debt' => 0,
            'suppliers_debt' => 0,
            'clients_debt' => 0,
            'overdue_debt' => 0,
            'total_count' => 0
        ];
    }
}

// Récupérer les données pour la page
$debts = getAllDebts($pdo);
$stats = getDebtStats($pdo);

// Définir les valeurs par défaut pour les stats
$totalDebt = $stats['total_debt'] ?? 0;
$suppliersDebt = $stats['suppliers_debt'] ?? 0;
$clientsDebt = $stats['clients_debt'] ?? 0;
$overdueDebt = $stats['overdue_debt'] ?? 0;
$totalCount = $stats['total_count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimerie Admin - Gestion des Dettes Administrateur</title>
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
            gap: 20px;
        }

        /* Content */
        .content {
            padding: 30px;
            flex: 1;
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
        .suppliers-debt .stat-icon { background: linear-gradient(135deg, #3498db, #2980b9); }
        .clients-debt .stat-icon { background: linear-gradient(135deg, #f39c12, #d35400); }
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
            gap: 12px;
        }

        .table-header h3 {
            font-size: 1.3rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-wrapper {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-width: 220px;
        }

        .search-clear {
            background: #eee;
            border: 1px solid #ddd;
            padding: 8px 10px;
            border-radius: 4px;
            cursor: pointer;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
        }

        thead {
            background: linear-gradient(90deg, var(--primary), #1a252f);
            color: white;
        }

        tbody tr {
            border-bottom: 1px solid #eee;
            transition: all 0.3s;
        }

        tbody tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-pending { background: #ffebee; color: #c62828; }
        .status-partial { background: #fff3e0; color: #ef6c00; }
        .status-overdue { background: #fce4ec; color: #ad1457; }
        .status-paid { background: #e8f5e9; color: #2e7d32; }

        /* Buttons */
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
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
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

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: var(--danger);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--secondary);
        }

        /* Action buttons */
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

            .main-content {
                margin-left: 70px;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
            }

            .main-content {
                margin-left: 0;
            }

            .stats-cards {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="REM.jpg" alt="Logo Imprimerie">
        </div>
        
        <div class="sidebar-menu">
            <ul>
                <li><a href="index.php"><i class="fas fa-home"></i><span>Tableau de Bord</span></a></li>
                <li><a href="probleme.php"><i class="fas fa-exclamation-triangle"></i><span>Problèmes Urgents</span></a></li>
                <li><a href="commande.php"><i class="fas fa-shopping-cart"></i><span>Commandes</span></a></li>
                <li><a href="devis.php"><i class="fas fa-file-invoice"></i><span>Devis</span></a></li>
                <li><a href="depenses.php"><i class="fas fa-money-bill-wave"></i><span>Dépenses</span></a></li>
                <li><a href="ajustestock.php"><i class="fas fa-box"></i><span>Stock</span></a></li>
                <li><a href="facture.php"><i class="fas fa-file-invoice-dollar"></i><span>Facturation</span></a></li>
                <li><a href="employees.php"><i class="fas fa-user-tie"></i><span>Employés</span></a></li>
                <li><a href="gestion.php"><i class="fas fa-cogs"></i><span>Gestion</span></a></li>
                <li><a href="ventes.php"><i class="fas fa-chart-line"></i><span>Ventes</span></a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i><span>Mon Profil</span></a></li>
                <li class="active"><a href="#"><i class="fas fa-hand-holding-usd"></i><span>Mes Dettes</span></a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i><span>Déconnexion</span></a></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <h1>Gestion de Mes Dettes</h1>
                <small>Dettes que je dois aux fournisseurs et clients</small>
            </div>
            <div class="header-right">
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Ajouter une Dette
                </button>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card total-debt">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="totalDebt"><?php echo number_format($totalDebt, 0, ',', ' '); ?> DA</h3>
                        <p>Dette Totale</p>
                    </div>
                </div>
                
                <div class="stat-card suppliers-debt">
                    <div class="stat-icon">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="suppliersDebt"><?php echo number_format($suppliersDebt, 0, ',', ' '); ?> DA</h3>
                        <p>Dettes Fournisseurs</p>
                    </div>
                </div>
                
                <div class="stat-card clients-debt">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="clientsDebt"><?php echo number_format($clientsDebt, 0, ',', ' '); ?> DA</h3>
                        <p>Dettes Clients</p>
                    </div>
                </div>
                
                <div class="stat-card overdue-debt">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="overdueDebt"><?php echo number_format($overdueDebt, 0, ',', ' '); ?> DA</h3>
                        <p>Dettes Échues</p>
                    </div>
                </div>
            </div>

            <!-- Dettes Table -->
            <div class="table-container">
                <div class="table-header">
                    <h3>
                        <i class="fas fa-list"></i>
                        Liste de Mes Dettes (<?php echo count($debts); ?>)
                    </h3>
                    <div class="search-wrapper">
                        <input type="search" id="searchInput" class="search-input" placeholder="Rechercher par nom, type, description, montant, statut..." aria-label="Rechercher">
                        <button id="clearSearch" class="search-clear" title="Effacer la recherche"><i class="fas fa-times"></i></button>
                    </div>
                </div>
                
                <table id="debtsTable">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Nom</th>
                            <th>Description</th>
                            <th>Montant</th>
                            <th>Date Échéance</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="debtsTableBody">
                        <?php foreach ($debts as $debt): ?>
                            <?php 
                            // Déterminer l'icône et la couleur selon le type
                            $typeIcon = '';
                            $typeColor = '';
                            $typeText = '';
                            
                            switch($debt['debt_type']) {
                                case 'supplier':
                                    $typeIcon = 'fas fa-truck';
                                    $typeColor = '#3498db';
                                    $typeText = 'Fournisseur';
                                    break;
                                case 'client':
                                    $typeIcon = 'fas fa-user';
                                    $typeColor = '#f39c12';
                                    $typeText = 'Client';
                                    break;
                                default:
                                    $typeIcon = 'fas fa-question-circle';
                                    $typeColor = '#95a5a6';
                                    $typeText = 'Autre';
                            }
                            
                            // Déterminer le statut
                            $statusClass = '';
                            $statusText = '';
                            
                            switch($debt['status']) {
                                case 'pending':
                                    $statusClass = 'status-pending';
                                    $statusText = 'En attente';
                                    break;
                                case 'partial':
                                    $statusClass = 'status-partial';
                                    $statusText = 'Partiel';
                                    break;
                                case 'overdue':
                                    $statusClass = 'status-overdue';
                                    $statusText = 'Échu';
                                    break;
                                case 'paid':
                                    $statusClass = 'status-paid';
                                    $statusText = 'Payé';
                                    break;
                            }
                            
                            // Calculer l'intervalle
                            $dueDate = new DateTime($debt['due_date']);
                            $today = new DateTime();
                            $interval = $today->diff($dueDate);
                            $daysRemaining = $interval->days;
                            if ($today > $dueDate) {
                                $daysRemaining = -$daysRemaining;
                            }
                            $isOverdue = $today > $dueDate && $debt['status'] !== 'paid';
                            
                            // Calculer le montant restant
                            $remainingAmount = $debt['amount'] - $debt['paid_amount'];
                            ?>
                            <tr data-id="<?php echo $debt['id']; ?>">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <i class="<?php echo $typeIcon; ?>" style="color: <?php echo $typeColor; ?>;"></i>
                                        <span class="debt-type"><?php echo $typeText; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <strong class="debt-name"><?php echo htmlspecialchars($debt['name']); ?></strong>
                                    <?php if (!empty($debt['contact_info'])): ?>
                                        <br><small class="debt-contact" style="color: var(--gray);"><?php echo htmlspecialchars($debt['contact_info']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="debt-desc"><?php echo htmlspecialchars($debt['description'] ?? ''); ?></td>
                                <td class="debt-amount">
                                    <strong style="font-size: 1.1rem;"><?php echo number_format($debt['amount'], 0, ',', ' '); ?> DA</strong>
                                    <?php if ($debt['paid_amount'] > 0): ?>
                                        <br><small style="color: var(--success);">Payé: <?php echo number_format($debt['paid_amount'], 0, ',', ' '); ?> DA</small>
                                        <br><small style="color: var(--danger);">Reste: <?php echo number_format($remainingAmount, 0, ',', ' '); ?> DA</small>
                                    <?php endif; ?>
                                </td>
                                <td class="debt-due">
                                    <?php echo date('d/m/Y', strtotime($debt['due_date'])); ?>
                                    <?php if ($debt['status'] !== 'paid'): ?>
                                        <?php if ($isOverdue): ?>
                                            <br><small style="color: var(--danger);">En retard: <?php echo abs($daysRemaining); ?> jours</small>
                                        <?php elseif ($daysRemaining === 0): ?>
                                            <br><small style="color: var(--warning);">Échéance aujourd'hui</small>
                                        <?php else: ?>
                                            <br><small style="color: var(--success);"><?php echo $daysRemaining; ?> jours restants</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $statusClass; ?> debt-status"><?php echo $statusText; ?></span>
                                    <br>
                                    <small style="color: var(--gray);" class="debt-date-created"><?php echo date('d/m/Y', strtotime($debt['date_created'] ?? $debt['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn" onclick="editDebt(<?php echo $debt['id']; ?>)" title="Modifier">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="action-btn" onclick="deleteDebt(<?php echo $debt['id']; ?>)" title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <button class="action-btn" onclick="viewDebtDetails(<?php echo $debt['id']; ?>)" title="Voir détails">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($debt['status'] !== 'paid'): ?>
                                            <button class="action-btn" onclick="markAsPaid(<?php echo $debt['id']; ?>, <?php echo $remainingAmount; ?>)" title="Enregistrer paiement">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($debts)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 30px;">
                                    <i class="fas fa-smile" style="font-size: 2rem; color: var(--gray); margin-bottom: 10px;"></i>
                                    <p>Aucune dette enregistrée</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal pour ajouter/modifier une dette -->
    <div id="debtModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Ajouter une Dette</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form id="debtForm" onsubmit="saveDebt(event)">
                <input type="hidden" id="debtId" name="id" value="">
                <input type="hidden" name="action" id="formAction" value="add">
                
                <div class="form-group">
                    <label for="debtType">Type de Dette *</label>
                    <select id="debtType" name="debt_type" class="form-control" required>
                        <option value="">Sélectionner...</option>
                        <option value="supplier">Fournisseur</option>
                        <option value="client">Client</option>
                        <option value="other">Autre</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="debtName">Nom *</label>
                    <input type="text" id="debtName" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="debtDescription">Description</label>
                    <textarea id="debtDescription" name="description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="debtAmount">Montant (DA) *</label>
                    <input type="number" id="debtAmount" name="amount" class="form-control" step="0.01" min="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="debtDate">Date de Création</label>
                    <input type="date" id="debtDate" name="date_created" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="dueDate">Date d'Échéance *</label>
                    <input type="date" id="dueDate" name="due_date" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="debtStatus">Statut *</label>
                    <select id="debtStatus" name="status" class="form-control" required>
                        <option value="pending">En attente</option>
                        <option value="partial">Partiellement payé</option>
                        <option value="overdue">Échu</option>
                        <option value="paid">Payé</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="paidAmount">Montant Payé (DA)</label>
                    <input type="number" id="paidAmount" name="paid_amount" class="form-control" step="0.01" min="0" value="0">
                </div>
                
                <div class="form-group">
                    <label for="contactInfo">Informations de Contact</label>
                    <input type="text" id="contactInfo" name="contact_info" class="form-control">
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 30px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                    <button type="button" class="btn" onclick="closeModal()" style="flex: 1; background: #eee;">
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Use the same page URL for API calls
        const apiUrl = window.location.pathname;

        // Safe JSON parser helper
        async function parseJSONSafe(response) {
            const contentType = response.headers.get('content-type') || '';
            const text = await response.text();
            if (contentType.includes('application/json')) {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    return { success: false, message: 'Invalid JSON response', raw: text };
                }
            } else {
                return { success: false, message: 'Server returned non-JSON response', raw: text };
            }
        }

        // Utility: normalize text (remove diacritics when supported) and lower-case
        function normalizeText(str) {
            if (!str) return '';
            try {
                // Use Unicode normalization and remove diacritics if the engine supports \p{Diacritic}
                return str
                    .toString()
                    .normalize('NFD')
                    .replace(/\p{Diacritic}/gu, '')
                    .toLowerCase()
                    .trim();
            } catch (e) {
                // Fallback: remove common accents with simple replacements and lowercase
                return str.toString()
                    .replace(/[àáâãäå]/gi, 'a')
                    .replace(/[èéêë]/gi, 'e')
                    .replace(/[ìíîï]/gi, 'i')
                    .replace(/[òóôõö]/gi, 'o')
                    .replace(/[ùúûü]/gi, 'u')
                    .replace(/[ç]/gi, 'c')
                    .toLowerCase()
                    .trim();
            }
        }

        // Debounce helper
        function debounce(fn, delay) {
            let timer = null;
            return function (...args) {
                clearTimeout(timer);
                timer = setTimeout(() => fn.apply(this, args), delay);
            };
        }

        // Initialiser la page
        document.addEventListener('DOMContentLoaded', function() {
            // Hook search input with debounce
            const searchInput = document.getElementById('searchInput');
            const clearBtn = document.getElementById('clearSearch');

            const debouncedSearch = debounce(function(e) {
                searchDebts(e.target.value);
            }, 220);

            searchInput.addEventListener('input', debouncedSearch);

            // Clear button
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                searchDebts('');
                searchInput.focus();
            });

            // Pre-fill dates
            const today = new Date().toISOString().split('T')[0];
            const dueDate = new Date();
            dueDate.setDate(dueDate.getDate() + 30);
            const dueDateStr = dueDate.toISOString().split('T')[0];
            document.getElementById('debtDate').value = today;
            document.getElementById('dueDate').value = dueDateStr;

            // Auto-update status based on paid amount
            document.getElementById('paidAmount').addEventListener('change', function() {
                const amount = parseFloat(document.getElementById('debtAmount').value) || 0;
                const paid = parseFloat(this.value) || 0;
                const statusSelect = document.getElementById('debtStatus');

                if (paid >= amount && amount > 0) {
                    statusSelect.value = 'paid';
                } else if (paid > 0) {
                    statusSelect.value = 'partial';
                }
            });

            // Allow pressing Enter in search to perform immediate filter
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    // immediate search without debounce
                    searchDebts(searchInput.value);
                }
            });
        });

        // Rechercher des dettes (client-side)
        function searchDebts(query) {
            const rows = document.querySelectorAll('#debtsTableBody tr');
            const searchTerm = normalizeText(query);

            if (searchTerm === '') {
                rows.forEach(row => row.style.display = '');
                return;
            }

            rows.forEach(row => {
                // Collect searchable fields from the row
                const type = normalizeText(row.querySelector('.debt-type')?.textContent || '');
                const name = normalizeText(row.querySelector('.debt-name')?.textContent || '');
                const contact = normalizeText(row.querySelector('.debt-contact')?.textContent || '');
                const desc = normalizeText(row.querySelector('.debt-desc')?.textContent || '');
                const amount = normalizeText(row.querySelector('.debt-amount')?.textContent || '');
                const status = normalizeText(row.querySelector('.debt-status')?.textContent || '');
                const due = normalizeText(row.querySelector('.debt-due')?.textContent || '');
                const created = normalizeText(row.querySelector('.debt-date-created')?.textContent || '');

                const combined = [type, name, contact, desc, amount, status, due, created].join(' ');

                // Check if all tokens present (multi-word search)
                const tokens = searchTerm.split(/\s+/).filter(Boolean);
                const matches = tokens.every(t => combined.indexOf(t) !== -1);

                row.style.display = matches ? '' : 'none';
            });
        }

        // Ouvrir le modal pour ajouter une dette
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Ajouter une Dette';
            document.getElementById('debtForm').reset();
            document.getElementById('debtId').value = '';
            document.getElementById('formAction').value = 'add';
            
            const today = new Date().toISOString().split('T')[0];
            const dueDate = new Date();
            dueDate.setDate(dueDate.getDate() + 30);
            const dueDateStr = dueDate.toISOString().split('T')[0];
            
            document.getElementById('debtDate').value = today;
            document.getElementById('dueDate').value = dueDateStr;
            document.getElementById('debtStatus').value = 'pending';
            document.getElementById('paidAmount').value = '0';
            
            document.getElementById('debtModal').style.display = 'block';
        }

        // Ouvrir le modal pour modifier une dette
        async function editDebt(id) {
            try {
                const formData = new FormData();
                formData.append('action', 'get_debt');
                formData.append('id', id);

                const response = await fetch(apiUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const result = await parseJSONSafe(response);

                if (result.success) {
                    const debt = result.data;
                    document.getElementById('modalTitle').textContent = 'Modifier la Dette';
                    document.getElementById('debtId').value = debt.id;
                    document.getElementById('formAction').value = 'edit';
                    document.getElementById('debtType').value = debt.debt_type;
                    document.getElementById('debtName').value = debt.name;
                    document.getElementById('debtDescription').value = debt.description || '';
                    document.getElementById('debtAmount').value = debt.amount;
                    document.getElementById('debtDate').value = debt.date_created || (debt.created_at ? debt.created_at.split(' ')[0] : '');
                    document.getElementById('dueDate').value = debt.due_date;
                    document.getElementById('debtStatus').value = debt.status;
                    document.getElementById('paidAmount').value = debt.paid_amount || 0;
                    document.getElementById('contactInfo').value = debt.contact_info || '';

                    document.getElementById('debtModal').style.display = 'block';
                } else {
                    console.error('get_debt error raw:', result.raw || result.message);
                    showNotification(result.message || 'Erreur lors du chargement de la dette', 'error');
                    const row = document.querySelector(`tr[data-id="${id}"]`);
                    if (row) extractDataFromRow(row, id);
                }
            } catch (error) {
                console.error('Error:', error);
                const row = document.querySelector(`tr[data-id="${id}"]`);
                if (row) extractDataFromRow(row, id);
                else showNotification('Erreur lors du chargement de la dette', 'error');
            }
        }

        // Méthode de secours pour extraire les données de la ligne
        function extractDataFromRow(row, id) {
            const cells = row.querySelectorAll('td');
            const typeText = cells[0].querySelector('span').textContent;
            const name = cells[1].querySelector('strong').textContent;
            const contactInfo = cells[1].querySelector('small')?.textContent || '';
            const description = cells[2].textContent;
            const amountText = cells[3].querySelector('strong').textContent;
            const amount = parseFloat(amountText.replace(/[^\d.]/g, '')) || 0;
            const dueDateText = cells[4].textContent.split('\n')[0].trim();
            const dueDateParts = dueDateText.split('/');
            const dueDate = dueDateParts.length === 3 ? `${dueDateParts[2]}-${dueDateParts[1]}-${dueDateParts[0]}` : '';
            const dateCreatedText = cells[5].querySelector('small')?.textContent || '';
            const dateParts = dateCreatedText.split('/');
            const dateCreated = (dateParts.length === 3) ? `${dateParts[2]}-${dateParts[1]}-${dateParts[0]}` : '';
            const statusText = cells[5].querySelector('.status-badge').textContent;
            let paidAmount = 0;
            const paidAmountElements = cells[3].querySelectorAll('small');
            if (paidAmountElements.length > 0 && paidAmountElements[0].textContent.includes('Payé:')) {
                paidAmount = parseFloat(paidAmountElements[0].textContent.replace(/[^\d.]/g, '')) || 0;
            }
            let debtType = 'other';
            if (typeText === 'Fournisseur') debtType = 'supplier';
            else if (typeText === 'Client') debtType = 'client';
            let status = 'pending';
            if (statusText === 'Partiel') status = 'partial';
            else if (statusText === 'Échu') status = 'overdue';
            else if (statusText === 'Payé') status = 'paid';

            document.getElementById('modalTitle').textContent = 'Modifier la Dette';
            document.getElementById('debtId').value = id;
            document.getElementById('formAction').value = 'edit';
            document.getElementById('debtType').value = debtType;
            document.getElementById('debtName').value = name.trim();
            document.getElementById('debtDescription').value = description.trim();
            document.getElementById('debtAmount').value = amount;
            document.getElementById('debtDate').value = dateCreated;
            document.getElementById('dueDate').value = dueDate;
            document.getElementById('debtStatus').value = status;
            document.getElementById('paidAmount').value = paidAmount;
            document.getElementById('contactInfo').value = contactInfo.trim();

            document.getElementById('debtModal').style.display = 'block';
        }

        // Sauvegarder une dette
        async function saveDebt(event) {
            event.preventDefault();

            const amount = parseFloat(document.getElementById('debtAmount').value);
            const paidAmount = parseFloat(document.getElementById('paidAmount').value) || 0;

            if (amount <= 0) {
                showNotification('Le montant doit être supérieur à 0', 'error');
                return;
            }

            if (paidAmount > amount) {
                showNotification('Le montant payé ne peut pas être supérieur au montant total', 'error');
                return;
            }

            const form = document.getElementById('debtForm');
            const formData = new FormData(form);

            try {
                const response = await fetch(apiUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const result = await parseJSONSafe(response);

                if (result.success) {
                    showNotification(result.message, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 900);
                } else {
                    console.error('Save error raw:', result.raw || result.message);
                    showNotification(result.message || 'Erreur inconnue', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Erreur lors de l\'enregistrement: ' + error.message, 'error');
            }
        }

        // Supprimer une dette
        async function deleteDebt(id) {
            if (confirm('Êtes-vous sûr de vouloir supprimer cette dette ? Cette action est irréversible.')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                try {
                    const response = await fetch(apiUrl, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    });

                    const result = await parseJSONSafe(response);

                    if (result.success) {
                        showNotification(result.message, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 900);
                    } else {
                        console.error('Delete error raw:', result.raw || result.message);
                        showNotification(result.message, 'error');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    showNotification('Erreur lors de la suppression', 'error');
                }
            }
        }

        // Voir les détails d'une dette
        function viewDebtDetails(id) {
            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (!row) return;

            const cells = row.querySelectorAll('td');

            const details = `
                <strong>Type:</strong> ${cells[0].querySelector('span').textContent}<br>
                <strong>Nom:</strong> ${cells[1].querySelector('strong').textContent}<br>
                <strong>Contact:</strong> ${cells[1].querySelector('small')?.textContent || 'Non spécifié'}<br>
                <strong>Description:</strong> ${cells[2].textContent || 'Non spécifiée'}<br>
                <strong>Montant total:</strong> ${cells[3].querySelector('strong').textContent}<br>
                <strong>Montant payé:</strong> ${cells[3].querySelectorAll('small')[0]?.textContent || '0 DA'}<br>
                <strong>Montant restant:</strong> ${cells[3].querySelectorAll('small')[1]?.textContent || cells[3].querySelector('strong').textContent}<br>
                <strong>Date création:</strong> ${cells[5].querySelector('small').textContent}<br>
                <strong>Date échéance:</strong> ${cells[4].textContent.split('\n')[0]}<br>
                <strong>Statut:</strong> ${cells[5].querySelector('.status-badge').textContent}
            `;

            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 2000;
            `;

            modal.innerHTML = `
                <div style="background: white; padding: 30px; border-radius: 10px; max-width: 500px; width: 90%;">
                    <h3 style="margin-bottom: 20px; color: var(--primary);">Détails de la Dette</h3>
                    <div style="margin-bottom: 20px; line-height: 1.6;">${details}</div>
                    <button onclick="this.parentElement.parentElement.remove()" 
                            style="padding: 10px 20px; background: var(--secondary); color: white; border: none; border-radius: 5px; cursor: pointer;">
                        Fermer
                    </button>
                </div>
            `;

            document.body.appendChild(modal);
        }

        // Marquer une dette comme payée
        async function markAsPaid(id, remainingAmount) {
            const paidAmount = prompt(`Entrez le montant payé (DA). Montant restant: ${formatCurrency(remainingAmount)} DA`, remainingAmount);

            if (paidAmount !== null && paidAmount !== '') {
                const amount = parseFloat(paidAmount);
                if (!isNaN(amount) && amount > 0 && amount <= remainingAmount) {
                    const formData = new FormData();
                    formData.append('action', 'mark_paid');
                    formData.append('id', id);
                    formData.append('paid_amount', amount);

                    try {
                        const response = await fetch(apiUrl, {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin'
                        });

                        const result = await parseJSONSafe(response);

                        if (result.success) {
                            showNotification(result.message, 'success');
                            setTimeout(() => {
                                location.reload();
                            }, 900);
                        } else {
                            console.error('mark_paid error raw:', result.raw || result.message);
                            showNotification(result.message, 'error');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        showNotification('Erreur lors de l\'enregistrement du paiement', 'error');
                    }
                } else {
                    alert('Montant invalide! Le montant doit être positif et inférieur ou égal au montant restant.');
                }
            }
        }

        // Fermer le modal
        function closeModal() {
            document.getElementById('debtModal').style.display = 'none';
        }

        // Afficher une notification
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 20px;
                background: ${type === 'success' ? '#2ecc71' : '#e74c3c'};
                color: white;
                border-radius: 8px;
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
                z-index: 10000;
                display: flex;
                align-items: center;
                gap: 10px;
                animation: slideIn 0.3s ease;
            `;

            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
                <span>${message}</span>
            `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    if (notification.parentElement) notification.parentElement.removeChild(notification);
                }, 300);
            }, 3000);

            if (!document.querySelector('#notification-styles')) {
                const style = document.createElement('style');
                style.id = 'notification-styles';
                style.textContent = `
                    @keyframes slideIn {
                        from {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                        to {
                            transform: translateX(0);
                            opacity: 1;
                        }
                    }
                    @keyframes slideOut {
                        from {
                            transform: translateX(0);
                            opacity: 1;
                        }
                        to {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                    }
                `;
                document.head.appendChild(style);
            }
        }

        // Fonctions utilitaires
        function formatCurrency(amount) {
            return amount.toLocaleString('fr-FR', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }) + ' DA';
        }

        // Fermer le modal en cliquant en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('debtModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>