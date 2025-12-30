<?php
session_start();

// Simulation de la session admin si non définie (pour les tests)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'admin';
    $_SESSION['full_name'] = 'Administrateur';
    $_SESSION['role'] = 'admin';
}

// Configuration de la base de données
define('DB_HOST', '127.0.0.1:3306');
define('DB_NAME', 'imprimerie');
define('DB_USER', 'root'); 
define('DB_PASS', 'admine'); 

// Connexion à la base de données
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données: " . $e->getMessage());
}

// Fonctions pour gérer les produits
function getProducts($pdo, $search = '') {
    $sql = "SELECT p.*, c.name as category_name, c.color as category_color 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " WHERE p.name LIKE :search OR p.description LIKE :search";
        $params[':search'] = "%$search%";
    }
    
    $sql .= " ORDER BY p.name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getProductById($pdo, $id) {
    $sql = "SELECT p.*, c.name as category_name, c.color as category_color 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

function addProduct($pdo, $data) {
    $sql = "INSERT INTO products (name, description, price, category_id) 
            VALUES (:name, :description, :price, :category_id)";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':name' => $data['name'],
        ':description' => $data['description'] ?? '',
        ':price' => $data['price'],
        ':category_id' => $data['category_id'] ?: null
    ]);
}

function updateProduct($pdo, $id, $data) {
    $sql = "UPDATE products SET 
            name = :name,
            description = :description,
            price = :price,
            category_id = :category_id
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    $data[':id'] = $id;
    return $stmt->execute($data);
}

function deleteProduct($pdo, $id) {
    $sql = "DELETE FROM products WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([':id' => $id]);
}

// Fonctions pour gérer le stock
function getStockItems($pdo, $search = '') {
    $sql = "SELECT * FROM stock";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " WHERE item_name LIKE :search";
        $params[':search'] = "%$search%";
    }
    
    $sql .= " ORDER BY item_name ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getStockById($pdo, $id) {
    $sql = "SELECT * FROM stock WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

function addStockItem($pdo, $data) {
    $sql = "INSERT INTO stock (item_name, quantity, unit, low_stock_limit) 
            VALUES (:item_name, :quantity, :unit, :low_stock_limit)";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':item_name' => $data['item_name'],
        ':quantity' => $data['quantity'],
        ':unit' => $data['unit'],
        ':low_stock_limit' => $data['low_stock_limit']
    ]);
}

function updateStockItem($pdo, $id, $data) {
    $sql = "UPDATE stock SET 
            item_name = :item_name,
            quantity = :quantity,
            unit = :unit,
            low_stock_limit = :low_stock_limit
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    $data[':id'] = $id;
    return $stmt->execute($data);
}

function deleteStockItem($pdo, $id) {
    $sql = "DELETE FROM stock WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([':id' => $id]);
}

function updateStockQuantity($pdo, $id, $quantity) {
    $sql = "UPDATE stock SET quantity = :quantity, updated_at = NOW() WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':quantity' => $quantity,
        ':id' => $id
    ]);
}

function addStockMovement($pdo, $stock_id, $change_type, $quantity, $note = '') {
    $sql = "INSERT INTO stock_history (stock_id, change_type, quantity, note) 
            VALUES (:stock_id, :change_type, :quantity, :note)";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':stock_id' => $stock_id,
        ':change_type' => $change_type,
        ':quantity' => $quantity,
        ':note' => $note
    ]);
}

function getStockHistory($pdo, $filters = []) {
    $sql = "SELECT sh.*, s.item_name 
            FROM stock_history sh 
            JOIN stock s ON sh.stock_id = s.id 
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($filters['stock_id']) && $filters['stock_id'] != 'all') {
        $sql .= " AND sh.stock_id = :stock_id";
        $params[':stock_id'] = $filters['stock_id'];
    }
    
    if (!empty($filters['change_type']) && $filters['change_type'] != 'all') {
        $sql .= " AND sh.change_type = :change_type";
        $params[':change_type'] = $filters['change_type'];
    }
    
    if (!empty($filters['date'])) {
        $sql .= " AND DATE(sh.created_at) = :date";
        $params[':date'] = $filters['date'];
    }
    
    $sql .= " ORDER BY sh.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Fonctions pour les statistiques
function getStockStats($pdo) {
    $stats = [];
    
    // Nombre total de produits
    $sql = "SELECT COUNT(*) as count FROM products";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stats['total_products'] = $stmt->fetch()['count'];
    
    // Articles avec stock bas
    $sql = "SELECT COUNT(*) as count FROM stock WHERE quantity <= low_stock_limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stats['low_stock_count'] = $stmt->fetch()['count'];
    
    // Valeur totale du stock (estimation)
    $sql = "SELECT COUNT(*) as count FROM stock";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stats['total_items'] = $stmt->fetch()['count'];
    
    // Mouvements ce mois
    $sql = "SELECT COUNT(*) as count FROM stock_history 
            WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
            AND YEAR(created_at) = YEAR(CURRENT_DATE())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stats['movements_count'] = $stmt->fetch()['count'];
    
    // Total des entrées ce mois
    $sql = "SELECT SUM(quantity) as total FROM stock_history 
            WHERE change_type = 'add' 
            AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
            AND YEAR(created_at) = YEAR(CURRENT_DATE())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stats['monthly_additions'] = $stmt->fetch()['total'] ?: 0;
    
    // Total des sorties ce mois
    $sql = "SELECT SUM(quantity) as total FROM stock_history 
            WHERE change_type = 'remove' 
            AND MONTH(created_at) = MONTH(CURRENT_DATE()) 
            AND YEAR(created_at) = YEAR(CURRENT_DATE())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $stats['monthly_removals'] = $stmt->fetch()['total'] ?: 0;
    
    return $stats;
}

// Fonctions pour les catégories
function getCategories($pdo) {
    $sql = "SELECT c.*, 
            (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count
            FROM categories c 
            ORDER BY c.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll();
}

function getCategoryById($pdo, $id) {
    $sql = "SELECT * FROM categories WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

function addCategory($pdo, $data) {
    $sql = "INSERT INTO categories (name, description, color, created_at) 
            VALUES (:name, :description, :color, NOW())";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':name' => $data['name'],
        ':description' => $data['description'],
        ':color' => $data['color']
    ]);
}

function updateCategory($pdo, $id, $data) {
    $sql = "UPDATE categories SET 
            name = :name,
            description = :description,
            color = :color
            WHERE id = :id";
    
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([
        ':name' => $data['name'],
        ':description' => $data['description'],
        ':color' => $data['color'],
        ':id' => $id
    ]);
}

function deleteCategory($pdo, $id) {
    // D'abord, mettre à jour les produits pour supprimer la référence à cette catégorie
    $sql = "UPDATE products SET category_id = NULL WHERE category_id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    
    // Ensuite, supprimer la catégorie
    $sql = "DELETE FROM categories WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([':id' => $id]);
}

function getProductsByCategory($pdo, $category_id) {
    $sql = "SELECT COUNT(*) as count FROM products WHERE category_id = :category_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':category_id' => $category_id]);
    return $stmt->fetch()['count'];
}

// Gérer les requêtes POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    // Ajouter un article au stock
    if (isset($_POST['action']) && $_POST['action'] === 'add_stock_item') {
        try {
            $success = addStockItem($pdo, [
                'item_name' => $_POST['item_name'],
                'quantity' => $_POST['quantity'],
                'unit' => $_POST['unit'],
                'low_stock_limit' => $_POST['low_stock_limit']
            ]);
            
            if ($success) {
                $stock_id = $pdo->lastInsertId();
                
                // Ajouter le mouvement initial
                addStockMovement($pdo, $stock_id, 'add', $_POST['quantity'], 'Stock initial');
                
                $response['success'] = true;
                $response['message'] = 'Article ajouté au stock avec succès!';
                $response['data'] = ['id' => $stock_id];
            }
        } catch (PDOException $e) {
            $response['message'] = 'Erreur: ' . $e->getMessage();
        }
    }
    
    // Ajuster le stock
    elseif (isset($_POST['action']) && $_POST['action'] === 'adjust_stock') {
        try {
            $stock_item = getStockById($pdo, $_POST['stock_id']);
            
            if ($stock_item) {
                $new_quantity = $_POST['adjust_type'] === 'add' 
                    ? $stock_item['quantity'] + $_POST['quantity']
                    : $stock_item['quantity'] - $_POST['quantity'];
                
                if ($new_quantity < 0) {
                    $response['message'] = 'Erreur: La quantité ne peut pas être négative!';
                } else {
                    // Mettre à jour le stock
                    updateStockQuantity($pdo, $_POST['stock_id'], $new_quantity);
                    
                    // Ajouter le mouvement
                    addStockMovement(
                        $pdo, 
                        $_POST['stock_id'], 
                        $_POST['adjust_type'], 
                        $_POST['quantity'],
                        $_POST['notes'] . ' (' . $_POST['reason'] . ')'
                    );
                    
                    $response['success'] = true;
                    $response['message'] = 'Stock ajusté avec succès!';
                    $response['data'] = ['new_quantity' => $new_quantity];
                }
            } else {
                $response['message'] = 'Article non trouvé!';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Erreur: ' . $e->getMessage();
        }
    }
    
    // Ajouter un produit
    elseif (isset($_POST['action']) && $_POST['action'] === 'add_product') {
        try {
            $success = addProduct($pdo, [
                'name' => $_POST['name'],
                'description' => $_POST['description'] ?? '',
                'price' => $_POST['price'],
                'category_id' => $_POST['category_id'] ?: null
            ]);
            
            if ($success) {
                $response['success'] = true;
                $response['message'] = 'Produit ajouté avec succès!';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Erreur: ' . $e->getMessage();
        }
    }
    
    // Modifier un article de stock
    elseif (isset($_POST['action']) && $_POST['action'] === 'update_stock_item') {
        try {
            $success = updateStockItem($pdo, $_POST['stock_id'], [
                'item_name' => $_POST['item_name'],
                'quantity' => $_POST['quantity'],
                'unit' => $_POST['unit'],
                'low_stock_limit' => $_POST['low_stock_limit'],
                'id' => $_POST['stock_id']
            ]);
            
            if ($success) {
                $response['success'] = true;
                $response['message'] = 'Article modifié avec succès!';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Erreur: ' . $e->getMessage();
        }
    }
    
    // Supprimer un article de stock
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete_stock_item') {
        try {
            // D'abord supprimer l'historique
            $sql = "DELETE FROM stock_history WHERE stock_id = :stock_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':stock_id' => $_POST['stock_id']]);
            
            // Ensuite supprimer l'article
            $success = deleteStockItem($pdo, $_POST['stock_id']);
            
            if ($success) {
                $response['success'] = true;
                $response['message'] = 'Article supprimé avec succès!';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Erreur: ' . $e->getMessage();
        }
    }
    
    // Mettre à jour un produit
    elseif (isset($_POST['action']) && $_POST['action'] === 'update_product') {
        try {
            $success = updateProduct($pdo, $_POST['product_id'], [
                ':name' => $_POST['name'],
                ':description' => $_POST['description'] ?? '',
                ':price' => $_POST['price'],
                ':category_id' => $_POST['category_id'] ?: null,
                ':id' => $_POST['product_id']
            ]);
            
            if ($success) {
                $response['success'] = true;
                $response['message'] = 'Produit modifié avec succès!';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Erreur: ' . $e->getMessage();
        }
    }
    
    // Supprimer un produit
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete_product') {
        try {
            $success = deleteProduct($pdo, $_POST['product_id']);
            
            if ($success) {
                $response['success'] = true;
                $response['message'] = 'Produit supprimé avec succès!';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Erreur: ' . $e->getMessage();
        }
    }
    
    // Ajouter une catégorie
    elseif (isset($_POST['action']) && $_POST['action'] === 'add_category') {
        try {
            $success = addCategory($pdo, [
                'name' => $_POST['name'],
                'description' => $_POST['description'] ?? '',
                'color' => $_POST['color'] ?? '#3498db'
            ]);
            
            if ($success) {
                $response['success'] = true;
                $response['message'] = 'Catégorie ajoutée avec succès!';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Erreur: ' . $e->getMessage();
        }
    }
    
    // Modifier une catégorie
    elseif (isset($_POST['action']) && $_POST['action'] === 'update_category') {
        try {
            $success = updateCategory($pdo, $_POST['category_id'], [
                'name' => $_POST['name'],
                'description' => $_POST['description'] ?? '',
                'color' => $_POST['color'] ?? '#3498db'
            ]);
            
            if ($success) {
                $response['success'] = true;
                $response['message'] = 'Catégorie modifiée avec succès!';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Erreur: ' . $e->getMessage();
        }
    }
    
    // Supprimer une catégorie
    elseif (isset($_POST['action']) && $_POST['action'] === 'delete_category') {
        try {
            $success = deleteCategory($pdo, $_POST['category_id']);
            
            if ($success) {
                $response['success'] = true;
                $response['message'] = 'Catégorie supprimée avec succès!';
            }
        } catch (PDOException $e) {
            $response['message'] = 'Erreur: ' . $e->getMessage();
        }
    }
    
    // Vérifier si la réponse est pour AJAX
    if (isset($_POST['ajax']) && $_POST['ajax'] == '1') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Redirection pour les requêtes non-AJAX
    if (isset($response['success']) && $response['success']) {
        $redirect_url = "?tab=" . ($_POST['redirect_tab'] ?? 'inventory') . "&message=" . urlencode($response['message']);
        header("Location: $redirect_url");
        exit;
    }
}

// Récupérer les paramètres GET
$search = $_GET['search'] ?? '';
$tab = $_GET['tab'] ?? 'inventory';

// Récupérer les données
$products = getProducts($pdo, $search);
$stock_items = getStockItems($pdo, $search);
$stats = getStockStats($pdo);
$categories = getCategories($pdo);

// Récupérer l'historique des mouvements selon le filtre actif
$movement_filters = [
    'stock_id' => $_GET['movement_stock'] ?? 'all',
    'change_type' => $_GET['movement_type'] ?? 'all',
    'date' => $_GET['movement_date'] ?? ''
];
$stock_history = getStockHistory($pdo, $movement_filters);

// Récupérer les données pour l'édition
$edit_stock_id = $_GET['edit_stock'] ?? 0;
$edit_product_id = $_GET['edit_product'] ?? 0;
$edit_category_id = $_GET['edit_category'] ?? 0;
$edit_stock_item = $edit_stock_id ? getStockById($pdo, $edit_stock_id) : null;
$edit_product = $edit_product_id ? getProductById($pdo, $edit_product_id) : null;
$edit_category = $edit_category_id ? getCategoryById($pdo, $edit_category_id) : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimerie Admin - Gestion des Stocks</title>
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
            min-height: 100vh;
        }

        /* Sidebar Toggle Button for Mobile */
        .sidebar-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 5px;
            width: 40px;
            height: 40px;
            font-size: 1.2rem;
            cursor: pointer;
            box-shadow: var(--shadow);
        }

        /* Enhanced Sidebar Styles */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, var(--primary) 0%, #1a252f 100%);
            color: white;
            transition: all 0.3s ease;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 25px 20px;
            background: rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
        }

        .sidebar-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--accent);
        }

        .sidebar-header img {
            width: 45px;
            height: 45px;
            background: white;
            border-radius: 10px;
            padding: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .sidebar-header h2 {
            font-size: 1.4rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            background: linear-gradient(90deg, white, #ecf0f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .sidebar-menu {
            padding: 20px 0;
            flex: 1;
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

        /* Sidebar Footer */
        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(0, 0, 0, 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }

        .user-details h4 {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 2px;
        }

        .user-details span {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.6);
        }

        /* Update main content for sidebar */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            margin-left: 250px; /* Match sidebar width */
            transition: margin-left 0.3s ease;
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

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title h2 {
            font-size: 1.8rem;
            color: var(--primary);
        }

        .page-title p {
            color: var(--gray);
            margin-top: 5px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--secondary);
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

        /* Form Card */
        .form-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .form-card h3 {
            font-size: 1.3rem;
            color: var(--primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-group input, .form-group select, .form-group textarea {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: var(--secondary);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 25px;
        }

        .btn-secondary {
            background: var(--gray);
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #27ae60;
        }

        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }

        .tab {
            padding: 12px 20px;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
            color: var(--gray);
            transition: all 0.3s;
            border-bottom: 3px solid transparent;
        }

        .tab.active {
            color: var(--secondary);
            border-bottom-color: var(--secondary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Stock Table */
        .stock-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: var(--shadow);
        }

        .stock-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .stock-header h3 {
            font-size: 1.3rem;
            color: var(--primary);
        }

        .stock-actions {
            display: flex;
            gap: 10px;
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

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            color: var(--gray);
            font-weight: 500;
            background: #f8f9fa;
        }

        .product-image {
            width: 50px;
            height: 50px;
            border-radius: 5px;
            object-fit: cover;
        }

        .product-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .product-name {
            font-weight: 500;
            color: var(--dark);
        }

        .product-sku {
            font-size: 0.9rem;
            color: var(--gray);
        }

        .stock-level {
            font-weight: 600;
        }

        .stock-level.low {
            color: var(--danger);
        }

        .stock-level.medium {
            color: var(--warning);
        }

        .stock-level.high {
            color: var(--success);
        }

        .category {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }

        .category-paper {
            background: #d1ecf1;
            color: #0c5460;
        }

        .category-ink {
            background: #d4edda;
            color: #155724;
        }

        .category-chemical {
            background: #f8d7da;
            color: #721c24;
        }

        .category-equipment {
            background: #fff3cd;
            color: #856404;
        }

        .category-finishing {
            background: #e2e3e5;
            color: #383d41;
        }

        .actions {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }

        .btn-adjust {
            background: rgba(52, 152, 219, 0.1);
            color: var(--secondary);
        }

        .btn-adjust:hover {
            background: var(--secondary);
            color: white;
        }

        .btn-edit {
            background: rgba(243, 156, 18, 0.1);
            color: var(--warning);
        }

        .btn-edit:hover {
            background: var(--warning);
            color: white;
        }

        .btn-delete {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger);
        }

        .btn-delete:hover {
            background: var(--danger);
            color: white;
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

        .modal-content {
            background: white;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            color: var(--primary);
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
            padding: 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }

        /* Movement History */
        .movement-history {
            margin-top: 30px;
        }

        .movement-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .movement-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: white;
        }

        .movement-icon.in {
            background: var(--success);
        }

        .movement-icon.out {
            background: var(--danger);
        }

        .movement-icon.adjust {
            background: var(--warning);
        }

        .movement-details {
            flex: 1;
        }

        .movement-product {
            font-weight: 500;
        }

        .movement-info {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .movement-quantity {
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 5px;
            background: #f8f9fa;
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ddd;
        }

        /* Alert messages */
        .alert {
            padding: 15px;
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

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        /* Scrollbar Styling for Sidebar */
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 992px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                width: 70px;
                transform: translateX(0);
            }
            
            .sidebar-header h2,
            .sidebar-menu span,
            .user-details {
                display: none;
            }
            
            .sidebar-header {
                padding: 20px 10px;
                justify-content: center;
            }
            
            .sidebar-header img {
                margin: 0;
            }
            
            .sidebar-menu li {
                padding: 0;
                text-align: center;
            }
            
            .sidebar-menu li a {
                padding: 15px 10px;
                justify-content: center;
            }
            
            .sidebar-menu i {
                margin-right: 0;
                font-size: 1.2rem;
            }
            
            .sidebar-menu li.active::before {
                width: 100%;
                height: 3px;
                top: auto;
                bottom: 0;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .sidebar-footer {
                padding: 15px 5px;
                text-align: center;
            }
            
            .user-avatar {
                width: 35px;
                height: 35px;
            }
            
            .table-container {
                overflow-x: auto;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 250px;
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar-toggle {
                display: block;
            }
            
            .search-bar input {
                width: 200px;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .stock-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .stock-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .stats-cards {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 576px) {
            .search-bar {
                display: none;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-wrap: wrap;
            }
        }
         .sidebar-header img {
            width: 210px;
            height: 80px;
            
            object-fit: cover;
        }
    </style>
</head>
<body>
    <!-- Sidebar Toggle Button -->
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
             <img src="REM.jpg" alt="Logo Imprimerie" >
        </div>
        <!-- Sidebar Menu Section -->
<div class="sidebar-menu">
    <ul>
        <li >
            <a href="dashboard.php" >
                <i class="fas fa-home"></i>
                <span>Tableau de Bord</span>
            </a>
        </li>
        <li >
                    <a href="probleme.php">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Problèmes Urgents</span>
                    </a>
                </li>
        <li>
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
        <li class="active">
            <a href="ajustestock.php">
                <i class="fas fa-box"></i>
                <span>Stock</span>
            </a>
        </li>
        <li>
            <a href="facture.php">
                <i class="fas fa-file-invoice-dollar"></i>
                <span>Facturation</span>
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
                <h1>Gestion des Stocks</h1>
            </div>
            <div class="header-right">
                <form method="GET" action="" class="search-bar" style="display: flex; align-items: center;">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Rechercher un produit..." value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="tab" value="<?php echo $tab; ?>">
                </form>
                <div class="user-profile">
                    <img src="https://i.pravatar.cc/150?img=12" alt="Admin">
                    <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <!-- Messages d'alerte -->
            <?php if (isset($_GET['message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($_GET['message']); ?></span>
            </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title">
                    <h2>Gestion des stocks</h2>
                    <p>Surveillez, ajustez et gérez votre inventaire d'imprimerie</p>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="showAddStockModal()">
                        <i class="fas fa-plus"></i> Ajouter au stock
                    </button>
                    <a href="?tab=products&action=add_product" class="btn btn-success">
                        <i class="fas fa-plus-circle"></i> Ajouter un produit
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card card-1">
                    <div class="stat-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="totalProducts"><?php echo $stats['total_items']; ?></h3>
                        <p>Articles en stock</p>
                    </div>
                </div>
                <div class="stat-card card-2">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="lowStockCount"><?php echo $stats['low_stock_count']; ?></h3>
                        <p>Articles en alerte</p>
                    </div>
                </div>
                <div class="stat-card card-3">
                    <div class="stat-icon">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="monthlyAdditions"><?php echo $stats['monthly_additions']; ?></h3>
                        <p>Entrées ce mois</p>
                    </div>
                </div>
                <div class="stat-card card-4">
                    <div class="stat-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3 id="movementsCount"><?php echo $stats['movements_count']; ?></h3>
                        <p>Mouvements ce mois</p>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tabs">
                <a href="?tab=inventory" class="tab <?php echo $tab === 'inventory' ? 'active' : ''; ?>">Inventaire</a>
                <a href="?tab=movements" class="tab <?php echo $tab === 'movements' ? 'active' : ''; ?>">Mouvements</a>
                <a href="?tab=categories" class="tab <?php echo $tab === 'categories' ? 'active' : ''; ?>">Catégories</a>
                <a href="?tab=products" class="tab <?php echo $tab === 'products' ? 'active' : ''; ?>">Produits</a>
            </div>

            <!-- Inventory Tab -->
            <div class="tab-content <?php echo $tab === 'inventory' ? 'active' : ''; ?>" id="inventoryTab">
                <!-- Stock Table -->
                <div class="stock-card">
                    <div class="stock-header">
                        <h3>Inventaire des articles (<?php echo count($stock_items); ?>)</h3>
                        <div class="stock-actions">
                            <button class="btn btn-outline" onclick="exportStockData()">
                                <i class="fas fa-file-export"></i> Exporter
                            </button>
                            <a href="?tab=inventory" class="btn btn-primary">
                                <i class="fas fa-sync"></i> Actualiser
                            </a>
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <table id="stockTable">
                            <thead>
                                <tr>
                                    <th>Article</th>
                                    <th>Stock actuel</th>
                                    <th>Stock minimum</th>
                                    <th>Unité</th>
                                    <th>Dernière mise à jour</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="stockList">
                                <?php if (empty($stock_items)): ?>
                                <tr>
                                    <td colspan="6">
                                        <div class="empty-state" style="text-align: center; padding: 20px;">
                                            <i class="fas fa-box-open"></i>
                                            <h3>Aucun article en stock</h3>
                                            <p>Cliquez sur "Ajouter au stock" pour commencer</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($stock_items as $item): ?>
                                <?php
                                // Déterminer le niveau de stock
                                $stock_level_class = 'high';
                                $stock_percent = ($item['quantity'] / $item['low_stock_limit']) * 100;
                                
                                if ($item['quantity'] <= $item['low_stock_limit']) {
                                    $stock_level_class = 'low';
                                } else if ($stock_percent < 150) {
                                    $stock_level_class = 'medium';
                                }
                                
                                // Formatage de la date
                                $updated_date = date('d/m/Y H:i', strtotime($item['updated_at']));
                                ?>
                                <tr>
                                    <td>
                                        <div class="product-info">
                                            <img src="https://images.unsplash.com/photo-1589829545856-d10d557cf95f?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=100&q=80" 
                                                 alt="<?php echo htmlspecialchars($item['item_name']); ?>" 
                                                 class="product-image">
                                            <div>
                                                <div class="product-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="stock-level <?php echo $stock_level_class; ?>"><?php echo $item['quantity']; ?></span></td>
                                    <td><?php echo $item['low_stock_limit']; ?></td>
                                    <td><?php echo htmlspecialchars($item['unit'] ?: 'unité'); ?></td>
                                    <td><?php echo $updated_date; ?></td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn-icon btn-adjust" onclick="showAdjustStockModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['item_name'])); ?>', <?php echo $item['quantity']; ?>)" 
                                                    title="Ajuster le stock">
                                                <i class="fas fa-exchange-alt"></i>
                                            </button>
                                            <a href="?tab=inventory&edit_stock=<?php echo $item['id']; ?>" class="btn-icon btn-edit" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn-icon btn-delete" onclick="deleteStockItem(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['item_name'])); ?>')" title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Formulaire d'ajout/modification d'article -->
                <?php if (isset($_GET['edit_stock']) || (isset($_GET['action']) && $_GET['action'] === 'add_stock')): ?>
                <div class="form-card">
                    <h3><?php echo $edit_stock_item ? 'Modifier l\'article' : 'Ajouter un article au stock'; ?></h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="<?php echo $edit_stock_item ? 'update_stock_item' : 'add_stock_item'; ?>">
                        <input type="hidden" name="ajax" value="0">
                        <input type="hidden" name="redirect_tab" value="inventory">
                        <?php if ($edit_stock_item): ?>
                        <input type="hidden" name="stock_id" value="<?php echo $edit_stock_item['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="item_name">Nom de l'article *</label>
                                <input type="text" id="item_name" name="item_name" value="<?php echo $edit_stock_item ? htmlspecialchars($edit_stock_item['item_name']) : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="quantity">Quantité initiale *</label>
                                <input type="number" id="quantity" name="quantity" min="0" value="<?php echo $edit_stock_item ? $edit_stock_item['quantity'] : '0'; ?>" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="unit">Unité</label>
                                <select id="unit" name="unit">
                                    <option value="unité" <?php echo ($edit_stock_item && $edit_stock_item['unit'] === 'unité') ? 'selected' : ''; ?>>Unité</option>
                                    <option value="paquet" <?php echo ($edit_stock_item && $edit_stock_item['unit'] === 'paquet') ? 'selected' : ''; ?>>Paquet</option>
                                    <option value="kg" <?php echo ($edit_stock_item && $edit_stock_item['unit'] === 'kg') ? 'selected' : ''; ?>>Kg</option>
                                    <option value="litre" <?php echo ($edit_stock_item && $edit_stock_item['unit'] === 'litre') ? 'selected' : ''; ?>>Litre</option>
                                    <option value="rouleau" <?php echo ($edit_stock_item && $edit_stock_item['unit'] === 'rouleau') ? 'selected' : ''; ?>>Rouleau</option>
                                    <option value="boîte" <?php echo ($edit_stock_item && $edit_stock_item['unit'] === 'boîte') ? 'selected' : ''; ?>>Boîte</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="low_stock_limit">Seuil d'alerte</label>
                                <input type="number" id="low_stock_limit" name="low_stock_limit" min="0" value="<?php echo $edit_stock_item ? $edit_stock_item['low_stock_limit'] : '10'; ?>">
                            </div>
                        </div>
                        <div class="form-actions">
                            <a href="?tab=inventory" class="btn btn-secondary">Annuler</a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> <?php echo $edit_stock_item ? 'Modifier' : 'Ajouter'; ?>
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- Movements Tab -->
            <div class="tab-content <?php echo $tab === 'movements' ? 'active' : ''; ?>" id="movementsTab">
                <div class="form-card">
                    <h3>Filtres des mouvements</h3>
                    <form method="GET" action="">
                        <input type="hidden" name="tab" value="movements">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="movement_type">Type de mouvement</label>
                                <select id="movement_type" name="movement_type">
                                    <option value="all" <?php echo $movement_filters['change_type'] === 'all' ? 'selected' : ''; ?>>Tous</option>
                                    <option value="add" <?php echo $movement_filters['change_type'] === 'add' ? 'selected' : ''; ?>>Entrées</option>
                                    <option value="remove" <?php echo $movement_filters['change_type'] === 'remove' ? 'selected' : ''; ?>>Sorties</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="movement_stock">Article</label>
                                <select id="movement_stock" name="movement_stock">
                                    <option value="all" <?php echo $movement_filters['stock_id'] === 'all' ? 'selected' : ''; ?>>Tous les articles</option>
                                    <?php foreach ($stock_items as $item): ?>
                                    <option value="<?php echo $item['id']; ?>" <?php echo $movement_filters['stock_id'] == $item['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($item['item_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="movement_date">Date</label>
                                <input type="date" id="movement_date" name="movement_date" value="<?php echo $movement_filters['date']; ?>">
                            </div>
                        </div>
                        <div class="form-actions">
                            <a href="?tab=movements" class="btn btn-secondary">Effacer les filtres</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filtrer
                            </button>
                        </div>
                    </form>
                </div>

                <div class="stock-card">
                    <div class="stock-header">
                        <h3>Historique des mouvements (<?php echo count($stock_history); ?>)</h3>
                        <button class="btn btn-primary" onclick="printMovements()">
                            <i class="fas fa-print"></i> Imprimer
                        </button>
                    </div>
                    
                    <div class="movement-history" id="movementHistory">
                        <?php if (empty($stock_history)): ?>
                        <div class="empty-state" id="emptyMovementState">
                            <i class="fas fa-history"></i>
                            <h3>Aucun mouvement trouvé</h3>
                            <p>Aucun mouvement ne correspond à vos critères de recherche</p>
                        </div>
                        <?php else: ?>
                        <?php foreach ($stock_history as $movement): ?>
                        <div class="movement-item">
                            <div class="movement-icon <?php echo $movement['change_type'] === 'add' ? 'in' : 'out'; ?>">
                                <i class="fas <?php echo $movement['change_type'] === 'add' ? 'fa-arrow-down' : 'fa-arrow-up'; ?>"></i>
                            </div>
                            <div class="movement-details">
                                <div class="movement-product"><?php echo htmlspecialchars($movement['item_name']); ?></div>
                                <div class="movement-info">
                                    <?php echo $movement['change_type'] === 'add' ? 'Entrée de stock' : 'Sortie de stock'; ?> • 
                                    <?php echo date('d/m/Y H:i', strtotime($movement['created_at'])); ?>
                                    <?php if (!empty($movement['note'])): ?>
                                    <br><small><?php echo htmlspecialchars($movement['note']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="movement-quantity <?php echo $movement['change_type'] === 'add' ? 'text-success' : 'text-danger'; ?>">
                                <?php echo ($movement['change_type'] === 'add' ? '+' : '-') . $movement['quantity']; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Categories Tab -->
            <div class="tab-content <?php echo $tab === 'categories' ? 'active' : ''; ?>" id="categoriesTab">
                <div class="form-card">
                    <h3><?php echo $edit_category ? 'Modifier la catégorie' : 'Ajouter une catégorie'; ?></h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="<?php echo $edit_category ? 'update_category' : 'add_category'; ?>">
                        <input type="hidden" name="ajax" value="0">
                        <input type="hidden" name="redirect_tab" value="categories">
                        <?php if ($edit_category): ?>
                        <input type="hidden" name="category_id" value="<?php echo $edit_category['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Nom de la catégorie *</label>
                                <input type="text" id="name" name="name" value="<?php echo $edit_category ? htmlspecialchars($edit_category['name']) : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="color">Couleur</label>
                                <input type="color" id="color" name="color" value="<?php echo $edit_category ? $edit_category['color'] : '#3498db'; ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" placeholder="Description de la catégorie..."><?php echo $edit_category ? htmlspecialchars($edit_category['description']) : ''; ?></textarea>
                        </div>
                        <div class="form-actions">
                            <?php if ($edit_category): ?>
                            <a href="?tab=categories" class="btn btn-secondary">Annuler</a>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> <?php echo $edit_category ? 'Modifier' : 'Ajouter'; ?>
                            </button>
                        </div>
                    </form>
                </div>

                <div class="stock-card">
                    <div class="stock-header">
                        <h3>Liste des catégories (<?php echo count($categories); ?>)</h3>
                        <?php if (!$edit_category): ?>
                        <a href="?tab=categories&action=add_category" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Ajouter une catégorie
                        </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="table-container">
                        <table id="categoriesTable">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Couleur</th>
                                    <th>Nombre de produits</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="categoriesList">
                                <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <i class="fas fa-tags"></i>
                                            <h3>Aucune catégorie</h3>
                                            <p>Créez votre première catégorie</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($category['name']); ?></strong></td>
                                    <td>
                                        <span class="category" style="background-color: <?php echo $category['color']; ?>20; color: <?php echo $category['color']; ?>;">
                                            <i class="fas fa-circle" style="color: <?php echo $category['color']; ?>;"></i> 
                                            <?php echo $category['color']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $category['product_count']; ?> produit(s)</td>
                                    <td><?php echo htmlspecialchars(substr($category['description'] ?? '', 0, 50)); ?><?php echo strlen($category['description'] ?? '') > 50 ? '...' : ''; ?></td>
                                    <td>
                                        <div class="actions">
                                            <a href="?tab=categories&edit_category=<?php echo $category['id']; ?>" class="btn-icon btn-edit" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn-icon btn-delete" onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars(addslashes($category['name'])); ?>')" title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Products Tab -->
            <div class="tab-content <?php echo $tab === 'products' ? 'active' : ''; ?>" id="productsTab">
                <?php if ((isset($_GET['action']) && $_GET['action'] === 'add_product') || isset($_GET['edit_product'])): ?>
                <div class="form-card">
                    <h3><?php echo $edit_product ? 'Modifier le produit' : 'Ajouter un produit'; ?></h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="<?php echo $edit_product ? 'update_product' : 'add_product'; ?>">
                        <input type="hidden" name="ajax" value="0">
                        <input type="hidden" name="redirect_tab" value="products">
                        <?php if ($edit_product): ?>
                        <input type="hidden" name="product_id" value="<?php echo $edit_product['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Nom du produit *</label>
                                <input type="text" id="name" name="name" value="<?php echo $edit_product ? htmlspecialchars($edit_product['name']) : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="category_id">Catégorie</label>
                                <select id="category_id" name="category_id">
                                    <option value="">Sélectionner une catégorie</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                        <?php echo ($edit_product && $edit_product['category_id'] == $category['id']) ? 'selected' : ''; ?>
                                        style="color: <?php echo $category['color']; ?>;">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="price">Prix (€) *</label>
                                <input type="number" id="price" name="price" min="0" step="0.01" value="<?php echo $edit_product ? $edit_product['price'] : '0'; ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" placeholder="Description du produit..."><?php echo $edit_product ? htmlspecialchars($edit_product['description']) : ''; ?></textarea>
                        </div>
                        <div class="form-actions">
                            <a href="?tab=products" class="btn btn-secondary">Annuler</a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> <?php echo $edit_product ? 'Modifier' : 'Ajouter'; ?>
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <div class="stock-card">
                    <div class="stock-header">
                        <h3>Liste des produits (<?php echo count($products); ?>)</h3>
                        <div class="stock-actions">
                            <a href="?tab=products&action=add_product" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Ajouter un produit
                            </a>
                        </div>
                    </div>
                    
                    <div class="table-container">
                        <table id="productsTable">
                            <thead>
                                <tr>
                                    <th>Produit</th>
                                    <th>Catégorie</th>
                                    <th>Prix</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="productsList">
                                <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <i class="fas fa-boxes"></i>
                                            <h3>Aucun produit</h3>
                                            <p>Ajoutez votre premier produit</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                <tr>
                                    <td>
                                        <div class="product-info">
                                            <img src="https://images.unsplash.com/photo-1545235617-9465d2a55698?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=100&q=80" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                 class="product-image">
                                            <div>
                                                <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($product['category_name']): ?>
                                        <span class="category" style="background-color: <?php echo $product['category_color'] ?? '#3498db'; ?>20; color: <?php echo $product['category_color'] ?? '#3498db'; ?>;">
                                            <?php echo htmlspecialchars($product['category_name']); ?>
                                        </span>
                                        <?php else: ?>
                                        <span class="category" style="background-color: #e2e3e5; color: #383d41;">
                                            Non catégorisé
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($product['price'], 2, ',', ' '); ?> €</td>
                                    <td><?php echo htmlspecialchars(substr($product['description'], 0, 50)); ?><?php echo strlen($product['description']) > 50 ? '...' : ''; ?></td>
                                    <td>
                                        <div class="actions">
                                            <a href="?tab=products&edit_product=<?php echo $product['id']; ?>" class="btn-icon btn-edit" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn-icon btn-delete" onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars(addslashes($product['name'])); ?>')" title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pour ajuster le stock -->
    <div class="modal" id="adjustStockModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Ajuster le stock</h3>
                <button class="modal-close" onclick="hideAdjustModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="adjustStockForm">
                    <input type="hidden" id="adjustStockId">
                    <div class="form-group">
                        <label for="adjustProductName">Article</label>
                        <input type="text" id="adjustProductName" readonly>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="adjustCurrentStock">Stock actuel</label>
                            <input type="number" id="adjustCurrentStock" readonly>
                        </div>
                        <div class="form-group">
                            <label for="adjustType">Type d'ajustement</label>
                            <select id="adjustType">
                                <option value="add">Entrée de stock</option>
                                <option value="remove">Sortie de stock</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="adjustQuantity">Quantité *</label>
                            <input type="number" id="adjustQuantity" min="1" value="1" required>
                        </div>
                        <div class="form-group">
                            <label for="adjustReason">Raison</label>
                            <select id="adjustReason">
                                <option value="purchase">Achat fournisseur</option>
                                <option value="production">Utilisation production</option>
                                <option value="damaged">Produit endommagé</option>
                                <option value="correction">Correction d'erreur</option>
                                <option value="other">Autre</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="adjustNotes">Notes (optionnel)</label>
                        <textarea id="adjustNotes" placeholder="Notes supplémentaires..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="hideAdjustModal()">Annuler</button>
                <button class="btn btn-success" onclick="saveStockAdjustment()">Enregistrer l'ajustement</button>
            </div>
        </div>
    </div>

    <!-- Modal pour ajouter un article au stock -->
    <div class="modal" id="addStockModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Ajouter un article au stock</h3>
                <button class="modal-close" onclick="hideAddStockModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addStockForm" onsubmit="return false;">
                    <div class="form-group">
                        <label for="addItemName">Nom de l'article *</label>
                        <input type="text" id="addItemName" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="addItemQuantity">Quantité initiale *</label>
                            <input type="number" id="addItemQuantity" min="0" value="0" required>
                        </div>
                        <div class="form-group">
                            <label for="addItemUnit">Unité</label>
                            <select id="addItemUnit">
                                <option value="unité">Unité</option>
                                <option value="paquet">Paquet</option>
                                <option value="kg">Kg</option>
                                <option value="litre">Litre</option>
                                <option value="rouleau">Rouleau</option>
                                <option value="boîte">Boîte</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="addItemLowStock">Seuil d'alerte</label>
                            <input type="number" id="addItemLowStock" min="0" value="10">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="hideAddStockModal()">Annuler</button>
                <button class="btn btn-success" onclick="saveStockItem()">Ajouter au stock</button>
            </div>
        </div>
    </div>

    <!-- Modal pour confirmer la suppression -->
    <div class="modal" id="confirmModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirmation</h3>
                <button class="modal-close" onclick="hideConfirmModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p id="confirmMessage"></p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="hideConfirmModal()">Annuler</button>
                <button class="btn btn-danger" onclick="confirmDelete()">Supprimer</button>
            </div>
        </div>
    </div>

    <script>
        // Variables pour la gestion
        let currentAdjustStockId = null;
        let currentDeleteId = null;
        let currentDeleteType = null;
        let currentDeleteName = null;

        // Éléments DOM
        const adjustStockModal = document.getElementById('adjustStockModal');
        const addStockModal = document.getElementById('addStockModal');
        const confirmModal = document.getElementById('confirmModal');
        const adjustStockForm = document.getElementById('adjustStockForm');
        const addStockForm = document.getElementById('addStockForm');

        // Sidebar toggle function
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const toggleBtn = document.querySelector('.sidebar-toggle');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(e.target) && e.target !== toggleBtn && !toggleBtn.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });

        // Close sidebar after clicking a link on mobile
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    document.getElementById('sidebar').classList.remove('active');
                }
            });
        });

        // Fonctions pour les modals
        function showAdjustStockModal(stockId, productName, currentStock) {
            currentAdjustStockId = stockId;
            
            document.getElementById('adjustStockId').value = stockId;
            document.getElementById('adjustProductName').value = productName;
            document.getElementById('adjustCurrentStock').value = currentStock;
            document.getElementById('adjustQuantity').value = 1;
            document.getElementById('adjustNotes').value = '';
            
            adjustStockModal.style.display = 'flex';
        }

        function hideAdjustModal() {
            adjustStockModal.style.display = 'none';
            adjustStockForm.reset();
            currentAdjustStockId = null;
        }

        function showAddStockModal() {
            addStockModal.style.display = 'flex';
        }

        function hideAddStockModal() {
            addStockModal.style.display = 'none';
            addStockForm.reset();
        }

        function showConfirmModal(message, type, id, name) {
            currentDeleteId = id;
            currentDeleteType = type;
            currentDeleteName = name;
            document.getElementById('confirmMessage').textContent = message;
            confirmModal.style.display = 'flex';
        }

        function hideConfirmModal() {
            confirmModal.style.display = 'none';
            currentDeleteId = null;
            currentDeleteType = null;
            currentDeleteName = null;
        }

        // Fonctions pour la gestion des données
        async function saveStockAdjustment() {
            const stockId = document.getElementById('adjustStockId').value;
            const adjustType = document.getElementById('adjustType').value;
            const quantity = parseInt(document.getElementById('adjustQuantity').value);
            const reason = document.getElementById('adjustReason').value;
            const notes = document.getElementById('adjustNotes').value;

            if (!quantity || quantity <= 0) {
                alert("Veuillez entrer une quantité valide");
                return;
            }

            const formData = new FormData();
            formData.append('action', 'adjust_stock');
            formData.append('ajax', '1');
            formData.append('stock_id', stockId);
            formData.append('adjust_type', adjustType);
            formData.append('quantity', quantity);
            formData.append('reason', reason);
            formData.append('notes', notes);

            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    alert(data.message);
                    hideAdjustModal();
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Une erreur est survenue lors de l\'ajustement du stock');
            }
        }

        async function saveStockItem() {
            const itemName = document.getElementById('addItemName').value.trim();
            const quantity = parseInt(document.getElementById('addItemQuantity').value);
            const unit = document.getElementById('addItemUnit').value;
            const lowStock = parseInt(document.getElementById('addItemLowStock').value) || 10;

            if (!itemName || quantity < 0) {
                alert("Veuillez remplir tous les champs obligatoires");
                return;
            }

            const formData = new FormData();
            formData.append('action', 'add_stock_item');
            formData.append('ajax', '1');
            formData.append('item_name', itemName);
            formData.append('quantity', quantity);
            formData.append('unit', unit);
            formData.append('low_stock_limit', lowStock);

            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    alert(data.message);
                    hideAddStockModal();
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Une erreur est survenue lors de l\'ajout au stock');
            }
        }

        function deleteStockItem(stockId, itemName) {
            showConfirmModal(
                `Êtes-vous sûr de vouloir supprimer l'article "${itemName}" ? Cette action supprimera également tout l'historique associé.`,
                'stock',
                stockId,
                itemName
            );
        }

        function deleteProduct(productId, productName) {
            showConfirmModal(
                `Êtes-vous sûr de vouloir supprimer le produit "${productName}" ?`,
                'product',
                productId,
                productName
            );
        }

        function deleteCategory(categoryId, categoryName) {
            showConfirmModal(
                `Êtes-vous sûr de vouloir supprimer la catégorie "${categoryName}" ?\n\nNote: Les produits de cette catégorie ne seront pas supprimés, mais leur catégorie sera effacée.`,
                'category',
                categoryId,
                categoryName
            );
        }

        async function confirmDelete() {
            if (!currentDeleteId || !currentDeleteType) {
                hideConfirmModal();
                return;
            }

            const formData = new FormData();
            let action = '';
            
            if (currentDeleteType === 'stock') {
                action = 'delete_stock_item';
                formData.append('stock_id', currentDeleteId);
            } else if (currentDeleteType === 'product') {
                action = 'delete_product';
                formData.append('product_id', currentDeleteId);
            } else if (currentDeleteType === 'category') {
                action = 'delete_category';
                formData.append('category_id', currentDeleteId);
            }

            formData.append('action', action);
            formData.append('ajax', '1');

            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                
                if (data.success) {
                    alert(data.message);
                    hideConfirmModal();
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Une erreur est survenue lors de la suppression');
            }
        }

        function exportStockData() {
            // Cette fonction exporterait les données au format CSV
            alert('Export CSV en cours de préparation...\n\nDans une version complète, un fichier CSV serait généré avec toutes les données du stock.');
        }

        function printMovements() {
            window.print();
        }

        // Gestion des onglets
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetTab = this.getAttribute('href').split('=')[1];
                    
                    // Mettre à jour l'URL sans recharger la page
                    const url = new URL(window.location);
                    url.searchParams.set('tab', targetTab);
                    window.history.pushState({}, '', url);
                    
                    // Activer l'onglet sélectionné
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));
                    
                    this.classList.add('active');
                    document.getElementById(targetTab + 'Tab').classList.add('active');
                });
            });
        });

        // Fermer les modals en cliquant en dehors
        window.onclick = function(event) {
            if (event.target === adjustStockModal) {
                hideAdjustModal();
            }
            if (event.target === addStockModal) {
                hideAddStockModal();
            }
            if (event.target === confirmModal) {
                hideConfirmModal();
            }
        }

        // Gérer les touches ESC pour fermer les modals
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideAdjustModal();
                hideAddStockModal();
                hideConfirmModal();
            }
        });
    </script>
</body>
</html>