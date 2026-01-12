<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header('Content-Type: application/json');

if (!isset($_SESSION['organization_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Organization not logged in']);
    exit();
}

$organization_id = $_SESSION['organization_id'];

$host = 'sql113.infinityfree.com';
$dbname = 'if0_39898687_nextstep';
$username = 'if0_39898687';
$db_password = 'nextstephub01';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed.']));
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                j.id, j.title, j.location, j.description, j.is_remote,
                j.job_type, j.salary_range, j.experience_level, j.requirements,
                DATE_FORMAT(j.posted_date, '%b %d, %Y') as formatted_date,
                COUNT(a.id) as applicant_count
            FROM jobs j
            LEFT JOIN applications a ON j.id = a.job_id
            WHERE j.organization_id = ?
            GROUP BY j.id
            ORDER BY j.posted_date DESC
        ");
        $stmt->execute([$organization_id]);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($jobs);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch jobs.']);
    }
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? null;

    try {
        if ($action === 'create') {
            $sql = "INSERT INTO jobs (organization_id, title, description, location, is_remote, job_type, salary_range, experience_level, requirements) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $organization_id,
                $data['jobTitle'],
                $data['description'],
                $data['location'],
                $data['isRemote'] ? 1 : 0,
                $data['job_type'],
                $data['salary_range'],
                $data['experience_level'],
                $data['requirements']
            ]);
            echo json_encode(['success' => true, 'message' => 'Job posted successfully!']);

        } elseif ($action === 'update') {
            $sql = "UPDATE jobs SET title = ?, description = ?, location = ?, is_remote = ?, job_type = ?, salary_range = ?, experience_level = ?, requirements = ? WHERE id = ? AND organization_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['jobTitle'],
                $data['description'],
                $data['location'],
                $data['isRemote'] ? 1 : 0,
                $data['job_type'],
                $data['salary_range'],
                $data['experience_level'],
                $data['requirements'],
                $data['id'],
                $organization_id
            ]);
            echo json_encode(['success' => true, 'message' => 'Job updated successfully!']);
            
        } elseif ($action === 'delete') {
            $job_id = $data['id'] ?? 0;
            $sql = "DELETE FROM jobs WHERE id = ? AND organization_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$job_id, $organization_id]);
            echo json_encode(['success' => true, 'message' => 'Job deleted successfully!']);
        
        } elseif ($action === 'get_applicants') {
            $job_id = $data['job_id'] ?? 0;
            $stmt = $pdo->prepare("SELECT u.name, u.email FROM applications a JOIN users u ON a.user_id = u.id WHERE a.job_id = ?");
            $stmt->execute([$job_id]);
            $applicants = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $applicants]);
            
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action specified.']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'A database error occurred: ' . $e->getMessage()]);
    }
}
?>

