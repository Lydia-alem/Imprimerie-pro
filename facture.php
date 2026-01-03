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
            width: 210px;
            height: 80px;
            
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

        .items-table input, .items-table select {
            width: 100%;
            padding: 6px 4px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 0.8rem;
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
            font-size: 0.8rem;
        }

        .items-table-preview th {
            background: #2c3e50;
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
            font-size: 0.75rem;
        }

        .print-items-table th {
            background: #2c3e50;
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
            width: 70px;
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
            
            .items-table th, .items-table td {
                padding: 6px 4px;
                font-size: 0.7rem;
            }
            
            .items-table input, .items-table select {
                padding: 4px 2px;
                font-size: 0.7rem;
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

        /* Amount in letters */
        .amount-in-letters {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            border: 1px solid #ddd;
            font-style: italic;
        }

        .amount-in-letters p {
            margin: 0;
            color: #333;
            font-size: 0.9rem;
        }

        /* TVA column */
        .tva-column {
            width: 60px;
        }

        /* Client info fields */
        .client-additional-info {
            margin-top: 10px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 5px;
        }

        /* BL item columns */
        .bl-price-col { width: 100px; }
        .bl-total-col { width: 100px; }
        .bl-palettes-col { width: 80px; }
        .bl-action-col { width: 50px; }

        /* Search results highlight */
        .highlight {
            background-color: yellow;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .client-additional-info .form-row {
                grid-template-columns: 1fr;
            }
            
            .bl-price-col, .bl-total-col {
                width: 80px;
            }
        }
        /* Additional style for submenu if needed */
        .sidebar-submenu {
            padding-left: 20px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }

        .sidebar-submenu.active {
            max-height: 200px;
        }

        .sidebar-submenu li {
            margin: 3px 0;
        }

        .sidebar-submenu li a {
            padding: 10px 20px;
            font-size: 0.85rem;
        }

        .has-submenu > a::after {
            content: '\f078';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            margin-left: auto;
            font-size: 0.8rem;
            transition: transform 0.3s;
        }

        .has-submenu.active > a::after {
            transform: rotate(180deg);
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

    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <!-- Company Logo -->
            <img src="REM.jpg" alt="Logo Imprimerie" >
            
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
        <li class="active">
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
                <h1>Gestion des Factures & BL</h1>
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
            
            // Fetch data from database
            try {
                // Fetch clients
                $clientsStmt = $pdo->query("SELECT * FROM clients ORDER BY name");
                $clients = $clientsStmt->fetchAll(PDO::FETCH_ASSOC);
                
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
                        $tva_rates = $_POST['item_tva_rate'] ?? [];
                        
                        for ($i = 0; $i < count($descriptions); $i++) {
                            if (!empty($descriptions[$i])) {
                                $itemStmt = $pdo->prepare("
                                    INSERT INTO invoice_items (invoice_id, description, quantity, price, subtotal) 
                                    VALUES (?, ?, ?, ?, ?)
                                ");
                                $itemStmt->execute([
                                    $invoice_id,
                                    $descriptions[$i] . " (" . ($units[$i] ?? 'unité') . ") - TVA " . ($tva_rates[$i] ?? '19') . "%",
                                    $quantities[$i],
                                    $prices[$i],
                                    $quantities[$i] * $prices[$i]
                                ]);
                            }
                        }
                        
                        $pdo->commit();
                        
                        // Refresh page to show new invoice
                        header("Location:facturephp?success=1&invoice_id=" . $invoice_id);
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
                        $prices = $_POST['bl_item_price'] ?? [];
                        $palettes = $_POST['bl_item_palettes'] ?? [];
                        
                        for ($i = 0; $i < count($descriptions); $i++) {
                            if (!empty($descriptions[$i])) {
                                $fullDescription = $descriptions[$i];
                                $fullDescription .= " (" . ($units[$i] ?? 'unité') . ")";
                                if (!empty($palettes[$i])) $fullDescription .= " | Palettes: " . $palettes[$i];
                                
                                $itemTotal = ($quantities[$i] ?? 0) * ($prices[$i] ?? 0);
                                
                                $itemStmt = $pdo->prepare("
                                    INSERT INTO order_items (order_id, description, quantity, price, subtotal) 
                                    VALUES (?, ?, ?, ?, ?)
                                ");
                                $itemStmt->execute([
                                    $order_id,
                                    $fullDescription,
                                    $quantities[$i] ?? 1,
                                    $prices[$i] ?? 0,
                                    $itemTotal
                                ]);
                            }
                        }
                        
                        $pdo->commit();
                        
                        // Refresh page to show new BL
                        header("Location: facture.php?success=2&bl_id=" . $order_id);
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
            
            // Fonction pour convertir les chiffres en lettres (en français)
            function nombreEnLettres($nombre) {
                $unites = array('', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf');
                $dizaines = array('', 'dix', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante-dix', 'quatre-vingt', 'quatre-vingt-dix');
                $exceptions = array(11 => 'onze', 12 => 'douze', 13 => 'treize', 14 => 'quatorze', 15 => 'quinze', 
                                    16 => 'seize', 17 => 'dix-sept', 18 => 'dix-huit', 19 => 'dix-neuf');
                
                if ($nombre == 0) return 'zéro';
                
                $texte = '';
                
                // Millions
                $millions = floor($nombre / 1000000);
                if ($millions > 0) {
                    $texte .= nombreEnLettres($millions) . ' million' . ($millions > 1 ? 's' : '') . ' ';
                    $nombre %= 1000000;
                }
                
                // Milliers
                $milliers = floor($nombre / 1000);
                if ($milliers > 0) {
                    if ($milliers == 1) {
                        $texte .= 'mille ';
                    } else {
                        $texte .= nombreEnLettres($milliers) . ' mille ';
                    }
                    $nombre %= 1000;
                }
                
                // Centaines
                $centaines = floor($nombre / 100);
                if ($centaines > 0) {
                    if ($centaines == 1) {
                        $texte .= 'cent ';
                    } else {
                        $texte .= $unites[$centaines] . ' cent ';
                    }
                    $nombre %= 100;
                }
                
                // Dizaines et unités
                if ($nombre > 0) {
                    if ($nombre < 10) {
                        $texte .= $unites[$nombre] . ' ';
                    } elseif ($nombre < 20) {
                        $texte .= $exceptions[$nombre] . ' ';
                    } else {
                        $dizaine = floor($nombre / 10);
                        $unite = $nombre % 10;
                        
                        if ($dizaine == 7 || $dizaine == 9) {
                            // Cas spéciaux pour soixante-dix et quatre-vingt-dix
                            $dizaine--;
                            $unite += 10;
                            $texte .= $dizaines[$dizaine];
                            if ($unite > 0) {
                                $texte .= '-' . $unites[$unite];
                            }
                            $texte .= ' ';
                        } else {
                            $texte .= $dizaines[$dizaine];
                            if ($unite > 0) {
                                if ($dizaine == 8) {
                                    $texte .= ($unite == 1) ? '-un' : '-' . $unites[$unite];
                                } else {
                                    $texte .= ($unite == 1) ? ' et un' : '-' . $unites[$unite];
                                }
                            }
                            $texte .= ' ';
                        }
                    }
                }
                
                return trim($texte);
            }
            
            // Fonction pour convertir un montant en lettres
            function montantEnLettres($montant) {
                $entier = floor($montant);
                $decimal = round(($montant - $entier) * 100);
                
                $texte = nombreEnLettres($entier) . ' dinar';
                if ($entier > 1) $texte .= 's';
                
                if ($decimal > 0) {
                    $texte .= ' et ' . nombreEnLettres($decimal) . ' centime';
                    if ($decimal > 1) $texte .= 's';
                }
                
                return $texte;
            }
            ?>
            
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
                                <div class="client-additional-info">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="client_nif">NIF Client</label>
                                            <input type="text" id="client_nif" name="client_nif" class="form-control" value="">
                                        </div>
                                        <div class="form-group">
                                            <label for="client_rc">RC Client</label>
                                            <input type="text" id="client_rc" name="client_rc" class="form-control" value="">
                                        </div>
                                        <div class="form-group">
                                            <label for="client_article_number">N° Article Client</label>
                                            <input type="text" id="client_article_number" name="client_article_number" class="form-control" value="">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3>Informations de l'entreprise</h3>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="company_nif">NIF Entreprise</label>
                                        <input type="text" id="company_nif" name="company_nif" class="form-control" value="123456789012345">
                                    </div>
                                    <div class="form-group">
                                        <label for="company_rc">RC Entreprise</label>
                                        <input type="text" id="company_rc" name="company_rc" class="form-control" value="RC123456789">
                                    </div>
                                    <div class="form-group">
                                        <label for="company_article_number">N° Article Entreprise</label>
                                        <input type="text" id="company_article_number" name="company_article_number" class="form-control" value="ART987654321">
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
                                            <th width="30">N°</th>
                                            <th>Description</th>
                                            <th width="60">Unité</th>
                                            <th width="70">Quantité</th>
                                            <th width="80">Prix unitaire (DA)</th>
                                            <th width="60" class="tva-column">TVA %</th>
                                            <th width="90">Total TTC (DA)</th>
                                            <th width="40">Action</th>
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
                                    <span class="total-label">Sous-total HT:</span>
                                    <span class="total-value" id="subtotal">0.00 DA</span>
                                </div>
                                <div class="total-row">
                                    <span class="total-label">TVA (<span id="tvaPercent">19</span>%):</span>
                                    <span class="total-value" id="tvaAmount">0.00 DA</span>
                                </div>
                                <div class="total-row grand-total">
                                    <span class="total-label">Total général TTC:</span>
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
                                <img src=REM.jpg alt="logo" height="50" width="100">
                                <p> Facture</p>
                                <p></p>
                                <p> <span id="previewCompanyNif"></span>  <span id="previewCompanyRc"></span>  <span id="previewCompanyArticleNumber"></span></p>
                            </div>

                            <div class="invoice-info-grid">
                                <div class="company-info">
                                    <h4>Émise par:</h4>
                                    <p><strong>REM</strong></p>
                                    <p>Rue Bellil Abd Allah lotissement 118 N° 119,Setif 19000</p>
                                    <p>Setif Centre, Algérie</p>
                                    <p>Tél: 0660639631 / 0560988875 </p>
                                    <p>NIF: <span id="previewCompanyNif2">298619280219028</span></p>
                                    <p>RC: <span id="previewCompanyRc2">15A0512508</span></p>
                                    <p>N° Article: <span id="previewCompanyArticleNumber2">19018372051</span></p>
                                </div>
                                <div class="client-info">
                                    <h4>Facturé à:</h4>
                                    <p id="previewClientName">-</p>
                                    <p id="previewClientAddress">-</p>
                                    <p id="previewClientContact">-</p>
                                    <p>NIF: <span id="previewClientNif">-</span></p>
                                    <p>RC: <span id="previewClientRc">-</span></p>
                                    <p>N° Article: <span id="previewClientArticleNumber">-</span></p>
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
                                        <th width="30">N°</th>
                                        <th>Description</th>
                                        <th width="60">Unité</th>
                                        <th width="70">Qté</th>
                                        <th width="80">Prix U.</th>
                                        <th width="60">TVA %</th>
                                        <th width="90">Total TTC</th>
                                    </tr>
                                </thead>
                                <tbody id="previewInvoiceItems">
                                    <!-- Les articles seront ajoutés ici dynamiquement -->
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 20px; color: #999;">
                                            Aucun article ajouté
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="5" style="text-align: right; font-weight: 600;">Sous-total HT:</td>
                                        <td colspan="2" id="previewSubtotal">0.00 DA</td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" style="text-align: right; font-weight: 600;">TVA (<span id="previewTvaPercent">19</span>%):</td>
                                        <td colspan="2" id="previewTvaAmount">0.00 DA</td>
                                    </tr>
                                    <tr>
                                        <td colspan="5" style="text-align: right; font-weight: 700;">Total général TTC:</td>
                                        <td colspan="2" id="previewTotalAmount">0.00 DA</td>
                                    </tr>
                                </tfoot>
                            </table>

                            <div class="amount-in-letters" id="previewAmountInLetters">
                                Arrêtée la présente facture à la somme de : <strong>zéro dinar</strong>
                            </div>

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
                                            <th width="30">N°</th>
                                            <th>Désignation</th>
                                            <th class="unit-column">Unité</th>
                                            <th width="70">Qté</th>
                                            <th class="bl-price-col">Prix U. (DA)</th>
                                            <th class="bl-total-col">Total (DA)</th>
                                            <th class="bl-palettes-col">Nb Pal</th>
                                            <th class="bl-action-col">Action</th>
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
                                <h3>Totaux du BL</h3>
                                <div class="total-row">
                                    <span class="total-label">Total quantité:</span>
                                    <span class="total-value" id="blTotalQty">0</span>
                                </div>
                                <div class="total-row">
                                    <span class="total-label">Total palettes:</span>
                                    <span class="total-value" id="blTotalPalettes">0</span>
                                </div>
                                <div class="total-row grand-total">
                                    <span class="total-label">Valeur totale:</span>
                                    <span class="total-value" id="blTotalValue">0.00 DA</span>
                                </div>
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
                                <img src=REM.jpg alt="logo" height="50" width="100">
                                <h3>BON DE LIVRAISON</h3>
                                
                            </div>

                            <div class="bl-info-grid">
                                <div class="company-info">
                                    <h4>Expéditeur:</h4>
                                    <p><strong>REM</strong></p>
                                    <p>Rue Bellil Abd Allah lotissement 118 N° 119,Setif 19000</p>
                                    <p>Setif Centre, Algérie</p>
                                    <p>Tél: 0660639631 / 0560988875 </p>
                                    <p>NIF: 298619280219028</p>
                                    <p>RC: 15A0512508</p>
                                    <p>N° Article: 19018372051</p>
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
                                        <th width="30">N°</th>
                                        <th>Désignation</th>
                                        <th width="50">Unité</th>
                                        <th width="50">Qté</th>
                                        <th width="70">Prix U.</th>
                                        <th width="80">Total</th>
                                        <th width="50">Nb Pal</th>
                                    </tr>
                                </thead>
                                <tbody id="previewBLItems">
                                    <!-- Les articles seront ajoutés ici dynamiquement -->
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 20px; color: #999;">
                                            Aucun article ajouté
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" style="text-align: right; font-weight: 600;">Totaux:</td>
                                        <td id="previewBLTotalQty">0</td>
                                        <td></td>
                                        <td id="previewBLTotalValue">0.00 DA</td>
                                        <td id="previewBLTotalPalettes">0</td>
                                    </tr>
                                </tfoot>
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
                                <button type="button" class="btn btn-success" id="calculateBL">
                                    <i class="fas fa-calculator"></i> Calculer
                                </button>
                                <button type="button" class="btn btn-primary" id="previewBL">
                                    <i class="fas fa-eye"></i> Prévisualiser
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
                                <tr class="document-row">
                                    <td>
                                        <i class="fas fa-file-invoice" style="color: #3498db;"></i> Facture
                                    </td>
                                    <td>FAC-<?php echo str_pad($invoice['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td class="client-name"><?php echo htmlspecialchars($invoice['client_name'] ?? 'N/A'); ?></td>
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
                                <tr class="document-row">
                                    <td>
                                        <i class="fas fa-truck" style="color: #2ecc71;"></i> BL
                                    </td>
                                    <td>BL-<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td class="client-name"><?php echo htmlspecialchars($order['client_name'] ?? 'N/A'); ?></td>
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
                        <img src="REM.jpg" alt="Logo Imprimerie" 
                             onerror="this.src='https://via.placeholder.com/100/3498db/ffffff?text=IP'">
                    </div>

                    
                    <p>NIF: <span id="printCompanyNif">298619280219028</span> | RC: <span id="printCompanyRc">15A0512508-19/00</span> | N° Article: <span id="printCompanyArticleNumber">19018372051</span></p>
                </div>

                <div class="print-invoice-info">
                    
                    <h3>FACTURE <?php echo str_pad($invoice['id'], 4, '0', STR_PAD_LEFT); ?></h3>
                    <div class="print-invoice-meta">
                        <div><strong>N°:</strong> <span id="printInvoiceNumber"><?php echo $nextInvoiceNumber; ?></span></div>
                        <div><strong>Date:</strong> <span id="printInvoiceDate"><?php echo date('d/m/Y'); ?></span></div>
                        <div><strong>Échéance:</strong> <span id="printDueDate"><?php echo date('d/m/Y', strtotime('+30 days')); ?></span></div>
                    </div>
                </div>

                <div class="print-details-grid">
                    <div class="print-company">
                        <h4>Émise par:</h4>
                        <p><strong>REM</strong></p>
                        <p>Rue Bellil Abd Allah lotissement 118 N° 119,Setif 19000</p>
                        <p>Setif Centre, Algérie</p>
                        <p>Tél: 0660639631 / 0560988875</p>
                        <p>Email: Rymemballagemoderne@gmail.com</p>
                        <p>NIF: <span id="printCompanyNif2">298619280219028</span></p>
                        <p>RC: <span id="printCompanyRc2">15A0512508</span></p>
                        <p>N° Article: <span id="printCompanyArticleNumber2">19018372051</span></p>
                    </div>
                    <div class="print-client">
                        <h4>Facturé à:</h4>
                        <p id="printClientName">-</p>
                        <p id="printClientAddress">-</p>
                        <p id="printClientContact">-</p>
                        <p>NIF: <span id="printClientNif">-</span></p>
                        <p>RC: <span id="printClientRc">-</span></p>
                        <p>N° Article: <span id="printClientArticleNumber">-</span></p>
                    </div>
                </div>

                <table class="print-items-table">
                    <thead>
                        <tr>
                            <th width="30">N°</th>
                            <th>Description</th>
                            <th width="50">Unité</th>
                            <th width="60">Qté</th>
                            <th width="70">Prix U. HT (DA)</th>
                            <th width="50">TVA %</th>
                            <th width="80">Total TTC (DA)</th>
                        </tr>
                    </thead>
                    <tbody id="printInvoiceItems">
                        <!-- Les articles seront insérés ici -->
                    </tbody>
                </table>

                <div class="print-totals">
                    <div class="print-total-row">
                        <span>Sous-total HT:</span>
                        <span id="printSubtotal">0.00 DA</span>
                    </div>
                    <div class="print-total-row">
                        <span>TVA (<span id="printTvaPercent">19</span>%):</span>
                        <span id="printTvaAmount">0.00 DA</span>
                    </div>
                    <div class="print-total-row print-grand-total">
                        <span>Total à payer TTC:</span>
                        <span id="printTotalAmount">0.00 DA</span>
                    </div>
                </div>

                <div class="amount-in-letters" id="printAmountInLetters">
                    Arrêtée la présente facture à la somme de : <strong>zéro dinar</strong>
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
                        
                    </button>
                    <button class="btn btn-info" style="margin-left: 10px;" onclick="downloadAsPDF('invoice')">
                        
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
                        <img src="REM.jpg" alt="Logo Imprimerie" 
                             onerror="this.src='https://via.placeholder.com/100/2ecc71/ffffff?text=BL'">
                    </div>
                    
                </div>

                <div class="print-bl-info">
                    
                    <h3>BON DE LIVRAISON <span id="printBLNumber"><?php echo $nextBLNumber; ?></span></h3>
                    <div class="print-bl-meta">
                        
                        <div><strong>Date:</strong> <span id="printBLDate"><?php echo date('d/m/Y'); ?></span></div>
                        <div><strong>Référence:</strong> <span id="printReference">-</span></div>
                    </div>
                </div>

                <div class="print-details-grid">
                    <div class="print-company">
                        <h4>Expéditeur:</h4>
                        <p><strong>REM</strong></p>
                        <p>Rue Bellil Abd Allah lotissement 118 N° 119,Setif 19000</p>
                        <p>Tél: 0660639631 / 0560988875</p>
                        <p>NIF: 298619280219028</p>
                        <p>RC: 15A0512508</p>
                        <p>N° Article: 19018372051</p>
                    </div>
                    <div class="print-client">
                        <h4>Destinataire:</h4>
                        <p id="printBLClientName">-</p>
                        <p id="printBLClientAddress">-</p>
                        <p id="printBLClientContact">-</p>
                    </div>
                </div>

                <div style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; font-size: 0.9rem;">
                        <div>
                            <strong>Livreur:</strong> <span id="printDeliveryPerson">-</span>
                        </div>
                        <div>
                            <strong>Véhicule:</strong> <span id="printVehicle">-</span>
                        </div>
                        <div>
                            <strong>Date de livraison:</strong> <span id="printDeliveryDate"><?php echo date('d/m/Y'); ?></span>
                        </div>
                        <div>
                            <strong>Conditions de remise:</strong> <span id="printConditions">Sur place</span>
                        </div>
                        <div>
                            <strong>Total palettes:</strong> <span id="printTotalPalettes">0</span>
                        </div>
                        <div>
                            <strong>Valeur totale:</strong> <span id="printTotalValue">0.00 DA</span>
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
                            <th width="50">Nb Pal</th>
                        </tr>
                    </thead>
                    <tbody id="printBLItems">
                        <!-- Les articles seront insérés ici -->
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" style="text-align: right; font-weight: 600; background: #f8f9fa;">Totaux:</td>
                            <td style="font-weight: 600; background: #f8f9fa;" id="printBLTotalQty">0</td>
                            <td style="background: #f8f9fa;"></td>
                            <td style="font-weight: 600; background: #f8f9fa;" id="printBLTotalValue">0.00 DA</td>
                            <td style="font-weight: 600; background: #f8f9fa;" id="printBLTotalPalettes">0</td>
                        </tr>
                    </tfoot>
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
                        
                    </button>
                    <button class="btn btn-info" style="margin-left: 10px;" onclick="downloadAsPDF('bl')">
                        
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Pour l'impression directe sans modal -->
    <div id="directPrintContent" style="display: none;"></div>

    <script>
        // Variables globales
        let invoiceItems = [];
        let blItems = [];
        let invoiceItemCount = 0;
        let blItemCount = 0;
        let currentDocumentType = 'invoice';

        // Fonctions pour convertir les nombres en lettres
        function nombreEnLettres(nombre) {
            const unites = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf'];
            const dizaines = ['', 'dix', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante-dix', 'quatre-vingt', 'quatre-vingt-dix'];
            const exceptions = {
                11: 'onze', 12: 'douze', 13: 'treize', 14: 'quatorze', 15: 'quinze',
                16: 'seize', 17: 'dix-sept', 18: 'dix-huit', 19: 'dix-neuf'
            };
            
            if (nombre === 0) return 'zéro';
            
            let texte = '';
            
            // Millions
            const millions = Math.floor(nombre / 1000000);
            if (millions > 0) {
                texte += nombreEnLettres(millions) + ' million' + (millions > 1 ? 's' : '') + ' ';
                nombre %= 1000000;
            }
            
            // Milliers
            const milliers = Math.floor(nombre / 1000);
            if (milliers > 0) {
                if (milliers === 1) {
                    texte += 'mille ';
                } else {
                    texte += nombreEnLettres(milliers) + ' mille ';
                }
                nombre %= 1000;
            }
            
            // Centaines
            const centaines = Math.floor(nombre / 100);
            if (centaines > 0) {
                if (centaines === 1) {
                    texte += 'cent ';
                } else {
                    texte += unites[centaines] + ' cent ';
                }
                nombre %= 100;
            }
            
            // Dizaines et unités
            if (nombre > 0) {
                if (nombre < 10) {
                    texte += unites[nombre] + ' ';
                } else if (nombre < 20) {
                    texte += exceptions[nombre] + ' ';
                } else {
                    const dizaine = Math.floor(nombre / 10);
                    const unite = nombre % 10;
                    
                    if (dizaine === 7 || dizaine === 9) {
                        const dizaineCorrigee = dizaine - 1;
                        const uniteCorrigee = unite + 10;
                        texte += dizaines[dizaineCorrigee];
                        if (uniteCorrigee > 0) {
                            texte += '-' + unites[uniteCorrigee];
                        }
                        texte += ' ';
                    } else {
                        texte += dizaines[dizaine];
                        if (unite > 0) {
                            if (dizaine === 8) {
                                texte += (unite === 1) ? '-un' : '-' + unites[unite];
                            } else {
                                texte += (unite === 1) ? ' et un' : '-' + unites[unite];
                            }
                        }
                        texte += ' ';
                    }
                }
            }
            
            return texte.trim();
        }

        function montantEnLettres(montant) {
            const entier = Math.floor(montant);
            const decimal = Math.round((montant - entier) * 100);
            
            let texte = nombreEnLettres(entier) + ' dinar';
            if (entier > 1) texte += 's';
            
            if (decimal > 0) {
                texte += ' et ' + nombreEnLettres(decimal) + ' centime';
                if (decimal > 1) texte += 's';
            }
            
            return texte;
        }

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

            // Setup search functionality
            setupSearch();
        });

        // Search functionality
        function setupSearch() {
            const searchInput = document.getElementById('globalSearch');
            
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                const rows = document.querySelectorAll('.document-row');
                
                if (searchTerm === '') {
                    // Reset all rows
                    rows.forEach(row => {
                        row.style.display = '';
                        removeHighlights(row);
                    });
                    return;
                }
                
                rows.forEach(row => {
                    removeHighlights(row);
                    
                    // Get text content from relevant cells
                    const clientName = row.querySelector('.client-name').textContent.toLowerCase();
                    const documentNumber = row.cells[1].textContent.toLowerCase();
                    const documentType = row.cells[0].textContent.toLowerCase();
                    
                    // Check if search term matches
                    const matches = clientName.includes(searchTerm) || 
                                  documentNumber.includes(searchTerm) ||
                                  documentType.includes(searchTerm);
                    
                    if (matches) {
                        row.style.display = '';
                        // Highlight matching text
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

        // Load client data when selected (for invoice)
        function loadClientData() {
            const clientSelect = document.getElementById('client_id');
            const clientAddress = document.getElementById('client_address');
            const clientContact = document.getElementById('client_contact');
            const clientNif = document.getElementById('client_nif');
            const clientRc = document.getElementById('client_rc');
            const clientArticleNumber = document.getElementById('client_article_number');
            const previewClientName = document.getElementById('previewClientName');
            const previewClientAddress = document.getElementById('previewClientAddress');
            const previewClientContact = document.getElementById('previewClientContact');
            const previewClientNif = document.getElementById('previewClientNif');
            const previewClientRc = document.getElementById('previewClientRc');
            const previewClientArticleNumber = document.getElementById('previewClientArticleNumber');
            
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
                    // Parse description for palettes
                    const desc = item.description || '';
                    
                    // Extract Palettes
                    const palMatch = desc.match(/Palettes:\s*([^|]+)/);
                    if (palMatch) {
                        itemRow.querySelector('.bl-item-palettes').value = palMatch[1].trim();
                    }
                    
                    // Extract basic description (before first parenthesis or pipe)
                    const baseDescMatch = desc.match(/^([^(|]+)/);
                    if (baseDescMatch) {
                        itemRow.querySelector('.bl-item-desc').value = baseDescMatch[1].trim();
                    }
                    
                    // Unit (inside parentheses)
                    const unitMatch = desc.match(/\(([^)]+)\)/);
                    if (unitMatch) {
                        itemRow.querySelector('.bl-item-unit').value = unitMatch[1].trim();
                    }
                    
                    itemRow.querySelector('.bl-item-qty').value = item.quantity;
                    itemRow.querySelector('.bl-item-price').value = item.price || 0;
                    
                    // Update item object
                    const itemObj = blItems.find(i => i.id === itemId);
                    if (itemObj) {
                        itemObj.description = item.description;
                        itemObj.quantity = item.quantity;
                        itemObj.price = item.price || 0;
                        itemObj.palettes = palMatch ? palMatch[1].trim() : '0';
                        
                        calculateBLItemTotal(itemId);
                    }
                }
            });
            
            // Calculate totals
            calculateBL();
            
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
                tvaRate: 19,
                totalTTC: 0
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
                <td class="tva-column">
                    <input type="number" name="item_tva_rate[]" class="item-tva-rate form-control" 
                           min="0" max="100" step="0.1" value="19" placeholder="19">
                </td>
                <td class="item-total-ttc">0.00 DA</td>
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
            const tvaRateInput = itemRow.querySelector('.item-tva-rate');
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
            
            tvaRateInput.addEventListener('input', function() {
                updateInvoiceItem(itemId, 'tvaRate', parseFloat(this.value) || 0);
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
                const subtotal = item.quantity * item.price;
                const tvaAmount = subtotal * (item.tvaRate / 100);
                item.totalTTC = subtotal + tvaAmount;
                
                const totalTtcCell = document.querySelector(`#${id} .item-total-ttc`);
                totalTtcCell.textContent = formatCurrency(item.totalTTC);
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
            let totalTVA = 0;
            let totalTTC = 0;
            
            // Calculer les totaux
            invoiceItems.forEach(item => {
                const itemSubtotal = item.quantity * item.price;
                const itemTVA = itemSubtotal * (item.tvaRate / 100);
                const itemTotal = itemSubtotal + itemTVA;
                
                subtotal += itemSubtotal;
                totalTVA += itemTVA;
                totalTTC += itemTotal;
            });
            
            // Update invoice totals
            document.getElementById('subtotal').textContent = formatCurrency(subtotal);
            document.getElementById('previewSubtotal').textContent = formatCurrency(subtotal);
            
            // Calculer la TVA globale
            const tvaRate = parseFloat(document.getElementById('tva_rate').value) || 0;
            document.getElementById('tvaPercent').textContent = tvaRate;
            document.getElementById('previewTvaPercent').textContent = tvaRate;
            
            document.getElementById('tvaAmount').textContent = formatCurrency(totalTVA);
            document.getElementById('totalAmount').textContent = formatCurrency(totalTTC);
            
            document.getElementById('previewTvaAmount').textContent = formatCurrency(totalTVA);
            document.getElementById('previewTotalAmount').textContent = formatCurrency(totalTTC);
            
            // Update hidden inputs for form submission
            document.getElementById('subtotal_input').value = subtotal;
            document.getElementById('tva_amount_input').value = totalTVA;
            document.getElementById('total_input').value = totalTTC;
            
            // Update amount in letters
            updateAmountInLetters(totalTTC);
            
            // Mettre à jour l'aperçu
            updateInvoicePreview();
            
            return { subtotal, totalTVA, totalTTC };
        }

        function updateAmountInLetters(amount) {
            const amountInLetters = montantEnLettres(amount);
            const previewElement = document.getElementById('previewAmountInLetters');
            if (previewElement) {
                previewElement.innerHTML = `Arrêtée la présente facture à la somme de : <strong>${amountInLetters}</strong>`;
            }
        }

        function updateInvoicePreview() {
            // Update company info in preview
            const companyNif = document.getElementById('company_nif').value;
            const companyRc = document.getElementById('company_rc').value;
            const companyArticleNumber = document.getElementById('company_article_number').value;
            
            document.getElementById('previewCompanyNif').textContent = companyNif;
            document.getElementById('previewCompanyNif2').textContent = companyNif;
            document.getElementById('previewCompanyRc').textContent = companyRc;
            document.getElementById('previewCompanyRc2').textContent = companyRc;
            document.getElementById('previewCompanyArticleNumber').textContent = companyArticleNumber;
            document.getElementById('previewCompanyArticleNumber2').textContent = companyArticleNumber;
            
            // Update client info in preview
            const clientNif = document.getElementById('client_nif').value;
            const clientRc = document.getElementById('client_rc').value;
            const clientArticleNumber = document.getElementById('client_article_number').value;
            
            document.getElementById('previewClientNif').textContent = clientNif || '-';
            document.getElementById('previewClientRc').textContent = clientRc || '-';
            document.getElementById('previewClientArticleNumber').textContent = clientArticleNumber || '-';
            
            // Update items preview
            const previewItemsContainer = document.getElementById('previewInvoiceItems');
            previewItemsContainer.innerHTML = '';
            
            if (invoiceItems.length === 0) {
                previewItemsContainer.innerHTML = `
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px; color: #999;">
                            Aucun article ajouté
                        </td>
                    </tr>
                `;
            } else {
                invoiceItems.forEach((item, index) => {
                    const itemSubtotal = item.quantity * item.price;
                    const itemTVA = itemSubtotal * (item.tvaRate / 100);
                    const itemTotal = itemSubtotal + itemTVA;
                    
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${index + 1}</td>
                        <td>${item.description || 'Article'}</td>
                        <td>${item.unit || 'unité'}</td>
                        <td>${formatNumber(item.quantity)}</td>
                        <td>${formatCurrency(item.price)}</td>
                        <td>${item.tvaRate}%</td>
                        <td>${formatCurrency(itemTotal)}</td>
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
                quantity: 1,
                price: 0,
                palettes: 0,
                total: 0
            };
            
            blItems.push(newItem);
            
            const itemRow = document.createElement('tr');
            itemRow.id = itemId;
            itemRow.innerHTML = `
                <td>${blItemCount}</td>
                <td>
                    <input type="text" name="bl_item_description[]" class="bl-item-desc form-control" 
                           placeholder="Désignation de l'article">
                </td>
                <td class="unit-column">
                    <select name="bl_item_unit[]" class="bl-item-unit form-control">
                        <option value="unité">unité</option>
                        <option value="paquet">paquet</option>
                        <option value="mètre">mètre</option>
                        <option value="kg">kg</option>
                        <option value="rouleau">rouleau</option>
                        <option value="carton">carton</option>
                        <option value="palette">palette</option>
                    </select>
                </td>
                <td>
                    <input type="number" name="bl_item_quantity[]" class="bl-item-qty form-control" 
                           min="1" step="1" value="1" placeholder="1">
                </td>
                <td class="bl-price-col">
                    <input type="number" name="bl_item_price[]" class="bl-item-price form-control" 
                           min="0" step="0.01" value="0" placeholder="0.00">
                </td>
                <td class="bl-total-col bl-item-total">0.00 DA</td>
                <td class="bl-palettes-col">
                    <input type="number" name="bl_item_palettes[]" class="bl-item-palettes form-control" 
                           min="0" step="1" value="0" placeholder="0">
                </td>
                <td><button type="button" class="btn-icon remove-bl-item"><i class="fas fa-trash"></i></button></td>
            `;
            
            document.getElementById('blItems').appendChild(itemRow);
            
            // Ajouter les écouteurs d'événements pour cet article
            addBLItemEventListeners(itemId);
            
            // Calculer le total pour cet article
            calculateBLItemTotal(itemId);
            
            // Mettre à jour l'aperçu
            updateBLPreview();
        }

        function addBLItemEventListeners(itemId) {
            const itemRow = document.getElementById(itemId);
            const descInput = itemRow.querySelector('.bl-item-desc');
            const unitSelect = itemRow.querySelector('.bl-item-unit');
            const qtyInput = itemRow.querySelector('.bl-item-qty');
            const priceInput = itemRow.querySelector('.bl-item-price');
            const palettesInput = itemRow.querySelector('.bl-item-palettes');
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
                calculateBLItemTotal(itemId);
                calculateBL();
            });
            
            priceInput.addEventListener('input', function() {
                updateBLItem(itemId, 'price', parseFloat(this.value) || 0);
                calculateBLItemTotal(itemId);
                calculateBL();
            });
            
            palettesInput.addEventListener('input', function() {
                updateBLItem(itemId, 'palettes', parseInt(this.value) || 0);
                calculateBL();
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

        function calculateBLItemTotal(id) {
            const item = blItems.find(item => item.id === id);
            if (item) {
                item.total = item.quantity * item.price;
                
                const totalCell = document.querySelector(`#${id} .bl-item-total`);
                totalCell.textContent = formatCurrency(item.total);
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
            
            calculateBL();
            updateBLPreview();
        }

        function calculateBL() {
            let totalQty = 0;
            let totalValue = 0;
            let totalPalettes = 0;
            
            // Calculer les totaux
            blItems.forEach(item => {
                totalQty += (item.quantity || 0);
                totalValue += (item.total || 0);
                totalPalettes += parseInt(item.palettes || 0);
            });
            
            // Update BL totals
            document.getElementById('blTotalQty').textContent = totalQty;
            document.getElementById('blTotalValue').textContent = formatCurrency(totalValue);
            document.getElementById('blTotalPalettes').textContent = totalPalettes;
            
            // Update preview totals
            document.getElementById('previewBLTotalQty').textContent = totalQty;
            document.getElementById('previewBLTotalValue').textContent = formatCurrency(totalValue);
            document.getElementById('previewBLTotalPalettes').textContent = totalPalettes;
            
            return { totalQty, totalValue, totalPalettes };
        }

        function updateBLPreview() {
            // Update items preview
            const previewItemsContainer = document.getElementById('previewBLItems');
            previewItemsContainer.innerHTML = '';
            
            if (blItems.length === 0) {
                previewItemsContainer.innerHTML = `
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 20px; color: #999;">
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
                        <td>${formatCurrency(item.price)}</td>
                        <td>${formatCurrency(item.total)}</td>
                        <td>${item.palettes || 0}</td>
                    `;
                    previewItemsContainer.appendChild(row);
                });
            }
            
            // Calculate and update totals
            const totals = calculateBL();
            
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
            
            // Update client NIF, RC, Article Number when changed
            document.getElementById('client_nif').addEventListener('input', updateInvoicePreview);
            document.getElementById('client_rc').addEventListener('input', updateInvoicePreview);
            document.getElementById('client_article_number').addEventListener('input', updateInvoicePreview);
            
            document.getElementById('company_nif').addEventListener('input', updateInvoicePreview);
            document.getElementById('company_rc').addEventListener('input', updateInvoicePreview);
            document.getElementById('company_article_number').addEventListener('input', updateInvoicePreview);
            
            document.getElementById('client_id').addEventListener('change', updateInvoicePreview);
            document.getElementById('invoice_date').addEventListener('input', updateInvoicePreview);
            document.getElementById('due_date').addEventListener('input', updateInvoicePreview);
            document.getElementById('payment_terms').addEventListener('change', updateInvoicePreview);
            document.getElementById('delivery_terms').addEventListener('change', updateInvoicePreview);
            document.getElementById('notes').addEventListener('input', updateInvoicePreview);
            
            // --- BL ---
            document.getElementById('addBLItem').addEventListener('click', function() {
                addNewBLItem();
                calculateBL();
            });
            
            document.getElementById('calculateBL').addEventListener('click', calculateBL);
            
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
                
                printDocument('invoice');
            });
            
            // Prévisualiser le BL
            document.getElementById('previewBL').addEventListener('click', function() {
                const result = calculateBL();
                if (blItems.length === 0) {
                    alert('Veuillez ajouter au moins un article au BL.');
                    return;
                }
                
                fillPrintData('bl');
                document.getElementById('printBLModal').classList.add('active');
            });
            
            // Imprimer le BL
            document.getElementById('printBL').addEventListener('click', function() {
                const result = calculateBL();
                if (blItems.length === 0) {
                    alert('Veuillez ajouter au moins un article au BL.');
                    return;
                }
                
                printDocument('bl');
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
                printFromModal('invoice');
            });
            
            document.getElementById('doPrintBL').addEventListener('click', function() {
                printFromModal('bl');
            });
        }

        // Fonction d'impression directe
        function printDocument(type) {
            if (type === 'invoice') {
                const result = calculateInvoice();
                if (invoiceItems.length === 0) {
                    alert('Veuillez ajouter au moins un article à la facture.');
                    return;
                }
                
                const printContent = generatePrintContent(type);
                const printWindow = window.open('', '_blank', 'width=800,height=600');
                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title></title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            .invoice-header { text-align: center; margin-bottom: 30px; }
                            .invoice-header h2 { color: #2c3e50; }
                            .company-info, .client-info { 
                                border: 1px solid #ddd; 
                                padding: 15px; 
                                margin-bottom: 20px; 
                                border-radius: 5px;
                            }
                            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                            th { background: #2c3e50; color: white; padding: 10px; text-align: left; }
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
                            <button onclick="window.print()" style="padding: 10px 20px; background: #3498db; color: white; border: none; border-radius: 5px; cursor: pointer;">
                                Imprimer
                            </button>
                            <button onclick="window.close()" style="padding: 10px 20px; background: #95a5a6; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
                                Fermer
                            </button>
                        </div>
                        <script>
                            // Auto-print after loading
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
                
            } else if (type === 'bl') {
                const result = calculateBL();
                if (blItems.length === 0) {
                    alert('Veuillez ajouter au moins un article au BL.');
                    return;
                }
                
                const printContent = generatePrintContent(type);
                const printWindow = window.open('', '_blank', 'width=800,height=600');
                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title></title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            .bl-header { text-align: center; margin-bottom: 30px; }
                            .bl-header h2 { color: #2ecc71; }
                            .company-info, .client-info { 
                                border: 1px solid #ddd; 
                                padding: 15px; 
                                margin-bottom: 20px; 
                                border-radius: 5px;
                            }
                            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                            th { background: #2ecc71; color: white; padding: 10px; text-align: left; }
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
                            <button onclick="window.print()" style="padding: 10px 20px; background: #2ecc71; color: white; border: none; border-radius: 5px; cursor: pointer;">
                                Imprimer
                            </button>
                            <button onclick="window.close()" style="padding: 10px 20px; background: #95a5a6; color: white; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
                                Fermer
                            </button>
                        </div>
                        <script>
                            // Auto-print after loading
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
        }

        // Fonction pour générer le contenu d'impression
        function generatePrintContent(type) {
            if (type === 'invoice') {
                const result = calculateInvoice();
                const clientSelect = document.getElementById('client_id');
                const selectedOption = clientSelect.options[clientSelect.selectedIndex];
                const clientName = selectedOption?.text || '-';
                const address = selectedOption?.getAttribute('data-address') || '-';
                const phone = selectedOption?.getAttribute('data-phone') || '';
                const email = selectedOption?.getAttribute('data-email') || '';
                
                // Informations de l'entreprise
                const companyNif = document.getElementById('company_nif').value;
                const companyRc = document.getElementById('company_rc').value;
                const companyArticleNumber = document.getElementById('company_article_number').value;
                
                // Informations du client
                const clientNif = document.getElementById('client_nif').value;
                const clientRc = document.getElementById('client_rc').value;
                const clientArticleNumber = document.getElementById('client_article_number').value;
                
                let itemsHtml = '';
                invoiceItems.forEach((item, index) => {
                    const itemSubtotal = item.quantity * item.price;
                    const itemTVA = itemSubtotal * (item.tvaRate / 100);
                    const itemTotal = itemSubtotal + itemTVA;
                    
                    itemsHtml += `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${item.description || 'Article'}</td>
                            <td>${item.unit || 'unité'}</td>
                            <td>${formatNumber(item.quantity)}</td>
                            <td>${formatCurrency(item.price)}</td>
                            <td>${item.tvaRate}%</td>
                            <td>${formatCurrency(itemTotal)}</td>
                        </tr>
                    `;
                });
                
                const amountInLetters = montantEnLettres(result.totalTTC);
                
                return `
                    <div class="invoice-header">
                        
                        <img src="REM.jpg" alt="Logo" style="max-width: 150px; margin-bottom: 10px;">
                        <h1>FACTURE ${document.getElementById('invoice_number').value}</h1>
                        <p>NIF: 298619280219028 | RC: 15A0512508-19/00 | N° Article: 19018372051</p>
                        
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                        <div>
                            
                            <p><strong>Date:</strong> ${formatDate(document.getElementById('invoice_date').value)}</p>
                            
                        </div>
                        <div>
                            <p><strong>Échéance:</strong> ${formatDate(document.getElementById('due_date').value)}</p>
                            
                        </div>
                        <div>
                            <p><strong>Conditions paiement:</strong> ${document.getElementById('payment_terms').value}</p>
                            
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                        <div class="company-info" style="flex: 1; margin-right: 10px; padding:7px ;height:225px">
                            <h3>Émise par:</h3>
                            <p><strong>RYM EMBALLAGE MODERNE</strong></p>
                            <p>123 Rue Bellil Abd Allah lotissement 118 N°119, Setif 19000, Algérie</p>
                            <p>Tél: 0660639631/0560988875</p>
                            <p>Email: Rymemballagemoderne@gmail.com</p>
                            
                        </div>
                        <div class="client-info" style="flex: 1; margin-left: 10px;padding:7px ;height:225px">
                            <h3>Facturé à:</h3>
                            <p><strong>${clientName}</strong></p>
                            <p>${address}</p>
                            <p>${phone + (email ? ' | ' + email : '')}</p>
                            <p>NIF: ${clientNif || '-'}</p>
                            <p>RC: ${clientRc || '-'}</p>
                            <p>N° Article: ${clientArticleNumber || '-'}</p>
                        </div>
                    </div>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>N°</th>
                                <th>Description</th>
                                <th>Unité</th>
                                <th>Qté</th>
                                <th>Prix U.</th>
                                <th>TVA %</th>
                                <th>Total TTC</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHtml}
                        </tbody>
                    </table>
                    
                    <div style="float: right; width: 300px;height:30px">
                        <div class="total-row">
                            <span>Sous-total HT:</span>
                            <span>${formatCurrency(result.subtotal)}</span>
                        </div>
                        <div class="total-row">
                            <span>TVA (${document.getElementById('tva_rate').value}%):</span>
                            <span>${formatCurrency(result.totalTVA)}</span>
                        </div>
                        <div class="total-row grand-total">
                            <span>Total à payer TTC:</span>
                            <span>${formatCurrency(result.totalTTC)}</span>
                        </div>
                    </div>
                    
                    
                    
                    <div style="margin-top: 30px;padding:5px">
                        <p><strong>Notes:</strong> ${document.getElementById('notes').value || '-'}</p>
                        <p style="font-style: italic;">
                            
                        </p>
                    </div>
                    
                    
                    <div class="signatures">
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
                `;
                
            } else if (type === 'bl') {
                const result = calculateBL();
                const clientSelect = document.getElementById('bl_client_id');
                const selectedOption = clientSelect.options[clientSelect.selectedIndex];
                const clientName = selectedOption?.text || '-';
                const address = selectedOption?.getAttribute('data-address') || '-';
                const phone = selectedOption?.getAttribute('data-phone') || '';
                const email = selectedOption?.getAttribute('data-email') || '';
                
                let itemsHtml = '';
                blItems.forEach((item, index) => {
                    itemsHtml += `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${item.description || 'Article'}</td>
                            <td>${item.unit || 'unité'}</td>
                            <td>${formatNumber(item.quantity)}</td>
                            <td>${formatCurrency(item.price)}</td>
                            <td>${formatCurrency(item.total)}</td>
                            <td>${item.palettes || 0}</td>
                        </tr>
                    `;
                });
                
                return `
                    <div class="bl-header">
                        <img src="REM.jpg" alt="Logo" style="max-width: 150px; margin-bottom: 10px;">
                        <h1>BON DE LIVRAISON ${document.getElementById('bl_number').value} </h1>
                        
                        <p>NIF: 298619280219028 | RC: 15A0512508-19/00 | N° Article: 19018372051</p>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                        <div>
                            
                            <p><strong>Date:</strong> ${formatDate(document.getElementById('bl_date').value)}</p>
                            <p><strong>Référence:</strong> ${document.getElementById('reference').value || '-'}</p>
                        </div>
                        <div>
                            <p><strong>Livreur:</strong> ${document.getElementById('delivery_person').value || '-'}</p>
                            <p><strong>Véhicule:</strong> ${document.getElementById('vehicle').value || '-'}</p>
                            <p><strong>Conditions:</strong> ${document.getElementById('conditions').value}</p>
                        </div>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                        <div class="company-info" style="flex: 1; margin-right: 10px;">
                            <h3>Expéditeur:</h3>
                            <p><strong>RYM EMBALLAGE MODERNE</strong></p>
                            <p>123 Rue Bellil Abd Allah lotissement 118 N°119, Setif 19000, Algérie</p>
                            <p>Tél: 0660639631/0560988875</p>
                            <p>Email: Rymemballagemoderne@gmail.com</p>
                        </div>
                        <div class="client-info" style="flex: 1; margin-left: 10px;">
                            <h3>Destinataire:</h3>
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
                                <th>Nb Pal</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${itemsHtml}
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" style="text-align: right; font-weight: bold;">Totaux:</td>
                                <td style="font-weight: bold;">${result.totalQty}</td>
                                <td></td>
                                <td style="font-weight: bold;">${formatCurrency(result.totalValue)}</td>
                                <td style="font-weight: bold;">${result.totalPalettes}</td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
                        <p><strong>Observations:</strong> ${document.getElementById('bl_notes').value || '-'}</p>
                    </div>
                    
                    <div class="signatures">
                        <div class="signature-box">
                            <p><strong>Le Livreur</strong></p>
                            <div class="signature-line"></div>
                            <p>Nom: _______________________</p>
                            <p>Signature:</p>
                        </div>
                        <div class="signature-box">
                            <p><strong>Le Client</strong></p>
                            <div class="signature-line"></div>
                            <p>Nom: _______________________</p>
                            <p>Signature:</p>
                            <p>Cachet:</p>
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px; text-align: center; font-size: 0.9em; color: #666;">
                        <p>Document établi en double exemplaire - Un exemplaire pour le client, un pour l'expéditeur</p>
                    </div>
                `;
            }
        }

        // Fonction pour imprimer depuis le modal
        function printFromModal(type) {
            window.print();
        }

        // Fonction pour télécharger en PDF (simulée)
        function downloadAsPDF(type) {
            alert('La fonction de téléchargement PDF sera implémentée avec une bibliothèque comme jsPDF.');
            // Pour une implémentation réelle, vous aurez besoin de jsPDF:
            // const { jsPDF } = window.jspdf;
            // const doc = new jsPDF();
            // doc.text("Votre document PDF", 10, 10);
            // doc.save(`${type}_${new Date().getTime()}.pdf`);
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
                
                // Informations de l'entreprise
                const companyNif = document.getElementById('company_nif').value;
                const companyRc = document.getElementById('company_rc').value;
                const companyArticleNumber = document.getElementById('company_article_number').value;
                
                // Informations du client
                const clientNif = document.getElementById('client_nif').value;
                const clientRc = document.getElementById('client_rc').value;
                const clientArticleNumber = document.getElementById('client_article_number').value;
                
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
                
                // Informations de l'entreprise
                document.getElementById('printCompanyNif').textContent = companyNif;
                document.getElementById('printCompanyNif2').textContent = companyNif;
                document.getElementById('printCompanyRc').textContent = companyRc;
                document.getElementById('printCompanyRc2').textContent = companyRc;
                document.getElementById('printCompanyArticleNumber').textContent = companyArticleNumber;
                document.getElementById('printCompanyArticleNumber2').textContent = companyArticleNumber;
                
                // Informations du client
                document.getElementById('printClientName').textContent = clientName;
                document.getElementById('printClientAddress').textContent = address;
                document.getElementById('printClientContact').textContent = phone + (email ? ' | ' + email : '');
                document.getElementById('printClientNif').textContent = clientNif || '-';
                document.getElementById('printClientRc').textContent = clientRc || '-';
                document.getElementById('printClientArticleNumber').textContent = clientArticleNumber || '-';
                
                // Articles
                const printItemsContainer = document.getElementById('printInvoiceItems');
                printItemsContainer.innerHTML = '';
                
                invoiceItems.forEach((item, index) => {
                    const itemSubtotal = item.quantity * item.price;
                    const itemTVA = itemSubtotal * (item.tvaRate / 100);
                    const itemTotal = itemSubtotal + itemTVA;
                    
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${index + 1}</td>
                        <td>${item.description || 'Article'}</td>
                        <td>${item.unit || 'unité'}</td>
                        <td>${formatNumber(item.quantity)}</td>
                        <td>${formatCurrency(item.price)}</td>
                        <td>${item.tvaRate}%</td>
                        <td>${formatCurrency(itemTotal)}</td>
                    `;
                    printItemsContainer.appendChild(row);
                });
                
                // Totaux
                document.getElementById('printSubtotal').textContent = formatCurrency(result.subtotal);
                document.getElementById('printTvaPercent').textContent = document.getElementById('tva_rate').value;
                document.getElementById('printTvaAmount').textContent = formatCurrency(result.totalTVA);
                document.getElementById('printTotalAmount').textContent = formatCurrency(result.totalTTC);
                
                // Montant en lettres
                const amountInLetters = montantEnLettres(result.totalTTC);
                document.getElementById('printAmountInLetters').innerHTML = 
                    `Arrêtée la présente facture à la somme de : <strong>${amountInLetters}</strong>`;
                
                // Notes
                const notes = document.getElementById('notes').value;
                document.getElementById('printNotes').textContent = notes || '-';
                
            } else if (type === 'bl') {
                const result = calculateBL();
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
                document.getElementById('printTotalPalettes').textContent = result.totalPalettes;
                document.getElementById('printTotalValue').textContent = formatCurrency(result.totalValue);
                
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
                        <td>${formatCurrency(item.price)}</td>
                        <td>${formatCurrency(item.total)}</td>
                        <td>${item.palettes || 0}</td>
                    `;
                    printItemsContainer.appendChild(row);
                });
                
                // Totaux
                document.getElementById('printBLTotalQty').textContent = result.totalQty;
                document.getElementById('printBLTotalValue').textContent = formatCurrency(result.totalValue);
                document.getElementById('printBLTotalPalettes').textContent = result.totalPalettes;
                
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
            // Pour l'instant, rediriger vers la page avec l'ID
            window.location.href = `facture.php?invoice_id=${invoiceId}`;
        };

        window.viewInvoice = function(invoiceId) {
            window.location.href = `facture.php?invoice_id=${invoiceId}`;
        };

        window.printExistingBL = function(orderId) {
            // Pour l'instant, rediriger vers la page avec l'ID
            window.location.href = `facture.php?bl_id=${orderId}`;
        };

        window.viewBL = function(orderId) {
            window.location.href = `facture.php?bl_id=${orderId}`;
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