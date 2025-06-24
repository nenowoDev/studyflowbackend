<?php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use PDO;
use PDOException;

class RemarkRequestController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get all remark requests.
     * Accessible to:
     * - Admin: All requests.
     * - Lecturer: Requests for students in their assigned courses.
     * - Student: Their own requests.
     * - Advisor: Requests for their advisee students.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @return Response The response object with request data or error.
     */
    public function getAllRemarkRequests(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        $query = "SELECT rr.*,
                         s.full_name AS student_name, s.matric_number,
                         sm.mark_obtained,
                         ac.component_name, ac.max_mark,
                         c.course_name, c.course_code, c.lecturer_id AS course_lecturer_id,
                         lecturer_resolver.full_name AS resolved_by_name
                  FROM remark_requests rr
                  JOIN student_marks sm ON rr.mark_id = sm.mark_id
                  JOIN enrollments e ON sm.enrollment_id = e.enrollment_id
                  JOIN users s ON rr.student_id = s.user_id
                  JOIN assessment_components ac ON sm.component_id = ac.component_id
                  JOIN courses c ON ac.course_id = c.course_id
                  LEFT JOIN users lecturer_resolver ON rr.resolved_by = lecturer_resolver.user_id";
        $params = [];

        if ($userRole === 'student') {
            $query .= " WHERE rr.student_id = ?";
            $params[] = $userId;
        } elseif ($userRole === 'lecturer') {
            $query .= " WHERE c.lecturer_id = ?"; // Lecturer can see requests for their courses
            $params[] = $userId;
        } elseif ($userRole === 'advisor') {
            // Advisor can see requests for their advisee students
            $query .= " JOIN advisor_student ads ON rr.student_id = ads.student_id WHERE ads.advisor_id = ?";
            $params[] = $userId;
        } elseif ($userRole !== 'admin') {
            $response->getBody()->write(json_encode(['error' => 'Access denied for this role.']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode($requests));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error fetching all remark requests: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve remark requests.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Get a single remark request by ID.
     * Accessible to:
     * - Admin: Any request.
     * - Lecturer: Requests for students in their assigned courses.
     * - Student: Their own requests.
     * - Advisor: Requests for their advisee students.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., request ID).
     * @return Response The response object with request data or error.
     */
    public function getRemarkRequestById(Request $request, Response $response, array $args): Response
    {
        $requestId = $args['id'];
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        if (!is_numeric($requestId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid request ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $query = "SELECT rr.*,
                         s.full_name AS student_name, s.matric_number,
                         sm.mark_obtained,
                         ac.component_name, ac.max_mark,
                         c.course_name, c.course_code, c.lecturer_id AS course_lecturer_id
                  FROM remark_requests rr
                  JOIN student_marks sm ON rr.mark_id = sm.mark_id
                  JOIN enrollments e ON sm.enrollment_id = e.enrollment_id
                  JOIN users s ON rr.student_id = s.user_id
                  JOIN assessment_components ac ON sm.component_id = ac.component_id
                  JOIN courses c ON ac.course_id = c.course_id
                  WHERE rr.request_id = ?";
        $params = [$requestId];

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $requestData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$requestData) {
                $response->getBody()->write(json_encode(['error' => 'Remark request not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Authorization check
            if ($userRole === 'student' && (string)$requestData['student_id'] !== (string)$userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only view your own remark requests.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole === 'lecturer' && (string)$requestData['course_lecturer_id'] !== (string)$userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only view remark requests for your courses.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole === 'advisor') {
                $stmtCheckAdvisor = $this->pdo->prepare("SELECT COUNT(*) FROM advisor_student WHERE advisor_id = ? AND student_id = ?");
                $stmtCheckAdvisor->execute([$userId, $requestData['student_id']]);
                if ($stmtCheckAdvisor->fetchColumn() === 0) {
                    $response->getBody()->write(json_encode(['error' => 'Access denied: You can only view remark requests for your advisees.']));
                    return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
                }
            } elseif ($userRole !== 'admin') {
                $response->getBody()->write(json_encode(['error' => 'Access denied for this role.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode($requestData));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error fetching remark request by ID {$requestId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve remark request.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Add a new remark request.
     * Accessible to students (for their own marks).
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @return Response The response object with success message or error.
     */
    public function addRemarkRequest(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');
        $studentId = $jwt->user_id; // The user making the request must be a student
        $userRole = $jwt->role;

        if ($userRole !== 'student') {
            $response->getBody()->write(json_encode(['error' => 'Access denied: Only students can submit remark requests.']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        $data = json_decode($request->getBody()->getContents(), true);

        // Basic validation for required fields
        if (empty($data['mark_id']) || empty($data['justification'])) {
            $response->getBody()->write(json_encode(['error' => 'Mark ID and justification are required.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Validate that the mark_id belongs to the student making the request
        try {
            $stmtCheckMark = $this->pdo->prepare("SELECT COUNT(*) FROM student_marks sm JOIN enrollments e ON sm.enrollment_id = e.enrollment_id WHERE sm.mark_id = ? AND e.student_id = ?");
            $stmtCheckMark->execute([$data['mark_id'], $studentId]);
            if ($stmtCheckMark->fetchColumn() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Invalid mark ID or mark does not belong to your account.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
        } catch (PDOException $e) {
            error_log("Error validating mark ID for remark request: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error during mark validation.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        try {
            $stmt = $this->pdo->prepare("INSERT INTO remark_requests (mark_id, student_id, justification, status) VALUES (?, ?, ?, 'pending')");
            $stmt->execute([
                $data['mark_id'],
                $studentId,
                $data['justification']
            ]);

            $response->getBody()->write(json_encode(['message' => 'Remark request submitted successfully', 'request_id' => $this->pdo->lastInsertId()]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // Unique constraint violation, if any (e.g., student can only submit one request per mark)
                $errorMessage = 'A remark request for this mark already exists from this student.';
            } else {
                $errorMessage = 'Database error: Could not submit remark request.';
            }
            error_log("Error adding remark request: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $errorMessage]));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Update an existing remark request.
     * Accessible to:
     * - Admin: Any request.
     * - Lecturer: Requests for students in their assigned courses (can change status, add notes).
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., request ID).
     * @return Response The response object with success message or error.
     */
    public function updateRemarkRequest(Request $request, Response $response, array $args): Response
    {
        $requestId = $args['id'];
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        if (!is_numeric($requestId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid request ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $data = json_decode($request->getBody()->getContents(), true);

        if (empty($data)) {
            $response->getBody()->write(json_encode(['error' => 'No data provided for update.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            // Fetch request details to check authorization
            $stmtRequest = $this->pdo->prepare("
                SELECT rr.*, c.lecturer_id AS course_lecturer_id
                FROM remark_requests rr
                JOIN student_marks sm ON rr.mark_id = sm.mark_id
                JOIN enrollments e ON sm.enrollment_id = e.enrollment_id
                JOIN assessment_components ac ON sm.component_id = ac.component_id
                JOIN courses c ON ac.course_id = c.course_id
                WHERE rr.request_id = ?
            ");
            $stmtRequest->execute([$requestId]);
            $existingRequest = $stmtRequest->fetch(PDO::FETCH_ASSOC);

            if (!$existingRequest) {
                $response->getBody()->write(json_encode(['error' => 'Remark request not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Authorization check
            if ($userRole === 'lecturer' && (string)$existingRequest['course_lecturer_id'] !== (string)$userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only update remark requests for your assigned courses.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole === 'student') {
                // Students cannot update requests once submitted (only delete if pending)
                $response->getBody()->write(json_encode(['error' => 'Access denied: Students cannot update remark requests.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole !== 'admin' && $userRole !== 'lecturer') {
                $response->getBody()->write(json_encode(['error' => 'Access denied for this role.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $setClauses = [];
            $params = [];

            if (isset($data['justification']) && $userRole === 'admin') { // Only admin can update justification directly
                $setClauses[] = 'justification = ?';
                $params[] = $data['justification'];
            }
            if (isset($data['status'])) {
                $allowedStatuses = ['pending', 'approved', 'rejected'];
                if (!in_array($data['status'], $allowedStatuses)) {
                    $response->getBody()->write(json_encode(['error' => 'Invalid status provided.']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
                $setClauses[] = 'status = ?';
                $params[] = $data['status'];
            }
            if (isset($data['lecturer_notes'])) {
                $setClauses[] = 'lecturer_notes = ?';
                $params[] = $data['lecturer_notes'];
            }
            // If status is being updated to approved/rejected, set resolved_by and resolved_at
            if (isset($data['status']) && in_array($data['status'], ['approved', 'rejected'])) {
                // Only lecturer or admin can resolve a request
                if ($userRole === 'lecturer' || $userRole === 'admin') {
                    $setClauses[] = 'resolved_by = ?';
                    $params[] = $userId; // The user resolving the request
                    $setClauses[] = 'resolved_at = ?';
                    $params[] = date('Y-m-d H:i:s');
                } else {
                    $response->getBody()->write(json_encode(['error' => 'Only lecturers or admins can resolve remark requests.']));
                    return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
                }
            }

            if (empty($setClauses)) {
                $response->getBody()->write(json_encode(['error' => 'No valid fields provided for update.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $params[] = $requestId; // Add the request ID for the WHERE clause

            $query = "UPDATE remark_requests SET " . implode(', ', $setClauses) . " WHERE request_id = ?";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['message' => 'Remark request updated successfully (or no changes made).']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Remark request updated successfully']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error updating remark request ID {$requestId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not update remark request.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Delete a remark request.
     * Accessible to:
     * - Admin: Any request.
     * - Student: Their own pending requests.
     * - Lecturer: Requests for students in their assigned courses (any status).
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., request ID).
     * @return Response The response object with success message or error.
     */
    public function deleteRemarkRequest(Request $request, Response $response, array $args): Response
    {
        $requestId = $args['id'];
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        if (!is_numeric($requestId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid request ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            // Fetch request details for authorization check
            $stmtRequest = $this->pdo->prepare("
                SELECT rr.request_id, rr.student_id, rr.status, c.lecturer_id AS course_lecturer_id
                FROM remark_requests rr
                JOIN student_marks sm ON rr.mark_id = sm.mark_id
                JOIN enrollments e ON sm.enrollment_id = e.enrollment_id
                JOIN assessment_components ac ON sm.component_id = ac.component_id
                JOIN courses c ON ac.course_id = c.course_id
                WHERE rr.request_id = ?
            ");
            $stmtRequest->execute([$requestId]);
            $existingRequest = $stmtRequest->fetch(PDO::FETCH_ASSOC);

            if (!$existingRequest) {
                $response->getBody()->write(json_encode(['error' => 'Remark request not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Authorization check
            if ($userRole === 'student') {
                if ((string)$existingRequest['student_id'] !== (string)$userId || $existingRequest['status'] !== 'pending') {
                    $response->getBody()->write(json_encode(['error' => 'Access denied: You can only delete your own pending remark requests.']));
                    return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
                }
            } elseif ($userRole === 'lecturer') {
                if ((string)$existingRequest['course_lecturer_id'] !== (string)$userId) {
                    $response->getBody()->write(json_encode(['error' => 'Access denied: You can only delete remark requests for your assigned courses.']));
                    return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
                }
            } elseif ($userRole !== 'admin') {
                $response->getBody()->write(json_encode(['error' => 'Access denied for this role.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $stmt = $this->pdo->prepare("DELETE FROM remark_requests WHERE request_id = ?");
            $stmt->execute([$requestId]);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Remark request not found or not authorized to delete.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Remark request deleted successfully']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error deleting remark request ID {$requestId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not delete remark request.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
