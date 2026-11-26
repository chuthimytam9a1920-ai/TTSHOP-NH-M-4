<?php
// Local autoload, PHP 8.0 compatible; no Composer dependency.
spl_autoload_register(static function (string $class): void {
    if (!preg_match('/^[A-Za-z][A-Za-z0-9]*$/D', $class)) return;
    foreach (['Core', 'Services', 'Repositories', 'Controllers', 'Payments'] as $folder) {
        $file = __DIR__ . '/' . $folder . '/' . $class . '.php';
        if (is_file($file)) { require_once $file; return; }
    }
});
