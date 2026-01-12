<?php
session_start();

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'User not logged in']);
    exit();
}

// 2. Database Connection
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
    // 3. Get user info (for avatar)
    $stmtUser = $pdo->prepare("SELECT avatar_url FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    // 4. Get all courses from the 'courses' table
    $stmtCourses = $pdo->query("SELECT id, title, description, thumbnail_url FROM courses");
    $allCourses = $stmtCourses->fetchAll(PDO::FETCH_ASSOC);

    // 5. Get the IDs of courses the user is already enrolled in
    $stmtEnrolled = $pdo->prepare("SELECT course_id FROM enrollments WHERE user_id = ?");
    $stmtEnrolled->execute([$userId]);
    $enrolledCourseIds = $stmtEnrolled->fetchAll(PDO::FETCH_COLUMN, 0);

    // 6. Add an 'is_enrolled' flag to each course
    $coursesWithStatus = array_map(function($course) use ($enrolledCourseIds) {
        $course['is_enrolled'] = in_array($course['id'], $enrolledCourseIds);
        return $course;
    }, $allCourses);
    
    // 7. Prepare the final data structure
    $data = [
        'user' => $user,
        'courses' => $coursesWithStatus
    ];
    
    header('Content-Type: application/json');
    echo json_encode($data);

} catch (PDOException $e) {
    http_response_code(500);
    // This line is helpful for debugging but can be removed in production
    echo json_encode(['error' => 'Could not fetch courses: ' . $e->getMessage()]);
}
?>

