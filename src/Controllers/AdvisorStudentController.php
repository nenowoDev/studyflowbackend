<?php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use PDO;
use PDOException;

class AdvisorStudentController
{
    private PDO $pdo;
    private NotificationController $notificationController;

    public function __construct(PDO $pdo, NotificationController $notificationController)
    {
        $this->pdo = $pdo;
        $this->notificationController = $notificationController;
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
                     s.full_name AS student_name, s.matric_number, s.email AS student_email,
                     MAX(n.meeting_date) AS last_meeting_date
              FROM advisor_student ads
              JOIN users a ON ads.advisor_id = a.user_id
              JOIN users s ON ads.student_id = s.user_id
              LEFT JOIN advisor_notes n ON ads.advisor_student_id = n.advisor_student_id";
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
    
    $query .= " GROUP BY ads.advisor_student_id, a.full_name, a.email, s.full_name, s.matric_number, s.email";

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
            if ($userRole === 'advisor' && (string) $assignment['advisor_id'] !== (string) $userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only view your own assigned students.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole === 'student' && (string) $assignment['student_id'] !== (string) $userId) {
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
            $stmtAdvisor = $this->pdo->prepare("SELECT user_id, full_name FROM users WHERE user_id = ? AND role = 'advisor'");
            $stmtAdvisor->execute([$data['advisor_id']]);
            $advisor = $stmtAdvisor->fetch(PDO::FETCH_ASSOC);
            if (!$advisor) {
                $response->getBody()->write(json_encode(['error' => 'Invalid advisor ID or user is not an advisor.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            $advisorFullName = $advisor['full_name'];

            $stmtStudent = $this->pdo->prepare("SELECT user_id, full_name FROM users WHERE user_id = ? AND role = 'student'");
            $stmtStudent->execute([$data['student_id']]);
            $student = $stmtStudent->fetch(PDO::FETCH_ASSOC);
            if (!$student) {
                $response->getBody()->write(json_encode(['error' => 'Invalid student ID or user is not a student.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            $studentFullName = $student['full_name'];
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

            $lastpdo = $this->pdo->lastInsertId();

            $this->notificationController->createNotification(
                $data["advisor_id"],
                "New Advisee!",
                "{$jwt->user} has assigned you {$studentFullName} as a new Advisee!",
                "Advisee Assignment",
                "{$lastpdo}"
            );

            $this->notificationController->createNotification(
                $data["student_id"],
                "New Advisor!",
                "{$jwt->user} has assigned you {$advisorFullName} as your new Advisor!",
                "Advisor Assignment",
                "{$lastpdo}"
            );

            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // Unique constraint violation
                $errorMessage = 'This student is already assigned to an advisor. A student can only have one advisor.';
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

        $originalAdvisorId = null;
        $originalStudentId = null;
        $originalAdvisorFullName = '';
        $originalStudentFullName = '';

        try {
            $stmtCurrent = $this->pdo->prepare("
                SELECT 
                    ast.advisor_id, 
                    ast.student_id,
                    adv.full_name AS advisor_full_name,
                    stu.full_name AS student_full_name
                FROM advisor_student ast
                JOIN users adv ON ast.advisor_id = adv.user_id
                JOIN users stu ON ast.student_id = stu.user_id
                WHERE ast.advisor_student_id = ?
            ");
            $stmtCurrent->execute([$assignmentId]);
            $currentAssignment = $stmtCurrent->fetch(PDO::FETCH_ASSOC);

            if (!$currentAssignment) {
                $response->getBody()->write(json_encode(['error' => 'Advisor-student assignment not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $originalAdvisorId = $currentAssignment['advisor_id'];
            $originalStudentId = $currentAssignment['student_id'];
            $originalAdvisorFullName = $currentAssignment['advisor_full_name'];
            $originalStudentFullName = $currentAssignment['student_full_name'];

        } catch (PDOException $e) {
            error_log("Error fetching current advisor-student assignment details for ID {$assignmentId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve current assignment details.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        $setClauses = [];
        $params = [];
        $newAdvisorId = $originalAdvisorId; 
        $newStudentId = $originalStudentId; 
        $newAdvisorFullName = $originalAdvisorFullName; 
        $newStudentFullName = $originalStudentFullName; 

        // Handle advisor_id update
        if (isset($data['advisor_id']) && $data['advisor_id'] !== $originalAdvisorId) {
            $stmtNewAdvisor = $this->pdo->prepare("SELECT user_id, full_name FROM users WHERE user_id = ? AND role = 'advisor'");
            $stmtNewAdvisor->execute([$data['advisor_id']]);
            $newAdvisor = $stmtNewAdvisor->fetch(PDO::FETCH_ASSOC);
            if (!$newAdvisor) {
                $response->getBody()->write(json_encode(['error' => 'Invalid new advisor ID or user is not an advisor.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            $newAdvisorId = $newAdvisor['user_id'];
            $newAdvisorFullName = $newAdvisor['full_name'];
            $setClauses[] = 'advisor_id = ?';
            $params[] = $newAdvisorId;
        }


        if (isset($data['student_id']) && $data['student_id'] !== $originalStudentId) {
            $stmtNewStudent = $this->pdo->prepare("SELECT user_id, full_name FROM users WHERE user_id = ? AND role = 'student'");
            $stmtNewStudent->execute([$data['student_id']]);
            $newStudent = $stmtNewStudent->fetch(PDO::FETCH_ASSOC);
            if (!$newStudent) {
                $response->getBody()->write(json_encode(['error' => 'Invalid new student ID or user is not a student.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            $newStudentId = $newStudent['user_id'];
            $newStudentFullName = $newStudent['full_name'];
            $setClauses[] = 'student_id = ?';
            $params[] = $newStudentId;
        }

        if (empty($setClauses)) {
            $response->getBody()->write(json_encode(['message' => 'No valid fields provided for update or no changes detected.']));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json'); 
        }

        $params[] = $assignmentId; 
        $query = "UPDATE advisor_student SET " . implode(', ', $setClauses) . " WHERE advisor_student_id = ?";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Advisor-student assignment not found or no actual changes made.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // --- 4. Send Notifications based on changes ---
            $adminFullName = $jwt->full_name ?? 'An Administrator';

            // Scenario 1: Advisor changed for the original student
            if ($newAdvisorId !== $originalAdvisorId) {
                // Notify NEW Advisor
                $this->notificationController->createNotification(
                    $newAdvisorId,
                    "New Advisee Assigned!",
                    "{$adminFullName} has assigned {$originalStudentFullName} as your new advisee.",
                    "Advisor Assignment",
                    $assignmentId
                );
                // Notify OLD Advisor (if different from new)
                $this->notificationController->createNotification(
                    $originalAdvisorId,
                    "Advisee Reassigned",
                    "{$adminFullName} has reassigned {$originalStudentFullName} from you. They are now advised by {$newAdvisorFullName}.",
                    "Advisor Assignment",
                    $assignmentId
                );
                // Notify Student about advisor change
                $this->notificationController->createNotification(
                    $originalStudentId, // Student ID remains the same here
                    "Your Advisor Has Changed",
                    "{$adminFullName} has updated your advisor from {$originalAdvisorFullName} to {$newAdvisorFullName}.",
                    "Advisor Assignment",
                    $assignmentId
                );
            }

            // Scenario 2: Student changed for the original advisor
            if ($newStudentId !== $originalStudentId) {
                // Notify NEW Student
                $this->notificationController->createNotification(
                    $newStudentId,
                    "New Academic Advisor Assigned!",
                    "{$adminFullName} has assigned {$originalAdvisorFullName} as your new Academic Advisor.",
                    "advisor_assignment_change",
                    $assignmentId
                );

                if ($newAdvisorId === $originalAdvisorId) {
                    $this->notificationController->createNotification(
                        $originalAdvisorId,
                        "Advisee Reassigned",
                        "{$adminFullName} has updated your advisee for this slot from {$originalStudentFullName} to {$newStudentFullName}.",
                        "advisor_assignment_change",
                        $assignmentId
                    );
                }
            }

            $response->getBody()->write(json_encode(['message' => 'Advisor-student assignment updated successfully']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $errorMessage = 'The assigned student is already associated with another advisor, or the new advisor is already assigned to this student. A student can only have one advisor.';
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

        // --- 1. Fetch CURRENT assignment details and user full names BEFORE deletion ---
        $advisorId = null;
        $studentId = null;
        $advisorFullName = '';
        $studentFullName = '';

        try {
            $stmtCurrent = $this->pdo->prepare("
                SELECT 
                    ast.advisor_id, 
                    ast.student_id,
                    adv.full_name AS advisor_full_name,
                    stu.full_name AS student_full_name
                FROM advisor_student ast
                JOIN users adv ON ast.advisor_id = adv.user_id
                JOIN users stu ON ast.student_id = stu.user_id
                WHERE ast.advisor_student_id = ?
            ");
            $stmtCurrent->execute([$assignmentId]);
            $currentAssignment = $stmtCurrent->fetch(PDO::FETCH_ASSOC);

            if (!$currentAssignment) {
                // If assignment not found, return 404 immediately
                $response->getBody()->write(json_encode(['error' => 'Advisor-student assignment not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $advisorId = $currentAssignment['advisor_id'];
            $studentId = $currentAssignment['student_id'];
            $advisorFullName = $currentAssignment['advisor_full_name'];
            $studentFullName = $currentAssignment['student_full_name'];

        } catch (PDOException $e) {
            error_log("Error fetching advisor-student assignment details for deletion (ID: {$assignmentId}): " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve assignment details for deletion.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        try {
            $stmt = $this->pdo->prepare("DELETE FROM advisor_student WHERE advisor_student_id = ?");
            $stmt->execute([$assignmentId]);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Advisor-student assignment not found or already deleted.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $adminFullName = $jwt->full_name ?? 'An Administrator'; // Get admin's name from JWT

            $this->notificationController->createNotification(
                $advisorId,
                "Advisee Unassigned",
                "{$adminFullName} has unassigned {$studentFullName} from your advisee list. They are no longer your advisee.",
                "advisor_unassignment", 
                $assignmentId 
            );

            $this->notificationController->createNotification(
                $studentId,
                "Academic Advisor Unassigned",
                "{$adminFullName} has unassigned you from {$advisorFullName}",
                "Advisor Assignment",
                $assignmentId
            );

            $response->getBody()->write(json_encode(['message' => 'Advisor-student assignment deleted successfully.']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error deleting advisor-student assignment ID {$assignmentId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not delete advisor-student assignment.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}