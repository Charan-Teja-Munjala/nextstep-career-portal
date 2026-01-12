<?php
session_start();

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'You must be logged in to enroll.']);
    exit();
}

// 2. Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Invalid request method.']);
    exit();
}

// 3. Get the course ID from the POST data
$data = json_decode(file_get_contents('php://input'), true);
$courseId = $data['course_id'] ?? null;

if (!$courseId) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Course ID is missing.']);
    exit();
}

// 4. Connect to the database
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

// 5. Check if the user is already enrolled to prevent duplicates
$stmt = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
$stmt->execute([$userId, $courseId]);
if ($stmt->fetch()) {
    http_response_code(409); // Conflict
    echo json_encode(['error' => 'You are already enrolled in this course.']);
    exit();
}

// 6. Insert the new enrollment into the database
try {
    $stmt = $pdo->prepare("INSERT INTO enrollments (user_id, course_id, progress) VALUES (?, ?, 0)");
    $stmt->execute([$userId, $courseId]);

    // 7. Send a success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Enrollment successful!']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Could not enroll in the course. Please try again.']);
}
?>
