<?php
// Chat TT SHOP: danh tinh va vai tro chi duoc xac dinh tren server.
require_once __DIR__ . '/app/bootstrap.php';

function ttchat_session(): void {
    ChatService::session();
}
function ttchat_identity(): array {
    return ChatService::identity();
}
function ttchat_token(string $scope): string {
    return ChatService::token($scope);
}
function ttchat_csrf(string $scope, $token): bool {
    return ChatService::csrf($scope, $token);
}
function ttchat_escape($value): string {
    return ChatService::escape($value);
}
function ttchat_query(mysqli $conn, string $sql, string $types = '', array $params = []) {
    return (new ChatService($conn))->query($sql, $types, $params);
}
function ttchat_conversation(mysqli $conn, array $identity, bool $create = false): ?array {
    return (new ChatService($conn))->conversation($identity, $create);
}
function ttchat_customer_name(mysqli $conn, array $identity): string {
    return (new ChatService($conn))->customerName($identity);
}
function ttchat_message($value): ?string {
    return ChatService::message($value);
}
function ttchat_bot_reply(string $message): string {
    return ChatService::botReply($message);
}
function ttchat_insert(mysqli $conn, int $id, string $name, string $role, string $message): void {
    (new ChatService($conn))->insert($id, $name, $role, $message);
}
function ttchat_messages(mysqli $conn, int $id): mysqli_result {
    return (new ChatService($conn))->messages($id);
}
function ttchat_json(array $data, int $status = 200): void {
    ChatService::json($data, $status);
}
