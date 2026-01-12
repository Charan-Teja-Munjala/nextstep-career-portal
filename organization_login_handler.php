<?php
session_start();

// --- 1. Get data from the form ---
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// --- 2. Validate input ---
if (empty($email) || empty($password)) {
    die("Error: Please enter both email and password.");
}

// --- 3. Database Connection ---
$host = 'sql113.infinityfree.com';
$dbname = 'if0_39898687_nextstep';
$username = 'if0_39898687';
$db_password = 'nextstephub01';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --- 4. Find the organization by email ---
try {
    $stmt = $pdo->prepare("SELECT id, name, password, is_approved FROM organizations WHERE email = ?");
    $stmt->execute([$email]);
    $organization = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- 5. Verify password and check if organization exists ---
    if ($organization && password_verify($password, $organization['password'])) {
        
        // Optional Check: You can enforce admin approval here in the future
        // if (!$organization['is_approved']) {
        //     die("Your organization's account is pending approval from the site administrator.");
        // }

        // --- 6. Set session variables for the organization ---
        $_SESSION['organization_id'] = $organization['id'];
        $_SESSION['organization_name'] = $organization['name'];
        
        // --- 7. Redirect to the organization dashboard ---
        header("Location: organization_dashboard.html");
        exit();
    } else {
        // --- If login fails ---
        echo "Invalid email or password. Please try again.";
        // You could redirect back to the login page with an error message
        // header("Location: organization_login.html?error=1");
    }

} catch (PDOException $e) {
    die("An error occurred during login. Please try again. Error: " . $e->getMessage());
}
?>
