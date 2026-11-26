<?php
final class AccountController
{
    public function updateProfile()
    {

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once (dirname(__DIR__, 2)) . '/connect.php';
require_once (dirname(__DIR__, 2)) . '/validate_helpers.php';

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header('Location: dangnhap.php');
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$isAddressUpdate = isset($_POST['full_name']);
$sdt = ValidationService::normalizeVnPhone($_POST['sdt'] ?? '');
$diachi = trim($_POST['diachi'] ?? '');
$errorField = $isAddressUpdate ? 'edit_address_sdt' : 'profile_sdt';
$fieldErrors = array();
$ngaysinh = null;

if ($isAddressUpdate) {
    $fullName = ValidationService::normalizePersonName($_POST['full_name'] ?? '');
    $nameParts = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY);
    if (count($nameParts) < 2) {
        $fieldErrors['edit_address_name'] = ValidationService::validationMessage('full_name_required');
    } else {
        $ten = array_pop($nameParts);
        $ho = implode(' ', $nameParts);
        if (!ValidationService::validatePersonName($ho) || !ValidationService::validatePersonName($ten)) {
            $fieldErrors['edit_address_name'] = ValidationService::validationMessage('person_full_name');
        }
    }
} else {
    $ho = ValidationService::normalizePersonName($_POST['ho'] ?? '');
    $ten = ValidationService::normalizePersonName($_POST['ten'] ?? '');
    if (!ValidationService::validatePersonName($ho)) {
        $fieldErrors['profile_ho'] = ValidationService::validationMessage('person_first');
    }
    if (!ValidationService::validatePersonName($ten)) {
        $fieldErrors['profile_ten'] = ValidationService::validationMessage('person_last');
    }

    $rawBirthDate = trim($_POST['ngaysinh'] ?? '');
    if ($rawBirthDate !== '') {
        $birthDate = DateTime::createFromFormat('!Y-m-d', $rawBirthDate);
        $birthDateErrors = DateTime::getLastErrors();
        $invalidBirthDate = $birthDate === false
            || ($birthDateErrors !== false
                && ($birthDateErrors['warning_count'] > 0 || $birthDateErrors['error_count'] > 0))
            || $birthDate->format('Y-m-d') !== $rawBirthDate
            || $birthDate < new DateTime('1900-01-01')
            || $birthDate > new DateTime('today');

        if ($invalidBirthDate) {
            $fieldErrors['profile_ngaysinh'] = ValidationService::validationMessage('birth_date_invalid');
        } else {
            $ngaysinh = $rawBirthDate;
        }
    }
}

if (!ValidationService::isValidVnPhone($sdt)) {
    $fieldErrors[$errorField] = ValidationService::validationMessage('phone_invalid');
}

if ($fieldErrors) {
    $_SESSION['account_field_errors'] = $fieldErrors;
    header('Location: taikhoan.php');
    exit();
}

try {
    if ((new ValidationService($conn))->isPhoneInUse( $sdt, $userId)) {
        $_SESSION['account_field_errors'] = array($errorField => ValidationService::validationMessage('phone_taken'));
        header('Location: taikhoan.php');
        exit();
    }

    if ($isAddressUpdate) {
        $statement = $conn->prepare(
            'UPDATE users SET ho = ?, ten = ?, sdt = ?, diachi = ? WHERE id = ?'
        );
        $statement->bind_param('ssssi', $ho, $ten, $sdt, $diachi, $userId);
    } else {
        $statement = $conn->prepare(
            'UPDATE users SET ho = ?, ten = ?, sdt = ?, ngaysinh = ?, diachi = ? WHERE id = ?'
        );
        $statement->bind_param('sssssi', $ho, $ten, $sdt, $ngaysinh, $diachi, $userId);
    }
    if ($statement->execute() !== true) {
        throw new RuntimeException('Không thể cập nhật thông tin tài khoản.');
    }
    $statement->close();

    $_SESSION['user_ho'] = $ho;
    $_SESSION['user_name'] = $ten;
    $_SESSION['user_sdt'] = $sdt;
    $_SESSION['user_diachi'] = $diachi;
    if (!$isAddressUpdate) {
        $_SESSION['user_ngaysinh'] = $ngaysinh;
    }
} catch (Throwable $exception) {
    error_log(sprintf('%s:%d: %s', (dirname(__DIR__, 2) . '/capnhat_tk.php'), __LINE__, $exception->getMessage()));
    $message = ValidationService::duplicateConstraintField($exception) === 'sdt'
        ? ValidationService::validationMessage('phone_taken')
        : ValidationService::validationMessage('system');
    $_SESSION['account_field_errors'] = array($errorField => $message);
}

header('Location: taikhoan.php');
exit();

    }

    public function addAddress()
    {

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once (dirname(__DIR__, 2)) . '/connect.php';
require_once (dirname(__DIR__, 2)) . '/validate_helpers.php';
require_once (dirname(__DIR__, 2)) . '/csrf.php';

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header('Location: dangnhap.php');
    exit();
}

$userId = (int)$_SESSION['user_id'];
$hoTen = trim($_POST['ho_ten'] ?? '');
$sdt = ValidationService::normalizeVnPhone($_POST['sdt'] ?? '');
$congTy = trim($_POST['cong_ty'] ?? '');
$diaChi = trim($_POST['dia_chi'] ?? '');
$quocGia = trim($_POST['quoc_gia'] ?? 'Vietnam');
$maZip = trim($_POST['ma_zip'] ?? '');
$macDinh = isset($_POST['mac_dinh']) ? 1 : 0;

if (!CsrfService::check($_POST['csrf_token'] ?? null)) {
    $_SESSION['account_field_errors'] = array('address_action' => ValidationService::validationMessage('system'));
    header('Location: taikhoan.php');
    exit();
}

if (!ValidationService::isValidVnPhone($sdt)) {
    $_SESSION['account_field_errors'] = array('address_sdt' => ValidationService::validationMessage('phone_invalid'));
    header('Location: taikhoan.php');
    exit();
}

try {
    if ($macDinh === 1) {
        $resetStatement = $conn->prepare('UPDATE diachi_khachhang SET mac_dinh = 0 WHERE user_id = ?');
        $resetStatement->bind_param('i', $userId);
        $resetStatement->execute();
        $resetStatement->close();
    }

    $statement = $conn->prepare(
        'INSERT INTO diachi_khachhang
         (user_id, ho_ten, so_dien_thoai, cong_ty, dia_chi, quoc_gia, ma_zip, mac_dinh)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $statement->bind_param('issssssi', $userId, $hoTen, $sdt, $congTy, $diaChi, $quocGia, $maZip, $macDinh);
    $statement->execute();
    $statement->close();
} catch (Throwable $exception) {
    error_log(sprintf('%s:%d: %s', (dirname(__DIR__, 2) . '/themdiachi.php'), __LINE__, $exception->getMessage()));
    $_SESSION['account_field_errors'] = array('address_sdt' => ValidationService::validationMessage('system'));
}

header('Location: taikhoan.php');
exit();

    }

    public function deleteAddress()
    {

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once (dirname(__DIR__, 2)) . '/connect.php';
require_once (dirname(__DIR__, 2)) . '/validate_helpers.php';
require_once (dirname(__DIR__, 2)) . '/csrf.php';

if (!isset($_SESSION['is_login']) || $_SESSION['is_login'] !== true) {
    header('Location: dangnhap.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['account_field_errors'] = array('address_action' => ValidationService::validationMessage('system'));
    header('Location: taikhoan.php');
    exit();
}

$rawAddressId = $_POST['id'] ?? null;
$addressId = is_scalar($rawAddressId)
    ? filter_var((string)$rawAddressId, FILTER_VALIDATE_INT)
    : false;
$userId = (int)($_SESSION['user_id'] ?? 0);

if (!CsrfService::check($_POST['csrf_token'] ?? null) || !$addressId || $userId <= 0) {
    $_SESSION['account_field_errors'] = array('address_action' => ValidationService::validationMessage('system'));
    header('Location: taikhoan.php');
    exit();
}

try {
    $statement = $conn->prepare('DELETE FROM diachi_khachhang WHERE id = ? AND user_id = ?');
    $statement->bind_param('ii', $addressId, $userId);
    $statement->execute();
    $deletedRows = $statement->affected_rows;
    $statement->close();

    if ($deletedRows !== 1) {
        $_SESSION['account_field_errors'] = array('address_action' => ValidationService::validationMessage('system'));
    }
} catch (Throwable $exception) {
    error_log(sprintf('%s:%d: %s', (dirname(__DIR__, 2) . '/xoadiachi.php'), __LINE__, $exception->getMessage()));
    $_SESSION['account_field_errors'] = array('address_action' => ValidationService::validationMessage('system'));
}

header('Location: taikhoan.php');
exit();

    }
}
