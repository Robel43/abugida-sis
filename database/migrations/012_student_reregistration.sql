-- Abugida SIS existing-student re-registration workflow.
-- Supports registration for a future Semester in the current academic year
-- or a future academic year / target grade without creating a new student account.

CREATE TABLE IF NOT EXISTS abugida_reregistration_requests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_reference VARCHAR(40) NOT NULL,
    student_id INT NOT NULL,
    school_id INT NOT NULL,
    from_syear INT NOT NULL,
    from_grade_id INT NULL,
    request_type VARCHAR(20) NOT NULL,
    target_syear INT NOT NULL,
    target_grade_id INT NOT NULL,
    target_semester_id INT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'SUBMITTED',
    registrar_decision_reason TEXT NULL,
    registrar_reviewed_at DATETIME NULL,
    registrar_reviewed_by INT NULL,
    payment_amount DECIMAL(12,2) NULL,
    payment_instructions TEXT NULL,
    payment_status VARCHAR(30) NOT NULL DEFAULT 'NOT_REQUIRED',
    receipt_stored_name VARCHAR(255) NULL,
    receipt_original_name VARCHAR(255) NULL,
    receipt_mime_type VARCHAR(100) NULL,
    receipt_size INT UNSIGNED NULL,
    receipt_submitted_at DATETIME NULL,
    finance_decision_reason TEXT NULL,
    finance_reviewed_at DATETIME NULL,
    finance_reviewed_by INT NULL,
    final_confirmed_at DATETIME NULL,
    final_confirmed_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_abugida_reregistration_reference (request_reference),
    KEY idx_abugida_reregistration_student (student_id, target_syear),
    KEY idx_abugida_reregistration_status (status),
    KEY idx_abugida_reregistration_target (school_id, target_syear, target_grade_id, target_semester_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;

CREATE TABLE IF NOT EXISTS abugida_reregistration_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id BIGINT UNSIGNED NOT NULL,
    from_status VARCHAR(30) NULL,
    to_status VARCHAR(30) NOT NULL,
    action VARCHAR(100) NOT NULL,
    actor_type VARCHAR(30) NOT NULL,
    actor_id INT NULL,
    actor_name VARCHAR(190) NULL,
    reason TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_abugida_reregistration_history_request (request_id),
    KEY idx_abugida_reregistration_history_status (to_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
