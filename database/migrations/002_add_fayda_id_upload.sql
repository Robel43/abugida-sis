-- Add Fayda ID upload metadata to online registration applicants.

ALTER TABLE abugida_applicants
    ADD COLUMN fayda_stored_name VARCHAR(255) NULL AFTER document_size,
    ADD COLUMN fayda_original_name VARCHAR(255) NULL AFTER fayda_stored_name,
    ADD COLUMN fayda_mime_type VARCHAR(100) NULL AFTER fayda_original_name,
    ADD COLUMN fayda_size INT UNSIGNED NULL AFTER fayda_mime_type;
