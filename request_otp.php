<?php
// Start a session to store the OTP temporarily.
session_start();

// --- Configuration ---
$db_host = 'sql113.infinityfree.com';
$db_user = 'if0_39898687';
$db_pass = 'nextstephub01';
$db_name = 'if0_39898687_nextstep';
$from_email = 'no-reply@nextstephub.com';
$email_subject = 'Your Password Reset Code';

header('Content-Type: application/json');

// --- Main Logic ---

$input = json_decode(file_get_contents('php://input'), true);
$email = filter_var($input['email'] ?? '', FILTER_VALIDATE_EMAIL);

if (!$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email address provided.']);
    exit;
}

// 1. Connect to DB to find the user
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    http_response_code(500);
    error_log("DB Connection Error: " . $conn->connect_error);
    echo json_encode(['error' => 'A server error occurred.']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

// To prevent attackers from checking which emails are registered,
// we always return a success message, even if the user is not found.
if ($result->num_rows === 0) {
    echo json_encode(['message' => 'If an account with that email exists, a reset code has been sent.']);
    exit;
}

$user = $result->fetch_assoc();
$user_id = $user['id'];
$conn->close();

// 2. Generate a simple 6-digit OTP
$otp = random_int(100000, 999999);

// 3. Store the OTP and user ID in the session
// The OTP will be valid for 10 minutes (600 seconds)
$_SESSION['reset_user_id'] = $user_id;
$_SESSION['reset_otp'] = $otp;
$_SESSION['reset_otp_expiry'] = time() + 600;

// 4. Send the email with the OTP
// In a real app, use a robust email library like PHPMailer
$email_message = "Your password reset code is: <h2>{$otp}</h2>This code is valid for 10 minutes.";
$headers = 'From: ' . $from_email . "\r\n" .
           'Reply-To: ' . $from_email . "\r\n" .
           'Content-Type: text/html; charset=UTF-8' . "\r\n" .
           'X-Mailer: PHP/' . phpversion();

// mail($email, $email_subject, $email_message, $headers);

// For testing purposes, we log the OTP instead of sending an email.
error_log("Password reset OTP for {$email}: {$otp}");

// We need to tell the front-end that the request was successful
// so it can redirect to the next step.
echo json_encode([
    'message' => 'A reset code has been sent to your email.',
    'redirect' => 'verify_otp.html' // URL for the new verification page
]);

?>
