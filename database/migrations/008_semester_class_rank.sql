-- Abugida semester / full-year percentage averages and class rank.

CREATE TABLE IF NOT EXISTS abugida_student_academic_rank (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    school_id INT NOT NULL,
    syear INT NOT NULL,
    student_id INT NOT NULL,
    grade_id INT NOT NULL,
    period_type VARCHAR(3) NOT NULL,
    marking_period_id INT NOT NULL,
    average_percent DECIMAL(6,2) NOT NULL,
    subject_count INT NOT NULL DEFAULT 0,
    rank_position INT NOT NULL,
    cohort_size INT NOT NULL,
    calculated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_abugida_rank_student_period (
        school_id, syear, student_id, period_type, marking_period_id
    ),
    KEY idx_abugida_rank_cohort (
        school_id, syear, grade_id, period_type, marking_period_id, rank_position
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;
