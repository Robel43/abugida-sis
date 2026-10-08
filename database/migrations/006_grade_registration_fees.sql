-- Grade-based registration fees for online applications.
-- One fee per school year and grade.

CREATE TABLE IF NOT EXISTS abugida_registration_fees (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id INT NOT NULL,
    syear INT NOT NULL,
    grade_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    updated_by INT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_abugida_registration_fee (school_id, syear, grade_id),
    KEY idx_abugida_registration_fee_grade (grade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
