    -- Disable foreign key checks temporarily to allow truncating tables with dependencies
    SET FOREIGN_KEY_CHECKS = 0;

    -- Truncate tables with foreign keys first, in reverse dependency order
    TRUNCATE TABLE `advisor_notes`;
    TRUNCATE TABLE `remark_requests`;
    TRUNCATE TABLE `student_marks`;
    TRUNCATE TABLE `assessment_components`;
    TRUNCATE TABLE `enrollments`;
    TRUNCATE TABLE `advisor_student`;

    -- Truncate parent tables last
    TRUNCATE TABLE `courses`;
    TRUNCATE TABLE `users`;

    -- Re-enable foreign key checks
    SET FOREIGN_KEY_CHECKS = 1;