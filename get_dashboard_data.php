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
$password = 'nextstephub01'; // <-- Your InfinityFree account password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed.']));
}

// --- Fetch All Dashboard Data ---
header('Content-Type: application/json');
$userId = $_SESSION['user_id'];

// 1. Get user's name and avatar
$stmt = $pdo->prepare("SELECT name, avatar_url FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Get stats
$coursesCount = $pdo->query("SELECT COUNT(*) FROM enrollments WHERE user_id = {$userId}")->fetchColumn();
$jobsCount = $pdo->query("SELECT COUNT(*) FROM applications WHERE user_id = {$userId}")->fetchColumn();
$roadmapsCount = $pdo->query("SELECT COUNT(*) FROM user_roadmaps WHERE user_id = {$userId}")->fetchColumn();
// saved_internships count is a placeholder for now as the feature is not built
$internshipsCount = 0; 

// 3. Get active roadmap details (this is the new, upgraded part)
$roadmapStmt = $pdo->prepare("
    SELECT 
        r.title AS roadmap_title,
        rs_current.title AS next_step,
        (SELECT COUNT(*) FROM roadmap_steps WHERE roadmap_id = ur.roadmap_id AND step_number < rs_current.step_number) AS steps_completed,
        (SELECT COUNT(*) FROM roadmap_steps WHERE roadmap_id = ur.roadmap_id) AS total_steps
    FROM user_roadmaps ur
    JOIN roadmaps r ON ur.roadmap_id = r.id
    JOIN roadmap_steps rs_current ON ur.current_step_id = rs_current.id
    WHERE ur.user_id = ?
");
$roadmapStmt->execute([$userId]);
$activeRoadmap = $roadmapStmt->fetch(PDO::FETCH_ASSOC);

$roadmapData = null;
if ($activeRoadmap && $activeRoadmap['total_steps'] > 0) {
    $progress = ($activeRoadmap['steps_completed'] / $activeRoadmap['total_steps']) * 100;
    $roadmapData = [
        "title" => $activeRoadmap['roadmap_title'],
        "nextStep" => "Next Step: " . $activeRoadmap['next_step'],
        "progress" => round($progress)
    ];
}


// --- Assemble and send the final JSON response ---
$dashboardData = [
    "userName" => $user['name'],
    "userAvatarUrl" => $user['avatar_url'],
    "stats" => [
        "courses" => (int)$coursesCount,
        "jobs" => (int)$jobsCount,
        "roadmaps" => (int)$roadmapsCount,
        "internships" => $internshipsCount
    ],
    "roadmap" => $roadmapData
];

echo json_encode($dashboardData);
?>