<?php
session_start();
header('Content-Type: application/json');
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'User not authenticated.']));
}

$host = 'sql113.infinityfree.com';
$dbname = 'if0_39898687_nextstep';
$username = 'if0_39898687';
$password = 'nextstephub01';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed.']));
}

$userId = $_SESSION['user_id'];

try {
    $response_data = [];

    // 1. Get user's main details
    $stmt = $pdo->prepare("SELECT name, email, headline, bio, skills, linkedin_url, github_url, portfolio_url FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $response_data['user'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Get user's education history
    $stmt = $pdo->prepare("SELECT * FROM education WHERE user_id = ? ORDER BY end_date DESC");
    $stmt->execute([$userId]);
    $response_data['education'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Get user's work experience
    $stmt = $pdo->prepare("SELECT * FROM experience WHERE user_id = ? ORDER BY end_date DESC");
    $stmt->execute([$userId]);
    $response_data['experience'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($response_data);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch resume data: ' . $e->getMessage()]);
}
?>

