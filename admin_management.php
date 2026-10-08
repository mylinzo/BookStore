<?php
session_start();

// ========== KONEKSI DATABASE ==========
$host = "localhost";
$user = "root";
$pass = "";
$db   = "toko-buku";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}

// ========== LOGOUT HANDLING DI AWAL ==========
if (isset($_GET['logout'])) {
    if (isset($_SESSION["id_user"])) {
        $user_id = $_SESSION["id_user"];
        $update_sql = "UPDATE users SET status = 'offline' WHERE id_user = ?";
        if ($update_stmt = mysqli_prepare($conn, $update_sql)) {
            mysqli_stmt_bind_param($update_stmt, "i", $user_id);
            mysqli_stmt_execute($update_stmt);
            mysqli_stmt_close($update_stmt);
        }
    }
    
    $_SESSION = array();
    session_destroy();
    
    header("Location: login.php?logout=success");
    exit;
}

// Check user login and role specifically for SUPER_ADMIN
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    if (isset($_SESSION['role'])) {
        if ($_SESSION['role'] == 'penjual') {
            header("Location: penjual_dashboard.php");
        } elseif ($_SESSION['role'] == 'pembeli') {
            header("Location: dashboard_pembeli.php");
        } else {
            header("Location: login.php");
        }
    } else {
        header("Location: login.php");
    }
    exit;
}

// ========== GET USER DATA ==========
$user_id = $_SESSION['id_user'] ?? 0;
$current_user = null;

// Get current user data
$sql_user = "SELECT * FROM users WHERE id_user = ?";
if ($stmt_user = mysqli_prepare($conn, $sql_user)) {
    mysqli_stmt_bind_param($stmt_user, "i", $user_id);
    mysqli_stmt_execute($stmt_user);
    $result_user = mysqli_stmt_get_result($stmt_user);
    $current_user = mysqli_fetch_assoc($result_user);
    mysqli_stmt_close($stmt_user);
}

// Get only admin users (super_admin role)
$admins = [];
$sql_admins = "SELECT * FROM users WHERE role = 'super_admin' ORDER BY nama";
$result_admins = mysqli_query($conn, $sql_admins);
if ($result_admins) {
    $admins = mysqli_fetch_all($result_admins, MYSQLI_ASSOC);
}

// Handle Add/Edit Admin
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $nama = mysqli_real_escape_string($conn, $_POST['nama'] ?? '');
        $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
        $role = 'super_admin'; // Always set to super_admin
        $status = mysqli_real_escape_string($conn, $_POST['status'] ?? 'offline');
        
        if ($action == 'add_admin') {
            if (!isset($_POST['password']) || empty($_POST['password'])) {
                header("Location: admin_management.php?error=Password is required");
                exit;
            }
            
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (nama, email, password, role, status) VALUES (?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssss", $nama, $email, $password, $role, $status);
            
            try {
                mysqli_stmt_execute($stmt);
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) { // Duplicate entry
                    header("Location: admin_management.php?error=Email already exists");
                    exit;
                } else {
                    header("Location: admin_management.php?error=" . urlencode($e->getMessage()));
                    exit;
                }
            }

            header("Location: admin_management.php?success=Admin added successfully");
            exit;
        } elseif ($action == 'edit_admin' && isset($_POST['id_user'])) {
            $id_user = $_POST['id_user'];
            
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $sql = "UPDATE users SET nama = ?, email = ?, password = ?, status = ? WHERE id_user = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ssssi", $nama, $email, $password, $status, $id_user);
            } else {
                $sql = "UPDATE users SET nama = ?, email = ?, status = ? WHERE id_user = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "sssi", $nama, $email, $status, $id_user);
            }
            
            try {
                mysqli_stmt_execute($stmt);
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) { // Duplicate entry
                    header("Location: admin_management.php?error=Email already exists");
                    exit;
                } else {
                    header("Location: admin_management.php?error=" . urlencode($e->getMessage()));
                    exit;
                }
            }

            header("Location: admin_management.php?success=Admin updated successfully");
            exit;
        } elseif ($action == 'delete_admin' && isset($_POST['id_user'])) {
            $id_user = $_POST['id_user'];
            
            // Check if user is trying to delete themselves
            if ($id_user == $_SESSION['id_user']) {
                header("Location: admin_management.php?error=Cannot delete your own account");
                exit;
            }
            
            // Check if this is the last admin
            $sql_count = "SELECT COUNT(*) as count FROM users WHERE role = 'super_admin'";
            $result_count = mysqli_query($conn, $sql_count);
            if ($result_count) {
                $admin_count = mysqli_fetch_assoc($result_count)['count'];
                
                if ($admin_count <= 1) {
                    header("Location: admin_management.php?error=Cannot delete the last admin account");
                    exit;
                }
            }
            
            $sql = "DELETE FROM users WHERE id_user = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $id_user);
            mysqli_stmt_execute($stmt);
            
            header("Location: admin_management.php?success=Admin deleted successfully");
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management - Pustaka Aruna</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Crimson+Text:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --vintage-cream: #f5f1e8;
            --vintage-brown: #8b4513;
            --vintage-dark: #654321;
            --vintage-gold: #d4af37;
            --vintage-red: #8b0000;
            --vintage-green: #556b2f;
            --vintage-paper: #f8f4e6;
            --vintage-border: #c9a87a;
            --sidebar-width: 250px;
            --header-height: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--vintage-cream);
            color: var(--vintage-dark);
            display: flex;
            min-height: 100vh;
            background-image: url('data:image/svg+xml;utf8,<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><path d="M0,0 L100,100" stroke="%23c9a87a" stroke-width="0.5" opacity="0.3"/><path d="M100,0 L0,100" stroke="%23c9a87a" stroke-width="0.5" opacity="0.3"/></svg>');
        }

        /* Sidebar Styles - Vintage Design */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--vintage-paper);
            border-right: 3px solid var(--vintage-border);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 100;
            box-shadow: 5px 0 15px rgba(139, 69, 19, 0.1);
            background-image: url('data:image/svg+xml;utf8,<svg width="20" height="20" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><rect width="20" height="20" fill="%23f8f4e6"/><path d="M0,0 L20,20" stroke="%23c9a87a" stroke-width="0.3" opacity="0.2"/><path d="M20,0 L0,20" stroke="%23c9a87a" stroke-width="0.3" opacity="0.2"/></svg>');
        }

        .logo-container {
            padding: 25px 20px;
            border-bottom: 2px dashed var(--vintage-border);
            text-align: center;
            background: linear-gradient(135deg, rgba(139, 69, 19, 0.1), rgba(212, 175, 55, 0.1));
        }

        .logo {
            font-family: 'Crimson Text', serif;
            font-size: 28px;
            font-weight: 600;
            color: var(--vintage-brown);
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            letter-spacing: 1px;
        }

        .logo i {
            color: var(--vintage-gold);
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        .logo-text::after {
            content: '';
            position: absolute;
            bottom: -8px;
            width: 60%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--vintage-gold), transparent);
        }

        .logo-subtitle {
            font-size: 11px;
            font-weight: 400;
            color: var(--vintage-brown);
            margin-top: 5px;
            letter-spacing: 2px;
            text-transform: uppercase;
            opacity: 0.8;
        }

        .user-profile {
            padding: 25px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            border-bottom: 2px dashed var(--vintage-border);
            background: rgba(245, 241, 232, 0.5);
        }

        .avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--vintage-brown), var(--vintage-gold));
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--vintage-paper);
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 15px;
            border: 3px solid var(--vintage-gold);
            box-shadow: 0 4px 15px rgba(139, 69, 19, 0.3);
        }

        .user-name {
            font-family: 'Crimson Text', serif;
            font-weight: 600;
            font-size: 18px;
            margin-bottom: 8px;
            color: var(--vintage-dark);
            text-align: center;
        }

        .user-role {
            font-size: 11px;
            color: var(--vintage-brown);
            background-color: var(--vintage-cream);
            padding: 5px 15px;
            border-radius: 20px;
            border: 1px solid var(--vintage-border);
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .nav-menu {
            flex: 1;
            padding: 25px 0;
            overflow-y: auto;
        }

        .nav-item {
            padding: 16px 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: var(--vintage-dark);
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            margin: 5px 15px;
            border-radius: 0 8px 8px 0;
            position: relative;
            overflow: hidden;
        }

        .nav-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 0;
            height: 100%;
            background: linear-gradient(90deg, rgba(139, 69, 19, 0.1), transparent);
            transition: width 0.3s ease;
        }

        .nav-item:hover::before {
            width: 100%;
        }

        .nav-item:hover {
            background-color: rgba(245, 241, 232, 0.7);
            color: var(--vintage-red);
            transform: translateX(5px);
        }

        .nav-item.active {
            background-color: var(--vintage-cream);
            color: var(--vintage-red);
            border-left-color: var(--vintage-gold);
            box-shadow: inset 3px 0 8px rgba(139, 69, 19, 0.2);
        }

        .nav-item.active::before {
            width: 100%;
        }

        .nav-item i {
            width: 22px;
            text-align: center;
            font-size: 18px;
            color: var(--vintage-brown);
        }

        .nav-item.active i {
            color: var(--vintage-red);
        }

        .nav-text {
            font-size: 15px;
            font-weight: 500;
            position: relative;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 2px dashed var(--vintage-border);
            background: rgba(139, 69, 19, 0.05);
        }

        .sidebar-footer .nav-item {
            margin: 0;
            border-radius: 8px;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 25px;
            overflow-x: hidden;
            background-color: var(--vintage-paper);
            min-height: 100vh;
            border-left: 1px solid var(--vintage-border);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--vintage-border);
            position: relative;
        }

        .header::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100px;
            height: 3px;
            background: var(--vintage-gold);
        }

        .page-title {
            font-family: 'Crimson Text', serif;
            font-size: 32px;
            font-weight: 600;
            color: var(--vintage-brown);
            letter-spacing: 0.5px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .search-container {
            position: relative;
            width: 300px;
        }

        .search-input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border-radius: 8px;
            border: 2px solid var(--vintage-border);
            font-size: 14px;
            transition: all 0.3s ease;
            background-color: var(--vintage-cream);
            color: var(--vintage-dark);
            font-family: 'Poppins', sans-serif;
        }

        .search-input::placeholder {
            color: rgba(139, 69, 19, 0.6);
            font-style: italic;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--vintage-gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--vintage-brown);
        }

        /* Alert Messages */
        .alert {
            padding: 18px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .alert::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 8px;
            height: 100%;
            background-color: currentColor;
            opacity: 0.3;
        }

        .alert-success {
            background-color: rgba(85, 107, 47, 0.1);
            color: var(--vintage-green);
            border-color: var(--vintage-green);
        }

        .alert-error {
            background-color: rgba(139, 0, 0, 0.1);
            color: var(--vintage-red);
            border-color: var(--vintage-red);
        }

        .close-alert {
            background: none;
            border: none;
            font-size: 22px;
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
            transition: opacity 0.3s ease;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close-alert:hover {
            opacity: 1;
            background-color: rgba(0, 0, 0, 0.1);
        }

        /* Tables */
        .table-container {
            background: linear-gradient(135deg, var(--vintage-paper), var(--vintage-cream));
            border-radius: 12px;
            padding: 0;
            box-shadow: 0 6px 20px rgba(139, 69, 19, 0.1);
            border: 2px solid var(--vintage-border);
            position: relative;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .table-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--vintage-gold), var(--vintage-brown));
        }

        .table-header {
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--vintage-border);
            background: rgba(139, 69, 19, 0.05);
        }

        .table-title {
            font-family: 'Crimson Text', serif;
            font-size: 20px;
            font-weight: 600;
            color: var(--vintage-brown);
        }

        .table-actions {
            display: flex;
            gap: 10px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background-color: rgba(201, 168, 122, 0.1);
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--vintage-brown);
            font-size: 14px;
            border-bottom: 2px solid var(--vintage-border);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 15px;
            font-size: 14px;
            border-bottom: 1px solid var(--vintage-border);
            color: var(--vintage-dark);
        }

        tbody tr:hover {
            background-color: rgba(245, 241, 232, 0.5);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
            min-width: 70px;
            text-align: center;
            border: 1px solid;
        }

        .status-online {
            background-color: rgba(85, 107, 47, 0.1);
            color: var(--vintage-green);
            border-color: var(--vintage-green);
        }

        .status-offline {
            background-color: rgba(139, 69, 19, 0.1);
            color: var(--vintage-brown);
            border-color: var(--vintage-border);
        }

        .role-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
            min-width: 100px;
            text-align: center;
            border: 1px solid;
        }

        .role-super_admin {
            background-color: rgba(139, 0, 0, 0.1);
            color: var(--vintage-red);
            border-color: var(--vintage-red);
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid;
            font-size: 14px;
            background-color: var(--vintage-paper);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(139, 69, 19, 0.2);
        }

        .edit-btn {
            color: var(--vintage-gold);
            border-color: var(--vintage-gold);
        }

        .edit-btn:hover {
            background-color: rgba(212, 175, 55, 0.1);
        }

        .detail-btn {
            color: var(--vintage-green);
            border-color: var(--vintage-green);
        }

        .detail-btn:hover {
            background-color: rgba(85, 107, 47, 0.1);
        }

        .delete-btn {
            color: var(--vintage-red);
            border-color: var(--vintage-red);
        }

        .delete-btn:hover {
            background-color: rgba(139, 0, 0, 0.1);
        }

        .action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            position: relative;
            overflow: hidden;
            font-family: 'Poppins', sans-serif;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s ease;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--vintage-gold), var(--vintage-brown));
            color: var(--vintage-paper);
            border: 2px solid var(--vintage-gold);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--vintage-brown), var(--vintage-gold));
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(212, 175, 55, 0.4);
        }

        .btn-secondary {
            background-color: var(--vintage-cream);
            color: var(--vintage-dark);
            border: 2px solid var(--vintage-border);
        }

        .btn-secondary:hover {
            background-color: rgba(201, 168, 122, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(139, 69, 19, 0.2);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(3px);
        }

        .modal-content {
            background: linear-gradient(135deg, var(--vintage-paper), var(--vintage-cream));
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            overflow: hidden;
            box-shadow: 0 15px 50px rgba(139, 69, 19, 0.3);
            border: 3px solid var(--vintage-border);
            position: relative;
        }

        .modal-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--vintage-gold), var(--vintage-brown));
        }

        .modal-header {
            padding: 20px;
            border-bottom: 2px solid var(--vintage-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(139, 69, 19, 0.05);
        }

        .modal-title {
            font-family: 'Crimson Text', serif;
            font-size: 20px;
            font-weight: 600;
            color: var(--vintage-brown);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            color: var(--vintage-brown);
            cursor: pointer;
            transition: color 0.3s ease;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close-modal:hover {
            color: var(--vintage-red);
            background-color: rgba(139, 69, 19, 0.1);
        }

        .modal-body {
            padding: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--vintage-brown);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border-radius: 8px;
            border: 2px solid var(--vintage-border);
            font-size: 14px;
            transition: all 0.3s ease;
            background-color: var(--vintage-cream);
            color: var(--vintage-dark);
            font-family: 'Poppins', sans-serif;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--vintage-gold);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
        }

        .form-control[readonly] {
            background-color: rgba(201, 168, 122, 0.1);
            cursor: not-allowed;
        }

        .form-text {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: var(--vintage-brown);
            font-style: italic;
        }

        .modal-footer {
            padding: 20px;
            border-top: 2px solid var(--vintage-border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: rgba(139, 69, 19, 0.05);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--vintage-red), #a52a2a);
            color: white;
            border: 2px solid var(--vintage-red);
        }

        .btn-danger:hover {
            background: linear-gradient(135deg, #a52a2a, var(--vintage-red));
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(139, 0, 0, 0.4);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                width: 70px;
            }
            
            .logo-text span:not(.logo-subtitle),
            .nav-text,
            .user-name,
            .user-role {
                display: none;
            }
            
            .logo {
                justify-content: center;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .user-profile {
                padding: 15px 5px;
            }
            
            .avatar {
                width: 50px;
                height: 50px;
                font-size: 20px;
            }
            
            .page-title {
                font-size: 28px;
            }
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            
            .search-container {
                width: 100%;
            }
            
            .table-container {
                overflow-x: auto;
            }
            
            table {
                min-width: 700px;
            }
            
            .table-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .table-actions {
                width: 100%;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .modal-content {
                width: 95%;
                margin: 10px;
            }
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--vintage-cream);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--vintage-brown);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--vintage-gold);
        }

        /* Animation for modals */
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-content {
            animation: modalFadeIn 0.3s ease-out;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo-container">
            <a href="dashboard_admin.php" class="logo">
                <i class="fas fa-book-open"></i>
                <div class="logo-text">
                    <span>Pustaka Aruna</span>
                    <span class="logo-subtitle">Admin Panel</span>
                </div>
            </a>
        </div>
        
        <div class="user-profile">
            <div class="avatar">
                <?php 
                $firstLetter = 'A';
                if (isset($current_user['nama']) && !empty($current_user['nama'])) {
                    $firstLetter = strtoupper(substr($current_user['nama'], 0, 1));
                }
                echo $firstLetter;
                ?>
            </div>
            <div class="user-name">
                <?php 
                if (isset($current_user['nama'])) {
                    echo htmlspecialchars($current_user['nama']);
                } else {
                    echo 'Administrator';
                }
                ?>
            </div>
            <div class="user-role">Super Admin</div>
        </div>
        
        <div class="nav-menu">
            <a href="dashboard_admin.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i>
                <span class="nav-text">Dashboard</span>
            </a>
            <a href="pembeli_management.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span class="nav-text">Pembeli</span>
            </a>
            <a href="penjual_management.php" class="nav-item">
                <i class="fas fa-user-tie"></i>
                <span class="nav-text">Account Penjual</span>
            </a>
            <a href="admin_profile.php" class="nav-item">
                <i class="fas fa-user"></i>
                <span class="nav-text">Profil Saya</span>
            </a>
        </div>
        
        <div class="sidebar-footer">
            <a href="#" class="nav-item">
                <i class="fas fa-question-circle"></i>
                <span class="nav-text">Help</span>
            </a>
            <a href="#" class="nav-item" id="signout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span class="nav-text">Sign Out</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <div class="page-title">Management Admin</div>
            <div class="search-container">
                <i class="fas fa-search search-icon"></i>
                <input type="text" class="search-input" placeholder="Cari admin..." id="search-input">
            </div>
        </div>
        
        <!-- Alert Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                <span><?php echo htmlspecialchars($_GET['success']); ?></span>
                <button class="close-alert">&times;</button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <span><?php echo htmlspecialchars($_GET['error']); ?></span>
                <button class="close-alert">&times;</button>
            </div>
        <?php endif; ?>
        
        <!-- Admin Management Table -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">Daftar Administrator Sistem</div>
                <div class="table-actions">
                    <?php if (count($admins) < 1): ?>
                    <button class="btn btn-primary" id="add-admin-btn">
                        <i class="fas fa-plus"></i> Tambah Admin
                    </button>
                    <?php else: ?>
                    <div style="color:#8b4513; font-size:14px; font-weight:600;">
                        <i class="fas fa-lock"></i> Hanya 1 Admin (Maksimal)
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody id="admin-table-body">
                    <?php foreach ($admins as $admin): ?>
                    <tr>
                        <td><?php echo $admin['id_user']; ?></td>
                        <td><?php echo htmlspecialchars($admin['nama']); ?></td>
                        <td><?php echo htmlspecialchars($admin['email']); ?></td>
                        <td>
                            <span class="role-badge role-super_admin">Super Admin</span>
                        </td>
                        <td>
                            <?php 
                            $statusClass = $admin['status'] === 'online' ? 'status-online' : 'status-offline';
                            $statusText = $admin['status'] === 'online' ? 'Online' : 'Offline';
                            ?>
                            <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($admin['created_at'])); ?></td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn edit-btn" data-id="<?php echo $admin['id_user']; ?>" title="Edit Admin">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn detail-btn" data-id="<?php echo $admin['id_user']; ?>" title="Lihat Detail">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="action-btn delete-btn" data-id="<?php echo $admin['id_user']; ?>" 
                                    <?php if ($admin['id_user'] == $_SESSION['id_user']) echo 'disabled title="Tidak dapat menghapus akun sendiri"'; else echo 'title="Hapus Admin"'; ?>>
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Admin Detail Modal -->
    <div class="modal" id="admin-detail-modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Detail Administrator</div>
                <button class="close-modal" id="close-detail-modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nama</label>
                    <input type="text" class="form-control" id="detail-nama" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" id="detail-email" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <input type="text" class="form-control" id="detail-role" value="Super Admin" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <input type="text" class="form-control" id="detail-status" readonly>
                </div>
                <div class="form-group">
                    <label class="form-label">Dibuat Pada</label>
                    <input type="text" class="form-control" id="detail-created" readonly>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="close-detail-btn">Tutup</button>
            </div>
        </div>
    </div>
    
    <!-- Add/Edit Admin Modal -->
    <div class="modal" id="admin-form-modal">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <div class="modal-title" id="form-modal-title">Tambah Admin Baru</div>
                    <button type="button" class="close-modal" id="close-form-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="form-action" value="add_admin">
                    <input type="hidden" name="id_user" id="admin-id">
                    <div class="form-group">
                        <label class="form-label">Nama *</label>
                        <input type="text" class="form-control" id="form-nama" name="nama" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" id="form-email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" class="form-control" id="form-password" name="password" required>
                        <small class="form-text" id="password-help">Wajib diisi untuk admin baru</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status *</label>
                        <select class="form-control" id="form-status" name="status" required>
                            <option value="offline">Offline</option>
                            <option value="online">Online</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancel-form-btn">Batal</button>
                    <button type="submit" class="btn btn-primary" id="submit-form-btn">Simpan Admin</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal" id="delete-confirm-modal">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <div class="modal-title">Konfirmasi Hapus</div>
                    <button type="button" class="close-modal" id="close-delete-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p style="margin-bottom: 15px; color: var(--vintage-dark);">Apakah Anda yakin ingin menghapus administrator ini? Tindakan ini tidak dapat dibatalkan.</p>
                    <p style="font-weight: 600; color: var(--vintage-red);" id="delete-admin-name"></p>
                    <input type="hidden" name="action" value="delete_admin">
                    <input type="hidden" name="id_user" id="delete-admin-id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancel-delete-btn">Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus Admin</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // User data from PHP
        const admins = <?php echo json_encode($admins); ?>;
        
        // DOM Elements
        const adminTableBody = document.getElementById('admin-table-body');
        const adminDetailModal = document.getElementById('admin-detail-modal');
        const adminFormModal = document.getElementById('admin-form-modal');
        const deleteConfirmModal = document.getElementById('delete-confirm-modal');
        const searchInput = document.getElementById('search-input');
        const passwordHelp = document.getElementById('password-help');
        
        // Filter admins based on search input
        function filterAdmins(searchTerm) {
            searchTerm = searchTerm.toLowerCase();
            const rows = adminTableBody.getElementsByTagName('tr');
            
            for (let row of rows) {
                const cells = row.getElementsByTagName('td');
                let match = false;
                
                for (let cell of cells) {
                    if (cell.textContent.toLowerCase().includes(searchTerm)) {
                        match = true;
                        break;
                    }
                }
                
                row.style.display = match ? '' : 'none';
            }
        }
        
        // Open admin detail modal
        function openAdminDetailModal(adminId) {
            const admin = admins.find(u => u.id_user == adminId);
            if (!admin) return;
            
            document.getElementById('detail-nama').value = admin.nama;
            document.getElementById('detail-email').value = admin.email;
            document.getElementById('detail-status').value = admin.status === 'online' ? 'Online' : 'Offline';
            document.getElementById('detail-created').value = new Date(admin.created_at).toLocaleDateString('id-ID', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            adminDetailModal.style.display = 'flex';
        }
        
        // Open edit admin modal
        function openEditAdminModal(adminId) {
            const admin = admins.find(u => u.id_user == adminId);
            if (!admin) return;
            
            document.getElementById('form-modal-title').textContent = 'Edit Administrator';
            document.getElementById('form-action').value = 'edit_admin';
            document.getElementById('admin-id').value = admin.id_user;
            document.getElementById('form-nama').value = admin.nama;
            document.getElementById('form-email').value = admin.email;
            document.getElementById('form-password').value = '';
            document.getElementById('form-password').required = false;
            document.getElementById('form-status').value = admin.status;
            passwordHelp.textContent = 'Kosongkan jika tidak ingin mengubah password';
            
            adminFormModal.style.display = 'flex';
        }
        
        // Open add admin modal
        function openAddAdminModal() {
            document.getElementById('form-modal-title').textContent = 'Tambah Admin Baru';
            document.getElementById('form-action').value = 'add_admin';
            document.getElementById('admin-form-modal').querySelector('form').reset();
            document.getElementById('admin-id').value = '';
            document.getElementById('form-status').value = 'offline';
            document.getElementById('form-password').required = true;
            passwordHelp.textContent = 'Wajib diisi untuk admin baru';
            
            adminFormModal.style.display = 'flex';
        }
        
        // Open delete confirmation modal
        function openDeleteConfirmModal(adminId) {
            const admin = admins.find(u => u.id_user == adminId);
            if (!admin) return;
            
            document.getElementById('delete-admin-name').textContent = `${admin.nama} (${admin.email})`;
            document.getElementById('delete-admin-id').value = adminId;
            
            deleteConfirmModal.style.display = 'flex';
        }
        
        // Close modals
        function closeAllModals() {
            adminDetailModal.style.display = 'none';
            adminFormModal.style.display = 'none';
            deleteConfirmModal.style.display = 'none';
        }
        
        // Event listeners for modal close buttons
        document.getElementById('close-detail-modal').addEventListener('click', closeAllModals);
        document.getElementById('close-detail-btn').addEventListener('click', closeAllModals);
        document.getElementById('close-form-modal').addEventListener('click', closeAllModals);
        document.getElementById('cancel-form-btn').addEventListener('click', closeAllModals);
        document.getElementById('close-delete-modal').addEventListener('click', closeAllModals);
        document.getElementById('cancel-delete-btn').addEventListener('click', closeAllModals);
        
        // Event listener for add admin button
        document.getElementById('add-admin-btn').addEventListener('click', openAddAdminModal);
        
        // Event listener for sign out button
        document.getElementById('signout-btn').addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Anda yakin ingin keluar?')) {
                window.location.href = 'dashboard_admin.php?logout=true';
            }
        });
        
        // Event listener for search input
        searchInput.addEventListener('input', function() {
            filterAdmins(this.value);
        });
        
        // Event listener for closing alerts
        document.querySelectorAll('.close-alert').forEach(btn => {
            btn.addEventListener('click', function() {
                this.parentElement.style.display = 'none';
            });
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === adminDetailModal || 
                event.target === adminFormModal || 
                event.target === deleteConfirmModal) {
                closeAllModals();
            }
        });
        
        // Attach event listeners to action buttons
        document.addEventListener('DOMContentLoaded', function() {
            // Edit buttons
            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const adminId = parseInt(this.getAttribute('data-id'));
                    if (!this.disabled) {
                        openEditAdminModal(adminId);
                    }
                });
            });
            
            // Detail buttons
            document.querySelectorAll('.detail-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const adminId = parseInt(this.getAttribute('data-id'));
                    openAdminDetailModal(adminId);
                });
            });
            
            // Delete buttons
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const adminId = parseInt(this.getAttribute('data-id'));
                    if (!this.disabled) {
                        openDeleteConfirmModal(adminId);
                    }
                });
            });
        });
    </script>
</body>
</html>

<?php
// Tutup koneksi database jika masih terbuka
if (isset($conn)) {
    mysqli_close($conn);
}
?>