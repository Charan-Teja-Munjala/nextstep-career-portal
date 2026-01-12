<?php
session_start();

// --- Anti-Caching Headers ---
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// --- Check if user is logged in ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'User not authenticated.']));
}

// --- IMPORTANT: UPDATE THESE DETAILS FOR YOUR LIVE SERVER ---
$host = 'sql113.infinityfree.com';      // <-- Your MySQL Host Name
$dbname = 'if0_39898687_nextstep';      // <-- Your new Database Name
$username = 'if0_39898687';             // <-- Your new Username
$password = 'nextstephub01';// <-- Your InfinityFree account password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    // Send a more specific error for debugging
    die(json_encode(['error' => 'Database connection failed. Check your credentials.']));
}

// --- Fetch Application Data ---
header('Content-Type: application/json');
$userId = $_SESSION['user_id'];

// This query now formats the date to match what the JavaScript expects
$stmt = $pdo->prepare("
    SELECT 
        j.title AS job_title,
        o.name AS company_name,
        DATE_FORMAT(a.applied_date, '%b %d, %Y') AS applied_date_formatted,
        a.status
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    JOIN organizations o ON j.organization_id = o.id
    WHERE a.user_id = ?
    ORDER BY a.applied_date DESC
");

$stmt->execute([$userId]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($applications);
?>