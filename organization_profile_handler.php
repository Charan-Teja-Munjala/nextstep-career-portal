<?php
// --- SECURITY AND SESSION START ---
session_start();
// Prevent caching to ensure fresh data is always fetched.
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Check if the organization is logged in. If not, return a 401 Unauthorized error.
if (!isset($_SESSION['organization_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Please log in.']);
    exit();
}
// --- END SECURITY AND SESSION START ---


// --- DATABASE CONNECTION ---
$host = 'sql113.infinityfree.com';
$dbname = 'if0_39898687_nextstep';
$username = 'if0_39898687';
$password = 'nextstephub01';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}
// --- END DATABASE CONNECTION ---


$organizationId = $_SESSION['organization_id'];


// --- HANDLE REQUESTS ---

// Check if the request is a GET request (for fetching data)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("SELECT name, email, website_url, description FROM organizations WHERE id = ?");
    $stmt->execute([$organizationId]);
    $organization = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($organization) {
        header('Content-Type: application/json');
        echo json_encode($organization);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Organization not found.']);
    }
}

// Check if the request is a POST request (for updating data)
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the JSON data sent from the frontend
    $data = json_decode(file_get_contents('php://input'), true);

    // Basic validation
    if (empty($data['orgName'])) {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Organization name cannot be empty.']);
        exit();
    }

    // --- Update Password (if provided) ---
    if (!empty($data['newPassword'])) {
        // Hash the new password securely
        $hashedPassword = password_hash($data['newPassword'], PASSWORD_DEFAULT);
        
        // Update both profile info and password
        $sql = "UPDATE organizations SET name = ?, website_url = ?, description = ?, password = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['orgName'],
            $data['websiteUrl'],
            $data['description'],
            $hashedPassword,
            $organizationId
        ]);
    } else {
        // Update just the profile info, leave password unchanged
        $sql = "UPDATE organizations SET name = ?, website_url = ?, description = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['orgName'],
            $data['websiteUrl'],
            $data['description'],
            $organizationId
        ]);
    }
    
    // Send a success response back to the frontend
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
}
// --- END HANDLE REQUESTS ---
?>
