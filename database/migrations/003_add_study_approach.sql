-- Add applicant learning approach selection.

ALTER TABLE abugida_applicants
    ADD COLUMN study_approach VARCHAR(30) NULL AFTER grade_level;
