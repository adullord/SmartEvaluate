<?php

/**
 * Additive database schema used by the in-app updater.
 * Never place DROP, TRUNCATE, DELETE, UPDATE or data-seeding statements here.
 */
function appDatabaseSchema(): array
{
    return [
        'departments' => [
            'create' => "CREATE TABLE IF NOT EXISTS `departments` (`id` INT NOT NULL AUTO_INCREMENT,`service_code` VARCHAR(10) DEFAULT NULL,`name` VARCHAR(255) NOT NULL,`short_name` VARCHAR(255) DEFAULT NULL,`type` ENUM('SSO','RPST','ADMIN') NOT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `uq_departments_service_code` (`service_code`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => ['service_code' => 'VARCHAR(10) DEFAULT NULL'],
        ],
        'positions' => [
            'create' => "CREATE TABLE IF NOT EXISTS `positions` (`id` INT NOT NULL AUTO_INCREMENT,`name` VARCHAR(255) NOT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => [],
        ],
        'ranks' => [
            'create' => "CREATE TABLE IF NOT EXISTS `ranks` (`id` INT NOT NULL AUTO_INCREMENT,`name` VARCHAR(255) NOT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => [],
        ],
        'roles' => [
            'create' => "CREATE TABLE IF NOT EXISTS `roles` (`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,`code` VARCHAR(30) NOT NULL,`name` VARCHAR(100) NOT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `code` (`code`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => ['code' => 'VARCHAR(30) DEFAULT NULL', 'name' => 'VARCHAR(100) DEFAULT NULL', 'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'],
        ],
        'users' => [
            'create' => "CREATE TABLE IF NOT EXISTS `users` (`id` INT NOT NULL AUTO_INCREMENT,`username` VARCHAR(13) NOT NULL,`password` VARCHAR(255) NOT NULL,`fullname` VARCHAR(255) NOT NULL,`role` ENUM('admin','ss_amphoe','sso_assistant','director','staff') NOT NULL,`department_id` INT NOT NULL,`position_id` INT NOT NULL,`rank_id` INT NOT NULL,`expected_level` INT DEFAULT 1,`is_active` TINYINT(1) DEFAULT 1,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `username` (`username`),KEY `department_id` (`department_id`),KEY `position_id` (`position_id`),KEY `rank_id` (`rank_id`),CONSTRAINT `users_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),CONSTRAINT `users_ibfk_2` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`),CONSTRAINT `users_ibfk_3` FOREIGN KEY (`rank_id`) REFERENCES `ranks` (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => ['expected_level' => 'INT DEFAULT 1', 'is_active' => 'TINYINT(1) DEFAULT 1'],
        ],
        'user_roles' => [
            'create' => "CREATE TABLE IF NOT EXISTS `user_roles` (`user_id` INT NOT NULL,`role_id` INT UNSIGNED NOT NULL,PRIMARY KEY (`user_id`,`role_id`),KEY `fk_user_roles_role` (`role_id`),CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => ['user_id' => 'INT DEFAULT NULL', 'role_id' => 'INT UNSIGNED DEFAULT NULL'],
        ],
        'evaluation_cycles' => [
            'create' => "CREATE TABLE IF NOT EXISTS `evaluation_cycles` (`id` INT NOT NULL AUTO_INCREMENT,`fiscal_year` VARCHAR(4) NOT NULL,`round_name` VARCHAR(255) NOT NULL,`start_date` DATE NOT NULL,`end_date` DATE NOT NULL,`status` ENUM('active','closed') DEFAULT 'active',`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => [],
        ],
        'competencies' => [
            'create' => "CREATE TABLE IF NOT EXISTS `competencies` (`id` INT NOT NULL AUTO_INCREMENT,`name` VARCHAR(255) NOT NULL,`description` TEXT DEFAULT NULL,`type` ENUM('core','functional') NOT NULL,`order_seq` INT DEFAULT 1,PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => [],
        ],
        'competency_levels' => [
            'create' => "CREATE TABLE IF NOT EXISTS `competency_levels` (`competency_id` INT NOT NULL,`expected_level` INT NOT NULL,`level_description` TEXT NOT NULL,PRIMARY KEY (`competency_id`,`expected_level`),CONSTRAINT `competency_levels_ibfk_1` FOREIGN KEY (`competency_id`) REFERENCES `competencies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => [],
        ],
        'indicators' => [
            'create' => "CREATE TABLE IF NOT EXISTS `indicators` (`id` INT NOT NULL AUTO_INCREMENT,`competency_id` INT NOT NULL,`expected_level` INT DEFAULT NULL,`position_id` INT DEFAULT NULL,`indicator_text` TEXT NOT NULL,`order_seq` INT DEFAULT 1,PRIMARY KEY (`id`),KEY `competency_id` (`competency_id`),KEY `position_id` (`position_id`),CONSTRAINT `indicators_ibfk_1` FOREIGN KEY (`competency_id`) REFERENCES `competencies` (`id`) ON DELETE CASCADE,CONSTRAINT `indicators_ibfk_2` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => ['expected_level' => 'INT DEFAULT NULL', 'position_id' => 'INT DEFAULT NULL'],
        ],
        'evaluation_templates' => [
            'create' => "CREATE TABLE IF NOT EXISTS `evaluation_templates` (`id` INT NOT NULL AUTO_INCREMENT,`position_id` INT NOT NULL,`expected_level` INT NOT NULL,`competency_id` INT NOT NULL,`weight` DECIMAL(5,2) NOT NULL,`level_description` TEXT DEFAULT NULL,PRIMARY KEY (`id`),KEY `position_id` (`position_id`),KEY `competency_id` (`competency_id`),CONSTRAINT `evaluation_templates_ibfk_1` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE CASCADE,CONSTRAINT `evaluation_templates_ibfk_2` FOREIGN KEY (`competency_id`) REFERENCES `competencies` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => ['level_description' => 'TEXT DEFAULT NULL'],
        ],
        'evaluator_mapping' => [
            'create' => "CREATE TABLE IF NOT EXISTS `evaluator_mapping` (`id` INT NOT NULL AUTO_INCREMENT,`cycle_id` INT NOT NULL,`evaluatee_id` INT NOT NULL,`evaluator_id` INT NOT NULL,PRIMARY KEY (`id`),KEY `cycle_id` (`cycle_id`),KEY `evaluatee_id` (`evaluatee_id`),KEY `evaluator_id` (`evaluator_id`),CONSTRAINT `evaluator_mapping_ibfk_1` FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles` (`id`) ON DELETE CASCADE,CONSTRAINT `evaluator_mapping_ibfk_2` FOREIGN KEY (`evaluatee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,CONSTRAINT `evaluator_mapping_ibfk_3` FOREIGN KEY (`evaluator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => [],
        ],
        'evaluations' => [
            'create' => "CREATE TABLE IF NOT EXISTS `evaluations` (`id` INT NOT NULL AUTO_INCREMENT,`cycle_id` INT NOT NULL,`evaluatee_id` INT NOT NULL,`evaluator_id` INT NOT NULL,`status` ENUM('draft','submitted','acknowledged','returned') DEFAULT 'draft',`total_score_base5` DECIMAL(10,4) DEFAULT 0.0000,`total_score_base100` DECIMAL(10,2) DEFAULT 0.00,`acknowledged_at` TIMESTAMP NULL DEFAULT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `cycle_id` (`cycle_id`),KEY `evaluatee_id` (`evaluatee_id`),KEY `evaluator_id` (`evaluator_id`),CONSTRAINT `evaluations_ibfk_1` FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles` (`id`),CONSTRAINT `evaluations_ibfk_2` FOREIGN KEY (`evaluatee_id`) REFERENCES `users` (`id`),CONSTRAINT `evaluations_ibfk_3` FOREIGN KEY (`evaluator_id`) REFERENCES `users` (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => ['status' => "ENUM('draft','submitted','acknowledged','returned') DEFAULT 'draft'", 'total_score_base5' => 'DECIMAL(10,4) DEFAULT 0.0000', 'total_score_base100' => 'DECIMAL(10,2) DEFAULT 0.00', 'acknowledged_at' => 'TIMESTAMP NULL DEFAULT NULL', 'updated_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
        ],
        'evaluation_scores' => [
            'create' => "CREATE TABLE IF NOT EXISTS `evaluation_scores` (`id` INT NOT NULL AUTO_INCREMENT,`evaluation_id` INT NOT NULL,`indicator_id` INT NOT NULL,`score` INT NOT NULL,`reason` TEXT DEFAULT NULL,PRIMARY KEY (`id`),KEY `evaluation_id` (`evaluation_id`),KEY `indicator_id` (`indicator_id`),CONSTRAINT `evaluation_scores_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`id`) ON DELETE CASCADE,CONSTRAINT `evaluation_scores_ibfk_2` FOREIGN KEY (`indicator_id`) REFERENCES `indicators` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => ['reason' => 'TEXT DEFAULT NULL'],
        ],
        'evaluation_logs' => [
            'create' => "CREATE TABLE IF NOT EXISTS `evaluation_logs` (`id` INT NOT NULL AUTO_INCREMENT,`evaluation_id` INT NOT NULL,`user_id` INT NOT NULL,`action` VARCHAR(255) NOT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `evaluation_id` (`evaluation_id`),KEY `user_id` (`user_id`),CONSTRAINT `evaluation_logs_ibfk_1` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`id`) ON DELETE CASCADE,CONSTRAINT `evaluation_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => [],
        ],
        'kpi_indicators' => [
            'create' => "CREATE TABLE IF NOT EXISTS `kpi_indicators` (`id` INT NOT NULL AUTO_INCREMENT,`cycle_id` INT NOT NULL,`name` TEXT NOT NULL,`target_label` VARCHAR(255) DEFAULT NULL,`unit` VARCHAR(100) DEFAULT NULL,`weight` DECIMAL(8,2) NOT NULL DEFAULT 0,`target_value` DECIMAL(14,4) DEFAULT NULL,`score_1_threshold` DECIMAL(14,4) NOT NULL DEFAULT 0,`score_2_threshold` DECIMAL(14,4) NOT NULL DEFAULT 0,`score_3_threshold` DECIMAL(14,4) NOT NULL DEFAULT 0,`score_4_threshold` DECIMAL(14,4) NOT NULL DEFAULT 0,`score_5_threshold` DECIMAL(14,4) NOT NULL DEFAULT 0,`scoring_direction` ENUM('ascending','descending') NOT NULL DEFAULT 'ascending',`order_seq` INT NOT NULL DEFAULT 1,`is_active` TINYINT(1) NOT NULL DEFAULT 1,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `idx_kpi_indicators_cycle` (`cycle_id`,`order_seq`),CONSTRAINT `fk_kpi_indicators_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => ['target_label' => 'VARCHAR(255) DEFAULT NULL', 'unit' => 'VARCHAR(100) DEFAULT NULL', 'weight' => 'DECIMAL(8,2) NOT NULL DEFAULT 0', 'target_value' => 'DECIMAL(14,4) DEFAULT NULL', 'score_1_threshold' => 'DECIMAL(14,4) NOT NULL DEFAULT 0', 'score_2_threshold' => 'DECIMAL(14,4) NOT NULL DEFAULT 0', 'score_3_threshold' => 'DECIMAL(14,4) NOT NULL DEFAULT 0', 'score_4_threshold' => 'DECIMAL(14,4) NOT NULL DEFAULT 0', 'score_5_threshold' => 'DECIMAL(14,4) NOT NULL DEFAULT 0', 'scoring_direction' => "ENUM('ascending','descending') NOT NULL DEFAULT 'ascending'", 'order_seq' => 'INT NOT NULL DEFAULT 1', 'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1', 'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP', 'updated_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
        ],
        'kpi_assignments' => [
            'create' => "CREATE TABLE IF NOT EXISTS `kpi_assignments` (`id` INT NOT NULL AUTO_INCREMENT,`indicator_id` INT NOT NULL,`user_id` INT NOT NULL,`responsibility_type` ENUM('primary','secondary') NOT NULL DEFAULT 'primary',`assigned_by` INT NOT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `uq_kpi_assignment` (`indicator_id`,`user_id`),KEY `idx_kpi_assignment_user` (`user_id`),CONSTRAINT `fk_kpi_assignment_indicator` FOREIGN KEY (`indicator_id`) REFERENCES `kpi_indicators` (`id`) ON DELETE CASCADE,CONSTRAINT `fk_kpi_assignment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,CONSTRAINT `fk_kpi_assignment_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => ['responsibility_type' => "ENUM('primary','secondary') NOT NULL DEFAULT 'primary'", 'assigned_by' => 'INT DEFAULT NULL', 'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'],
        ],
        'kpi_results' => [
            'create' => "CREATE TABLE IF NOT EXISTS `kpi_results` (`id` INT NOT NULL AUTO_INCREMENT,`indicator_id` INT NOT NULL,`department_id` INT NOT NULL,`actual_value` DECIMAL(14,4) DEFAULT NULL,`percentage` DECIMAL(14,4) DEFAULT NULL,`score` DECIMAL(5,2) DEFAULT NULL,`weighted_score` DECIMAL(12,4) DEFAULT NULL,`note` TEXT DEFAULT NULL,`entered_by` INT NOT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `uq_kpi_result` (`indicator_id`,`department_id`),KEY `idx_kpi_result_department` (`department_id`),CONSTRAINT `fk_kpi_result_indicator` FOREIGN KEY (`indicator_id`) REFERENCES `kpi_indicators` (`id`) ON DELETE CASCADE,CONSTRAINT `fk_kpi_result_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT,CONSTRAINT `fk_kpi_result_entered_by` FOREIGN KEY (`entered_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => ['actual_value' => 'DECIMAL(14,4) DEFAULT NULL', 'percentage' => 'DECIMAL(14,4) DEFAULT NULL', 'score' => 'DECIMAL(5,2) DEFAULT NULL', 'weighted_score' => 'DECIMAL(12,4) DEFAULT NULL', 'note' => 'TEXT DEFAULT NULL', 'entered_by' => 'INT DEFAULT NULL', 'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP', 'updated_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
        ],
        'component3_cycle_settings' => [
            'create' => "CREATE TABLE IF NOT EXISTS `component3_cycle_settings` (`cycle_id` INT NOT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`cycle_id`),CONSTRAINT `fk_component3_cycle_setting_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => ['created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'],
        ],
        'component3_items' => [
            'create' => "CREATE TABLE IF NOT EXISTS `component3_items` (`id` INT NOT NULL AUTO_INCREMENT,`cycle_id` INT NOT NULL,`item_no` INT NOT NULL,`name` TEXT NOT NULL,`weight` DECIMAL(8,2) NOT NULL DEFAULT 0,`target_value` DECIMAL(14,4) DEFAULT NULL,`target_label` VARCHAR(255) DEFAULT NULL,`input_type` ENUM('count','percentage','department_score') NOT NULL DEFAULT 'count',`audience` ENUM('all','sso_director') NOT NULL DEFAULT 'all',`responsible` VARCHAR(255) DEFAULT NULL,`score_1_threshold` DECIMAL(14,4) DEFAULT NULL,`score_2_threshold` DECIMAL(14,4) DEFAULT NULL,`score_3_threshold` DECIMAL(14,4) DEFAULT NULL,`score_4_threshold` DECIMAL(14,4) DEFAULT NULL,`score_5_threshold` DECIMAL(14,4) DEFAULT NULL,`is_active` TINYINT(1) NOT NULL DEFAULT 1,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `uq_component3_item_no` (`cycle_id`,`item_no`),KEY `idx_component3_item_cycle` (`cycle_id`,`is_active`,`item_no`),CONSTRAINT `fk_component3_item_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => ['item_no' => 'INT DEFAULT NULL', 'name' => 'TEXT DEFAULT NULL', 'weight' => 'DECIMAL(8,2) NOT NULL DEFAULT 0', 'target_value' => 'DECIMAL(14,4) DEFAULT NULL', 'target_label' => 'VARCHAR(255) DEFAULT NULL', 'input_type' => "ENUM('count','percentage','department_score') NOT NULL DEFAULT 'count'", 'audience' => "ENUM('all','sso_director') NOT NULL DEFAULT 'all'", 'responsible' => 'VARCHAR(255) DEFAULT NULL', 'score_1_threshold' => 'DECIMAL(14,4) DEFAULT NULL', 'score_2_threshold' => 'DECIMAL(14,4) DEFAULT NULL', 'score_3_threshold' => 'DECIMAL(14,4) DEFAULT NULL', 'score_4_threshold' => 'DECIMAL(14,4) DEFAULT NULL', 'score_5_threshold' => 'DECIMAL(14,4) DEFAULT NULL', 'is_active' => 'TINYINT(1) NOT NULL DEFAULT 1', 'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP', 'updated_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
        ],
        'component3_assessments' => [
            'create' => "CREATE TABLE IF NOT EXISTS `component3_assessments` (`id` INT NOT NULL AUTO_INCREMENT,`cycle_id` INT NOT NULL,`user_id` INT NOT NULL,`status` ENUM('draft','submitted') NOT NULL DEFAULT 'draft',`applicable_weight` DECIMAL(8,2) NOT NULL DEFAULT 0,`total_weighted_score` DECIMAL(12,4) NOT NULL DEFAULT 0,`final_score` DECIMAL(8,2) NOT NULL DEFAULT 0,`submitted_at` TIMESTAMP NULL DEFAULT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY (`id`),UNIQUE KEY `uq_component3_assessment` (`cycle_id`,`user_id`),KEY `idx_component3_assessment_user` (`user_id`),CONSTRAINT `fk_component3_assessment_cycle` FOREIGN KEY (`cycle_id`) REFERENCES `evaluation_cycles` (`id`) ON DELETE CASCADE,CONSTRAINT `fk_component3_assessment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => ['status' => "ENUM('draft','submitted') NOT NULL DEFAULT 'draft'", 'applicable_weight' => 'DECIMAL(8,2) NOT NULL DEFAULT 0', 'total_weighted_score' => 'DECIMAL(12,4) NOT NULL DEFAULT 0', 'final_score' => 'DECIMAL(8,2) NOT NULL DEFAULT 0', 'submitted_at' => 'TIMESTAMP NULL DEFAULT NULL', 'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP', 'updated_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
        ],
        'component3_scores' => [
            'create' => "CREATE TABLE IF NOT EXISTS `component3_scores` (`id` INT NOT NULL AUTO_INCREMENT,`assessment_id` INT NOT NULL,`item_no` TINYINT NOT NULL,`actual_value` DECIMAL(14,4) DEFAULT NULL,`percentage` DECIMAL(14,4) DEFAULT NULL,`score` DECIMAL(5,2) NOT NULL DEFAULT 0,`weight` DECIMAL(8,2) NOT NULL DEFAULT 0,`weighted_score` DECIMAL(12,4) NOT NULL DEFAULT 0,PRIMARY KEY (`id`),UNIQUE KEY `uq_component3_score` (`assessment_id`,`item_no`),CONSTRAINT `fk_component3_score_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `component3_assessments` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => ['actual_value' => 'DECIMAL(14,4) DEFAULT NULL', 'percentage' => 'DECIMAL(14,4) DEFAULT NULL', 'score' => 'DECIMAL(5,2) NOT NULL DEFAULT 0', 'weight' => 'DECIMAL(8,2) NOT NULL DEFAULT 0', 'weighted_score' => 'DECIMAL(12,4) NOT NULL DEFAULT 0'],
        ],
        'component3_logs' => [
            'create' => "CREATE TABLE IF NOT EXISTS `component3_logs` (`id` INT NOT NULL AUTO_INCREMENT,`assessment_id` INT NOT NULL,`user_id` INT NOT NULL,`action` VARCHAR(100) NOT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `idx_component3_log_assessment` (`assessment_id`),KEY `idx_component3_log_user` (`user_id`),CONSTRAINT `fk_component3_log_assessment` FOREIGN KEY (`assessment_id`) REFERENCES `component3_assessments` (`id`) ON DELETE CASCADE,CONSTRAINT `fk_component3_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => ['action' => 'VARCHAR(100) DEFAULT NULL', 'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'],
        ],
        'schema_migrations' => [
            'create' => "CREATE TABLE IF NOT EXISTS `schema_migrations` (`id` INT NOT NULL AUTO_INCREMENT,`migration_key` VARCHAR(100) NOT NULL,`executed_by` INT DEFAULT NULL,`summary` TEXT DEFAULT NULL,`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (`id`),KEY `idx_schema_migrations_created` (`created_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'columns' => ['migration_key' => 'VARCHAR(100) DEFAULT NULL', 'executed_by' => 'INT DEFAULT NULL', 'summary' => 'TEXT DEFAULT NULL', 'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'],
        ],
    ];
}

/** Extract every column definition from the hard-coded CREATE TABLE statement. */
function appDatabaseSchemaColumns(array $tableDefinition): array
{
    $sql = $tableDefinition['create'];
    $start = strpos($sql, '(');
    $end = strrpos($sql, ') ENGINE=');
    if ($start === false || $end === false || $end <= $start) {
        throw new RuntimeException('รูปแบบคำสั่งสร้างตารางไม่ถูกต้อง');
    }
    $body = substr($sql, $start + 1, $end - $start - 1);
    $parts = [];
    $buffer = '';
    $depth = 0;
    $inQuote = false;
    $length = strlen($body);
    for ($index = 0; $index < $length; $index++) {
        $character = $body[$index];
        if ($character === "'") {
            if ($inQuote && $index + 1 < $length && $body[$index + 1] === "'") {
                $buffer .= "''";
                $index++;
                continue;
            }
            $inQuote = !$inQuote;
        } elseif (!$inQuote) {
            if ($character === '(') $depth++;
            if ($character === ')') $depth--;
            if ($character === ',' && $depth === 0) {
                $parts[] = trim($buffer);
                $buffer = '';
                continue;
            }
        }
        $buffer .= $character;
    }
    if (trim($buffer) !== '') $parts[] = trim($buffer);

    $columns = [];
    foreach ($parts as $part) {
        if (preg_match('/^`([a-z0-9_]+)`\s+(.+)$/isD', $part, $matches)) {
            $columns[$matches[1]] = trim($matches[2]);
        }
    }
    foreach ($tableDefinition['columns'] as $column => $safeDefinition) {
        $columns[$column] = $safeDefinition;
    }
    return $columns;
}

function inspectAppDatabaseSchema(PDO $pdo): array
{
    $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($database === '') throw new RuntimeException('ไม่พบฐานข้อมูลที่กำลังใช้งาน');
    $tableQuery = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
    $columnQuery = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=?');
    $result = [];
    foreach (appDatabaseSchema() as $table => $definition) {
        $expectedColumns = appDatabaseSchemaColumns($definition);
        $tableQuery->execute([$database, $table]);
        $exists = (bool) $tableQuery->fetchColumn();
        $missingColumns = [];
        if ($exists && $expectedColumns) {
            $columnQuery->execute([$database, $table]);
            $existing = array_fill_keys($columnQuery->fetchAll(PDO::FETCH_COLUMN), true);
            foreach ($expectedColumns as $column => $unused) {
                if (!isset($existing[$column])) $missingColumns[] = $column;
            }
        }
        $result[$table] = ['exists' => $exists, 'missing_columns' => $missingColumns];
    }
    return $result;
}

function applyAppDatabaseSchema(PDO $pdo, int $adminId): array
{
    $lock = (int) $pdo->query("SELECT GET_LOCK('smart_evaluate_schema_update',10)")->fetchColumn();
    if ($lock !== 1) throw new RuntimeException('ระบบกำลังปรับโครงสร้างฐานข้อมูลอยู่ กรุณาลองใหม่อีกครั้ง');
    $actions = [];
    try {
        $before = inspectAppDatabaseSchema($pdo);
        foreach (appDatabaseSchema() as $table => $definition) {
            $expectedColumns = appDatabaseSchemaColumns($definition);
            if (!$before[$table]['exists']) {
                if (!preg_match('/^CREATE TABLE IF NOT EXISTS\s+/i', $definition['create']) || str_contains($definition['create'], ';')) {
                    throw new RuntimeException('คำสั่งสร้างตารางไม่ผ่านการตรวจสอบความปลอดภัย');
                }
                $pdo->exec($definition['create']);
                $actions[] = 'เพิ่มตาราง ' . $table;
                continue;
            }
            foreach ($before[$table]['missing_columns'] as $column) {
                if (!preg_match('/^[a-z0-9_]+$/D', $table) || !preg_match('/^[a-z0-9_]+$/D', $column)) {
                    throw new RuntimeException('พบชื่อโครงสร้างฐานข้อมูลที่ไม่ปลอดภัย');
                }
                $columnDefinition = $expectedColumns[$column];
                if (str_contains($columnDefinition, ';')) throw new RuntimeException('คำสั่งเพิ่มคอลัมน์ไม่ผ่านการตรวจสอบความปลอดภัย');
                if (stripos($columnDefinition, 'AUTO_INCREMENT') !== false) $columnDefinition .= ' PRIMARY KEY';
                $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $columnDefinition);
                $actions[] = 'เพิ่มคอลัมน์ ' . $table . '.' . $column;
            }
        }
        $roleColumnType = (string)$pdo->query(
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role'"
        )->fetchColumn();
        if ($roleColumnType !== '' && !str_contains($roleColumnType, "'sso_assistant'")) {
            $pdo->exec("ALTER TABLE `users` MODIFY `role` ENUM('admin','ss_amphoe','sso_assistant','director','staff') NOT NULL");
            $actions[] = 'เพิ่มบทบาทผู้ช่วย สสอ. ในตาราง users';
        }

        $roleExists = $pdo->prepare('SELECT COUNT(*) FROM roles WHERE code=?');
        $roleExists->execute(['sso_assistant']);
        if (!(int)$roleExists->fetchColumn()) {
            $pdo->prepare('INSERT INTO roles (code,name) VALUES (?,?)')->execute(['sso_assistant', 'ผู้ช่วย สสอ.']);
            $actions[] = 'เพิ่มบทบาทผู้ช่วย สสอ.';
        }
        if ($actions) {
            $stmt = $pdo->prepare('INSERT INTO schema_migrations (migration_key,executed_by,summary) VALUES (?,?,?)');
            $stmt->execute(['automatic-' . date('Ymd-His'), $adminId, implode('; ', $actions)]);
        }
        return $actions;
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('smart_evaluate_schema_update')");
    }
}
