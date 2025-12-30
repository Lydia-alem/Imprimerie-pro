<?php
// Démarrage de la session pour les messages
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

// Variables pour les messages
$message = '';
$messageType = '';

// Gérer les actions CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'add' || $action === 'edit') {
            // Valider et nettoyer les données
            $firstName = trim($_POST['firstName']);
            $lastName = trim($_POST['lastName']);
            $email = trim($_POST['email']);
            $position = trim($_POST['position']);
            $department = $_POST['department'];
            $salary = floatval($_POST['salary']);
            $hireDate = $_POST['hireDate'];
            $phone = trim($_POST['phone']);
            
            // Valider les données requises
            if (empty($firstName) || empty($lastName) || empty($email) || empty($position) || empty($department)) {
                $message = "Veuillez remplir tous les champs obligatoires!";
                $messageType = "error";
                $_SESSION['message'] = $message;
                $_SESSION['message_type'] = $messageType;
            } else {
                // Pour la base de données
                $username = strtolower(str_replace(' ', '.', $firstName . '.' . $lastName));
                $fullName = $firstName . ' ' . $lastName;
                $role = 'employee';
                
                // Pour le mot de passe par défaut
                $defaultPassword = password_hash('password123', PASSWORD_DEFAULT);
                
                if ($action === 'add') {
                    // Vérifier si l'email existe déjà
                    $checkEmailSql = "SELECT id FROM admin_users WHERE email = :email";
                    $checkStmt = $pdo->prepare($checkEmailSql);
                    $checkStmt->execute([':email' => $email]);
                    
                    if ($checkStmt->rowCount() > 0) {
                        $_SESSION['message'] = "Cet email est déjà utilisé par un autre utilisateur!";
                        $_SESSION['message_type'] = "error";
                    } else {
                        // Ajouter un nouvel employé
                        try {
                            $pdo->beginTransaction();
                            
                            // Insérer dans admin_users
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
                            
                            $employeeId = $pdo->lastInsertId();
                            
                            // Insérer dans employees
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
                            
                            $pdo->commit();
                            $_SESSION['message'] = "Employé ajouté avec succès!";
                            $_SESSION['message_type'] = "success";
                            
                            header("Location: employees.php");
                            exit();
                        } catch(PDOException $e) {
                            $pdo->rollBack();
                            $_SESSION['message'] = "Erreur lors de l'ajout de l'employé: " . $e->getMessage();
                            $_SESSION['message_type'] = "error";
                            header("Location: employees.php");
                            exit();
                        }
                    }
                } elseif ($action === 'edit' && isset($_POST['employee_id'])) {
                    // Modifier un employé existant
                    $employeeId = intval($_POST['employee_id']);
                    
                    try {
                        $pdo->beginTransaction();
                        
                        // Mettre à jour admin_users
                        $sql = "UPDATE admin_users 
                                SET full_name = :full_name, email = :email 
                                WHERE id = :id AND role = 'employee'";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            ':full_name' => $fullName,
                            ':email' => $email,
                            ':id' => $employeeId
                        ]);
                        
                        // Vérifier si l'entrée existe déjà dans employees
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
                        
                        $pdo->commit();
                        $_SESSION['message'] = "Employé modifié avec succès!";
                        $_SESSION['message_type'] = "success";
                        
                        header("Location: employees.php");
                        exit();
                    } catch(PDOException $e) {
                        $pdo->rollBack();
                        $_SESSION['message'] = "Erreur lors de la modification de l'employé: " . $e->getMessage();
                        $_SESSION['message_type'] = "error";
                        header("Location: employees.php");
                        exit();
                    }
                }
            }
        } elseif ($action === 'delete' && isset($_POST['employee_id'])) {
            // Supprimer un employé
            $employeeId = intval($_POST['employee_id']);
            
            try {
                $sql = "DELETE FROM admin_users WHERE id = :id AND role = 'employee'";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':id' => $employeeId]);
                
                if ($stmt->rowCount() > 0) {
                    $_SESSION['message'] = "Employé supprimé avec succès!";
                    $_SESSION['message_type'] = "success";
                } else {
                    $_SESSION['message'] = "Employé non trouvé ou ne peut pas être supprimé!";
                    $_SESSION['message_type'] = "error";
                }
                
                header("Location: employees.php");
                exit();
            } catch(PDOException $e) {
                $_SESSION['message'] = "Erreur lors de la suppression de l'employé: " . $e->getMessage();
                $_SESSION['message_type'] = "error";
                header("Location: employees.php");
                exit();
            }
        }
    }
}

// Récupérer les messages de session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Récupérer les employés
try {
    $sql = "SELECT au.id, au.full_name, au.email, au.username, au.role, au.created_at,
                   e.position, e.department, e.salary, e.hire_date, e.phone
            FROM admin_users au
            LEFT JOIN employees e ON au.id = e.user_id
            WHERE au.role = 'employee'
            ORDER BY COALESCE(e.hire_date, au.created_at) DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
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
    $employeeId = intval($_GET['edit']);
    
    try {
        $sql = "SELECT au.id, au.full_name, au.email, au.username, au.role,
                       e.position, e.department, e.salary, e.hire_date, e.phone
                FROM admin_users au
                LEFT JOIN employees e ON au.id = e.user_id
                WHERE au.id = :id AND au.role = 'employee'";
        
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

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f5f7fa; color: var(--dark); display: flex; min-height: 100vh; }

        /* Sidebar */
        .sidebar { width: 250px; background: var(--primary); color: white; box-shadow: var(--shadow); display:flex; flex-direction:column; }
        .sidebar-header { padding: 20px; display:flex; align-items:center; gap:15px; }
        .sidebar-header i { font-size: 1.4rem; }
        .sidebar-header h2 { font-size:1.2rem; }
        .sidebar-menu { padding: 15px 0; flex:1; }
        .sidebar-menu ul { list-style:none; }
        .sidebar-menu li a { padding:12px 20px; display:flex; align-items:center; color:white; text-decoration:none; }
        .sidebar-menu li.active a { background: var(--secondary); border-left: 4px solid var(--accent); }

        .main-content { flex:1; display:flex; flex-direction:column; overflow-x:hidden; }

        .header { background:white; padding:15px 30px; display:flex; justify-content:space-between; align-items:center; box-shadow: var(--shadow); }
        .header-left h1 { font-size:1.6rem; color:var(--primary); }
        .header-right { display:flex; align-items:center; gap:20px; }

        .search-bar { position:relative; }
        .search-bar input { padding:10px 15px 10px 40px; border:1px solid var(--gray); border-radius:30px; width:300px; }
        .search-bar i { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--gray); }

        .content { padding:30px; flex:1; }

        .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
        .page-title h2 { font-size:1.4rem; color:var(--primary); }
        .btn { padding:10px 18px; border:none; border-radius:5px; cursor:pointer; font-weight:500; display:inline-flex; align-items:center; gap:8px; }
        .btn-primary { background:var(--secondary); color:white; }

        .form-card { background:white; border-radius:10px; padding:20px; box-shadow:var(--shadow); margin-bottom:20px; display:none; }
        .form-row { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:15px; margin-bottom:10px; }
        .form-group label { margin-bottom:6px; font-weight:600; color:var(--dark); display:block; }
        .form-group input, .form-group select { padding:10px 12px; border:1px solid #ddd; border-radius:6px; width:100%; }

        .employees-card { background:white; border-radius:10px; padding:20px; box-shadow:var(--shadow); }
        .employees-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:12px 10px; text-align:left; border-bottom:1px solid #eee; }
        th { background:#f8f9fa; color:var(--gray); }
        .department { padding:6px 10px; border-radius:14px; font-weight:600; font-size:0.85rem; display:inline-block; }
        .department-printing { background:#d1ecf1; color:#0c5460; }
        .department-design { background:#d4edda; color:#155724; }
        .department-sales { background:#fff3cd; color:#856404; }
        .department-finance { background:#f8d7da; color:#721c24; }
        .department-admin { background:#e2e3e5; color:#383d41; }

        /* salary shown with DA suffix in markup */
        .salary { font-weight:600; color:var(--dark); }

        .actions { display:flex; gap:8px; align-items:center; }
        .btn-icon { width:36px; height:36px; border-radius:6px; display:inline-flex; align-items:center; justify-content:center; border:none; cursor:pointer; }
        .btn-edit { background:rgba(52,152,219,0.08); color:var(--secondary); }
        .btn-delete { background:rgba(231,76,60,0.08); color:var(--danger); }

        .empty-state { text-align:center; padding:30px; color:var(--gray); }

        .alert { padding:10px 14px; border-radius:6px; margin-bottom:14px; display:flex; justify-content:space-between; align-items:center; }
        .alert-success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
        .alert-error { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }

        @media (max-width:992px) { .sidebar { width:80px; } .search-bar input { width:180px; } .main-content { margin-left:80px; } }
        @media (max-width:576px) { .search-bar { display:none; } .form-row { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-print"></i>
            <h2>Imprimerie Pro</h2>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-home"></i><span style="margin-left:8px">Tableau de Bord</span></a></li>
                <li><a href="ajustestock.php"><i class="fas fa-box"></i><span style="margin-left:8px">Stock</span></a></li>
                <li><a href="gestion.php"><i class="fas fa-users"></i><span style="margin-left:8px">Gestion</span></a></li>
                <li class="active"><a href="employees.php"><i class="fas fa-user-tie"></i><span style="margin-left:8px">Employés</span></a></li>
                <li><a href="facture.php"><i class="fas fa-file-invoice-dollar"></i><span style="margin-left:8px">Facturation</span></a></li>
                <li><a href="profile.php"><i class="fas fa-user"></i><span style="margin-left:8px">Mon Profil</span></a></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="header-left"><h1>Gestion des Employés</h1></div>
            <div class="header-right">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Rechercher un employé...">
                </div>
                <div class="user-profile">
                    <img src="https://i.pravatar.cc/150?img=12" alt="Admin" style="width:36px;height:36px;border-radius:50%;">
                    <span>Admin</span>
                </div>
            </div>
        </div>

        <div class="content">
            <!-- Messages -->
            <?php if ($message): ?>
            <div class="alert <?php echo $messageType === 'success' ? 'alert-success' : 'alert-error'; ?>" id="messageAlert">
                <?php echo htmlspecialchars($message); ?>
                <button class="close-alert" onclick="document.getElementById('messageAlert').style.display='none'">&times;</button>
            </div>
            <?php endif; ?>

            <div class="page-header">
                <div class="page-title">
                    <h2>Gestion du personnel</h2>
                    <p>Ajoutez, modifiez ou supprimez des employés et gérez leurs salaires</p>
                </div>
                <button class="btn btn-primary" id="addEmployeeBtn" onclick="showAddForm()"><i class="fas fa-plus"></i> Ajouter un employé</button>
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
                                <option value="design" <?php echo ($isEditing && $currentEmployee && isset($currentEmployee['department']) && $currentEmployee['department'] == 'design') ? 'selected' : ''; ?>>Design</option>
                                <option value="sales" <?php echo ($isEditing && $currentEmployee && isset($currentEmployee['department']) && $currentEmployee['department'] == 'sales') ? 'selected' : ''; ?>>Ventes</option>
                                <option value="finance" <?php echo ($isEditing && $currentEmployee && isset($currentEmployee['department']) && $currentEmployee['department'] == 'finance') ? 'selected' : ''; ?>>Finance</option>
                                <option value="admin" <?php echo ($isEditing && $currentEmployee && isset($currentEmployee['department']) && $currentEmployee['department'] == 'admin') ? 'selected' : ''; ?>>Administration</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="salary">Salaire mensuel (DA) *</label>
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
                        <div class="form-group"></div>
                    </div>
                    
                    <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:10px;">
                        <button type="button" class="btn" onclick="hideForm()">Annuler</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo $isEditing ? 'Mettre à jour' : 'Enregistrer'; ?></button>
                    </div>
                </form>
            </div>

            <!-- Employees List -->
            <div class="employees-card">
                <div class="employees-header">
                    <h3>Liste des employés</h3>
                    <div class="employees-actions"></div>
                </div>
                
                <div class="table-container">
                    <table id="employeesTable" aria-describedby="employeesList">
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
                            <tr class="no-results">
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="fas fa-user-friends" style="font-size:2rem;"></i>
                                        <h3>Aucun employé trouvé</h3>
                                        <p>Cliquez sur "Ajouter un employé" pour commencer</p>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($employees as $employee): ?>
                                <?php
                                $hireDate = isset($employee['hire_date']) && $employee['hire_date'] ? date('d/m/Y', strtotime($employee['hire_date'])) : (isset($employee['created_at']) ? date('d/m/Y', strtotime($employee['created_at'])) : 'N/A');
                                $department = $employee['department'] ?? '';
                                $deptClass = 'department-admin';
                                $deptText = 'Non défini';
                                switch($department) {
                                    case 'printing': $deptClass = 'department-printing'; $deptText = 'Impression'; break;
                                    case 'design': $deptClass = 'department-design'; $deptText = 'Design'; break;
                                    case 'sales': $deptClass = 'department-sales'; $deptText = 'Ventes'; break;
                                    case 'finance': $deptClass = 'department-finance'; $deptText = 'Finance'; break;
                                    case 'admin': $deptClass = 'department-admin'; $deptText = 'Administration'; break;
                                }
                                $salary = $employee['salary'] ?? 0;
                                ?>
                                <tr data-name="<?php echo htmlspecialchars(strtolower($employee['full_name'])); ?>"
                                    data-email="<?php echo htmlspecialchars(strtolower($employee['email'])); ?>"
                                    data-position="<?php echo htmlspecialchars(strtolower($employee['position'] ?? '')); ?>"
                                    data-department="<?php echo htmlspecialchars(strtolower($deptText)); ?>">
                                    <td>
                                        <div style="display:flex; gap:10px; align-items:center;">
                                            <div>
                                                <div style="font-weight:600;"><?php echo htmlspecialchars($employee['full_name']); ?></div>
                                                <div style="color:var(--gray); font-size:0.9rem;"><?php echo htmlspecialchars($employee['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($employee['position'] ?? 'Non défini'); ?></td>
                                    <td><span class="department <?php echo $deptClass; ?>"><?php echo $deptText; ?></span></td>
                                    <td><?php echo $hireDate; ?></td>
                                    <td class="salary"><?php echo number_format($salary, 0, ',', ' '); ?> DA</td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn-icon btn-edit" title="Modifier" onclick="editEmployee(<?php echo $employee['id']; ?>)"><i class="fas fa-edit"></i></button>
                                            <form method="POST" style="display:inline;" onsubmit="return confirmDelete()">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                <button type="submit" class="btn-icon btn-delete" title="Supprimer"><i class="fas fa-trash"></i></button>
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
    // Form handling
    function showAddForm(){
        document.getElementById('employeeForm').style.display = 'block';
        document.getElementById('formTitle').textContent = 'Ajouter un nouvel employé';
        const form = document.getElementById('employeeFormData');
        form.reset();
        form.querySelector('input[name="action"]').value = 'add';
        // remove employee_id if present
        const idInput = form.querySelector('input[name="employee_id"]');
        if (idInput) idInput.remove();
        document.getElementById('hireDate').value = new Date().toISOString().split('T')[0];
        window.scrollTo({top:0, behavior:'smooth'});
    }
    function hideForm(){
        document.getElementById('employeeForm').style.display = 'none';
        // reload to clear edit state if any
        if (location.search.indexOf('edit=') !== -1) {
            window.location.href = window.location.pathname;
        }
    }
    function editEmployee(id){
        // simply navigate to ?edit=id (server will populate the form)
        window.location.href = '?edit=' + id;
    }
    function confirmDelete(){
        return confirm("Êtes-vous sûr de vouloir supprimer cet employé ?");
    }

    // Search functionality - client side, real-time
    document.addEventListener('DOMContentLoaded', function(){
        const searchInput = document.getElementById('searchInput');
        const tbody = document.querySelector('#employeesList');
        if (!tbody) return;

        function updateEmptyState(visibleCount){
            // show/hide the no-results row
            const noResultsRow = tbody.querySelector('.no-results');
            if (visibleCount === 0) {
                if (!noResultsRow) {
                    const tr = document.createElement('tr');
                    tr.className = 'no-results';
                    tr.innerHTML = '<td colspan="6"><div class="empty-state"><i class="fas fa-user-friends" style="font-size:2rem;"></i><h3>Aucun employé trouvé</h3><p>Essayez un autre terme de recherche.</p></div></td>';
                    tbody.appendChild(tr);
                }
            } else {
                if (noResultsRow) noResultsRow.remove();
            }
        }

        function filterEmployees(){
            const term = searchInput.value.trim().toLowerCase();
            const rows = Array.from(tbody.querySelectorAll('tr')).filter(r => !r.classList.contains('no-results'));
            let visible = 0;
            rows.forEach(row => {
                const name = row.getAttribute('data-name') || '';
                const email = row.getAttribute('data-email') || '';
                const position = row.getAttribute('data-position') || '';
                const department = row.getAttribute('data-department') || '';
                const salaryCell = row.querySelector('.salary') ? row.querySelector('.salary').textContent.toLowerCase() : '';

                const hay = [name, email, position, department, salaryCell].join(' ');
                if (hay.indexOf(term) !== -1) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            });
            updateEmptyState(visible);
        }

        searchInput.addEventListener('input', filterEmployees);
        // init: if there is a value already (e.g. mobile keyboard), run filter
        if (searchInput.value.trim() !== '') filterEmployees();

        // Auto-hide messages
        const messageAlert = document.getElementById('messageAlert');
        if (messageAlert) {
            setTimeout(()=>{ messageAlert.style.display = 'none'; }, 5000);
        }
    });
</script>
</body>
</html>