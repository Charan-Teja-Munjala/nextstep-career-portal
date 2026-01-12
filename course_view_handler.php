<?php
session_start();

// --- Anti-Caching Headers & Content Type ---
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header('Content-Type: application/json');

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

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

// --- GET Request: Fetching Course View Data ---
if ($method === 'GET') {
    if (!isset($_GET['id'])) {
        http_response_code(400);
        die(json_encode(['error' => 'Course ID is required.']));
    }
    $courseId = $_GET['id'];
    
    try {
        // Core course data
        $stmt = $pdo->prepare("SELECT c.*, o.name AS organization_name FROM courses c JOIN organizations o ON c.organization_id = o.id WHERE c.id = ?");
        $stmt->execute([$courseId]);
        $courseData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$courseData) {
            http_response_code(404);
            die(json_encode(['error' => 'Course not found.']));
        }

        // Modules and Lessons
        $modulesStmt = $pdo->prepare("SELECT * FROM course_modules WHERE course_id = ? ORDER BY id ASC");
        $modulesStmt->execute([$courseId]);
        $modules = $modulesStmt->fetchAll(PDO::FETCH_ASSOC);

        $totalLessons = 0;
        for ($i = 0; $i < count($modules); $i++) {
            $lessonsStmt = $pdo->prepare("SELECT id, title, lesson_type, content, video_url FROM course_lessons WHERE module_id = ? ORDER BY id ASC");
            $lessonsStmt->execute([$modules[$i]['id']]);
            $lessons = $lessonsStmt->fetchAll(PDO::FETCH_ASSOC);
            $modules[$i]['lessons'] = $lessons;
            $totalLessons += count($lessons);
        }
        $courseData['modules'] = $modules;

        // Progress Calculation
        $completedStmt = $pdo->prepare("SELECT lp.lesson_id FROM lesson_progress lp JOIN course_lessons cl ON lp.lesson_id = cl.id JOIN course_modules cm ON cl.module_id = cm.id WHERE lp.user_id = ? AND cm.course_id = ?");
        $completedStmt->execute([$userId, $courseId]);
        $completedLessons = $completedStmt->fetchAll(PDO::FETCH_COLUMN, 0);
        $courseData['completed_lessons'] = $completedLessons;
        
        if ($totalLessons > 0) {
            $courseData['progress'] = round((count($completedLessons) / $totalLessons) * 100);
        } else {
            $courseData['progress'] = 0;
        }

        // Check for existing certificate
        $certStmt = $pdo->prepare("SELECT certificate_uid FROM certificates WHERE user_id = ? AND course_id = ?");
        $certStmt->execute([$userId, $courseId]);
        $certificate = $certStmt->fetch(PDO::FETCH_ASSOC);
        if ($certificate) {
            $courseData['certificate_uid'] = $certificate['certificate_uid'];
        }

        echo json_encode($courseData);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database query failed: ' . $e->getMessage()]);
    }
}

// --- POST Request: Mark Lesson as Complete ---
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['action']) && $data['action'] === 'mark_complete') {
        $lessonId = $data['lesson_id'];
        
        try {
            // 1. Mark the lesson as complete
            $stmt = $pdo->prepare("INSERT IGNORE INTO lesson_progress (user_id, lesson_id) VALUES (?, ?)");
            $stmt->execute([$userId, $lessonId]);

            // 2. Recalculate progress to check for completion
            $courseIdStmt = $pdo->prepare("SELECT cm.course_id FROM course_lessons cl JOIN course_modules cm ON cl.module_id = cm.id WHERE cl.id = ?");
            $courseIdStmt->execute([$lessonId]);
            $course = $courseIdStmt->fetch(PDO::FETCH_ASSOC);

            $responsePayload = ['success' => true];

            if ($course) {
                $courseId = $course['course_id'];
                
                $totalLessonsStmt = $pdo->prepare("SELECT COUNT(*) FROM course_lessons cl JOIN course_modules cm ON cl.module_id = cm.id WHERE cm.course_id = ?");
                $totalLessonsStmt->execute([$courseId]);
                $totalLessons = $totalLessonsStmt->fetchColumn();

                $completedLessonsStmt = $pdo->prepare("SELECT COUNT(*) FROM lesson_progress lp JOIN course_lessons cl ON lp.lesson_id = cl.id JOIN course_modules cm ON cl.module_id = cm.id WHERE lp.user_id = ? AND cm.course_id = ?");
                $completedLessonsStmt->execute([$userId, $courseId]);
                $completedCount = $completedLessonsStmt->fetchColumn();

                // 3. If course is 100% complete, generate certificate and log email
                if ($totalLessons > 0 && $completedCount >= $totalLessons) {
                    $certCheckStmt = $pdo->prepare("SELECT certificate_uid FROM certificates WHERE user_id = ? AND course_id = ?");
                    $certCheckStmt->execute([$userId, $courseId]);
                    
                    if ($existingCert = $certCheckStmt->fetch(PDO::FETCH_ASSOC)) {
                        $responsePayload['certificate_uid'] = $existingCert['certificate_uid'];
                    } else {
                        $certificateUid = uniqid('cert_', true);
                        
                        $detailsStmt = $pdo->prepare("SELECT c.title AS course_title, c.organization_id, o.name AS org_name FROM courses c JOIN organizations o ON c.organization_id = o.id WHERE c.id = ?");
                        $detailsStmt->execute([$courseId]);
                        $courseDetails = $detailsStmt->fetch(PDO::FETCH_ASSOC);
                        
                        $userStmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                        $userStmt->execute([$userId]);
                        $userDetails = $userStmt->fetch(PDO::FETCH_ASSOC);
                        
                        $insertCertStmt = $pdo->prepare("INSERT INTO certificates (user_id, course_id, organization_id, issue_date, certificate_uid) VALUES (?, ?, ?, CURDATE(), ?)");
                        $insertCertStmt->execute([$userId, $courseId, $courseDetails['organization_id'], $certificateUid]);
                        
                        // Log the "email" to the server's error log
                        $emailSubject = "Congratulations on Completing Your Course!";
                        $emailBody = "Hi " . $userDetails['name'] . ",\n\nCongratulations on successfully completing the course: '" . $courseDetails['course_title'] . "'.\n\nYou can view your new certificate from the course page on our website.\n\nWell done!\n The NextStep Hub Team";
                        error_log("--- CERTIFICATE EMAIL ---\nTO: " . $userDetails['email'] . "\nSUBJECT: " . $emailSubject . "\nBODY:\n" . $emailBody . "\n-----------------------\n");
                        
                        $responsePayload['certificate_uid'] = $certificateUid;
                    }
                }
            }

            echo json_encode($responsePayload);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database operation failed: ' . $e->getMessage()]);
        }
    }
}
?>

