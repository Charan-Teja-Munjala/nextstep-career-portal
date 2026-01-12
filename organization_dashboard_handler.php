<?php
// --- Force the browser not to cache this response ---
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header('Content-Type: application/json');

// --- Error Reporting for Debugging ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Check if organization is logged in
if (!isset($_SESSION['organization_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['error' => 'Organization not logged in. Please log in again.']);
    exit();
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
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}

$organizationId = $_SESSION['organization_id'];
$response_data = [
    'organization_name' => 'Guest',
    'stats' => [],
    'active_jobs' => [],
    'recent_applicants' => [],
    'errors' => [] // Array to hold debug error messages
];

// --- Fetch Organization Name ---
try {
    $stmt = $pdo->prepare("SELECT name FROM organizations WHERE id = ?");
    $stmt->execute([$organizationId]);
    $organization = $stmt->fetch(PDO::FETCH_ASSOC);
    $response_data['organization_name'] = $organization ? $organization['name'] : 'Guest';
} catch (PDOException $e) {
    $response_data['errors'][] = "Error fetching organization name: " . $e->getMessage();
}


// --- Fetch All Stats ---
try {
    $stmt_stats = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM jobs WHERE organization_id = :org_id1) as jobs_posted,
            (SELECT COUNT(*) FROM courses WHERE organization_id = :org_id2) as courses_listed,
            (SELECT COUNT(*) FROM applications WHERE job_id IN (SELECT id FROM jobs WHERE organization_id = :org_id3)) as total_applicants,
            (SELECT COUNT(*) FROM enrollments WHERE course_id IN (SELECT id FROM courses WHERE organization_id = :org_id4)) as course_enrollments
    ");
    $stmt_stats->execute([
        ':org_id1' => $organizationId,
        ':org_id2' => $organizationId,
        ':org_id3' => $organizationId,
        ':org_id4' => $organizationId
    ]);
    $response_data['stats'] = $stmt_stats->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $response_data['errors'][] = "Error fetching stats: " . $e->getMessage();
}


// --- Fetch Active Job Listings ---
try {
    $stmt_jobs = $pdo->prepare("
        SELECT id, title, (SELECT COUNT(*) FROM applications WHERE job_id = jobs.id) as applicants
        FROM jobs
        WHERE organization_id = ?
        ORDER BY posted_date DESC
        LIMIT 5
    ");
    $stmt_jobs->execute([$organizationId]);
    $response_data['active_jobs'] = $stmt_jobs->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $response_data['errors'][] = "Error fetching active jobs: " . $e->getMessage();
}


// --- Fetch Recent Applicants ---
try {
    $stmt_applicants = $pdo->prepare("
        SELECT u.name, j.title as role_applied
        FROM applications a
        JOIN users u ON a.user_id = u.id
        JOIN jobs j ON a.job_id = j.id
        WHERE j.organization_id = ?
        ORDER BY a.id DESC -- <<< THIS IS THE FIX
        LIMIT 5
    ");
    $stmt_applicants->execute([$organizationId]);
    $response_data['recent_applicants'] = $stmt_applicants->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $response_data['errors'][] = "Error fetching recent applicants: " . $e->getMessage();
}


// --- Send Final Response ---
if (!empty($response_data['errors'])) {
    http_response_code(500);
    echo json_encode(['error' => implode('; ', $response_data['errors'])]);
    exit();
}

echo json_encode($response_data);
?>

