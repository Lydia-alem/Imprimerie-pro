<?php
// Database configuration
define('DB_HOST', '127.0.0.1:3306');
define('DB_NAME', 'imprimerie');
define('DB_USER', 'root');
define('DB_PASS', 'admine');

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

// Récupérer les informations du devis (colonnes corrigées)
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

// Récupérer les informations de l'entreprise depuis la table settings
$settings_stmt = $pdo->prepare("SELECT * FROM settings WHERE setting_key IN ('company_name', 'company_address', 'company_phone', 'company_email', 'company_logo', 'company_tax_id', 'company_bank_info')");
$settings_stmt->execute();
$settings = [];
while ($row = $settings_stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

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

// Fonction pour formater une date
function formatDate($date, $format = 'd/m/Y') {
    if (!$date) {
        return 'Non définie';
    }
    
    return date($format, strtotime($date));
}

// Fonction pour formater le montant
function formatAmount($amount) {
    return number_format($amount, 2, ',', ' ') . ' DA';
}

// Calculer le total HT
$total_ht = $devis['total'] ?? 0;
$tva_rate = 0.19; // 19% TVA
$total_tva = $total_ht * $tva_rate;
$total_ttc = $total_ht + $total_tva;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devis #<?php echo str_pad($devis_id, 5, '0', STR_PAD_LEFT); ?> - Impression</title>
    <style>
        /* Styles optimisés pour l'impression */
        @page {
            margin: 1cm;
            size: A4;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
            color: #000;
        }

        body {
            background: white;
            color: black;
            font-size: 12pt;
            line-height: 1.4;
            padding: 0;
        }

        .print-container {
            max-width: 21cm;
            margin: 0 auto;
            padding: 20px;
        }

        /* En-tête */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #333;
        }

        .company-info {
            flex: 2;
        }

        .devis-info {
            flex: 1;
            text-align: right;
        }

        .company-name {
            font-size: 24pt;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .company-details {
            font-size: 10pt;
            line-height: 1.3;
        }

        .devis-title {
            font-size: 18pt;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .devis-number {
            font-size: 14pt;
            font-weight: bold;
        }

        .devis-date {
            font-size: 10pt;
        }

        /* Section client et infos */
        .info-section {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            gap: 30px;
        }

        .client-box, .details-box {
            flex: 1;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 5px;
        }

        .section-title {
            font-size: 12pt;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }

        .info-item {
            margin-bottom: 5px;
            font-size: 10pt;
        }

        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 80px;
        }

        /* Table des articles */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0 30px 0;
            font-size: 10pt;
        }

        .items-table th {
            background: #f5f5f5;
            color: #333;
            padding: 10px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
        }

        .items-table td {
            padding: 8px 10px;
            border: 1px solid #ddd;
            vertical-align: top;
        }

        .items-table tr:nth-child(even) {
            background: #fafafa;
        }

        .description-cell {
            max-width: 250px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* Totaux */
        .totals-section {
            width: 300px;
            margin-left: auto;
            margin-bottom: 30px;
        }

        .total-line {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            font-size: 11pt;
        }

        .total-label {
            font-weight: bold;
        }

        .grand-total {
            font-size: 13pt;
            font-weight: bold;
            color: #2c3e50;
            border-top: 2px solid #333;
            margin-top: 10px;
            padding-top: 10px;
        }

        /* Conditions et signature */
        .conditions-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        .conditions-title {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .conditions-list {
            font-size: 9pt;
            line-height: 1.4;
            margin-bottom: 20px;
        }

        .conditions-list li {
            margin-bottom: 5px;
            margin-left: 20px;
        }

        .signature-area {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }

        .signature-box {
            text-align: center;
            width: 250px;
            padding-top: 40px;
            border-top: 1px solid #333;
        }

        .signature-label {
            font-size: 10pt;
            font-weight: bold;
            margin-top: 5px;
        }

        /* Statut */
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 15px;
            font-size: 9pt;
            font-weight: bold;
            margin-top: 10px;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .status-accepted {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        /* Pied de page */
        .footer {
            margin-top: 50px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 8pt;
            color: #666;
            text-align: center;
        }

        /* Classes utilitaires */
        .no-print {
            display: none;
        }

        .page-break {
            page-break-before: always;
        }

        /* Pour éviter les coupures dans les tableaux */
        table, tr, td, th {
            page-break-inside: avoid;
        }

        /* Styles pour les logos */
        .logo-container {
            margin-bottom: 15px;
        }

        .logo {
            max-width: 200px;
            max-height: 80px;
        }
    </style>
</head>
<body>
    <div class="print-container">
        <!-- En-tête -->
        <div class="header">
            <div class="company-info">
                <?php if (!empty($settings['company_logo'])): ?>
                <div class="logo-container">
                    <img src="<?php echo htmlspecialchars($settings['company_logo']); ?>" alt="Logo" class="logo">
                </div>
                <?php endif; ?>
                
                <div class="company-name">
                    <?php echo !empty($settings['company_name']) ? htmlspecialchars($settings['company_name']) : 'Imprimerie Pro'; ?>
                </div>
                <div class="company-details">
                    <?php if (!empty($settings['company_address'])): ?>
                    <div><?php echo htmlspecialchars($settings['company_address']); ?></div>
                    <?php else: ?>
                    <div>123 Rue de l'Impression, 1000 Tunis</div>
                    <?php endif; ?>
                    
                    <?php if (!empty($settings['company_phone'])): ?>
                    <div>Téléphone : <?php echo htmlspecialchars($settings['company_phone']); ?></div>
                    <?php else: ?>
                    <div>Téléphone : +216 71 123 456</div>
                    <?php endif; ?>
                    
                    <?php if (!empty($settings['company_email'])): ?>
                    <div>Email : <?php echo htmlspecialchars($settings['company_email']); ?></div>
                    <?php else: ?>
                    <div>Email : contact@imprimeriepro.tn</div>
                    <?php endif; ?>
                    
                    <?php if (!empty($settings['company_tax_id'])): ?>
                    <div>Matricule fiscale : <?php echo htmlspecialchars($settings['company_tax_id']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="devis-info">
                <div class="devis-title">DEVIS</div>
                <div class="devis-number">N° <?php echo str_pad($devis_id, 5, '0', STR_PAD_LEFT); ?></div>
                <div class="devis-date">Date : <?php echo formatDate($devis['created_at']); ?></div>
                
                <div class="status-badge status-<?php echo $devis['status']; ?>">
                    <?php echo getStatusText($devis['status']); ?>
                </div>
                
                <?php if (!empty($settings['company_tax_id'])): ?>
                <div style="margin-top: 10px; font-size: 9pt;">
                    Matricule : <?php echo htmlspecialchars($settings['company_tax_id']); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Informations client et détails -->
        <div class="info-section">
            <div class="client-box">
                <div class="section-title">CLIENT</div>
                <div class="info-item">
                    <span class="info-label">Nom :</span>
                    <?php echo htmlspecialchars($devis['client_name']); ?>
                </div>
                <?php if (!empty($devis['client_address'])): ?>
                <div class="info-item">
                    <span class="info-label">Adresse :</span>
                    <?php echo htmlspecialchars($devis['client_address']); ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($devis['client_phone'])): ?>
                <div class="info-item">
                    <span class="info-label">Téléphone :</span>
                    <?php echo htmlspecialchars($devis['client_phone']); ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($devis['client_email'])): ?>
                <div class="info-item">
                    <span class="info-label">Email :</span>
                    <?php echo htmlspecialchars($devis['client_email']); ?>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="details-box">
                <div class="section-title">INFORMATIONS</div>
                <div class="info-item">
                    <span class="info-label">Devis N° :</span>
                    <?php echo str_pad($devis_id, 5, '0', STR_PAD_LEFT); ?>
                </div>
                <div class="info-item">
                    <span class="info-label">Date :</span>
                    <?php echo formatDate($devis['created_at']); ?>
                </div>
                <div class="info-item">
                    <span class="info-label">Validité :</span>
                    30 jours
                </div>
                <div class="info-item">
                    <span class="info-label">Statut :</span>
                    <?php echo getStatusText($devis['status']); ?>
                </div>
            </div>
        </div>

        <!-- Table des articles -->
        <table class="items-table">
            <thead>
                <tr>
                    <th width="40">#</th>
                    <th>Description</th>
                    <th width="80" class="text-center">Qté</th>
                    <th width="80" class="text-center">Unité</th>
                    <th width="100" class="text-right">Prix unitaire</th>
                    <th width="120" class="text-right">Montant HT</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr>
                    <td colspan="6" class="text-center">Aucun article dans ce devis</td>
                </tr>
                <?php else: ?>
                <?php $counter = 1; ?>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td class="text-center"><?php echo $counter++; ?></td>
                    <td class="description-cell">
                        <strong><?php echo $item['product_name'] ? htmlspecialchars($item['product_name']) : 'Article personnalisé'; ?></strong>
                        <?php if ($item['description']): ?>
                        <div style="margin-top: 3px; font-size: 9pt; color: #555;">
                            <?php echo nl2br(htmlspecialchars($item['description'])); ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?php echo $item['quantity']; ?></td>
                    <td class="text-center">unité</td>
                    <td class="text-right"><?php echo formatAmount($item['price']); ?></td>
                    <td class="text-right"><?php echo formatAmount($item['subtotal']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Totaux -->
        <div class="totals-section">
            <div class="total-line">
                <span class="total-label">Total HT :</span>
                <span><?php echo formatAmount($total_ht); ?></span>
            </div>
            <div class="total-line">
                <span class="total-label">TVA (19%) :</span>
                <span><?php echo formatAmount($total_tva); ?></span>
            </div>
            <div class="total-line grand-total">
                <span class="total-label">TOTAL TTC :</span>
                <span><?php echo formatAmount($total_ttc); ?></span>
            </div>
        </div>

        <!-- Conditions générales -->
        <div class="conditions-section">
            <div class="conditions-title">CONDITIONS GÉNÉRALES</div>
            <ul class="conditions-list">
                <li>Ce devis est valable 30 jours à compter de sa date d'émission.</li>
                <li>Les prix sont exprimés en Dinars Algériens (DA) toutes taxes comprises.</li>
                <li>Le délai de livraison commence à courir à partir de la confirmation de la commande.</li>
                <li>Toute modification après confirmation pourra entraîner des frais supplémentaires.</li>
                <li>Les retours doivent être effectués dans les 7 jours suivant la livraison.</li>
                <li>Le paiement doit être effectué dans les 30 jours suivant la date de facturation.</li>
                <li>Tout retard de paiement entraînera des pénalités de retard de 1,5% par mois.</li>
            </ul>
            
            <?php if (!empty($settings['company_bank_info'])): ?>
            <div style="margin-top: 20px; font-size: 9pt;">
                <strong>INFORMATIONS BANCAIRES :</strong><br>
                <?php echo nl2br(htmlspecialchars($settings['company_bank_info'])); ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Signature -->
        <div class="signature-area">
            <div class="signature-box">
                <div class="signature-label">Le client</div>
            </div>
            <div class="signature-box">
                <div class="signature-label">Pour <?php echo !empty($settings['company_name']) ? htmlspecialchars($settings['company_name']) : 'Imprimerie Pro'; ?></div>
            </div>
        </div>

        <!-- Pied de page -->
        <div class="footer">
            <?php echo !empty($settings['company_name']) ? htmlspecialchars($settings['company_name']) : 'Imprimerie Pro'; ?> - 
            <?php echo !empty($settings['company_address']) ? htmlspecialchars($settings['company_address']) : '123 Rue de l\'Impression, 1000 Tunis'; ?> - 
            Tél: <?php echo !empty($settings['company_phone']) ? htmlspecialchars($settings['company_phone']) : '+216 71 123 456'; ?> - 
            Email: <?php echo !empty($settings['company_email']) ? htmlspecialchars($settings['company_email']) : 'contact@imprimeriepro.tn'; ?>
            <?php if (!empty($settings['company_tax_id'])): ?>
            - Matricule fiscale: <?php echo htmlspecialchars($settings['company_tax_id']); ?>
            <?php endif; ?>
        </div>
    </div>
    

    <script>
        // Impression automatique au chargement de la page
        window.onload = function() {
            window.print();
            
            // Redirection après impression (optionnel)
            setTimeout(function() {
                window.close();
            }, 1000);
        };

        // Empêcher la fermeture si l'impression est annulée
        window.addEventListener('afterprint', function() {
            setTimeout(function() {
                if (!document.hasFocus()) {
                    window.close();
                }
            }, 500);
        });
    </script>
</body>
</html>