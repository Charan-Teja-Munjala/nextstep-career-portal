<?php
// --- 1. Get User Input ---
$name = $_POST['fullName'];
$email = $_POST['email'];
$password = $_POST['password'];

// --- 2. Validate Input ---
if (empty($name) || empty($email) || empty($password)) {
    die("Error: Please fill in all fields.");
}

// --- 3. Securely Hash the Password ---
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// --- NEW: Generate Avatar URL from Initials ---
$words = explode(" ", $name);
$initials = "";
if (count($words) >= 2) {
    // Use first letter of the first two words (e.g., "Teja Kumar" -> "TK")
    $initials = strtoupper(substr($words[0], 0, 1) . substr(end($words), 0, 1));
} else {
    // Use the first letter of a single word name (e.g., "Teja" -> "T")
    $initials = strtoupper(substr($name, 0, 1));
}
// Create a URL for a placeholder image with the initials
$avatarUrl = "https://placehold.co/100x100/4F46E5/FFFFFF?text=" . urlencode($initials);


// --- 4. Database Connection Details ---
$host = 'sql113.infinityfree.com';
$dbname = 'if0_39898687_nextstep';
$username = 'if0_39898687';
$db_password = 'nextstephub01';

// --- 5. Connect to the Database using MySQLi ---
$conn = mysqli_connect($host, $username, $db_password, $dbname);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// --- 6. Prepare and Execute the SQL to Insert the New User ---
// UPDATED: The SQL now includes the new avatar_url column
$sql = "INSERT INTO users (name, email, password, avatar_url) VALUES (?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sql);

// UPDATED: Bind the new avatar URL to the query ("ssss" means four string variables)
mysqli_stmt_bind_param($stmt, "ssss", $name, $email, $hashedPassword, $avatarUrl);

if (mysqli_stmt_execute($stmt)) {
    // --- Success! ---
    header("Location: login.html");
    exit();
} else {
    // --- Handle Errors ---
    if (mysqli_errno($conn) == 1062) {
        die("Error: An account with this email address already exists.");
    } else {
        die("An error occurred during registration. Please try again.");
    }
}

// --- 7. Clean Up ---
mysqli_stmt_close($stmt);
mysqli_close($conn);

?>

