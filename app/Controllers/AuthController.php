<?php
final class AuthController
{
    public function login()
    {

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once (dirname(__DIR__, 2)) . '/connect.php';
require_once (dirname(__DIR__, 2)) . '/validate_helpers.php';

$email = strtolower(trim($_POST['email'] ?? ''));
$matkhau = (string)($_POST['matkhau'] ?? '');

$loginSql = 'SELECT id, ho, ten, email, sdt, matkhau, ngaysinh, diachi
    FROM users WHERE email = ? LIMIT 1';

if (!ValidationService::validatePersonalEmail($email) || $matkhau === '') {
    $_SESSION['login_error'] = ValidationService::validationMessage('login_credentials');
    header('Location: dangnhap.php');
    exit();
}

try {
    $statement = $conn->prepare($loginSql);
    $statement->bind_param('s', $email);
    $statement->execute();
    $user = $statement->get_result()->fetch_assoc();
    $statement->close();

    if ($user && password_verify($matkhau, $user['matkhau'])) {
        session_regenerate_id(true);
        $_SESSION['is_login'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_ho'] = $user['ho'];
        $_SESSION['user_name'] = $user['ten'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_sdt'] = $user['sdt'];
        $_SESSION['user_ngaysinh'] = $user['ngaysinh'];
        $_SESSION['user_diachi'] = $user['diachi'];

        header('Location: taikhoan.php');
        exit();
    }
} catch (Throwable $exception) {
    error_log(sprintf(
        '%s:%d: %s | mysqli_error=%s | SQL=%s',
        (dirname(__DIR__, 2) . '/xulydangnhap.php'),
        __LINE__,
        $exception->getMessage(),
        mysqli_error($conn),
        $loginSql
    ));
    $_SESSION['login_error'] = ValidationService::validationMessage('system');
    header('Location: dangnhap.php');
    exit();
}

$_SESSION['login_error'] = ValidationService::validationMessage('login_credentials');
header('Location: dangnhap.php');
exit();

    }

    public function register()
    {

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once (dirname(__DIR__, 2)) . '/connect.php';
require_once (dirname(__DIR__, 2)) . '/validate_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dangky.php');
    exit();
}

unset(
    $_SESSION['is_login'],
    $_SESSION['user_id'],
    $_SESSION['user_ho'],
    $_SESSION['user_name'],
    $_SESSION['user_email'],
    $_SESSION['user_sdt'],
    $_SESSION['user_ngaysinh'],
    $_SESSION['user_diachi']
);

$ho = ValidationService::normalizePersonName($_POST['ho'] ?? '');
$ten = ValidationService::normalizePersonName($_POST['ten'] ?? '');
$email = strtolower(trim($_POST['register_email'] ?? ''));
$sdt = ValidationService::normalizeVnPhone($_POST['sdt'] ?? '');
$matkhau = (string)($_POST['register_password'] ?? '');
$matkhauNhapLai = (string)($_POST['register_password_confirm'] ?? '');
$customerType = ($_POST['customer_type'] ?? 'ca-nhan') === 'uu-tien' ? 'uu-tien' : 'ca-nhan';
$tenCongTy = trim($_POST['ten_cong_ty'] ?? '');
$maSoThue = trim($_POST['ma_so_thue'] ?? '');
$diaChiCty = trim($_POST['dia_chi_cty'] ?? '');
$emailCty = strtolower(trim($_POST['email_cty'] ?? ''));
$errors = array();

if (!ValidationService::validatePersonName($ho)) {
    $errors['ho'] = ValidationService::validationMessage('person_first');
}
if (!ValidationService::validatePersonName($ten)) {
    $errors['ten'] = ValidationService::validationMessage('person_last');
}
if (!ValidationService::validatePersonalEmail($email)) {
    $errors['register_email'] = ValidationService::validationMessage('email_invalid');
}
if (!ValidationService::isValidVnPhone($sdt)) {
    $errors['sdt'] = ValidationService::phoneValidationMessage();
}
$passwordError = ValidationService::passwordBlockingError($matkhau);
if ($passwordError !== null) {
    $errors['register_password'] = $passwordError;
}
if ($matkhau !== $matkhauNhapLai) {
    $errors['register_password_confirm'] = ValidationService::validationMessage('password_confirm');
}

if ($customerType === 'uu-tien') {
    if (!ValidationService::validateCompanyName($tenCongTy)) {
        $errors['ten_cong_ty'] = ValidationService::validationMessage('company_name');
    }
    if (!ValidationService::validateTaxCode($maSoThue)) {
        $errors['ma_so_thue'] = ValidationService::validationMessage('tax_code');
    }
    if (!ValidationService::validateCompanyAddress($diaChiCty)) {
        $errors['dia_chi_cty'] = ValidationService::validationMessage('company_address');
    }
    if ($emailCty === $email) {
        $errors['email_cty'] = ValidationService::validationMessage('email_company_same');
    } elseif (!ValidationService::validateCompanyEmail($emailCty, $email)) {
        $errors['email_cty'] = ValidationService::validationMessage('email_company_invalid');
    }
} else {
    $tenCongTy = '';
    $maSoThue = '';
    $diaChiCty = '';
    $emailCty = '';
}

$oldValues = array(
    'ho' => $ho,
    'ten' => $ten,
    'register_email' => $email,
    'sdt' => $sdt,
    'ten_cong_ty' => $tenCongTy,
    'ma_so_thue' => $maSoThue,
    'dia_chi_cty' => $diaChiCty,
    'email_cty' => $emailCty,
    'customer_type' => $customerType
);

if ($errors) {
    $_SESSION['register_errors'] = $errors;
    $_SESSION['register_old'] = $oldValues;
    header('Location: dangky.php');
    exit();
}

$currentSql = '';
try {
    $currentSql = 'SELECT id FROM users WHERE email = ? LIMIT 1';
    $checkEmail = $conn->prepare($currentSql);
    $checkEmail->bind_param('s', $email);
    $checkEmail->execute();
    $checkEmail->store_result();
    if ($checkEmail->num_rows > 0) {
        $errors['register_email'] = ValidationService::validationMessage('email_taken');
    }
    $checkEmail->close();

    if (ValidationService::isValidVnPhone($sdt) && (new ValidationService($conn))->isPhoneInUse( $sdt)) {
        $errors['sdt'] = ValidationService::validationMessage('phone_taken');
    }

    if ($customerType === 'uu-tien') {
        $currentSql = 'SELECT id FROM users WHERE ma_so_thue = ? LIMIT 1';
        $checkTaxCode = $conn->prepare($currentSql);
        $checkTaxCode->bind_param('s', $maSoThue);
        $checkTaxCode->execute();
        $checkTaxCode->store_result();
        if ($checkTaxCode->num_rows > 0) {
            $errors['ma_so_thue'] = ValidationService::validationMessage('tax_code_taken');
        }
        $checkTaxCode->close();

        $currentSql = 'SELECT id FROM users WHERE email_cty = ? LIMIT 1';
        $checkCompanyEmail = $conn->prepare($currentSql);
        $checkCompanyEmail->bind_param('s', $emailCty);
        $checkCompanyEmail->execute();
        $checkCompanyEmail->store_result();
        if ($checkCompanyEmail->num_rows > 0) {
            $errors['email_cty'] = ValidationService::validationMessage('email_company_taken');
        }
        $checkCompanyEmail->close();
    }
} catch (Throwable $exception) {
    error_log(sprintf(
        '%s:%d: %s | mysqli_error=%s | SQL=%s',
        (dirname(__DIR__, 2) . '/xulydangky.php'),
        __LINE__,
        $exception->getMessage(),
        mysqli_error($conn),
        $currentSql
    ));

    $duplicateField = ValidationService::duplicateConstraintField($exception);
    $duplicateMessages = array(
        'sdt' => array('sdt', 'phone_taken'),
        'email' => array('register_email', 'email_taken'),
        'ma_so_thue' => array('ma_so_thue', 'tax_code_taken'),
        'email_cty' => array('email_cty', 'email_company_taken')
    );
    if ($duplicateField !== null && isset($duplicateMessages[$duplicateField])) {
        list($field, $messageKey) = $duplicateMessages[$duplicateField];
        $_SESSION['register_errors'] = array($field => ValidationService::validationMessage($messageKey));
        $_SESSION['register_old'] = $oldValues;
        header('Location: dangky.php');
        exit();
    }

    $_SESSION['error_message'] = ValidationService::validationMessage('system');
    $_SESSION['register_old'] = $oldValues;
    header('Location: dangky.php');
    exit();
}

if ($errors) {
    $_SESSION['register_errors'] = $errors;
    $_SESSION['register_old'] = $oldValues;
    header('Location: dangky.php');
    exit();
}

$insertSql = $customerType === 'uu-tien'
    ? 'INSERT INTO users
        (ho, ten, email, sdt, matkhau, ten_cong_ty, ma_so_thue, dia_chi_cty, email_cty)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    : 'INSERT INTO users (ho, ten, email, sdt, matkhau) VALUES (?, ?, ?, ?, ?)';
$verifySql = 'SELECT id, ho, ten, email, sdt, ngaysinh, diachi
    FROM users WHERE id = ? LIMIT 1';
$currentSql = $insertSql;
$transactionStarted = false;
$insertStatement = null;
$databaseName = '';
$affectedRows = -1;
$newId = 0;
$mysqliError = '';
$executeSucceeded = false;
$passwordHash = password_hash($matkhau, PASSWORD_DEFAULT);

try {
    $conn->begin_transaction();
    $transactionStarted = true;

    $databaseStatement = $conn->prepare('SELECT DATABASE()');
    $databaseStatement->execute();
    $databaseStatement->bind_result($databaseName);
    $databaseStatement->fetch();
    $databaseStatement->close();

    $insertStatement = $conn->prepare($insertSql);
    if ($customerType === 'uu-tien') {
        $insertStatement->bind_param(
            'sssssssss',
            $ho,
            $ten,
            $email,
            $sdt,
            $passwordHash,
            $tenCongTy,
            $maSoThue,
            $diaChiCty,
            $emailCty
        );
    } else {
        $insertStatement->bind_param('sssss', $ho, $ten, $email, $sdt, $passwordHash);
    }

    $executeSucceeded = $insertStatement->execute() === true;
    $affectedRows = mysqli_stmt_affected_rows($insertStatement);
    $newId = mysqli_insert_id($conn);
    $mysqliError = mysqli_error($conn);

    error_log(sprintf(
        '%s:%d [DEBUG TẠM] database=%s affected_rows=%d insert_id=%d mysqli_error=%s SQL=%s',
        (dirname(__DIR__, 2) . '/xulydangky.php'),
        __LINE__,
        $databaseName,
        $affectedRows,
        $newId,
        $mysqliError,
        $insertSql
    ));

    if ($executeSucceeded !== true || $affectedRows !== 1) {
        throw new RuntimeException('INSERT users không tạo đúng một dòng. ' . $mysqliError);
    }

    $conn->commit();
    $transactionStarted = false;

    if ($newId <= 0) {
        throw new RuntimeException('mysqli_insert_id trả về giá trị không hợp lệ.');
    }

    $currentSql = $verifySql;
    $verifyStatement = $conn->prepare($verifySql);
    $verifyStatement->bind_param('i', $newId);
    $verifyStatement->execute();
    $verifyUser = $verifyStatement->get_result()->fetch_assoc();
    $verifyStatement->close();

    if (!$verifyUser || (int)$verifyUser['id'] !== $newId) {
        throw new RuntimeException('Không tìm thấy dòng users vừa thêm sau khi xác nhận.');
    }
} catch (Throwable $exception) {
    $mysqliError = mysqli_error($conn);
    error_log(sprintf(
        '%s:%d: %s | database=%s | affected_rows=%d | insert_id=%d | mysqli_error=%s | SQL=%s',
        (dirname(__DIR__, 2) . '/xulydangky.php'),
        __LINE__,
        $exception->getMessage(),
        $databaseName,
        $affectedRows,
        mysqli_insert_id($conn),
        $mysqliError,
        $currentSql
    ));

    if ($transactionStarted) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackException) {
            error_log(sprintf('%s:%d: %s', (dirname(__DIR__, 2) . '/xulydangky.php'), __LINE__, $rollbackException->getMessage()));
        }
    }
    if ($insertStatement instanceof mysqli_stmt) {
        $insertStatement->close();
    }

    $duplicateField = ValidationService::duplicateConstraintField($exception);
    $duplicateMessages = array(
        'sdt' => array('sdt', 'phone_taken'),
        'email' => array('register_email', 'email_taken'),
        'ma_so_thue' => array('ma_so_thue', 'tax_code_taken'),
        'email_cty' => array('email_cty', 'email_company_taken')
    );
    if ($duplicateField !== null && isset($duplicateMessages[$duplicateField])) {
        list($field, $messageKey) = $duplicateMessages[$duplicateField];
        $_SESSION['register_errors'] = array($field => ValidationService::validationMessage($messageKey));
        $_SESSION['register_old'] = $oldValues;
        header('Location: dangky.php');
        exit();
    }

    $_SESSION['error_message'] = ValidationService::validationMessage('system');
    $_SESSION['register_old'] = $oldValues;
    header('Location: dangky.php');
    exit();
}

$insertStatement->close();
session_regenerate_id(true);
$_SESSION['is_login'] = true;
$_SESSION['user_id'] = $newId;
$_SESSION['user_ho'] = $verifyUser['ho'];
$_SESSION['user_name'] = $verifyUser['ten'];
$_SESSION['user_email'] = $verifyUser['email'];
$_SESSION['user_sdt'] = $verifyUser['sdt'];
$_SESSION['user_ngaysinh'] = $verifyUser['ngaysinh'];
$_SESSION['user_diachi'] = $verifyUser['diachi'];

header('Location: taikhoan.php');
exit();

    }

    public function logout()
    {

session_start();
session_unset();
session_destroy();

header("Location: index.php");
exit();
    }

    private static function redirectOtpError($field, $message)
{
    unset($_SESSION['reset_success'], $_SESSION['reset_error']);
    $_SESSION['reset_password_errors'] = array($field => $message);
    header('Location: datlaimatkhau.php');
    exit();
}

    public function resetPassword()
    {

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('TT_SHOP_PASSWORD_RESET_FLOW')) {
    define('TT_SHOP_PASSWORD_RESET_FLOW', true);
}
require_once (dirname(__DIR__, 2)) . '/connect.php';
require_once (dirname(__DIR__, 2)) . '/validate_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: quenmatkhau.php');
    exit();
}



$csrfToken = $_POST['csrf_token'] ?? '';
$storedCsrfToken = $_SESSION['reset_password_csrf_token'] ?? '';
if (
    !is_string($csrfToken) ||
    !is_string($storedCsrfToken) ||
    $csrfToken === '' ||
    !hash_equals($storedCsrfToken, $csrfToken)
) {
    unset($_SESSION['reset_password_csrf_token'], $_SESSION['reset_success']);
    $_SESSION['reset_error'] = ValidationService::validationMessage('reset_csrf_expired');
    header('Location: quenmatkhau.php');
    exit();
}
unset($_SESSION['reset_password_csrf_token']);

$pendingEmail = $_SESSION['reset_pending_email'] ?? '';
if (!is_string($pendingEmail) || $pendingEmail === '') {
    $_SESSION['reset_error'] = ValidationService::validationMessage('reset_otp_invalid');
    header('Location: quenmatkhau.php');
    exit();
}

$otpInput = $_POST['otp_code'] ?? '';
$otp = is_string($otpInput) ? trim($otpInput) : '';
if ($otp === '') {
    self::redirectOtpError('otp_code', ValidationService::validationMessage('reset_otp_required'));
}
if (preg_match('/^[0-9]{6}$/', $otp) !== 1) {
    self::redirectOtpError('otp_code', ValidationService::validationMessage('reset_otp_format'));
}

$newPasswordInput = $_POST['new_password'] ?? '';
$confirmPasswordInput = $_POST['confirm_password'] ?? '';
$newPassword = is_string($newPasswordInput) ? $newPasswordInput : '';
$confirmPassword = is_string($confirmPasswordInput) ? $confirmPasswordInput : '';
$transactionStarted = false;
$currentSql = '';

try {
    $conn->begin_transaction();
    $transactionStarted = true;

    $currentSql = 'SELECT id, email, matkhau FROM users WHERE email = ? LIMIT 1 FOR UPDATE';
    $userStatement = $conn->prepare($currentSql);
    $userStatement->bind_param('s', $pendingEmail);
    $userStatement->execute();
    $user = $userStatement->get_result()->fetch_assoc();
    $userStatement->close();

    if (!$user) {
        $conn->rollback();
        $transactionStarted = false;
        self::redirectOtpError('otp_code', ValidationService::validationMessage('reset_otp_invalid'));
    }

    $userId = (int)$user['id'];
    $currentSql = 'SELECT id, token_hash, attempts
        FROM password_resets
        WHERE user_id = ? AND expires_at > NOW() AND used_at IS NULL
        ORDER BY created_at DESC
        LIMIT 1 FOR UPDATE';
    $otpStatement = $conn->prepare($currentSql);
    $otpStatement->bind_param('i', $userId);
    $otpStatement->execute();
    $resetRecord = $otpStatement->get_result()->fetch_assoc();
    $otpStatement->close();

    if (!$resetRecord) {
        $conn->rollback();
        $transactionStarted = false;
        self::redirectOtpError('otp_code', ValidationService::validationMessage('reset_otp_invalid'));
    }

    $expectedHash = hash_hmac('sha256', $otp, session_id());
    if (!hash_equals((string)$resetRecord['token_hash'], $expectedHash)) {
        $nextAttempts = (int)$resetRecord['attempts'] + 1;
        $currentSql = 'UPDATE password_resets
            SET attempts = ?, used_at = CASE WHEN ? >= 5 THEN NOW() ELSE used_at END
            WHERE id = ?';
        $attemptUpdate = $conn->prepare($currentSql);
        $attemptUpdate->bind_param('iii', $nextAttempts, $nextAttempts, $resetRecord['id']);
        if ($attemptUpdate->execute() !== true || $attemptUpdate->affected_rows !== 1) {
            throw new RuntimeException('Không cập nhật được số lần nhập mã xác minh.');
        }
        $attemptUpdate->close();
        $conn->commit();
        $transactionStarted = false;
        self::redirectOtpError('otp_code', ValidationService::validationMessage('reset_otp_invalid'));
    }

    $errors = array();
    $passwordError = ValidationService::passwordBlockingError($newPassword);
    if ($passwordError !== null) {
        $errors['new_password'] = $passwordError;
    }
    if ($newPassword !== $confirmPassword) {
        $errors['confirm_password'] = ValidationService::validationMessage('password_confirm');
    }
    if (!is_string($user['matkhau']) || $user['matkhau'] === '') {
        throw new RuntimeException('Tài khoản không có mật khẩu đã mã hóa hợp lệ.');
    }
    if (!$errors && password_verify($newPassword, $user['matkhau'])) {
        $errors['new_password'] = ValidationService::validationMessage('reset_password_same');
    }
    if ($errors) {
        $conn->rollback();
        $transactionStarted = false;
        $_SESSION['reset_password_errors'] = $errors;
        header('Location: datlaimatkhau.php');
        exit();
    }

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $currentSql = 'UPDATE users SET matkhau = ? WHERE id = ?';
    $update = $conn->prepare($currentSql);
    $update->bind_param('si', $passwordHash, $userId);
    if ($update->execute() !== true || $update->affected_rows !== 1) {
        throw new RuntimeException('Không cập nhật được mật khẩu người dùng.');
    }
    $update->close();

    $currentSql = 'DELETE FROM password_resets WHERE user_id = ?';
    $delete = $conn->prepare($currentSql);
    $delete->bind_param('i', $userId);
    if ($delete->execute() !== true || $delete->affected_rows < 1) {
        throw new RuntimeException('Không thể vô hiệu hóa mã xác minh.');
    }
    $delete->close();

    $conn->commit();
    $transactionStarted = false;

    session_regenerate_id(true);
    unset(
        $_SESSION['reset_pending_email'],
        $_SESSION['reset_password_errors'],
        $_SESSION['reset_password_csrf_token'],
        $_SESSION['reset_error'],
        $_SESSION['reset_dev_otp'],
        $_SESSION['reset_dev_link']
    );
    $_SESSION['reset_success'] = 'Đặt lại mật khẩu thành công. Vui lòng đăng nhập bằng mật khẩu mới.';
    header('Location: dangnhap.php');
    exit();
} catch (Throwable $exception) {
    if ($transactionStarted) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackException) {
            error_log(sprintf('%s:%d: Rollback failed: %s', (dirname(__DIR__, 2) . '/xulydatlaimatkhau.php'), __LINE__, $rollbackException->getMessage()));
        }
    }

    error_log(sprintf(
        '%s:%d: %s | SQL=%s',
        (dirname(__DIR__, 2) . '/xulydatlaimatkhau.php'),
        __LINE__,
        $exception->getMessage(),
        $currentSql
    ));
    $_SESSION['reset_password_errors'] = array('new_password' => ValidationService::validationMessage('reset_system'));
    header('Location: datlaimatkhau.php');
    exit();
}

    }

    public function checkPhone()
    {

define('TT_SHOP_JSON_REQUEST', true);
header('Content-Type: application/json; charset=utf-8');

require_once (dirname(__DIR__, 2)) . '/connect.php';
require_once (dirname(__DIR__, 2)) . '/validate_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('trung' => false));
    exit();
}

$phone = ValidationService::normalizeVnPhone($_POST['sdt'] ?? '');
if (!ValidationService::isValidVnPhone($phone)) {
    echo json_encode(array('trung' => false));
    exit();
}

try {
    echo json_encode(array('trung' => (new ValidationService($conn))->isPhoneInUse( $phone)));
} catch (Throwable $exception) {
    error_log(sprintf('%s:%d: %s', (dirname(__DIR__, 2) . '/kiem_tra_sdt.php'), __LINE__, $exception->getMessage()));
    http_response_code(503);
    echo json_encode(array('trung' => false));
}

    }
}
