
-- Table for all users (Lecturers, Students, Academic Advisors, Admins)
-- Uses a single table with a 'role' column to differentiate user types.
CREATE TABLE `users` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('lecturer', 'student', 'advisor', 'admin') NOT NULL,
    `email` VARCHAR(100) UNIQUE,
    `full_name` VARCHAR(200) NOT NULL,
    `matric_number` VARCHAR(20) UNIQUE ,
    `pin` VARCHAR(100) ,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `profile_picture` VARCHAR(255) 
);

-- Table for courses offered
CREATE TABLE `courses` (
    `course_id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_code` VARCHAR(50) NOT NULL UNIQUE,
    `course_name` VARCHAR(255) NOT NULL,
    `lecturer_id` INT NOT NULL ,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`lecturer_id`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT
);


CREATE TABLE `enrollments` (
    `enrollment_id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL ,
    `course_id` INT NOT NULL ,
    `enrollment_date` DATE NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE (`student_id`, `course_id`),
    FOREIGN KEY (`student_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`course_id`) ON DELETE CASCADE
);


CREATE TABLE `assessment_components` (
    `component_id` INT AUTO_INCREMENT PRIMARY KEY,
    `course_id` INT NOT NULL ,
    `component_name` VARCHAR(100) NOT NULL ,
    `max_mark` DECIMAL(5,2) NOT NULL ,
    `weight_percentage` DECIMAL(5,2) NOT NULL ,
    `is_final_exam` BOOLEAN DEFAULT FALSE ,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`course_id`) REFERENCES `courses`(`course_id`) ON DELETE CASCADE
) ;

CREATE TABLE `student_marks` (
    `mark_id` INT AUTO_INCREMENT PRIMARY KEY,
    `enrollment_id` INT NOT NULL,
    `component_id` INT NOT NULL ,
    `mark_obtained` DECIMAL(5,2) NOT NULL ,
    `recorded_by` INT NOT NULL  ,
    `recorded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE (`enrollment_id`, `component_id`), -- Ensures only one mark per student per component per course
    FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments`(`enrollment_id`) ON DELETE CASCADE,
    FOREIGN KEY (`component_id`) REFERENCES `assessment_components`(`component_id`) ON DELETE CASCADE,
    FOREIGN KEY (`recorded_by`) REFERENCES `users`(`user_id`) ON DELETE RESTRICT
) ;


CREATE TABLE `remark_requests` (
    `request_id` INT AUTO_INCREMENT PRIMARY KEY,
    `mark_id` INT NOT NULL ,
    `student_id` INT NOT NULL ,
    `justification` TEXT NOT NULL ,
    `request_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ,
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' NOT NULL ,
    `lecturer_notes` TEXT ,
    `resolved_by` INT ,
    `resolved_at` TIMESTAMP ,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`mark_id`) REFERENCES `student_marks`(`mark_id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`resolved_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL
) ;

-- Junction table to link Academic Advisors to their advisee students
CREATE TABLE `advisor_student` (
    `advisor_student_id` INT AUTO_INCREMENT PRIMARY KEY,
    `advisor_id` INT NOT NULL ,
    `student_id` INT NOT NULL ,
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (`advisor_id`, `student_id`), -- Ensures a unique advisor-student pairing
    FOREIGN KEY (`advisor_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
);

CREATE TABLE `advisor_notes` (
    `note_id` INT AUTO_INCREMENT PRIMARY KEY,
    `advisor_student_id` INT NOT NULL,
    `note_content` TEXT NOT NULL ,
    `meeting_date` DATE ,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`advisor_student_id`) REFERENCES `advisor_student`(`advisor_student_id`) ON DELETE CASCADE
) ;


CREATE TABLE `notifications` (
    `notification_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `type` VARCHAR(50), 
    `related_id` INT, 
    `is_read` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE
);