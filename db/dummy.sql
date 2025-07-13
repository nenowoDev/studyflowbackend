
-- --- Dummy Data for `users` table ---

-- Admin
INSERT INTO `users` (`username`, `password_hash`, `role`, `full_name`, `email`, `matric_number`, `pin`, `profile_picture`) VALUES
('admin01', '1234', 'admin', 'Admin User', 'admin@example.com', NULL, NULL, 'https://placehold.co/128x128/9CA3AF/1F2937?text=Admin');

-- Lecturers
INSERT INTO `users` (`username`, `password_hash`, `role`, `full_name`, `email`, `matric_number`, `pin`, `profile_picture`) VALUES
('lecturer01', '1234', 'lecturer', 'Dr. Alice Smith', 'alice.smith@university.edu', NULL, NULL, 'https://placehold.co/128x128/90CDF4/2B6CB0?text=A.S.');
INSERT INTO `users` (`username`, `password_hash`, `role`, `full_name`, `email`, `matric_number`, `pin`, `profile_picture`) VALUES
('lecturer02', '1234', 'lecturer', 'Prof. Bob Johnson', 'bob.j@university.edu', NULL, NULL, 'https://placehold.co/128x128/A7F3D0/065F46?text=B.J.');

-- Academic Advisors
INSERT INTO `users` (`username`, `password_hash`, `role`, `full_name`, `email`, `matric_number`, `pin`, `profile_picture`) VALUES
('advisor01', '1234', 'advisor', 'Ms. Carol White', 'carol.w@university.edu', NULL, NULL, 'https://placehold.co/128x128/FDE68A/92400E?text=C.W.');
INSERT INTO `users` (`username`, `password_hash`, `role`, `full_name`, `email`, `matric_number`, `pin`, `profile_picture`) VALUES
('advisor02', '1234', 'advisor', 'Mr. David Green', 'david.g@university.edu', NULL, NULL, 'https://placehold.co/128x128/D1FAE5/065F46?text=D.G.');

-- Students
INSERT INTO `users` (`username`, `password_hash`, `role`, `full_name`, `email`, `matric_number`, `pin`, `profile_picture`) VALUES
('student1', '1234', 'student', 'Emily Brown', 'emily.b@student.university.edu', 'A20EC0001', '1234', 'https://placehold.co/128x128/FECACA/B91C1C?text=E.B.');
INSERT INTO `users` (`username`, `password_hash`, `role`, `full_name`, `email`, `matric_number`, `pin`, `profile_picture`) VALUES
('student2', '1234', 'student', 'Frank Miller', 'frank.m@student.university.edu', 'A20EC0002', '5678', 'https://placehold.co/128x128/DBEAFE/1E40AF?text=F.M.');
INSERT INTO `users` (`username`, `password_hash`, `role`, `full_name`, `email`, `matric_number`, `pin`, `profile_picture`) VALUES
('student3', '1234', 'student', 'Grace Davis', 'grace.d@student.university.edu', 'A20EC0003', '9012', 'https://placehold.co/128x128/D1FAE5/065F46?text=G.D.');
INSERT INTO `users` (`username`, `password_hash`, `role`, `full_name`, `email`, `matric_number`, `pin`, `profile_picture`) VALUES
('student4', '1234', 'student', 'Henry Wilson', 'henry.w@student.university.edu', 'A20EC0004', '3456', 'https://placehold.co/128x128/FFEDD5/9A3412?text=H.W.');

-- --- Dummy Data for `courses` table ---
-- Assuming lecturer01 (user_id 2) and lecturer02 (user_id 3)
INSERT INTO `courses` (`course_code`, `course_name`, `lecturer_id`) VALUES
('SECJ3483', 'Web Technology', 2); -- Assigned to Dr. Alice Smith
INSERT INTO `courses` (`course_code`, `course_name`, `lecturer_id`) VALUES
('SECR2043', 'Database Systems', 3); -- Assigned to Prof. Bob Johnson
INSERT INTO `courses` (`course_code`, `course_name`, `lecturer_id`) VALUES
('SCSR1013', 'Programming I', 2); -- Assigned to Dr. Alice Smith

-- --- Dummy Data for `enrollments` table ---
-- Assuming students (user_id 6, 7, 8, 9) and courses (course_id 1, 2, 3)
INSERT INTO `enrollments` (`student_id`, `course_id`, `enrollment_date`) VALUES
(6, 1, '2024-09-01'), -- Emily Brown enrolled in Web Technology
(7, 1, '2024-09-01'), -- Frank Miller enrolled in Web Technology
(8, 1, '2024-09-01'), -- Grace Davis enrolled in Web Technology
(9, 1, '2024-09-01'), -- Henry Wilson enrolled in Web Technology
(6, 2, '2024-09-01'), -- Emily Brown enrolled in Database Systems
(7, 3, '2024-09-01'); -- Frank Miller enrolled in Programming I

-- --- Dummy Data for `assessment_components` table ---
-- Assuming courses: Web Technology (course_id 1), Database Systems (course_id 2), Programming I (course_id 3)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(1, 'Quiz 1', 20.00, 10.00, FALSE),
(1, 'Assignment 1', 50.00, 20.00, FALSE),
(1, 'Lab Exercise', 30.00, 15.00, FALSE),
(1, 'Midterm Test', 100.00, 25.00, FALSE),
(1, 'Final Exam', 100.00, 30.00, TRUE), -- Total continuous: 10+20+15+25 = 70%

(2, 'Lab 1', 25.00, 10.00, FALSE),
(2, 'Project', 100.00, 40.00, FALSE),
(2, 'Final Exam', 100.00, 30.00, TRUE),

(3, 'Exercise 1', 10.00, 5.00, FALSE),
(3, 'Assignment', 60.00, 25.00, FALSE),
(3, 'Final Exam', 100.00, 30.00, TRUE);

-- --- Dummy Data for `student_marks` table ---
-- Assuming lecturer01 (user_id 2) and lecturer02 (user_id 3)
-- Enrollments: Emily (ID 1, course 1), Frank (ID 2, course 1), Grace (ID 3, course 1), Henry (ID 4, course 1), Emily (ID 5, course 2), Frank (ID 6, course 3)
-- Assessment Components:
-- Web Technology (course_id 1): Quiz 1 (ID 1), Assignment 1 (ID 2), Lab Exercise (ID 3), Midterm Test (ID 4), Final Exam (ID 5)
-- Database Systems (course_id 2): Lab 1 (ID 6), Project (ID 7), Final Exam (ID 8)
-- Programming I (course_id 3): Exercise 1 (ID 9), Assignment (ID 10), Final Exam (ID 11)

-- Emily Brown (Enrollment 1 - Web Technology)
INSERT INTO `student_marks` (`enrollment_id`, `component_id`, `mark_obtained`, `recorded_by`) VALUES
(1, 1, 18.00, 2), -- Quiz 1 (max 20)
(1, 2, 45.00, 2), -- Assignment 1 (max 50)
(1, 3, 27.00, 2), -- Lab Exercise (max 30)
(1, 4, 80.00, 2), -- Midterm Test (max 100)
(1, 5, 75.00, 2); -- Final Exam (max 100)

-- Frank Miller (Enrollment 2 - Web Technology)
INSERT INTO `student_marks` (`enrollment_id`, `component_id`, `mark_obtained`, `recorded_by`) VALUES
(2, 1, 15.00, 2),
(2, 2, 40.00, 2),
(2, 3, 20.00, 2),
(2, 4, 65.00, 2),
(2, 5, 60.00, 2);

-- Grace Davis (Enrollment 3 - Web Technology)
INSERT INTO `student_marks` (`enrollment_id`, `component_id`, `mark_obtained`, `recorded_by`) VALUES
(3, 1, 19.00, 2),
(3, 2, 48.00, 2),
(3, 3, 29.00, 2),
(3, 4, 92.00, 2),
(3, 5, 88.00, 2);

-- Henry Wilson (Enrollment 4 - Web Technology) - At-risk student example
INSERT INTO `student_marks` (`enrollment_id`, `component_id`, `mark_obtained`, `recorded_by`) VALUES
(4, 1, 8.00, 2),
(4, 2, 25.00, 2),
(4, 3, 10.00, 2),
(4, 4, 40.00, 2),
(4, 5, 35.00, 2);

-- Emily Brown (Enrollment 5 - Database Systems)
INSERT INTO `student_marks` (`enrollment_id`, `component_id`, `mark_obtained`, `recorded_by`) VALUES
(5, 6, 20.00, 3), -- Lab 1 (max 25)
(5, 7, 85.00, 3), -- Project (max 100)
(5, 8, 70.00, 3); -- Final Exam (max 100)

-- Frank Miller (Enrollment 6 - Programming I)
INSERT INTO `student_marks` (`enrollment_id`, `component_id`, `mark_obtained`, `recorded_by`) VALUES
(6, 9, 8.00, 2), -- Exercise 1 (max 10)
(6, 10, 50.00, 2), -- Assignment (max 60)
(6, 11, 70.00, 2); -- Final Exam (max 100)

-- --- Dummy Data for `remark_requests` table ---
-- Assuming mark_id for Emily Brown's Quiz 1 is 1.
-- Assuming mark_id for Frank Miller's Midterm Test (Web Tech) is 4.
INSERT INTO `remark_requests` (`mark_id`, `student_id`, `justification`, `request_date`, `status`, `lecturer_notes`) VALUES
(1, 6, 'I believe there was an error in grading Question 3. I followed the rubric exactly.', '2025-01-10 10:00:00', 'pending', NULL),
(4, 7, 'My script was submitted late due to technical issues, but I believe my answers were correct. Requesting re-evaluation.', '2025-01-15 14:30:00', 'rejected', 'Late submission policy applies. No re-evaluation.');


-- --- Dummy Data for `advisor_student` table ---
-- Assuming advisor01 (user_id 4) and advisor02 (user_id 5)
-- Students: Emily Brown (user_id 6), Frank Miller (user_id 7), Grace Davis (user_id 8), Henry Wilson (user_id 9)
INSERT INTO `advisor_student` (`advisor_id`, `student_id`) VALUES
(4, 6), -- Carol White advises Emily Brown
(4, 7), -- Carol White advises Frank Miller
(5, 8), -- David Green advises Grace Davis
(5, 9); -- David Green advises Henry Wilson

-- --- Dummy Data for `advisor_notes` table ---
-- Assuming advisor_student_id values based on the above inserts.
-- advisor_student_id 1: Carol White - Emily Brown
-- advisor_student_id 4: David Green - Henry Wilson (at-risk)
INSERT INTO `advisor_notes` (`advisor_student_id`, `note_content`, `meeting_date`) VALUES
(1, 'Discussed academic goals and course load for next semester. Emily is performing well.', '2025-01-20'),
(4, 'Meeting with Henry to discuss his performance in SECJ3483. Advised him to seek tutoring and attend more lab sessions. Follow-up needed next month.', '2025-01-22'),
(4, 'Second follow-up with Henry. Some improvement noted, but still needs to focus on fundamentals. Suggested joining a study group.', '2025-02-15');
