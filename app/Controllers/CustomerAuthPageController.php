<?php
final class CustomerAuthPageController
{
    public function login(): array
    {

session_start();
$resetSuccess = $_SESSION['reset_success'] ?? '';
unset($_SESSION['reset_success']);

        return get_defined_vars();
    }

    public static function register_registrationFieldError($field, $errors)
{
    if (!isset($errors[$field])) {
        return;
    }
    echo '<small class="field-error" data-error-for="' . htmlspecialchars($field, ENT_QUOTES, 'UTF-8') . '" aria-live="polite">' . htmlspecialchars($errors[$field], ENT_QUOTES, 'UTF-8') . '</small>';
}

    public function register(): array
    {
 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$emailFromFooter = strtolower(trim($_GET['email'] ?? ''));
$registerErrors = $_SESSION['register_errors'] ?? array();
$registerOld = $_SESSION['register_old'] ?? array();
$registerMessage = $_SESSION['error_message'] ?? '';
unset($_SESSION['register_errors'], $_SESSION['register_old'], $_SESSION['error_message']);



        return get_defined_vars();
    }

    public function forgotPassword(): array
    {

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (
    !isset($_SESSION['reset_csrf_token']) ||
    !is_string($_SESSION['reset_csrf_token']) ||
    strlen($_SESSION['reset_csrf_token']) !== 64
) {
    $_SESSION['reset_csrf_token'] = bin2hex(random_bytes(32));
}

$success = $_SESSION['reset_success'] ?? '';
$error = $_SESSION['reset_error'] ?? '';

unset($_SESSION['reset_success'], $_SESSION['reset_error']);

        return get_defined_vars();
    }

    public function resetPassword(): array
    {

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once (dirname(__DIR__, 2)) . '/reset_helpersemail.php';
require_once (dirname(__DIR__, 2)) . '/validate_helpers.php';

$pendingEmail = $_SESSION['reset_pending_email'] ?? '';
$hasPendingRequest = is_string($pendingEmail) && $pendingEmail !== '';
$pageError = $hasPendingRequest ? '' : ValidationService::validationMessage('reset_otp_invalid');
$success = $_SESSION['reset_success'] ?? '';
$error = $_SESSION['reset_error'] ?? '';
$resetDevOtp = $_SESSION['reset_dev_otp'] ?? '';
$resetDevLink = $_SESSION['reset_dev_link'] ?? '';
$resetErrors = $_SESSION['reset_password_errors'] ?? array();

unset(
    $_SESSION['reset_success'],
    $_SESSION['reset_error'],
    $_SESSION['reset_password_errors']
);

$isLocalDevelopment = PasswordResetMailService::isLocalResetDevelopment();
if (!$isLocalDevelopment || !is_string($resetDevOtp)) {
    $resetDevOtp = '';
}
if (!$isLocalDevelopment || !is_string($resetDevLink)) {
    $resetDevLink = '';
}

if (
    $hasPendingRequest &&
    (!isset($_SESSION['reset_password_csrf_token']) ||
        !is_string($_SESSION['reset_password_csrf_token']) ||
        strlen($_SESSION['reset_password_csrf_token']) !== 64)
) {
    $_SESSION['reset_password_csrf_token'] = bin2hex(random_bytes(32));
}

        return get_defined_vars();
    }
}
