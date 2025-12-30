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
            --primary:#2c3e50; --secondary:#3498db; --light:#f5f7fa; --dark:#2c3e50; --gray:#95a5a6; --success:#2ecc71;
        }
        *{box-sizing:border-box}
        body{margin:0;font-family:Segoe UI, Tahoma, Geneva, Verdana, sans-serif;background:var(--light);color:var(--dark);display:flex;min-height:100vh}
        .sidebar{width:250px;background:var(--primary);color:#fff;padding-bottom:30px}
        .sidebar .logo{padding:20px;display:flex;align-items:center;gap:12px}
        .sidebar .logo img{width:48px;height:48px;border-radius:6px;object-fit:cover;border:2px solid rgba(255,255,255,0.08)}
        .sidebar .logo h2{margin:0;font-size:1.2rem}
        .sidebar nav{padding:10px}
        .sidebar a{display:block;color:#fff;padding:12px 16px;text-decoration:none;border-radius:6px;margin:6px 8px}
        .sidebar a.active, .sidebar a:hover{background:var(--secondary)}
        .main{flex:1;padding:24px}
        .header{background:#fff;padding:16px;border-radius:10px;box-shadow:0 4px 8px rgba(0,0,0,0.05);display:flex;justify-content:space-between;align-items:center;margin-bottom:18px}
        .header .brand {display:flex; align-items:center; gap:12px;}
        .header .brand img {width:40px;height:40px;border-radius:6px; object-fit:cover; border:1px solid #eee;}
        .card{background:#fff;padding:18px;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,0.04);margin-bottom:16px}
        h1{font-size:1.4rem;margin:0}
        form .form-row{display:flex;gap:12px;flex-wrap:wrap}
        .form-group{flex:1;min-width:220px;margin-bottom:12px}
        label{display:block;font-size:0.9rem;margin-bottom:6px;color:var(--dark);font-weight:600}
        input[type="text"], input[type="email"], input[type="password"]{width:100%;padding:10px;border:1px solid #ddd;border-radius:6px}
        .btn{display:inline-flex;gap:8px;align-items:center;padding:10px 14px;border-radius:6px;border:0;cursor:pointer}
        .btn-primary{background:var(--secondary);color:#fff}
        .btn-outline{background:transparent;border:1px solid var(--secondary);color:var(--secondary)}
        .muted{color:var(--gray);font-size:0.9rem}
        .messages{margin-bottom:12px}
        .msg-success{background:#e7f8ef;border:1px solid #c6f0d6;padding:10px;border-radius:6px;color:#13633a}
        .msg-error{background:#ffeaea;border:1px solid #f5c6c6;padding:10px;border-radius:6px;color:#8a1f17}
        .small{font-size:0.85rem;color:var(--gray)}
        .split{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        @media(max-width:880px){.split{grid-template-columns:1fr}}
    </style>
</head>
<body>
    <aside class="sidebar" role="navigation" aria-label="Sidebar">
        <div class="logo">
            <!-- Primary logo file in repo. If not present, fallback to placeholder using onerror. -->
            <img src="assets/logo.png" alt="Imprimerie Pro Logo" onerror="this.onerror=null;this.src='https://via.placeholder.com/48/3498db/ffffff?text=IP'">
            <h2>Imprimerie Pro</h2>
        </div>
        <nav>
            <a href="index.php"><i class="fas fa-home"></i> &nbsp; Tableau de bord</a>
            <a href="gestion.php"><i class="fas fa-users"></i> &nbsp; Clients</a>
            <a href="commande.php"><i class="fas fa-shopping-cart"></i> &nbsp; Commandes</a>
            <a href="ventes.php"><i class="fas fa-file-invoice-dollar"></i> &nbsp; Ventes</a>
            <a href="profile.php" class="active"><i class="fas fa-user"></i> &nbsp; Mon profil</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> &nbsp; Déconnexion</a>
        </nav>
    </aside>

    <main class="main">
        <div class="header card" role="banner">
            <div class="brand">
                <!-- small logo in header (also falls back to placeholder if not found) -->
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
                <div class="msg-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (!empty($errors)): foreach ($errors as $err): ?>
                <div class="msg-error"><?php echo htmlspecialchars($err); ?></div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Personal Information -->
        <section class="card" aria-labelledby="personalInfo">
            <h2 id="personalInfo">Informations personnelles</h2>
            <p class="small">Modifiez votre nom et votre adresse email.</p>

            <form method="POST" action="" style="margin-top:12px">
                <input type="hidden" name="action" value="update_info">
                <div class="form-row">
                    <div class="form-group">
                        <label for="full_name">Nom complet</label>
                        <input id="full_name" name="full_name" type="text" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                </div>

                <div style="margin-top:10px">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
                </div>
            </form>
            <div style="margin-top:12px" class="small">Compte créé le : <?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></div>
        </section>

        <!-- Security / Change password -->
        <section class="card" aria-labelledby="security">
            <h2 id="security">Sécurité</h2>
            <p class="small">Changez votre mot de passe. Vous devrez connaître votre mot de passe actuel pour le modifier.</p>

            <form method="POST" action="" style="margin-top:12px">
                <input type="hidden" name="action" value="change_password">
                <div class="split">
                    <div>
                        <div class="form-group">
                            <label for="current_password">Mot de passe actuel</label>
                            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                        </div>
                    </div>
                    <div>
                        <div class="form-group">
                            <label for="new_password">Nouveau mot de passe</label>
                            <input id="new_password" name="new_password" type="password" autocomplete="new-password" required>
                        </div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="new_password_confirm">Confirmer le nouveau mot de passe</label>
                        <input id="new_password_confirm" name="new_password_confirm" type="password" required>
                    </div>
                </div>

                <div style="margin-top:10px">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-key"></i> Changer le mot de passe</button>
                </div>
            </form>
        </section>

        <footer style="margin-top:18px" class="small muted">
            <div>Si vous avez oublié votre mot de passe, contactez l'administrateur principal.</div>
        </footer>
    </main>

</body>
</html>