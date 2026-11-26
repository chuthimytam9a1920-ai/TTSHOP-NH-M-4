<?php

require_once __DIR__ . '/app/bootstrap.php';

function csrf_token()
{
    return CsrfService::token();
}

function csrf_check($submittedToken)
{
    return CsrfService::check($submittedToken);
}
