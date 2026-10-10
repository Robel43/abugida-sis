-- Abugida SIS online registration applicant table.
-- Target: MariaDB/MySQL development and production databases.
-- Applicants remain separate from permanent RosarioSIS student records.

CREATE TABLE IF NOT EXISTS abugida_applicants (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    application_reference VARCHAR(40) NULL,
    phone VARCHAR(20) NOT NULL,
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    email VARCHAR(190) NULL,
    grade_level TINYINT UNSIGNED NULL,
    document_stored_name VARCHAR(255) NULL,
    document_original_name VARCHAR(255) NULL,
    document_mime_type VARCHAR(100) NULL,
    document_size INT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    current_step TINYINT UNSIGNED NOT NULL DEFAULT 1,
    submitted_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_abugida_applicants_reference (application_reference),
    UNIQUE KEY uq_abugida_applicants_phone (phone),
    KEY idx_abugida_applicants_status (status),
    KEY idx_abugida_applicants_grade (grade_level),
    CONSTRAINT chk_abugida_applicants_grade
        CHECK (grade_level IS NULL OR grade_level BETWEEN 7 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
