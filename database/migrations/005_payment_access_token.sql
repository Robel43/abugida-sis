-- Add secure payment-link token for approved applicants.

ALTER TABLE abugida_applicants
    ADD COLUMN payment_access_token VARCHAR(64) NULL AFTER payment_status,
    ADD UNIQUE KEY uq_abugida_payment_access_token (payment_access_token);
