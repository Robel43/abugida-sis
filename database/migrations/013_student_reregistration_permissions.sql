-- Permissions for existing-student re-registration workflow.

INSERT INTO profile_exceptions (PROFILE_ID, MODNAME, CAN_USE, CAN_EDIT)
SELECT up.ID, 'Students/ReRegistration.php', 'Y', 'N'
FROM user_profiles up
WHERE up.PROFILE='student'
AND NOT EXISTS (
    SELECT 1 FROM profile_exceptions pe
    WHERE pe.PROFILE_ID=up.ID
    AND pe.MODNAME='Students/ReRegistration.php'
);

INSERT INTO profile_exceptions (PROFILE_ID, MODNAME, CAN_USE, CAN_EDIT)
SELECT up.ID, 'Custom/ReRegistrationReview.php', 'Y', 'Y'
FROM user_profiles up
WHERE up.PROFILE='admin'
AND NOT EXISTS (
    SELECT 1 FROM profile_exceptions pe
    WHERE pe.PROFILE_ID=up.ID
    AND pe.MODNAME='Custom/ReRegistrationReview.php'
);

INSERT INTO profile_exceptions (PROFILE_ID, MODNAME, CAN_USE, CAN_EDIT)
SELECT up.ID, 'Custom/ReRegistrationPayments.php', 'Y', 'Y'
FROM user_profiles up
WHERE up.PROFILE='admin'
AND NOT EXISTS (
    SELECT 1 FROM profile_exceptions pe
    WHERE pe.PROFILE_ID=up.ID
    AND pe.MODNAME='Custom/ReRegistrationPayments.php'
);
