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
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // This block handles fetching the data when the page loads.
    try {
        $stmt = $pdo->prepare("SELECT name, email, avatar_url, headline, bio, skills, linkedin_url, github_url, portfolio_url FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            echo json_encode($user);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'User not found.']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch profile data.']);
    }
} elseif ($method === 'POST') {
    // This block handles updating the data when the form is saved.
    $data = json_decode(file_get_contents('php://input'), true);

    try {
        $fields = [
            'name' => $data['fullName'] ?? null,
            'headline' => $data['headline'] ?? null,
            'bio' => $data['bio'] ?? null,
            'skills' => $data['skills'] ?? null,
            'linkedin_url' => $data['linkedin_url'] ?? null,
            'github_url' => $data['github_url'] ?? null,
            'portfolio_url' => $data['portfolio_url'] ?? null,
            'avatar_url' => $data['avatar_url'] ?? null,
        ];
        
        $sqlParts = [];
        $params = [];
        foreach($fields as $key => $value) {
            if ($value !== null) {
                $sqlParts[] = "$key = ?";
                $params[] = $value;
            }
        }

        if (!empty($data['newPassword'])) {
            $hashedPassword = password_hash($data['newPassword'], PASSWORD_DEFAULT);
            $sqlParts[] = "password = ?";
            $params[] = $hashedPassword;
        }

        if (!empty($sqlParts)) {
            $params[] = $userId;
            $sql = "UPDATE users SET " . implode(', ', $sqlParts) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
    }
}
?>

