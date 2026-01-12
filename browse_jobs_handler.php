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
    // 1. Get IDs of jobs the user has already applied for
    $appliedStmt = $pdo->prepare("SELECT job_id FROM applications WHERE user_id = ?");
    $appliedStmt->execute([$userId]);
    $appliedJobIds = $appliedStmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // 2. Get all jobs with all details AND applicant count
    $jobsSql = "
        SELECT 
            j.id, j.title, j.description, j.location, j.is_remote,
            j.job_type, j.salary_range, j.experience_level, j.requirements,
            DATE_FORMAT(j.posted_date, '%b %d, %Y') as formatted_date,
            o.name as company_name,
            COUNT(a.id) as applicant_count
        FROM jobs j
        JOIN organizations o ON j.organization_id = o.id
        LEFT JOIN applications a ON j.id = a.job_id
        GROUP BY j.id
        ORDER BY j.posted_date DESC
    ";
    $jobsStmt = $pdo->query($jobsSql);
    $allJobs = $jobsStmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Add an 'has_applied' flag to each job
    $jobsWithStatus = [];
    foreach ($allJobs as $job) {
        $job['has_applied'] = in_array($job['id'], $appliedJobIds);
        $jobsWithStatus[] = $job;
    }

    echo json_encode($jobsWithStatus);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch jobs: ' . $e->getMessage()]);
}
?>

