<?php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use PDO;
use PDOException;

class AdvisorStudentController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get all advisor-student assignments.
     * Accessible to:
     * - Admin: All assignments.
     * - Advisor: Their own assigned students.
     * - Student: Their assigned advisor.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @return Response The response object with data or error.
     */
    public function getAllAdvisorStudents(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        $query = "SELECT ads.*,
                         a.full_name AS advisor_name, a.email AS advisor_email,
                         s.full_name AS student_name, s.matric_number, s.email AS student_email
                  FROM advisor_student ads
                  JOIN users a ON ads.advisor_id = a.user_id
                  JOIN users s ON ads.student_id = s.user_id";
        $params = [];

        if ($userRole === 'advisor') {
            $query .= " WHERE ads.advisor_id = ?";
            $params[] = $userId;
        } elseif ($userRole === 'student') {
            $query .= " WHERE ads.student_id = ?";
            $params[] = $userId;
        } elseif ($userRole !== 'admin') {
            $response->getBody()->write(json_encode(['error' => 'Access denied for this role.']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode($assignments));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error fetching all advisor-student assignments: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve advisor-student assignments.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Get a single advisor-student assignment by ID.
     * Accessible to:
     * - Admin: Any assignment.
     * - Advisor: Their own assigned students.
     * - Student: Their assigned advisor.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., assignment ID).
     * @return Response The response object with data or error.
     */
    public function getAdvisorStudentById(Request $request, Response $response, array $args): Response
    {
        $assignmentId = $args['id'];
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        if (!is_numeric($assignmentId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid assignment ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $query = "SELECT ads.*,
                         a.full_name AS advisor_name, a.email AS advisor_email,
                         s.full_name AS student_name, s.matric_number, s.email AS student_email
                  FROM advisor_student ads
                  JOIN users a ON ads.advisor_id = a.user_id
                  JOIN users s ON ads.student_id = s.user_id
                  WHERE ads.advisor_student_id = ?";
        $params = [$assignmentId];

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$assignment) {
                $response->getBody()->write(json_encode(['error' => 'Advisor-student assignment not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Authorization check
            if ($userRole === 'advisor' && (string)$assignment['advisor_id'] !== (string)$userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only view your own assigned students.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole === 'student' && (string)$assignment['student_id'] !== (string)$userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only view your own advisor assignment.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole !== 'admin') {
                $response->getBody()->write(json_encode(['error' => 'Access denied for this role.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode($assignment));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error fetching advisor-student assignment by ID {$assignmentId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve advisor-student assignment.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Add a new advisor-student assignment.
     * Accessible to admin users only.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @return Response The response object with success message or error.
     */
    public function addAdvisorStudent(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');

        if (!isset($jwt->role) || $jwt->role !== 'admin') {
            $response->getBody()->write(json_encode(['error' => 'Access denied: admin only']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        $data = json_decode($request->getBody()->getContents(), true);

        // Basic validation for required fields
        if (empty($data['advisor_id']) || empty($data['student_id'])) {
            $response->getBody()->write(json_encode(['error' => 'Advisor ID and Student ID are required.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Validate advisor_id and student_id exist and have correct roles
        try {
            $stmtAdvisor = $this->pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'advisor'");
            $stmtAdvisor->execute([$data['advisor_id']]);
            if (!$stmtAdvisor->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Invalid advisor ID or user is not an advisor.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $stmtStudent = $this->pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'student'");
            $stmtStudent->execute([$data['student_id']]);
            if (!$stmtStudent->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Invalid student ID or user is not a student.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        } catch (PDOException $e) {
            error_log("Error validating advisor/student IDs for assignment: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error during ID validation.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO advisor_student (advisor_id, student_id) VALUES (?, ?)");
            $stmt->execute([
                $data['advisor_id'],
                $data['student_id']
            ]);

            $response->getBody()->write(json_encode(['message' => 'Advisor-student assignment added successfully', 'advisor_student_id' => $this->pdo->lastInsertId()]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // Unique constraint violation
                $errorMessage = 'This advisor is already assigned to this student.';
            } else {
                $errorMessage = 'Database error: Could not add advisor-student assignment.';
            }
            error_log("Error adding advisor-student assignment: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $errorMessage]));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Update an existing advisor-student assignment.
     * Accessible to admin users only.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., assignment ID).
     * @return Response The response object with success message or error.
     */
    public function updateAdvisorStudent(Request $request, Response $response, array $args): Response
    {
        $assignmentId = $args['id'];
        $jwt = $request->getAttribute('jwt');

        if (!isset($jwt->role) || $jwt->role !== 'admin') {
            $response->getBody()->write(json_encode(['error' => 'Access denied: admin only']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        if (!is_numeric($assignmentId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid assignment ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $data = json_decode($request->getBody()->getContents(), true);

        if (empty($data)) {
            $response->getBody()->write(json_encode(['error' => 'No data provided for update.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $setClauses = [];
        $params = [];

        if (isset($data['advisor_id'])) {
            $stmtAdvisor = $this->pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'advisor'");
            $stmtAdvisor->execute([$data['advisor_id']]);
            if (!$stmtAdvisor->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Invalid new advisor ID or user is not an advisor.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            $setClauses[] = 'advisor_id = ?';
            $params[] = $data['advisor_id'];
        }
        if (isset($data['student_id'])) {
            $stmtStudent = $this->pdo->prepare("SELECT user_id FROM users WHERE user_id = ? AND role = 'student'");
            $stmtStudent->execute([$data['student_id']]);
            if (!$stmtStudent->fetch()) {
                $response->getBody()->write(json_encode(['error' => 'Invalid new student ID or user is not a student.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            $setClauses[] = 'student_id = ?';
            $params[] = $data['student_id'];
        }

        if (empty($setClauses)) {
            $response->getBody()->write(json_encode(['error' => 'No valid fields provided for update.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $params[] = $assignmentId; // Add the assignment ID for the WHERE clause

        $query = "UPDATE advisor_student SET " . implode(', ', $setClauses) . " WHERE advisor_student_id = ?";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Advisor-student assignment not found or no changes made.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Advisor-student assignment updated successfully']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $errorMessage = 'This advisor is already assigned to this student with the new IDs.';
            } else {
                $errorMessage = 'Database error: Could not update advisor-student assignment.';
            }
            error_log("Error updating advisor-student assignment ID {$assignmentId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $errorMessage]));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Delete an advisor-student assignment.
     * Accessible to admin users only.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., assignment ID).
     * @return Response The response object with success message or error.
     */
    public function deleteAdvisorStudent(Request $request, Response $response, array $args): Response
    {
        $assignmentId = $args['id'];
        $jwt = $request->getAttribute('jwt');

        if (!isset($jwt->role) || $jwt->role !== 'admin') {
            $response->getBody()->write(json_encode(['error' => 'Access denied: admin only']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        if (!is_numeric($assignmentId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid assignment ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $stmt = $this->pdo->prepare("DELETE FROM advisor_student WHERE advisor_student_id = ?");
            $stmt->execute([$assignmentId]);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Advisor-student assignment not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Advisor-student assignment deleted successfully']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error deleting advisor-student assignment ID {$assignmentId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not delete advisor-student assignment.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
