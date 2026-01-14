<?php
// Database connection
$host = '127.0.0.1:3306';
$dbname = 'imprimerie';
$username = 'root';
$password = 'admine';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['save_bc'])) {
        try {
            $pdo->beginTransaction();
            
            // Save bon de commande
            $client_id = $_POST['bc_client_id'];
            $reference = $_POST['bc_reference'];
            $bc_date = $_POST['bc_date'];
            $delivery_date = $_POST['delivery_date'];
            $payment_terms = $_POST['payment_terms'];
            $delivery_address = $_POST['delivery_address'];
            $notes = $_POST['bc_notes'];
            $status = 'pending';
            
            // Calculate total from items
            $total = 0;
            $quantities = $_POST['bc_item_quantity'] ?? [];
            $prices = $_POST['bc_item_price'] ?? [];
            
            for ($i = 0; $i < count($quantities); $i++) {
                if (!empty($quantities[$i]) && !empty($prices[$i])) {
                    $total += ($quantities[$i] * $prices[$i]);
                }
            }
            
            $bcStmt = $pdo->prepare("
                INSERT INTO orders (client_id, status, deadline, total, created_at) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $bcStmt->execute([
                $client_id, 
                $status, 
                $delivery_date, 
                $total,
                date('Y-m-d H:i:s')
            ]);
            $bc_id = $pdo->lastInsertId();
            
            // Save bc items
            $descriptions = $_POST['bc_item_description'] ?? [];
            $quantities = $_POST['bc_item_quantity'] ?? [];
            $units = $_POST['bc_item_unit'] ?? [];
            $prices = $_POST['bc_item_price'] ?? [];
            $specifications = $_POST['bc_item_specifications'] ?? [];
            
            for ($i = 0; $i < count($descriptions); $i++) {
                if (!empty($descriptions[$i])) {
                    $description = $descriptions[$i];
                    if (!empty($specifications[$i])) {
                        $description .= " | Spécifications: " . $specifications[$i];
                    }
                    
                    $itemStmt = $pdo->prepare("
                        INSERT INTO order_items (order_id, description, quantity, price, subtotal) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $itemStmt->execute([
                        $bc_id,
                        $description . " (" . ($units[$i] ?? 'unité') . ")",
                        $quantities[$i] ?? 1,
                        $prices[$i] ?? 0,
                        ($quantities[$i] ?? 0) * ($prices[$i] ?? 0)
                    ]);
                }
            }
            
            $pdo->commit();
            
            header("Location: bondecommande.php?success=1&bc_id=" . $bc_id);
            exit();
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $error_message = "Erreur lors de l'enregistrement: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['delete_bc']) && isset($_POST['bc_id'])) {
        try {
            $bc_id = $_POST['bc_id'];
            
            // Delete associated items first
            $deleteItemsStmt = $pdo->prepare("DELETE FROM order_items WHERE order_id = ?");
            $deleteItemsStmt->execute([$bc_id]);
            
            // Delete the bon de commande
            $deleteBcStmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
            $deleteBcStmt->execute([$bc_id]);
            
            header("Location: bondecommande.php?success=3");
            exit();
            
        } catch(PDOException $e) {
            $error_message = "Erreur lors de la suppression: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['update_status']) && isset($_POST['bc_id'])) {
        try {
            $bc_id = $_POST['bc_id'];
            $new_status = $_POST['new_status'];
            
            $updateStmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $updateStmt->execute([$new_status, $bc_id]);
            
            header("Location: bondecommande.php?success=4&bc_id=" . $bc_id);
            exit();
            
        } catch(PDOException $e) {
            $error_message = "Erreur lors de la mise à jour: " . $e->getMessage();
        }
    }
}

// Fetch data from database
try {
    // Fetch clients
    $clientsStmt = $pdo->query("SELECT * FROM clients ORDER BY name");
    $clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch existing bons de commande
    $bcStmt = $pdo->query("
        SELECT o.*, c.name as client_name, c.email, c.phone, c.address 
        FROM orders o 
        LEFT JOIN clients c ON o.client_id = c.id 
        WHERE o.quote_id IS NULL
        ORDER BY o.created_at DESC 
        LIMIT 50
    ");
    $bonsCommande = $bcStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate next BC number
    $lastBcStmt = $pdo->query("SELECT id FROM orders ORDER BY id DESC LIMIT 1");
    $lastBc = $lastBcStmt->fetch(PDO::FETCH_ASSOC);
    $nextBCNumber = "BC-" . date('Y') . "-" . str_pad(($lastBc ? $lastBc['id'] + 1 : 1), 4, '0', STR_PAD_LEFT);
    
} catch(PDOException $e) {
    die("Erreur lors du chargement des données: " . $e->getMessage());
}

// Get BC data for preview if bc_id is set
$preview_bc = null;
$preview_bc_items = null;
if (isset($_GET['bc_id'])) {
    $bc_id = $_GET['bc_id'];
    $stmt = $pdo->prepare("
        SELECT o.*, c.name as client_name, c.email, c.phone, c.address 
        FROM orders o 
        LEFT JOIN clients c ON o.client_id = c.id 
        WHERE o.id = ?
    ");
    $stmt->execute([$bc_id]);
    $preview_bc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($preview_bc) {
        $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $itemsStmt->execute([$bc_id]);
        $preview_bc_items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimerie Admin - Bons de Commande</title>
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
            --info: #17a2b8;
            --gray: #95a5a6;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --bc-color: #9b59b6;
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
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: var(--primary);
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
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
            width: 210px;
            height: 80px;
            object-fit: cover;
        }

        .sidebar-menu {
            padding: 20px 0;
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

        /* Main Content Styles */
        .main-content {
            margin-left: 250px;
            min-height: 100vh;
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

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h2 {
            font-size: 1.8rem;
            color: var(--primary);
        }

        /* Onglets de navigation */
        .tabs {
            display: flex;
            background: white;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .tab {
            flex: 1;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }

        .tab:hover {
            background: var(--light);
        }

        .tab.active {
            background: var(--light);
            border-bottom: 3px solid var(--bc-color);
            font-weight: 600;
            color: var(--bc-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* BC Container */
        .bc-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        @media (max-width: 992px) {
            .bc-container {
                grid-template-columns: 1fr;
            }
        }

        /* Formulaire de BC */
        .bc-form, .bc-preview {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
        }

        .bc-form {
            border-top: 5px solid var(--bc-color);
        }

        .bc-preview {
            border-top: 5px solid var(--bc-color);
        }

        .form-section {
            margin-bottom: 25px;
        }

        .form-section h3 {
            color: var(--primary);
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--light);
            font-size: 1.2rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 15px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--dark);
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 0.95rem;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            border-color: var(--bc-color);
            outline: none;
        }

        /* Table des Articles */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 0.8rem;
        }

        .items-table th, .items-table td {
            padding: 8px 6px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .items-table th {
            background-color: #f8f9fa;
            color: var(--primary);
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .items-table input, .items-table select, .items-table textarea {
            width: 100%;
            padding: 6px 4px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.8rem;
        }

        .items-table textarea {
            resize: vertical;
            min-height: 40px;
        }

        .items-table .total-cell {
            font-weight: 600;
            color: var(--primary);
        }

        .btn-icon {
            background: none;
            border: none;
            color: var(--danger);
            cursor: pointer;
            font-size: 1rem;
            transition: color 0.3s;
        }

        .btn-icon:hover {
            color: #c0392b;
        }

        .btn-add-item {
            background: var(--success);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: background 0.3s;
            font-size: 0.9rem;
        }

        .btn-add-item:hover {
            background: #27ae60;
        }

        /* Aperçu */
        .bc-header-preview {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light);
        }

        .bc-header-preview h3 {
            color: var(--bc-color);
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .bc-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .company-info, .client-info {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #eee;
        }

        .company-info h4, .client-info h4 {
            color: var(--primary);
            margin-bottom: 10px;
            font-size: 1rem;
        }

        .company-info p, .client-info p {
            margin-bottom: 5px;
            color: #555;
            font-size: 0.85rem;
        }

        .bc-meta {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .meta-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #eee;
        }

        .meta-label {
            font-weight: 600;
            color: var(--primary);
        }

        .meta-value {
            color: #555;
        }

        .items-table-preview {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 0.8rem;
        }

        .items-table-preview th {
            background: var(--bc-color);
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: 500;
        }

        .items-table-preview td {
            padding: 8px 6px;
            border-bottom: 1px solid #eee;
        }

        .items-table-preview tfoot td {
            padding: 10px;
            font-weight: 600;
            background: #f8f9fa;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .total-label {
            font-weight: 600;
            color: var(--primary);
        }

        .total-value {
            font-weight: 600;
            color: var(--dark);
        }

        .grand-total {
            font-size: 1.1rem;
            color: var(--primary);
            border-top: 2px solid var(--primary);
            margin-top: 10px;
            padding-top: 10px;
        }

        .bc-terms {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            font-size: 0.85rem;
            color: #555;
        }

        .bc-terms h4 {
            color: var(--primary);
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .bc-actions {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary {
            background: var(--bc-color);
            color: white;
        }

        .btn-primary:hover {
            background: #8e44ad;
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

        .btn-info {
            background: var(--info);
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        /* Print Modal */
        .print-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .print-modal.active {
            display: flex;
        }

        .print-content {
            background: white;
            width: 90%;
            max-width: 1000px;
            border-radius: 10px;
            padding: 30px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }

        .print-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--primary);
        }

        .print-header .logo {
            width: 120px;
            height: 120px;
            margin: 0 auto 15px;
        }

        .print-header .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .print-header h2 {
            color: var(--primary);
            font-size: 1.8rem;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .print-header p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 3px;
        }

        .print-bc-info {
            text-align: center;
            margin-bottom: 25px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .print-bc-info h3 {
            color: var(--bc-color);
            font-size: 1.4rem;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .print-bc-meta {
            display: flex;
            justify-content: center;
            gap: 30px;
            font-size: 0.9rem;
            color: #555;
        }

        .print-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .print-company, .print-client {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        .print-company h4, .print-client h4 {
            color: var(--primary);
            margin-bottom: 10px;
            font-size: 1rem;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }

        .print-company p, .print-client p {
            margin-bottom: 5px;
            color: #555;
            font-size: 0.85rem;
        }

        .print-items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            font-size: 0.75rem;
        }

        .print-items-table th {
            background: var(--bc-color);
            color: white;
            padding: 10px 6px;
            text-align: left;
            font-weight: 500;
            border: 1px solid #ddd;
        }

        .print-items-table td {
            padding: 8px 6px;
            border: 1px solid #ddd;
        }

        .print-totals {
            float: right;
            width: 300px;
            margin-top: 20px;
        }

        .print-total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px dashed #ddd;
            font-size: 0.9rem;
        }

        .print-grand-total {
            font-size: 1.1rem;
            font-weight: 700;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid #333;
            color: var(--primary);
        }

        .print-footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 0.85rem;
        }

        .print-terms {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            font-size: 0.85rem;
        }

        .print-terms h4 {
            color: var(--primary);
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .print-signatures {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }

        .signature-box {
            text-align: center;
        }

        .signature-line {
            width: 100%;
            height: 1px;
            background: #333;
            margin: 40px 0 10px;
        }

        .close-modal {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--danger);
        }

        /* Messages */
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9rem;
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

        /* Document list styles */
        .document-list {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
        }

        .documents-table-container {
            overflow-x: auto;
            margin-bottom: 30px;
        }

        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-in_production {
            background: #cce5ff;
            color: #004085;
        }

        .status-ready {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-delivered {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .card-1 .stat-icon { background: var(--bc-color); color: white; }
        .card-2 .stat-icon { background: #2ecc71; color: white; }
        .card-3 .stat-icon { background: #3498db; color: white; }
        .card-4 .stat-icon { background: #f39c12; color: white; }

        .stat-info h3 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        /* Modal de confirmation */
        .confirmation-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .confirmation-modal.active {
            display: flex;
        }

        .confirmation-content {
            background: white;
            border-radius: 10px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            text-align: center;
        }

        .confirmation-content h3 {
            color: var(--danger);
            margin-bottom: 15px;
        }

        .confirmation-content p {
            margin-bottom: 25px;
            color: #555;
        }

        .confirmation-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
        }

        /* Responsive */
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
            
            .main-content {
                margin-left: 80px;
            }
        }

        @media (max-width: 768px) {
            .search-bar input {
                width: 200px;
            }
            
            .print-details-grid, .print-signatures {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .print-totals {
                width: 100%;
                float: none;
            }
            
            .bc-info-grid {
                grid-template-columns: 1fr;
            }
            
            .items-table th, .items-table td {
                padding: 6px 4px;
                font-size: 0.7rem;
            }
            
            .items-table input, .items-table select, .items-table textarea {
                padding: 4px 2px;
                font-size: 0.7rem;
            }
            
            .bc-meta {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .search-bar {
                display: none;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media print {
            body * {
                visibility: hidden;
            }
            .print-content, .print-content * {
                visibility: visible;
            }
            .print-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                box-shadow: none;
                padding: 20px;
                background: white;
            }
            .close-modal, .no-print {
                display: none;
            }
            .print-header .logo {
                width: 100px;
                height: 100px;
            }
            @page {
                margin: 20mm;
            }
        }

        /* BC specific styles */
        .bc-section {
            background: linear-gradient(to right, rgba(155, 89, 182, 0.1), rgba(155, 89, 182, 0.05));
            border-left: 4px solid var(--bc-color);
        }

        /* Unit column in items table */
        .unit-column {
            width: 70px;
        }

        /* Small buttons */
        .btn-sm {
            padding: 8px 15px;
            font-size: 0.85rem;
        }

        /* Action buttons in table */
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        /* Column widths */
        .bc-price-col { width: 100px; }
        .bc-total-col { width: 100px; }
        .bc-action-col { width: 120px; }

        /* Search results highlight */
        .highlight {
            background-color: yellow;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="REM.jpg" alt="Logo Imprimerie">
        </div>
        <div class="sidebar-menu">
    <ul>
        <li >
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
        <li >
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
        <li >
            <a href="facture.php">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Facturation</span>
            </a>
        </li>
        <li class="active">
                    <a href="bondecommande.php">
                        <i class="fas fa-clipboard-list"></i>
                        <span>Bon de Commande</span>
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
        <li>
                    <a href="admin_dettes.php">
                        <i class="fas fa-hand-holding-usd"></i>
                        <span>Mes Dettes</span>
                    </a>
                </li>
        <li>
        <li>
            <a href="profile.php">
                <i class="fas fa-user"></i>
                <span>Mon Profil</span>
            </a>
        </li>
    </ul>
</div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <h1>Gestion des Bons de Commande</h1>
                <p style="color: #7f8c8d; font-size: 0.9rem;">Bienvenue, Admin</p>
            </div>
            <div class="header-right">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" id="globalSearch" placeholder="Rechercher...">
                </div>
                <div class="user-profile">
                    <img src="" alt="Admin">
                    <span>Admin</span>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Success/Error Messages -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                    if ($_GET['success'] == 1) {
                        echo "Bon de commande enregistré avec succès! Numéro: " . ($_GET['bc_id'] ?? '');
                    } elseif ($_GET['success'] == 2) {
                        echo "Bon de commande mis à jour avec succès!";
                    } elseif ($_GET['success'] == 3) {
                        echo "Bon de commande supprimé avec succès!";
                    } elseif ($_GET['success'] == 4) {
                        echo "Statut mis à jour avec succès!";
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="tabs">
                <div class="tab active" data-tab="create">Créer un Bon de Commande</div>
                <div class="tab" data-tab="list">Liste des Bons de Commande</div>
            </div>

            <!-- Onglet Création -->
            <div class="tab-content active" id="create-tab">
                <div class="page-header">
                    <h2>Créer un nouveau bon de commande</h2>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="save_bc" value="1">
                    
                    <div class="bc-container">
                        <!-- Formulaire de BC -->
                        <div class="bc-form">
                            <div class="form-section">
                                <h3>Informations du client</h3>
                                <div class="form-group">
                                    <label for="bc_client_id">Sélectionner un client *</label>
                                    <select id="bc_client_id" name="bc_client_id" class="form-control" required>
                                        <option value="">-- Sélectionner un client --</option>
                                        <?php foreach ($clients as $client): ?>
                                            <option value="<?php echo $client['id']; ?>" 
                                                    data-address="<?php echo htmlspecialchars($client['address'] ?? ''); ?>"
                                                    data-phone="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>"
                                                    data-email="<?php echo htmlspecialchars($client['email'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($client['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="client_address">Adresse de livraison</label>
                                        <textarea id="client_address" name="delivery_address" class="form-control" rows="2" required></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="client_contact">Contact</label>
                                        <input type="text" id="client_contact" class="form-control" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3>Détails du bon de commande</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="bc_reference">Référence BC</label>
                                        <input type="text" id="bc_reference" name="bc_reference" 
                                               class="form-control" value="<?php echo $nextBCNumber; ?>" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label for="bc_date">Date de commande</label>
                                        <input type="date" id="bc_date" name="bc_date" 
                                               class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="delivery_date">Date de livraison souhaitée</label>
                                        <input type="date" id="delivery_date" name="delivery_date" 
                                               class="form-control" value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="payment_terms">Conditions de paiement</label>
                                        <select id="payment_terms" name="payment_terms" class="form-control">
                                            <option value="30% acompte, solde à livraison">30% acompte, solde à livraison</option>
                                            <option value="50% acompte, solde à livraison">50% acompte, solde à livraison</option>
                                            <option value="Comptant à la livraison">Comptant à la livraison</option>
                                            <option value="30 jours net">30 jours net</option>
                                            <option value="45 jours net">45 jours net</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3>Articles commandés</h3>
                                <table class="items-table">
                                    <thead>
                                        <tr>
                                            <th width="30">N°</th>
                                            <th>Désignation *</th>
                                            <th class="unit-column">Unité</th>
                                            <th width="70">Quantité *</th>
                                            <th class="bc-price-col">Prix U. (DA) *</th>
                                            <th class="bc-total-col">Total (DA)</th>
                                            <th>Spécifications</th>
                                            <th width="40">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="bcItems">
                                        <!-- Les articles seront ajoutés ici dynamiquement -->
                                    </tbody>
                                </table>
                                <button type="button" class="btn-add-item" id="addBCItem">
                                    <i class="fas fa-plus"></i> Ajouter un article
                                </button>
                            </div>

                            <div class="form-section">
                                <h3>Totaux</h3>
                                <div class="total-row">
                                    <span class="total-label">Nombre d'articles:</span>
                                    <span class="total-value" id="bcTotalQty">0</span>
                                </div>
                                <div class="total-row">
                                    <span class="total-label">Valeur totale:</span>
                                    <span class="total-value" id="bcTotalValue">0.00 DA</span>
                                </div>
                                <div class="total-row grand-total">
                                    <span class="total-label">Montant total de la commande:</span>
                                    <span class="total-value" id="bcGrandTotal">0.00 DA</span>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3>Notes et conditions spéciales</h3>
                                <div class="form-group">
                                    <textarea id="bc_notes" name="bc_notes" class="form-control" rows="4" 
                                              placeholder="Conditions spéciales, exigences particulières, notes..."></textarea>
                                </div>
                            </div>

                            <div class="form-section bc-section">
                                <h3>Validation</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="validator_name">Validé par</label>
                                        <input type="text" id="validator_name" class="form-control" value="Admin" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label for="validation_date">Date de validation</label>
                                        <input type="text" id="validation_date" class="form-control" value="<?php echo date('d/m/Y'); ?>" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Aperçu du BC -->
                        <div class="bc-preview">
                            <div class="bc-header-preview">
                                <img src="REM.jpg" alt="logo" height="50" width="100">
                                <h3>BON DE COMMANDE</h3>
                                <p>Référence: <span id="previewBCReference"><?php echo $nextBCNumber; ?></span></p>
                            </div>

                            <div class="bc-info-grid">
                                <div class="company-info">
                                    <h4>Fournisseur:</h4>
                                    <p><strong>REM</strong></p>
                                    <p>Rue Bellil Abd Allah lotissement 118 N° 119,Setif 19000</p>
                                    <p>Setif Centre, Algérie</p>
                                    <p>Tél: 0660639631 / 0560988875</p>
                                    <p>NIF: 298619280219028</p>
                                    <p>RC: 15A0512508</p>
                                    <p>N° Article: 19018372051</p>
                                </div>
                                <div class="client-info">
                                    <h4>Client:</h4>
                                    <p id="previewBCClientName">-</p>
                                    <p id="previewBCClientAddress">-</p>
                                    <p id="previewBCClientContact">-</p>
                                </div>
                            </div>

                            <div class="bc-meta">
                                <div class="meta-item">
                                    <span class="meta-label">Date de commande:</span>
                                    <span class="meta-value" id="previewBCDate"><?php echo date('d/m/Y'); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Livraison souhaitée:</span>
                                    <span class="meta-value" id="previewDeliveryDate"><?php echo date('d/m/Y', strtotime('+7 days')); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Conditions paiement:</span>
                                    <span class="meta-value" id="previewPaymentTerms">30% acompte, solde à livraison</span>
                                </div>
                            </div>

                            <table class="items-table-preview">
                                <thead>
                                    <tr>
                                        <th width="30">N°</th>
                                        <th>Désignation</th>
                                        <th width="50">Unité</th>
                                        <th width="50">Qté</th>
                                        <th width="70">Prix U.</th>
                                        <th width="80">Total</th>
                                    </tr>
                                </thead>
                                <tbody id="previewBCItems">
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 20px; color: #999;">
                                            Aucun article ajouté
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" style="text-align: right; font-weight: 600;">Total commande:</td>
                                        <td colspan="2" id="previewBCTotalValue">0.00 DA</td>
                                    </tr>
                                </tfoot>
                            </table>

                            <div class="bc-terms">
                                <h4>Notes et conditions</h4>
                                <p id="previewBCNotes">-</p>
                                
                                <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #ddd;">
                                    <h5 style="color: var(--primary); margin-bottom: 10px;">Engagements</h5>
                                    <p style="font-size: 0.8rem; color: #666;">
                                        1. Le présent bon de commande constitue un engagement ferme des deux parties.<br>
                                        2. Les délais de livraison sont indicatifs et peuvent varier selon les disponibilités.<br>
                                        3. Toute modification doit être confirmée par écrit.
                                    </p>
                                </div>
                            </div>

                            <div class="bc-actions">
                                <button type="button" class="btn btn-success" id="calculateBC">
                                    <i class="fas fa-calculator"></i> Calculer
                                </button>
                                <button type="button" class="btn btn-primary" id="previewBC">
                                    <i class="fas fa-eye"></i> Prévisualiser
                                </button>
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-save"></i> Enregistrer
                                </button>
                                <button type="button" class="btn btn-info" id="printBC">
                                    <i class="fas fa-print"></i> Imprimer
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Onglet Liste -->
            <div class="tab-content" id="list-tab">
                <div class="page-header">
                    <h2>Liste des bons de commande</h2>
                    <div>
                        <button class="btn btn-primary" id="refreshList">
                            <i class="fas fa-sync-alt"></i> Actualiser
                        </button>
                    </div>
                </div>

                <div class="document-list">
                    <div class="document-filters">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="filterStatusBC">Statut</label>
                                <select id="filterStatusBC" class="form-control">
                                    <option value="all">Tous les statuts</option>
                                    <option value="pending">En attente</option>
                                    <option value="in_production">En production</option>
                                    <option value="ready">Prêt</option>
                                    <option value="delivered">Livré</option>
                                    <option value="cancelled">Annulé</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="filterDate">Période</label>
                                <select id="filterDate" class="form-control">
                                    <option value="all">Toutes les dates</option>
                                    <option value="today">Aujourd'hui</option>
                                    <option value="week">Cette semaine</option>
                                    <option value="month">Ce mois</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="documents-table-container">
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th width="100">Référence</th>
                                    <th>Client</th>
                                    <th width="100">Date</th>
                                    <th width="100">Livraison</th>
                                    <th width="120">Montant (DA)</th>
                                    <th width="100">Statut</th>
                                    <th width="200" class="bc-action-col">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="bcList">
                                <?php foreach ($bonsCommande as $bc): ?>
                                <tr class="bc-row" data-status="<?php echo $bc['status']; ?>" 
                                    data-date="<?php echo date('Y-m-d', strtotime($bc['created_at'])); ?>">
                                    <td>BC-<?php echo str_pad($bc['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td class="client-name"><?php echo htmlspecialchars($bc['client_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($bc['created_at'])); ?></td>
                                    <td><?php echo $bc['deadline'] ? date('d/m/Y', strtotime($bc['deadline'])) : '-'; ?></td>
                                    <td><?php echo number_format($bc['total'], 2, ',', ' '); ?> DA</td>
                                    <td>
                                        <span class="status status-<?php echo $bc['status']; ?>">
                                            <?php 
                                            switch($bc['status']) {
                                                case 'pending': echo 'En attente'; break;
                                                case 'in_production': echo 'En production'; break;
                                                case 'ready': echo 'Prêt'; break;
                                                case 'delivered': echo 'Livré'; break;
                                                case 'cancelled': echo 'Annulé'; break;
                                                default: echo $bc['status'];
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-primary btn-sm" onclick="printExistingBC(<?php echo $bc['id']; ?>)">
                                                <i class="fas fa-print"></i>
                                            </button>
                                            <button class="btn btn-info btn-sm" onclick="viewBC(<?php echo $bc['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-warning btn-sm" onclick="showStatusModal(<?php echo $bc['id']; ?>)">
                                                <i class="fas fa-sync"></i>
                                            </button>
                                            <button class="btn btn-danger btn-sm" onclick="showDeleteModal(<?php echo $bc['id']; ?>, 'BC-<?php echo str_pad($bc['id'], 4, '0', STR_PAD_LEFT); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($bonsCommande)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 30px;">
                                        <p>Aucun bon de commande trouvé</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="document-stats">
                        <div class="stats-cards">
                            <?php 
                            $totalBC = count($bonsCommande);
                            $pendingBC = count(array_filter($bonsCommande, function($bc) {
                                return $bc['status'] == 'pending';
                            }));
                            $inProductionBC = count(array_filter($bonsCommande, function($bc) {
                                return $bc['status'] == 'in_production';
                            }));
                            $totalRevenueBC = array_sum(array_column($bonsCommande, 'total'));
                            ?>
                            <div class="stat-card card-1">
                                <div class="stat-icon">
                                    <i class="fas fa-file-contract"></i>
                                </div>
                                <div class="stat-info">
                                    <h3><?php echo $totalBC; ?></h3>
                                    <p>Bons de Commande</p>
                                </div>
                            </div>
                            <div class="stat-card card-2">
                                <div class="stat-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="stat-info">
                                    <h3><?php echo $pendingBC; ?></h3>
                                    <p>En attente</p>
                                </div>
                            </div>
                            <div class="stat-card card-3">
                                <div class="stat-icon">
                                    <i class="fas fa-cogs"></i>
                                </div>
                                <div class="stat-info">
                                    <h3><?php echo $inProductionBC; ?></h3>
                                    <p>En production</p>
                                </div>
                            </div>
                            <div class="stat-card card-4">
                                <div class="stat-icon">
                                    <i class="fas fa-euro-sign"></i>
                                </div>
                                <div class="stat-info">
                                    <h3><?php echo number_format($totalRevenueBC, 0, ',', ' '); ?> DA</h3>
                                    <p>Valeur totale</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal d'impression BC -->
    <div class="print-modal" id="printBCModal">
        <div class="print-content">
            <button class="close-modal" id="closeBCModal">&times;</button>
            
            <div id="printBCContent">
                <div class="print-header">
                    <div class="logo">
                        <img src="REM.jpg" alt="Logo Imprimerie">
                    </div>
                    
                    <p>NIF: 298619280219028 | RC: 15A0512508 | N° Article: 19018372051</p>
                </div>

                <div class="print-bc-info">
                    <h3>BON DE COMMANDE <span id="printBCReference"><?php echo $nextBCNumber; ?></span></h3>
                    <div class="print-bc-meta">
                        <div><strong>Date:</strong> <span id="printBCDate"><?php echo date('d/m/Y'); ?></span></div>
                        <div><strong>Livraison:</strong> <span id="printDeliveryDateBC"><?php echo date('d/m/Y', strtotime('+7 days')); ?></span></div>
                    </div>
                </div>

                <div class="print-details-grid">
                    <div class="print-company">
                        <h4>Fournisseur:</h4>
                        <p><strong>REM</strong></p>
                        <p>Rue Bellil Abd Allah lotissement 118 N° 119,Setif 19000</p>
                        <p>Setif Centre, Algérie</p>
                        <p>Tél: 0660639631 / 0560988875</p>
                        <p>Email: Rymemballagemoderne@gmail.com</p>
                    </div>
                    <div class="print-client">
                        <h4>Client:</h4>
                        <p id="printBCClientName">-</p>
                        <p id="printBCClientAddress">-</p>
                        <p id="printBCClientContact">-</p>
                    </div>
                </div>

                <div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 0.9rem;">
                        <div>
                            <strong>Conditions de paiement:</strong> <span id="printPaymentTermsBC">30% acompte, solde à livraison</span>
                        </div>
                        <div>
                            <strong>Validé par:</strong> <span id="printValidator">Admin</span>
                        </div>
                    </div>
                </div>

                <table class="print-items-table">
                    <thead>
                        <tr>
                            <th width="30">N°</th>
                            <th>Désignation</th>
                            <th width="50">Unité</th>
                            <th width="50">Qté</th>
                            <th width="60">Prix U.</th>
                            <th width="70">Total</th>
                        </tr>
                    </thead>
                    <tbody id="printBCItems">
                        <!-- Les articles seront insérés ici -->
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" style="text-align: right; font-weight: 600; background: #f8f9fa;">Total commande:</td>
                            <td style="font-weight: 600; background: #f8f9fa;" id="printBCTotalValue">0.00 DA</td>
                        </tr>
                    </tfoot>
                </table>

                <div class="print-terms">
                    <h4>Notes et conditions</h4>
                    <p id="printBCNotes">-</p>
                    
                    <div style="margin-top: 20px;">
                        <h4>Conditions générales</h4>
                        <p style="font-size: 0.85rem;">
                            1. Le présent bon de commande constitue un engagement ferme des deux parties.<br>
                            2. Les délais de livraison sont indicatifs et peuvent varier selon les disponibilités.<br>
                            3. Toute modification doit être confirmée par écrit.<br>
                            4. Les prix sont indiqués en dinars algériens (DA) et sont hors taxes.<br>
                            5. En cas de retard de livraison non justifié, le client peut annuler la commande.
                        </p>
                    </div>
                </div>

                <div class="print-signatures">
                    <div class="signature-box">
                        <p><strong>Le Fournisseur</strong></p>
                        <div class="signature-line"></div>
                        <p>Signature & cachet</p>
                        <p>Date: ________________</p>
                    </div>
                    <div class="signature-box">
                        <p><strong>Le Client</strong></p>
                        <div class="signature-line"></div>
                        <p>Signature & cachet</p>
                        <p>Date: ________________</p>
                    </div>
                </div>
                
                <div class="no-print" style="margin-top: 30px; text-align: center;">
                    <button class="btn btn-primary" id="doPrintBC">
                        <i class="fas fa-print"></i> Imprimer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div class="confirmation-modal" id="deleteModal">
        <div class="confirmation-content">
            <h3>Confirmer la suppression</h3>
            <p id="deleteMessage">Êtes-vous sûr de vouloir supprimer ce bon de commande ?</p>
            <form method="POST" action="" id="deleteForm">
                <input type="hidden" name="delete_bc" value="1">
                <input type="hidden" name="bc_id" id="deleteBCId">
                <div class="confirmation-actions">
                    <button type="button" class="btn btn-secondary" id="cancelDelete">
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-danger">
                        Supprimer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de changement de statut -->
    <div class="confirmation-modal" id="statusModal">
        <div class="confirmation-content">
            <h3>Changer le statut</h3>
            <p>Choisissez le nouveau statut pour ce bon de commande:</p>
            <form method="POST" action="" id="statusForm">
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" name="bc_id" id="statusBCId">
                <div class="form-group">
                    <select name="new_status" class="form-control" required>
                        <option value="pending">En attente</option>
                        <option value="in_production">En production</option>
                        <option value="ready">Prêt</option>
                        <option value="delivered">Livré</option>
                        <option value="cancelled">Annulé</option>
                    </select>
                </div>
                <div class="confirmation-actions" style="margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" id="cancelStatus">
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Mettre à jour
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Variables globales
        let bcItems = [];
        let bcItemCount = 0;

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Ajouter un premier article au BC
            addNewBCItem();
            
            // Mettre à jour l'aperçu
            updateBCPreview();
            
            // Configurer les écouteurs d'événements
            setupEventListeners();
            
            // Initialiser les onglets
            initTabs();
            
            // Load client data on select change
            loadClientData();
            
            // Setup search functionality
            setupSearch();
            
            // Setup filters
            setupFilters();
            
            // Load preview BC if exists
            <?php if ($preview_bc): ?>
            loadBCForPreview(<?php echo json_encode($preview_bc); ?>, <?php echo json_encode($preview_bc_items ?? []); ?>);
            <?php endif; ?>
        });

        // Load client data when selected
        function loadClientData() {
            const clientSelect = document.getElementById('bc_client_id');
            const clientAddress = document.getElementById('client_address');
            const clientContact = document.getElementById('client_contact');
            const previewClientName = document.getElementById('previewBCClientName');
            const previewClientAddress = document.getElementById('previewBCClientAddress');
            const previewClientContact = document.getElementById('previewBCClientContact');
            
            clientSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (this.value) {
                    const clientName = selectedOption.text;
                    const address = selectedOption.getAttribute('data-address') || '';
                    const phone = selectedOption.getAttribute('data-phone') || '';
                    const email = selectedOption.getAttribute('data-email') || '';
                    
                    // Update form fields
                    clientAddress.value = address;
                    clientContact.value = phone + (email ? ' | ' + email : '');
                    
                    // Update preview
                    previewClientName.textContent = clientName;
                    previewClientAddress.textContent = address;
                    previewClientContact.textContent = phone + (email ? ' | ' + email : '');
                } else {
                    clientAddress.value = '';
                    clientContact.value = '';
                    previewClientName.textContent = '-';
                    previewClientAddress.textContent = '-';
                    previewClientContact.textContent = '-';
                }
            });
        }

        // Load BC for preview
        function loadBCForPreview(bcData, bcItemsData) {
            // Fill client data
            const clientSelect = document.getElementById('bc_client_id');
            const clientOption = Array.from(clientSelect.options).find(opt => opt.value == bcData.client_id);
            if (clientOption) {
                clientSelect.value = bcData.client_id;
                clientSelect.dispatchEvent(new Event('change'));
            }
            
            // Fill BC details
            document.getElementById('bc_reference').value = 'BC-' + bcData.id.toString().padStart(4, '0');
            document.getElementById('bc_date').value = new Date(bcData.created_at).toISOString().split('T')[0];
            document.getElementById('delivery_date').value = bcData.deadline ? new Date(bcData.deadline).toISOString().split('T')[0] : '';
            
            // Clear existing items
            bcItems = [];
            document.getElementById('bcItems').innerHTML = '';
            bcItemCount = 0;
            
            // Add BC items
            bcItemsData.forEach((item, index) => {
                addNewBCItem();
                const itemId = `bc-item-${bcItemCount}`;
                const itemRow = document.getElementById(itemId);
                
                if (itemRow) {
                    // Parse description for specifications
                    const desc = item.description || '';
                    
                    // Extract specifications
                    const specMatch = desc.match(/Spécifications:\s*([^|]+)/);
                    if (specMatch) {
                        itemRow.querySelector('.bc-item-specifications').value = specMatch[1].trim();
                    }
                    
                    // Extract basic description (before first parenthesis or pipe)
                    const baseDescMatch = desc.match(/^([^(|]+)/);
                    if (baseDescMatch) {
                        itemRow.querySelector('.bc-item-desc').value = baseDescMatch[1].trim();
                    }
                    
                    // Unit (inside parentheses)
                    const unitMatch = desc.match(/\(([^)]+)\)/);
                    if (unitMatch) {
                        itemRow.querySelector('.bc-item-unit').value = unitMatch[1].trim();
                    }
                    
                    itemRow.querySelector('.bc-item-qty').value = item.quantity;
                    itemRow.querySelector('.bc-item-price').value = item.price || 0;
                    
                    // Update item object
                    const itemObj = bcItems.find(i => i.id === itemId);
                    if (itemObj) {
                        itemObj.description = item.description;
                        itemObj.quantity = item.quantity;
                        itemObj.price = item.price || 0;
                        itemObj.specifications = specMatch ? specMatch[1].trim() : '';
                        
                        calculateBCItemTotal(itemId);
                    }
                }
            });
            
            // Calculate totals
            calculateBC();
            
            // Update preview
            updateBCPreview();
            
            // Switch to create tab
            document.querySelector('.tab[data-tab="create"]').click();
        }

        // Gestion des onglets
        function initTabs() {
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    document.querySelectorAll('.tab-content').forEach(content => {
                        content.classList.remove('active');
                    });
                    document.getElementById(`${tabId}-tab`).classList.add('active');
                });
            });
        }

        // ============ FONCTIONS BC ============
        function addNewBCItem() {
            bcItemCount++;
            const itemId = `bc-item-${bcItemCount}`;
            
            const newItem = {
                id: itemId,
                description: '',
                unit: 'unité',
                quantity: 1,
                price: 0,
                specifications: '',
                total: 0
            };
            
            bcItems.push(newItem);
            
            const itemRow = document.createElement('tr');
            itemRow.id = itemId;
            itemRow.innerHTML = `
                <td>${bcItemCount}</td>
                <td>
                    <input type="text" name="bc_item_description[]" class="bc-item-desc form-control" 
                           placeholder="Désignation de l'article" required>
                </td>
                <td class="unit-column">
                    <select name="bc_item_unit[]" class="bc-item-unit form-control">
                        <option value="unité">unité</option>
                        <option value="paquet">paquet</option>
                        <option value="mètre">mètre</option>
                        <option value="kg">kg</option>
                        <option value="rouleau">rouleau</option>
                        <option value="carton">carton</option>
                        <option value="lot">lot</option>
                        <option value="heure">heure</option>
                    </select>
                </td>
                <td>
                    <input type="number" name="bc_item_quantity[]" class="bc-item-qty form-control" 
                           min="1" step="1" value="1" placeholder="1" required>
                </td>
                <td class="bc-price-col">
                    <input type="number" name="bc_item_price[]" class="bc-item-price form-control" 
                           min="0" step="0.01" value="0" placeholder="0.00" required>
                </td>
                <td class="bc-total-col bc-item-total">0.00 DA</td>
                <td>
                    <textarea name="bc_item_specifications[]" class="bc-item-specifications form-control" 
                              placeholder="Couleur, dimensions, qualité..."></textarea>
                </td>
                <td><button type="button" class="btn-icon remove-bc-item"><i class="fas fa-trash"></i></button></td>
            `;
            
            document.getElementById('bcItems').appendChild(itemRow);
            
            addBCItemEventListeners(itemId);
            calculateBCItemTotal(itemId);
            updateBCPreview();
        }

        function addBCItemEventListeners(itemId) {
            const itemRow = document.getElementById(itemId);
            const descInput = itemRow.querySelector('.bc-item-desc');
            const unitSelect = itemRow.querySelector('.bc-item-unit');
            const qtyInput = itemRow.querySelector('.bc-item-qty');
            const priceInput = itemRow.querySelector('.bc-item-price');
            const specsInput = itemRow.querySelector('.bc-item-specifications');
            const removeBtn = itemRow.querySelector('.remove-bc-item');
            
            descInput.addEventListener('input', function() {
                updateBCItem(itemId, 'description', this.value);
                updateBCPreview();
            });
            
            unitSelect.addEventListener('change', function() {
                updateBCItem(itemId, 'unit', this.value);
                updateBCPreview();
            });
            
            qtyInput.addEventListener('input', function() {
                updateBCItem(itemId, 'quantity', parseInt(this.value) || 1);
                calculateBCItemTotal(itemId);
                calculateBC();
            });
            
            priceInput.addEventListener('input', function() {
                updateBCItem(itemId, 'price', parseFloat(this.value) || 0);
                calculateBCItemTotal(itemId);
                calculateBC();
            });
            
            specsInput.addEventListener('input', function() {
                updateBCItem(itemId, 'specifications', this.value);
            });
            
            removeBtn.addEventListener('click', function() {
                removeBCItem(itemId);
            });
        }

        function updateBCItem(id, field, value) {
            const item = bcItems.find(item => item.id === id);
            if (item) {
                item[field] = value;
            }
        }

        function calculateBCItemTotal(id) {
            const item = bcItems.find(item => item.id === id);
            if (item) {
                item.total = item.quantity * item.price;
                
                const totalCell = document.querySelector(`#${id} .bc-item-total`);
                totalCell.textContent = formatCurrency(item.total);
            }
        }

        function removeBCItem(id) {
            bcItems = bcItems.filter(item => item.id !== id);
            const itemRow = document.getElementById(id);
            if (itemRow) {
                itemRow.remove();
            }
            
            const itemRows = document.querySelectorAll('#bcItems tr');
            itemRows.forEach((row, index) => {
                row.cells[0].textContent = index + 1;
            });
            
            calculateBC();
            updateBCPreview();
        }

        function calculateBC() {
            let totalQty = 0;
            let totalValue = 0;
            
            bcItems.forEach(item => {
                totalQty += (item.quantity || 0);
                totalValue += (item.total || 0);
            });
            
            document.getElementById('bcTotalQty').textContent = totalQty;
            document.getElementById('bcTotalValue').textContent = formatCurrency(totalValue);
            document.getElementById('bcGrandTotal').textContent = formatCurrency(totalValue);
            
            document.getElementById('previewBCTotalValue').textContent = formatCurrency(totalValue);
            
            return { totalQty, totalValue };
        }

        function updateBCPreview() {
            const previewItemsContainer = document.getElementById('previewBCItems');
            previewItemsContainer.innerHTML = '';
            
            if (bcItems.length === 0) {
                previewItemsContainer.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 20px; color: #999;">
                            Aucun article ajouté
                        </td>
                    </tr>
                `;
            } else {
                bcItems.forEach((item, index) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${index + 1}</td>
                        <td>${item.description || 'Article'}${item.specifications ? ' (' + item.specifications + ')' : ''}</td>
                        <td>${item.unit || 'unité'}</td>
                        <td>${formatNumber(item.quantity)}</td>
                        <td>${formatCurrency(item.price)}</td>
                        <td>${formatCurrency(item.total)}</td>
                    `;
                    previewItemsContainer.appendChild(row);
                });
            }
            
            const totals = calculateBC();
            
            const paymentTerms = document.getElementById('payment_terms').value;
            const notes = document.getElementById('bc_notes').value;
            
            document.getElementById('previewPaymentTerms').textContent = paymentTerms;
            document.getElementById('previewBCNotes').textContent = notes || '-';
            
            document.getElementById('previewBCDate').textContent = formatDate(document.getElementById('bc_date').value);
            document.getElementById('previewDeliveryDate').textContent = formatDate(document.getElementById('delivery_date').value);
        }

        function setupEventListeners() {
            document.getElementById('addBCItem').addEventListener('click', function() {
                addNewBCItem();
                calculateBC();
            });
            
            document.getElementById('calculateBC').addEventListener('click', calculateBC);
            
            document.getElementById('bc_client_id').addEventListener('change', updateBCPreview);
            document.getElementById('bc_date').addEventListener('input', updateBCPreview);
            document.getElementById('delivery_date').addEventListener('input', updateBCPreview);
            document.getElementById('payment_terms').addEventListener('change', updateBCPreview);
            document.getElementById('bc_notes').addEventListener('input', updateBCPreview);
            
            document.getElementById('previewBC').addEventListener('click', function() {
                const result = calculateBC();
                if (bcItems.length === 0) {
                    alert('Veuillez ajouter au moins un article au bon de commande.');
                    return;
                }
                
                fillPrintData();
                document.getElementById('printBCModal').classList.add('active');
            });
            
            document.getElementById('printBC').addEventListener('click', function() {
                const result = calculateBC();
                if (bcItems.length === 0) {
                    alert('Veuillez ajouter au moins un article au bon de commande.');
                    return;
                }
                
                printDocument();
            });
            
            document.getElementById('refreshList').addEventListener('click', function() {
                location.reload();
            });
            
            document.getElementById('closeBCModal').addEventListener('click', function() {
                document.getElementById('printBCModal').classList.remove('active');
            });
            
            document.getElementById('doPrintBC').addEventListener('click', function() {
                printFromModal();
            });
            
            // Delete modal
            document.getElementById('cancelDelete').addEventListener('click', function() {
                document.getElementById('deleteModal').classList.remove('active');
            });
            
            // Status modal
            document.getElementById('cancelStatus').addEventListener('click', function() {
                document.getElementById('statusModal').classList.remove('active');
            });
        }

        function fillPrintData() {
            const result = calculateBC();
            const clientSelect = document.getElementById('bc_client_id');
            const selectedOption = clientSelect.options[clientSelect.selectedIndex];
            const clientName = selectedOption?.text || '-';
            const address = selectedOption?.getAttribute('data-address') || '-';
            const phone = selectedOption?.getAttribute('data-phone') || '';
            const email = selectedOption?.getAttribute('data-email') || '';
            
            document.getElementById('printBCReference').textContent = 
                document.getElementById('bc_reference').value;
            document.getElementById('printBCDate').textContent = 
                formatDate(document.getElementById('bc_date').value);
            document.getElementById('printDeliveryDateBC').textContent = 
                formatDate(document.getElementById('delivery_date').value);
            document.getElementById('printPaymentTermsBC').textContent = 
                document.getElementById('payment_terms').value;
            
            document.getElementById('printBCClientName').textContent = clientName;
            document.getElementById('printBCClientAddress').textContent = address;
            document.getElementById('printBCClientContact').textContent = phone + (email ? ' | ' + email : '');
            
            const printItemsContainer = document.getElementById('printBCItems');
            printItemsContainer.innerHTML = '';
            
            bcItems.forEach((item, index) => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${index + 1}</td>
                    <td>${item.description || 'Article'}${item.specifications ? '<br><small>' + item.specifications + '</small>' : ''}</td>
                    <td>${item.unit || 'unité'}</td>
                    <td>${formatNumber(item.quantity)}</td>
                    <td>${formatCurrency(item.price)}</td>
                    <td>${formatCurrency(item.total)}</td>
                `;
                printItemsContainer.appendChild(row);
            });
            
            document.getElementById('printBCTotalValue').textContent = formatCurrency(result.totalValue);
            
            const notes = document.getElementById('bc_notes').value;
            document.getElementById('printBCNotes').textContent = notes || '-';
        }

        function printDocument() {
            const result = calculateBC();
            if (bcItems.length === 0) {
                alert('Veuillez ajouter au moins un article au bon de commande.');
                return;
            }
            
            const printContent = generatePrintContent();
            const printWindow = window.open('', '_blank', 'width=800,height=600');
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Bon de Commande</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .bc-header { text-align: center; margin-bottom: 30px; }
                        .bc-header h2 { color: #9b59b6; }
                        .company-info, .client-info { 
                            border: 1px solid #ddd; 
                            padding: 15px; 
                            margin-bottom: 20px; 
                            border-radius: 5px;
                        }
                        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                        th { background: #9b59b6; color: white; padding: 10px; text-align: left; }
                        td { padding: 8px; border-bottom: 1px solid #ddd; }
                        .total-row { display: flex; justify-content: space-between; margin-bottom: 10px; }
                        .grand-total { font-weight: bold; font-size: 1.1em; border-top: 2px solid #333; padding-top: 10px; }
                        .signatures { display: flex; justify-content: space-between; margin-top: 40px; }
                        .signature-box { text-align: center; }
                        .signature-line { width: 200px; height: 1px; background: #333; margin: 40px auto 10px; }
                        @media print {
                            @page { margin: 20mm; }
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    ${printContent}
                    <div class="no-print" style="text-align: center; margin-top: 20px;">
                        <button onclick="window.print()" style="padding: 10px 20px; background: #9b59b6; color: white; border: none; border-radius: 5px; cursor: pointer;">
                            Imprimer
                        </button>
                        <button onclick="window.close()" style="padding: 10px 20px; background: #95a5a6; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
                            Fermer
                        </button>
                    </div>
                    <script>
                        window.onload = function() {
                            setTimeout(function() {
                                window.print();
                            }, 500);
                        };
                    <\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
        }

        function generatePrintContent() {
            const result = calculateBC();
            const clientSelect = document.getElementById('bc_client_id');
            const selectedOption = clientSelect.options[clientSelect.selectedIndex];
            const clientName = selectedOption?.text || '-';
            const address = selectedOption?.getAttribute('data-address') || '-';
            const phone = selectedOption?.getAttribute('data-phone') || '';
            const email = selectedOption?.getAttribute('data-email') || '';
            
            let itemsHtml = '';
            bcItems.forEach((item, index) => {
                itemsHtml += `
                    <tr>
                        <td>${index + 1}</td>
                        <td>${item.description || 'Article'}${item.specifications ? '<br><small>' + item.specifications + '</small>' : ''}</td>
                        <td>${item.unit || 'unité'}</td>
                        <td>${formatNumber(item.quantity)}</td>
                        <td>${formatCurrency(item.price)}</td>
                        <td>${formatCurrency(item.total)}</td>
                    </tr>
                `;
            });
            
            return `
                <div class="bc-header">
                    <img src="REM.jpg" alt="Logo" style="max-width: 150px; margin-bottom: 10px;">
                    <h1>BON DE COMMANDE ${document.getElementById('bc_reference').value}</h1>
                    <p>NIF: 298619280219028 | RC: 15A0512508 | N° Article: 19018372051</p>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <div>
                        <p><strong>Date de commande:</strong> ${formatDate(document.getElementById('bc_date').value)}</p>
                        <p><strong>Livraison souhaitée:</strong> ${formatDate(document.getElementById('delivery_date').value)}</p>
                    </div>
                    <div>
                        <p><strong>Conditions paiement:</strong> ${document.getElementById('payment_terms').value}</p>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <div class="company-info" style="flex: 1; margin-right: 10px;">
                        <h3>Fournisseur:</h3>
                        <p><strong>REM</strong></p>
                        <p>Rue Bellil Abd Allah lotissement 118 N° 119,Setif 19000</p>
                        <p>Setif Centre, Algérie</p>
                        <p>Tél: 0660639631 / 0560988875</p>
                        <p>Email: Rymemballagemoderne@gmail.com</p>
                    </div>
                    <div class="client-info" style="flex: 1; margin-left: 10px;">
                        <h3>Client:</h3>
                        <p><strong>${clientName}</strong></p>
                        <p>${address}</p>
                        <p>${phone + (email ? ' | ' + email : '')}</p>
                    </div>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Désignation</th>
                            <th>Unité</th>
                            <th>Qté</th>
                            <th>Prix U.</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHtml}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="5" style="text-align: right; font-weight: bold;">Total commande:</td>
                            <td style="font-weight: bold;">${formatCurrency(result.totalValue)}</td>
                        </tr>
                    </tfoot>
                </table>
                
                <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                    <p><strong>Notes:</strong> ${document.getElementById('bc_notes').value || '-'}</p>
                </div>
                
                <div style="margin-top: 20px;">
                    <h4>Conditions générales</h4>
                    <p style="font-size: 0.85rem;">
                        1. Le présent bon de commande constitue un engagement ferme des deux parties.<br>
                        2. Les délais de livraison sont indicatifs et peuvent varier selon les disponibilités.<br>
                        3. Toute modification doit être confirmée par écrit.<br>
                        4. Les prix sont indiqués en dinars algériens (DA) et sont hors taxes.<br>
                        5. En cas de retard de livraison non justifié, le client peut annuler la commande.
                    </p>
                </div>
                
                <div class="signatures">
                    <div class="signature-box">
                        <p><strong>Le Fournisseur</strong></p>
                        <div class="signature-line"></div>
                        <p>Signature & cachet</p>
                        <p>Date: ________________</p>
                    </div>
                    <div class="signature-box">
                        <p><strong>Le Client</strong></p>
                        <div class="signature-line"></div>
                        <p>Signature & cachet</p>
                        <p>Date: ________________</p>
                    </div>
                </div>
            `;
        }

        function printFromModal() {
            window.print();
        }

        // Fonctions utilitaires
        function formatCurrency(amount) {
            return new Intl.NumberFormat('fr-DZ', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount) + ' DA';
        }

        function formatNumber(num) {
            return new Intl.NumberFormat('fr-DZ', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 3
            }).format(num);
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            if (isNaN(date.getTime())) return 'Non spécifiée';
            
            return date.toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        }

        // Functions for existing BC operations
        window.printExistingBC = function(bcId) {
            window.open(`bondecommande.php?bc_id=${bcId}`, '_blank');
        };

        window.viewBC = function(bcId) {
            window.location.href = `bondecommande.php?bc_id=${bcId}`;
        };

        window.showDeleteModal = function(bcId, bcReference) {
            document.getElementById('deleteBCId').value = bcId;
            document.getElementById('deleteMessage').textContent = 
                `Êtes-vous sûr de vouloir supprimer le bon de commande ${bcReference} ? Cette action est irréversible.`;
            document.getElementById('deleteModal').classList.add('active');
        };

        window.showStatusModal = function(bcId) {
            document.getElementById('statusBCId').value = bcId;
            document.getElementById('statusModal').classList.add('active');
        };

        // Search functionality
        function setupSearch() {
            const searchInput = document.getElementById('globalSearch');
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                const rows = document.querySelectorAll('.bc-row');
                
                if (searchTerm === '') {
                    rows.forEach(row => {
                        row.style.display = '';
                        removeHighlights(row);
                    });
                    return;
                }
                
                rows.forEach(row => {
                    removeHighlights(row);
                    
                    const clientName = row.querySelector('.client-name').textContent.toLowerCase();
                    const reference = row.cells[0].textContent.toLowerCase();
                    
                    const matches = clientName.includes(searchTerm) || 
                                  reference.includes(searchTerm);
                    
                    if (matches) {
                        row.style.display = '';
                        highlightText(row, searchTerm);
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }

        function highlightText(element, searchTerm) {
            const walker = document.createTreeWalker(
                element,
                NodeFilter.SHOW_TEXT,
                null,
                false
            );
            
            let node;
            while (node = walker.nextNode()) {
                const parent = node.parentNode;
                if (parent.nodeName === 'SPAN' && parent.classList.contains('highlight')) {
                    continue;
                }
                
                const text = node.nodeValue;
                const regex = new RegExp(`(${searchTerm})`, 'gi');
                const newText = text.replace(regex, '<span class="highlight">$1</span>');
                
                if (newText !== text) {
                    const newSpan = document.createElement('span');
                    newSpan.innerHTML = newText;
                    parent.replaceChild(newSpan, node);
                }
            }
        }

        function removeHighlights(element) {
            const highlights = element.querySelectorAll('.highlight');
            highlights.forEach(highlight => {
                const parent = highlight.parentNode;
                parent.replaceChild(document.createTextNode(highlight.textContent), highlight);
                parent.normalize();
            });
        }

        // Filter functionality
        function setupFilters() {
            document.getElementById('filterStatusBC').addEventListener('change', filterBC);
            document.getElementById('filterDate').addEventListener('change', filterBC);
        }

        function filterBC() {
            const statusFilter = document.getElementById('filterStatusBC').value;
            const dateFilter = document.getElementById('filterDate').value;
            const rows = document.querySelectorAll('.bc-row');
            const today = new Date();
            
            rows.forEach(row => {
                const status = row.getAttribute('data-status');
                const dateStr = row.getAttribute('data-date');
                const rowDate = new Date(dateStr);
                
                let showRow = true;
                
                if (statusFilter !== 'all' && status !== statusFilter) {
                    showRow = false;
                }
                
                if (dateFilter !== 'all' && showRow) {
                    if (dateFilter === 'today') {
                        const isToday = rowDate.toDateString() === today.toDateString();
                        if (!isToday) showRow = false;
                    } else if (dateFilter === 'week') {
                        const startOfWeek = new Date(today);
                        startOfWeek.setDate(today.getDate() - today.getDay());
                        startOfWeek.setHours(0, 0, 0, 0);
                        
                        const endOfWeek = new Date(startOfWeek);
                        endOfWeek.setDate(startOfWeek.getDate() + 6);
                        endOfWeek.setHours(23, 59, 59, 999);
                        
                        if (rowDate < startOfWeek || rowDate > endOfWeek) {
                            showRow = false;
                        }
                    } else if (dateFilter === 'month') {
                        const sameMonth = rowDate.getMonth() === today.getMonth() && 
                                        rowDate.getFullYear() === today.getFullYear();
                        if (!sameMonth) showRow = false;
                    }
                }
                
                row.style.display = showRow ? '' : 'none';
            });
        }
    </script>
</body>
</html>