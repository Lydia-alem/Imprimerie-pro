<?php
session_start();

// Database configuration
$host = '127.0.0.1:3306';
$dbname = 'imprimerie';
$username = 'root';
$password = 'admine';

// Initialize variables
$email = $password_input = '';
$error = '';
$success = '';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize form data
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password_input = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    // Basic validation
    if (empty($email) || empty($password_input)) {
        $error = "Veuillez remplir tous les champs";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) && !preg_match('/^[a-zA-Z0-9_]+$/', $email)) {
        $error = "Adresse email ou identifiant invalide";
    } else {
        try {
            // Connect to database
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Search user in admin_users table
            $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE email = :email OR username = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verify password - Using password_verify for hashed passwords
                if (password_verify($password_input, $user['password'])) {
                    // Login successful
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['logged_in'] = true;
                    
                    // If "Remember me" is checked
                    if ($remember) {
                        setcookie('user_email', $email, time() + (30 * 24 * 60 * 60), '/'); // 30 days
                    }
                    
                    // Redirect to dashboard
                    header('Location: dashboard.php');
                    exit();
                } else {
                    // If password is not hashed (for testing with plain text in DB)
                    // Note: In production, always use password_hash() and password_verify()
                    if ($password_input === $user['password']) {
                        // Login successful (for plain text passwords - not recommended)
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['logged_in'] = true;
                        
                        if ($remember) {
                            setcookie('user_email', $email, time() + (30 * 24 * 60 * 60), '/');
                        }
                        
                        header('Location: dashboard.php');
                        exit();
                    } else {
                        $error = "Mot de passe incorrect";
                    }
                }
            } else {
                $error = "Aucun compte trouvé avec cet email/identifiant";
            }
            
        } catch (PDOException $e) {
            $error = "Erreur de connexion à la base de données: " . $e->getMessage();
        }
    }
}

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimerie Admin - Connexion</title>
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

        .login-container {
            display: flex;
            width: 900px;
            height: 600px;
            background: white;
            border-radius: 15px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .login-left {
            flex: 1;
            background: linear-gradient(135deg, var(--primary) 0%, #1a2530 100%);
            color: white;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
        }

        .login-left::after {
            content: '';
            position: absolute;
            bottom: -80px;
            left: -80px;
            width: 250px;
            height: 250px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
        }

        .company-logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-img {
            max-width: 250px;
            height: 100px;
            margin-bottom: 10px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
        }

        .logo-text {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .logo-text i {
            font-size: 2.5rem;
            color: var(--secondary);
        }

        .logo-text h1 {
            font-size: 1.8rem;
            font-weight: 600;
            text-align: center;
        }

        .welcome-text h2 {
            font-size: 2.2rem;
            margin-bottom: 15px;
            font-weight: 600;
            text-align: center;
        }

        .welcome-text p {
            font-size: 1rem;
            line-height: 1.6;
            opacity: 0.8;
            margin-bottom: 30px;
            text-align: center;
        }

        .features {
            margin-top: 30px;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .feature i {
            color: var(--secondary);
            font-size: 1.2rem;
            width: 25px;
        }

        .feature p {
            font-size: 0.95rem;
            opacity: 0.9;
        }

        .login-right {
            flex: 1;
            padding: 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .login-header h2 {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .login-header p {
            color: var(--gray);
            font-size: 0.95rem;
        }

        .login-form {
            width: 100%;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }

        .input-with-icon input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
            outline: none;
        }

        .input-with-icon input:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .remember-me input {
            accent-color: var(--secondary);
        }

        .forgot-password {
            color: var(--secondary);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.3s;
        }

        .forgot-password:hover {
            color: var(--primary);
            text-decoration: underline;
        }

        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-login {
            background: var(--secondary);
            color: white;
            margin-bottom: 20px;
        }

        .btn-login:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
        }

        .divider {
            text-align: center;
            margin: 25px 0;
            position: relative;
            color: var(--gray);
            font-size: 0.9rem;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            width: 45%;
            height: 1px;
            background: #ddd;
        }

        .divider::after {
            content: '';
            position: absolute;
            top: 50%;
            right: 0;
            width: 45%;
            height: 1px;
            background: #ddd;
        }

        .social-login {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .social-btn {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f5f7fa;
            border: 1px solid #ddd;
            cursor: pointer;
            transition: all 0.3s;
        }

        .social-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .social-btn.google:hover {
            background: #db4437;
            color: white;
        }

        .social-btn.facebook:hover {
            background: #4267B2;
            color: white;
        }

        .social-btn.twitter:hover {
            background: #1DA1F2;
            color: white;
        }

        .register-link {
            text-align: center;
            font-size: 0.9rem;
            color: var(--gray);
        }

        .register-link a {
            color: var(--secondary);
            text-decoration: none;
            font-weight: 500;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.95rem;
            text-align: center;
        }

        .alert-error {
            background-color: #ffeaea;
            color: var(--danger);
            border: 1px solid #ffcccc;
        }

        .alert-success {
            background-color: #eaffea;
            color: var(--success);
            border: 1px solid #ccffcc;
        }

        @media (max-width: 950px) {
            .login-container {
                width: 90%;
                height: auto;
            }
        }

        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                height: auto;
            }
            
            .login-left, .login-right {
                padding: 30px;
            }
            
            .login-left {
                text-align: center;
            }
            
            .company-logo {
                margin-bottom: 20px;
            }
        }

        @media (max-width: 480px) {
            .login-container {
                width: 95%;
            }
            
            .login-left, .login-right {
                padding: 20px;
            }
            
            .logo-img {
                max-width: 120px;
            }
            
            .remember-forgot {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <div class="company-logo">
                <img src="REM.jpg" alt="Logo Imprimerie" class="logo-img">
            </div>
            
            <div class="welcome-text">
                <h2>Tableau de Bord Admin</h2>
                <p>Connectez-vous pour gérer vos commandes, suivre la production et analyser les performances de votre imprimerie.</p>
            </div>
            <div class="features">
                <div class="feature">
                    <i class="fas fa-chart-line"></i>
                    <p>Analyses détaillées et rapports de performance</p>
                </div>
                <div class="feature">
                    <i class="fas fa-shopping-cart"></i>
                    <p>Gestion complète des commandes et clients</p>
                </div>
                <div class="feature">
                    <i class="fas fa-cogs"></i>
                    <p>Outils avancés pour optimiser votre production</p>
                </div>
            </div>
        </div>
        <div class="login-right">
            <div class="login-header">
                <h2>Connexion</h2>
                <p>Entrez vos identifiants pour accéder à votre compte</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <form class="login-form" method="POST" action="">
                <div class="form-group">
                    <label for="email">Adresse e-mail ou identifiant</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" id="email" name="email" placeholder="admin@imprimerie.com ou nom d'utilisateur" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Votre mot de passe" required>
                    </div>
                </div>
                <div class="remember-forgot">
                    <div class="remember-me">
                        <input type="checkbox" id="remember" name="remember" <?php echo isset($_COOKIE['user_email']) ? 'checked' : ''; ?>>
                        <label for="remember">Se souvenir de moi</label>
                    </div>
                    <a href="#" class="forgot-password">Mot de passe oublié?</a>
                </div>
                <button type="submit" class="btn btn-login">Se connecter</button>
            </form>
            
            <div class="register-link">
                <p>Nouvel administrateur? <a href="register.php">Créer un compte</a></p>
            </div>
        </div>
    </div>

    <script>
        // Add interactive effects
        const socialButtons = document.querySelectorAll('.social-btn');
        socialButtons.forEach(button => {
            button.addEventListener('click', function() {
                alert('Option de connexion sociale - À implémenter');
            });
        });

        // Auto-fill email if cookie exists
        window.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            <?php if (isset($_COOKIE['user_email'])): ?>
                emailInput.value = "<?php echo htmlspecialchars($_COOKIE['user_email']); ?>";
            <?php endif; ?>
            
            // Logo loading effect
            const logoImg = document.querySelector('.logo-img');
            if (logoImg) {
                logoImg.style.opacity = '0';
                logoImg.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    logoImg.style.transition = 'all 0.8s ease';
                    logoImg.style.opacity = '1';
                    logoImg.style.transform = 'translateY(0)';
                }, 300);
            }
            
            // Form validation enhancement
            const form = document.querySelector('.login-form');
            form.addEventListener('submit', function(e) {
                const email = document.getElementById('email').value;
                const password = document.getElementById('password').value;
                
                if (!email || !password) {
                    e.preventDefault();
                    alert('Veuillez remplir tous les champs');
                    return false;
                }
            });
        });
    </script>
</body>
</html>