-- Grant the Abugida Student Portal pages to existing student profiles.
-- Student pages are read-only and always use the authenticated session student ID.

INSERT INTO profile_exceptions (PROFILE_ID, MODNAME, CAN_USE, CAN_EDIT)
SELECT up.ID, 'Students/StudentDashboard.php', 'Y', 'N'
FROM user_profiles up
WHERE up.PROFILE='student'
AND NOT EXISTS (
    SELECT 1 FROM profile_exceptions pe
    WHERE pe.PROFILE_ID=up.ID
    AND pe.MODNAME='Students/StudentDashboard.php'
);

INSERT INTO profile_exceptions (PROFILE_ID, MODNAME, CAN_USE, CAN_EDIT)
SELECT up.ID, 'Students/MyCourses.php', 'Y', 'N'
FROM user_profiles up
WHERE up.PROFILE='student'
AND NOT EXISTS (
    SELECT 1 FROM profile_exceptions pe
    WHERE pe.PROFILE_ID=up.ID
    AND pe.MODNAME='Students/MyCourses.php'
);

INSERT INTO profile_exceptions (PROFILE_ID, MODNAME, CAN_USE, CAN_EDIT)
SELECT up.ID, 'Students/MyGrades.php', 'Y', 'N'
FROM user_profiles up
WHERE up.PROFILE='student'
AND NOT EXISTS (
    SELECT 1 FROM profile_exceptions pe
    WHERE pe.PROFILE_ID=up.ID
    AND pe.MODNAME='Students/MyGrades.php'
);

INSERT INTO profile_exceptions (PROFILE_ID, MODNAME, CAN_USE, CAN_EDIT)
SELECT up.ID, 'Students/MyPayments.php', 'Y', 'N'
FROM user_profiles up
WHERE up.PROFILE='student'
AND NOT EXISTS (
    SELECT 1 FROM profile_exceptions pe
    WHERE pe.PROFILE_ID=up.ID
    AND pe.MODNAME='Students/MyPayments.php'
);

INSERT INTO profile_exceptions (PROFILE_ID, MODNAME, CAN_USE, CAN_EDIT)
SELECT up.ID, 'Students/MyProfile.php', 'Y', 'N'
FROM user_profiles up
WHERE up.PROFILE='student'
AND NOT EXISTS (
    SELECT 1 FROM profile_exceptions pe
    WHERE pe.PROFILE_ID=up.ID
    AND pe.MODNAME='Students/MyProfile.php'
);
