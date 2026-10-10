-- Grant the Abugida Class Grade Report to existing administrator and teacher profiles.

INSERT INTO profile_exceptions (PROFILE_ID, MODNAME, CAN_USE, CAN_EDIT)
SELECT up.ID, 'Grades/ClassGradeReport.php', 'Y', 'N'
FROM user_profiles up
WHERE up.PROFILE IN ('admin','teacher')
AND NOT EXISTS (
    SELECT 1
    FROM profile_exceptions pe
    WHERE pe.PROFILE_ID=up.ID
    AND pe.MODNAME='Grades/ClassGradeReport.php'
);
