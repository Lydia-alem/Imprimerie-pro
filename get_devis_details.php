<?php
// Database configuration
define('DB_HOST', '127.0.0.1:3306');
define('DB_NAME', 'imprimerie');
define('DB_USER', 'root');
define('DB_PASS', 'admine');

// Start session
session_start();

// Headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

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
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get devis ID
$devisId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$devisId) {
    echo json_encode(['success' => false, 'message' => 'ID du devis manquant']);
    exit;
}

try {
    // Get devis information
    $stmt = $pdo->prepare("
        SELECT q.*, c.name as client_name, c.email, c.phone, c.address
        FROM quotes q
        LEFT JOIN clients c ON q.client_id = c.id
        WHERE q.id = ?
    ");
    $stmt->execute([$devisId]);
    $devis = $stmt->fetch();
    
    if (!$devis) {
        echo json_encode(['success' => false, 'message' => 'Devis non trouvé']);
        exit;
    }
    
    // Get devis items
    $stmt = $pdo->prepare("
        SELECT qi.*, p.name as product_name
        FROM quote_items qi
        LEFT JOIN products p ON qi.product_id = p.id
        WHERE qi.quote_id = ?
        ORDER BY qi.id
    ");
    $stmt->execute([$devisId]);
    $items = $stmt->fetchAll();
    
    // Format the data
    $formattedDevis = [
        'id' => $devis['id'],
        'client_name' => $devis['client_name'] ?? 'N/A',
        'email' => $devis['email'] ?? '',
        'phone' => $devis['phone'] ?? '',
        'address' => $devis['address'] ?? '',
        'status' => $devis['status'] ?? 'pending',
        'total' => floatval($devis['total'] ?? 0),
        'created_at' => $devis['created_at'] ?? date('Y-m-d H:i:s')
    ];
    
    $formattedItems = [];
    foreach ($items as $item) {
        $formattedItems[] = [
            'description' => $item['description'] ?? '',
            'product_name' => $item['product_name'] ?? '',
            'quantity' => intval($item['quantity'] ?? 0),
            'price' => floatval($item['price'] ?? 0),
            'subtotal' => floatval($item['subtotal'] ?? 0)
        ];
    }
    
    echo json_encode([
        'success' => true,
        'devis' => $formattedDevis,
        'items' => $formattedItems
    ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    
} catch (Exception $e) {
    error_log("Error in get_devis_details.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur interne du serveur: ' . $e->getMessage()
    ]);
}
?>