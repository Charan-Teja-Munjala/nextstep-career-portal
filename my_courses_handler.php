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

// --- Database Connection ---
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

// --- Fetch Course Data with DYNAMIC Progress ---
header('Content-Type: application/json');
$userId = $_SESSION['user_id'];

try {
    // 1. Get all courses the user is enrolled in
    $stmt = $pdo->prepare("
        SELECT
            c.id AS course_id,
            c.title,
            c.description,
            c.thumbnail_url
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        WHERE e.user_id = ?
        ORDER BY e.id DESC
    ");
    $stmt->execute([$userId]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. For each course, calculate the progress
    foreach ($courses as &$course) { // Use reference to modify array directly
        $courseId = $course['course_id'];

        // a. Count total lessons for the course
        $totalLessonsStmt = $pdo->prepare("
            SELECT COUNT(*) FROM course_lessons cl
            JOIN course_modules cm ON cl.module_id = cm.id
            WHERE cm.course_id = ?
        ");
        $totalLessonsStmt->execute([$courseId]);
        $totalLessons = $totalLessonsStmt->fetchColumn();

        // b. Count completed lessons for the user in this course
        $completedLessonsStmt = $pdo->prepare("
            SELECT COUNT(*) FROM lesson_progress lp
            JOIN course_lessons cl ON lp.lesson_id = cl.id
            JOIN course_modules cm ON cl.module_id = cm.id
            WHERE lp.user_id = ? AND cm.course_id = ?
        ");
        $completedLessonsStmt->execute([$userId, $courseId]);
        $completedLessons = $completedLessonsStmt->fetchColumn();

        // c. Calculate percentage and add it to the course object
        if ($totalLessons > 0) {
            $course['progress'] = round(($completedLessons / $totalLessons) * 100);
        } else {
            $course['progress'] = 0; // No lessons, so progress is 0
        }
    }
    // Unset the reference to avoid potential side effects
    unset($course); 

    echo json_encode($courses);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch course data: ' . $e->getMessage()]);
}
?>

