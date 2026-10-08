-- Abugida SIS registration approval and payment workflow.

ALTER TABLE abugida_applicants
    ADD COLUMN registrar_decision_reason TEXT NULL AFTER status,
    ADD COLUMN registrar_reviewed_at DATETIME NULL AFTER registrar_decision_reason,
    ADD COLUMN registrar_reviewed_by INT NULL AFTER registrar_reviewed_at,
    ADD COLUMN payment_amount DECIMAL(12,2) NULL AFTER registrar_reviewed_by,
    ADD COLUMN payment_instructions TEXT NULL AFTER payment_amount,
    ADD COLUMN payment_status VARCHAR(30) NOT NULL DEFAULT 'NOT_REQUIRED' AFTER payment_instructions,
    ADD COLUMN receipt_stored_name VARCHAR(255) NULL AFTER payment_status,
    ADD COLUMN receipt_original_name VARCHAR(255) NULL AFTER receipt_stored_name,
    ADD COLUMN receipt_mime_type VARCHAR(100) NULL AFTER receipt_original_name,
    ADD COLUMN receipt_size INT UNSIGNED NULL AFTER receipt_mime_type,
    ADD COLUMN receipt_submitted_at DATETIME NULL AFTER receipt_size,
    ADD COLUMN finance_decision_reason TEXT NULL AFTER receipt_submitted_at,
    ADD COLUMN finance_reviewed_at DATETIME NULL AFTER finance_decision_reason,
    ADD COLUMN finance_reviewed_by INT NULL AFTER finance_reviewed_at,
    ADD COLUMN final_confirmed_at DATETIME NULL AFTER finance_reviewed_by,
    ADD COLUMN final_confirmed_by INT NULL AFTER final_confirmed_at,
    ADD COLUMN student_id INT NULL AFTER final_confirmed_by,
    ADD COLUMN generated_username VARCHAR(100) NULL AFTER student_id;

CREATE TABLE IF NOT EXISTS abugida_application_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    applicant_id BIGINT UNSIGNED NOT NULL,
    from_status VARCHAR(30) NULL,
    to_status VARCHAR(30) NOT NULL,
    action VARCHAR(80) NOT NULL,
    actor_type VARCHAR(30) NOT NULL,
    actor_id INT NULL,
    actor_name VARCHAR(190) NULL,
    reason TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_abugida_history_applicant (applicant_id),
    KEY idx_abugida_history_status (to_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
