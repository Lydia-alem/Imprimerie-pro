<?php
// DÉBUT DU CODE PHP - SESSION DOIT ÊTRE AU DÉBUT
session_start();

// Connexion à la base de données
$host = '127.0.0.1:3306';
$dbname = 'imprimerie';
$username = 'root';
$password = 'admine';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Vérifier si l'utilisateur est connecté - rediriger vers login si non
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

// ID de l'admin connecté
$adminId = $_SESSION['user_id'];

// Récupérer les informations de l'admin
try {
    $sql = "SELECT * FROM admin_users WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $adminId]);
    $admin = $stmt->fetch();
    
    if (!$admin) {
        die("Administrateur non trouvé");
    }
    
    // Récupérer les informations employé si elles existent
    $employeeSql = "SELECT * FROM employees WHERE user_id = :user_id";
    $employeeStmt = $pdo->prepare($employeeSql);
    $employeeStmt->execute([':user_id' => $adminId]);
    $employee = $employeeStmt->fetch();
    
    // Fusionner les données admin et employee
    if ($employee) {
        $admin = array_merge($admin, $employee);
    }
    
    // Séparer le nom complet en prénom et nom
    if (!empty($admin['full_name'])) {
        $nameParts = explode(' ', $admin['full_name'], 2);
        $admin['first_name'] = $nameParts[0] ?? '';
        $admin['last_name'] = $nameParts[1] ?? '';
    } else {
        $admin['first_name'] = '';
        $admin['last_name'] = '';
    }
    
} catch(PDOException $e) {
    die("Erreur lors de la récupération des informations: " . $e->getMessage());
}

// Récupérer les statistiques
try {
    // Compter le nombre de commandes
    $ordersSql = "SELECT COUNT(*) as order_count FROM orders";
    $ordersStmt = $pdo->query($ordersSql);
    $orderCount = $ordersStmt->fetch()['order_count'];
    
    // Compter le nombre de clients
    $clientsSql = "SELECT COUNT(*) as client_count FROM clients";
    $clientsStmt = $pdo->query($clientsSql);
    $clientCount = $clientsStmt->fetch()['client_count'];
    
    // Compter le nombre d'employés
    $employeesSql = "SELECT COUNT(*) as employee_count FROM admin_users WHERE role = 'employee'";
    $employeesStmt = $pdo->query($employeesSql);
    $employeeCount = $employeesStmt->fetch()['employee_count'];
    
    // Récupérer l'activité récente
    $activitySql = "SELECT 
        'Nouvelle commande' as type, 
        CONCAT('Commande #', id, ' créée') as description,
        created_at as time
        FROM orders 
        ORDER BY created_at DESC 
        LIMIT 5";
    $activityStmt = $pdo->query($activitySql);
    $activities = $activityStmt->fetchAll();
    
} catch(PDOException $e) {
    $orderCount = 0;
    $clientCount = 0;
    $employeeCount = 0;
    $activities = [];
}

// Variables pour les messages
$message = '';
$messageType = '';

// Gérer les actions de mise à jour
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'update_profile') {
            // Mettre à jour le profil
            $firstName = $_POST['first_name'] ?? '';
            $lastName = $_POST['last_name'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            $department = $_POST['department'] ?? '';
            $position = $_POST['position'] ?? '';
            
            $fullName = trim($firstName . ' ' . $lastName);
            
            try {
                // Mettre à jour admin_users
                $sql = "UPDATE admin_users 
                        SET full_name = :full_name, email = :email 
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':full_name' => $fullName,
                    ':email' => $email,
                    ':id' => $adminId
                ]);
                
                // Vérifier si l'employé existe déjà
                $checkSql = "SELECT id FROM employees WHERE user_id = :user_id";
                $checkStmt = $pdo->prepare($checkSql);
                $checkStmt->execute([':user_id' => $adminId]);
                
                if ($checkStmt->rowCount() > 0) {
                    // Mettre à jour l'employé existant
                    $updateSql = "UPDATE employees 
                                 SET position = :position, department = :department, phone = :phone 
                                 WHERE user_id = :user_id";
                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute([
                        ':position' => $position,
                        ':department' => $department,
                        ':phone' => $phone,
                        ':user_id' => $adminId
                    ]);
                } else {
                    // Créer un nouvel enregistrement employé
                    $insertSql = "INSERT INTO employees (user_id, position, department, phone) 
                                 VALUES (:user_id, :position, :department, :phone)";
                    $insertStmt = $pdo->prepare($insertSql);
                    $insertStmt->execute([
                        ':user_id' => $adminId,
                        ':position' => $position,
                        ':department' => $department,
                        ':phone' => $phone
                    ]);
                }
                
                // Mettre à jour les données de session
                $_SESSION['full_name'] = $fullName;
                $_SESSION['email'] = $email;
                
                // Recharger les informations de l'admin
                $sql = "SELECT * FROM admin_users WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $adminId]);
                $admin = $stmt->fetch();
                
                // Recharger les informations employé
                $employeeSql = "SELECT * FROM employees WHERE user_id = :user_id";
                $employeeStmt = $pdo->prepare($employeeSql);
                $employeeStmt->execute([':user_id' => $adminId]);
                $employee = $employeeStmt->fetch();
                
                if ($employee) {
                    $admin = array_merge($admin, $employee);
                }
                
                // Mettre à jour le nom séparé
                $nameParts = explode(' ', $admin['full_name'], 2);
                $admin['first_name'] = $nameParts[0] ?? '';
                $admin['last_name'] = $nameParts[1] ?? '';
                
                $message = "Profil mis à jour avec succès!";
                $messageType = "success";
                
            } catch(PDOException $e) {
                $message = "Erreur lors de la mise à jour du profil: " . $e->getMessage();
                $messageType = "error";
            }
        }
        elseif ($action === 'change_password') {
            // Changer le mot de passe
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            // Vérifier si les nouveaux mots de passe correspondent
            if ($newPassword !== $confirmPassword) {
                $message = "Les nouveaux mots de passe ne correspondent pas!";
                $messageType = "error";
            } elseif (strlen($newPassword) < 6) {
                $message = "Le mot de passe doit contenir au moins 6 caractères!";
                $messageType = "error";
            } else {
                // Vérifier le mot de passe actuel
                try {
                    $sql = "SELECT password FROM admin_users WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([':id' => $adminId]);
                    $result = $stmt->fetch();
                    
                    // Vérifier le mot de passe (en clair ou hashé)
                    $passwordValid = false;
                    if ($result) {
                        // Essayer d'abord avec password_verify (pour les mots de passe hashés)
                        if (password_verify($currentPassword, $result['password'])) {
                            $passwordValid = true;
                        }
                        // Sinon vérifier en clair (pour compatibilité)
                        elseif ($currentPassword === $result['password']) {
                            $passwordValid = true;
                        }
                    }
                    
                    if ($passwordValid) {
                        // Mettre à jour le mot de passe (hasher)
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        $updateSql = "UPDATE admin_users SET password = :password WHERE id = :id";
                        $updateStmt = $pdo->prepare($updateSql);
                        $updateStmt->execute([
                            ':password' => $hashedPassword,
                            ':id' => $adminId
                        ]);
                        
                        $message = "Mot de passe changé avec succès!";
                        $messageType = "success";
                    } else {
                        $message = "Mot de passe actuel incorrect!";
                        $messageType = "error";
                    }
                } catch(PDOException $e) {
                    $message = "Erreur lors du changement de mot de passe: " . $e->getMessage();
                    $messageType = "error";
                }
            }
        }
        elseif ($action === 'update_preferences') {
            // Mettre à jour les préférences (simplifié)
            $theme = $_POST['theme'] ?? 'light';
            $notifications = isset($_POST['notifications']) ? 1 : 0;
            $language = $_POST['language'] ?? 'fr';
            
            try {
                // Dans une version complète, vous auriez une table settings
                $message = "Préférences mises à jour avec succès!";
                $messageType = "success";
                
            } catch(PDOException $e) {
                $message = "Erreur lors de la mise à jour des préférences: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimerie Admin - Mon Profil</title>
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
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
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

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: var(--primary);
            color: white;
            transition: all 0.3s;
            box-shadow: var(--shadow);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .sidebar-header i {
            font-size: 2rem;
            color: var(--secondary);
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .sidebar-menu {
            padding: 15px 0;
        }

        .sidebar-menu ul {
            list-style: none;
        }

        .sidebar-menu li {
            padding: 12px 20px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
        }

        .sidebar-menu li:hover {
            background: rgba(255, 255, 255, 0.1);
            cursor: pointer;
        }

        .sidebar-menu li.active {
            background: var(--secondary);
            border-left: 4px solid var(--accent);
        }

        .sidebar-menu a {
            text-decoration: none;
            color: white;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
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

        .header-left p {
            color: var(--gray);
            margin-top: 5px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            position: relative;
        }

        .user-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .user-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: var(--shadow-lg);
            width: 200px;
            display: none;
            z-index: 100;
            margin-top: 10px;
        }

        .user-dropdown.show {
            display: block;
        }

        .user-dropdown a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: var(--dark);
            text-decoration: none;
            transition: all 0.3s;
        }

        .user-dropdown a:hover {
            background: #f5f7fa;
        }

        .user-dropdown i {
            margin-right: 10px;
            color: var(--secondary);
        }

        /* Content Styles */
        .content {
            padding: 30px;
            flex: 1;
        }

        /* Profile Container */
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }

        @media (max-width: 992px) {
            .profile-container {
                grid-template-columns: 1fr;
            }
        }

        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .profile-avatar {
            position: relative;
            margin-bottom: 20px;
        }

        .profile-avatar img {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #f5f7fa;
        }

        .avatar-upload {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: var(--secondary);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .avatar-upload input {
            display: none;
        }

        .profile-info h3 {
            font-size: 1.5rem;
            margin-bottom: 5px;
            color: var(--primary);
        }

        .profile-info p {
            color: var(--gray);
            margin-bottom: 15px;
        }

        .profile-role {
            display: inline-block;
            background: var(--secondary);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--gray);
            margin-top: 5px;
        }

        /* Info Card */
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .info-card h3 {
            font-size: 1.3rem;
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-card h3 i {
            color: var(--secondary);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-group input, .form-group select, .form-group textarea {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .form-group input:disabled {
            background: #f5f7fa;
            cursor: not-allowed;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 25px;
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
            transform: translateY(-2px);
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

        /* Activity Card */
        .activity-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            display: flex;
            padding: 15px 0;
            border-bottom: 1px solid #f5f7fa;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f5f7fa;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: var(--secondary);
        }

        .activity-content h4 {
            font-size: 1rem;
            margin-bottom: 5px;
            color: var(--primary);
        }

        .activity-content p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .activity-time {
            font-size: 0.8rem;
            color: var(--gray);
            margin-top: 5px;
        }

        /* Security Section */
        .security-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f5f7fa;
        }

        .security-item:last-child {
            border-bottom: none;
        }

        .security-info h4 {
            font-size: 1rem;
            margin-bottom: 5px;
            color: var(--primary);
        }

        .security-info p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Messages */
        .alert {
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .close-alert {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: inherit;
        }

        /* Tabs */
        .profile-tabs {
            display: flex;
            border-bottom: 1px solid #eee;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .tab {
            padding: 10px 20px;
            cursor: pointer;
            font-weight: 500;
            color: var(--gray);
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .tab.active {
            color: var(--secondary);
            border-bottom-color: var(--secondary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar-header h2, .sidebar-menu span {
                display: none;
            }
            
            .sidebar-menu li {
                text-align: center;
                padding: 15px 10px;
            }
            
            .sidebar-menu i {
                margin-right: 0;
                font-size: 1.2rem;
            }
            
            .profile-container {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .header-right {
                width: 100%;
                justify-content: space-between;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .profile-stats {
                grid-template-columns: 1fr;
            }
            
            .security-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .security-item .btn {
                width: auto;
                align-self: flex-end;
            }
        }

        @media (max-width: 576px) {
            .profile-tabs {
                flex-direction: column;
            }
            
            .tab {
                border-bottom: none;
                border-left: 3px solid transparent;
            }
            
            .tab.active {
                border-left-color: var(--secondary);
            }
        }

        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Search Bar */
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

        @media (max-width: 768px) {
            .search-bar input {
                width: 200px;
            }
        }

        @media (max-width: 576px) {
            .search-bar {
                display: none;
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
                <li><a href="dashboard.php"><i class="fas fa-home"></i> <span>Tableau de Bord</span></a></li>
                <li><a href="ajustestock.php"><i class="fas fa-box"></i> <span>Stock</span></a></li>
                <li><a href="gestion.php"><i class="fas fa-users"></i> <span>Gestion</span></a></li>
                <li><a href="employees.php"><i class="fas fa-user-tie"></i> <span>Employés</span></a></li>
                <li ><a href="facture.php"><i class="fas fa-file-invoice-dollar"></i> <span>Facturation</span></li>
                <li class="active"><i class="fas fa-user"></i> <span>Mon Profil</span></a></li>
                
                
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <h1>Mon Profil</h1>
                <p>Gérez vos informations personnelles et paramètres</p>
            </div>
            <div class="header-right">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Rechercher...">
                </div>
                <div class="user-profile" id="userProfile">
                    <img src="REM.jpg" alt="Admin" id="profileImage">
                    <span><?php echo htmlspecialchars($admin['first_name']); ?></span>
                    <i class="fas fa-chevron-down"></i>
                    
                    <div class="user-dropdown" id="userDropdown">
                        <a href="profile.php"><i class="fas fa-user"></i> Mon Profil</a>
                        <a href="settings.php"><i class="fas fa-cog"></i> Paramètres</a>
                        <a href="notifications.php"><i class="fas fa-bell"></i> Notifications</a>
                        <hr style="margin: 5px 0; border-color: #eee;">
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Messages -->
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : ($messageType === 'warning' ? 'warning' : 'error'); ?>" id="messageAlert">
                <?php echo htmlspecialchars($message); ?>
                <button class="close-alert" onclick="document.getElementById('messageAlert').style.display='none'">
                    &times;
                </button>
            </div>
            <?php endif; ?>

            <!-- Profile Tabs -->
            <div class="profile-tabs" id="profileTabs">
                <div class="tab active" data-tab="profile">Informations personnelles</div>
                <div class="tab" data-tab="security">Sécurité</div>
                <div class="tab" data-tab="preferences">Préférences</div>
                <div class="tab" data-tab="activity">Activité récente</div>
            </div>

            <!-- Profile Container -->
            <div class="profile-container">
                <!-- Profile Card (Left) -->
                <div class="profile-card">
                    <div class="profile-avatar">
                        <img src="" alt="<?php echo htmlspecialchars($admin['full_name']); ?>" id="avatarImage">
                        <div class="avatar-upload">
                            <i class="fas fa-camera"></i>
                            <input type="file" id="avatarInput" accept="image/*">
                        </div>
                    </div>
                    
                    <div class="profile-info">
                        <h3><?php echo htmlspecialchars($admin['full_name']); ?></h3>
                        <p><?php echo htmlspecialchars($admin['email']); ?></p>
                        <div class="profile-role"><?php echo htmlspecialchars(ucfirst($admin['role'])); ?></div>
                        
                        <div class="profile-stats">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $orderCount; ?></div>
                                <div class="stat-label">Commandes</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $clientCount; ?></div>
                                <div class="stat-label">Clients</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $employeeCount; ?></div>
                                <div class="stat-label">Employés</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number">
                                    <?php 
                                    $joinDate = isset($admin['hire_date']) ? $admin['hire_date'] : ($admin['created_at'] ?? date('Y-m-d'));
                                    echo date('d/m/Y', strtotime($joinDate));
                                    ?>
                                </div>
                                <div class="stat-label">Membre depuis</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Profile Content (Right) -->
                <div class="profile-content">
                    <!-- Profile Information Tab -->
                    <div class="tab-content active" id="profile-tab">
                        <div class="info-card">
                            <h3><i class="fas fa-user-circle"></i> Informations personnelles</h3>
                            <form method="POST" id="profileForm" onsubmit="return validateProfileForm()">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="first_name">Prénom</label>
                                        <input type="text" id="first_name" name="first_name" required
                                               value="<?php echo htmlspecialchars($admin['first_name']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="last_name">Nom</label>
                                        <input type="text" id="last_name" name="last_name" required
                                               value="<?php echo htmlspecialchars($admin['last_name']); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input type="email" id="email" name="email" required
                                               value="<?php echo htmlspecialchars($admin['email']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="phone">Téléphone</label>
                                        <input type="tel" id="phone" name="phone"
                                               value="<?php echo htmlspecialchars($admin['phone'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="position">Poste</label>
                                        <input type="text" id="position" name="position"
                                               value="<?php echo htmlspecialchars($admin['position'] ?? ''); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="department">Département</label>
                                        <select id="department" name="department">
                                            <option value="">Sélectionnez...</option>
                                            <option value="printing" <?php echo (isset($admin['department']) && $admin['department'] == 'printing') ? 'selected' : ''; ?>>Impression</option>
                                            <option value="design" <?php echo (isset($admin['department']) && $admin['department'] == 'design') ? 'selected' : ''; ?>>Design</option>
                                            <option value="sales" <?php echo (isset($admin['department']) && $admin['department'] == 'sales') ? 'selected' : ''; ?>>Ventes</option>
                                            <option value="finance" <?php echo (isset($admin['department']) && $admin['department'] == 'finance') ? 'selected' : ''; ?>>Finance</option>
                                            <option value="admin" <?php echo (isset($admin['department']) && $admin['department'] == 'admin') ? 'selected' : ''; ?>>Administration</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="username">Nom d'utilisateur</label>
                                        <input type="text" id="username" name="username" disabled
                                               value="<?php echo htmlspecialchars($admin['username']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="role">Rôle</label>
                                        <input type="text" id="role" name="role" disabled
                                               value="<?php echo htmlspecialchars(ucfirst($admin['role'])); ?>">
                                    </div>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-save"></i> Enregistrer les modifications
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Security Tab -->
                    <div class="tab-content" id="security-tab">
                        <div class="info-card">
                            <h3><i class="fas fa-shield-alt"></i> Sécurité du compte</h3>
                            
                            <div class="security-item">
                                <div class="security-info">
                                    <h4>Mot de passe</h4>
                                    <p>Modifiez votre mot de passe régulièrement pour protéger votre compte</p>
                                </div>
                                <button class="btn btn-primary" onclick="showPasswordForm()">
                                    <i class="fas fa-key"></i> Changer
                                </button>
                            </div>
                            
                            <div class="security-item">
                                <div class="security-info">
                                    <h4>Connexion sécurisée</h4>
                                    <p>Dernière connexion: <?php echo date('d/m/Y H:i'); ?></p>
                                </div>
                                <span style="color: var(--success); font-weight: 500;">
                                    <i class="fas fa-check-circle"></i> Active
                                </span>
                            </div>
                            
                            <!-- Password Change Form (hidden by default) -->
                            <div id="passwordForm" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                                <form method="POST" id="changePasswordForm" onsubmit="return validatePasswordForm()">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="current_password">Mot de passe actuel</label>
                                            <input type="password" id="current_password" name="current_password" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="new_password">Nouveau mot de passe</label>
                                            <input type="password" id="new_password" name="new_password" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="confirm_password">Confirmer le mot de passe</label>
                                            <input type="password" id="confirm_password" name="confirm_password" required>
                                        </div>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <button type="button" class="btn btn-secondary" onclick="hidePasswordForm()">
                                            Annuler
                                        </button>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-key"></i> Changer le mot de passe
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Preferences Tab -->
                    

                    <!-- Activity Tab -->
                    <div class="tab-content" id="activity-tab">
                        <div class="activity-card">
                            <h3><i class="fas fa-history"></i> Activité récente</h3>
                            
                            <ul class="activity-list">
                                <?php if (empty($activities)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-history fa-3x"></i>
                                    <p>Aucune activité récente</p>
                                </div>
                                <?php else: ?>
                                    <?php foreach ($activities as $activity): ?>
                                    <li class="activity-item">
                                        <div class="activity-icon">
                                            <i class="fas fa-shopping-cart"></i>
                                        </div>
                                        <div class="activity-content">
                                            <h4><?php echo htmlspecialchars($activity['type']); ?></h4>
                                            <p><?php echo htmlspecialchars($activity['description']); ?></p>
                                            <div class="activity-time">
                                                <?php echo date('d/m/Y H:i', strtotime($activity['time'])); ?>
                                            </div>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variables pour la gestion du profil
        const profileTabs = document.getElementById('profileTabs');
        const tabContents = document.querySelectorAll('.tab-content');
        const userProfile = document.getElementById('userProfile');
        const userDropdown = document.getElementById('userDropdown');
        const avatarInput = document.getElementById('avatarInput');
        const avatarImage = document.getElementById('avatarImage');
        const profileImage = document.getElementById('profileImage');
        const passwordForm = document.getElementById('passwordForm');

        // Gestion des onglets
        function switchTab(tabId) {
            // Désactiver tous les onglets
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Masquer tous les contenus d'onglets
            tabContents.forEach(content => {
                content.classList.remove('active');
            });
            
            // Activer l'onglet sélectionné
            document.querySelector(`.tab[data-tab="${tabId}"]`).classList.add('active');
            document.getElementById(`${tabId}-tab`).classList.add('active');
            
            // Sauvegarder l'onglet actif dans localStorage
            localStorage.setItem('activeProfileTab', tabId);
        }

        // Afficher/masquer le dropdown utilisateur
        function toggleUserDropdown() {
            userDropdown.classList.toggle('show');
        }

        // Changer l'avatar
        function changeAvatar(event) {
            const file = event.target.files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) { // 5MB max
                    alert('L\'image est trop grande. Maximum 5MB.');
                    return;
                }
                
                if (!file.type.match('image.*')) {
                    alert('Veuillez sélectionner une image.');
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    avatarImage.src = e.target.result;
                    profileImage.src = e.target.result;
                    
                    // Simulation d'envoi au serveur
                    alert('Avatar mis à jour! (Dans un cas réel, l\'image serait enregistrée sur le serveur)');
                };
                reader.readAsDataURL(file);
            }
        }

        // Afficher le formulaire de changement de mot de passe
        function showPasswordForm() {
            passwordForm.style.display = 'block';
            document.getElementById('security-tab').scrollIntoView({ behavior: 'smooth' });
        }

        // Masquer le formulaire de changement de mot de passe
        function hidePasswordForm() {
            passwordForm.style.display = 'none';
            document.getElementById('changePasswordForm').reset();
        }

        // Valider le formulaire de changement de mot de passe
        function validatePasswordForm() {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (!currentPassword) {
                alert('Veuillez entrer votre mot de passe actuel.');
                return false;
            }
            
            if (newPassword.length < 6) {
                alert('Le nouveau mot de passe doit contenir au moins 6 caractères.');
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                alert('Les nouveaux mots de passe ne correspondent pas.');
                return false;
            }
            
            return true;
        }

        // Valider le formulaire de profil
        function validateProfileForm() {
            const firstName = document.getElementById('first_name').value;
            const lastName = document.getElementById('last_name').value;
            const email = document.getElementById('email').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (!firstName || !lastName) {
                alert('Veuillez entrer votre prénom et nom.');
                return false;
            }
            
            if (!emailRegex.test(email)) {
                alert('Veuillez entrer une adresse email valide.');
                return false;
            }
            
            return true;
        }

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion des clics sur les onglets
            profileTabs.addEventListener('click', function(e) {
                if (e.target.classList.contains('tab')) {
                    const tabId = e.target.getAttribute('data-tab');
                    switchTab(tabId);
                }
            });
            
            // Gestion du dropdown utilisateur
            userProfile.addEventListener('click', function(e) {
                if (!e.target.closest('.user-dropdown')) {
                    toggleUserDropdown();
                }
            });
            
            // Fermer le dropdown en cliquant à l'extérieur
            document.addEventListener('click', function(e) {
                if (!userProfile.contains(e.target)) {
                    userDropdown.classList.remove('show');
                }
            });
            
            // Gestion du changement d'avatar
            avatarInput.addEventListener('change', changeAvatar);
            
            // Récupérer l'onglet actif depuis localStorage
            const activeTab = localStorage.getItem('activeProfileTab') || 'profile';
            switchTab(activeTab);
            
            // Masquer le message après 5 secondes
            const messageAlert = document.getElementById('messageAlert');
            if (messageAlert) {
                setTimeout(() => {
                    messageAlert.style.display = 'none';
                }, 5000);
            }
        });
    </script>
</body>
</html>