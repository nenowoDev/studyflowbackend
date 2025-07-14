<?php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use PDO;
use PDOException;

class EnrollmentController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get all enrollments.
     * Accessible to admin; students can only view their own enrollments.
     * Lecturers can view enrollments for their courses.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @return Response The response object with enrollment data or error.
     */
    public function getAllEnrollments(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        $query = "SELECT e.*, u.full_name AS student_name, c.course_name, c.course_code 
                  FROM enrollments e
                  JOIN users u ON e.student_id = u.user_id
                  JOIN courses c ON e.course_id = c.course_id";
        $params = [];

        if ($userRole === 'student') {
            $query .= " WHERE e.student_id = ?";
            $params[] = $userId;
        } elseif ($userRole === 'lecturer') {
            // Lecturers can see enrollments for courses they teach
            $query .= " WHERE c.lecturer_id = ?";
            $params[] = $userId;
        } elseif ($userRole !== 'admin') {
            // Deny access for other roles (e.g., advisor) unless explicitly allowed
            $response->getBody()->write(json_encode(['error' => 'Access denied for this role.']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode($enrollments));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error fetching all enrollments: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve enrollments.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Get a single enrollment by ID.
     * Accessible to admin; students can only view their own enrollments; lecturers can view for their courses.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., enrollment ID).
     * @return Response The response object with enrollment data or error.
     */
    public function getEnrollmentById(Request $request, Response $response, array $args): Response
    {
        $enrollmentId = $args['id'];
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        if (!is_numeric($enrollmentId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid enrollment ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $query = "SELECT e.*, u.full_name AS student_name, c.course_name, c.course_code, c.lecturer_id 
                  FROM enrollments e
                  JOIN users u ON e.student_id = u.user_id
                  JOIN courses c ON e.course_id = c.course_id
                  WHERE e.enrollment_id = ?";
        $params = [$enrollmentId];

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $enrollment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$enrollment) {
                $response->getBody()->write(json_encode(['error' => 'Enrollment not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Authorization check
            if ($userRole === 'student' && (string)$enrollment['student_id'] !== (string)$userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only view your own enrollments.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole === 'lecturer' && (string)$enrollment['lecturer_id'] !== (string)$userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only view enrollments for your courses.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole !== 'admin' && $userRole !== 'student' && $userRole !== 'lecturer') {
                $response->getBody()->write(json_encode(['error' => 'Access denied for this role.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }


            $response->getBody()->write(json_encode($enrollment));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error fetching enrollment by ID {$enrollmentId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve enrollment.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Add a new enrollment.
     * Accessible to admin only.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @return Response The response object with success message or error.
     */
    public function addEnrollment(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');

        if (!isset($jwt->role) || $jwt->role !== 'admin') {
            $response->getBody()->write(json_encode(['error' => 'Access denied: admin only']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        $data = json_decode($request->getBody()->getContents(), true);

        // Basic validation for required fields
        if (empty($data['student_id']) || empty($data['course_id']) || empty($data['enrollment_date'])) {
            $response->getBody()->write(json_encode(['error' => 'Student ID, Course ID, and enrollment date are required.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Validate student_id and course_id exist and are valid types
        try {
            $stmtStudent = $this->pdo->prepare("SELECT user_id, role FROM users WHERE user_id = ? AND role = 'student'");
            $stmtStudent->execute([$data['student_id']]);
            if (!$stmtStudent->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Invalid student ID or user is not a student.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $stmtCourse = $this->pdo->prepare("SELECT course_id FROM courses WHERE course_id = ?");
            $stmtCourse->execute([$data['course_id']]);
            if (!$stmtCourse->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Invalid course ID.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        } catch (PDOException $e) {
            error_log("Error validating student/course ID for enrollment: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error during ID validation.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO enrollments (student_id, course_id, enrollment_date) VALUES (?, ?, ?)");
            $stmt->execute([
                $data['student_id'],
                $data['course_id'],
                $data['enrollment_date']
            ]);

            $response->getBody()->write(json_encode(['message' => 'Enrollment added successfully', 'enrollment_id' => $this->pdo->lastInsertId()]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // SQLSTATE for Integrity Constraint Violation (e.g., unique student_id, course_id)
                $errorMessage = 'This student is already enrolled in this course.';
            } else {
                $errorMessage = 'Database error: Could not add enrollment.';
            }
            error_log("Error adding enrollment: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $errorMessage]));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Update an existing enrollment.
     * Accessible to admin only.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., enrollment ID).
     * @return Response The response object with success message or error.
     */
    public function updateEnrollment(Request $request, Response $response, array $args): Response
    {
        $enrollmentId = $args['id'];
        $jwt = $request->getAttribute('jwt');

        if (!isset($jwt->role) || $jwt->role !== 'admin') {
            $response->getBody()->write(json_encode(['error' => 'Access denied: admin only']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        if (!is_numeric($enrollmentId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid enrollment ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $data = json_decode($request->getBody()->getContents(), true);

        if (empty($data)) {
            $response->getBody()->write(json_encode(['error' => 'No data provided for update.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $setClauses = [];
        $params = [];

        // Validate and add fields to update
        if (isset($data['student_id'])) {
            $stmtStudent = $this->pdo->prepare("SELECT user_id, role FROM users WHERE user_id = ? AND role = 'student'");
            $stmtStudent->execute([$data['student_id']]);
            if (!$stmtStudent->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Invalid student ID or user is not a student.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            $setClauses[] = 'student_id = ?';
            $params[] = $data['student_id'];
        }
        if (isset($data['course_id'])) {
            $stmtCourse = $this->pdo->prepare("SELECT course_id FROM courses WHERE course_id = ?");
            $stmtCourse->execute([$data['course_id']]);
            if (!$stmtCourse->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Invalid course ID.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            $setClauses[] = 'course_id = ?';
            $params[] = $data['course_id'];
        }
        if (isset($data['enrollment_date'])) {
            $setClauses[] = 'enrollment_date = ?';
            $params[] = $data['enrollment_date'];
        }

        if (empty($setClauses)) {
            $response->getBody()->write(json_encode(['error' => 'No valid fields provided for update.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $params[] = $enrollmentId; // Add the enrollment ID for the WHERE clause

        $query = "UPDATE enrollments SET " . implode(', ', $setClauses) . " WHERE enrollment_id = ?";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Enrollment not found or no changes made.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Enrollment updated successfully']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $errorMessage = 'This student is already enrolled in this course.';
            } else {
                $errorMessage = 'Database error: Could not update enrollment.';
            }
            error_log("Error updating enrollment ID {$enrollmentId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $errorMessage]));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Delete an enrollment.
     * Accessible to admin only.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., enrollment ID).
     * @return Response The response object with success message or error.
     */
    public function deleteEnrollment(Request $request, Response $response, array $args): Response
    {
        $enrollmentId = $args['id'];
        $jwt = $request->getAttribute('jwt');

        if (!isset($jwt->role) || $jwt->role !== 'admin') {
            $response->getBody()->write(json_encode(['error' => 'Access denied: admin only']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        if (!is_numeric($enrollmentId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid enrollment ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $stmt = $this->pdo->prepare("DELETE FROM enrollments WHERE enrollment_id = ?");
            $stmt->execute([$enrollmentId]);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Enrollment not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Enrollment deleted successfully']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error deleting enrollment ID {$enrollmentId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not delete enrollment.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    } 
   /**
     * Fetches students who are not currently enrolled in a specific course.
     * Accessible by lecturer role.
     * Endpoint: GET /enrollments/{id}/eligible-students
     */
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
                $stmt->execute([$studentId, $courseId]);
                $addedCount++;
                $messages[] = "Student ID {$studentId} successfully enrolled.";

            } catch (PDOException $e) {
                // Log the specific error for this student
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
}
