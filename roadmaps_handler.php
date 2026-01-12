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
$host = 'sql113.infinityfree.com';
$dbname = 'if0_39898687_nextstep';
$username = 'if0_39898687';
$password = 'nextstephub01';// <-- Your InfinityFree account password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed.']));
}

// --- Fetch Roadmap Data ---
header('Content-Type: application/json');
$userId = $_SESSION['user_id'];

// This complex query gets all roadmaps and checks if the current user has started them.
$stmt = $pdo->prepare("
    SELECT 
        r.id,
        r.title,
        r.description,
        r.thumbnail_url,
        CASE WHEN ur.id IS NOT NULL THEN 1 ELSE 0 END AS is_started
    FROM roadmaps r
    LEFT JOIN user_roadmaps ur ON r.id = ur.roadmap_id AND ur.user_id = ?
");

$stmt->execute([$userId]);
$roadmaps = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($roadmaps);
?>
