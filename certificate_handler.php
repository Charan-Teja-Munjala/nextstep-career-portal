<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header('Content-Type: application/json');

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

if (!isset($_GET['uid'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Certificate UID is required.']));
}

$certificateUid = $_GET['uid'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            c.certificate_uid,
            c.issue_date,
            u.name AS user_name,
            co.title AS course_title,
            o.name AS organization_name
        FROM certificates c
        JOIN users u ON c.user_id = u.id
        JOIN courses co ON c.course_id = co.id
        JOIN organizations o ON c.organization_id = o.id
        WHERE c.certificate_uid = ?
    ");
    $stmt->execute([$certificateUid]);
    $certificate = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($certificate) {
        // Format the date for better display
        $date = new DateTime($certificate['issue_date']);
        $certificate['formatted_issue_date'] = $date->format('F j, Y');
        echo json_encode($certificate);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Certificate not found.']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed: ' . $e->getMessage()]);
}
?>
