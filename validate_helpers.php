<?php

require_once __DIR__ . '/app/bootstrap.php';

function normalize_vn_phone($phone)
{
    return ValidationService::normalizeVnPhone($phone);
}

function validation_messages()
{
    return ValidationService::validationMessages();
}

function validation_message($key)
{
    return ValidationService::validationMessage($key);
}

function duplicate_constraint_field($exception)
{
    return ValidationService::duplicateConstraintField($exception);
}

function is_valid_vn_phone($phone)
{
    return ValidationService::isValidVnPhone($phone);
}

function validate_personal_email($email)
{
    return ValidationService::validatePersonalEmail($email);
}

function is_phone_in_use($conn, $phone, $excludeUserId = null)
{
    return (new ValidationService($conn))->isPhoneInUse($phone, $excludeUserId);
}

function normalize_person_name($name)
{
    return ValidationService::normalizePersonName($name);
}

function validate_person_name($name)
{
    return ValidationService::validatePersonName($name);
}

function validate_company_name($name)
{
    return ValidationService::validateCompanyName($name);
}

function validate_tax_code($taxCode)
{
    return ValidationService::validateTaxCode($taxCode);
}

function validate_company_address($address)
{
    return ValidationService::validateCompanyAddress($address);
}

function disposable_email_domains()
{
    return ValidationService::disposableEmailDomains();
}

function validate_company_email($email, $personalEmail)
{
    return ValidationService::validateCompanyEmail($email, $personalEmail);
}

function common_passwords()
{
    return ValidationService::commonPasswords();
}

function password_blocking_error($password)
{
    return ValidationService::passwordBlockingError($password);
}

function password_soft_warnings($password, $email, $phone, $name)
{
    return ValidationService::passwordSoftWarnings($password, $email, $phone, $name);
}

function phone_validation_message()
{
    return ValidationService::phoneValidationMessage();
}

