<?php
require_once __DIR__.'/membership.php';
require_once __DIR__.'/payment_helpers.php';
require_once __DIR__ . '/app/bootstrap.php';

function upgrade_prices(): array {
    return MembershipUpgradeService::prices();
}
function upgrade_find(mysqli $db, int $id, ?int $uid=null): ?array {
    return (new MembershipUpgradeService($db))->find($id, $uid);
}
function upgrade_active(mysqli $db,int $uid): ?array {
    return (new MembershipUpgradeService($db))->active($uid);
}
function upgrade_new(mysqli $db,int $uid,int $target,int $expectedRank): array {
    return (new MembershipUpgradeService($db))->createUpgrade($uid, $target, $expectedRank);
}
function upgrade_validate(array $o,array $d,string $key='id'): string {
    return MembershipUpgradeService::validate($o, $d, $key);
}
function upgrade_sync(mysqli $db,array $o): array {
    return (new MembershipUpgradeService($db))->sync($o);
}
function upgrade_checkout(mysqli $db,array $o): string {
    return (new MembershipUpgradeService($db))->checkout($o);
}
