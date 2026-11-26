<?php

// ==========================================
// TT SHOP - PASSWORD RESET HELPERS
// ==========================================

use PHPMailer\PHPMailer\PHPMailer;

$phpMailerAutoload = __DIR__ . '/vendor/autoload.php';
try {
    if (is_file($phpMailerAutoload)) {
        require_once $phpMailerAutoload;
    } else {
        $phpMailerFiles = array(
            __DIR__ . '/PHPMailer/src/Exception.php',
            __DIR__ . '/PHPMailer/src/PHPMailer.php',
            __DIR__ . '/PHPMailer/src/SMTP.php'
        );
        if (count(array_filter($phpMailerFiles, 'is_file')) === count($phpMailerFiles)) {
            foreach ($phpMailerFiles as $phpMailerFile) {
                require_once $phpMailerFile;
            }
        }
    }
} catch (Throwable $exception) {
    error_log(sprintf('%s:%d: Unable to load PHPMailer: %s', __FILE__, __LINE__, $exception->getMessage()));
}

require_once __DIR__ . '/app/bootstrap.php';

function resetMailConfig()
{
    return PasswordResetMailService::resetMailConfig();
}

function isLocalResetDevelopment($config = null)
{
    return PasswordResetMailService::isLocalResetDevelopment($config);
}


// ==========================================
// 2. TẠO ĐƯỜNG DẪN WEBSITE
// ==========================================

function resetBaseUrl()
{
    return PasswordResetMailService::resetBaseUrl();
}


// ==========================================
// 3. HÀM GỬI EMAIL KHÔI PHỤC
// ==========================================

function sendResetEmail($email, $resetCode)
{
    return PasswordResetMailService::sendResetEmail($email, $resetCode);
}
