<?php

/** Keep the normalized role table in sync with the legacy primary role column. */
function syncUserRoles(PDO $pdo, int $userId, string $primaryRole): void
{
    $roleCodes = $primaryRole === 'admin' ? ['staff', 'admin'] : [$primaryRole];
    $pdo->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$userId]);
    $insert = $pdo->prepare(
        'INSERT INTO user_roles (user_id, role_id)
         SELECT ?, id FROM roles WHERE code = ?'
    );
    foreach ($roleCodes as $roleCode) {
        $insert->execute([$userId, $roleCode]);
        if ($insert->rowCount() !== 1) {
            throw new RuntimeException('ไม่พบบทบาท ' . $roleCode . ' ในระบบ');
        }
    }
}

