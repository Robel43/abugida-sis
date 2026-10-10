-- Grant the new Abugida grade programs to existing administrator-type profiles.
-- This ensures menu visibility and direct access after deployment.

INSERT INTO profile_exceptions (PROFILE_ID, MODNAME, CAN_USE, CAN_EDIT)
SELECT up.ID, 'Grades/ClassRank.php', 'Y', 'Y'
FROM user_profiles up
WHERE up.PROFILE='admin'
AND NOT EXISTS (
    SELECT 1
    FROM profile_exceptions pe
    WHERE pe.PROFILE_ID=up.ID
    AND pe.MODNAME='Grades/ClassRank.php'
);

INSERT INTO profile_exceptions (PROFILE_ID, MODNAME, CAN_USE, CAN_EDIT)
SELECT up.ID, 'Grades/GradeImport.php', 'Y', 'Y'
FROM user_profiles up
WHERE up.PROFILE='admin'
AND NOT EXISTS (
    SELECT 1
    FROM profile_exceptions pe
    WHERE pe.PROFILE_ID=up.ID
    AND pe.MODNAME='Grades/GradeImport.php'
);
