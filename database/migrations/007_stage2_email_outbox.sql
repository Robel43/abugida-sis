-- Durable Stage 2 notifications. No existing applicant or core SIS data is changed.
CREATE TABLE IF NOT EXISTS abugida_email_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    history_id BIGINT UNSIGNED NOT NULL,
    applicant_id BIGINT UNSIGNED NOT NULL,
    actor_type VARCHAR(30) NOT NULL,
    expected_status VARCHAR(30) NOT NULL,
    recipient VARCHAR(190) NOT NULL,
    subject VARCHAR(190) NOT NULL,
    message TEXT NOT NULL,
    delivery_status VARCHAR(30) NOT NULL DEFAULT 'PENDING',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    last_error VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    attempted_at DATETIME NULL,
    sent_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_abugida_email_history (history_id),
    KEY idx_abugida_email_applicant (applicant_id),
    KEY idx_abugida_email_delivery (delivery_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
