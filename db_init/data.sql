-- Create 10 Students
INSERT INTO `users` (`username`, `password_hash`, `role`, `email`, `full_name`, `matric_number`, `pin`) VALUES
('student01', '1234', 'student', 'student01@example.com', 'Alice Smith', 'S10001', '4567'),
('student02', '1234', 'student', 'student02@example.com', 'Bob Johnson', 'S10002', '4567'),
('student03', '1234', 'student', 'student03@example.com', 'Charlie Brown', 'S10003', '4567'),
('student04', '1234', 'student', 'student04@example.com', 'Diana Prince', 'S10004', '4567'),
('student05', '1234', 'student', 'student05@example.com', 'Eve Adams', 'S10005', '4567'),
('student06', '1234', 'student', 'student06@example.com', 'Frank White', 'S10006', '4567'),
('student07', '1234', 'student', 'student07@example.com', 'Grace Lee', 'S10007', '4567'),
('student08', '1234', 'student', 'student08@example.com', 'Henry Davis', 'S10008', '4567'),
('student09', '1234', 'student', 'student09@example.com', 'Ivy Chen', 'S10009', '4567'),
('student10', '1234', 'student', 'student10@example.com', 'Jack Wilson', 'S10010', '4567');

-- Create 3 Lecturers
INSERT INTO `users` (`username`, `password_hash`, `role`, `email`, `full_name`) VALUES
('lecturer01', '1234', 'lecturer', 'lecturer01@example.com', 'Dr. Alex Wong'),
('lecturer02', '1234', 'lecturer', 'lecturer02@example.com', 'Prof. Sarah Tan'),
('lecturer03', '1234', 'lecturer', 'lecturer03@example.com', 'Dr. Ben Lim');

-- Create 3 Advisors
INSERT INTO `users` (`username`, `password_hash`, `role`, `email`, `full_name`) VALUES
('advisor01', '1234', 'advisor', 'advisor01@example.com', 'Ms. Chloe Lee'),
('advisor02', '1234', 'advisor', 'advisor02@example.com', 'Mr. David Chan'),
('advisor03', '1234', 'advisor', 'advisor03@example.com', 'Ms. Emily Goh');

-- Create 1 Admin
INSERT INTO `users` (`username`, `password_hash`, `role`, `email`, `full_name`) VALUES
('admin01', '1234', 'admin', 'admin01@example.com', 'Admin System');


-- Assuming user_ids for lecturers:
-- lecturer01: user_id = 11
-- lecturer02: user_id = 12
-- lecturer03: user_id = 13

-- Insert Courses
INSERT INTO `courses` (`course_code`, `course_name`, `lecturer_id`, `credit_hours`) VALUES
('SECI1013', 'Discrete Structure', 11, 3),
('SECJ1013', 'Programming Technique I', 11, 3),
('SECR1013', 'Digital Logic', 12, 3),
('SECP1513', 'Technology & Information System', 12, 3),
('SECI1113', 'Computational Mathematics', 11, 3),
('SECI1143', 'Probability & Statistical Data Analysis', 12, 3),
('SECJ1023', 'Programming Technique II', 11, 3),
('SECR1033', 'Computer Organisation and Architecture', 13, 3),
('SECD2523', 'Database', 11, 3),
('SECD2613', 'System Analysis and Design', 12, 3),
('SECJ2013', 'Data Structure and Algorithm', 11, 3),
('SECR2213', 'Network Communications', 13, 3),
('SECV2113', 'Human Computer Interaction', 12, 3),
('SECJ2203', 'Software Engineering', 11, 3),
('SECV2223', 'Web Programming', 12, 3),
('SECR2043', 'Operating Systems', 13, 3),
('SECJ2154', 'Object Oriented Programming', 11, 4),
('SECJ3032', 'Software Engineering Project I', 11, 2),
('SECJ3203', 'Theory of Computer Science', 11, 3),
-- For Industrial Training, credit hours and assessment might vary, assuming typical
-- For 'HL' in Industrial Training, I'll assume it's "Hours/Lecture" and use a default credit hour value.
('SECJ4118', 'Industrial Training', 13, 8), -- Assuming lecturer03 supervises
('SECJ4114', 'Industrial Training Report', 13, 4),
('SECJ4134', 'Software Engineering Project II', 11, 4),
('SECD3761', 'Technopreneurship Seminar', 12, 1);

-- Insert Assessment Components for each course
-- (Assuming general component names and weights. You can adjust max_mark and weight_percentage as needed.)

-- For SECI1013 Discrete Structure (Course_id = 1 if inserted first)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(1, 'Final Exam', 100.00, 50.00, TRUE),
(1, 'Midterm Exam', 100.00, 25.00, FALSE),
(1, 'Quiz 1', 100.00, 10.00, FALSE),
(1, 'Assignment 1', 100.00, 15.00, FALSE);

-- For SECJ1013 Programming Technique I (Course_id = 2)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(2, 'Final Exam', 100.00, 40.00, TRUE),
(2, 'Midterm Exam', 100.00, 20.00, FALSE),
(2, 'Lab Assignments', 100.00, 30.00, FALSE),
(2, 'Quizzes', 100.00, 10.00, FALSE);

-- For SECR1013 Digital Logic (Course_id = 3)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(3, 'Final Exam', 100.00, 50.00, TRUE),
(3, 'Midterm Exam', 100.00, 30.00, FALSE),
(3, 'Project', 100.00, 20.00, FALSE);

-- For SECP1513 Technology & Information System (Course_id = 4)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(4, 'Final Exam', 100.00, 45.00, TRUE),
(4, 'Midterm Exam', 100.00, 25.00, FALSE),
(4, 'Presentation', 100.00, 20.00, FALSE),
(4, 'Participation', 100.00, 10.00, FALSE);

-- For SECI1113 Computational Mathematics (Course_id = 5)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(5, 'Final Exam', 100.00, 50.00, TRUE),
(5, 'Midterm Exam', 100.00, 30.00, FALSE),
(5, 'Homework', 100.00, 20.00, FALSE);

-- For SECI1143 Probability & Statistical Data Analysis (Course_id = 6)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(6, 'Final Exam', 100.00, 50.00, TRUE),
(6, 'Assignments', 100.00, 30.00, FALSE),
(6, 'Quizzes', 100.00, 20.00, FALSE);

-- For SECJ1023 Programming Technique II (Course_id = 7)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(7, 'Final Exam', 100.00, 40.00, TRUE),
(7, 'Practical Exam', 100.00, 30.00, FALSE),
(7, 'Project', 100.00, 20.00, FALSE),
(7, 'Quizzes', 100.00, 10.00, FALSE);

-- For SECR1033 Computer Organisation and Architecture (Course_id = 8)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(8, 'Final Exam', 100.00, 50.00, TRUE),
(8, 'Midterm Exam', 100.00, 25.00, FALSE),
(8, 'Lab Work', 100.00, 25.00, FALSE);

-- For SECD2523 Database (Course_id = 9)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(9, 'Final Exam', 100.00, 40.00, TRUE),
(9, 'Project', 100.00, 30.00, FALSE),
(9, 'Quizzes', 100.00, 15.00, FALSE),
(9, 'Assignments', 100.00, 15.00, FALSE);

-- For SECD2613 System Analysis and Design (Course_id = 10)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(10, 'Final Exam', 100.00, 40.00, TRUE),
(10, 'Group Project', 100.00, 35.00, FALSE),
(10, 'Case Study', 100.00, 25.00, FALSE);

-- For SECJ2013 Data Structure and Algorithm (Course_id = 11)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(11, 'Final Exam', 100.00, 50.00, TRUE),
(11, 'Midterm Exam', 100.00, 25.00, FALSE),
(11, 'Programming Assignments', 100.00, 25.00, FALSE);

-- For SECR2213 Network Communications (Course_id = 12)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(12, 'Final Exam', 100.00, 45.00, TRUE),
(12, 'Lab Exercises', 100.00, 30.00, FALSE),
(12, 'Quizzes', 100.00, 25.00, FALSE);

-- For SECV2113 Human Computer Interaction (Course_id = 13)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(13, 'Final Exam', 100.00, 40.00, TRUE),
(13, 'Usability Study', 100.00, 30.00, FALSE),
(13, 'Design Project', 100.00, 30.00, FALSE);

-- For SECJ2203 Software Engineering (Course_id = 14)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(14, 'Final Exam', 100.00, 40.00, TRUE),
(14, 'Team Project', 100.00, 40.00, FALSE),
(14, 'Quizzes', 100.00, 20.00, FALSE);

-- For SECV2223 Web Programming (Course_id = 15)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(15, 'Final Project', 100.00, 40.00, FALSE),
(15, 'Midterm Exam', 100.00, 20.00, FALSE),
(15, 'Assignments', 100.00, 30.00, FALSE),
(15, 'Quizzes', 100.00, 10.00, FALSE);

-- For SECR2043 Operating Systems (Course_id = 16)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(16, 'Final Exam', 100.00, 50.00, TRUE),
(16, 'Assignments', 100.00, 30.00, FALSE),
(16, 'Lab Exercises', 100.00, 20.00, FALSE);

-- For SECJ2154 Object Oriented Programming (Course_id = 17)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(17, 'Final Exam', 100.00, 40.00, TRUE),
(17, 'Programming Project', 100.00, 30.00, FALSE),
(17, 'Midterm Exam', 100.00, 20.00, FALSE),
(17, 'Quizzes', 100.00, 10.00, FALSE);

-- For SECJ3032 Software Engineering Project I (Course_id = 18)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(18, 'Project Proposal', 100.00, 30.00, FALSE),
(18, 'Progress Report', 100.00, 30.00, FALSE),
(18, 'Final Presentation', 100.00, 40.00, FALSE);

-- For SECJ3203 Theory of Computer Science (Course_id = 19)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(19, 'Final Exam', 100.00, 50.00, TRUE),
(19, 'Assignments', 100.00, 30.00, FALSE),
(19, 'Quizzes', 100.00, 20.00, FALSE);

-- For SECJ4118 Industrial Training (Course_id = 20) - Often pass/fail or based on logbook/report
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(20, 'Internship Performance Evaluation', 100.00, 70.00, FALSE),
(20, 'Logbook Submission', 100.00, 30.00, FALSE);

-- For SECJ4114 Industrial Training Report (Course_id = 21)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(21, 'Report Submission', 100.00, 100.00, FALSE);

-- For SECJ4134 Software Engineering Project II (Course_id = 22)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(22, 'Final Project Demo', 100.00, 40.00, FALSE),
(22, 'Project Report', 100.00, 30.00, FALSE),
(22, 'Peer Evaluation', 100.00, 10.00, FALSE),
(22, 'Supervisor Assessment', 100.00, 20.00, FALSE);

-- For SECD3761 Technopreneurship Seminar (Course_id = 23)
INSERT INTO `assessment_components` (`course_id`, `component_name`, `max_mark`, `weight_percentage`, `is_final_exam`) VALUES
(23, 'Seminar Participation', 100.00, 40.00, FALSE),
(23, 'Business Idea Pitch', 100.00, 60.00, FALSE);


-- Step 1: Assign all students to advisors (one advisor per student)

-- Assign student01 to student04 to advisor01 (user_id=14)
INSERT INTO `advisor_student` (`advisor_id`, `student_id`, `assigned_at`) VALUES
(14, 1, '2025-07-22'), -- student01
(14, 2, '2025-07-22'), -- student02
(14, 3, '2025-07-22'), -- student03
(14, 4, '2025-07-22'); -- student04

-- Assign student05 to student07 to advisor02 (user_id=15)
INSERT INTO `advisor_student` (`advisor_id`, `student_id`, `assigned_at`) VALUES
(15, 5, '2025-07-22'), -- student05
(15, 6, '2025-07-22'), -- student06
(15, 7, '2025-07-22'); -- student07

-- Assign student08 to student10 to advisor03 (user_id=16)
INSERT INTO `advisor_student` (`advisor_id`, `student_id`, `assigned_at`) VALUES
(16, 8, '2025-07-22'), -- student08
(16, 9, '2025-07-22'), -- student09
(16, 10, '2025-07-22'); -- student10

-- Step 2: Create fake advisor notes (at least 3 for each student)
-- This requires knowing the advisor_student_id. Assuming auto-increment from 1
-- advisor_student_id 1: advisor_id=14, student_id=1 (student01)
-- advisor_student_id 2: advisor_id=14, student_id=2 (student02)
-- advisor_student_id 3: advisor_id=14, student_id=3 (student03)
-- advisor_student_id 4: advisor_id=14, student_id=4 (student04)
-- advisor_student_id 5: advisor_id=15, student_id=5 (student05)
-- advisor_student_id 6: advisor_id=15, student_id=6 (student06)
-- advisor_student_id 7: advisor_id=15, student_id=7 (student07)
-- advisor_student_id 8: advisor_id=16, student_id=8 (student08)
-- advisor_student_id 9: advisor_id=16, student_id=9 (student09)
-- advisor_student_id 10: advisor_id=16, student_id=10 (student10)


-- Notes for Student01 (advisor_student_id=1, assigned to advisor01)
INSERT INTO `advisor_notes` (`advisor_student_id`, `note_content`, `meeting_date`, `recommendations`, `follow_up_required`) VALUES
(1, 'Initial meeting to discuss academic goals and course selections. Student seems motivated.', '2025-07-22', '{"recommendations": ["Focus on core subjects", "Explore extracurriculars"]}', FALSE),
(1, 'Follow-up on mid-term performance. Noticed a slight dip in Programming Technique I. Discussed strategies for improvement.', '2025-10-15', '{"recommendations": ["Attend tutoring sessions for Programming Technique I", "Allocate more study time"]}', TRUE),
(1, 'Reviewed end-of-semester grades. Good progress after implementing advice. Discussed potential specialization areas for next year.', '2026-01-20', '{"recommendations": ["Research software engineering track", "Consider a summer internship"]}', FALSE);

-- Notes for Student02 (advisor_student_id=2, assigned to advisor01)
INSERT INTO `advisor_notes` (`advisor_student_id`, `note_content`, `meeting_date`, `recommendations`, `follow_up_required`) VALUES
(2, 'First meeting. Student expressed interest in AI/Machine Learning. Advised on foundational courses.', '2025-08-01', '{"recommendations": ["Prioritize Computational Mathematics", "Join AI student club"]}', FALSE),
(2, 'Discussion about time management challenges. Recommended using a study planner and setting realistic goals.', '2025-11-05', '{"recommendations": ["Utilize university academic support services", "Break down large tasks"]}', TRUE),
(2, 'Positive progress report. Student has improved significantly in challenging courses. Discussed research opportunities.', '2026-02-10', '{"recommendations": ["Look into UGR or research assistant positions"]}', FALSE);

-- Notes for Student03 (advisor_student_id=3, assigned to advisor01)
INSERT INTO `advisor_notes` (`advisor_student_id`, `note_content`, `meeting_date`, `recommendations`, `follow_up_required`) VALUES
(3, 'Initial academic planning session. Student seems well-organized but anxious about workload. Recommended stress management techniques.', '2025-07-25', '{"recommendations": ["Balance study with leisure activities", "Explore campus wellness programs"]}', FALSE),
(3, 'Meeting regarding a specific assignment in Digital Logic. Provided guidance on resource utilization and problem-solving.', '2025-09-20', '{"recommendations": ["Form study groups", "Review fundamental concepts regularly"]}', TRUE),
(3, 'Discussed future career paths. Student is considering a role in cybersecurity. Advised on relevant electives.', '2026-03-01', '{"recommendations": ["Enroll in Network Communications", "Attend cybersecurity workshops"]}', FALSE);

-- Notes for Student04 (advisor_student_id=4, assigned to advisor01)
INSERT INTO `advisor_notes` (`advisor_student_id`, `note_content`, `meeting_date`, `recommendations`, `follow_up_required`) VALUES
(4, 'Introductory meeting. Student is a transfer student, discussed credit transfers and academic alignment.', '2025-08-10', '{"recommendations": ["Verify all transferred credits", "Familiarize with university policies"]}', FALSE),
(4, 'Mid-semester check-in. Student is adapting well, but expressed concerns about a group project. Offered conflict resolution strategies.', '2025-11-15', '{"recommendations": ["Communicate openly with team members", "Seek lecturer intervention if necessary"]}', FALSE),
(4, 'End of year review. Student has successfully integrated and performed strongly. Advised on leadership roles in student organizations.', '2026-04-05', '{"recommendations": ["Apply for student leadership positions", "Mentor junior students"]}', FALSE);

-- Notes for Student05 (advisor_student_id=5, assigned to advisor02)
INSERT INTO `advisor_notes` (`advisor_student_id`, `note_content`, `meeting_date`, `recommendations`, `follow_up_required`) VALUES
(5, 'First meeting. Student interested in entrepreneurship alongside technical skills. Suggested joining technopreneurship activities.', '2025-07-23', '{"recommendations": ["Participate in startup competitions", "Network with industry professionals"]}', FALSE),
(5, 'Discussion on project management for Database course. Provided resources for agile methodologies.', '2025-10-20', '{"recommendations": ["Read about Scrum and Kanban", "Practice agile development"]}', FALSE),
(5, 'Reviewed progress. Student is balancing academics and external projects well. Discussed potential for a capstone project idea.', '2026-01-25', '{"recommendations": ["Brainstorm capstone project topics", "Seek faculty mentorship for projects"]}', FALSE);

-- Notes for Student06 (advisor_student_id=6, assigned to advisor02)
INSERT INTO `advisor_notes` (`advisor_student_id`, `note_content`, `meeting_date`, `recommendations`, `follow_up_required`) VALUES
(6, 'Initial chat about academic load. Student feels overwhelmed with Programming Technique II. Suggested breaking down study sessions.', '2025-08-05', '{"recommendations": ["Implement Pomodoro Technique", "Attend supplemental instruction"]}', TRUE),
(6, 'Follow-up on academic performance. Improvement observed. Discussed importance of practical application in courses.', '2025-11-10', '{"recommendations": ["Work on personal coding projects", "Apply theoretical knowledge to real-world problems"]}', FALSE),
(6, 'Career discussion. Student is leaning towards becoming a full-stack developer. Advised on web programming courses and internships.', '2026-02-15', '{"recommendations": ["Take Web Programming and Software Engineering courses", "Apply for relevant internships"]}', FALSE);

-- Notes for Student07 (advisor_student_id=7, assigned to advisor02)
INSERT INTO `advisor_notes` (`advisor_student_id`, `note_content`, `meeting_date`, `recommendations`, `follow_up_required`) VALUES
(7, 'Academic goal setting meeting. Student aims for high GPA. Advised on effective study habits and avoiding burnout.', '2025-07-28', '{"recommendations": ["Prioritize difficult subjects", "Schedule regular breaks"]}', FALSE),
(7, 'Discussed feedback from a lecturer regarding class participation. Encouraged student to engage more in discussions.', '2025-09-01', '{"recommendations": ["Prepare questions before class", "Actively listen and contribute"]}', FALSE),
(7, 'Reviewed mock exam results. Identified areas for improvement in Computer Organisation and Architecture. Recommended practice problems.', '2025-12-05', '{"recommendations": ["Solve past year questions", "Seek conceptual clarity from lecturer"]}', TRUE);

-- Notes for Student08 (advisor_student_id=8, assigned to advisor03)
INSERT INTO `advisor_notes` (`advisor_student_id`, `note_content`, `meeting_date`, `recommendations`, `follow_up_required`) VALUES
(8, 'Initial meeting. Student interested in game development. Advised on relevant programming languages and electives.', '2025-07-24', '{"recommendations": ["Learn C++ and Python", "Explore game design courses"]}', FALSE),
(8, 'Mid-semester review. Student performing well. Discussed opportunities to join a game development club or project.', '2025-10-30', '{"recommendations": ["Join game dev hackathons", "Collaborate on small game projects"]}', FALSE),
(8, 'End of year assessment. Student demonstrated strong technical skills. Recommended exploring a minor in digital media.', '2026-01-15', '{"recommendations": ["Review minor options in related fields", "Consider graphic design fundamentals"]}', FALSE);

-- Notes for Student09 (advisor_student_id=9, assigned to advisor03)
INSERT INTO `advisor_notes` (`advisor_student_id`, `note_content`, `meeting_date`, `recommendations`, `follow_up_required`) VALUES
(9, 'First meeting. Student concerned about workload balance. Provided tips on effective multi-tasking and delegation (if group work).', '2025-08-02', '{"recommendations": ["Use productivity tools", "Prioritize tasks based on deadlines"]}', TRUE),
(9, 'Meeting to address a remark request for Probability & Statistical Data Analysis. Advised on how to professionally appeal.', '2025-11-20', '{"recommendations": ["Gather evidence for remark", "Communicate clearly with lecturer"]}', FALSE),
(9, 'Reviewed overall academic standing. Student is on track. Discussed preparing for industrial training applications.', '2026-03-10', '{"recommendations": ["Update resume/CV", "Practice interview skills"]}', FALSE);

-- Notes for Student10 (advisor_student_id=10, assigned to advisor03)
INSERT INTO `advisor_notes` (`advisor_student_id`, `note_content`, `meeting_date`, `recommendations`, `follow_up_required`) VALUES
(10, 'Introductory discussion. Student has strong interest in networking and systems administration.', '2025-07-29', '{"recommendations": ["Focus on Network Communications and Operating Systems", "Get CompTIA certifications"]}', FALSE),
(10, 'Mid-term results check-in. Student performed below expectations in Digital Logic. Developed a study plan.', '2025-10-01', '{"recommendations": ["Review foundational circuit concepts", "Seek peer tutoring"]}', TRUE),
(10, 'Final semester review. Good recovery in performance. Discussed internship opportunities in IT infrastructure.', '2026-02-20', '{"recommendations": ["Apply for network admin internships", "Attend industry career fairs"]}', FALSE);

-- Enrollments for Students 1-4 (user_ids 1-4, mostly with Lecturer 11)
INSERT INTO `enrollments` (`student_id`, `course_id`, `enrollment_date`) VALUES
(1, 1, '2025-09-01'), -- Student01 in SECI1013 Discrete Structure (Lecturer 11)
(1, 2, '2025-09-01'), -- Student01 in SECJ1013 Programming Technique I (Lecturer 11)
(1, 9, '2025-09-01'), -- Student01 in SECD2523 Database (Lecturer 11)
(2, 1, '2025-09-01'), -- Student02 in SECI1013 Discrete Structure (Lecturer 11)
(2, 5, '2025-09-01'), -- Student02 in SECI1113 Computational Mathematics (Lecturer 11)
(2, 11, '2025-09-01'), -- Student02 in SECJ2013 Data Structure and Algorithm (Lecturer 11)
(3, 2, '2025-09-01'), -- Student03 in SECJ1013 Programming Technique I (Lecturer 11)
(3, 7, '2025-09-01'), -- Student03 in SECJ1023 Programming Technique II (Lecturer 11)
(3, 14, '2025-09-01'), -- Student03 in SECJ2203 Software Engineering (Lecturer 11)
(4, 1, '2025-09-01'), -- Student04 in SECI1013 Discrete Structure (Lecturer 11)
(4, 9, '2025-09-01'), -- Student04 in SECD2523 Database (Lecturer 11)
(4, 17, '2025-09-01'); -- Student04 in SECJ2154 Object Oriented Programming (Lecturer 11)

-- Enrollments for Students 5-7 (user_ids 5-7, mostly with Lecturer 12)
INSERT INTO `enrollments` (`student_id`, `course_id`, `enrollment_date`) VALUES
(5, 4, '2025-09-01'), -- Student05 in SECP1513 Technology & Information System (Lecturer 12)
(5, 6, '2025-09-01'), -- Student05 in SECI1143 Probability & Statistical Data Analysis (Lecturer 12)
(5, 10, '2025-09-01'), -- Student05 in SECD2613 System Analysis and Design (Lecturer 12)
(6, 3, '2025-09-01'), -- Student06 in SECR1013 Digital Logic (Lecturer 12)
(6, 13, '2025-09-01'), -- Student06 in SECV2113 Human Computer Interaction (Lecturer 12)
(6, 15, '2025-09-01'), -- Student06 in SECV2223 Web Programming (Lecturer 12)
(7, 4, '2025-09-01'), -- Student07 in SECP1513 Technology & Information System (Lecturer 12)
(7, 6, '2025-09-01'), -- Student07 in SECI1143 Probability & Statistical Data Analysis (Lecturer 12)
(7, 10, '2025-09-01'); -- Student07 in SECD2613 System Analysis and Design (Lecturer 12)

-- Enrollments for Students 8-10 (user_ids 8-10, mostly with Lecturer 13)
INSERT INTO `enrollments` (`student_id`, `course_id`, `enrollment_date`) VALUES
(8, 3, '2025-09-01'), -- Student08 in SECR1013 Digital Logic (Taught by L12, but marking for this student cluster by L13)
(8, 8, '2025-09-01'), -- Student08 in SECR1033 Computer Organisation and Architecture (Lecturer 13)
(8, 12, '2025-09-01'), -- Student08 in SECR2213 Network Communications (Lecturer 13)
(9, 8, '2025-09-01'), -- Student09 in SECR1033 Computer Organisation and Architecture (Lecturer 13)
(9, 16, '2025-09-01'), -- Student09 in SECR2043 Operating Systems (Lecturer 13)
(9, 20, '2025-09-01'), -- Student09 in SECJ4118 Industrial Training (Lecturer 13)
(10, 12, '2025-09-01'), -- Student10 in SECR2213 Network Communications (Lecturer 13)
(10, 16, '2025-09-01'), -- Student10 in SECR2043 Operating Systems (Lecturer 13)
(10, 21, '2025-09-01'); -- Student10 in SECJ4114 Industrial Training Report (Lecturer 13)


-- Student Marks for Student01 (user_id=1)
-- Enrollment IDs: 1 (SECI1013), 2 (SECJ1013), 3 (SECD2523)
-- Recorded by Lecturer 11 (Dr. Alex Wong)
INSERT INTO `student_marks` (`enrollment_id`, `component_id`, `mark_obtained`, `recorded_by`) VALUES
(1, 1, FLOOR(60 + RAND() * 36), 11), -- SECI1013 Final Exam
(1, 2, FLOOR(60 + RAND() * 36), 11), -- SECI1013 Midterm Exam
(1, 3, FLOOR(60 + RAND() * 36), 11), -- SECI1013 Quiz 1
(1, 4, FLOOR(60 + RAND() * 36), 11), -- SECI1013 Assignment 1
(2, 5, FLOOR(60 + RAND() * 36), 11), -- SECJ1013 Final Exam
(2, 6, FLOOR(60 + RAND() * 36), 11), -- SECJ1013 Midterm Exam
(2, 7, FLOOR(60 + RAND() * 36), 11), -- SECJ1013 Lab Assignments
(2, 8, FLOOR(60 + RAND() * 36), 11), -- SECJ1013 Quizzes
(3, 29, FLOOR(60 + RAND() * 36), 11), -- SECD2523 Final Exam
(3, 30, FLOOR(60 + RAND() * 36), 11), -- SECD2523 Project
(3, 31, FLOOR(60 + RAND() * 36), 11), -- SECD2523 Quizzes
(3, 32, FLOOR(60 + RAND() * 36), 11); -- SECD2523 Assignments

-- Student Marks for Student02 (user_id=2)
-- Enrollment IDs: 4 (SECI1013), 5 (SECI1113), 6 (SECJ2013)
-- Recorded by Lecturer 11 (Dr. Alex Wong)
INSERT INTO `student_marks` (`enrollment_id`, `component_id`, `mark_obtained`, `recorded_by`) VALUES
(4, 1, FLOOR(60 + RAND() * 36), 11), -- SECI1013 Final Exam
(4, 2, FLOOR(60 + RAND() * 36), 11), -- SECI1013 Midterm Exam
(4, 3, FLOOR(60 + RAND() * 36), 11), -- SECI1013 Quiz 1
(4, 4, FLOOR(60 + RAND() * 36), 11), -- SECI1013 Assignment 1
(5, 17, FLOOR(60 + RAND() * 36), 11), -- SECI1113 Final Exam
(5, 18, FLOOR(60 + RAND() * 36), 11), -- SECI1113 Midterm Exam
(5, 19, FLOOR(60 + RAND() * 36), 11), -- SECI1113 Homework
(6, 41, FLOOR(60 + RAND() * 36), 11), -- SECJ2013 Final Exam
(6, 42, FLOOR(60 + RAND() * 36), 11), -- SECJ2013 Midterm Exam
(6, 43, FLOOR(60 + RAND() * 36), 11); -- SECJ2013 Programming Assignments

-- Student Marks for Student03 (user_id=3)
-- Enrollment IDs: 7 (SECJ1013), 8 (SECJ1023), 9 (SECJ2203)
-- Recorded by Lecturer 11 (Dr. Alex Wong)
INSERT INTO `student_marks` (`enrollment_id`, `component_id`, `mark_obtained`, `recorded_by`) VALUES
(7, 5, FLOOR(60 + RAND() * 36), 11), -- SECJ1013 Final Exam
(7, 6, FLOOR(60 + RAND() * 36), 11), -- SECJ1013 Midterm Exam
(7, 7, FLOOR(60 + RAND() * 36), 11), -- SECJ1013 Lab Assignments
(7, 8, FLOOR(60 + RAND() * 36), 11), -- SECJ1013 Quizzes
(8, 25, FLOOR(60 + RAND() * 36), 11), -- SECJ1023 Final Exam
(8, 26, FLOOR(60 + RAND() * 36), 11), -- SECJ1023 Practical Exam
(8, 27, FLOOR(60 + RAND() * 36), 11), -- SECJ1023 Project
(8, 28, FLOOR(60 + RAND() * 36), 11), -- SECJ1023 Quizzes
(9, 51, FLOOR(60 + RAND() * 36), 11), -- SECJ2203 Final Exam
(9, 52, FLOOR(60 + RAND() * 36), 11), -- SECJ2203 Team Project
(9, 53, FLOOR(60 + RAND() * 36), 11); -- SECJ2203 Quizzes

-- Student Marks for Student04 (user_id=4)
-- Enrollment IDs: 10 (SECI1013), 11 (SECD2523), 12 (SECJ2154)
-- Recorded by Lecturer 11 (Dr. Alex Wong)
INSERT INTO `student_marks` (`enrollment_id`, `component_id`, `mark_obtained`, `recorded_by`) VALUES
(10, 1, FLOOR(60 + RAND() * 36), 11), -- SECI1013 Final Exam
(10, 2, FLOOR(60 + RAND() * 36), 11), -- SECI1013 Midterm Exam
(10, 3, FLOOR(60 + RAND() * 36), 11), -- SECI1013 Quiz 1
(10, 4, FLOOR(60 + RAND() * 36), 11), -- SECI1013 Assignment 1
(11, 29, FLOOR(60 + RAND() * 36), 11), -- SECD2523 Final Exam
(11, 30, FLOOR(60 + RAND() * 36), 11), -- SECD2523 Project
(11, 31, FLOOR(60 + RAND() * 36), 11), -- SECD2523 Quizzes
(11, 32, FLOOR(60 + RAND() * 36), 11), -- SECD2523 Assignments
(12, 60, FLOOR(60 + RAND() * 36), 11), -- SECJ2154 Final Exam
(12, 61, FLOOR(60 + RAND() * 36), 11), -- SECJ2154 Programming Project
(12, 62, FLOOR(60 + RAND() * 36), 11), -- SECJ2154 Midterm Exam
(12, 63, FLOOR(60 + RAND() * 36), 11); -- SECJ2154 Quizzes

-- Student Marks for Student05 (user_id=5)
-- Enrollment IDs: 13 (SECP1513), 14 (SECI1143), 15 (SECD2613)
-- Recorded by Lecturer 12 (Prof. Sarah Tan)
INSERT INTO `student_marks` (`enrollment_id`, `component_id`, `mark_obtained`, `recorded_by`) VALUES
(13, 13, FLOOR(60 + RAND() * 36), 12), -- SECP1513 Final Exam
(13, 14, FLOOR(60 + RAND() * 36), 12), -- SECP1513 Midterm Exam
(13, 15, FLOOR(60 + RAND() * 36), 12), -- SECP1513 Presentation
(13, 16, FLOOR(60 + RAND() * 36), 12), -- SECP1513 Participation
(14, 20, FLOOR(60 + RAND() * 36), 12), -- SECI1143 Final Exam
(14, 21, FLOOR(60 + RAND() * 36), 12), -- SECI1143 Assignments
(14, 22, FLOOR(60 + RAND() * 36), 12), -- SECI1143 Quizzes
(15, 33, FLOOR(60 + RAND() * 36), 12), -- SECD2613 Final Exam
(15, 34, FLOOR(60 + RAND() * 36), 12), -- SECD2613 Group Project
(15, 35, FLOOR(60 + RAND() * 36), 12); -- SECD2613 Case Study

-- Student Marks for Student06 (user_id=6)
-- Enrollment IDs: 16 (SECR1013), 17 (SECV2113), 18 (SECV2223)
-- Recorded by Lecturer 12 (Prof. Sarah Tan)
INSERT INTO `student_marks` (`enrollment_id`, `component_id`, `mark_obtained`, `recorded_by`) VALUES
(16, 9, FLOOR(60 + RAND() * 36), 12), -- SECR1013 Digital Logic Final Exam
(16, 10, FLOOR(60 + RAND() * 36), 12), -- SECR1013 Digital Logic Midterm Exam
(16, 11, FLOOR(60 + RAND() * 36), 12), -- SECR1013 Digital Logic Project
(17, 48, FLOOR(60 + RAND() * 36), 12), -- SECV2113 Human Computer Interaction Final Exam
(17, 49, FLOOR(60 + RAND() * 36), 12), -- SECV2113 Human Computer Interaction Usability Study
(17, 50, FLOOR(60 + RAND() * 36), 12), -- SECV2113 Human Computer Interaction Design Project
(18, 54, FLOOR(60 + RAND() * 36), 12), -- SECV2223 Web Programming Final Project
(18, 55, FLOOR(60 + RAND() * 36), 12), -- SECV2223 Web Programming Midterm Exam
(18, 56, FLOOR(60 + RAND() * 36), 12), -- SECV2223 Web Programming Assignments
(18, 57, FLOOR(60 + RAND() * 36), 12); -- SECV2223 Web Programming Quizzes

-- Student Marks for Student07 (user_id=7)
-- Enrollment IDs: 19 (SECP1513), 20 (SECI1143), 21 (SECD2613)
-- Recorded by Lecturer 12 (Prof. Sarah Tan)
INSERT INTO `student_marks` (`enrollment_id`, `component_id`, `mark_obtained`, `recorded_by`) VALUES
(19, 13, FLOOR(60 + RAND() * 36), 12), -- SECP1513 Final Exam
(19, 14, FLOOR(60 + RAND() * 36), 12), -- SECP1513 Midterm Exam
(19, 15, FLOOR(60 + RAND() * 36), 12), -- SECP1513 Presentation
(19, 16, FLOOR(60 + RAND() * 36), 12), -- SECP1513 Participation
(20, 20, FLOOR(60 + RAND() * 36), 12), -- SECI1143 Final Exam
(20, 21, FLOOR(60 + RAND() * 36), 12), -- SECI1143 Assignments
(20, 22, FLOOR(60 + RAND() * 36), 12), -- SECI1143 Quizzes
(21, 33, FLOOR(60 + RAND() * 36), 12), -- SECD2613 Final Exam
(21, 34, FLOOR(60 + RAND() * 36), 12), -- SECD2613 Group Project
(21, 35, FLOOR(60 + RAND() * 36), 12); -- SECD2613 Case Study

-- Student Marks for Student08 (user_id=8)
-- Enrollment IDs: 22 (SECR1013), 23 (SECR1033), 24 (SECR2213)
-- Recorded by Lecturer 13 (Dr. Ben Lim)
INSERT INTO `student_marks` (`enrollment_id`, `component_id`, `mark_obtained`, `recorded_by`) VALUES
(22, 9, FLOOR(60 + RAND() * 36), 13), -- SECR1013 Digital Logic Final Exam
(22, 10, FLOOR(60 + RAND() * 36), 13), -- SECR1013 Digital Logic Midterm Exam
(22, 11, FLOOR(60 + RAND() * 36), 13), -- SECR1013 Digital Logic Project
(23, 29, FLOOR(60 + RAND() * 36), 13), -- SECR1033 Computer Organisation and Architecture Final Exam
(23, 30, FLOOR(60 + RAND() * 36), 13), -- SECR1033 Computer Organisation and Architecture Midterm Exam
(23, 31, FLOOR(60 + RAND() * 36), 13), -- SECR1033 Computer Organisation and Architecture Lab Work
(24, 44, FLOOR(60 + RAND() * 36), 13), -- SECR2213 Network Communications Final Exam
(24, 45, FLOOR(60 + RAND() * 36), 13), -- SECR2213 Network Communications Lab Exercises
(24, 46, FLOOR(60 + RAND() * 36), 13); -- SECR2213 Network Communications Quizzes

-- Student Marks for Student09 (user_id=9)
-- Enrollment IDs: 25 (SECR1033), 26 (SECR2043), 27 (SECJ4118)
-- Recorded by Lecturer 13 (Dr. Ben Lim)
INSERT INTO `student_marks` (`enrollment_id`, `component_id`, `mark_obtained`, `recorded_by`) VALUES
(25, 29, FLOOR(60 + RAND() * 36), 13), -- SECR1033 Final Exam
(25, 30, FLOOR(60 + RAND() * 36), 13), -- SECR1033 Midterm Exam
(25, 31, FLOOR(60 + RAND() * 36), 13), -- SECR1033 Lab Work
(26, 58, FLOOR(60 + RAND() * 36), 13), -- SECR2043 Operating Systems Final Exam
(26, 59, FLOOR(60 + RAND() * 36), 13), -- SECR2043 Operating Systems Assignments
(26, 60, FLOOR(60 + RAND() * 36), 13), -- SECR2043 Operating Systems Lab Exercises
(27, 68, FLOOR(60 + RAND() * 36), 13), -- SECJ4118 Industrial Training Internship Performance Evaluation
(27, 69, FLOOR(60 + RAND() * 36), 13); -- SECJ4118 Industrial Training Logbook Submission

-- Student Marks for Student10 (user_id=10)
-- Enrollment IDs: 28 (SECR2213), 29 (SECR2043), 30 (SECJ4114)
-- Recorded by Lecturer 13 (Dr. Ben Lim)
INSERT INTO `student_marks` (`enrollment_id`, `component_id`, `mark_obtained`, `recorded_by`) VALUES
(28, 44, FLOOR(60 + RAND() * 36), 13), -- SECR2213 Network Communications Final Exam
(28, 45, FLOOR(60 + RAND() * 36), 13), -- SECR2213 Network Communications Lab Exercises
(28, 46, FLOOR(60 + RAND() * 36), 13), -- SECR2213 Network Communications Quizzes
(29, 58, FLOOR(60 + RAND() * 36), 13), -- SECR2043 Operating Systems Final Exam
(29, 59, FLOOR(60 + RAND() * 36), 13), -- SECR2043 Operating Systems Assignments
(29, 60, FLOOR(60 + RAND() * 36), 13), -- SECR2043 Operating Systems Lab Exercises
(30, 70, FLOOR(60 + RAND() * 36), 13); -- SECJ4114 Industrial Training Report Report Submission

INSERT INTO `remark_requests` (`mark_id`, `student_id`, `justification`, `request_date`, `status`, `lecturer_notes`, `resolved_by`, `resolved_at`) VALUES
-- Request 1: Student01 for SECI1013 Midterm Exam (enrollment_id=1, component_id=2)
-- Assuming mark_id is 2 for this (check your auto-incremented IDs if different)
(2, 1, 'I believe there might be a calculation error in my Midterm Exam script for SECI1013. I reviewed my answers and feel my score is lower than expected.', '2026-01-25 10:00:00', 'pending', NULL, NULL, NULL),

-- Request 2: Student02 for SECJ2013 Programming Assignments (enrollment_id=6, component_id=43)
-- Assuming mark_id is 18 for this (check your auto-incremented IDs if different)
(18, 2, 'I would like to request a remark for my Programming Assignments in SECJ2013. I put in significant effort and believe my implementation was robust.', '2026-01-26 11:30:00', 'approved', 'Upon review, a minor part of the code logic was re-evaluated, resulting in a slight increase in marks.', 11, '2026-02-05 14:00:00'),

-- Request 3: Student03 for SECJ1023 Practical Exam (enrollment_id=8, component_id=26)
-- Assuming mark_id is 26 for this (check your auto-incremented IDs if different)
(26, 3, 'I feel my practical exam for SECJ1023 was graded too harshly. I encountered technical issues during the exam that might have affected my submission.', '2026-01-27 09:15:00', 'rejected', 'No evidence of technical issues found. Rubric applied consistently. Mark stands.', 11, '2026-02-08 10:30:00'),

-- Request 4: Student05 for SECP1513 Participation (enrollment_id=13, component_id=16)
-- Assuming mark_id is 32 for this (check your auto-incremented IDs if different)
(32, 5, 'I actively participated in class discussions and group activities for SECP1513. I would like my participation grade to be reviewed.', '2026-01-28 14:00:00', 'pending', NULL, NULL, NULL),

-- Request 5: Student06 for SECV2223 Final Project (enrollment_id=18, component_id=54)
-- Assuming mark_id is 47 for this (check your auto-incremented IDs if different)
(47, 6, 'There seems to be a discrepancy in the marking of my final project for SECV2223. I believe certain features were overlooked.', '2026-01-29 16:45:00', 'approved', 'Re-evaluation of specific feature implementation led to an adjustment in the mark.', 12, '2026-02-12 11:00:00'),

-- Request 6: Student08 for SECR1013 Digital Logic Project (enrollment_id=22, component_id=11)
-- Assuming mark_id is 50 for this (check your auto-incremented IDs if different)
(50, 8, 'I am requesting a remark for my Digital Logic project. I followed all specifications and tested my circuit thoroughly.', '2026-02-01 08:30:00', 'pending', NULL, NULL, NULL),

-- Request 7: Student09 for SECJ4118 Internship Performance Evaluation (enrollment_id=27, component_id=68)
-- Assuming mark_id is 66 for this (check your auto-incremented IDs if different)
(66, 9, 'I believe my internship performance evaluation mark does not accurately reflect my contributions and efforts during the industrial training period. I have documentation to support my claim.', '2026-02-02 10:00:00', 'rejected', 'The evaluation was based on supervisor feedback and logbook entries. No grounds for remark found.', 13, '2026-02-15 17:00:00');