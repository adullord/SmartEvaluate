SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS kpi_indicators (
  id INT NOT NULL AUTO_INCREMENT,
  cycle_id INT NOT NULL,
  name TEXT NOT NULL,
  target_label VARCHAR(255) DEFAULT NULL,
  unit VARCHAR(100) DEFAULT NULL,
  weight DECIMAL(8,2) NOT NULL DEFAULT 0,
  target_value DECIMAL(14,4) DEFAULT NULL,
  score_1_threshold DECIMAL(14,4) NOT NULL,
  score_2_threshold DECIMAL(14,4) NOT NULL,
  score_3_threshold DECIMAL(14,4) NOT NULL,
  score_4_threshold DECIMAL(14,4) NOT NULL,
  score_5_threshold DECIMAL(14,4) NOT NULL,
  scoring_direction ENUM('ascending','descending') NOT NULL DEFAULT 'ascending',
  order_seq INT NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_kpi_indicators_cycle (cycle_id, order_seq),
  CONSTRAINT fk_kpi_indicators_cycle FOREIGN KEY (cycle_id) REFERENCES evaluation_cycles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kpi_assignments (
  id INT NOT NULL AUTO_INCREMENT,
  indicator_id INT NOT NULL,
  user_id INT NOT NULL,
  responsibility_type ENUM('primary','secondary') NOT NULL DEFAULT 'primary',
  assigned_by INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_kpi_assignment (indicator_id, user_id),
  KEY idx_kpi_assignment_user (user_id),
  CONSTRAINT fk_kpi_assignment_indicator FOREIGN KEY (indicator_id) REFERENCES kpi_indicators(id) ON DELETE CASCADE,
  CONSTRAINT fk_kpi_assignment_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_kpi_assignment_by FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kpi_results (
  id INT NOT NULL AUTO_INCREMENT,
  indicator_id INT NOT NULL,
  department_id INT NOT NULL,
  actual_value DECIMAL(14,4) DEFAULT NULL,
  percentage DECIMAL(14,4) DEFAULT NULL,
  score DECIMAL(5,2) DEFAULT NULL,
  weighted_score DECIMAL(12,4) DEFAULT NULL,
  note TEXT DEFAULT NULL,
  entered_by INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_kpi_result (indicator_id, department_id),
  KEY idx_kpi_result_department (department_id),
  CONSTRAINT fk_kpi_result_indicator FOREIGN KEY (indicator_id) REFERENCES kpi_indicators(id) ON DELETE CASCADE,
  CONSTRAINT fk_kpi_result_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT,
  CONSTRAINT fk_kpi_result_entered_by FOREIGN KEY (entered_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO kpi_indicators
  (cycle_id, name, target_label, unit, weight, target_value, score_1_threshold, score_2_threshold, score_3_threshold, score_4_threshold, score_5_threshold, scoring_direction, order_seq)
SELECT c.id, seed.name, seed.target_label, seed.unit, seed.weight, seed.target_value, seed.s1, seed.s2, seed.s3, seed.s4, seed.s5, seed.direction, seed.order_seq
FROM evaluation_cycles c
JOIN (
  SELECT 1 order_seq, 'หน่วยงานส่งข้อมูล 43 แฟ้ม ทันเวลา อย่างน้อยวันละ 1 ครั้ง' name, 'ร้อยละ 100' target_label, 'ร้อยละ' unit, 1.50 weight, 100.0000 target_value, 80.0000 s1, 85.0000 s2, 90.0000 s3, 95.0000 s4, 100.0000 s5, 'ascending' direction
  UNION ALL SELECT 2, 'การเบิกจ่ายค่าชดเชยบริการสาธารณสุข', '100 บาท/ประชากร', 'บาท/ประชากร', 1.50, 20.0000, 1.0000, 5.0000, 10.0000, 15.0000, 20.0000, 'ascending'
  UNION ALL SELECT 3, 'ประชากรซ้ำซ้อน ไม่เกินร้อยละ 1', 'น้อยกว่าร้อยละ 1', 'ร้อยละ', 1.50, 1.0000, 1.2500, 1.0000, 0.7500, 0.5000, 0.2500, 'descending'
) seed
WHERE c.fiscal_year = '2569' AND (c.round_name = '2' OR c.round_name LIKE '%2%')
  AND NOT EXISTS (SELECT 1 FROM kpi_indicators existing WHERE existing.cycle_id = c.id);
