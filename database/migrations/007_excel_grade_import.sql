-- Abugida SIS Excel grade import audit trail.

CREATE TABLE IF NOT EXISTS abugida_grade_import_batches (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id INT NOT NULL,
    syear INT NOT NULL,
    grade_id INT NOT NULL,
    course_period_id INT NOT NULL,
    marking_period_id INT NOT NULL,
    source_filename VARCHAR(255) NOT NULL,
    total_rows INT NOT NULL DEFAULT 0,
    imported_rows INT NOT NULL DEFAULT 0,
    skipped_rows INT NOT NULL DEFAULT 0,
    imported_by INT NULL,
    imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_abugida_grade_import_batch_context (school_id, syear, course_period_id, marking_period_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS abugida_grade_import_rows (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    batch_id BIGINT UNSIGNED NOT NULL,
    excel_row_number INT NOT NULL,
    student_id INT NULL,
    student_name VARCHAR(255) NULL,
    old_percent DECIMAL(6,2) NULL,
    new_percent DECIMAL(6,2) NULL,
    action VARCHAR(30) NOT NULL,
    message VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_abugida_grade_import_rows_batch (batch_id),
    KEY idx_abugida_grade_import_rows_student (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
