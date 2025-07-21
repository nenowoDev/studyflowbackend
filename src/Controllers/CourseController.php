<?php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use PDO;
use PDOException;

class CourseController
{
    private PDO $pdo;
    private NotificationController $notificationController;

    public function __construct(PDO $pdo, NotificationController $notificationController)
    {
        $this->pdo = $pdo;
        $this->notificationController = $notificationController;
    }

    /**
     * Get all courses.
     * Accessible to all authenticated users.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @return Response The response object with course data or error.
     */
    public function getAllCourses(Request $request, Response $response): Response
    {
        try {
            // Join with users table to get lecturer's full name
            // The "c.*" wildcard automatically includes the new credit_hours column. No change needed here.
            $stmt = $this->pdo->query("SELECT c.*, u.full_name AS lecturer_name FROM courses c JOIN users u ON c.lecturer_id = u.user_id");
            $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode($courses));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error fetching all courses: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve courses.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Get a single course by ID.
     * Accessible to all authenticated users.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., course ID).
     * @return Response The response object with course data or error.
     */
    public function getCourseById(Request $request, Response $response, array $args): Response
    {
        $courseId = $args['id'];

        if (!is_numeric($courseId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid course ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            // The "c.*" wildcard automatically includes the new credit_hours column. No change needed here.
            $stmt = $this->pdo->prepare("SELECT c.*, u.full_name AS lecturer_name FROM courses c JOIN users u ON c.lecturer_id = u.user_id WHERE c.course_id = ?");
            $stmt->execute([$courseId]);
            $course = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$course) {
                $response->getBody()->write(json_encode(['error' => 'Course not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode($course));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error fetching course by ID {$courseId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve course.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Add a new course.
     * Accessible to admin and lecturers. Lecturers can only assign themselves.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @return Response The response object with success message or error.
     */
    public function addCourse(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id; // User ID from the JWT token
        $userRole = $jwt->role; // User role from the JWT token

        $data = json_decode($request->getBody()->getContents(), true);

        // --- CHANGE: Add validation for the new credit_hours column ---
        if (empty($data['course_code']) || empty($data['course_name']) || empty($data['lecturer_id']) || !isset($data['credit_hours'])) {
            $response->getBody()->write(json_encode(['error' => 'Course code, name, lecturer ID, and credit hours are required.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Authorization check
        // Admin can assign any lecturer. Lecturers can only assign themselves.
        if ($userRole === 'lecturer' && (string) $data['lecturer_id'] !== (string) $userId) {
            $response->getBody()->write(json_encode(['error' => 'Access denied: Lecturers can only assign courses to themselves.']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        } elseif ($userRole !== 'admin' && $userRole !== 'lecturer') {
            $response->getBody()->write(json_encode(['error' => 'Access denied: Only admins and lecturers can add courses.']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        // Validate if lecturer_id exists and is a lecturer role, and fetch full_name
        $lecturerFullName = '';
        try {
            $stmt = $this->pdo->prepare("SELECT user_id, role, full_name FROM users WHERE user_id = ? AND role = 'lecturer'");
            $stmt->execute([$data['lecturer_id']]);
            $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lecturer) {
                $response->getBody()->write(json_encode(['error' => 'Invalid lecturer ID or user is not a lecturer.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            $lecturerFullName = $lecturer['full_name'];
        } catch (PDOException $e) {
            error_log("Error validating lecturer ID: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error during lecturer validation.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        try {
            // --- CHANGE: Add credit_hours to the INSERT statement and parameters ---
            $stmt = $this->pdo->prepare("INSERT INTO courses (course_code, course_name, lecturer_id, credit_hours) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $data['course_code'],
                $data['course_name'],
                $data['lecturer_id'],
                $data['credit_hours'] // New value
            ]);

            $newCourseId = $this->pdo->lastInsertId();
            $courseName = $data['course_name'];
            $courseCode = $data['course_code'];

            $adminOrLecturerName = $jwt->user ?? 'An Administrator/Lecturer'; // Name of the user who added the course

            // 1. Notify the assigned Lecturer
            $this->notificationController->createNotification(
                $data['lecturer_id'],
                "New Course Assignment!",
                "{$adminOrLecturerName} has assigned you to teach the new course: **{$courseName} ({$courseCode})**.",
                "New Course",
                $newCourseId
            );

            // 2. Notify ALL Students about the new course
            $this->notificationController->notifyRoles(
                ['student'],
                "New Course Available: {$courseCode} - {$courseName}",
                "A new course, **{$courseName} ({$courseCode})**, taught by {$lecturerFullName}, is now available for enrollment!",
                "New Course",
                $newCourseId
            );

            $response->getBody()->write(json_encode(['message' => 'Course added successfully and notifications sent.', 'course_id' => $newCourseId]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // SQLSTATE for Integrity Constraint Violation
                $errorMessage = 'A course with this course code already exists.';
            } else {
                $errorMessage = 'Database error: Could not add course.';
            }
            error_log("Error adding course: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $errorMessage]));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Update an existing course.
     * Accessible to admin and lecturers (lecturers can only update their own courses).
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., course ID).
     * @return Response The response object with success message or error.
     */
    public function updateCourse(Request $request, Response $response, array $args): Response
    {
        $courseId = $args['id'];
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        if (!is_numeric($courseId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid course ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $data = json_decode($request->getBody()->getContents(), true);

        if (empty($data)) {
            $response->getBody()->write(json_encode(['message' => 'No data provided for update.']));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        }

        // --- 1. Fetch CURRENT course details and lecturer full name ---
        $originalCourseCode = '';
        $originalCourseName = '';
        $originalLecturerId = null;
        $originalLecturerFullName = '';
        $originalCreditHours = null; // --- CHANGE: New variable for credit hours ---

        try {
            // --- CHANGE: Add credit_hours to the SELECT statement to fetch the current value ---
            $stmtCurrent = $this->pdo->prepare("
                SELECT 
                    c.course_code, 
                    c.course_name, 
                    c.lecturer_id,
                    c.credit_hours,
                    u.full_name AS lecturer_full_name
                FROM courses c
                JOIN users u ON c.lecturer_id = u.user_id
                WHERE c.course_id = ?
            ");
            $stmtCurrent->execute([$courseId]);
            $currentCourse = $stmtCurrent->fetch(PDO::FETCH_ASSOC);

            if (!$currentCourse) {
                $response->getBody()->write(json_encode(['error' => 'Course not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $originalCourseCode = $currentCourse['course_code'];
            $originalCourseName = $currentCourse['course_name'];
            $originalLecturerId = $currentCourse['lecturer_id'];
            $originalLecturerFullName = $currentCourse['lecturer_full_name'];
            $originalCreditHours = $currentCourse['credit_hours']; // --- CHANGE: Assign the fetched value ---

            if ($userRole === 'lecturer' && (string) $originalLecturerId !== (string) $userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only update courses you are assigned to.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole !== 'admin' && $userRole !== 'lecturer') {
                $response->getBody()->write(json_encode(['error' => 'Access denied: Only admins and lecturers can update courses.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

        } catch (PDOException $e) {
            error_log("Error fetching current course details for ID {$courseId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve current course details.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        $setClauses = [];
        $params = [];
        $newLecturerId = $originalLecturerId; 
        $newLecturerFullName = $originalLecturerFullName; 
        $newCourseCode = $originalCourseCode; 
        $newCourseName = $originalCourseName;
        $newCreditHours = $originalCreditHours; // --- CHANGE: New variable for the new value ---

        if (isset($data['course_code']) && $data['course_code'] !== $originalCourseCode) {
            $setClauses[] = 'course_code = ?';
            $params[] = $data['course_code'];
            $newCourseCode = $data['course_code'];
        }
        if (isset($data['course_name']) && $data['course_name'] !== $originalCourseName) {
            $setClauses[] = 'course_name = ?';
            $params[] = $data['course_name'];
            $newCourseName = $data['course_name'];
        }

        // --- CHANGE: Add logic to handle the new credit_hours column ---
        if (isset($data['credit_hours']) && $data['credit_hours'] !== $originalCreditHours) {
            $setClauses[] = 'credit_hours = ?';
            $params[] = $data['credit_hours'];
            $newCreditHours = $data['credit_hours'];
        }

        if (isset($data['lecturer_id']) && $data['lecturer_id'] !== $originalLecturerId) {

            $stmtNewLecturer = $this->pdo->prepare("SELECT user_id, role, full_name FROM users WHERE user_id = ? AND role = 'lecturer'");
            $stmtNewLecturer->execute([$data['lecturer_id']]);
            $newLecturer = $stmtNewLecturer->fetch(PDO::FETCH_ASSOC);

            if (!$newLecturer) {
                $response->getBody()->write(json_encode(['error' => 'Invalid new lecturer ID or user is not a lecturer.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            if ($userRole === 'lecturer' && (string) $data['lecturer_id'] !== (string) $userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: Lecturers cannot change course lecturer to someone else.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $newLecturerId = $newLecturer['user_id'];
            $newLecturerFullName = $newLecturer['full_name'];
            $setClauses[] = 'lecturer_id = ?';
            $params[] = $newLecturerId;
        }

        if (empty($setClauses)) {
            $response->getBody()->write(json_encode(['message' => 'No valid fields provided for update or no changes detected.']));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        }

        $params[] = $courseId; 
        $query = "UPDATE courses SET " . implode(', ', $setClauses) . " WHERE course_id = ?";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['message' => 'Course updated successfully (no changes applied as data was identical).']));
                return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
            }

            $adminOrLecturerName = $jwt->full_name ?? $jwt->username ?? 'An Administrator/Lecturer';

            $stmtEnrolledStudents = $this->pdo->prepare("SELECT student_id FROM enrollments WHERE course_id = ?");
            $stmtEnrolledStudents->execute([$courseId]);
            $enrolledStudentIds = $stmtEnrolledStudents->fetchAll(PDO::FETCH_COLUMN);

            if ($newCourseName !== $originalCourseName || $newCourseCode !== $originalCourseCode) {
                $message = "The course **{$originalCourseName} ({$originalCourseCode})** has been updated by {$adminOrLecturerName}. ";
                $message .= "It is now known as **{$newCourseName} ({$newCourseCode})**.";

                foreach ($enrolledStudentIds as $student_id) {
                    $this->notificationController->createNotification(
                        $student_id,
                        "Course Update: {$newCourseCode} - {$newCourseName}",
                        $message,
                        "course_update",
                        $courseId
                    );
                }

                if ((string) $originalLecturerId !== (string) $userId || $userRole === 'admin') {
                    $this->notificationController->createNotification(
                        $originalLecturerId,
                        "Your Course Updated: {$newCourseCode} - {$newCourseName}",
                        $message,
                        "course_update",
                        $courseId
                    );
                }
            }


            if ($newLecturerId !== $originalLecturerId) {
                $this->notificationController->createNotification(
                    $newLecturerId,
                    "New Course Assignment!",
                    "{$adminOrLecturerName} has assigned you to teach the course: **{$newCourseName} ({$newCourseCode})**.",
                    "course_lecturer_change",
                    $courseId
                );

                $this->notificationController->createNotification(
                    $originalLecturerId,
                    "Course Reassignment",
                    "{$adminOrLecturerName} has reassigned you from teaching **{$originalCourseName} ({$originalCourseCode})**. It is now taught by {$newLecturerFullName}.",
                    "course_lecturer_change",
                    $courseId
                );

                $studentLecturerChangeMessage = "The lecturer for **{$newCourseName} ({$newCourseCode})** has changed from {$originalLecturerFullName} to {$newLecturerFullName}.";
                foreach ($enrolledStudentIds as $student_id) {
                    $this->notificationController->createNotification(
                        $student_id,
                        "Lecturer Change: {$newCourseCode} - {$newCourseName}",
                        $studentLecturerChangeMessage,
                        "course_lecturer_change",
                        $courseId
                    );
                }
            }

            $response->getBody()->write(json_encode(['message' => 'Course updated successfully and notifications sent.']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $errorMessage = 'A course with this course code already exists.';
            } else {
                $errorMessage = 'Database error: Could not update course.';
            }
            error_log("Error updating course ID {$courseId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $errorMessage]));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Delete a course.
     * Accessible to admin only.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., course ID).
     * @return Response The response object with success message or error.
     */
    public function deleteCourse(Request $request, Response $response, array $args): Response
    {
        $courseId = $args['id'];
        $jwt = $request->getAttribute('jwt');

        if (!isset($jwt->role) || $jwt->role !== 'admin') {
            $response->getBody()->write(json_encode(['error' => 'Access denied: admin only']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        if (!is_numeric($courseId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid course ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $courseName = '';
        $courseCode = '';
        $lecturerId = null;
        $lecturerFullName = '';
        $enrolledStudentIds = [];

        try {
            $stmtCourse = $this->pdo->prepare("
                SELECT 
                    c.course_name, 
                    c.course_code, 
                    c.lecturer_id,
                    u.full_name AS lecturer_full_name
                FROM courses c
                JOIN users u ON c.lecturer_id = u.user_id
                WHERE c.course_id = ?
            ");
            $stmtCourse->execute([$courseId]);
            $courseDetails = $stmtCourse->fetch(PDO::FETCH_ASSOC);

            if (!$courseDetails) {
                $response->getBody()->write(json_encode(['error' => 'Course not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $courseName = $courseDetails['course_name'];
            $courseCode = $courseDetails['course_code'];
            $lecturerId = $courseDetails['lecturer_id'];
            $lecturerFullName = $courseDetails['lecturer_full_name'];

            $stmtEnrolledStudents = $this->pdo->prepare("SELECT student_id FROM enrollments WHERE course_id = ?");
            $stmtEnrolledStudents->execute([$courseId]);
            $enrolledStudentIds = $stmtEnrolledStudents->fetchAll(PDO::FETCH_COLUMN);

        } catch (PDOException $e) {
            error_log("Error fetching course details for deletion (ID: {$courseId}): " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve course details for deletion.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        try {
            $stmt = $this->pdo->prepare("DELETE FROM courses WHERE course_id = ?");
            $stmt->execute([$courseId]);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Course not found or already deleted.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $adminFullName = $jwt->full_name ?? $jwt->username ?? 'An Administrator';

            $this->notificationController->createNotification(
                $lecturerId,
                "Course Deleted: {$courseCode} - {$courseName}",
                "{$adminFullName} has deleted the course you were assigned to teach: **{$courseName} ({$courseCode})**.",
                "course_deleted",
                $courseId
            );

            $studentDeletionMessage = "The course **{$courseName} ({$courseCode})** has been deleted by {$adminFullName}. It is no longer available.";
            foreach ($enrolledStudentIds as $student_id) {
                $this->notificationController->createNotification(
                    $student_id,
                    "Course Deleted: {$courseCode} - {$courseName}",
                    $studentDeletionMessage,
                    "course_deleted",
                    $courseId
                );
            }

            $response->getBody()->write(json_encode(['message' => 'Course deleted successfully and notifications sent.']));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $errorMessage = 'Cannot delete course: It might have associated data (e.g., assessment components, enrollments) that prevent deletion. Please ensure all related data is handled first.';
            } else {
                $errorMessage = 'Database error: Could not delete course.';
            }
            error_log("Error deleting course ID {$courseId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $errorMessage]));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }
    }

    public function getEligibleStudents(Request $request, Response $response, array $args): Response
    {
        $courseId = $args['id'];
        $jwt = $request->getAttribute('jwt');
        $requesterRole = $jwt->role ?? null;
        $lecturerId = $jwt->user_id ?? null;

        if ($requesterRole !== 'lecturer' || !$lecturerId) {
            $response->getBody()->write(json_encode(['error' => 'Access denied: Lecturer role required.']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        try {
            // Verify that the lecturer actually teaches this course
            $stmtCourse = $this->pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_id = ? AND lecturer_id = ?");
            $stmtCourse->execute([$courseId, $lecturerId]);
            if ($stmtCourse->fetchColumn() == 0) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You do not teach this course.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            // Fetch students who are not currently enrolled in this course
            $stmt = $this->pdo->prepare("
                SELECT u.user_id, u.username, u.full_name, u.matric_number, u.email, u.profile_picture
                FROM users u
                WHERE u.role = 'student'
                AND u.user_id NOT IN (
                    SELECT e.student_id
                    FROM enrollments e
                    WHERE e.course_id = ?
                )
            ");
            $stmt->execute([$courseId]);
            $eligibleStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$eligibleStudents) {
                $eligibleStudents = [];
            }

            $response->getBody()->write(json_encode(['eligibleStudents' => $eligibleStudents]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (PDOException $e) {
            error_log("Error fetching eligible students for course {$courseId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to fetch eligible students.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Assigns one or more existing students to a specific course.
     * Accessible by lecturer role.
     * Endpoint: POST /courses/{id}/add-students
     * Payload: { student_ids: [1, 2, 3] }
     */
    public function addStudentsToCourse(Request $request, Response $response, array $args): Response
    {
        $courseId = $args['id'];
        $jwt = $request->getAttribute('jwt');
        $requesterRole = $jwt->role ?? null;
        $lecturerId = $jwt->user_id ?? null;

        if ($requesterRole !== 'lecturer' || !$lecturerId) {
            $response->getBody()->write(json_encode(['error' => 'Access denied: Lecturer role required.']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        // Verify that the lecturer actually teaches this course
        $stmtCourse = $this->pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_id = ? AND lecturer_id = ?");
        $stmtCourse->execute([$courseId, $lecturerId]);
        if ($stmtCourse->fetchColumn() == 0) {
            $response->getBody()->write(json_encode(['error' => 'Access denied: You do not teach this course.']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        $data = json_decode($request->getBody()->getContents(), true);

        if (empty($data['student_ids']) || !is_array($data['student_ids'])) {
            $response->getBody()->write(json_encode(['error' => 'student_ids array is required.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $addedCount = 0;
        $failedCount = 0;
        $messages = [];

        foreach ($data['student_ids'] as $studentId) {
            try {
                // Check if student is already enrolled to prevent duplicates
                $stmtCheck = $this->pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND course_id = ?");
                $stmtCheck->execute([$studentId, $courseId]);
                if ($stmtCheck->fetchColumn() > 0) {
                    $messages[] = "Student ID {$studentId} is already enrolled in course {$courseId}.";
                    $failedCount++;
                    continue;
                }

                $stmt = $this->pdo->prepare("INSERT INTO enrollments (student_id, course_id, enrollment_date) VALUES (?, ?, CURDATE())");
                if ($stmt->execute([$studentId, $courseId])) {
                    $addedCount++;
                    $messages[] = "Student ID {$studentId} successfully enrolled.";
                } else {
                    $messages[] = "Failed to enroll student ID {$studentId}."; // Generic error if execute fails
                    $failedCount++;
                }

            } catch (PDOException $e) {
                error_log("Error enrolling student {$studentId} in course {$courseId}: " . $e->getMessage());
                $messages[] = "Failed to enroll student ID {$studentId}: " . $e->getMessage();
                $failedCount++;
            }
        }

        $response->getBody()->write(json_encode([
            'message' => "Enrollment process completed. Added: {$addedCount}, Failed: {$failedCount}.",
            'details' => $messages
        ]));
        return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
    }

    public function getLecturerCourses(Request $request, Response $response, array $args): Response
    {
        $username = $args['username'];
        $jwt = $request->getAttribute('jwt');
        $requesterRole = $jwt->role ?? null;
        $lecturerId = $jwt->user_id ?? null;

        if ($requesterRole !== 'lecturer' || !$lecturerId) {
            $response->getBody()->write(json_encode(['error' => 'Access denied: Lecturer role required.']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        try {
            // Verify that the username in the URL matches the logged-in lecturer's username
            $stmtUser = $this->pdo->prepare("SELECT user_id FROM users WHERE username = ? AND role = 'lecturer'");
            $stmtUser->execute([$username]);
            $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

            if (!$user || $user['user_id'] !== $lecturerId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: Token mismatch or invalid lecturer.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }


// $this->notificationController->notifyRoles(
//                 ['student'],
//                 "New Course Available: {$courseCode} - {$courseName}",
//                 "A new course, **{$courseName} ({$courseCode})**, taught by {$lecturerFullName}, is now available for enrollment!",
//                 "New Course",
//                 $newCourseId
//             );

//             $response->getBody()->write(json_encode(['message' => 'Course added successfully and notifications sent.', 'course_id' => $newCourseId]));
//             return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
//         } catch (PDOException $e) {
//             if ($e->getCode() == '23000') { // SQLSTATE for Integrity Constraint Violation
//                 $errorMessage = 'A course with this course code already exists.';
//             } else {
//                 $errorMessage = 'Database error: Could not add course.';
//             }
//             error_log("Error adding course: " . $e->getMessage());
//             $response->getBody()->write(json_encode(['error' => $errorMessage]));
//             return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
//         }
//     }

//     /**
//      * Update an existing course.
//      * Accessible to admin and lecturers (lecturers can only update their own courses).
//      *
//      * @param Request $request The request object.
//      * @param Response $response The response object.
//      * @param array $args Route arguments (e.g., course ID).
//      * @return Response The response object with success message or error.
//      */
//     public function updateCourse(Request $request, Response $response, array $args): Response
//     {
//         $courseId = $args['id'];
//         $jwt = $request->getAttribute('jwt');
//         $userId = $jwt->user_id;
//         $userRole = $jwt->role;

//         if (!is_numeric($courseId)) {
//             $response->getBody()->write(json_encode(['error' => 'Invalid course ID']));
//             return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
//         }

//         $data = json_decode($request->getBody()->getContents(), true);

//         if (empty($data)) {
//             $response->getBody()->write(json_encode(['message' => 'No data provided for update.']));
//             return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
//         }

//         // --- 1. Fetch CURRENT course details and lecturer full name ---
//         $originalCourseCode = '';
//         $originalCourseName = '';
//         $originalLecturerId = null;
//         $originalLecturerFullName = '';
//         $originalCreditHours = null; // --- CHANGE: New variable for credit hours ---

//         try {
//             // --- CHANGE: Add credit_hours to the SELECT statement to fetch the current value ---
//             $stmtCurrent = $this->pdo->prepare("
//                 SELECT 
//                     c.course_code, 
//                     c.course_name, 
//                     c.lecturer_id,
//                     c.credit_hours,
//                     u.full_name AS lecturer_full_name
//                 FROM courses c
//                 JOIN users u ON c.lecturer_id = u.user_id
//                 WHERE c.course_id = ?
//             ");
//             $stmtCurrent->execute([$courseId]);
//             $currentCourse = $stmtCurrent->fetch(PDO::FETCH_ASSOC);

//             if (!$currentCourse) {
//                 $response->getBody()->write(json_encode(['error' => 'Course not found.']));
//                 return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
//             }

//             $originalCourseCode = $currentCourse['course_code'];
//             $originalCourseName = $currentCourse['course_name'];
//             $originalLecturerId = $currentCourse['lecturer_id'];
//             $originalLecturerFullName = $currentCourse['lecturer_full_name'];
//             $originalCreditHours = $currentCourse['credit_hours']; // --- CHANGE: Assign the fetched value ---

//             if ($userRole === 'lecturer' && (string) $originalLecturerId !== (string) $userId) {
//                 $response->getBody()->write(json_encode(['error' => 'Access denied: You can only update courses you are assigned to.']));
//                 return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
//             } elseif ($userRole !== 'admin' && $userRole !== 'lecturer') {
//                 $response->getBody()->write(json_encode(['error' => 'Access denied: Only admins and lecturers can update courses.']));
//                 return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
//             }

//         } catch (PDOException $e) {
//             error_log("Error fetching current course details for ID {$courseId}: " . $e->getMessage());
//             $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve current course details.']));
//             return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
//         }

//         $setClauses = [];
//         $params = [];
//         $newLecturerId = $originalLecturerId; 
//         $newLecturerFullName = $originalLecturerFullName; 
//         $newCourseCode = $originalCourseCode; 
//         $newCourseName = $originalCourseName;
//         $newCreditHours = $originalCreditHours; // --- CHANGE: New variable for the new value ---

//         if (isset($data['course_code']) && $data['course_code'] !== $originalCourseCode) {
//             $setClauses[] = 'course_code = ?';
//             $params[] = $data['course_code'];
//             $newCourseCode = $data['course_code'];
//         }
//         if (isset($data['course_name']) && $data['course_name'] !== $originalCourseName) {
//             $setClauses[] = 'course_name = ?';
//             $params[] = $data['course_name'];
//             $newCourseName = $data['course_name'];
//         }

//         // --- CHANGE: Add logic to handle the new credit_hours column ---
//         if (isset($data['credit_hours']) && $data['credit_hours'] !== $originalCreditHours) {
//             $setClauses[] = 'credit_hours = ?';
//             $params[] = $data['credit_hours'];
//             $newCreditHours = $data['credit_hours'];
//         }

//         if (isset($data['lecturer_id']) && $data['lecturer_id'] !== $originalLecturerId) {

//             $stmtNewLecturer = $this->pdo->prepare("SELECT user_id, role, full_name FROM users WHERE user_id = ? AND role = 'lecturer'");
//             $stmtNewLecturer->execute([$data['lecturer_id']]);
//             $newLecturer = $stmtNewLecturer->fetch(PDO::FETCH_ASSOC);

//             if (!$newLecturer) {
//                 $response->getBody()->write(json_encode(['error' => 'Invalid new lecturer ID or user is not a lecturer.']));
//                 return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
//             }

//             if ($userRole === 'lecturer' && (string) $data['lecturer_id'] !== (string) $userId) {
//                 $response->getBody()->write(json_encode(['error' => 'Access denied: Lecturers cannot change course lecturer to someone else.']));
//                 return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
//             }

//             $newLecturerId = $newLecturer['user_id'];
//             $newLecturerFullName = $newLecturer['full_name'];
//             $setClauses[] = 'lecturer_id = ?';
//             $params[] = $newLecturerId;
//         }

//         if (empty($setClauses)) {
//             $response->getBody()->write(json_encode(['message' => 'No valid fields provided for update or no changes detected.']));
//             return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
//         }

//         $params[] = $courseId; 
//         $query = "UPDATE courses SET " . implode(', ', $setClauses) . " WHERE course_id = ?";

//         try {
//             $stmt = $this->pdo->prepare($query);
//             $stmt->execute($params);

//             if ($stmt->rowCount() === 0) {
//                 $response->getBody()->write(json_encode(['message' => 'Course updated successfully (no changes applied as data was identical).']));
//                 return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
//             }

//             $adminOrLecturerName = $jwt->full_name ?? $jwt->username ?? 'An Administrator/Lecturer';

//             $stmtEnrolledStudents = $this->pdo->prepare("SELECT student_id FROM enrollments WHERE course_id = ?");
//             $stmtEnrolledStudents->execute([$courseId]);
//             $enrolledStudentIds = $stmtEnrolledStudents->fetchAll(PDO::FETCH_COLUMN);

//             if ($newCourseName !== $originalCourseName || $newCourseCode !== $originalCourseCode) {
//                 $message = "The course **{$originalCourseName} ({$originalCourseCode})** has been updated by {$adminOrLecturerName}. ";
//                 $message .= "It is now known as **{$newCourseName} ({$newCourseCode})**.";

//                 foreach ($enrolledStudentIds as $student_id) {
//                     $this->notificationController->createNotification(
//                         $student_id,
//                         "Course Update: {$newCourseCode} - {$newCourseName}",
//                         $message,
//                         "course_update",
//                         $courseId
//                     );
//                 }

//                 if ((string) $originalLecturerId !== (string) $userId || $userRole === 'admin') {
//                     $this->notificationController->createNotification(
//                         $originalLecturerId,
//                         "Your Course Updated: {$newCourseCode} - {$newCourseName}",
//                         $message,
//                         "course_update",
//                         $courseId
//                     );
//                 }
//             }


//             if ($newLecturerId !== $originalLecturerId) {
//                 $this->notificationController->createNotification(
//                     $newLecturerId,
//                     "New Course Assignment!",
//                     "{$adminOrLecturerName} has assigned you to teach the course: **{$newCourseName} ({$newCourseCode})**.",
//                     "course_lecturer_change",
//                     $courseId
//                 );

//                 $this->notificationController->createNotification(
//                     $originalLecturerId,
//                     "Course Reassignment",
//                     "{$adminOrLecturerName} has reassigned you from teaching **{$originalCourseName} ({$originalCourseCode})**. It is now taught by {$newLecturerFullName}.",
//                     "course_lecturer_change",
//                     $courseId
//                 );

//                 $studentLecturerChangeMessage = "The lecturer for **{$newCourseName} ({$newCourseCode})** has changed from {$originalLecturerFullName} to {$newLecturerFullName}.";
//                 foreach ($enrolledStudentIds as $student_id) {
//                     $this->notificationController->createNotification(
//                         $student_id,
//                         "Lecturer Change: {$newCourseCode} - {$newCourseName}",
//                         $studentLecturerChangeMessage,
//                         "course_lecturer_change",
//                         $courseId
//                     );
//                 }
//             }

//             $response->getBody()->write(json_encode(['message' => 'Course updated successfully and notifications sent.']));
//             return $response->withHeader('Content-Type', 'application/json');

//         } catch (PDOException $e) {
//             if ($e->getCode() == '23000') {
//                 $errorMessage = 'A course with this course code already exists.';
//             } else {
//                 $errorMessage = 'Database error: Could not update course.';
//             }
//             error_log("Error updating course ID {$courseId}: " . $e->getMessage());
//             $response->getBody()->write(json_encode(['error' => $errorMessage]));
//             return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
//         }
//     }



            // The "SELECT *" query will automatically fetch the new credit_hours column. No change needed here.
            $stmt = $this->pdo->prepare("SELECT * FROM courses WHERE lecturer_id = ?");
            $stmt->execute([$lecturerId]);
            $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$courses) {
                $courses = []; // Return empty array if no courses found
            }

            $response->getBody()->write(json_encode(['courses' => $courses]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (PDOException $e) {
            error_log("Error fetching courses for lecturer {$username}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to fetch lecturer courses.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}