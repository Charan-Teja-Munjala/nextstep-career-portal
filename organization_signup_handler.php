<?php
// --- 1. Get data from the form ---
$orgName = $_POST['organizationName'] ?? '';
$email = $_POST['email'] ?? '';
$website = $_POST['website'] ?? '';
$password = $_POST['password'] ?? '';

// --- 2. Validate input ---
if (empty($orgName) || empty($email) || empty($password) || empty($website)) {
    die("Error: Please fill in all fields.");
}

// --- 3. Securely hash the password ---
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// --- 4. Database Connection ---
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

// --- 5. Insert the new organization into the database ---
try {
    $stmt = $pdo->prepare("INSERT INTO organizations (name, email, website_url, password) VALUES (?, ?, ?, ?)");
    $stmt->execute([$orgName, $email, $website, $hashedPassword]);

    // --- 6. Redirect to the organization login page on success ---
    header("Location: organization_login.html");
    exit();

} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        die("Error: An organization with this email address already exists.");
    } else {
        die("An error occurred during registration. Please try again. Error: " . $e->getMessage());
    }
}
?>
