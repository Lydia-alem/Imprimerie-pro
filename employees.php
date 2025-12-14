<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimerie Admin - Gestion des Employés</title>
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

        .sidebar-header img {
            width: 40px;
            height: 40px;
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

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title h2 {
            font-size: 1.8rem;
            color: var(--primary);
        }

        .page-title p {
            color: var(--gray);
            margin-top: 5px;
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

        /* Form Card */
        .form-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .form-card h3 {
            font-size: 1.3rem;
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
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

        .form-group input, .form-group select {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 25px;
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

        /* Employees Table */
        .employees-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
        }

        .employees-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .employees-header h3 {
            font-size: 1.3rem;
            color: var(--primary);
        }

        .employees-actions {
            display: flex;
            gap: 10px;
        }

        .btn-outline {
            background: transparent;
            color: var(--secondary);
            border: 1px solid var(--secondary);
        }

        .btn-outline:hover {
            background: var(--secondary);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            color: var(--gray);
            font-weight: 500;
            background: #f8f9fa;
        }

        .employee-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .employee-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .employee-name {
            font-weight: 500;
            color: var(--dark);
        }

        .employee-email {
            font-size: 0.9rem;
            color: var(--gray);
        }

        .department {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .department-printing {
            background: #d1ecf1;
            color: #0c5460;
        }

        .department-design {
            background: #d4edda;
            color: #155724;
        }

        .department-finance {
            background: #f8d7da;
            color: #721c24;
        }

        .department-sales {
            background: #fff3cd;
            color: #856404;
        }

        .department-admin {
            background: #e2e3e5;
            color: #383d41;
        }

        .salary {
            font-weight: 600;
            color: var(--dark);
        }

        .salary::after {
            content: " €";
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }

        .btn-edit {
            background: rgba(52, 152, 219, 0.1);
            color: var(--secondary);
        }

        .btn-edit:hover {
            background: var(--secondary);
            color: white;
        }

        .btn-delete {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger);
        }

        .btn-delete:hover {
            background: var(--danger);
            color: white;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ddd;
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

        .close-alert {
            background: none;
            border: none;
            font-size: 1.2rem;
            cursor: pointer;
            color: inherit;
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
            
            .table-container {
                overflow-x: auto;
            }
        }

        @media (max-width: 768px) {
            .search-bar input {
                width: 200px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .employees-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .employees-actions {
                width: 100%;
                justify-content: space-between;
            }
        }

        @media (max-width: 576px) {
            .search-bar {
                display: none;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
<?php
// Connexion à la base de données
$host = '127.0.0.1:3306';
$dbname = 'imprimerie';
$username = 'root'; // À modifier selon votre configuration
$password = 'admine'; // À modifier selon votre configuration

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Variables pour les messages
$message = '';
$messageType = '';

// Gérer les actions CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'add' || $action === 'edit') {
            // Ajouter ou modifier un employé
            $firstName = $_POST['firstName'];
            $lastName = $_POST['lastName'];
            $email = $_POST['email'];
            $position = $_POST['position'];
            $department = $_POST['department'];
            $salary = $_POST['salary'];
            $hireDate = $_POST['hireDate'];
            $phone = $_POST['phone'];
            
            // Pour la base de données, nous utiliserons admin_users table
            // Nous allons adapter les champs pour correspondre à la table admin_users
            $username = strtolower($firstName . '.' . $lastName);
            $fullName = $firstName . ' ' . $lastName;
            $role = 'employee'; // Par défaut, tous les employés créés ici auront le rôle 'employee'
            
            // Pour le mot de passe, nous allons générer un mot de passe par défaut (hashé)
            $defaultPassword = password_hash('password123', PASSWORD_DEFAULT);
            
            if ($action === 'add') {
                // Ajouter un nouvel employé
                try {
                    $sql = "INSERT INTO admin_users (username, password, full_name, email, role, created_at) 
                            VALUES (:username, :password, :full_name, :email, :role, NOW())";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':username' => $username,
                        ':password' => $defaultPassword,
                        ':full_name' => $fullName,
                        ':email' => $email,
                        ':role' => $role
                    ]);
                    
                    // Maintenant, insérer les informations supplémentaires dans une nouvelle table
                    // Nous allons créer une table 'employees' si elle n'existe pas
                    $employeeId = $pdo->lastInsertId();
                    
                    // Créer la table employees si elle n'existe pas
                    $createTableSql = "CREATE TABLE IF NOT EXISTS employees (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        position VARCHAR(200),
                        department VARCHAR(100),
                        salary DECIMAL(10,2),
                        hire_date DATE,
                        phone VARCHAR(50),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES admin_users(id) ON DELETE CASCADE
                    )";
                    $pdo->exec($createTableSql);
                    
                    // Insérer les détails de l'employé
                    $sqlEmployee = "INSERT INTO employees (user_id, position, department, salary, hire_date, phone) 
                                    VALUES (:user_id, :position, :department, :salary, :hire_date, :phone)";
                    $stmtEmployee = $pdo->prepare($sqlEmployee);
                    $stmtEmployee->execute([
                        ':user_id' => $employeeId,
                        ':position' => $position,
                        ':department' => $department,
                        ':salary' => $salary,
                        ':hire_date' => $hireDate,
                        ':phone' => $phone
                    ]);
                    
                    $message = "Employé ajouté avec succès!";
                    $messageType = "success";
                } catch(PDOException $e) {
                    $message = "Erreur lors de l'ajout de l'employé: " . $e->getMessage();
                    $messageType = "error";
                }
            } elseif ($action === 'edit' && isset($_POST['employee_id'])) {
                // Modifier un employé existant
                $employeeId = $_POST['employee_id'];
                
                try {
                    // Mettre à jour l'utilisateur dans admin_users
                    $sql = "UPDATE admin_users 
                            SET full_name = :full_name, email = :email 
                            WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':full_name' => $fullName,
                        ':email' => $email,
                        ':id' => $employeeId
                    ]);
                    
                    // Mettre à jour les détails dans employees
                    // Vérifier si l'entrée existe déjà
                    $checkSql = "SELECT id FROM employees WHERE user_id = :user_id";
                    $checkStmt = $pdo->prepare($checkSql);
                    $checkStmt->execute([':user_id' => $employeeId]);
                    
                    if ($checkStmt->rowCount() > 0) {
                        // Mettre à jour
                        $updateSql = "UPDATE employees 
                                     SET position = :position, department = :department, 
                                         salary = :salary, hire_date = :hire_date, phone = :phone 
                                     WHERE user_id = :user_id";
                        $updateStmt = $pdo->prepare($updateSql);
                        $updateStmt->execute([
                            ':position' => $position,
                            ':department' => $department,
                            ':salary' => $salary,
                            ':hire_date' => $hireDate,
                            ':phone' => $phone,
                            ':user_id' => $employeeId
                        ]);
                    } else {
                        // Insérer
                        $insertSql = "INSERT INTO employees (user_id, position, department, salary, hire_date, phone) 
                                     VALUES (:user_id, :position, :department, :salary, :hire_date, :phone)";
                        $insertStmt = $pdo->prepare($insertSql);
                        $insertStmt->execute([
                            ':user_id' => $employeeId,
                            ':position' => $position,
                            ':department' => $department,
                            ':salary' => $salary,
                            ':hire_date' => $hireDate,
                            ':phone' => $phone
                        ]);
                    }
                    
                    $message = "Employé modifié avec succès!";
                    $messageType = "success";
                } catch(PDOException $e) {
                    $message = "Erreur lors de la modification de l'employé: " . $e->getMessage();
                    $messageType = "error";
                }
            }
        } elseif ($action === 'delete' && isset($_POST['employee_id'])) {
            // Supprimer un employé
            $employeeId = $_POST['employee_id'];
            
            try {
                // La suppression dans admin_users déclenchera CASCADE dans employees
                $sql = "DELETE FROM admin_users WHERE id = :id AND role = 'employee'";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $employeeId]);
                
                if ($stmt->rowCount() > 0) {
                    $message = "Employé supprimé avec succès!";
                    $messageType = "success";
                } else {
                    $message = "Employé non trouvé ou ne peut pas être supprimé!";
                    $messageType = "error";
                }
            } catch(PDOException $e) {
                $message = "Erreur lors de la suppression de l'employé: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }
}

// Récupérer les employés (avec jointure entre admin_users et employees)
try {
    // Vérifier si la table employees existe
    $tableExists = $pdo->query("SHOW TABLES LIKE 'employees'")->rowCount() > 0;
    
    if ($tableExists) {
        $sql = "SELECT au.id, au.full_name, au.email, au.username, au.role, au.created_at,
                       e.position, e.department, e.salary, e.hire_date, e.phone
                FROM admin_users au
                LEFT JOIN employees e ON au.id = e.user_id
                WHERE au.role = 'employee'
                ORDER BY e.hire_date DESC";
    } else {
        // Si la table employees n'existe pas encore, récupérer seulement les utilisateurs avec rôle employee
        $sql = "SELECT id, full_name, email, username, role, created_at
                FROM admin_users
                WHERE role = 'employee'
                ORDER BY created_at DESC";
    }
    
    $stmt = $pdo->query($sql);
    $employees = $stmt->fetchAll();
} catch(PDOException $e) {
    $employees = [];
    $message = "Erreur lors de la récupération des employés: " . $e->getMessage();
    $messageType = "error";
}

// Initialiser les variables pour le formulaire
$isEditing = false;
$currentEmployee = null;

// Si on veut éditer un employé
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $employeeId = $_GET['edit'];
    
    try {
        if ($tableExists) {
            $sql = "SELECT au.id, au.full_name, au.email, au.username, au.role,
                           e.position, e.department, e.salary, e.hire_date, e.phone
                    FROM admin_users au
                    LEFT JOIN employees e ON au.id = e.user_id
                    WHERE au.id = :id AND au.role = 'employee'";
        } else {
            $sql = "SELECT id, full_name, email, username, role
                    FROM admin_users
                    WHERE id = :id AND role = 'employee'";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $employeeId]);
        $currentEmployee = $stmt->fetch();
        
        if ($currentEmployee) {
            $isEditing = true;
            
            // Séparer le nom complet en prénom et nom
            $nameParts = explode(' ', $currentEmployee['full_name'], 2);
            $currentEmployee['first_name'] = $nameParts[0] ?? '';
            $currentEmployee['last_name'] = $nameParts[1] ?? '';
        }
    } catch(PDOException $e) {
        $message = "Erreur lors de la récupération de l'employé: " . $e->getMessage();
        $messageType = "error";
    }
}
?>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-print fa-2x"></i>
            <h2>Imprimerie Pro</h2>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li><i class="fas fa-home"></i> <span>Tableau de Bord</span></li>
                <li><i class="fas fa-shopping-cart"></i> <span>Commandes</span></li>
                <li><i class="fas fa-box"></i> <span>Produits</span></li>
                <li><i class="fas fa-users"></i> <span>Clients</span></li>
                <li class="active"><i class="fas fa-user-tie"></i> <span>Employés</span></li>
                <li><i class="fas fa-chart-bar"></i> <span>Rapports</span></li>
                <li><i class="fas fa-cog"></i> <span>Paramètres</span></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <h1>Gestion des Employés</h1>
            </div>
            <div class="header-right">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Rechercher un employé...">
                </div>
                <div class="user-profile">
                    <img src="https://i.pravatar.cc/150?img=12" alt="Admin">
                    <span>Admin</span>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Messages -->
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'error'; ?>" id="messageAlert">
                <?php echo htmlspecialchars($message); ?>
                <button class="close-alert" onclick="document.getElementById('messageAlert').style.display='none'">
                    &times;
                </button>
            </div>
            <?php endif; ?>
            
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title">
                    <h2>Gestion du personnel</h2>
                    <p>Ajoutez, modifiez ou supprimez des employés et gérez leurs salaires</p>
                </div>
                <button class="btn btn-primary" id="addEmployeeBtn" onclick="showAddForm()">
                    <i class="fas fa-plus"></i> Ajouter un employé
                </button>
            </div>

            <!-- Form Card -->
            <div class="form-card" id="employeeForm" style="display: <?php echo $isEditing ? 'block' : 'none'; ?>;">
                <h3 id="formTitle"><?php echo $isEditing ? 'Modifier l\'employé' : 'Ajouter un nouvel employé'; ?></h3>
                <form method="POST" id="employeeFormData">
                    <input type="hidden" name="action" value="<?php echo $isEditing ? 'edit' : 'add'; ?>">
                    <?php if ($isEditing && $currentEmployee): ?>
                    <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($currentEmployee['id']); ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstName">Prénom *</label>
                            <input type="text" id="firstName" name="firstName" required
                                   value="<?php echo $isEditing && $currentEmployee ? htmlspecialchars($currentEmployee['first_name']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="lastName">Nom *</label>
                            <input type="text" id="lastName" name="lastName" required
                                   value="<?php echo $isEditing && $currentEmployee ? htmlspecialchars($currentEmployee['last_name']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required
                                   value="<?php echo $isEditing && $currentEmployee ? htmlspecialchars($currentEmployee['email']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="position">Poste *</label>
                            <input type="text" id="position" name="position" required
                                   value="<?php echo $isEditing && $currentEmployee && isset($currentEmployee['position']) ? htmlspecialchars($currentEmployee['position']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="department">Département *</label>
                            <select id="department" name="department" required>
                                <option value="">Sélectionnez...</option>
                                <option value="printing" <?php echo ($isEditing && $currentEmployee && isset($currentEmployee['department']) && $currentEmployee['department'] == 'printing') ? 'selected' : ''; ?>>Impression</option>
                                <option value="design" <?php echo ($isEditing && $currentEmployee && isset($currentEmployee['department']) && $currentEmployee['department'] == 'design') ? 'selected' : ''; ?>>Design & Conception</option>
                                <option value="sales" <?php echo ($isEditing && $currentEmployee && isset($currentEmployee['department']) && $currentEmployee['department'] == 'sales') ? 'selected' : ''; ?>>Ventes</option>
                                <option value="finance" <?php echo ($isEditing && $currentEmployee && isset($currentEmployee['department']) && $currentEmployee['department'] == 'finance') ? 'selected' : ''; ?>>Finance & Comptabilité</option>
                                <option value="admin" <?php echo ($isEditing && $currentEmployee && isset($currentEmployee['department']) && $currentEmployee['department'] == 'admin') ? 'selected' : ''; ?>>Administration</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="salary">Salaire mensuel (€) *</label>
                            <input type="number" id="salary" name="salary" min="0" step="50" required
                                   value="<?php echo $isEditing && $currentEmployee && isset($currentEmployee['salary']) ? htmlspecialchars($currentEmployee['salary']) : '2500'; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="hireDate">Date d'embauche *</label>
                            <input type="date" id="hireDate" name="hireDate" required
                                   value="<?php echo $isEditing && $currentEmployee && isset($currentEmployee['hire_date']) ? htmlspecialchars($currentEmployee['hire_date']) : date('Y-m-d'); ?>">
                        </div>
                        <div class="form-group">
                            <label for="phone">Téléphone</label>
                            <input type="tel" id="phone" name="phone"
                                   value="<?php echo $isEditing && $currentEmployee && isset($currentEmployee['phone']) ? htmlspecialchars($currentEmployee['phone']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <!-- Empty column for alignment -->
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="hideForm()">
                            Annuler
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> <?php echo $isEditing ? 'Mettre à jour' : 'Enregistrer'; ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Employees List -->
            <div class="employees-card">
                <div class="employees-header">
                    <h3>Liste des employés</h3>
                    <div class="employees-actions">
                        
                        </button>
                        
                        </button>
                    </div>
                </div>
                
                <div class="table-container">
                    <table id="employeesTable">
                        <thead>
                            <tr>
                                <th>Employé</th>
                                <th>Poste</th>
                                <th>Département</th>
                                <th>Date d'embauche</th>
                                <th>Salaire mensuel</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="employeesList">
                            <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="fas fa-user-friends"></i>
                                        <h3>Aucun employé trouvé</h3>
                                        <p>Cliquez sur "Ajouter un employé" pour commencer</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($employees as $employee): ?>
                                <?php
                                // Formater la date d'embauche
                                $hireDate = isset($employee['hire_date']) ? date('d/m/Y', strtotime($employee['hire_date'])) : 
                                           (isset($employee['created_at']) ? date('d/m/Y', strtotime($employee['created_at'])) : 'N/A');
                                
                                // Déterminer la classe CSS pour le département
                                $deptClass = '';
                                $deptText = '';
                                $department = $employee['department'] ?? '';
                                
                                switch($department) {
                                    case 'printing':
                                        $deptClass = 'department-printing';
                                        $deptText = 'Impression';
                                        break;
                                    case 'design':
                                        $deptClass = 'department-design';
                                        $deptText = 'Design';
                                        break;
                                    case 'sales':
                                        $deptClass = 'department-sales';
                                        $deptText = 'Ventes';
                                        break;
                                    case 'finance':
                                        $deptClass = 'department-finance';
                                        $deptText = 'Finance';
                                        break;
                                    case 'admin':
                                        $deptClass = 'department-admin';
                                        $deptText = 'Administration';
                                        break;
                                    default:
                                        $deptClass = 'department-admin';
                                        $deptText = 'Non défini';
                                }
                                
                                // Salaire
                                $salary = $employee['salary'] ?? 0;
                                ?>
                                <tr>
                                    <td>
                                        <div class="employee-info">
                                            <div>
                                                <div class="employee-name"><?php echo htmlspecialchars($employee['full_name']); ?></div>
                                                <div class="employee-email"><?php echo htmlspecialchars($employee['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($employee['position'] ?? 'Non défini'); ?></td>
                                    <td><span class="department <?php echo $deptClass; ?>"><?php echo $deptText; ?></span></td>
                                    <td><?php echo $hireDate; ?></td>
                                    <td class="salary"><?php echo number_format($salary, 0, ',', ' '); ?></td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn-icon btn-edit" onclick="editEmployee(<?php echo $employee['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display: inline;" onsubmit="return confirmDelete()">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                <button type="submit" class="btn-icon btn-delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variables pour la gestion du formulaire
        let isEditing = false;
        let currentEmployeeId = null;

        // Éléments DOM
        const employeeForm = document.getElementById('employeeForm');
        const searchInput = document.getElementById('searchInput');
        const employeesTable = document.getElementById('employeesTable');

        // Afficher le formulaire d'ajout
        function showAddForm() {
            employeeForm.style.display = 'block';
            document.getElementById('formTitle').textContent = "Ajouter un nouvel employé";
            document.getElementById('employeeFormData').action = '';
            
            // Réinitialiser le formulaire
            const form = document.getElementById('employeeFormData');
            form.reset();
            
            // Définir la date d'aujourd'hui comme valeur par défaut
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('hireDate').value = today;
            
            // Définir l'action comme "add"
            const actionInput = form.querySelector('input[name="action"]');
            if (actionInput) {
                actionInput.value = 'add';
            }
            
            // Supprimer l'ID d'employé s'il existe
            const employeeIdInput = form.querySelector('input[name="employee_id"]');
            if (employeeIdInput) {
                employeeIdInput.remove();
            }
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // Éditer un employé
        function editEmployee(employeeId) {
            window.location.href = '?edit=' + employeeId;
        }

        // Cacher le formulaire
        function hideForm() {
            employeeForm.style.display = 'none';
            window.location.href = window.location.pathname; // Retour à la page sans paramètres
        }

        // Confirmer la suppression
        function confirmDelete() {
            return confirm("Êtes-vous sûr de vouloir supprimer cet employé ?");
        }

        // Filtrer les employés
        function filterEmployees() {
            const searchTerm = searchInput.value.toLowerCase();
            const rows = employeesTable.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                let found = false;
                
                for (let j = 0; j < cells.length; j++) {
                    const cellText = cells[j].textContent.toLowerCase();
                    if (cellText.includes(searchTerm)) {
                        found = true;
                        break;
                    }
                }
                
                row.style.display = found ? '' : 'none';
            }
        }

        // Exporter les employés (simulé)
        function exportEmployees() {
            alert('Exportation des données en CSV... (Fonctionnalité à implémenter complètement)');
        }

        // Afficher les filtres (simulé)
        function showFilters() {
            alert('Fenêtre de filtrage (Fonctionnalité à implémenter complètement)');
        }

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Ajouter l'événement de recherche
            if (searchInput) {
                searchInput.addEventListener('input', filterEmployees);
            }
            
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