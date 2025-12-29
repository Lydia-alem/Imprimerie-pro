<?php
// Start session for user authentication
session_start();

// Database configuration - UPDATE THESE FOR YOUR ENVIRONMENT
$host = '127.0.0.1:3306';
$dbname = 'imprimerie';
$username = 'root'; // Change this
$password = 'admine'; // Change this

// Create connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if we need to handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    handlePostRequest($pdo);
    exit();
}

// Handle GET requests for details
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['get_order_details'])) {
    getOrderDetails($pdo);
    exit();
}

// Handle login/logout
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'logout') {
        session_destroy();
        header('Location: index.php');
        exit();
    }
}

// Main function to handle POST requests
function handlePostRequest($pdo) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'add_client':
            addClient($pdo);
            break;
        case 'update_client':
            updateClient($pdo);
            break;
        case 'delete_client':
            deleteClient($pdo);
            break;
        case 'add_order':
            addOrder($pdo);
            break;
        case 'update_order':
            updateOrder($pdo);
            break;
        case 'delete_order':
            deleteOrder($pdo);
            break;
        case 'add_payment':
            addPayment($pdo);
            break;
        case 'update_payment':
            updatePayment($pdo);
            break;
        case 'delete_payment':
            deletePayment($pdo);
            break;
        case 'add_supplier':
            addSupplier($pdo);
            break;
        case 'update_supplier':
            updateSupplier($pdo);
            break;
        case 'delete_supplier':
            deleteSupplier($pdo);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
            exit();
    }
}

// Functions for database operations
function addClient($pdo) {
    try {
        $stmt = $pdo->prepare("INSERT INTO clients (name, email, phone, address) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['address']
        ]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Client ajouté avec succès']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit();
}

function updateClient($pdo) {
    try {
        $stmt = $pdo->prepare("UPDATE clients SET name = ?, email = ?, phone = ?, address = ? WHERE id = ?");
        $stmt->execute([
            $_POST['name'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['address'],
            $_POST['id']
        ]);
        echo json_encode(['success' => true, 'message' => 'Client mis à jour avec succès']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit();
}

function deleteClient($pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        echo json_encode(['success' => true, 'message' => 'Client supprimé avec succès']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit();
}

function addOrder($pdo) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO orders (client_id, status, deadline, total) VALUES (?, ?, ?, ?)");
        $total = $_POST['quantity'] * $_POST['price'];
        $stmt->execute([
            $_POST['client_id'],
            $_POST['status'],
            $_POST['deadline'] ?: null,
            $total
        ]);
        $orderId = $pdo->lastInsertId();
        
        // Add order item
        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, description, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $orderId,
            $_POST['description'],
            $_POST['quantity'],
            $_POST['price'],
            $total
        ]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $orderId, 'message' => 'Commande ajoutée avec succès']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit();
}

function updateOrder($pdo) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        $total = $_POST['quantity'] * $_POST['price'];
        $stmt = $pdo->prepare("UPDATE orders SET client_id = ?, status = ?, deadline = ?, total = ? WHERE id = ?");
        $stmt->execute([
            $_POST['client_id'],
            $_POST['status'],
            $_POST['deadline'] ?: null,
            $total,
            $_POST['id']
        ]);
        
        // Update or insert order item
        $stmt = $pdo->prepare("SELECT id FROM order_items WHERE order_id = ?");
        $stmt->execute([$_POST['id']]);
        $orderItem = $stmt->fetch();
        
        if ($orderItem) {
            $stmt = $pdo->prepare("UPDATE order_items SET description = ?, quantity = ?, price = ?, subtotal = ? WHERE order_id = ?");
            $stmt->execute([
                $_POST['description'],
                $_POST['quantity'],
                $_POST['price'],
                $total,
                $_POST['id']
            ]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, description, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['id'],
                $_POST['description'],
                $_POST['quantity'],
                $_POST['price'],
                $total
            ]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Commande mise à jour avec succès']);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit();
}

function deleteOrder($pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        echo json_encode(['success' => true, 'message' => 'Commande supprimée avec succès']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit();
}

function addPayment($pdo) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // First, check if an invoice exists for this order
        $stmt = $pdo->prepare("SELECT id, total FROM invoices WHERE order_id = ?");
        $stmt->execute([$_POST['order_id']]);
        $invoice = $stmt->fetch();
        
        if (!$invoice) {
            // Create an invoice for this order
            $stmt = $pdo->prepare("SELECT client_id, total FROM orders WHERE id = ?");
            $stmt->execute([$_POST['order_id']]);
            $order = $stmt->fetch();
            
            if (!$order) {
                throw new Exception("Commande non trouvée");
            }
            
            $stmt = $pdo->prepare("INSERT INTO invoices (order_id, client_id, status, total, vat) VALUES (?, ?, 'unpaid', ?, 0.00)");
            $stmt->execute([
                $_POST['order_id'],
                $order['client_id'],
                $order['total']
            ]);
            $invoiceId = $pdo->lastInsertId();
        } else {
            $invoiceId = $invoice['id'];
        }
        
        // Get client_id from invoice
        $stmt = $pdo->prepare("SELECT client_id FROM invoices WHERE id = ?");
        $stmt->execute([$invoiceId]);
        $invoiceData = $stmt->fetch();
        $clientId = $invoiceData['client_id'];
        
        // Add payment
        $stmt = $pdo->prepare("INSERT INTO payments (invoice_id, client_id, amount, payment_date, payment_method, reference, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $invoiceId,
            $clientId,
            $_POST['amount'],
            $_POST['payment_date'],
            $_POST['payment_method'],
            $_POST['reference'],
            $_POST['notes']
        ]);
        
        // Recalculate invoice status
        updateInvoiceStatus($pdo, $invoiceId);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Versement ajouté avec succès']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit();
}

function updatePayment($pdo) {
    try {
        $stmt = $pdo->prepare("UPDATE payments SET amount = ?, payment_date = ?, payment_method = ?, reference = ?, notes = ? WHERE id = ?");
        $stmt->execute([
            $_POST['amount'],
            $_POST['payment_date'],
            $_POST['payment_method'],
            $_POST['reference'],
            $_POST['notes'],
            $_POST['id']
        ]);
        
        // Get invoice_id from payment to recalculate status
        $stmt = $pdo->prepare("SELECT invoice_id FROM payments WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $payment = $stmt->fetch();
        
        if ($payment) {
            updateInvoiceStatus($pdo, $payment['invoice_id']);
        }
        
        echo json_encode(['success' => true, 'message' => 'Versement mis à jour avec succès']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit();
}

function deletePayment($pdo) {
    try {
        // Get invoice_id before deleting
        $stmt = $pdo->prepare("SELECT invoice_id FROM payments WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        $payment = $stmt->fetch();
        
        // Delete payment
        $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        
        // Recalculate invoice status
        if ($payment) {
            updateInvoiceStatus($pdo, $payment['invoice_id']);
        }
        
        echo json_encode(['success' => true, 'message' => 'Versement supprimé avec succès']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit();
}

function updateInvoiceStatus($pdo, $invoiceId) {
    // Calculate total paid for this invoice
    $stmt = $pdo->prepare("SELECT SUM(amount) as total_paid FROM payments WHERE invoice_id = ?");
    $stmt->execute([$invoiceId]);
    $totalPaid = $stmt->fetch()['total_paid'] ?? 0;
    
    // Get invoice total
    $stmt = $pdo->prepare("SELECT total FROM invoices WHERE id = ?");
    $stmt->execute([$invoiceId]);
    $invoiceTotal = $stmt->fetch()['total'];
    
    // Update invoice status based on payments
    $newStatus = 'unpaid';
    if ($totalPaid >= $invoiceTotal) {
        $newStatus = 'paid';
    } elseif ($totalPaid > 0) {
        $newStatus = 'partial';
    }
    
    $stmt = $pdo->prepare("UPDATE invoices SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $invoiceId]);
}

function addSupplier($pdo) {
    try {
        $stmt = $pdo->prepare("INSERT INTO stock (item_name, quantity, unit, low_stock_limit) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_POST['name'],
            $_POST['quantity'] ?: 0,
            $_POST['unit'] ?: 'unité',
            $_POST['low_stock_limit'] ?: 10
        ]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Fournisseur ajouté avec succès']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit();
}

function updateSupplier($pdo) {
    try {
        $stmt = $pdo->prepare("UPDATE stock SET item_name = ?, quantity = ?, unit = ?, low_stock_limit = ? WHERE id = ?");
        $stmt->execute([
            $_POST['name'],
            $_POST['quantity'] ?: 0,
            $_POST['unit'] ?: 'unité',
            $_POST['low_stock_limit'] ?: 10,
            $_POST['id']
        ]);
        echo json_encode(['success' => true, 'message' => 'Fournisseur mis à jour avec succès']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit();
}

function deleteSupplier($pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM stock WHERE id = ?");
        $stmt->execute([$_POST['id']]);
        echo json_encode(['success' => true, 'message' => 'Fournisseur supprimé avec succès']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
    exit();
}

function getOrderDetails($pdo) {
    $orderId = $_GET['get_order_details'];
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, c.name as client_name, c.email as client_email, c.phone as client_phone,
                   oi.description, oi.quantity, oi.price, oi.subtotal
            FROM orders o
            LEFT JOIN clients c ON o.client_id = c.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order) {
            // Get invoice for this order
            $stmt = $pdo->prepare("SELECT * FROM invoices WHERE order_id = ?");
            $stmt->execute([$orderId]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($invoice) {
                // Get payments for this invoice
                $stmt = $pdo->prepare("
                    SELECT * FROM payments 
                    WHERE invoice_id = ? 
                    ORDER BY payment_date DESC
                ");
                $stmt->execute([$invoice['id']]);
                $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Calculate total paid
                $stmt = $pdo->prepare("SELECT SUM(amount) as total_paid FROM payments WHERE invoice_id = ?");
                $stmt->execute([$invoice['id']]);
                $totalPaid = $stmt->fetch()['total_paid'] ?? 0;
                
                $order['invoice'] = $invoice;
                $order['payments'] = $payments;
                $order['total_paid'] = $totalPaid;
                $order['remaining'] = $invoice['total'] - $totalPaid;
            } else {
                $order['invoice'] = null;
                $order['payments'] = [];
                $order['total_paid'] = 0;
                $order['remaining'] = $order['total'];
            }
            
            echo json_encode($order);
        } else {
            echo json_encode(['error' => 'Commande non trouvée']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Erreur: ' . $e->getMessage()]);
    }
    exit();
}

// Fetch data from database
function getClients($pdo) {
    $stmt = $pdo->query("SELECT * FROM clients ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getOrders($pdo) {
    // Include order_items.description so description is available in the $orders array
    $stmt = $pdo->query("
        SELECT o.*, c.name as client_name,
               oi.description AS description,
               COALESCE(oi.subtotal, o.total) as total_amount,
               i.status as invoice_status,
               i.id as invoice_id,
               (SELECT COALESCE(SUM(amount), 0) FROM payments p WHERE p.invoice_id = i.id) as total_paid,
               (COALESCE(i.total, o.total) - (SELECT COALESCE(SUM(amount), 0) FROM payments p WHERE p.invoice_id = i.id)) as remaining
        FROM orders o
        LEFT JOIN clients c ON o.client_id = c.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN invoices i ON o.id = i.order_id
        ORDER BY o.created_at DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPayments($pdo) {
    $stmt = $pdo->query("
        SELECT p.*, c.name as client_name, i.order_id as order_id
        FROM payments p
        LEFT JOIN invoices i ON p.invoice_id = i.id
        LEFT JOIN clients c ON p.client_id = c.id
        ORDER BY p.payment_date DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSuppliers($pdo) {
    $stmt = $pdo->query("SELECT * FROM stock ORDER BY updated_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getProducts($pdo) {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get statistics
function getStats($pdo) {
    $stats = [];
    
    // Clients count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM clients");
    $stats['clients'] = $stmt->fetch()['count'];
    
    // Orders count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders");
    $stats['orders'] = $stmt->fetch()['count'];
    
    // Suppliers count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM stock");
    $stats['suppliers'] = $stmt->fetch()['count'];
    
    // Total revenue (from orders)
    $stmt = $pdo->query("SELECT SUM(total) as revenue FROM orders WHERE status != 'cancelled'");
    $result = $stmt->fetch();
    $stats['revenue'] = $result['revenue'] ?: 0;
    
    // Total paid (from payments)
    $stmt = $pdo->query("SELECT SUM(amount) as total_paid FROM payments");
    $result = $stmt->fetch();
    $stats['total_paid'] = $result['total_paid'] ?: 0;
    
    return $stats;
}

// Helper functions for display
function getStatusClass($status) {
    $statusMap = [
        'pending' => 'status-pending',
        'in_production' => 'status-in-progress',
        'ready' => 'status-in-progress',
        'delivered' => 'status-completed',
        'cancelled' => 'status-cancelled',
        'paid' => 'status-completed',
        'unpaid' => 'status-pending',
        'partial' => 'status-in-progress'
    ];
    return $statusMap[$status] ?? 'status-pending';
}

function getStatusText($status) {
    $statusMap = [
        'pending' => 'En attente',
        'in_production' => 'En production',
        'ready' => 'Prêt',
        'delivered' => 'Livré',
        'cancelled' => 'Annulé',
        'paid' => 'Payé',
        'unpaid' => 'Impayé',
        'partial' => 'Partiel'
    ];
    return $statusMap[$status] ?? 'En attente';
}

// Fetch all data
$clients = getClients($pdo);
$orders = getOrders($pdo);
$payments = getPayments($pdo);
$suppliers = getSuppliers($pdo);
$products = getProducts($pdo);
$stats = getStats($pdo);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimerie Admin - Gestion Complète</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Le CSS reste exactement le même que dans la version précédente */
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

        .sidebar-header img.logo {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid var(--secondary);
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

        /* Tabs */
        .tabs {
            display: flex;
            background: white;
            border-radius: 10px;
            padding: 5px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            flex-wrap: wrap;
        }

        .tab {
            padding: 12px 25px;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 500;
            flex: 1;
            text-align: center;
            min-width: 120px;
        }

        .tab.active {
            background: var(--secondary);
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .card-header h3 {
            font-size: 1.2rem;
            color: var(--primary);
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--gray);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
            outline: none;
        }

        .form-control:focus {
            border-color: var(--secondary);
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            color: var(--gray);
            font-weight: 500;
        }

        tr:hover {
            background: var(--light);
        }

        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-in-progress {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .action-btn {
            width: 35px;
            height: 35px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            background: var(--light);
            color: var(--gray);
        }

        .action-btn:hover {
            background: var(--secondary);
            color: white;
        }

        .action-btn.delete:hover {
            background: var(--danger);
        }

        .action-btn.info:hover {
            background: var(--info);
        }

        .payment {
            background: #f8f9fa;
            border-left: 4px solid var(--success);
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 4px;
        }

        .payment.unpaid {
            border-left-color: var(--danger);
        }

        .payment.partial {
            border-left-color: var(--warning);
        }

        /* Modal */
        .modal {
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

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 1.3rem;
            color: var(--primary);
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        /* Payment Section */
        .payment-section {
            background: var(--light);
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
        }

        .payment-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }

        .payment-item:last-child {
            border-bottom: none;
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
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

        .btn-info {
            background: var(--info);
            color: white;
        }

        .btn-info:hover {
            background: #138496;
        }

        .btn-outline {
            background: transparent;
            color: var(--secondary);
            border: 1px solid var(--secondary);
        }

        .btn-outline:hover {
            background: var(--secondary);
            color: white;
        }

        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-info h3 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .stat-info p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .card-1 .stat-icon {
            background: var(--secondary);
        }

        .card-2 .stat-icon {
            background: var(--success);
        }

        .card-3 .stat-icon {
            background: var(--warning);
        }

        .card-4 .stat-icon {
            background: var(--danger);
        }

        .card-5 .stat-icon {
            background: var(--info);
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
            
            .header {
                padding: 15px 20px;
            }
            
            .content {
                padding: 20px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .stats-cards {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 576px) {
            .search-bar {
                display: none;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                flex: none;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
        }
        
        /* Amount Styles */
        .amount-paid {
            color: var(--success);
            font-weight: 600;
        }
        
        .amount-remaining {
            color: var(--danger);
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <img src="https://via.placeholder.com/50x50/3498db/ffffff?text=IP" alt="Logo" class="logo">
            <h2>Imprimerie Pro</h2>
        </div>
        <div class="sidebar-menu">
            <ul>
                <li><i class="fas fa-home"></i> <span>Tableau de Bord</span></li>
                <li class="active"><i class="fas fa-users"></i> <span>Clients</span></li>
                <li><i class="fas fa-shopping-cart"></i> <span>Commandes</span></li>
                <li><i class="fas fa-money-bill-wave"></i> <span>Versements</span></li>
                <li><i class="fas fa-truck"></i> <span>Fournisseurs</span></li>
                <li><i class="fas fa-box"></i> <span>Produits</span></li>
                <li><i class="fas fa-chart-bar"></i> <span>Rapports</span></li>
                <li><i class="fas fa-cog"></i> <span>Paramètres</span></li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <h1>Gestion Imprimerie</h1>
            </div>
            <div class="header-right">
                <div class="search-bar">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Rechercher..." id="globalSearch">
                </div>
                <div class="user-profile">
                    <img src="" alt="Admin">
                    <span>Admin</span>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Stats Overview -->
            <div class="stats-cards">
                <div class="stat-card card-1">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="clientsCount"><?php echo $stats['clients']; ?></h3>
                        <p>Clients</p>
                    </div>
                </div>
                <div class="stat-card card-2">
                    <div class="stat-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="ordersCount"><?php echo $stats['orders']; ?></h3>
                        <p>Commandes</p>
                    </div>
                </div>
                <div class="stat-card card-3">
                    <div class="stat-icon">
                        <i class="fas fa-euro-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="revenueCount"><?php echo number_format($stats['revenue'], 2); ?> DA</h3>
                        <p>Chiffre d'Affaires</p>
                    </div>
                </div>
                <div class="stat-card card-4">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="paidCount"><?php echo number_format($stats['total_paid'], 2); ?> DA</h3>
                        <p>Total Payé</p>
                    </div>
                </div>
            </div>

            <!-- Page Header -->
            <div class="card">
                <div class="card-header">
                    <h3>Gestion Complète - Clients, Commandes et Versements</h3>
                    <button class="btn btn-primary" id="quickAddBtn">
                        <i class="fas fa-plus"></i> Ajout Rapide
                    </button>
                </div>
                <p>Gérez l'ensemble de votre activité d'imprimerie en un seul endroit</p>
            </div>

            <!-- Tabs -->
            <div class="tabs">
                <div class="tab active" data-tab="clients">Clients</div>
                <div class="tab" data-tab="orders">Commandes</div>
                <div class="tab" data-tab="payments">Versements</div>
                <div class="tab" data-tab="suppliers">Fournisseurs</div>
            </div>

            <!-- Clients Tab -->
            <div class="tab-content active" id="clients-tab">
                <div class="card">
                    <div class="card-header">
                        <h3>Liste des Clients</h3>
                        <button class="btn btn-primary" id="addNewClientBtn">
                            <i class="fas fa-plus"></i> Ajouter Client
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table id="clientsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th>Email</th>
                                    <th>Téléphone</th>
                                    <th>Adresse</th>
                                    <th>Créé le</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clients as $client): ?>
                                <tr>
                                    <td><?php echo $client['id']; ?></td>
                                    <td><?php echo htmlspecialchars($client['name']); ?></td>
                                    <td><?php echo htmlspecialchars($client['email']); ?></td>
                                    <td><?php echo htmlspecialchars($client['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($client['address']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($client['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <div class="action-btn edit-client" data-id="<?php echo $client['id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </div>
                                            <div class="action-btn delete delete-client" data-id="<?php echo $client['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Orders Tab -->
            <div class="tab-content" id="orders-tab">
                <div class="card">
                    <div class="card-header">
                        <h3>Liste des Commandes</h3>
                        <button class="btn btn-primary" id="addNewOrderBtn">
                            <i class="fas fa-plus"></i> Nouvelle Commande
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table id="ordersTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th>Description</th>
                                    <th>Statut</th>
                                    <th>Date limite</th>
                                    <th>Total</th>
                                    <th>Payé</th>
                                    <th>Reste</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>CMD-<?php echo str_pad($order['id'], 3, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo htmlspecialchars($order['client_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['description'] ?? 'N/A'); ?></td>
                                    <td><span class="status <?php echo getStatusClass($order['status']); ?>"><?php echo getStatusText($order['status']); ?></span></td>
                                    <td><?php echo $order['deadline'] ? date('d/m/Y', strtotime($order['deadline'])) : 'N/A'; ?></td>
                                    <td><strong><?php echo number_format($order['total_amount'] ?? $order['total'], 2); ?> DA</strong></td>
                                    <td><span class="amount-paid"><?php echo number_format($order['total_paid'] ?? 0, 2); ?> DA</span></td>
                                    <td><span class="amount-remaining"><?php echo number_format($order['remaining'] ?? $order['total'], 2); ?> DA</span></td>
                                    <td>
                                        <div class="action-buttons">
                                            <div class="action-btn view-order" data-id="<?php echo $order['id']; ?>" title="Voir détails">
                                                <i class="fas fa-eye"></i>
                                            </div>
                                            <div class="action-btn add-payment" data-id="<?php echo $order['id']; ?>" title="Ajouter versement">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </div>
                                            <div class="action-btn edit-order" data-id="<?php echo $order['id']; ?>" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </div>
                                            <div class="action-btn delete delete-order" data-id="<?php echo $order['id']; ?>" title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Payments Tab -->
            <div class="tab-content" id="payments-tab">
                <div class="card">
                    <div class="card-header">
                        <h3>Historique des Versements</h3>
                        <button class="btn btn-primary" id="addNewPaymentBtn">
                            <i class="fas fa-plus"></i> Nouveau Versement
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table id="paymentsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Client</th>
                                    <th>Commande</th>
                                    <th>Montant</th>
                                    <th>Date</th>
                                    <th>Méthode</th>
                                    <th>Référence</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo $payment['id']; ?></td>
                                    <td><?php echo htmlspecialchars($payment['client_name']); ?></td>
                                    <td><?php echo $payment['order_id'] ? 'CMD-' . str_pad($payment['order_id'], 3, '0', STR_PAD_LEFT) : 'N/A'; ?></td>
                                    <td><strong><?php echo number_format($payment['amount'], 2); ?> DA</strong></td>
                                    <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_method']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['reference']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <div class="action-btn edit-payment" data-id="<?php echo $payment['id']; ?>" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </div>
                                            <div class="action-btn delete delete-payment" data-id="<?php echo $payment['id']; ?>" title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Suppliers Tab -->
            <div class="tab-content" id="suppliers-tab">
                <div class="card">
                    <div class="card-header">
                        <h3>Liste des Fournisseurs/Stock</h3>
                        <button class="btn btn-primary" id="addNewSupplierBtn">
                            <i class="fas fa-plus"></i> Nouveau Fournisseur
                        </button>
                    </div>
                    <div class="table-responsive">
                        <table id="suppliersTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nom</th>
                                    <th>Quantité</th>
                                    <th>Unité</th>
                                    <th>Limite basse</th>
                                    <th>Mis à jour</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($suppliers as $supplier): ?>
                                <tr>
                                    <td><?php echo $supplier['id']; ?></td>
                                    <td><?php echo htmlspecialchars($supplier['item_name']); ?></td>
                                    <td><span class="<?php echo ($supplier['quantity'] <= $supplier['low_stock_limit']) ? 'status status-pending' : ''; ?>"><?php echo $supplier['quantity']; ?></span></td>
                                    <td><?php echo htmlspecialchars($supplier['unit']); ?></td>
                                    <td><?php echo $supplier['low_stock_limit']; ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($supplier['updated_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <div class="action-btn edit-supplier" data-id="<?php echo $supplier['id']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </div>
                                            <div class="action-btn delete delete-supplier" data-id="<?php echo $supplier['id']; ?>">
                                                <i class="fas fa-trash"></i>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Client Modal -->
    <div class="modal" id="clientModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="clientModalTitle">Ajouter un Client</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="clientForm">
                    <input type="hidden" id="clientId" name="id">
                    <input type="hidden" name="action" id="clientAction" value="add_client">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="clientName">Nom Complet *</label>
                            <input type="text" id="clientName" name="name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="clientEmail">Email</label>
                            <input type="email" id="clientEmail" name="email" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="clientPhone">Téléphone *</label>
                            <input type="text" id="clientPhone" name="phone" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="clientAddress">Adresse</label>
                            <textarea id="clientAddress" name="address" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" id="cancelClientBtn">Annuler</button>
                <button class="btn btn-primary" id="saveClientBtn">Enregistrer</button>
            </div>
        </div>
    </div>

    <!-- Order Modal -->
    <div class="modal" id="orderModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="orderModalTitle">Nouvelle Commande</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="orderForm">
                    <input type="hidden" id="orderId" name="id">
                    <input type="hidden" name="action" id="orderAction" value="add_order">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="orderClient">Client *</label>
                            <select id="orderClient" name="client_id" class="form-control" required>
                                <option value="">Sélectionner un client</option>
                                <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id']; ?>"><?php echo htmlspecialchars($client['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="orderStatus">Statut *</label>
                            <select id="orderStatus" name="status" class="form-control" required>
                                <option value="pending">En attente</option>
                                <option value="in_production">En production</option>
                                <option value="ready">Prêt</option>
                                <option value="delivered">Livré</option>
                                <option value="cancelled">Annulé</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="orderDeadline">Date limite</label>
                            <input type="date" id="orderDeadline" name="deadline" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="orderDescription">Description *</label>
                            <input type="text" id="orderDescription" name="description" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="orderQuantity">Quantité *</label>
                            <input type="number" id="orderQuantity" name="quantity" class="form-control" required min="1" value="1">
                        </div>
                        <div class="form-group">
                            <label for="orderPrice">Prix unitaire (DA) *</label>
                            <input type="number" id="orderPrice" name="price" class="form-control" required min="0" step="0.01">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" id="cancelOrderBtn">Annuler</button>
                <button class="btn btn-primary" id="saveOrderBtn">Enregistrer</button>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div class="modal" id="paymentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="paymentModalTitle">Nouveau Versement</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <input type="hidden" id="paymentId" name="id">
                    <input type="hidden" name="action" id="paymentAction" value="add_payment">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="paymentOrder">Commande *</label>
                            <select id="paymentOrder" name="order_id" class="form-control" required>
                                <option value="">Sélectionner une commande</option>
                                <?php foreach ($orders as $order): ?>
                                <?php 
                                    $total = $order['total_amount'] ?? $order['total'];
                                    $totalPaid = $order['total_paid'] ?? 0;
                                ?>
                                <option value="<?php echo $order['id']; ?>" data-total="<?php echo htmlspecialchars($total); ?>" data-paid="<?php echo htmlspecialchars($totalPaid); ?>">
                                    CMD-<?php echo str_pad($order['id'], 3, '0', STR_PAD_LEFT); ?> - <?php echo htmlspecialchars($order['client_name']); ?> (Total: <?php echo number_format($total, 2); ?> DA, Payé: <?php echo number_format($totalPaid, 2); ?> DA)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="paymentAmount">Montant (DA) *</label>
                            <input type="number" id="paymentAmount" name="amount" class="form-control" required min="0" step="0.01">
                            <small id="remainingAmount">Reste à payer: <span id="remainingValue">0.00</span> DA</small>
                        </div>
                        <div class="form-group">
                            <label for="paymentDate">Date *</label>
                            <input type="date" id="paymentDate" name="payment_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="paymentMethod">Méthode de paiement</label>
                            <select id="paymentMethod" name="payment_method" class="form-control">
                                <option value="cash">Espèces</option>
                                <option value="check">Chèque</option>
                                <option value="bank_transfer">Virement bancaire</option>
                                <option value="card">Carte bancaire</option>
                                <option value="other">Autre</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="paymentReference">Référence</label>
                            <input type="text" id="paymentReference" name="reference" class="form-control" placeholder="N° chèque, référence virement...">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="paymentNotes">Notes</label>
                        <textarea id="paymentNotes" name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" id="cancelPaymentBtn">Annuler</button>
                <button class="btn btn-primary" id="savePaymentBtn">Enregistrer</button>
            </div>
        </div>
    </div>

    <!-- Supplier Modal -->
    <div class="modal" id="supplierModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="supplierModalTitle">Nouveau Fournisseur/Stock</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <form id="supplierForm">
                    <input type="hidden" id="supplierId" name="id">
                    <input type="hidden" name="action" id="supplierAction" value="add_supplier">
                    <div class="form-group">
                        <label for="supplierName">Nom de l'article *</label>
                            <input type="text" id="supplierName" name="name" class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="supplierQuantity">Quantité</label>
                            <input type="number" id="supplierQuantity" name="quantity" class="form-control" min="0" value="0">
                        </div>
                        <div class="form-group">
                            <label for="supplierUnit">Unité</label>
                            <input type="text" id="supplierUnit" name="unit" class="form-control" value="unité">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="supplierLimit">Limite de stock basse</label>
                        <input type="number" id="supplierLimit" name="low_stock_limit" class="form-control" min="0" value="10">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" id="cancelSupplierBtn">Annuler</button>
                <button class="btn btn-primary" id="saveSupplierBtn">Enregistrer</button>
            </div>
        </div>
    </div>

    <!-- Order Details Modal -->
    <div class="modal" id="orderDetailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Détails de la Commande</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div id="orderDetailsContent">
                    <!-- Order details will be populated by JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" id="addPaymentFromDetailsBtn">Ajouter un versement</button>
                <button class="btn btn-outline" id="closeDetailsBtn">Fermer</button>
            </div>
        </div>
    </div>

    <script>
        // DOM Elements
        const tabs = document.querySelectorAll('.tab');
        const tabContents = document.querySelectorAll('.tab-content');
        const modals = document.querySelectorAll('.modal');
        const modalCloses = document.querySelectorAll('.modal-close');

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
            // Set today's date for deadline fields
            const today = new Date().toISOString().split('T')[0];
            const orderDeadlineElem = document.getElementById('orderDeadline');
            if (orderDeadlineElem) orderDeadlineElem.min = today;
            const paymentDateElem = document.getElementById('paymentDate');
            if (paymentDateElem) paymentDateElem.value = today;
            
            // Setup payment order change event
            const paymentOrderElem = document.getElementById('paymentOrder');
            if (paymentOrderElem) paymentOrderElem.addEventListener('change', updateRemainingAmount);
            const paymentAmountElem = document.getElementById('paymentAmount');
            if (paymentAmountElem) paymentAmountElem.addEventListener('input', validatePaymentAmount);
        });

        // Event Listeners
        function setupEventListeners() {
            // Tab switching
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const tabId = tab.getAttribute('data-tab');
                    
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    
                    tabContents.forEach(content => {
                        content.classList.remove('active');
                        if (content.id === `${tabId}-tab`) {
                            content.classList.add('active');
                        }
                    });
                });
            });

            // Modal open/close
            document.querySelectorAll('.modal-close, .btn-outline').forEach(btn => {
                btn.addEventListener('click', () => {
                    modals.forEach(modal => modal.classList.remove('active'));
                });
            });

            // Add buttons
            const addClientBtn = document.getElementById('addNewClientBtn');
            if (addClientBtn) addClientBtn.addEventListener('click', openClientModal);
            const addOrderBtn = document.getElementById('addNewOrderBtn');
            if (addOrderBtn) addOrderBtn.addEventListener('click', openOrderModal);
            const addPaymentBtn = document.getElementById('addNewPaymentBtn');
            if (addPaymentBtn) addPaymentBtn.addEventListener('click', openPaymentModal);
            const addSupplierBtn = document.getElementById('addNewSupplierBtn');
            if (addSupplierBtn) addSupplierBtn.addEventListener('click', openSupplierModal);
            const quickAddBtn = document.getElementById('quickAddBtn');
            if (quickAddBtn) quickAddBtn.addEventListener('click', openClientModal);
            
            // Add payment from order details
            const addPaymentFromDetails = document.getElementById('addPaymentFromDetailsBtn');
            if (addPaymentFromDetails) {
                addPaymentFromDetails.addEventListener('click', function() {
                    const orderId = this.getAttribute('data-order-id');
                    if (orderId) {
                        openPaymentModalForOrder(orderId);
                    }
                });
            }

            // Save buttons
            const saveClientBtn = document.getElementById('saveClientBtn');
            if (saveClientBtn) saveClientBtn.addEventListener('click', saveClient);
            const saveOrderBtn = document.getElementById('saveOrderBtn');
            if (saveOrderBtn) saveOrderBtn.addEventListener('click', saveOrder);
            const savePaymentBtn = document.getElementById('savePaymentBtn');
            if (savePaymentBtn) savePaymentBtn.addEventListener('click', savePayment);
            const saveSupplierBtn = document.getElementById('saveSupplierBtn');
            if (saveSupplierBtn) saveSupplierBtn.addEventListener('click', saveSupplier);

            // View details and edit/delete actions
            document.addEventListener('click', function(e) {
                // View order details
                if (e.target.closest('.view-order')) {
                    const orderId = e.target.closest('.view-order').getAttribute('data-id');
                    viewOrderDetails(orderId);
                }
                
                // Add payment to order
                if (e.target.closest('.add-payment')) {
                    const orderId = e.target.closest('.add-payment').getAttribute('data-id');
                    openPaymentModalForOrder(orderId);
                }
                
                // Edit client
                if (e.target.closest('.edit-client')) {
                    const clientId = e.target.closest('.edit-client').getAttribute('data-id');
                    editClient(clientId);
                }
                
                // Edit order
                if (e.target.closest('.edit-order')) {
                    const orderId = e.target.closest('.edit-order').getAttribute('data-id');
                    editOrder(orderId);
                }
                
                // Edit payment
                if (e.target.closest('.edit-payment')) {
                    const paymentId = e.target.closest('.edit-payment').getAttribute('data-id');
                    editPayment(paymentId);
                }
                
                // Edit supplier
                if (e.target.closest('.edit-supplier')) {
                    const supplierId = e.target.closest('.edit-supplier').getAttribute('data-id');
                    editSupplier(supplierId);
                }
                
                // Delete actions
                if (e.target.closest('.delete-client')) {
                    const clientId = e.target.closest('.delete-client').getAttribute('data-id');
                    deleteItem('client', clientId);
                }
                
                if (e.target.closest('.delete-order')) {
                    const orderId = e.target.closest('.delete-order').getAttribute('data-id');
                    deleteItem('order', orderId);
                }
                
                if (e.target.closest('.delete-payment')) {
                    const paymentId = e.target.closest('.delete-payment').getAttribute('data-id');
                    deleteItem('payment', paymentId);
                }
                
                if (e.target.closest('.delete-supplier')) {
                    const supplierId = e.target.closest('.delete-supplier').getAttribute('data-id');
                    deleteItem('supplier', supplierId);
                }
            });

            // Close details modal
            const closeDetailsBtn = document.getElementById('closeDetailsBtn');
            if (closeDetailsBtn) closeDetailsBtn.addEventListener('click', () => {
                document.getElementById('orderDetailsModal').classList.remove('active');
            });

            // Global search
            const globalSearch = document.getElementById('globalSearch');
            if (globalSearch) {
                globalSearch.addEventListener('input', function(e) {
                    const searchTerm = e.target.value.toLowerCase();
                    const activeTab = document.querySelector('.tab.active').getAttribute('data-tab');
                    
                    if (activeTab === 'clients') {
                        searchTable('clientsTable', searchTerm);
                    } else if (activeTab === 'orders') {
                        searchTable('ordersTable', searchTerm);
                    } else if (activeTab === 'payments') {
                        searchTable('paymentsTable', searchTerm);
                    } else if (activeTab === 'suppliers') {
                        searchTable('suppliersTable', searchTerm);
                    }
                });
            }
        }

        function searchTable(tableId, searchTerm) {
            const table = document.getElementById(tableId);
            if (!table) return;
            const tbody = table.getElementsByTagName('tbody')[0];
            if (!tbody) return;
            const rows = tbody.getElementsByTagName('tr');
            
            for (let row of rows) {
                let rowText = row.textContent.toLowerCase();
                row.style.display = rowText.includes(searchTerm) ? '' : 'none';
            }
        }

        // Modal functions
        function openClientModal() {
            document.getElementById('clientModalTitle').textContent = 'Ajouter un Client';
            document.getElementById('clientForm').reset();
            document.getElementById('clientId').value = '';
            document.getElementById('clientAction').value = 'add_client';
            document.getElementById('clientModal').classList.add('active');
        }

        function openOrderModal() {
            document.getElementById('orderModalTitle').textContent = 'Nouvelle Commande';
            document.getElementById('orderForm').reset();
            document.getElementById('orderId').value = '';
            document.getElementById('orderAction').value = 'add_order';
            const today = new Date().toISOString().split('T')[0];
            const orderDeadline = document.getElementById('orderDeadline');
            if (orderDeadline) orderDeadline.value = today;
            document.getElementById('orderModal').classList.add('active');
        }

        function openPaymentModal() {
            document.getElementById('paymentModalTitle').textContent = 'Nouveau Versement';
            document.getElementById('paymentForm').reset();
            document.getElementById('paymentId').value = '';
            document.getElementById('paymentAction').value = 'add_payment';
            const today = new Date().toISOString().split('T')[0];
            const paymentDate = document.getElementById('paymentDate');
            if (paymentDate) paymentDate.value = today;
            document.getElementById('remainingValue').textContent = '0.00';
            document.getElementById('paymentModal').classList.add('active');
        }
        
        function openPaymentModalForOrder(orderId) {
            document.getElementById('paymentModalTitle').textContent = 'Nouveau Versement';
            document.getElementById('paymentForm').reset();
            document.getElementById('paymentId').value = '';
            document.getElementById('paymentAction').value = 'add_payment';
            const today = new Date().toISOString().split('T')[0];
            const paymentDate = document.getElementById('paymentDate');
            if (paymentDate) paymentDate.value = today;
            
            // Set the order
            const orderSelect = document.getElementById('paymentOrder');
            if (orderSelect) {
                const option = orderSelect.querySelector(`option[value="${orderId}"]`);
                if (option) {
                    orderSelect.value = orderId;
                    updateRemainingAmount();
                }
            }
            
            document.getElementById('paymentModal').classList.add('active');
        }

        function openSupplierModal() {
            document.getElementById('supplierModalTitle').textContent = 'Nouveau Fournisseur/Stock';
            document.getElementById('supplierForm').reset();
            document.getElementById('supplierId').value = '';
            document.getElementById('supplierAction').value = 'add_supplier';
            document.getElementById('supplierModal').classList.add('active');
        }

        // Edit functions
        function editClient(clientId) {
            const row = document.querySelector(`.edit-client[data-id="${clientId}"]`).closest('tr');
            document.getElementById('clientModalTitle').textContent = 'Modifier le Client';
            document.getElementById('clientId').value = clientId;
            document.getElementById('clientName').value = row.cells[1].textContent.trim();
            document.getElementById('clientEmail').value = row.cells[2].textContent.trim();
            document.getElementById('clientPhone').value = row.cells[3].textContent.trim();
            document.getElementById('clientAddress').value = row.cells[4].textContent.trim();
            document.getElementById('clientAction').value = 'update_client';
            document.getElementById('clientModal').classList.add('active');
        }

        function editOrder(orderId) {
            const row = document.querySelector(`.edit-order[data-id="${orderId}"]`).closest('tr');
            document.getElementById('orderModalTitle').textContent = 'Modifier la Commande';
            document.getElementById('orderId').value = orderId;
            
            // Extract data from table row
            const clientName = row.cells[1].textContent.trim();
            const description = row.cells[2].textContent.trim();
            const statusText = row.cells[3].querySelector('.status').textContent.trim();
            const deadline = row.cells[4].textContent.trim();
            // Robust number parsing: remove non-number characters
            const totalText = row.cells[5].textContent.trim();
            const total = parseFloat(totalText.replace(/[^0-9\.-]+/g,"")) || 0;
            
            // Set form values
            document.getElementById('orderClient').value = findClientIdByName(clientName);
            document.getElementById('orderDescription').value = description === 'N/A' ? '' : description;
            document.getElementById('orderStatus').value = getStatusValueFromText(statusText);
            
            if (deadline !== 'N/A') {
                const deadlineDate = deadline.split('/').reverse().join('-');
                document.getElementById('orderDeadline').value = deadlineDate;
            } else {
                document.getElementById('orderDeadline').value = '';
            }
            
            // For now, set default quantity and price
            // In a real app, you would fetch the full order details
            document.getElementById('orderQuantity').value = 1;
            document.getElementById('orderPrice').value = total;
            
            document.getElementById('orderAction').value = 'update_order';
            document.getElementById('orderModal').classList.add('active');
        }

        async function editPayment(paymentId) {
            try {
                // In a real app, you would fetch payment details from server
                // For now, we'll extract from table row
                const row = document.querySelector(`.edit-payment[data-id="${paymentId}"]`).closest('tr');
                document.getElementById('paymentModalTitle').textContent = 'Modifier le Versement';
                document.getElementById('paymentId').value = paymentId;
                
                // Extract data from table row
                const orderRef = row.cells[2].textContent.trim();
                const amountText = row.cells[3].textContent.trim();
                const amount = parseFloat(amountText.replace(/[^0-9\.-]+/g,"")) || 0;
                const date = row.cells[4].textContent.trim();
                const method = row.cells[5].textContent.trim();
                const reference = row.cells[6].textContent.trim();
                
                // Set form values
                if (orderRef !== 'N/A') {
                    const orderId = orderRef.replace('CMD-', '').trim();
                    document.getElementById('paymentOrder').value = parseInt(orderId);
                    updateRemainingAmount();
                }
                
                document.getElementById('paymentAmount').value = amount;
                
                if (date !== 'N/A') {
                    const paymentDate = date.split('/').reverse().join('-');
                    document.getElementById('paymentDate').value = paymentDate;
                }
                
                document.getElementById('paymentMethod').value = getPaymentMethodValue(method);
                document.getElementById('paymentReference').value = reference;
                
                document.getElementById('paymentAction').value = 'update_payment';
                document.getElementById('paymentModal').classList.add('active');
            } catch (error) {
                console.error('Error editing payment:', error);
                alert('Erreur lors du chargement du versement');
            }
        }

        function editSupplier(supplierId) {
            const row = document.querySelector(`.edit-supplier[data-id="${supplierId}"]`).closest('tr');
            document.getElementById('supplierModalTitle').textContent = 'Modifier le Fournisseur/Stock';
            document.getElementById('supplierId').value = supplierId;
            document.getElementById('supplierName').value = row.cells[1].textContent.trim();
            document.getElementById('supplierQuantity').value = parseInt(row.cells[2].textContent) || 0;
            document.getElementById('supplierUnit').value = row.cells[3].textContent.trim();
            document.getElementById('supplierLimit').value = parseInt(row.cells[4].textContent) || 0;
            document.getElementById('supplierAction').value = 'update_supplier';
            document.getElementById('supplierModal').classList.add('active');
        }

        // Helper functions
        function findClientIdByName(clientName) {
            const select = document.getElementById('orderClient');
            if (!select) return '';
            for (let option of select.options) {
                if (option.text === clientName) {
                    return option.value;
                }
            }
            return '';
        }

        function getStatusValueFromText(statusText) {
            const statusMap = {
                'En attente': 'pending',
                'En production': 'in_production',
                'Prêt': 'ready',
                'Livré': 'delivered',
                'Annulé': 'cancelled'
            };
            return statusMap[statusText] || 'pending';
        }
        
        function getPaymentMethodValue(methodText) {
            const methodMap = {
                'Espèces': 'cash',
                'Chèque': 'check',
                'Virement bancaire': 'bank_transfer',
                'Carte bancaire': 'card',
                'Autre': 'other'
            };
            return methodMap[methodText] || 'cash';
        }

        // View order details
        async function viewOrderDetails(orderId) {
            try {
                const response = await fetch(`?get_order_details=${orderId}`);
                const order = await response.json();
                
                if (order.error) {
                    alert(order.error);
                    return;
                }
                
                let detailsHTML = `
                    <div class="form-group">
                        <h4>Informations de la commande</h4>
                        <p><strong>Référence:</strong> CMD-${String(order.id).padStart(3, '0')}</p>
                        <p><strong>Client:</strong> ${order.client_name}</p>
                        <p><strong>Email:</strong> ${order.client_email || 'N/A'}</p>
                        <p><strong>Téléphone:</strong> ${order.client_phone || 'N/A'}</p>
                        <p><strong>Description:</strong> ${order.description || 'N/A'}</p>
                        <p><strong>Statut:</strong> ${getStatusText(order.status)}</p>
                        <p><strong>Date limite:</strong> ${formatDate(order.deadline)}</p>
                        <p><strong>Quantité:</strong> ${order.quantity || 'N/A'}</p>
                        <p><strong>Prix unitaire:</strong> ${order.price ? parseFloat(order.price).toFixed(2) + ' DA' : 'N/A'}</p>
                        <p><strong>Sous-total:</strong> ${order.subtotal ? parseFloat(order.subtotal).toFixed(2) + ' DA' : 'N/A'}</p>
                        <p><strong>Total commande:</strong> <strong>${parseFloat(order.total).toFixed(2)} DA</strong></p>
                        <p><strong>Total payé:</strong> <span class="amount-paid">${parseFloat(order.total_paid || 0).toFixed(2)} DA</span></p>
                        <p><strong>Reste à payer:</strong> <span class="amount-remaining">${parseFloat(order.remaining || order.total).toFixed(2)} DA</span></p>
                        <p><strong>Créé le:</strong> ${formatDate(order.created_at)}</p>
                    </div>
                `;
                
                // Add payments section
                if (order.payments && order.payments.length > 0) {
                    detailsHTML += `
                        <div class="payment-section">
                            <h4>Historique des versements</h4>
                            ${order.payments.map(payment => `
                                <div class="payment">
                                    <p><strong>Montant:</strong> ${parseFloat(payment.amount).toFixed(2)} DA</p>
                                    <p><strong>Date:</strong> ${formatDate(payment.payment_date)}</p>
                                    <p><strong>Méthode:</strong> ${payment.payment_method}</p>
                                    ${payment.reference ? `<p><strong>Référence:</strong> ${payment.reference}</p>` : ''}
                                    ${payment.notes ? `<p><strong>Notes:</strong> ${payment.notes}</p>` : ''}
                                    <p><small>Créé le: ${formatDate(payment.created_at)}</small></p>
                                </div>
                            `).join('')}
                        </div>
                    `;
                } else {
                    detailsHTML += `
                        <div class="payment-section">
                            <h4>Historique des versements</h4>
                            <p>Aucun versement enregistré pour cette commande.</p>
                        </div>
                    `;
                }

                document.getElementById('orderDetailsContent').innerHTML = detailsHTML;
                document.getElementById('addPaymentFromDetailsBtn').setAttribute('data-order-id', orderId);
                document.getElementById('orderDetailsModal').classList.add('active');
            } catch (error) {
                console.error('Error fetching order details:', error);
                alert('Erreur lors du chargement des détails de la commande');
            }
        }

        function formatDate(dateString) {
            if (!dateString || dateString === '0000-00-00') return 'N/A';
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR');
        }

        function getStatusText(status) {
            const statusMap = {
                'pending': 'En attente',
                'in_production': 'En production',
                'ready': 'Prêt',
                'delivered': 'Livré',
                'cancelled': 'Annulé'
            };
            return statusMap[status] || 'En attente';
        }
        
        // Payment validation
        function updateRemainingAmount() {
            const orderSelect = document.getElementById('paymentOrder');
            if (!orderSelect) return;
            const selectedOption = orderSelect.options[orderSelect.selectedIndex];
            
            if (selectedOption && selectedOption.value) {
                const total = parseFloat(selectedOption.getAttribute('data-total')) || 0;
                const paid = parseFloat(selectedOption.getAttribute('data-paid')) || 0;
                const remaining = total - paid;
                
                document.getElementById('remainingValue').textContent = remaining.toFixed(2);
            } else {
                document.getElementById('remainingValue').textContent = '0.00';
            }
        }
        
        function validatePaymentAmount() {
            const amount = parseFloat(document.getElementById('paymentAmount').value) || 0;
            const remaining = parseFloat(document.getElementById('remainingValue').textContent) || 0;
            
            if (amount > remaining) {
                document.getElementById('paymentAmount').setCustomValidity(`Le montant ne peut pas dépasser ${remaining.toFixed(2)} DA`);
            } else {
                document.getElementById('paymentAmount').setCustomValidity('');
            }
        }

        // Save functions using AJAX
        async function saveClient() {
            const form = document.getElementById('clientForm');
            const formData = new FormData(form);
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Erreur: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Erreur lors de l\'enregistrement: ' + error.message);
            }
        }

        async function saveOrder() {
            const form = document.getElementById('orderForm');
            const formData = new FormData(form);
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Erreur: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Erreur lors de l\'enregistrement: ' + error.message);
            }
        }

        async function savePayment() {
            const form = document.getElementById('paymentForm');
            const formData = new FormData(form);
            
            // Validate amount
            const amount = parseFloat(document.getElementById('paymentAmount').value) || 0;
            const remaining = parseFloat(document.getElementById('remainingValue').textContent) || 0;
            
            if (amount > remaining) {
                alert(`Erreur: Le montant ne peut pas dépasser ${remaining.toFixed(2)} DA`);
                return;
            }
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Erreur: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Erreur lors de l\'enregistrement: ' + error.message);
            }
        }

        async function saveSupplier() {
            const form = document.getElementById('supplierForm');
            const formData = new FormData(form);
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Erreur: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Erreur lors de l\'enregistrement: ' + error.message);
            }
        }

        // Delete functions
        async function deleteItem(type, id) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cet élément?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('action', `delete_${type}`);
            formData.append('id', id);
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                if (result.success) {
                    alert(result.message);
                    location.reload();
                } else {
                    alert('Erreur: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Erreur lors de la suppression: ' + error.message);
            }
        }
    </script>
</body>
</html>