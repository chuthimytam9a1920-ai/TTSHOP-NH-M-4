<?php
// TT SHOP - payOS REST API. No credentials in source control.
require_once __DIR__ . '/app/bootstrap.php';
function payos_config(): array {
    return PayOSService::config();
}
function payos_ready(): bool {
    return PayOSService::ready();
}
function payos_signature(array $data, string $key): string {
    return PayOSService::signature($data, $key);
}
function payos_api(string $method, string $path, ?array $body = null): array {
    return PayOSService::api($method, $path, $body);
}
function payos_label(array $o): string {
    return PayOSService::label($o);
}
function payos_escape($v): string {
    return PayOSService::escape($v);
}
function payos_order(mysqli $db, int $id, ?int $owner = null): ?array {
    return (new PayOSService($db))->order($id, $owner);
}
function payos_validate_data(array $order, array $d, string $idKey = 'id'): string {
    return PayOSService::validateData($order, $d, $idKey);
}
function payos_url_valid(string $url): bool {
    return PayOSService::urlValid($url);
}
// Only a server-to-server GET determines final payment state; returnUrl is never trusted.
function payos_apply(mysqli $db, int $id, array $d): array {
    return (new PayOSService($db))->apply($id, $d);
}
function payos_sync(mysqli $db, array $o, bool $force = false): array {
    return (new PayOSService($db))->sync($o, $force);
}
function payos_create(mysqli $db, array $o): string {
    return (new PayOSService($db))->create($o);
}
