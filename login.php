<?php
// Start the session to manage user logins.
session_start();

// --- Database Connection ---
$host = 'sql113.infinityfree.com';
$dbname = 'if0_39898687_nextstep';
$username = 'if0_39898687';
$db_password = 'nextstephub01';

$conn = mysqli_connect($host, $username, $db_password, $dbname);
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// --- Get User Input ---
$email = $_POST['email'];
$password_input = $_POST['password'];

if (empty($email) || empty($password_input)) {
    die("Email and password are required.");
}

// --- Find the user by email (Securely) ---
$sql = "SELECT id, name, password FROM users WHERE email = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

// --- Verify Password and Log In ---
// This securely checks the typed password against the hashed one in the database.
if ($user && password_verify($password_input, $user['password'])) {
    
    // --- Login Successful ---
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    
    header("Location: dashboard.html");
    exit();
    
} else {
    // --- Login Failed ---
    echo "<script>
            alert('Invalid email or password. Please try again.');
            window.location.href = 'login.html';
          </script>";
}

// --- Clean up ---
mysqli_stmt_close($stmt);
mysqli_close($conn);

?>

