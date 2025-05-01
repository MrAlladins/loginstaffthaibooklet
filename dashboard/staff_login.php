<?php
// Simple staff login file
session_start();
require_once 'includes/db_connect.php';
require_once '../Auth.php';

$auth = new Auth($pdo);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $staff = $auth->authenticateStaff($username, $password);
    
    if ($staff) {
        $_SESSION['staff_id'] = $staff['id'];
        $_SESSION['user_id'] = $staff['user_id'];
        $_SESSION['staff_name'] = $staff['name'];
        $_SESSION['shop_id'] = $staff['shop_id'];
        $_SESSION['shop_name'] = $staff['shop_name'];
        $_SESSION['staff_role'] = $staff['role'];
        $_SESSION['staff_login'] = true;
        
        header('Location: staff_dashboard.php');
        exit;
    } else {
        $error = 'Login failed';
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Staff Login</title>
</head>
<body>
    <h1>Staff Login</h1>
    
    <?php if ($error) echo "<p>$error</p>"; ?>
    
    <form method="post">
        <p>
            <label>Username:</label>
            <input type="text" name="username">
        </p>
        <p>
            <label>Password:</label>
            <input type="password" name="password">
        </p>
        <p>
            <button type="submit">Login</button>
        </p>
    </form>
    
    <p><a href="index.php">Back to home</a></p>
</body>
</html>
