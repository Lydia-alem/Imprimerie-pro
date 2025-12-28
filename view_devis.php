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



// Récupérer l'ID du devis depuis l'URL
$devis_id = $_GET['id'] ?? 0;

if (!$devis_id) {
    die("ID du devis non spécifié");
}

// Récupérer les informations du devis
$stmt = $pdo->prepare("
    SELECT q.*, c.name as client_name, c.email as client_email, 
           c.phone as client_phone, c.address as client_address
    FROM quotes q
    LEFT JOIN clients c ON q.client_id = c.id
    WHERE q.id = ?
");
$stmt->execute([$devis_id]);
$devis = $stmt->fetch();

if (!$devis) {
    die("Devis non trouvé");
}

// Récupérer les articles du devis
$items_stmt = $pdo->prepare("
    SELECT qi.*, p.name as product_name, p.description as product_description
    FROM quote_items qi
    LEFT JOIN products p ON qi.product_id = p.id
    WHERE qi.quote_id = ?
");
$items_stmt->execute([$devis_id]);
$items = $items_stmt->fetchAll();

// Fonction pour obtenir le statut en français
function getStatusText($status) {
    $statusMap = [
        'pending' => 'En attente',
        'accepted' => 'Accepté',
        'rejected' => 'Rejeté',
        'converted' => 'Converti en commande'
    ];
    
    return $statusMap[$status] ?? $status;
}

// Fonction pour obtenir la classe CSS du statut
function getStatusClass($status) {
    switch ($status) {
        case 'pending':
            return 'status-pending';
        case 'accepted':
            return 'status-accepted';
        case 'rejected':
            return 'status-rejected';
        case 'converted':
            return 'status-converted';
        default:
            return 'status-pending';
    }
}

// Fonction pour formater une date
function formatDate($date) {
    if (!$date) {
        return 'Non définie';
    }
    
    return date('d/m/Y', strtotime($date));
}

// Fonction pour formater le montant
function formatAmount($amount) {
    return number_format($amount, 2, ',', ' ') . ' DA';
}

// Traitement de la conversion en commande
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['convert_to_order'])) {
    // Créer une nouvelle commande à partir du devis
    $order_stmt = $pdo->prepare("
        INSERT INTO orders (client_id, quote_id, status, total, created_at) 
        VALUES (?, ?, 'pending', ?, NOW())
    ");
    $order_stmt->execute([$devis['client_id'], $devis_id, $devis['total']]);
    $order_id = $pdo->lastInsertId();
    
    // Copier les articles du devis vers la commande
    foreach ($items as $item) {
        $item_stmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, description, quantity, price, subtotal) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $item_stmt->execute([
            $order_id, 
            $item['product_id'], 
            $item['description'], 
            $item['quantity'], 
            $item['price'], 
            $item['subtotal']
        ]);
    }
    
    // Mettre à jour le statut du devis
    $update_stmt = $pdo->prepare("UPDATE quotes SET status = 'converted' WHERE id = ?");
    $update_stmt->execute([$devis_id]);
    
    // Rediriger vers la commande
    header("Location: commande_client.php?order_created=" . $order_id);
    exit();
}

// Traitement du changement de statut
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    
    $update_stmt = $pdo->prepare("UPDATE quotes SET status = ? WHERE id = ?");
    $update_stmt->execute([$new_status, $devis_id]);
    
    header("Location: view_devis.php?id=" . $devis_id . "&updated=1");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devis #<?php echo $devis_id; ?> - Imprimerie Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --gray: #95a5a6;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7fa;
            color: #333;
            padding: 20px;
        }

        .devis-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .devis-header {
            background: linear-gradient(90deg, var(--primary) 0%, #1a252f 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .devis-header h1 {
            font-size: 2.2rem;
            margin-bottom: 10px;
        }

        .devis-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .devis-status {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-top: 15px;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-accepted {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .status-converted {
            background: #cce5ff;
            color: #004085;
        }

        .devis-content {
            padding: 40px;
        }

        .company-info, .client-info {
            margin-bottom: 30px;
        }

        .info-section h3 {
            color: var(--primary);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            margin-bottom: 10px;
        }

        .info-label {
            font-weight: 600;
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 1rem;
            color: #333;
        }

        .devis-details {
            margin-top: 40px;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .items-table th {
            background: var(--primary);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        .items-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .items-table tr:nth-child(even) {
            background: #f9f9f9;
        }

        .items-table tr:hover {
            background: #f1f1f1;
        }

        .total-section {
            margin-top: 30px;
            text-align: right;
        }

        .total-line {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            margin-bottom: 10px;
            padding: 10px 0;
        }

        .total-label {
            font-weight: 600;
            margin-right: 20px;
            min-width: 150px;
            text-align: right;
        }

        .total-amount {
            font-size: 1.1rem;
            min-width: 150px;
            text-align: right;
        }

        .grand-total {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--primary);
            border-top: 2px solid var(--primary);
            padding-top: 15px;
            margin-top: 15px;
        }

        .actions-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .status-form {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 0.95rem;
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
            background: #27ae60;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn-secondary {
            background: var(--gray);
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
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

        .print-section {
            text-align: center;
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .devis-container {
                box-shadow: none;
                border-radius: 0;
            }
            
            .actions-section,
            .print-section {
                display: none;
            }
            
            .btn {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .devis-content {
                padding: 20px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .actions-section {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            .status-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .items-table {
                font-size: 0.85rem;
            }
            
            .items-table th,
            .items-table td {
                padding: 10px 5px;
            }
        }
    </style>
</head>
<body>
    <div class="devis-container">
        <div class="devis-header">
            <h1>DEVIS #<?php echo str_pad($devis_id, 5, '0', STR_PAD_LEFT); ?></h1>
            <p>Imprimerie Pro - Votre partenaire d'impression</p>
            <span class="devis-status <?php echo getStatusClass($devis['status']); ?>">
                <?php echo getStatusText($devis['status']); ?>
            </span>
        </div>

        <div class="devis-content">
            <?php if (isset($_GET['updated']) && $_GET['updated'] == 1): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Statut du devis mis à jour avec succès !
            </div>
            <?php endif; ?>

            <div class="info-grid">
                <div class="company-info">
                    <h3><i class="fas fa-print"></i> Société</h3>
                    <div class="info-item">
                        <div class="info-label">Nom</div>
                        <div class="info-value">Imprimerie Pro</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Adresse</div>
                        <div class="info-value">123 Rue de l'Impression, 1000 Tunis</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Téléphone</div>
                        <div class="info-value">+216 71 123 456</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value">contact@imprimeriepro.tn</div>
                    </div>
                </div>

                <div class="client-info">
                    <h3><i class="fas fa-user"></i> Client</h3>
                    <div class="info-item">
                        <div class="info-label">Nom</div>
                        <div class="info-value"><?php echo htmlspecialchars($devis['client_name']); ?></div>
                    </div>
                    <?php if ($devis['client_email']): ?>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($devis['client_email']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($devis['client_phone']): ?>
                    <div class="info-item">
                        <div class="info-label">Téléphone</div>
                        <div class="info-value"><?php echo htmlspecialchars($devis['client_phone']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($devis['client_address']): ?>
                    <div class="info-item">
                        <div class="info-label">Adresse</div>
                        <div class="info-value"><?php echo htmlspecialchars($devis['client_address']); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <div class="info-label">Date du devis</div>
                        <div class="info-value"><?php echo formatDate($devis['created_at']); ?></div>
                    </div>
                </div>
            </div>

            <div class="devis-details">
                <h3><i class="fas fa-list"></i> Détail du devis</h3>
                
                <?php if (empty($items)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Aucun article dans ce devis.
                </div>
                <?php else: ?>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Quantité</th>
                            <th>Prix unitaire</th>
                            <th>Sous-total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <strong><?php echo $item['product_name'] ?: 'Article personnalisé'; ?></strong>
                                <?php if ($item['description']): ?>
                                <div style="font-size: 0.85rem; color: var(--gray); margin-top: 5px;">
                                    <?php echo nl2br(htmlspecialchars($item['description'])); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td><?php echo formatAmount($item['price']); ?></td>
                            <td><?php echo formatAmount($item['subtotal']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <div class="total-section">
                    <div class="total-line">
                        <div class="total-label">Sous-total :</div>
                        <div class="total-amount"><?php echo formatAmount($devis['total']); ?></div>
                    </div>
                    <div class="total-line">
                        <div class="total-label">TVA (0%) :</div>
                        <div class="total-amount">0,00 DA</div>
                    </div>
                    <div class="total-line grand-total">
                        <div class="total-label">TOTAL :</div>
                        <div class="total-amount"><?php echo formatAmount($devis['total']); ?></div>
                    </div>
                </div>
            </div>

            <div class="actions-section">
                <form method="POST" class="status-form">
                    <select name="status" class="form-control" style="width: auto;">
                        <option value="pending" <?php echo $devis['status'] == 'pending' ? 'selected' : ''; ?>>En attente</option>
                        <option value="accepted" <?php echo $devis['status'] == 'accepted' ? 'selected' : ''; ?>>Accepté</option>
                        <option value="rejected" <?php echo $devis['status'] == 'rejected' ? 'selected' : ''; ?>>Rejeté</option>
                        <option value="converted" <?php echo $devis['status'] == 'converted' ? 'selected' : ''; ?>>Converti en commande</option>
                    </select>
                    <button type="submit" name="update_status" class="btn btn-primary">
                        <i class="fas fa-save"></i> Mettre à jour
                    </button>
                </form>

                <div style="display: flex; gap: 10px;">
                    <?php if ($devis['status'] != 'converted'): ?>
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="convert_to_order" class="btn btn-success" onclick="return confirm('Convertir ce devis en commande ?')">
                            <i class="fas fa-exchange-alt"></i> Convertir en commande
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <a href="devis.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Retour aux devis
                    </a>
                    
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Imprimer
                    </button>
                </div>
            </div>

            <div class="print-section">
                <p><strong>Conditions générales :</strong></p>
                <p>1. Ce devis est valable 30 jours à partir de la date d'émission.</p>
                <p>2. Les prix sont exprimés en Dinars Algériens (DA) toutes taxes comprises.</p>
                <p>3. Le délai de livraison commence à courir à partir de la confirmation de la commande.</p>
                <p>4. Toute modification après confirmation pourra entraîner des frais supplémentaires.</p>
            </div>
        </div>
    </div>

    <script>
        // Fonction pour télécharger le devis en PDF (fonctionnalité avancée)
        function downloadPDF() {
            alert('Fonctionnalité PDF à implémenter. Pour le moment, utilisez le bouton "Imprimer".');
        }
    </script>
</body>
</html>