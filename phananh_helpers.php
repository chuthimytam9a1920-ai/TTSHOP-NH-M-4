<?php
require_once __DIR__ . '/csrf.php';

require_once __DIR__ . '/app/bootstrap.php';

function pa_h($v): string {
    return FeedbackService::h($v);
}
function pa_text($v): string {
    return FeedbackService::text($v);
}
function pa_len(string $s): int {
    return FeedbackService::len($s);
}
function pa_id($v): int {
    return FeedbackService::id($v);
}
function pa_types(): array {
    return FeedbackService::types();
}
function pa_statuses(): array {
    return FeedbackService::statuses();
}
function pa_code($id): string {
    return FeedbackService::code($id);
}
function pa_date($v): string {
    return FeedbackService::date($v);
}
function pa_stmt($conn, string $sql, string $types='', array $args=[]) {
    return (new FeedbackService($conn))->stmt($sql, $types, $args);
}
function pa_rows($conn, string $sql, string $types='', array $args=[]): array {
    return (new FeedbackService($conn))->rows($sql, $types, $args);
}
function pa_one($conn, string $sql, string $types='', array $args=[]): ?array {
    return (new FeedbackService($conn))->one($sql, $types, $args);
}
function pa_exec($conn, string $sql, string $types='', array $args=[]): void {
    (new FeedbackService($conn))->exec($sql, $types, $args);
}
function pa_customer($conn): ?array {
    return (new FeedbackService($conn))->customer();
}
function pa_admin($conn): int {
    return (new FeedbackService($conn))->admin();
}
function pa_upload(): array {
    return FeedbackService::upload();
}
function pa_flash(string $key, int $owner, string $msg): void {
    FeedbackService::flash($key, $owner, $msg);
}
function pa_get_flash(string $key, int $owner): string {
    return FeedbackService::getFlash($key, $owner);
}
function pa_error(Throwable $e): string {
    return FeedbackService::error($e);
}
function pa_status_html(string $s): string {
    return FeedbackService::statusHtml($s);
}
function pa_history(array $replies): void {
    FeedbackService::history($replies);
}
