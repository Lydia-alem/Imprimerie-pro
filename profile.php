<?php
// profile.php
// Page: Modifier le profil (informations personnelles + sécurité)
// Keep the same style and sidebar as other pages. Allows admin to update info and change password.

session_start();

// DB config — keep same as other files
$host = '127.0.0.1:3306';
$dbname = 'imprimerie';
$username = 'root';
$password = 'admine';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Connexion impossible : " . $e->getMessage());
}

// Ensure user is logged in. If not, try to fallback to first admin user (graceful).
if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];
} else {
    // Fallback: load first admin account (so page remains usable in local dev)
    $stmt = $pdo->query("SELECT id FROM admin_users WHERE role = 'admin' ORDER BY id LIMIT 1");
    $row = $stmt->fetch();
    if ($row) {
        $userId = (int)$row['id'];
        // Optionally set to session for subsequent requests
        $_SESSION['user_id'] = $userId;
    } else {
        die("Aucun utilisateur administrateur trouvé. Veuillez vous connecter.");
    }
}

// Load current user
$stmt = $pdo->prepare("SELECT id, username, password, full_name, email, created_at FROM admin_users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    die("Utilisateur introuvable.");
}

$errors = [];
$success = '';

// Helper: detect if stored password is hashed (bcrypt)
function is_hashed($hash) {
    return (bool) preg_match('/^\$2[ayb]\$.{56}$/', $hash);
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update personal info
    if (isset($_POST['action']) && $_POST['action'] === 'update_info') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        // Basic validation
        if ($full_name === '') $errors[] = "Le nom complet est obligatoire.";
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide.";
        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("UPDATE admin_users SET full_name = ?, email = ? WHERE id = ?");
                $stmt->execute([$full_name, $email, $userId]);
                $success = "Informations personnelles mises à jour avec succès.";
                // Refresh $user
                $stmt = $pdo->prepare("SELECT id, username, password, full_name, email, created_at FROM admin_users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
            } catch (PDOException $e) {
                $errors[] = "Erreur lors de la mise à jour : " . $e->getMessage();
            }
        }
    }

    // Change password
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $new_confirm = $_POST['new_password_confirm'] ?? '';

        if ($current === '' || $new === '' || $new_confirm === '') {
            $errors[] = "Tous les champs du changement de mot de passe sont obligatoires.";
        } elseif ($new !== $new_confirm) {
            $errors[] = "Le nouveau mot de passe et sa confirmation ne correspondent pas.";
        } elseif (strlen($new) < 6) {
            $errors[] = "Le nouveau mot de passe doit comporter au moins 6 caractères.";
        } else {
            // Verify current password against stored value
            $stored = $user['password'];

            $verified = false;
            if ($stored !== null && $stored !== '') {
                if (is_hashed($stored)) {
                    if (password_verify($current, $stored)) $verified = true;
                } else {
                    // older plaintext passwords: allow direct compare
                    if ($current === $stored) $verified = true;
                }
            }

            if (!$verified) {
                $errors[] = "Le mot de passe actuel est incorrect.";
            } else {
                // Hash the new password and update
                $newHash = password_hash($new, PASSWORD_DEFAULT);
                try {
                    $stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
                    $stmt->execute([$newHash, $userId]);
                    $success = "Mot de passe changé avec succès.";
                    // Refresh user
                    $stmt = $pdo->prepare("SELECT id, username, password, full_name, email, created_at FROM admin_users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $user = $stmt->fetch();
                } catch (PDOException $e) {
                    $errors[] = "Erreur lors du changement de mot de passe : " . $e->getMessage();
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Mon Profil - Imprimerie Pro</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="icon" href="assets/logo.png" type="image/png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{
            --primary:#2c3e50; 
            --secondary:#3498db; 
            --accent: #e74c3c;
            --light:#f5f7fa; 
            --dark:#2c3e50; 
            --gray:#95a5a6; 
            --success:#2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        *{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body{
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--light);
            color: var(--dark);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Fixed Sidebar */
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
            left: 0;
            top: 0;
        }

        /* Main Content - Fixed with proper margin */
        .main {
            flex: 1;
            padding: 24px;
            margin-left: 250px;
            width: calc(100% - 250px);
            min-height: 100vh;
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

        /* Header Styles */
        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .brand img {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid #eee;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
        }

        .brand h1 {
            font-size: 1.6rem;
            color: var(--primary);
            margin-bottom: 4px;
        }

        .muted {
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Card Styles */
        .card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            margin-bottom: 24px;
            border: 1px solid #eee;
        }

        h2 {
            font-size: 1.3rem;
            color: var(--primary);
            margin-bottom: 10px;
        }

        /* Form Styles */
        form {
            margin-top: 16px;
        }

        .form-row {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .form-group {
            flex: 1;
            min-width: 250px;
            margin-bottom: 16px;
        }

        label {
            display: block;
            font-size: 0.9rem;
            margin-bottom: 8px;
            color: var(--dark);
            font-weight: 600;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        /* Button Styles */
        .btn {
            display: inline-flex;
            gap: 8px;
            align-items: center;
            padding: 12px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: var(--secondary);
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--secondary);
            color: var(--secondary);
        }

        .btn-outline:hover {
            background: var(--secondary);
            color: white;
        }

        /* Message Styles */
        .messages {
            margin-bottom: 20px;
        }

        .msg-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 15px 20px;
            border-radius: 8px;
            color: #155724;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .msg-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 15px 20px;
            border-radius: 8px;
            color: #721c24;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Split Layout */
        .split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .small {
            font-size: 0.85rem;
            color: var(--gray);
            line-height: 1.5;
        }

        /* Footer */
        footer {
            margin-top: 30px;
            padding: 15px 0;
            border-top: 1px solid #eee;
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }
            
            .main {
                margin-left: 80px;
                width: calc(100% - 80px);
            }
            
            .sidebar-header h2,
            .sidebar-menu span {
                display: none;
            }
            
            .sidebar-header {
                justify-content: center;
                padding: 20px 10px;
            }
            
            .sidebar-menu i {
                margin-right: 0;
                font-size: 1.2rem;
            }
            
            .sidebar-menu li a {
                justify-content: center;
                padding: 15px;
            }
        }

        @media (max-width: 768px) {
            .main {
                padding: 16px;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
                padding: 20px;
            }
            
            .brand h1 {
                font-size: 1.4rem;
            }
            
            .split {
                grid-template-columns: 1fr;
            }
            
            .form-group {
                min-width: 100%;
            }
            
            .card {
                padding: 20px;
            }
        }

        @media (max-width: 576px) {
            .sidebar {
                width: 70px;
            }
            
            .main {
                margin-left: 70px;
                width: calc(100% - 70px);
                padding: 12px;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
         .sidebar-header img {
            width: 210px;
            height: 80px;
            
            object-fit: cover;
        }
    </style>
</head>
<body>
    <aside class="sidebar" role="navigation" aria-label="Sidebar">
        <div class="sidebar-header">
             <img src="REM.jpg" alt="Logo Imprimerie" >
        </div>
        <div class="sidebar-menu">
            <ul>
                <li>
                    <a href="dashboard.php">
                        <i class="fas fa-home"></i>
                        <span>Tableau de Bord</span>
                    </a>
                </li>
                <li>
                    <a href="probleme.php">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Problèmes Urgents</span>
                    </a>
                </li>
                <li>
                    <a href="commande.php">
                        <i class="fas fa-shopping-cart"></i>
                        <span>Commandes</span>
                    </a>
                </li>
                <li>
                    <a href="devis.php">
                        <i class="fas fa-file-invoice"></i>
                        <span>Devis</span>
                    </a>
                </li>
                <li>
                    <a href="depenses.php">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Dépenses</span>
                    </a>
                </li>
                <li>
                    <a href="ajustestock.php">
                        <i class="fas fa-box"></i>
                        <span>Stock</span>
                    </a>
                </li>
                <li>
                    <a href="facture.php">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>Facturation</span>
                    </a>
                </li>
                <li>
                    <a href="employees.php">
                        <i class="fas fa-user-tie"></i>
                        <span>Employés</span>
                    </a>
                </li>
                <li>
                    <a href="gestion.php">
                        <i class="fas fa-cogs"></i>
                        <span>Gestion</span>
                    </a>
                </li>
                <li>
            <a href="ventes.php">
                <i class="fas fa-sales"></i>
                <span>Ventes</span>
            </a>
        </li>
                <li class="active">
                    <a href="profile.php">
                        <i class="fas fa-user"></i>
                        <span>Mon Profil</span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <main class="main">
        <div class="header card" role="banner">
            <div class="brand">
                <img src="assets/logo.png" alt="Logo" onerror="this.onerror=null;this.src='https://via.placeholder.com/40/3498db/ffffff?text=IP'">
                <div>
                    <h1>Mon profil</h1>
                    <div class="muted">Gérez vos informations personnelles et la sécurité du compte</div>
                </div>
            </div>
            <div class="muted">Connecté en tant que: <strong><?php echo htmlspecialchars($user['username']); ?></strong></div>
        </div>

        <div class="messages">
            <?php if ($success): ?>
                <div class="msg-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($errors)): foreach ($errors as $err): ?>
                <div class="msg-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($err); ?>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Personal Information -->
        <section class="card" aria-labelledby="personalInfo">
            <h2 id="personalInfo">Informations personnelles</h2>
            <p class="small">Modifiez votre nom et votre adresse email.</p>

            <form method="POST" action="">
                <input type="hidden" name="action" value="update_info">
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Nom complet *</label>
                        <input id="full_name" name="full_name" type="text" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                </div>
            </form>
            
            <div style="margin-top: 20px;" class="small">
                <p><strong>Informations du compte:</strong></p>
                <p>Nom d'utilisateur: <strong><?php echo htmlspecialchars($user['username']); ?></strong></p>
                <p>Compte créé le : <?php echo date('d/m/Y à H:i', strtotime($user['created_at'])); ?></p>
            </div>
        </section>

        <!-- Security / Change password -->
        <section class="card" aria-labelledby="security">
            <h2 id="security">Sécurité du compte</h2>
            <p class="small">Changez votre mot de passe. Vous devrez connaître votre mot de passe actuel pour le modifier.</p>

            <form method="POST" action="">
                <input type="hidden" name="action" value="change_password">
                <div class="split">
                    <div>
                        <div class="form-group">
                            <label for="current_password">Mot de passe actuel *</label>
                            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                            <small class="muted" style="display: block; margin-top: 5px;">
                                Saisissez votre mot de passe actuel pour confirmer votre identité
                            </small>
                        </div>
                    </div>
                    <div>
                        <div class="form-group">
                            <label for="new_password">Nouveau mot de passe *</label>
                            <input id="new_password" name="new_password" type="password" autocomplete="new-password" required>
                            <small class="muted" style="display: block; margin-top: 5px;">
                                Minimum 6 caractères
                            </small>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="new_password_confirm">Confirmer le nouveau mot de passe *</label>
                        <input id="new_password_confirm" name="new_password_confirm" type="password" required>
                        <small class="muted" style="display: block; margin-top: 5px;">
                            Ressaisissez le nouveau mot de passe pour vérification
                        </small>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-key"></i> Changer le mot de passe
                    </button>
                </div>
            </form>
        </section>

        <footer>
            <div><i class="fas fa-info-circle"></i> Si vous avez oublié votre mot de passe, contactez l'administrateur principal du système.</div>
            <div style="margin-top: 8px;">Version 1.0 - Imprimerie Pro © <?php echo date('Y'); ?></div>
        </footer>
    </main>

    <script>
        // Add some interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Form validation
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    // Basic client-side validation
                    const requiredFields = form.querySelectorAll('[required]');
                    let valid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            valid = false;
                            field.style.borderColor = '#e74c3c';
                        } else {
                            field.style.borderColor = '#ddd';
                        }
                    });
                    
                    // Password confirmation validation
                    const newPass = document.getElementById('new_password');
                    const confirmPass = document.getElementById('new_password_confirm');
                    
                    if (newPass && confirmPass && newPass.value !== confirmPass.value) {
                        valid = false;
                        newPass.style.borderColor = '#e74c3c';
                        confirmPass.style.borderColor = '#e74c3c';
                        alert('Les mots de passe ne correspondent pas.');
                    }
                    
                    if (!valid) {
                        e.preventDefault();
                        return false;
                    }
                });
            });
            
            // Auto-hide success messages after 5 seconds
            const successMsg = document.querySelector('.msg-success');
            if (successMsg) {
                setTimeout(() => {
                    successMsg.style.opacity = '0';
                    successMsg.style.transition = 'opacity 0.5s';
                    setTimeout(() => {
                        successMsg.style.display = 'none';
                    }, 500);
                }, 5000);
            }
            
            // Show password strength indicator
            const newPasswordField = document.getElementById('new_password');
            if (newPasswordField) {
                newPasswordField.addEventListener('input', function() {
                    const strength = checkPasswordStrength(this.value);
                    // You could add a visual indicator here
                });
            }
            
            function checkPasswordStrength(password) {
                let strength = 0;
                if (password.length >= 6) strength++;
                if (password.length >= 8) strength++;
                if (/[A-Z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^A-Za-z0-9]/.test(password)) strength++;
                return strength;
            }
        });
    </script>
</body>
</html>