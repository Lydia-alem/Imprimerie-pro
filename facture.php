<?php
$host = '127.0.0.1:3306';
$dbname = 'imprimerie';
$username = 'root';
$password = 'admine';

// Create connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Fetch data from database
try {
    // Fetch clients
    $clientsStmt = $pdo->query("SELECT * FROM clients ORDER BY name");
    $clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch products
    $productsStmt = $pdo->query("SELECT * FROM products ORDER BY name");
    $products = $productsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch existing invoices
    $invoicesStmt = $pdo->query("
        SELECT i.*, c.name as client_name, c.email, c.phone, c.address
        FROM invoices i 
        LEFT JOIN clients c ON i.client_id = c.id 
        ORDER BY i.created_at DESC 
        LIMIT 50
    ");
    $invoices = $invoicesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch existing orders (BL)
    $ordersStmt = $pdo->query("
        SELECT o.*, c.name as client_name 
        FROM orders o 
        LEFT JOIN clients c ON o.client_id = c.id 
        ORDER BY o.created_at DESC 
        LIMIT 50
    ");
    $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate next invoice number
    $lastInvoiceStmt = $pdo->query("SELECT id FROM invoices ORDER BY id DESC LIMIT 1");
    $lastInvoice = $lastInvoiceStmt->fetch(PDO::FETCH_ASSOC);
    $nextInvoiceNumber = "FAC-" . date('Y') . "-" . str_pad(($lastInvoice ? $lastInvoice['id'] + 1 : 1), 4, '0', STR_PAD_LEFT);
    
    // Generate next BL number
    $lastOrderStmt = $pdo->query("SELECT id FROM orders ORDER BY id DESC LIMIT 1");
    $lastOrder = $lastOrderStmt->fetch(PDO::FETCH_ASSOC);
    $nextBLNumber = "BL-" . date('Y') . "-" . str_pad(($lastOrder ? $lastOrder['id'] + 1 : 1), 4, '0', STR_PAD_LEFT);
    
} catch(PDOException $e) {
    die("Erreur lors du chargement des données: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['save_invoice'])) {
        try {
            $pdo->beginTransaction();
            
            // Save invoice
            $client_id = $_POST['client_id'];
            $invoice_number = $_POST['invoice_number'];
            $invoice_date = $_POST['invoice_date'];
            $due_date = $_POST['due_date'];
            $payment_terms = $_POST['payment_terms'];
            $delivery_terms = $_POST['delivery_terms'];
            $subtotal = $_POST['subtotal'];
            $tva_rate = $_POST['tva_rate'];
            $tva_amount = $_POST['tva_amount'];
            $total = $_POST['total'];
            $notes = $_POST['notes'];
            $status = 'unpaid';
            
            $invoiceStmt = $pdo->prepare("
                INSERT INTO invoices (client_id, status, total, vat, created_at) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $invoiceStmt->execute([
                $client_id, 
                $status, 
                $total, 
                $tva_amount,
                date('Y-m-d H:i:s')
            ]);
            $invoice_id = $pdo->lastInsertId();
            
            // Save invoice items
            $descriptions = $_POST['item_description'] ?? [];
            $quantities = $_POST['item_quantity'] ?? [];
            $prices = $_POST['item_price'] ?? [];
            $units = $_POST['item_unit'] ?? [];
            
            for ($i = 0; $i < count($descriptions); $i++) {
                if (!empty($descriptions[$i])) {
                    $itemStmt = $pdo->prepare("
                        INSERT INTO invoice_items (invoice_id, description, quantity, price, subtotal) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $itemStmt->execute([
                        $invoice_id,
                        $descriptions[$i],
                        $quantities[$i],
                        $prices[$i],
                        $quantities[$i] * $prices[$i]
                    ]);
                }
            }
            
            $pdo->commit();
            
            // Refresh page to show new invoice
            header("Location: facturation.php?success=1&invoice_id=" . $invoice_id);
            exit();
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $error_message = "Erreur lors de l'enregistrement: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['save_bl'])) {
        try {
            $pdo->beginTransaction();
            
            // Save order (BL)
            $client_id = $_POST['bl_client_id'];
            $bl_number = $_POST['bl_number'];
            $bl_date = $_POST['bl_date'];
            $delivery_person = $_POST['delivery_person'];
            $vehicle = $_POST['vehicle'];
            $reference = $_POST['reference'];
            $conditions = $_POST['conditions'];
            $notes = $_POST['bl_notes'];
            $status = 'pending';
            
            $orderStmt = $pdo->prepare("
                INSERT INTO orders (client_id, status, deadline, total, created_at) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $orderStmt->execute([
                $client_id, 
                $status, 
                $bl_date, 
                0,
                date('Y-m-d H:i:s')
            ]);
            $order_id = $pdo->lastInsertId();
            
            // Save order items
            $descriptions = $_POST['bl_item_description'] ?? [];
            $quantities = $_POST['bl_item_quantity'] ?? [];
            $units = $_POST['bl_item_unit'] ?? [];
            
            for ($i = 0; $i < count($descriptions); $i++) {
                if (!empty($descriptions[$i])) {
                    $itemStmt = $pdo->prepare("
                        INSERT INTO order_items (order_id, description, quantity, price, subtotal) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $itemStmt->execute([
                        $order_id,
                        $descriptions[$i] . " (" . ($units[$i] ?? 'unité') . ")",
                        $quantities[$i],
                        0,
                        0
                    ]);
                }
            }
            
            $pdo->commit();
            
            // Refresh page to show new BL
            header("Location: facturation.php?success=2&bl_id=" . $order_id);
            exit();
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $error_message = "Erreur lors de l'enregistrement: " . $e->getMessage();
        }
    }
}

// Get invoice data for preview if invoice_id is set
$preview_invoice = null;
$preview_invoice_items = null;
if (isset($_GET['invoice_id'])) {
    $invoice_id = $_GET['invoice_id'];
    $stmt = $pdo->prepare("
        SELECT i.*, c.name as client_name, c.email, c.phone, c.address 
        FROM invoices i 
        LEFT JOIN clients c ON i.client_id = c.id 
        WHERE i.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $preview_invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($preview_invoice) {
        $itemsStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
        $itemsStmt->execute([$invoice_id]);
        $preview_invoice_items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get BL data for preview if bl_id is set
$preview_bl = null;
$preview_bl_items = null;
if (isset($_GET['bl_id'])) {
    $bl_id = $_GET['bl_id'];
    $stmt = $pdo->prepare("
        SELECT o.*, c.name as client_name, c.email, c.phone, c.address 
        FROM orders o 
        LEFT JOIN clients c ON o.client_id = c.id 
        WHERE o.id = ?
    ");
    $stmt->execute([$bl_id]);
    $preview_bl = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($preview_bl) {
        $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
        $itemsStmt->execute([$bl_id]);
        $preview_bl_items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimerie Admin - Facturation & BL</title>
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
            --bl-color: #2ecc71;
            --invoice-color: #3498db;
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
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background: var(--primary);
            color: white;
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
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .sidebar-menu {
            padding: 15px 0;
        }

        .sidebar-menu ul {
            list-style: none;
        }

        .sidebar-menu li {
            padding: 12px 20px;
            transition: all 0.3s;
        }

        .sidebar-menu li:hover {
            background: rgba(255, 255, 255, 0.1);
            cursor: pointer;
        }

        .sidebar-menu li.active {
            background: var(--secondary);
            border-left: 4px solid var(--accent);
        }

        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
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
            border-bottom: 3px solid var(--secondary);
            font-weight: 600;
            color: var(--secondary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Facturation Container */
        .facturation-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        @media (max-width: 992px) {
            .facturation-container {
                grid-template-columns: 1fr;
            }
        }

        /* Formulaire de Facture/BL */
        .invoice-form, .bl-form {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
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
            border-color: var(--secondary);
            outline: none;
        }

        /* Table des Articles */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 0.9rem;
        }

        .items-table th, .items-table td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .items-table th {
            background-color: #f8f9fa;
            color: var(--primary);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
        }

        .items-table input, .items-table select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.9rem;
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
        .invoice-preview, .bl-preview {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
        }

        .invoice-header-preview, .bl-header-preview {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--light);
        }

        .invoice-header-preview h3, .bl-header-preview h3 {
            color: var(--primary);
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .invoice-info-grid, .bl-info-grid {
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

        .invoice-meta, .bl-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
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
            font-size: 0.85rem;
        }

        .items-table-preview th {
            background: #2c3e50;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: 500;
        }

        .items-table-preview td {
            padding: 10px;
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

        .invoice-terms, .bl-terms {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            font-size: 0.85rem;
            color: #555;
        }

        .invoice-terms h4, .bl-terms h4 {
            color: var(--primary);
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .invoice-actions, .bl-actions {
            margin-top: auto;
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

        .btn-info {
            background: var(--info);
            color: white;
        }

        .btn-info:hover {
            background: #138496;
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
            width: 80%;
            max-width: 800px;
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

        .print-invoice-info, .print-bl-info {
            text-align: center;
            margin-bottom: 25px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .print-invoice-info h3, .print-bl-info h3 {
            color: var(--primary);
            font-size: 1.4rem;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .print-invoice-meta, .print-bl-meta {
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
            font-size: 0.85rem;
        }

        .print-items-table th {
            background: #2c3e50;
            color: white;
            padding: 12px 10px;
            text-align: left;
            font-weight: 500;
            border: 1px solid #ddd;
        }

        .print-items-table td {
            padding: 10px;
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

        .status-paid {
            background: #d4edda;
            color: #155724;
        }

        .status-unpaid {
            background: #fff3cd;
            color: #856404;
        }

        .status-pending {
            background: #cce5ff;
            color: #004085;
        }

        .status-delivered {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-in_production {
            background: #f8d7da;
            color: #721c24;
        }

        .status-ready {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-cancelled {
            background: #f5c6cb;
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

        .card-1 .stat-icon { background: #3498db; color: white; }
        .card-2 .stat-icon { background: #2ecc71; color: white; }
        .card-3 .stat-icon { background: #9b59b6; color: white; }
        .card-4 .stat-icon { background: #f39c12; color: white; }

        .stat-info h3 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        /* Logout button */
        .btn-logout {
            background: var(--danger);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
            font-size: 0.9rem;
        }

        .btn-logout:hover {
            background: #c0392b;
        }

        /* Unit column in items table */
        .unit-column {
            width: 80px;
        }

        /* Small buttons */
        .btn-sm {
            padding: 8px 15px;
            font-size: 0.85rem;
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
            
            .invoice-info-grid {
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
            
            .invoice-meta {
                grid-template-columns: 1fr;
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
            }
            .close-modal, .no-print {
                display: none;
            }
            .print-header .logo {
                width: 100px;
                height: 100px;
            }
        }

        /* BL specific styles */
        .bl-section {
            background: linear-gradient(to right, rgba(46, 204, 113, 0.1), rgba(46, 204, 113, 0.05));
            border-left: 4px solid var(--bl-color);
        }

        .bl-preview {
            border-top: 5px solid var(--bl-color);
        }

        .bl-header-preview h3 {
            color: var(--bl-color);
        }

        .bl-form {
            border-top: 5px solid var(--bl-color);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <!-- Company Logo -->
            <img src="logo.png" alt="Logo Imprimerie" onerror="this.src='https://via.placeholder.com/40/3498db/ffffff?text=IP'">
            <h2>Imprimerie Pro</h2>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li><i class="fas fa-home"></i> <span>Tableau de Bord</span></li>
                <li><i class="fas fa-shopping-cart"></i> <span>Commandes</span></li>
                <li class="active"><i class="fas fa-file-invoice-dollar"></i> <span>Facturation</span></li>
                <li><i class="fas fa-truck"></i> <span>Livraisons</span></li>
                <li><i class="fas fa-box"></i> <span>Produits</span></li>
                <li><i class="fas fa-users"></i> <span>Clients</span></li>
                <li><i class="fas fa-chart-bar"></i> <span>Rapports</span></li>
                <li><i class="fas fa-cog"></i> <span>Paramètres</span></li>
                <li>
                    <form method="POST" action="logout.php" style="display: inline;">
                        <button type="submit" class="btn-logout" style="width: 100%; text-align: left; background: none; color: white;">
                            <i class="fas fa-sign-out-alt"></i> <span>Déconnexion</span>
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <h1>Gestion des Factures & BL</h1>
                <p style="color: #7f8c8d; font-size: 0.9rem;">Bienvenue, Admin</p>
            </div>
            <div class="header-right">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Rechercher...">
                </div>
                <div class="user-profile">
                    <img src="https://i.pravatar.cc/150?img=12" alt="Admin">
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
                        echo "Facture enregistrée avec succès! Numéro: " . ($_GET['invoice_id'] ?? '');
                    } elseif ($_GET['success'] == 2) {
                        echo "Bon de livraison enregistré avec succès! Numéro: " . ($_GET['bl_id'] ?? '');
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="tabs">
                <div class="tab active" data-tab="invoice">Créer une Facture</div>
                <div class="tab" data-tab="bl">Créer un BL</div>
                <div class="tab" data-tab="list">Liste des Documents</div>
            </div>

            <!-- Onglet Facture -->
            <div class="tab-content active" id="invoice-tab">
                <div class="page-header">
                    <h2>Créer une nouvelle facture</h2>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="save_invoice" value="1">
                    <input type="hidden" id="subtotal_input" name="subtotal" value="0">
                    <input type="hidden" id="tva_amount_input" name="tva_amount" value="0">
                    <input type="hidden" id="total_input" name="total" value="0">
                    
                    <div class="facturation-container">
                        <!-- Formulaire de facture -->
                        <div class="invoice-form">
                            <div class="form-section">
                                <h3>Informations du client</h3>
                                <div class="form-group">
                                    <label for="client_id">Sélectionner un client *</label>
                                    <select id="client_id" name="client_id" class="form-control" required>
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
                                        <label for="client_address">Adresse</label>
                                        <textarea id="client_address" name="client_address" class="form-control" rows="2" readonly></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="client_contact">Contact</label>
                                        <input type="text" id="client_contact" class="form-control" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3>Détails de la facture</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="invoice_number">Numéro de facture</label>
                                        <input type="text" id="invoice_number" name="invoice_number" 
                                               class="form-control" value="<?php echo $nextInvoiceNumber; ?>" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label for="invoice_date">Date de facturation</label>
                                        <input type="date" id="invoice_date" name="invoice_date" 
                                               class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="due_date">Date d'échéance</label>
                                        <input type="date" id="due_date" name="due_date" 
                                               class="form-control" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="payment_terms">Conditions de paiement</label>
                                        <select id="payment_terms" name="payment_terms" class="form-control">
                                            <option value="30 jours">30 jours</option>
                                            <option value="45 jours">45 jours</option>
                                            <option value="60 jours">60 jours</option>
                                            <option value="Comptant">Comptant</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="delivery_terms">Conditions de livraison</label>
                                        <select id="delivery_terms" name="delivery_terms" class="form-control">
                                            <option value="FOB">FOB</option>
                                            <option value="CIF">CIF</option>
                                            <option value="EXW">EXW</option>
                                            <option value="DDP">DDP</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="tva_rate">Taux de TVA (%)</label>
                                        <input type="number" id="tva_rate" name="tva_rate" class="form-control" 
                                               value="19" min="0" max="100" step="0.1">
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3>Articles de la facture</h3>
                                <table class="items-table">
                                    <thead>
                                        <tr>
                                            <th width="40">N°</th>
                                            <th>Description</th>
                                            <th width="80">Unité</th>
                                            <th width="90">Quantité</th>
                                            <th width="100">Prix unitaire (DA)</th>
                                            <th width="100">Total (DA)</th>
                                            <th width="50">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="invoiceItems">
                                        <!-- Les articles seront ajoutés ici dynamiquement -->
                                    </tbody>
                                </table>
                                <button type="button" class="btn-add-item" id="addInvoiceItem">
                                    <i class="fas fa-plus"></i> Ajouter un article
                                </button>
                            </div>

                            <div class="form-section">
                                <h3>Totaux</h3>
                                <div class="total-row">
                                    <span class="total-label">Sous-total:</span>
                                    <span class="total-value" id="subtotal">0.00 DA</span>
                                </div>
                                <div class="total-row">
                                    <span class="total-label">TVA (<span id="tvaPercent">19</span>%):</span>
                                    <span class="total-value" id="tvaAmount">0.00 DA</span>
                                </div>
                                <div class="total-row grand-total">
                                    <span class="total-label">Total général:</span>
                                    <span class="total-value" id="totalAmount">0.00 DA</span>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3>Notes et conditions</h3>
                                <div class="form-group">
                                    <textarea id="notes" name="notes" class="form-control" rows="3" 
                                              placeholder="Notes supplémentaires, conditions spéciales..."></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Aperçu de la facture -->
                        <div class="invoice-preview">
                            <div class="invoice-header-preview">
                                <h3>IMPRIMERIE PRO</h3>
                                <p>123 Rue d'Impression, Alger Centre</p>
                                <p>Tél: 021 23 45 67 | Email: contact@imprimerie-pro.dz</p>
                            </div>

                            <div class="invoice-info-grid">
                                <div class="company-info">
                                    <h4>Émise par:</h4>
                                    <p><strong>IMPRIMERIE PRO</strong></p>
                                    <p>123 Rue d'Impression</p>
                                    <p>Alger Centre, Algérie</p>
                                    <p>Tél: 021 23 45 67</p>
                                    <p>NIF: 123456789012345</p>
                                </div>
                                <div class="client-info">
                                    <h4>Facturé à:</h4>
                                    <p id="previewClientName">-</p>
                                    <p id="previewClientAddress">-</p>
                                    <p id="previewClientContact">-</p>
                                </div>
                            </div>

                            <div class="invoice-meta">
                                <div class="meta-item">
                                    <span class="meta-label">N° Facture:</span>
                                    <span class="meta-value" id="previewInvoiceNumber"><?php echo $nextInvoiceNumber; ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Date:</span>
                                    <span class="meta-value" id="previewInvoiceDate"><?php echo date('d/m/Y'); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Échéance:</span>
                                    <span class="meta-value" id="previewDueDate"><?php echo date('d/m/Y', strtotime('+30 days')); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Conditions:</span>
                                    <span class="meta-value" id="previewPaymentTerms">30 jours</span>
                                </div>
                            </div>

                            <table class="items-table-preview">
                                <thead>
                                    <tr>
                                        <th width="40">N°</th>
                                        <th>Description</th>
                                        <th width="80">Unité</th>
                                        <th width="90">Qté</th>
                                        <th width="100">Prix U.</th>
                                        <th width="100">Total</th>
                                    </tr>
                                </thead>
                                <tbody id="previewInvoiceItems">
                                    <!-- Les articles seront ajoutés ici dynamiquement -->
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 20px; color: #999;">
                                            Aucun article ajouté
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4" style="text-align: right; font-weight: 600;">Sous-total:</td>
                                        <td colspan="2" id="previewSubtotal">0.00 DA</td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" style="text-align: right; font-weight: 600;">TVA (19%):</td>
                                        <td colspan="2" id="previewTvaAmount">0.00 DA</td>
                                    </tr>
                                    <tr>
                                        <td colspan="4" style="text-align: right; font-weight: 700;">Total général:</td>
                                        <td colspan="2" id="previewTotalAmount">0.00 DA</td>
                                    </tr>
                                </tfoot>
                            </table>

                            <div class="invoice-terms">
                                <h4>Conditions et notes</h4>
                                <p id="previewNotes">-</p>
                                <p style="margin-top: 10px; font-style: italic;">
                                    Paiement par virement bancaire à l'IBAN: DZ 1234 5678 9012 3456 7890 1234
                                </p>
                            </div>

                            <div class="invoice-actions">
                                <button type="button" class="btn btn-success" id="calculateInvoice">
                                    <i class="fas fa-calculator"></i> Calculer
                                </button>
                                <button type="button" class="btn btn-primary" id="previewInvoice">
                                    <i class="fas fa-eye"></i> Prévisualiser
                                </button>
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-save"></i> Enregistrer
                                </button>
                                <button type="button" class="btn btn-info" id="printInvoice">
                                    <i class="fas fa-print"></i> Imprimer
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Onglet BL -->
            <div class="tab-content" id="bl-tab">
                <div class="page-header">
                    <h2>Créer un nouveau Bon de Livraison (BL)</h2>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="save_bl" value="1">
                    
                    <div class="facturation-container">
                        <!-- Formulaire de BL -->
                        <div class="bl-form">
                            <div class="form-section">
                                <h3>Informations du client</h3>
                                <div class="form-group">
                                    <label for="bl_client_id">Sélectionner un client *</label>
                                    <select id="bl_client_id" name="bl_client_id" class="form-control" required>
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
                                        <label for="bl_client_address">Adresse de livraison</label>
                                        <textarea id="bl_client_address" name="bl_client_address" class="form-control" rows="2" readonly></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="bl_client_contact">Contact</label>
                                        <input type="text" id="bl_client_contact" class="form-control" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3>Détails du BL</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="bl_number">Numéro du BL</label>
                                        <input type="text" id="bl_number" name="bl_number" 
                                               class="form-control" value="<?php echo $nextBLNumber; ?>" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label for="bl_date">Date de livraison</label>
                                        <input type="date" id="bl_date" name="bl_date" 
                                               class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="delivery_person">Livreur</label>
                                        <input type="text" id="delivery_person" name="delivery_person" 
                                               class="form-control" placeholder="Nom du livreur">
                                    </div>
                                    <div class="form-group">
                                        <label for="vehicle">Véhicule</label>
                                        <input type="text" id="vehicle" name="vehicle" 
                                               class="form-control" placeholder="Immatriculation">
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="reference">Référence commande/facture</label>
                                        <input type="text" id="reference" name="reference" 
                                               class="form-control" placeholder="Référence">
                                    </div>
                                    <div class="form-group">
                                        <label for="conditions">Conditions de remise</label>
                                        <select id="conditions" name="conditions" class="form-control">
                                            <option value="Sur place">Sur place</option>
                                            <option value="Transport client">Transport client</option>
                                            <option value="Transport fournisseur">Transport fournisseur</option>
                                            <option value="Express">Express</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3>Articles du BL</h3>
                                <table class="items-table">
                                    <thead>
                                        <tr>
                                            <th width="40">N°</th>
                                            <th>Description</th>
                                            <th width="80">Unité</th>
                                            <th width="90">Quantité</th>
                                            <th width="50">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="blItems">
                                        <!-- Les articles seront ajoutés ici dynamiquement -->
                                    </tbody>
                                </table>
                                <button type="button" class="btn-add-item" id="addBLItem">
                                    <i class="fas fa-plus"></i> Ajouter un article
                                </button>
                            </div>

                            <div class="form-section">
                                <h3>Notes et observations</h3>
                                <div class="form-group">
                                    <textarea id="bl_notes" name="bl_notes" class="form-control" rows="3" 
                                              placeholder="Observations sur l'état des marchandises, conditions particulières..."></textarea>
                                </div>
                            </div>

                            <div class="form-section bl-section">
                                <h3>Signature et validation</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="driver_signature">Signature chauffeur</label>
                                        <input type="text" id="driver_signature" class="form-control" placeholder="À remplir à la livraison" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label for="client_signature">Signature client</label>
                                        <input type="text" id="client_signature" class="form-control" placeholder="À remplir à la livraison" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Aperçu du BL -->
                        <div class="bl-preview">
                            <div class="bl-header-preview">
                                <h3>BON DE LIVRAISON</h3>
                                <p>123 Rue d'Impression, Alger Centre</p>
                                <p>Tél: 021 23 45 67 | Email: contact@imprimerie-pro.dz</p>
                            </div>

                            <div class="bl-info-grid">
                                <div class="company-info">
                                    <h4>Expéditeur:</h4>
                                    <p><strong>IMPRIMERIE PRO</strong></p>
                                    <p>123 Rue d'Impression</p>
                                    <p>Alger Centre, Algérie</p>
                                    <p>Tél: 021 23 45 67</p>
                                </div>
                                <div class="client-info">
                                    <h4>Destinataire:</h4>
                                    <p id="previewBLClientName">-</p>
                                    <p id="previewBLClientAddress">-</p>
                                    <p id="previewBLClientContact">-</p>
                                </div>
                            </div>

                            <div class="bl-meta">
                                <div class="meta-item">
                                    <span class="meta-label">N° BL:</span>
                                    <span class="meta-value" id="previewBLNumber"><?php echo $nextBLNumber; ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Date:</span>
                                    <span class="meta-value" id="previewBLDate"><?php echo date('d/m/Y'); ?></span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Livreur:</span>
                                    <span class="meta-value" id="previewDeliveryPerson">-</span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Véhicule:</span>
                                    <span class="meta-value" id="previewVehicle">-</span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Référence:</span>
                                    <span class="meta-value" id="previewReference">-</span>
                                </div>
                                <div class="meta-item">
                                    <span class="meta-label">Conditions:</span>
                                    <span class="meta-value" id="previewConditions">Sur place</span>
                                </div>
                            </div>

                            <table class="items-table-preview">
                                <thead>
                                    <tr>
                                        <th width="40">N°</th>
                                        <th>Description</th>
                                        <th width="80">Unité</th>
                                        <th width="90">Qté livrée</th>
                                        <th width="100">Observations</th>
                                    </tr>
                                </thead>
                                <tbody id="previewBLItems">
                                    <!-- Les articles seront ajoutés ici dynamiquement -->
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 20px; color: #999;">
                                            Aucun article ajouté
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="bl-terms">
                                <h4>Observations et signatures</h4>
                                <p id="previewBLNotes">-</p>
                                
                                <div style="margin-top: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                                    <div>
                                        <h5 style="margin-bottom: 10px; color: var(--primary);">Le Livreur</h5>
                                        <div style="border-top: 1px solid #333; padding-top: 10px; margin-top: 40px;">
                                            <p>Signature:</p>
                                            <p>Nom: <span id="previewDriverSignature">_________________</span></p>
                                        </div>
                                    </div>
                                    <div>
                                        <h5 style="margin-bottom: 10px; color: var(--primary);">Le Client</h5>
                                        <div style="border-top: 1px solid #333; padding-top: 10px; margin-top: 40px;">
                                            <p>Signature:</p>
                                            <p>Nom: <span id="previewClientSignature">_________________</span></p>
                                            <p>Cachet:</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bl-actions">
                                <button type="button" class="btn btn-success" id="previewBL">
                                    <i class="fas fa-eye"></i> Prévisualiser
                                </button>
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-save"></i> Enregistrer BL
                                </button>
                                <button type="button" class="btn btn-info" id="printBL">
                                    <i class="fas fa-print"></i> Imprimer BL
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Onglet Liste des documents -->
            <div class="tab-content" id="list-tab">
                <div class="page-header">
                    <h2>Liste des documents</h2>
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
                                <label for="filterType">Type de document</label>
                                <select id="filterType" class="form-control">
                                    <option value="all">Tous</option>
                                    <option value="invoice">Factures</option>
                                    <option value="bl">Bons de livraison</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="filterStatus">Statut</label>
                                <select id="filterStatus" class="form-control">
                                    <option value="all">Tous les statuts</option>
                                    <option value="paid">Payée</option>
                                    <option value="unpaid">Non payée</option>
                                    <option value="pending">En attente</option>
                                    <option value="delivered">Livré</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="documents-table-container">
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th width="80">Type</th>
                                    <th width="120">Numéro</th>
                                    <th>Client</th>
                                    <th width="100">Date</th>
                                    <th width="120">Montant (DA)</th>
                                    <th width="100">Statut</th>
                                    <th width="150">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="documentsList">
                                <!-- Factures -->
                                <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-file-invoice" style="color: #3498db;"></i> Facture
                                    </td>
                                    <td>FAC-<?php echo str_pad($invoice['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['client_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($invoice['created_at'])); ?></td>
                                    <td><?php echo number_format($invoice['total'], 2, ',', ' '); ?> DA</td>
                                    <td>
                                        <span class="status status-<?php echo $invoice['status']; ?>">
                                            <?php 
                                            switch($invoice['status']) {
                                                case 'paid': echo 'Payée'; break;
                                                case 'unpaid': echo 'Non payée'; break;
                                                case 'partial': echo 'Partiel'; break;
                                                default: echo $invoice['status'];
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="printExistingInvoice(<?php echo $invoice['id']; ?>)">
                                            <i class="fas fa-print"></i> Imprimer
                                        </button>
                                        <button class="btn btn-info btn-sm" onclick="viewInvoice(<?php echo $invoice['id']; ?>)">
                                            <i class="fas fa-eye"></i> Voir
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <!-- Bons de Livraison -->
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-truck" style="color: #2ecc71;"></i> BL
                                    </td>
                                    <td>BL-<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($order['client_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                                    <td>-</td>
                                    <td>
                                        <span class="status status-<?php echo $order['status']; ?>">
                                            <?php 
                                            switch($order['status']) {
                                                case 'pending': echo 'En attente'; break;
                                                case 'in_production': echo 'En production'; break;
                                                case 'ready': echo 'Prêt'; break;
                                                case 'delivered': echo 'Livré'; break;
                                                case 'cancelled': echo 'Annulé'; break;
                                                default: echo $order['status'];
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-primary btn-sm" onclick="printExistingBL(<?php echo $order['id']; ?>)">
                                            <i class="fas fa-print"></i> Imprimer
                                        </button>
                                        <button class="btn btn-info btn-sm" onclick="viewBL(<?php echo $order['id']; ?>)">
                                            <i class="fas fa-eye"></i> Voir
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($invoices) && empty($orders)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 30px;">
                                        <p>Aucun document trouvé</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="document-stats">
                        <div class="stats-cards">
                            <div class="stat-card card-1">
                                <div class="stat-icon">
                                    <i class="fas fa-file-invoice"></i>
                                </div>
                                <div class="stat-info">
                                    <?php 
                                    $totalInvoices = count($invoices);
                                    $totalRevenue = array_sum(array_column($invoices, 'total'));
                                    ?>
                                    <h3><?php echo $totalInvoices; ?></h3>
                                    <p>Factures</p>
                                </div>
                            </div>
                            <div class="stat-card card-2">
                                <div class="stat-icon">
                                    <i class="fas fa-truck"></i>
                                </div>
                                <div class="stat-info">
                                    <h3><?php echo count($orders); ?></h3>
                                    <p>BL</p>
                                </div>
                            </div>
                            <div class="stat-card card-3">
                                <div class="stat-icon">
                                    <i class="fas fa-euro-sign"></i>
                                </div>
                                <div class="stat-info">
                                    <h3><?php echo number_format($totalRevenue, 2, ',', ' '); ?> DA</h3>
                                    <p>Chiffre d'affaires</p>
                                </div>
                            </div>
                            <div class="stat-card card-4">
                                <div class="stat-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="stat-info">
                                    <?php 
                                    $pendingInvoices = count(array_filter($invoices, function($inv) {
                                        return $inv['status'] == 'unpaid';
                                    }));
                                    ?>
                                    <h3><?php echo $pendingInvoices; ?></h3>
                                    <p>Factures en attente</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal d'impression Facture -->
    <div class="print-modal" id="printInvoiceModal">
        <div class="print-content">
            <button class="close-modal" id="closeInvoiceModal">&times;</button>
            
            <div id="printInvoiceContent">
                <div class="print-header">
                    <div class="logo">
                        <img src="logo.png" alt="Logo Imprimerie" 
                             onerror="this.src='REM.jpg'">
                    </div>
                    
                    <p>123 Rue d'Impression, Alger Centre, Algérie</p>
                    <p>Tél: 021 23 45 67 | Email: contact@imprimerie-pro.dz</p>
                    <p>NIF: 123456789012345</p>
                </div>

                <div class="print-invoice-info">
                    <h3>FACTURE</h3>
                    <div class="print-invoice-meta">
                        <div><strong>N°:</strong> <span id="printInvoiceNumber"><?php echo $nextInvoiceNumber; ?></span></div>
                        <div><strong>Date:</strong> <span id="printInvoiceDate"><?php echo date('d/m/Y'); ?></span></div>
                        <div><strong>Échéance:</strong> <span id="printDueDate"><?php echo date('d/m/Y', strtotime('+30 days')); ?></span></div>
                    </div>
                </div>

                <div class="print-details-grid">
                    <div class="print-company">
                        <h4>Émise par:</h4>
                        <p><strong>IMPRIMERIE PRO</strong></p>
                        <p>123 Rue d'Impression</p>
                        <p>Alger Centre, Algérie</p>
                        <p>Tél: 021 23 45 67</p>
                        <p>Email: contact@imprimerie-pro.dz</p>
                    </div>
                    <div class="print-client">
                        <h4>Facturé à:</h4>
                        <p id="printClientName">-</p>
                        <p id="printClientAddress">-</p>
                        <p id="printClientContact">-</p>
                    </div>
                </div>

                <table class="print-items-table">
                    <thead>
                        <tr>
                            <th width="40">N°</th>
                            <th>Description</th>
                            <th width="80">Unité</th>
                            <th width="90">Qté</th>
                            <th width="100">Prix U. (DA)</th>
                            <th width="100">Total (DA)</th>
                        </tr>
                    </thead>
                    <tbody id="printInvoiceItems">
                        <!-- Les articles seront insérés ici -->
                    </tbody>
                </table>

                <div class="print-totals">
                    <div class="print-total-row">
                        <span>Sous-total:</span>
                        <span id="printSubtotal">0.00 DA</span>
                    </div>
                    <div class="print-total-row">
                        <span>TVA (<span id="printTvaPercent">19</span>%):</span>
                        <span id="printTvaAmount">0.00 DA</span>
                    </div>
                    <div class="print-total-row print-grand-total">
                        <span>Total à payer:</span>
                        <span id="printTotalAmount">0.00 DA</span>
                    </div>
                </div>

                <div class="print-terms">
                    <h4>Conditions et notes</h4>
                    <p id="printNotes">-</p>
                    <p style="margin-top: 10px;">
                        <strong>Conditions de paiement:</strong> <span id="printPaymentTerms">30 jours</span><br>
                        <strong>Conditions de livraison:</strong> <span id="printDeliveryTerms">FOB</span>
                    </p>
                    <p style="margin-top: 10px; font-style: italic;">
                        Paiement par virement bancaire à l'IBAN: DZ 1234 5678 9012 3456 7890 1234
                    </p>
                </div>

                <div class="print-footer">
                    <p>Merci pour votre confiance et à bientôt!</p>
                </div>

                <div class="print-signatures">
                    <div class="signature-box">
                        <p>Le Responsable</p>
                        <div class="signature-line"></div>
                        <p>Signature & cachet</p>
                    </div>
                    <div class="signature-box">
                        <p>Le Client</p>
                        <div class="signature-line"></div>
                        <p>Signature & cachet</p>
                    </div>
                </div>
                
                <div class="no-print" style="margin-top: 30px; text-align: center;">
                    <button class="btn btn-primary" id="doPrintInvoice">
                        <i class="fas fa-print"></i> Imprimer maintenant
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal d'impression BL -->
    <div class="print-modal" id="printBLModal">
        <div class="print-content">
            <button class="close-modal" id="closeBLModal">&times;</button>
            
            <div id="printBLContent">
                <div class="print-header">
                    <div class="logo">
                        <img src="logo.png" alt="Logo Imprimerie" 
                             onerror="this.src='REM.jpg'">
                    </div>
                    
                    <p>123 Rue d'Impression, Alger Centre, Algérie</p>
                    <p>Tél: 021 23 45 67 | Email: contact@imprimerie-pro.dz</p>
                </div>

                <div class="print-bl-info">
                    <h3>BON DE LIVRAISON</h3>
                    <div class="print-bl-meta">
                        <div><strong>N° BL:</strong> <span id="printBLNumber"><?php echo $nextBLNumber; ?></span></div>
                        <div><strong>Date:</strong> <span id="printBLDate"><?php echo date('d/m/Y'); ?></span></div>
                        <div><strong>Référence:</strong> <span id="printReference">-</span></div>
                    </div>
                </div>

                <div class="print-details-grid">
                    <div class="print-company">
                        <h4>Expéditeur:</h4>
                        <p><strong>IMPRIMERIE PRO</strong></p>
                        <p>123 Rue d'Impression</p>
                        <p>Alger Centre, Algérie</p>
                        <p>Tél: 021 23 45 67</p>
                    </div>
                    <div class="print-client">
                        <h4>Destinataire:</h4>
                        <p id="printBLClientName">-</p>
                        <p id="printBLClientAddress">-</p>
                        <p id="printBLClientContact">-</p>
                    </div>
                </div>

                <div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div>
                            <strong>Livreur:</strong> <span id="printDeliveryPerson">-</span>
                        </div>
                        <div>
                            <strong>Véhicule:</strong> <span id="printVehicle">-</span>
                        </div>
                        <div>
                            <strong>Conditions de remise:</strong> <span id="printConditions">Sur place</span>
                        </div>
                        <div>
                            <strong>Date de livraison:</strong> <span id="printDeliveryDate"><?php echo date('d/m/Y'); ?></span>
                        </div>
                    </div>
                </div>

                <table class="print-items-table">
                    <thead>
                        <tr>
                            <th width="40">N°</th>
                            <th>Description</th>
                            <th width="80">Unité</th>
                            <th width="90">Qté livrée</th>
                            <th width="120">Observations</th>
                        </tr>
                    </thead>
                    <tbody id="printBLItems">
                        <!-- Les articles seront insérés ici -->
                    </tbody>
                </table>

                <div class="print-terms">
                    <h4>Observations</h4>
                    <p id="printBLNotes">-</p>
                    
                    <div style="margin-top: 30px; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                        <h4>Signatures</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-top: 20px;">
                            <div style="text-align: center;">
                                <p><strong>Le Livreur</strong></p>
                                <div style="margin-top: 50px;">
                                    <p>Nom: _______________________________</p>
                                    <p>Signature:</p>
                                    <div style="border-top: 1px solid #333; margin-top: 40px;"></div>
                                </div>
                            </div>
                            <div style="text-align: center;">
                                <p><strong>Le Client</strong></p>
                                <div style="margin-top: 50px;">
                                    <p>Nom: _______________________________</p>
                                    <p>Signature:</p>
                                    <div style="border-top: 1px solid #333; margin-top: 40px;"></div>
                                    <p style="margin-top: 10px;">Cachet:</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="print-footer">
                    <p>Document établi en double exemplaire - Un exemplaire pour le client, un pour l'expéditeur</p>
                </div>
                
                <div class="no-print" style="margin-top: 30px; text-align: center;">
                    <button class="btn btn-primary" id="doPrintBL">
                        <i class="fas fa-print"></i> Imprimer maintenant
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Variables globales
        let invoiceItems = [];
        let blItems = [];
        let invoiceItemCount = 0;
        let blItemCount = 0;
        let currentDocumentType = 'invoice';

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Ajouter un premier article à la facture et au BL
            addNewInvoiceItem();
            addNewBLItem();
            
            // Mettre à jour les aperçus
            updateInvoicePreview();
            updateBLPreview();
            
            // Configurer les écouteurs d'événements
            setupEventListeners();
            
            // Initialiser les onglets
            initTabs();
            
            // Load client data on select change
            loadClientData();
            loadBLClientData();
            
            // Handle success message display
            if (window.location.search.includes('success')) {
                setTimeout(() => {
                    window.history.replaceState({}, document.title, window.location.pathname);
                }, 3000);
            }

            // Load preview invoice if exists
            <?php if ($preview_invoice): ?>
            loadInvoiceForPreview(<?php echo json_encode($preview_invoice); ?>, <?php echo json_encode($preview_invoice_items ?? []); ?>);
            <?php endif; ?>

            // Load preview BL if exists
            <?php if ($preview_bl): ?>
            loadBLForPreview(<?php echo json_encode($preview_bl); ?>, <?php echo json_encode($preview_bl_items ?? []); ?>);
            <?php endif; ?>
        });

        // Load client data when selected (for invoice)
        function loadClientData() {
            const clientSelect = document.getElementById('client_id');
            const clientAddress = document.getElementById('client_address');
            const clientContact = document.getElementById('client_contact');
            const previewClientName = document.getElementById('previewClientName');
            const previewClientAddress = document.getElementById('previewClientAddress');
            const previewClientContact = document.getElementById('previewClientContact');
            
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

        // Load client data when selected (for BL)
        function loadBLClientData() {
            const clientSelect = document.getElementById('bl_client_id');
            const clientAddress = document.getElementById('bl_client_address');
            const clientContact = document.getElementById('bl_client_contact');
            const previewClientName = document.getElementById('previewBLClientName');
            const previewClientAddress = document.getElementById('previewBLClientAddress');
            const previewClientContact = document.getElementById('previewBLClientContact');
            
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

        // Load invoice for preview
        function loadInvoiceForPreview(invoiceData, invoiceItemsData) {
            // Fill client data
            const clientSelect = document.getElementById('client_id');
            const clientOption = Array.from(clientSelect.options).find(opt => opt.value == invoiceData.client_id);
            if (clientOption) {
                clientSelect.value = invoiceData.client_id;
                clientSelect.dispatchEvent(new Event('change'));
            }
            
            // Fill invoice details
            document.getElementById('invoice_number').value = 'FAC-' + invoiceData.id.toString().padStart(4, '0');
            document.getElementById('invoice_date').value = new Date(invoiceData.created_at).toISOString().split('T')[0];
            
            // Clear existing items
            invoiceItems = [];
            document.getElementById('invoiceItems').innerHTML = '';
            invoiceItemCount = 0;
            
            // Add invoice items
            invoiceItemsData.forEach((item, index) => {
                addNewInvoiceItem();
                const itemId = `invoice-item-${invoiceItemCount}`;
                const itemRow = document.getElementById(itemId);
                
                if (itemRow) {
                    itemRow.querySelector('.item-desc').value = item.description;
                    itemRow.querySelector('.item-unit').value = 'unité';
                    itemRow.querySelector('.item-qty').value = item.quantity;
                    itemRow.querySelector('.item-price').value = item.price;
                    
                    // Update item object
                    const itemObj = invoiceItems.find(i => i.id === itemId);
                    if (itemObj) {
                        itemObj.description = item.description;
                        itemObj.quantity = item.quantity;
                        itemObj.price = item.price;
                        itemObj.total = item.quantity * item.price;
                        
                        calculateInvoiceItemTotal(itemId);
                    }
                }
            });
            
            // Calculate totals
            calculateInvoice();
            
            // Switch to invoice tab
            document.querySelector('.tab[data-tab="invoice"]').click();
        }

        // Load BL for preview
        function loadBLForPreview(blData, blItemsData) {
            // Fill client data
            const clientSelect = document.getElementById('bl_client_id');
            const clientOption = Array.from(clientSelect.options).find(opt => opt.value == blData.client_id);
            if (clientOption) {
                clientSelect.value = blData.client_id;
                clientSelect.dispatchEvent(new Event('change'));
            }
            
            // Fill BL details
            document.getElementById('bl_number').value = 'BL-' + blData.id.toString().padStart(4, '0');
            document.getElementById('bl_date').value = new Date(blData.deadline || blData.created_at).toISOString().split('T')[0];
            
            // Clear existing items
            blItems = [];
            document.getElementById('blItems').innerHTML = '';
            blItemCount = 0;
            
            // Add BL items
            blItemsData.forEach((item, index) => {
                addNewBLItem();
                const itemId = `bl-item-${blItemCount}`;
                const itemRow = document.getElementById(itemId);
                
                if (itemRow) {
                    const descMatch = item.description.match(/^(.*?)\s*\((.*?)\)$/);
                    if (descMatch) {
                        itemRow.querySelector('.bl-item-desc').value = descMatch[1].trim();
                        itemRow.querySelector('.bl-item-unit').value = descMatch[2].trim();
                    } else {
                        itemRow.querySelector('.bl-item-desc').value = item.description;
                        itemRow.querySelector('.bl-item-unit').value = 'unité';
                    }
                    itemRow.querySelector('.bl-item-qty').value = item.quantity;
                    
                    // Update item object
                    const itemObj = blItems.find(i => i.id === itemId);
                    if (itemObj) {
                        itemObj.description = item.description;
                        itemObj.quantity = item.quantity;
                    }
                }
            });
            
            // Update preview
            updateBLPreview();
            
            // Switch to BL tab
            document.querySelector('.tab[data-tab="bl"]').click();
        }

        // Gestion des onglets
        function initTabs() {
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    // Mettre à jour l'onglet actif
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Afficher le contenu correspondant
                    document.querySelectorAll('.tab-content').forEach(content => {
                        content.classList.remove('active');
                    });
                    document.getElementById(`${tabId}-tab`).classList.add('active');
                    
                    currentDocumentType = tabId;
                });
            });
        }

        // ============ FONCTIONS FACTURE ============
        function addNewInvoiceItem() {
            invoiceItemCount++;
            const itemId = `invoice-item-${invoiceItemCount}`;
            
            const newItem = {
                id: itemId,
                description: '',
                unit: 'unité',
                quantity: 1,
                price: 0,
                total: 0
            };
            
            invoiceItems.push(newItem);
            
            const itemRow = document.createElement('tr');
            itemRow.id = itemId;
            itemRow.innerHTML = `
                <td>${invoiceItemCount}</td>
                <td>
                    <input type="text" name="item_description[]" class="item-desc form-control" 
                           placeholder="Description de l'article">
                </td>
                <td class="unit-column">
                    <select name="item_unit[]" class="item-unit form-control">
                        <option value="unité">unité</option>
                        <option value="paquet">paquet</option>
                        <option value="mètre">mètre</option>
                        <option value="kg">kg</option>
                        <option value="heure">heure</option>
                        <option value="jour">jour</option>
                    </select>
                </td>
                <td>
                    <input type="number" name="item_quantity[]" class="item-qty form-control" 
                           min="0.001" step="0.001" value="1" placeholder="1">
                </td>
                <td>
                    <input type="number" name="item_price[]" class="item-price form-control" 
                           min="0" step="0.01" value="0" placeholder="0.00">
                </td>
                <td class="item-total">0.00 DA</td>
                <td><button type="button" class="btn-icon remove-item"><i class="fas fa-trash"></i></button></td>
            `;
            
            document.getElementById('invoiceItems').appendChild(itemRow);
            
            // Ajouter les écouteurs d'événements pour cet article
            addInvoiceItemEventListeners(itemId);
            
            // Calculer le total pour cet article
            calculateInvoiceItemTotal(itemId);
            
            // Mettre à jour le nombre d'articles dans l'aperçu
            updateInvoicePreview();
        }

        function addInvoiceItemEventListeners(itemId) {
            const itemRow = document.getElementById(itemId);
            const descInput = itemRow.querySelector('.item-desc');
            const unitSelect = itemRow.querySelector('.item-unit');
            const qtyInput = itemRow.querySelector('.item-qty');
            const priceInput = itemRow.querySelector('.item-price');
            const removeBtn = itemRow.querySelector('.remove-item');
            
            descInput.addEventListener('input', function() {
                updateInvoiceItem(itemId, 'description', this.value);
                updateInvoicePreview();
            });
            
            unitSelect.addEventListener('change', function() {
                updateInvoiceItem(itemId, 'unit', this.value);
                updateInvoicePreview();
            });
            
            qtyInput.addEventListener('input', function() {
                updateInvoiceItem(itemId, 'quantity', parseFloat(this.value) || 0);
                calculateInvoiceItemTotal(itemId);
                calculateInvoice();
            });
            
            priceInput.addEventListener('input', function() {
                updateInvoiceItem(itemId, 'price', parseFloat(this.value) || 0);
                calculateInvoiceItemTotal(itemId);
                calculateInvoice();
            });
            
            removeBtn.addEventListener('click', function() {
                removeInvoiceItem(itemId);
            });
        }

        function updateInvoiceItem(id, field, value) {
            const item = invoiceItems.find(item => item.id === id);
            if (item) {
                item[field] = value;
            }
        }

        function calculateInvoiceItemTotal(id) {
            const item = invoiceItems.find(item => item.id === id);
            if (item) {
                item.total = item.quantity * item.price;
                const totalCell = document.querySelector(`#${id} .item-total`);
                totalCell.textContent = formatCurrency(item.total);
            }
        }

        function removeInvoiceItem(id) {
            invoiceItems = invoiceItems.filter(item => item.id !== id);
            const itemRow = document.getElementById(id);
            if (itemRow) {
                itemRow.remove();
            }
            
            // Renumber items
            const itemRows = document.querySelectorAll('#invoiceItems tr');
            itemRows.forEach((row, index) => {
                row.cells[0].textContent = index + 1;
            });
            
            calculateInvoice();
            updateInvoicePreview();
        }

        function calculateInvoice() {
            let subtotal = 0;
            
            // Calculer le sous-total
            invoiceItems.forEach(item => {
                subtotal += item.total;
            });
            
            document.getElementById('subtotal').textContent = formatCurrency(subtotal);
            document.getElementById('previewSubtotal').textContent = formatCurrency(subtotal);
            
            // Calculer la TVA
            const tvaRate = parseFloat(document.getElementById('tva_rate').value) || 0;
            const tvaAmount = subtotal * (tvaRate / 100);
            const total = subtotal + tvaAmount;
            
            document.getElementById('tvaPercent').textContent = tvaRate;
            document.getElementById('tvaAmount').textContent = formatCurrency(tvaAmount);
            document.getElementById('totalAmount').textContent = formatCurrency(total);
            
            document.getElementById('previewTvaAmount').textContent = formatCurrency(tvaAmount);
            document.getElementById('previewTotalAmount').textContent = formatCurrency(total);
            
            // Update hidden inputs for form submission
            document.getElementById('subtotal_input').value = subtotal;
            document.getElementById('tva_amount_input').value = tvaAmount;
            document.getElementById('total_input').value = total;
            
            // Mettre à jour l'aperçu
            updateInvoicePreview();
            
            return { subtotal, tvaAmount, total };
        }

        function updateInvoicePreview() {
            // Update items preview
            const previewItemsContainer = document.getElementById('previewInvoiceItems');
            previewItemsContainer.innerHTML = '';
            
            if (invoiceItems.length === 0) {
                previewItemsContainer.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 20px; color: #999;">
                            Aucun article ajouté
                        </td>
                    </tr>
                `;
            } else {
                invoiceItems.forEach((item, index) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${index + 1}</td>
                        <td>${item.description || 'Article'}</td>
                        <td>${item.unit || 'unité'}</td>
                        <td>${formatNumber(item.quantity)}</td>
                        <td>${formatCurrency(item.price)}</td>
                        <td>${formatCurrency(item.total)}</td>
                    `;
                    previewItemsContainer.appendChild(row);
                });
            }
            
            // Update other preview fields
            const dueDate = document.getElementById('due_date').value;
            const paymentTerms = document.getElementById('payment_terms').value;
            const notes = document.getElementById('notes').value;
            
            document.getElementById('previewDueDate').textContent = formatDate(dueDate);
            document.getElementById('previewPaymentTerms').textContent = paymentTerms;
            document.getElementById('previewNotes').textContent = notes || '-';
            
            // Update dates
            document.getElementById('previewInvoiceDate').textContent = formatDate(document.getElementById('invoice_date').value);
        }

        // ============ FONCTIONS BL ============
        function addNewBLItem() {
            blItemCount++;
            const itemId = `bl-item-${blItemCount}`;
            
            const newItem = {
                id: itemId,
                description: '',
                unit: 'unité',
                quantity: 1
            };
            
            blItems.push(newItem);
            
            const itemRow = document.createElement('tr');
            itemRow.id = itemId;
            itemRow.innerHTML = `
                <td>${blItemCount}</td>
                <td>
                    <input type="text" name="bl_item_description[]" class="bl-item-desc form-control" 
                           placeholder="Description de l'article">
                </td>
                <td class="unit-column">
                    <select name="bl_item_unit[]" class="bl-item-unit form-control">
                        <option value="unité">unité</option>
                        <option value="paquet">paquet</option>
                        <option value="mètre">mètre</option>
                        <option value="kg">kg</option>
                        <option value="rouleau">rouleau</option>
                        <option value="carton">carton</option>
                    </select>
                </td>
                <td>
                    <input type="number" name="bl_item_quantity[]" class="bl-item-qty form-control" 
                           min="1" step="1" value="1" placeholder="1">
                </td>
                <td><button type="button" class="btn-icon remove-bl-item"><i class="fas fa-trash"></i></button></td>
            `;
            
            document.getElementById('blItems').appendChild(itemRow);
            
            // Ajouter les écouteurs d'événements pour cet article
            addBLItemEventListeners(itemId);
            
            // Mettre à jour l'aperçu
            updateBLPreview();
        }

        function addBLItemEventListeners(itemId) {
            const itemRow = document.getElementById(itemId);
            const descInput = itemRow.querySelector('.bl-item-desc');
            const unitSelect = itemRow.querySelector('.bl-item-unit');
            const qtyInput = itemRow.querySelector('.bl-item-qty');
            const removeBtn = itemRow.querySelector('.remove-bl-item');
            
            descInput.addEventListener('input', function() {
                updateBLItem(itemId, 'description', this.value);
                updateBLPreview();
            });
            
            unitSelect.addEventListener('change', function() {
                updateBLItem(itemId, 'unit', this.value);
                updateBLPreview();
            });
            
            qtyInput.addEventListener('input', function() {
                updateBLItem(itemId, 'quantity', parseInt(this.value) || 1);
                updateBLPreview();
            });
            
            removeBtn.addEventListener('click', function() {
                removeBLItem(itemId);
            });
        }

        function updateBLItem(id, field, value) {
            const item = blItems.find(item => item.id === id);
            if (item) {
                item[field] = value;
            }
        }

        function removeBLItem(id) {
            blItems = blItems.filter(item => item.id !== id);
            const itemRow = document.getElementById(id);
            if (itemRow) {
                itemRow.remove();
            }
            
            // Renumber items
            const itemRows = document.querySelectorAll('#blItems tr');
            itemRows.forEach((row, index) => {
                row.cells[0].textContent = index + 1;
            });
            
            updateBLPreview();
        }

        function updateBLPreview() {
            // Update items preview
            const previewItemsContainer = document.getElementById('previewBLItems');
            previewItemsContainer.innerHTML = '';
            
            if (blItems.length === 0) {
                previewItemsContainer.innerHTML = `
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px; color: #999;">
                            Aucun article ajouté
                        </td>
                    </tr>
                `;
            } else {
                blItems.forEach((item, index) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${index + 1}</td>
                        <td>${item.description || 'Article'}</td>
                        <td>${item.unit || 'unité'}</td>
                        <td>${formatNumber(item.quantity)}</td>
                        <td></td>
                    `;
                    previewItemsContainer.appendChild(row);
                });
            }
            
            // Update other preview fields
            const deliveryPerson = document.getElementById('delivery_person').value;
            const vehicle = document.getElementById('vehicle').value;
            const reference = document.getElementById('reference').value;
            const conditions = document.getElementById('conditions').value;
            const notes = document.getElementById('bl_notes').value;
            
            document.getElementById('previewDeliveryPerson').textContent = deliveryPerson || '-';
            document.getElementById('previewVehicle').textContent = vehicle || '-';
            document.getElementById('previewReference').textContent = reference || '-';
            document.getElementById('previewConditions').textContent = conditions;
            document.getElementById('previewBLNotes').textContent = notes || '-';
            
            // Update dates
            document.getElementById('previewBLDate').textContent = formatDate(document.getElementById('bl_date').value);
        }

        // ============ CONFIGURATION DES ÉCOUTEURS ============
        function setupEventListeners() {
            // --- Factures ---
            document.getElementById('addInvoiceItem').addEventListener('click', function() {
                addNewInvoiceItem();
                calculateInvoice();
            });
            
            document.getElementById('calculateInvoice').addEventListener('click', calculateInvoice);
            
            document.getElementById('tva_rate').addEventListener('input', calculateInvoice);
            
            document.getElementById('client_id').addEventListener('change', updateInvoicePreview);
            document.getElementById('invoice_date').addEventListener('input', updateInvoicePreview);
            document.getElementById('due_date').addEventListener('input', updateInvoicePreview);
            document.getElementById('payment_terms').addEventListener('change', updateInvoicePreview);
            document.getElementById('delivery_terms').addEventListener('change', updateInvoicePreview);
            document.getElementById('notes').addEventListener('input', updateInvoicePreview);
            
            // --- BL ---
            document.getElementById('addBLItem').addEventListener('click', function() {
                addNewBLItem();
                updateBLPreview();
            });
            
            document.getElementById('bl_client_id').addEventListener('change', updateBLPreview);
            document.getElementById('bl_date').addEventListener('input', updateBLPreview);
            document.getElementById('delivery_person').addEventListener('input', updateBLPreview);
            document.getElementById('vehicle').addEventListener('input', updateBLPreview);
            document.getElementById('reference').addEventListener('input', updateBLPreview);
            document.getElementById('conditions').addEventListener('change', updateBLPreview);
            document.getElementById('bl_notes').addEventListener('input', updateBLPreview);
            
            // --- Actions communes ---
            setupCommonEventListeners();
        }

        function setupCommonEventListeners() {
            // Prévisualiser la facture
            document.getElementById('previewInvoice').addEventListener('click', function() {
                const result = calculateInvoice();
                if (invoiceItems.length === 0) {
                    alert('Veuillez ajouter au moins un article à la facture.');
                    return;
                }
                
                fillPrintData('invoice');
                document.getElementById('printInvoiceModal').classList.add('active');
            });
            
            // Imprimer la facture
            document.getElementById('printInvoice').addEventListener('click', function() {
                const result = calculateInvoice();
                if (invoiceItems.length === 0) {
                    alert('Veuillez ajouter au moins un article à la facture.');
                    return;
                }
                
                fillPrintData('invoice');
                document.getElementById('printInvoiceModal').classList.add('active');
            });
            
            // Prévisualiser le BL
            document.getElementById('previewBL').addEventListener('click', function() {
                if (blItems.length === 0) {
                    alert('Veuillez ajouter au moins un article au BL.');
                    return;
                }
                
                fillPrintData('bl');
                document.getElementById('printBLModal').classList.add('active');
            });
            
            // Imprimer le BL
            document.getElementById('printBL').addEventListener('click', function() {
                if (blItems.length === 0) {
                    alert('Veuillez ajouter au moins un article au BL.');
                    return;
                }
                
                fillPrintData('bl');
                document.getElementById('printBLModal').classList.add('active');
            });
            
            // Actualiser la liste
            document.getElementById('refreshList').addEventListener('click', function() {
                location.reload();
            });
            
            // Fermer les modals
            document.getElementById('closeInvoiceModal').addEventListener('click', function() {
                document.getElementById('printInvoiceModal').classList.remove('active');
            });
            
            document.getElementById('closeBLModal').addEventListener('click', function() {
                document.getElementById('printBLModal').classList.remove('active');
            });
            
            // Imprimer depuis les modals
            document.getElementById('doPrintInvoice').addEventListener('click', function() {
                window.print();
            });
            
            document.getElementById('doPrintBL').addEventListener('click', function() {
                window.print();
            });
        }

        // Remplir les données dans le modal d'impression
        function fillPrintData(type) {
            if (type === 'invoice') {
                const result = calculateInvoice();
                const clientSelect = document.getElementById('client_id');
                const selectedOption = clientSelect.options[clientSelect.selectedIndex];
                const clientName = selectedOption?.text || '-';
                const address = selectedOption?.getAttribute('data-address') || '-';
                const phone = selectedOption?.getAttribute('data-phone') || '';
                const email = selectedOption?.getAttribute('data-email') || '';
                
                // Informations de base
                document.getElementById('printInvoiceNumber').textContent = 
                    document.getElementById('invoice_number').value;
                document.getElementById('printInvoiceDate').textContent = 
                    formatDate(document.getElementById('invoice_date').value);
                document.getElementById('printDueDate').textContent = 
                    formatDate(document.getElementById('due_date').value);
                document.getElementById('printPaymentTerms').textContent = 
                    document.getElementById('payment_terms').value;
                document.getElementById('printDeliveryTerms').textContent = 
                    document.getElementById('delivery_terms').value;
                
                // Informations du client
                document.getElementById('printClientName').textContent = clientName;
                document.getElementById('printClientAddress').textContent = address;
                document.getElementById('printClientContact').textContent = phone + (email ? ' | ' + email : '');
                
                // Articles
                const printItemsContainer = document.getElementById('printInvoiceItems');
                printItemsContainer.innerHTML = '';
                
                invoiceItems.forEach((item, index) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${index + 1}</td>
                        <td>${item.description || 'Article'}</td>
                        <td>${item.unit || 'unité'}</td>
                        <td>${formatNumber(item.quantity)}</td>
                        <td>${formatCurrency(item.price)}</td>
                        <td>${formatCurrency(item.total)}</td>
                    `;
                    printItemsContainer.appendChild(row);
                });
                
                // Totaux
                document.getElementById('printSubtotal').textContent = formatCurrency(result.subtotal);
                document.getElementById('printTvaPercent').textContent = document.getElementById('tva_rate').value;
                document.getElementById('printTvaAmount').textContent = formatCurrency(result.tvaAmount);
                document.getElementById('printTotalAmount').textContent = formatCurrency(result.total);
                
                // Notes
                const notes = document.getElementById('notes').value;
                document.getElementById('printNotes').textContent = notes || '-';
                
            } else if (type === 'bl') {
                const clientSelect = document.getElementById('bl_client_id');
                const selectedOption = clientSelect.options[clientSelect.selectedIndex];
                const clientName = selectedOption?.text || '-';
                const address = selectedOption?.getAttribute('data-address') || '-';
                const phone = selectedOption?.getAttribute('data-phone') || '';
                const email = selectedOption?.getAttribute('data-email') || '';
                
                // Informations de base
                document.getElementById('printBLNumber').textContent = 
                    document.getElementById('bl_number').value;
                document.getElementById('printBLDate').textContent = 
                    formatDate(document.getElementById('bl_date').value);
                document.getElementById('printDeliveryDate').textContent = 
                    formatDate(document.getElementById('bl_date').value);
                document.getElementById('printReference').textContent = 
                    document.getElementById('reference').value || '-';
                document.getElementById('printDeliveryPerson').textContent = 
                    document.getElementById('delivery_person').value || '-';
                document.getElementById('printVehicle').textContent = 
                    document.getElementById('vehicle').value || '-';
                document.getElementById('printConditions').textContent = 
                    document.getElementById('conditions').value;
                
                // Informations du client
                document.getElementById('printBLClientName').textContent = clientName;
                document.getElementById('printBLClientAddress').textContent = address;
                document.getElementById('printBLClientContact').textContent = phone + (email ? ' | ' + email : '');
                
                // Articles
                const printItemsContainer = document.getElementById('printBLItems');
                printItemsContainer.innerHTML = '';
                
                blItems.forEach((item, index) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${index + 1}</td>
                        <td>${item.description || 'Article'}</td>
                        <td>${item.unit || 'unité'}</td>
                        <td>${formatNumber(item.quantity)}</td>
                        <td></td>
                    `;
                    printItemsContainer.appendChild(row);
                });
                
                // Notes
                const notes = document.getElementById('bl_notes').value;
                document.getElementById('printBLNotes').textContent = notes || '-';
            }
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

        // Functions for printing existing documents
        window.printExistingInvoice = function(invoiceId) {
            window.open(`print_invoice.php?id=${invoiceId}`, '_blank');
        };

        window.viewInvoice = function(invoiceId) {
            window.location.href = `facturation.php?invoice_id=${invoiceId}`;
        };

        window.printExistingBL = function(orderId) {
            window.open(`print_bl.php?id=${orderId}`, '_blank');
        };

        window.viewBL = function(orderId) {
            window.location.href = `facturation.php?bl_id=${orderId}`;
        };

        // Filter documents
        document.getElementById('filterType').addEventListener('change', filterDocuments);
        document.getElementById('filterStatus').addEventListener('change', filterDocuments);

        function filterDocuments() {
            const typeFilter = document.getElementById('filterType').value;
            const statusFilter = document.getElementById('filterStatus').value;
            const rows = document.querySelectorAll('#documentsList tr');
            
            rows.forEach(row => {
                const type = row.cells[0].textContent.toLowerCase().includes('facture') ? 'invoice' : 'bl';
                const status = row.cells[5].textContent.toLowerCase();
                
                let showRow = true;
                
                // Filter by type
                if (typeFilter !== 'all' && type !== typeFilter) {
                    showRow = false;
                }
                
                // Filter by status
                if (statusFilter !== 'all') {
                    let statusMatch = false;
                    switch(statusFilter) {
                        case 'paid':
                            statusMatch = status.includes('payée');
                            break;
                        case 'unpaid':
                            statusMatch = status.includes('non payée');
                            break;
                        case 'pending':
                            statusMatch = status.includes('attente') || status.includes('en cours');
                            break;
                        case 'delivered':
                            statusMatch = status.includes('livré');
                            break;
                    }
                    if (!statusMatch) {
                        showRow = false;
                    }
                }
                
                row.style.display = showRow ? '' : 'none';
            });
        }
    </script>
</body>
</html>