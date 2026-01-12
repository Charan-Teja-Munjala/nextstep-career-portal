<?php
session_start();

// --- Anti-Caching Headers ---
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// --- Check if user is logged in ---
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'You must be logged in to apply.']));
}

// --- IMPORTANT: UPDATE THESE DETAILS FOR YOUR LIVE SERVER ---
$host = 'sql113.infinityfree.com';      // <-- Your MySQL Host Name
$dbname = 'if0_39898687_nextstep';      // <-- Your new Database Name
$username = 'if0_39898687';             // <-- Your new Username
$password = 'nextstephub01';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database connection error.']));
}

// --- Handle Application Logic ---
header('Content-Type: application/json');
$userId = $_SESSION['user_id'];

// Get job_id from the incoming POST request
$data = json_decode(file_get_contents('php://input'), true);
$jobId = $data['job_id'] ?? null;

if (!$jobId) {
    die(json_encode(['success' => false, 'message' => 'Invalid job specified.']));
}

// Check if user has already applied for this job
$stmt = $pdo->prepare("SELECT id FROM applications WHERE user_id = ? AND job_id = ?");
$stmt->execute([$userId, $jobId]);
if ($stmt->fetch()) {
    die(json_encode(['success' => false, 'message' => 'You have already applied for this job.']));
}

// Insert the new application
$stmt = $pdo->prepare("INSERT INTO applications (user_id, job_id) VALUES (?, ?)");
if ($stmt->execute([$userId, $jobId])) {
    echo json_encode(['success' => true, 'message' => 'Application submitted successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit application.']);
}
?>