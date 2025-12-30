<?php
session_start();

// Déconnexion de l'utilisateur
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    // Détruire toutes les variables de session
    $_SESSION = array();
    
    // Si vous voulez détruire complètement la session, supprimez aussi le cookie de session
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Détruire la session
    session_destroy();
    
    // Rediriger vers la page de connexion avec un message
    header("Location: index.php?logout=success");
    exit();
}

// Vérifier si l'utilisateur est connecté
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Déconnexion - Imprimerie Admin</title>
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
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-image: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
        }

        .logout-container {
            width: 100%;
            max-width: 500px;
            padding: 40px;
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .logout-header {
            margin-bottom: 30px;
        }

        .logo-img {
            max-width: 120px;
            height: auto;
            margin-bottom: 20px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
        }

        .logout-header h1 {
            font-size: 2.2rem;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .logout-header p {
            color: var(--gray);
            font-size: 1rem;
            line-height: 1.5;
        }

        .logout-icon {
            font-size: 5rem;
            color: var(--accent);
            margin: 30px 0;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .user-info {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 10px;
            margin: 25px 0;
            border-left: 4px solid var(--secondary);
        }

        .user-info h3 {
            color: var(--primary);
            margin-bottom: 10px;
            font-size: 1.2rem;
        }

        .user-details {
            display: flex;
            flex-direction: column;
            gap: 8px;
            text-align: left;
            max-width: 300px;
            margin: 0 auto;
        }

        .user-detail {
            display: flex;
            justify-content: space-between;
        }

        .user-detail span:first-child {
            font-weight: 600;
            color: var(--dark);
        }

        .user-detail span:last-child {
            color: var(--gray);
        }

        .buttons-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 14px 20px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-logout {
            background: var(--accent);
            color: white;
        }

        .btn-logout:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(231, 76, 60, 0.3);
        }

        .btn-cancel {
            background: #f8f9fa;
            color: var(--dark);
            border: 1px solid #ddd;
        }

        .btn-cancel:hover {
            background: #e9ecef;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .btn-dashboard {
            background: var(--secondary);
            color: white;
        }

        .btn-dashboard:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            text-align: center;
            display: none;
        }

        .alert-success {
            background-color: #eaffea;
            color: var(--success);
            border: 1px solid #ccffcc;
            display: block;
        }

        .alert-info {
            background-color: #e8f4fd;
            color: var(--secondary);
            border: 1px solid #b3d9ff;
            display: block;
        }

        .alert-warning {
            background-color: #fff9e6;
            color: var(--warning);
            border: 1px solid #ffe6b3;
            display: block;
        }

        @media (max-width: 768px) {
            .logout-container {
                width: 90%;
                padding: 30px 20px;
            }
            
            .logout-header h1 {
                font-size: 1.8rem;
            }
            
            .logout-icon {
                font-size: 4rem;
            }
        }

        @media (max-width: 480px) {
            .logout-container {
                width: 95%;
                padding: 20px 15px;
            }
            
            .buttons-container {
                gap: 10px;
            }
            
            .btn {
                padding: 12px 15px;
                font-size: 0.95rem;
            }
            
            .user-details {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-header">
            <img src="REM.jpg" alt="Logo Imprimerie" class="logo-img">
            <h1>Déconnexion</h1>
            <p>Gérez votre session de connexion en toute sécurité</p>
        </div>
        
        <?php if (isset($_GET['logout']) && $_GET['logout'] == 'success'): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Déconnexion réussie ! Vous allez être redirigé...
            </div>
            <script>
                setTimeout(function() {
                    window.location.href = 'index.php';
                }, 2000);
            </script>
        <?php endif; ?>
        
        <?php if ($isLoggedIn): ?>
            <div class="logout-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Vous êtes actuellement connecté en tant que <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'Utilisateur'); ?></strong>
            </div>
            
            <div class="user-info">
                <h3>Informations de votre session</h3>
                <div class="user-details">
                    <div class="user-detail">
                        <span>Nom d'utilisateur:</span>
                        <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'Non défini'); ?></span>
                    </div>
                    <div class="user-detail">
                        <span>Nom complet:</span>
                        <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Non défini'); ?></span>
                    </div>
                    <div class="user-detail">
                        <span>Email:</span>
                        <span><?php echo htmlspecialchars($_SESSION['email'] ?? 'Non défini'); ?></span>
                    </div>
                    <div class="user-detail">
                        <span>Rôle:</span>
                        <span><?php echo htmlspecialchars($_SESSION['role'] ?? 'Non défini'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Êtes-vous sûr de vouloir vous déconnecter ? Toutes les données de session seront effacées.
            </div>
            
            <div class="buttons-container">
                <a href="?logout=true" class="btn btn-logout">
                    <i class="fas fa-sign-out-alt"></i> Se déconnecter
                </a>
                
                <a href="dashboard.php" class="btn btn-dashboard">
                    <i class="fas fa-tachometer-alt"></i> Retour au Tableau de Bord
                </a>
                
                
            </div>
            
        <?php else: ?>
            <div class="logout-icon">
                <i class="fas fa-user-slash"></i>
            </div>
            
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Vous n'êtes pas connecté. Aucune session active à fermer.
            </div>
            
            <div class="buttons-container">
                <a href="index.php" class="btn btn-dashboard">
                    <i class="fas fa-sign-in-alt"></i> Se connecter
                </a>
                
                <a href="register.php" class="btn btn-cancel">
                    <i class="fas fa-user-plus"></i> Créer un compte
                </a>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; color: var(--gray); font-size: 0.85rem;">
            <p><i class="fas fa-info-circle"></i> Pour des raisons de sécurité, il est recommandé de vous déconnecter après chaque session, surtout sur un ordinateur partagé.</p>
        </div>
    </div>

    <script>
        // Animation pour les boutons
        document.addEventListener('DOMContentLoaded', function() {
            const buttons = document.querySelectorAll('.btn');
            buttons.forEach(button => {
                button.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px)';
                });
                
                button.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Confirmation pour la déconnexion
            const logoutBtn = document.querySelector('.btn-logout');
            if (logoutBtn) {
                logoutBtn.addEventListener('click', function(e) {
                    if (!confirm('Êtes-vous sûr de vouloir vous déconnecter ?')) {
                        e.preventDefault();
                    }
                });
            }
            
            // Animation de l'icône
            const logoutIcon = document.querySelector('.logout-icon');
            if (logoutIcon) {
                setTimeout(() => {
                    logoutIcon.style.animation = 'none';
                    setTimeout(() => {
                        logoutIcon.style.animation = 'pulse 2s infinite';
                    }, 100);
                }, 4000);
            }
            
            // Auto-redirection si déconnecté avec succès
            <?php if (isset($_GET['logout']) && $_GET['logout'] == 'success'): ?>
                setTimeout(function() {
                    window.location.href = 'index.php';
                }, 2000);
            <?php endif; ?>
        });
    </script>
</body>
</html>