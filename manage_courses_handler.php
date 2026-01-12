<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header('Content-Type: application/json');

if (!isset($_SESSION['organization_id'])) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Organization not authenticated.']));
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
    die(json_encode(['success' => false, 'message' => 'Database connection failed.']));
}

$organizationId = $_SESSION['organization_id'];
$method = $_SERVER['REQUEST_METHOD'];

function convert_youtube_url_to_embed($url) {
    if (preg_match('/(youtube\.com|youtu\.be)\/(watch\?v=|embed\/|v\/|)([\w\-]{11})/', $url, $matches)) {
        return 'https://www.youtube.com/embed/' . $matches[3];
    }
    return $url;
}

if ($method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT c.*, COUNT(e.id) AS enrollment_count 
        FROM courses c 
        LEFT JOIN enrollments e ON c.id = e.course_id
        WHERE c.organization_id = ?
        GROUP BY c.id
        ORDER BY c.id DESC
    ");
    $stmt->execute([$organizationId]);
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($courses);

} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? 'create';

    try {
        if ($action === 'create') {
            $stmt = $pdo->prepare("INSERT INTO courses (organization_id, title, description, thumbnail_url, category, level, duration_hours, language) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$organizationId, $data['courseTitle'], $data['courseDescription'], $data['thumbnailUrl'], $data['category'], $data['level'], $data['duration_hours'], $data['language']]);
            echo json_encode(['success' => true, 'message' => 'Course created successfully!']);

        } elseif ($action === 'update') {
            $stmt = $pdo->prepare("UPDATE courses SET title = ?, description = ?, thumbnail_url = ?, category = ?, level = ?, duration_hours = ?, language = ? WHERE id = ? AND organization_id = ?");
            $stmt->execute([$data['title'], $data['description'], $data['thumbnail_url'], $data['category'], $data['level'], $data['duration_hours'], $data['language'], $data['id'], $organizationId]);
            echo json_encode(['success' => true, 'message' => 'Course updated successfully.']);

        } elseif ($action === 'delete') {
            $pdo->beginTransaction();
            $courseId = $data['id'];
            $stmt = $pdo->prepare("DELETE FROM course_lessons WHERE module_id IN (SELECT id FROM course_modules WHERE course_id = ?)");
            $stmt->execute([$courseId]);
            $stmt = $pdo->prepare("DELETE FROM course_modules WHERE course_id = ?");
            $stmt->execute([$courseId]);
            $stmt = $pdo->prepare("DELETE FROM enrollments WHERE course_id = ?");
            $stmt->execute([$courseId]);
            $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ? AND organization_id = ?");
            $stmt->execute([$courseId, $organizationId]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Course and all related content deleted.']);
            
        } elseif ($action === 'get_content') {
            $courseId = $data['course_id'];
            $modulesStmt = $pdo->prepare("SELECT * FROM course_modules WHERE course_id = ? ORDER BY id ASC");
            $modulesStmt->execute([$courseId]);
            $modules = $modulesStmt->fetchAll(PDO::FETCH_ASSOC);

            for ($i = 0; $i < count($modules); $i++) {
                $lessonsStmt = $pdo->prepare("SELECT * FROM course_lessons WHERE module_id = ? ORDER BY id ASC");
                $lessonsStmt->execute([$modules[$i]['id']]);
                $modules[$i]['lessons'] = $lessonsStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            echo json_encode(['success' => true, 'data' => $modules]);

        } elseif ($action === 'add_module') {
            $stmt = $pdo->prepare("INSERT INTO course_modules (course_id, title) VALUES (?, ?)");
            $stmt->execute([$data['course_id'], $data['title']]);
            echo json_encode(['success' => true, 'message' => 'Module added.']);
        
        } elseif ($action === 'add_lesson') {
            $lessonType = $data['lesson_type'] ?? 'video';
            $videoUrl = null;
            $content = null;

            if ($lessonType === 'video') {
                $videoUrl = convert_youtube_url_to_embed($data['video_url']);
            } else {
                $content = $data['content'];
            }

            $stmt = $pdo->prepare("INSERT INTO course_lessons (module_id, title, lesson_type, video_url, content) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$data['module_id'], $data['title'], $lessonType, $videoUrl, $content]);
            echo json_encode(['success' => true, 'message' => 'Lesson added.']);
       
        } elseif ($action === 'get_enrolled_students') {
            $courseId = $data['course_id'];
            $stmt = $pdo->prepare("SELECT u.id, u.name, u.email FROM users u JOIN enrollments e ON u.id = e.user_id WHERE e.course_id = ?");
            $stmt->execute([$courseId]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $students]);
       
        } elseif ($action === 'delete_module') {
            $moduleId = $data['module_id'];
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("DELETE FROM course_lessons WHERE module_id = ?");
            $stmt->execute([$moduleId]);
            $stmt = $pdo->prepare("DELETE FROM course_modules WHERE id = ?");
            $stmt->execute([$moduleId]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Module deleted.']);

        } elseif ($action === 'delete_lesson') {
            $lessonId = $data['lesson_id'];
            $stmt = $pdo->prepare("DELETE FROM course_lessons WHERE id = ?");
            $stmt->execute([$lessonId]);
            echo json_encode(['success' => true, 'message' => 'Lesson deleted.']);
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'A database error occurred: ' . $e->getMessage()]);
    }
}
?>

