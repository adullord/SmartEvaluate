<?php

/** Create the initial administrator only from an explicit, private deployment secret. */
function ensureDefaultAdmin(PDO $pdo, array $credentials = []): void
{
    $requiredTables = ['users', 'departments', 'positions', 'ranks'];
    $tableCheck = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    foreach ($requiredTables as $table) {
        $tableCheck->execute([$table]);
        if (!(int)$tableCheck->fetchColumn()) return;
    }

    $roleType = (string)$pdo->query(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role'"
    )->fetchColumn();
    if ($roleType !== '' && !str_contains($roleType, "'sso_assistant'")) {
        $pdo->exec("ALTER TABLE users MODIFY role ENUM('admin','ss_amphoe','sso_assistant','director','staff') NOT NULL");
    }
    if ((int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn() > 0) return;

    $username = trim((string)($credentials['username'] ?? ''));
    $password = (string)($credentials['password'] ?? '');
    if (!preg_match('/^[A-Za-z0-9._-]{4,13}$/', $username) || strlen($password) < 12 || strlen($password) > 255) {
        // Never create a predictable public default account.
        return;
    }

    $pdo->beginTransaction();
    try {
        $departmentId = (int)$pdo->query("SELECT id FROM departments WHERE type='SSO' ORDER BY id LIMIT 1")->fetchColumn();
        if (!$departmentId) {
            $pdo->prepare("INSERT INTO departments (name, type) VALUES (?, 'SSO')")
                ->execute(['สำนักงานสาธารณสุขอำเภอบันนังสตา']);
            $departmentId = (int)$pdo->lastInsertId();
        }

        $findPosition = $pdo->prepare('SELECT id FROM positions WHERE name=? LIMIT 1');
        $findPosition->execute(['นักวิชาการคอมพิวเตอร์']);
        $positionId = (int)$findPosition->fetchColumn();
        if (!$positionId) {
            $pdo->prepare('INSERT INTO positions (name) VALUES (?)')->execute(['นักวิชาการคอมพิวเตอร์']);
            $positionId = (int)$pdo->lastInsertId();
        }

        $findRank = $pdo->prepare('SELECT id FROM ranks WHERE name=? LIMIT 1');
        $findRank->execute(['ชำนาญการ']);
        $rankId = (int)$findRank->fetchColumn();
        if (!$rankId) {
            $pdo->prepare('INSERT INTO ranks (name) VALUES (?)')->execute(['ชำนาญการ']);
            $rankId = (int)$pdo->lastInsertId();
        }

        $usernameExists = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username=?');
        $usernameExists->execute([$username]);
        if ($usernameExists->fetchColumn()) throw new RuntimeException('Initial administrator username already exists');

        $insert = $pdo->prepare(
            'INSERT INTO users
             (username,password,fullname,role,department_id,position_id,rank_id)
             VALUES (?,?,?,?,?,?,?)'
        );
        $insert->execute([
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            'ผู้ดูแลระบบ',
            'admin',
            $departmentId,
            $positionId,
            $rankId,
        ]);
        $userId = (int)$pdo->lastInsertId();

        $tableCheck->execute(['roles']);
        $hasRoles = (bool)$tableCheck->fetchColumn();
        $tableCheck->execute(['user_roles']);
        $hasUserRoles = (bool)$tableCheck->fetchColumn();
        if ($hasRoles && $hasUserRoles) {
            require_once __DIR__ . '/user_role_helper.php';
            syncUserRoles($pdo, $userId, 'admin');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
