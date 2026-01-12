<?php
// We need to access the same session where we stored the OTP
session_start();

// --- Configuration ---
$db_host = 'sql113.infinityfree.com';
$db_user = 'if0_39898687';
$db_pass = 'nextstephub01';
$db_name = 'if0_39898687_nextstep';

header('Content-Type: application/json');

// --- Main Logic ---

// 1. Check if the session variables are even set
if (!isset($_SESSION['reset_otp']) || !isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_otp_expiry'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No reset process initiated or session has expired. Please start over.']);
    exit;
}

// 2. Check if the OTP has expired
if (time() > $_SESSION['reset_otp_expiry']) {
    // Clean up the expired session variables
    unset($_SESSION['reset_otp'], $_SESSION['reset_user_id'], $_SESSION['reset_otp_expiry']);
    http_response_code(400);
    echo json_encode(['error' => 'The verification code has expired. Please request a new one.']);
    exit;
}

// 3. Get the data from the front-end
$input = json_decode(file_get_contents('php://input'), true);
$submitted_otp = $input['otp'] ?? null;
$new_password = $input['password'] ?? null;

// 4. Validate the submitted OTP
if ($submitted_otp != $_SESSION['reset_otp']) {
    http_response_code(400);
    echo json_encode(['error' => 'The verification code is incorrect.']);
    exit;
}

// 5. If OTP is correct, update the password
$user_id = $_SESSION['reset_user_id'];
$new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    error_log("DB Connection Error: " . $conn->connect_error);
    echo json_encode(['error' => 'A server error occurred.']);
    exit;
}

$stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
$stmt->bind_param("si", $new_password_hash, $user_id);

if ($stmt->execute()) {
    // Success! Clean up the session to prevent reuse
    unset($_SESSION['reset_otp'], $_SESSION['reset_user_id'], $_SESSION['reset_otp_expiry']);
    echo json_encode(['message' => 'Your password has been reset successfully!']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Could not update your password. Please try again.']);
}

$conn->close();

?>
