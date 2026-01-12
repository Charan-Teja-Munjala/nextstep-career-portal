<?php
// --- Database Connection ---
$host = 'sql113.infinityfree.com';
$dbname = 'if0_39898687_nextstep';
$username = 'if0_39898687';
$db_password = 'nextstephub01';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Database connection failed.']));
}

// --- Prevent Caching ---
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT'); // Date in the past

// --- Fetch Latest Jobs ---
// Fetches the 3 most recent jobs from approved organizations
$jobs_sql = "
    SELECT j.title, j.location, j.is_remote, o.name AS company_name
    FROM jobs j
    JOIN organizations o ON j.organization_id = o.id
    WHERE o.is_approved = TRUE
    ORDER BY j.posted_date DESC
    LIMIT 3
";
$jobs_stmt = $pdo->query($jobs_sql);
$jobs = $jobs_stmt->fetchAll(PDO::FETCH_ASSOC);


// --- Fetch Featured Courses ---
// Fetches 3 courses from approved organizations
$courses_sql = "
    SELECT c.title, c.description, c.thumbnail_url, o.name AS organization_name
    FROM courses c
    JOIN organizations o ON c.organization_id = o.id
    WHERE o.is_approved = TRUE
    ORDER BY c.id DESC -- For now, just gets the latest courses
    LIMIT 3
";
$courses_stmt = $pdo->query($courses_sql);
$courses = $courses_stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Combine and Send Response ---
$data = [
    'latest_jobs' => $jobs,
    'featured_courses' => $courses
];

echo json_encode($data);
?>

