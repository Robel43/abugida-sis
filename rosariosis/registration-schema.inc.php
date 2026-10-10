<?php
/** Read-only readiness check: schema changes belong to the approved CLI runner. */
function abugida_registration_require_schema(): void
{
    global $DatabaseType;
    $required = [
        'abugida_applicants' => ['id', 'application_reference', 'phone', 'first_name', 'last_name', 'email',
            'grade_level', 'study_approach', 'document_stored_name', 'document_original_name',
            'document_mime_type', 'document_size', 'fayda_stored_name', 'fayda_original_name',
            'fayda_mime_type', 'fayda_size', 'status', 'current_step', 'submitted_at', 'created_at',
            'updated_at', 'registrar_decision_reason', 'registrar_reviewed_at', 'registrar_reviewed_by',
            'payment_amount', 'payment_instructions', 'payment_status', 'payment_access_token',
            'receipt_stored_name', 'receipt_original_name', 'receipt_mime_type', 'receipt_size',
            'receipt_submitted_at', 'finance_decision_reason', 'finance_reviewed_at', 'finance_reviewed_by',
            'final_confirmed_at', 'final_confirmed_by', 'student_id', 'generated_username'],
        'abugida_application_history' => ['id', 'applicant_id', 'from_status', 'to_status', 'action',
            'actor_type', 'actor_id', 'actor_name', 'reason', 'created_at'],
        'abugida_registration_fees' => ['id', 'school_id', 'syear', 'grade_id', 'amount', 'updated_by', 'updated_at'],
    ];
    $ready = $DatabaseType === 'mysql';
    if ($ready) {
        $columns = DBGet("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.columns
            WHERE table_schema=DATABASE() AND table_name IN
            ('abugida_applicants', 'abugida_application_history', 'abugida_registration_fees')");
        $present = [];
        foreach ($columns as $column) {
            $present[strtolower($column['TABLE_NAME'])][] = strtolower($column['COLUMN_NAME']);
        }
        foreach ($required as $table => $names) {
            if (array_diff($names, $present[$table] ?? [])) { $ready = false; break; }
        }
    }
    if (!$ready) {
        http_response_code(503);
        header('Content-Type: text/html; charset=UTF-8');
        header('Retry-After: 3600');
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Registration unavailable</title>';
        echo '<h1>Registration is temporarily unavailable</h1><p>Please try again later or contact the school.</p></html>';
        error_log('Abugida registration schema incomplete: back up the database and apply approved migrations 001–006 using database/migrate-registration.php.');
        exit;
    }
}
