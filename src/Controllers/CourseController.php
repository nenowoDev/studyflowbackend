<?php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use PDO;
use PDOException;

class CourseController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
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
            // Join with users table to get lecturer's full name
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

        // Basic validation for required fields
        if (empty($data['course_code']) || empty($data['course_name']) || empty($data['lecturer_id'])) {
            $response->getBody()->write(json_encode(['error' => 'Course code, name, and lecturer ID are required.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Authorization check
        // Admin can assign any lecturer. Lecturers can only assign themselves.
        if ($userRole === 'lecturer' && (string)$data['lecturer_id'] !== (string)$userId) {
            $response->getBody()->write(json_encode(['error' => 'Access denied: Lecturers can only assign courses to themselves.']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        } elseif ($userRole !== 'admin' && $userRole !== 'lecturer') {
            $response->getBody()->write(json_encode(['error' => 'Access denied: Only admins and lecturers can add courses.']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        // Validate if lecturer_id exists and is a lecturer role
        try {
            $stmt = $this->pdo->prepare("SELECT user_id, role FROM users WHERE user_id = ? AND role = 'lecturer'");
            $stmt->execute([$data['lecturer_id']]);
            $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$lecturer) {
                $response->getBody()->write(json_encode(['error' => 'Invalid lecturer ID or user is not a lecturer.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        } catch (PDOException $e) {
            error_log("Error validating lecturer ID: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error during lecturer validation.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO courses (course_code, course_name, lecturer_id) VALUES (?, ?, ?)");
            $stmt->execute([
                $data['course_code'],
                $data['course_name'],
                $data['lecturer_id']
            ]);

            $response->getBody()->write(json_encode(['message' => 'Course added successfully', 'course_id' => $this->pdo->lastInsertId()]));
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
            $response->getBody()->write(json_encode(['error' => 'No data provided for update.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            // First, fetch the course to check ownership for lecturers
            $stmt = $this->pdo->prepare("SELECT lecturer_id FROM courses WHERE course_id = ?");
            $stmt->execute([$courseId]);
            $course = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$course) {
                $response->getBody()->write(json_encode(['error' => 'Course not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Authorization check
            if ($userRole === 'lecturer' && (string)$course['lecturer_id'] !== (string)$userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only update courses you are assigned to.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole !== 'admin' && $userRole !== 'lecturer') {
                $response->getBody()->write(json_encode(['error' => 'Access denied: Only admins and lecturers can update courses.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $setClauses = [];
            $params = [];

            if (isset($data['course_code'])) {
                $setClauses[] = 'course_code = ?';
                $params[] = $data['course_code'];
            }
            if (isset($data['course_name'])) {
                $setClauses[] = 'course_name = ?';
                $params[] = $data['course_name'];
            }
            if (isset($data['lecturer_id'])) {
                // If lecturer_id is being updated, validate it
                $stmtLecturer = $this->pdo->prepare("SELECT user_id, role FROM users WHERE user_id = ? AND role = 'lecturer'");
                $stmtLecturer->execute([$data['lecturer_id']]);
                $newLecturer = $stmtLecturer->fetch(PDO::FETCH_ASSOC);

                if (!$newLecturer) {
                    $response->getBody()->write(json_encode(['error' => 'Invalid new lecturer ID or user is not a lecturer.']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
                // Admin can assign to any lecturer; lecturer can only re-assign to themselves (already covered by initial check)
                if ($userRole === 'lecturer' && (string)$data['lecturer_id'] !== (string)$userId) {
                     $response->getBody()->write(json_encode(['error' => 'Access denied: Lecturers cannot change course lecturer to someone else.']));
                     return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
                }

                $setClauses[] = 'lecturer_id = ?';
                $params[] = $data['lecturer_id'];
            }

            if (empty($setClauses)) {
                $response->getBody()->write(json_encode(['error' => 'No valid fields provided for update.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $params[] = $courseId; // Add the course ID for the WHERE clause

            $query = "UPDATE courses SET " . implode(', ', $setClauses) . " WHERE course_id = ?";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                // This could mean the ID wasn't found (already handled), or no changes were made (data was identical)
                $response->getBody()->write(json_encode(['message' => 'Course updated successfully (or no changes made).']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Course updated successfully']));
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

        try {
            $stmt = $this->pdo->prepare("DELETE FROM courses WHERE course_id = ?");
            $stmt->execute([$courseId]);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Course not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Course deleted successfully']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error deleting course ID {$courseId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not delete course.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
