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

// Vérifier si une commande a été créée
$order_created = $_GET['order_created'] ?? 0;
$order_id = $_GET['order_id'] ?? $order_created;

if (!$order_id) {
    // Rediriger si aucun ID n'est fourni
    header("Location: devis.php");
    exit();
}

// Récupérer les informations de la commande
$stmt = $pdo->prepare("
    SELECT o.*, c.name as client_name, c.email as client_email, 
           c.phone as client_phone, c.address as client_address,
           q.id as quote_id, q.total as quote_total
    FROM orders o
    LEFT JOIN clients c ON o.client_id = c.id
    LEFT JOIN quotes q ON o.quote_id = q.id
    WHERE o.id = ?
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    die("Commande non trouvée");
}

// Récupérer les articles de la commande
$items_stmt = $pdo->prepare("
    SELECT oi.*, p.name as product_name, p.description as product_description
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = ?
");
$items_stmt->execute([$order_id]);
$items = $items_stmt->fetchAll();

// Traitement du changement de statut
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $new_status = $_POST['status'];
    $new_deadline = $_POST['deadline'] ?? null;
    
    $update_stmt = $pdo->prepare("UPDATE orders SET status = ?, deadline = ? WHERE id = ?");
    $update_stmt->execute([$new_status, $new_deadline, $order_id]);
    
    // Ajouter une note d'historique
    if (isset($_POST['status_note']) && !empty($_POST['status_note'])) {
        // Vous pourriez créer une table order_history pour suivre les changements
    }
    
    header("Location: commande_client.php?order_id=" . $order_id . "&updated=1");
    exit();
}

// Traitement de la création de facture
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_invoice'])) {
    // Créer une facture à partir de la commande
    $invoice_stmt = $pdo->prepare("
        INSERT INTO invoices (order_id, client_id, status, total, vat, created_at) 
        VALUES (?, ?, 'unpaid', ?, 0, NOW())
    ");
    $invoice_stmt->execute([$order_id, $order['client_id'], $order['total']]);
    $invoice_id = $pdo->lastInsertId();
    
    // Copier les articles de la commande vers la facture
    foreach ($items as $item) {
        $item_stmt = $pdo->prepare("
            INSERT INTO invoice_items (invoice_id, description, quantity, price, subtotal) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $item_stmt->execute([
            $invoice_id, 
            $item['product_name'] . ($item['description'] ? ': ' . $item['description'] : ''), 
            $item['quantity'], 
            $item['price'], 
            $item['subtotal']
        ]);
    }
    
    header("Location: factures.php?invoice_created=" . $invoice_id);
    exit();
}

// Fonction pour obtenir le statut en français
function getStatusText($status) {
    $statusMap = [
        'pending' => 'En attente',
        'in_production' => 'En production',
        'ready' => 'Prête',
        'delivered' => 'Livrée',
        'cancelled' => 'Annulée'
    ];
    
    return $statusMap[$status] ?? $status;
}

// Fonction pour obtenir la classe CSS du statut
function getStatusClass($status) {
    switch ($status) {
        case 'pending':
            return 'status-pending';
        case 'in_production':
            return 'status-production';
        case 'ready':
            return 'status-ready';
        case 'delivered':
            return 'status-delivered';
        case 'cancelled':
            return 'status-cancelled';
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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commande #<?php echo $order_id; ?> - Imprimerie Pro</title>
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
            --production: #9b59b6;
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

        .order-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .order-header {
            background: linear-gradient(90deg, var(--primary) 0%, #1a252f 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .order-header h1 {
            font-size: 2.2rem;
            margin-bottom: 10px;
        }

        .order-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .order-status {
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

        .status-production {
            background: #e8d4f7;
            color: #4a235a;
        }

        .status-ready {
            background: #d4edda;
            color: #155724;
        }

        .status-delivered {
            background: #cce5ff;
            color: #004085;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .success-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--success);
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .order-content {
            padding: 40px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .info-section h3 {
            color: var(--primary);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-item {
            margin-bottom: 12px;
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

        .order-details {
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
        }

        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
        }

        .status-form {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--primary);
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

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background: #e67e22;
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
            width: 100%;
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

        .timeline {
            margin-top: 40px;
        }

        .timeline h3 {
            color: var(--primary);
            margin-bottom: 20px;
        }

        .timeline-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-top: 20px;
        }

        .timeline-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 3px;
            background: #ddd;
            z-index: 1;
        }

        .timeline-step {
            text-align: center;
            position: relative;
            z-index: 2;
            flex: 1;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: white;
            border: 3px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
            color: #999;
        }

        .step-circle.active {
            border-color: var(--secondary);
            background: var(--secondary);
            color: white;
        }

        .step-circle.completed {
            border-color: var(--success);
            background: var(--success);
            color: white;
        }

        .step-label {
            font-size: 0.9rem;
            color: #666;
        }

        .step-label.active {
            color: var(--secondary);
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .order-content {
                padding: 20px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .timeline-steps {
                flex-wrap: wrap;
                gap: 20px;
            }
            
            .timeline-step {
                flex: 0 0 calc(50% - 10px);
            }
        }
    </style>
</head>
<body>
    <div class="order-container">
        <div class="order-header">
            <h1>COMMANDE #<?php echo str_pad($order_id, 5, '0', STR_PAD_LEFT); ?></h1>
            <p>Créée à partir du devis #<?php echo $order['quote_id'] ? str_pad($order['quote_id'], 5, '0', STR_PAD_LEFT) : 'N/A'; ?></p>
            <span class="order-status <?php echo getStatusClass($order['status']); ?>">
                <?php echo getStatusText($order['status']); ?>
            </span>
            
            <?php if (isset($_GET['order_created'])): ?>
            <div class="success-badge">
                <i class="fas fa-check-circle"></i>
                Commande créée avec succès !
            </div>
            <?php endif; ?>
        </div>

        <div class="order-content">
            <?php if (isset($_GET['updated']) && $_GET['updated'] == 1): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                Statut de la commande mis à jour avec succès !
            </div>
            <?php endif; ?>

            <div class="info-grid">
                <div class="info-section">
                    <h3><i class="fas fa-user"></i> Client</h3>
                    <div class="info-item">
                        <div class="info-label">Nom</div>
                        <div class="info-value"><?php echo htmlspecialchars($order['client_name']); ?></div>
                    </div>
                    <?php if ($order['client_email']): ?>
                    <div class="info-item">
                        <div class="info-label">Email</div>
                        <div class="info-value"><?php echo htmlspecialchars($order['client_email']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($order['client_phone']): ?>
                    <div class="info-item">
                        <div class="info-label">Téléphone</div>
                        <div class="info-value"><?php echo htmlspecialchars($order['client_phone']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($order['client_address']): ?>
                    <div class="info-item">
                        <div class="info-label">Adresse</div>
                        <div class="info-value"><?php echo htmlspecialchars($order['client_address']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="info-section">
                    <h3><i class="fas fa-info-circle"></i> Informations commande</h3>
                    <div class="info-item">
                        <div class="info-label">Date création</div>
                        <div class="info-value"><?php echo formatDate($order['created_at']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Date limite</div>
                        <div class="info-value">
                            <?php echo $order['deadline'] ? formatDate($order['deadline']) : 'Non définie'; ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Devis d'origine</div>
                        <div class="info-value">
                            <?php if ($order['quote_id']): ?>
                            <a href="view_devis.php?id=<?php echo $order['quote_id']; ?>" style="color: var(--secondary); text-decoration: none;">
                                Devis #<?php echo str_pad($order['quote_id'], 5, '0', STR_PAD_LEFT); ?>
                            </a>
                            <?php else: ?>
                            Non associé
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="timeline">
                <h3><i class="fas fa-stream"></i> Progression de la commande</h3>
                <div class="timeline-steps">
                    <?php
                    $steps = [
                        'pending' => 'En attente',
                        'in_production' => 'En production',
                        'ready' => 'Prête',
                        'delivered' => 'Livrée'
                    ];
                    
                    $currentStep = array_search($order['status'], array_keys($steps));
                    $currentStep = $currentStep !== false ? $currentStep : 0;
                    $stepIndex = 0;
                    
                    foreach ($steps as $key => $label):
                    ?>
                    <div class="timeline-step">
                        <div class="step-circle 
                            <?php echo $stepIndex < $currentStep ? 'completed' : ''; ?>
                            <?php echo $stepIndex == $currentStep ? 'active' : ''; ?>">
                            <?php echo $stepIndex + 1; ?>
                        </div>
                        <div class="step-label <?php echo $stepIndex == $currentStep ? 'active' : ''; ?>">
                            <?php echo $label; ?>
                        </div>
                    </div>
                    <?php $stepIndex++; endforeach; ?>
                </div>
            </div>

            <div class="order-details">
                <h3><i class="fas fa-list"></i> Détail de la commande</h3>
                
                <?php if (empty($items)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Aucun article dans cette commande.
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
                        <div class="total-amount"><?php echo formatAmount($order['total']); ?></div>
                    </div>
                    <div class="total-line">
                        <div class="total-label">TVA (0%) :</div>
                        <div class="total-amount">0,00 DA</div>
                    </div>
                    <div class="total-line grand-total">
                        <div class="total-label">TOTAL :</div>
                        <div class="total-amount"><?php echo formatAmount($order['total']); ?></div>
                    </div>
                </div>
            </div>

            <div class="actions-section">
                <div class="action-buttons">
                    <a href="devis.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Retour aux devis
                    </a>
                    
                    <a href="commande.php" class="btn btn-secondary">
                        <i class="fas fa-list"></i> Voir toutes les commandes
                    </a>
                    
                    <form method="POST" style="display: inline;">
                        <button type="submit" name="create_invoice" class="btn btn-success" onclick="return confirm('Créer une facture pour cette commande ?')">
                            <i class="fas fa-file-invoice-dollar"></i> Créer une facture
                        </button>
                    </form>
                    
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Imprimer la commande
                    </button>
                </div>

                <div class="status-form">
                    <h3><i class="fas fa-edit"></i> Mettre à jour le statut</h3>
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Statut :</label>
                                <select name="status" class="form-control" required>
                                    <option value="pending" <?php echo $order['status'] == 'pending' ? 'selected' : ''; ?>>En attente</option>
                                    <option value="in_production" <?php echo $order['status'] == 'in_production' ? 'selected' : ''; ?>>En production</option>
                                    <option value="ready" <?php echo $order['status'] == 'ready' ? 'selected' : ''; ?>>Prête</option>
                                    <option value="delivered" <?php echo $order['status'] == 'delivered' ? 'selected' : ''; ?>>Livrée</option>
                                    <option value="cancelled" <?php echo $order['status'] == 'cancelled' ? 'selected' : ''; ?>>Annulée</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Date limite :</label>
                                <input type="date" name="deadline" class="form-control" 
                                       value="<?php echo $order['deadline'] ? date('Y-m-d', strtotime($order['deadline'])) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Note (optionnelle) :</label>
                            <textarea name="status_note" class="form-control" rows="3" placeholder="Ajouter une note sur le changement de statut..."></textarea>
                        </div>
                        
                        <button type="submit" name="update_status" class="btn btn-primary">
                            <i class="fas fa-save"></i> Mettre à jour la commande
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mettre à jour la timeline selon le statut
        function updateTimeline() {
            // Cette fonction pourrait être utilisée pour des mises à jour dynamiques
        }
        
        // Confirmation pour annuler une commande
        function confirmCancel() {
            if (confirm("Êtes-vous sûr de vouloir annuler cette commande ? Cette action est irréversible.")) {
                // Mettre à jour le statut à annulé
                document.querySelector('select[name="status"]').value = 'cancelled';
                document.querySelector('button[name="update_status"]').click();
            }
        }
    </script>
</body>
</html>