<?php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use PDO;
use PDOException;

class StudentMarkController
{
    private PDO $pdo;
    private NotificationController $notificationController; // Declare NotificationController

    public function __construct(PDO $pdo, NotificationController $notificationController) // Inject NotificationController
    {
        $this->pdo = $pdo;
        $this->notificationController = $notificationController; // Assign it to a property
    }

    /**
     * Get anonymized student marks for peer comparison
     * Students can see aggregated data without individual student identities (except their own)
     * Lecturers and admins can see full details
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @return Response The response object with mark data or error.
     */
    public function getAllStudentMarksForPeerComparison(Request $request, Response $response): Response
    {
        // Get current user info from JWT token or session
        $currentUser = $request->getAttribute('jwt'); // Use 'jwt' as attribute name
        $userRole = $currentUser->role ?? 'student'; // Access role from JWT object
        $currentStudentId = $currentUser->user_id ?? null; // Access user_id from JWT object

        $query = "SELECT sm.*,
                         u.user_id as student_id,
                         u.full_name AS student_name,
                         c.course_name,
                         ac.component_name,
                         ac.max_mark, -- Added for GPA calculation
                         ac.weight_percentage, -- Added for GPA calculation
                         recorded_by_user.full_name AS recorded_by_name
                     FROM student_marks sm
                     JOIN enrollments e ON sm.enrollment_id = e.enrollment_id
                     JOIN users u ON e.student_id = u.user_id
                     JOIN courses c ON e.course_id = c.course_id
                     JOIN assessment_components ac ON sm.component_id = ac.component_id
                     JOIN users recorded_by_user ON sm.recorded_by = recorded_by_user.user_id
                     ORDER BY c.course_name, ac.component_name, sm.mark_obtained DESC";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute();
            $marks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // If user is a student, anonymize other students' data
            if ($userRole === 'student') {
                $anonymizedMarks = [];
                $studentCounter = 1;
                $anonymizationMap = [];

                foreach ($marks as $mark) {
                    $studentId = $mark['student_id'];

                    // Keep current student's data identifiable
                    if ($studentId == $currentStudentId) {
                        $mark['student_name'] = $mark['student_name']; // Keep real name
                        $mark['is_current_user'] = true;
                    } else {
                        // Anonymize other students
                        if (!isset($anonymizationMap[$studentId])) {
                            $anonymizationMap[$studentId] = "Student " . $studentCounter;
                            $studentCounter++;
                        }
                        $mark['student_name'] = $anonymizationMap[$studentId];
                        $mark['is_current_user'] = false;
                    }

                    // Remove sensitive identifiable information
                    // Note: 'student_id' is kept here for internal processing, but can be unset if not needed on frontend
                    // unset($mark['student_id']);
                    unset($mark['recorded_by_name']);

                    $anonymizedMarks[] = $mark;
                }

                $marks = $anonymizedMarks;
            }

            // Return the marks as a JSON response
            $response->getBody()->write(json_encode($marks));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error fetching student marks for peer comparison: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve student marks.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    /**
     * Get all student marks.
     * Accessible to admin; lecturers for their courses; students for their own marks.
     * Advisors can see full details for their advisees.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @return Response The response object with mark data or error.
     */
    public function getAllStudentMarks(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        // Added ac.max_mark and ac.weight_percentage to the SELECT statement for GPA calculation
        $query = "SELECT sm.*, u.full_name AS student_name, c.course_name, c.course_code, ac.component_name, ac.max_mark, ac.weight_percentage, recorded_by_user.full_name AS recorded_by_name
                     FROM student_marks sm
                     JOIN enrollments e ON sm.enrollment_id = e.enrollment_id
                     JOIN users u ON e.student_id = u.user_id
                     JOIN courses c ON e.course_id = c.course_id
                     JOIN assessment_components ac ON sm.component_id = ac.component_id
                     LEFT JOIN users recorded_by_user ON sm.recorded_by = recorded_by_user.user_id";
        $params = [];

        if ($userRole === 'student') {
            $query .= " WHERE e.student_id = ?";
            $params[] = $userId;
        } elseif ($userRole === 'lecturer') {
            $query .= " WHERE c.lecturer_id = ?";
            $params[] = $userId;
        } elseif ($userRole === 'advisor') { // Added advisor role check
            $query .= " JOIN advisor_student ads ON e.student_id = ads.student_id WHERE ads.advisor_id = ?";
            $params[] = $userId;
        } elseif ($userRole !== 'admin') {
            $response->getBody()->write(json_encode(['error' => 'Access denied for this role.']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $marks = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode($marks));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error fetching all student marks: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve student marks.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Get a single student mark by ID.
     * Accessible to admin; lecturers for their courses; students for their own marks.
     * Advisors can see full details for their advisees.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., mark ID).
     * @return Response The response object with mark data or error.
     */
    public function getStudentMarkById(Request $request, Response $response, array $args): Response
    {
        $markId = $args['id'];
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        if (!is_numeric($markId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid mark ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Added ac.max_mark and ac.weight_percentage to the SELECT statement for GPA calculation
        $query = "SELECT sm.*, e.student_id, c.lecturer_id, u.full_name AS student_name, c.course_name, c.course_code, ac.component_name, ac.max_mark, ac.weight_percentage, recorded_by_user.full_name AS recorded_by_name
                     FROM student_marks sm
                     JOIN enrollments e ON sm.enrollment_id = e.enrollment_id
                     JOIN users u ON e.student_id = u.user_id
                     JOIN courses c ON e.course_id = c.course_id
                     JOIN assessment_components ac ON sm.component_id = ac.component_id
                     LEFT JOIN users recorded_by_user ON sm.recorded_by = recorded_by_user.user_id
                     WHERE sm.mark_id = ?";
        $params = [$markId];

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $mark = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$mark) {
                $response->getBody()->write(json_encode(['error' => 'Student mark not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Authorization check
            if ($userRole === 'student' && (string)$mark['student_id'] !== (string)$userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only view your own marks.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole === 'lecturer' && (string)$mark['lecturer_id'] !== (string)$userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only view marks for your courses.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole === 'advisor') { // Added advisor role check
                // Check if the student of this mark is an advisee of the current advisor
                $stmtCheckAdvisorAdvisee = $this->pdo->prepare("SELECT COUNT(*) FROM advisor_student WHERE advisor_id = ? AND student_id = ?");
                $stmtCheckAdvisorAdvisee->execute([$userId, $mark['student_id']]);
                if ($stmtCheckAdvisorAdvisee->fetchColumn() === 0) {
                    $response->getBody()->write(json_encode(['error' => 'Access denied: You can only view marks for your advisees.']));
                    return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
                }
            } elseif ($userRole !== 'admin') {
                $response->getBody()->write(json_encode(['error' => 'Access denied for this role.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode($mark));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error fetching student mark by ID {$markId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve student mark.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Add a new student mark.
     * Accessible to lecturers (for their courses) and admin.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @return Response The response object with success message or error.
     */
    public function addStudentMark(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');
        $recordedBy = $jwt->user_id; // The user who is recording the mark
        $userRole = $jwt->role;

        $data = json_decode($request->getBody()->getContents(), true);

        // Basic validation for required fields
        if (empty($data['enrollment_id']) || empty($data['component_id']) || !isset($data['mark_obtained'])) {
            $response->getBody()->write(json_encode(['error' => 'Enrollment ID, Component ID, and mark obtained are required.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (!is_numeric($data['mark_obtained']) || $data['mark_obtained'] < 0) {
            $response->getBody()->write(json_encode(['error' => 'Mark obtained must be a non-negative number.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $this->pdo->beginTransaction(); // Start transaction

            // Validate enrollment and component, and check lecturer's authority over the course
            $stmtCheck = $this->pdo->prepare("
                SELECT
                    e.student_id,
                    u.full_name AS student_name,
                    ac.course_id,
                    c.lecturer_id,
                    ac.max_mark,
                    ac.component_name,
                    c.course_name
                FROM enrollments e
                JOIN assessment_components ac ON ac.course_id = e.course_id
                JOIN courses c ON e.course_id = c.course_id
                JOIN users u ON e.student_id = u.user_id
                WHERE e.enrollment_id = ? AND ac.component_id = ?
            ");
            $stmtCheck->execute([$data['enrollment_id'], $data['component_id']]);
            $checkResult = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$checkResult) {
                $this->pdo->rollBack(); // Rollback on validation failure
                $response->getBody()->write(json_encode(['error' => 'Invalid enrollment ID or component ID for this course.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Authorization check: Admin or Lecturer assigned to this course
            if ($userRole === 'lecturer' && (string)$checkResult['lecturer_id'] !== (string)$recordedBy) {
                $this->pdo->rollBack(); // Rollback on authorization failure
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only record marks for your assigned courses.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole !== 'admin' && $userRole !== 'lecturer') {
                $this->pdo->rollBack(); // Rollback on authorization failure
                $response->getBody()->write(json_encode(['error' => 'Access denied: Only admins and lecturers can add student marks.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            // Check if mark_obtained exceeds max_mark for the component
            if ($data['mark_obtained'] > $checkResult['max_mark']) {
                $this->pdo->rollBack(); // Rollback on validation failure
                $response->getBody()->write(json_encode(['error' => "Mark obtained ({$data['mark_obtained']}) exceeds the maximum mark allowed ({$checkResult['max_mark']}) for this component."]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $stmt = $this->pdo->prepare("INSERT INTO student_marks (enrollment_id, component_id, mark_obtained, recorded_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $data['enrollment_id'],
                $data['component_id'],
                $data['mark_obtained'],
                $recordedBy
            ]);

            $markId = $this->pdo->lastInsertId();

            // Notify the student about the new mark
            $this->notificationController->createNotification(
                $checkResult['student_id'],
                "New Mark Recorded: {$checkResult['component_name']} in {$checkResult['course_code']}",
                "A new mark of {$data['mark_obtained']}/{$checkResult['max_mark']} has been recorded for your '{$checkResult['component_name']}' component in '{$checkResult['course_name']}'.",
                "new_mark",
                $markId
            );

            $this->pdo->commit(); // Commit transaction

            $response->getBody()->write(json_encode(['message' => 'Student mark added successfully', 'mark_id' => $markId]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            $this->pdo->rollBack(); // Rollback transaction on error
            if ($e->getCode() == '23000') { // Unique constraint violation
                $errorMessage = 'A mark for this student and assessment component already exists. Use PUT to update.';
            } else {
                $errorMessage = 'Database error: Could not add student mark.';
            }
            error_log("Error adding student mark: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $errorMessage]));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Update an existing student mark.
     * Accessible to lecturers (for their courses) and admin.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., mark ID).
     * @return Response The response object with success message or error.
     */
    public function updateStudentMark(Request $request, Response $response, array $args): Response
    {
        $markId = $args['id'];
        $jwt = $request->getAttribute('jwt');
        $recordedBy = $jwt->user_id;
        $userRole = $jwt->role;

        if (!is_numeric($markId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid mark ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $data = json_decode($request->getBody()->getContents(), true);

        if (empty($data)) {
            $response->getBody()->write(json_encode(['error' => 'No data provided for update.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $this->pdo->beginTransaction(); // Start transaction

            // Verify recordedBy user exists
            $stmtCheckRecorder = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE user_id = ?");
            $stmtCheckRecorder->execute([$recordedBy]);
            if ($stmtCheckRecorder->fetchColumn() === 0) {
                $this->pdo->rollBack();
                error_log("ERROR: updateStudentMark - Recorded by user ID {$recordedBy} not found in users table.");
                $response->getBody()->write(json_encode(['error' => 'The user recording this mark does not exist.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Fetch mark details to check authorization and max_mark, and get student/course info for notification
            $stmtMark = $this->pdo->prepare("
                SELECT
                    sm.enrollment_id,
                    sm.component_id,
                    sm.mark_obtained AS old_mark_obtained, -- Get old mark for notification
                    e.student_id,
                    u.full_name AS student_name,
                    ac.course_id,
                    c.lecturer_id,
                    ac.max_mark,
                    ac.component_name,
                    c.course_name,
                    c.course_code
                FROM student_marks sm
                JOIN enrollments e ON sm.enrollment_id = e.enrollment_id
                JOIN users u ON e.student_id = u.user_id
                JOIN assessment_components ac ON sm.component_id = ac.component_id
                JOIN courses c ON e.course_id = c.course_id
                WHERE sm.mark_id = ?
            ");
            $stmtMark->execute([$markId]);
            $existingMark = $stmtMark->fetch(PDO::FETCH_ASSOC);

            if (!$existingMark) {
                $this->pdo->rollBack(); // Rollback on validation failure
                $response->getBody()->write(json_encode(['error' => 'Student mark not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Authorization check
            if ($userRole === 'lecturer' && (string)$existingMark['lecturer_id'] !== (string)$recordedBy) {
                $this->pdo->rollBack(); // Rollback on authorization failure
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only update marks for your assigned courses.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole !== 'admin' && $userRole !== 'lecturer') {
                $this->pdo->rollBack(); // Rollback on authorization failure
                $response->getBody()->write(json_encode(['error' => 'Access denied: Only admins and lecturers can update student marks.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $setClauses = [];
            $params = [];
            $notifyStudent = false;
            $notificationMessage = "";

            if (isset($data['mark_obtained'])) {
                if (!is_numeric($data['mark_obtained']) || $data['mark_obtained'] < 0) {
                    $this->pdo->rollBack(); // Rollback on validation failure
                    $response->getBody()->write(json_encode(['error' => 'Mark obtained must be a non-negative number.']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
                // Validate against max_mark
                if ($data['mark_obtained'] > $existingMark['max_mark']) {
                    $this->pdo->rollBack(); // Rollback on validation failure
                    $response->getBody()->write(json_encode(['error' => "Mark obtained ({$data['mark_obtained']}) exceeds the maximum mark allowed ({$existingMark['max_mark']}) for this component."]));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
                $setClauses[] = 'mark_obtained = ?';
                $params[] = $data['mark_obtained'];

                // Prepare notification message if mark changed
                if ((float)$data['mark_obtained'] !== (float)$existingMark['old_mark_obtained']) {
                    $notifyStudent = true;
                    $notificationMessage = "Your mark for '{$existingMark['component_name']}' in '{$existingMark['course_name']}' has been updated from {$existingMark['old_mark_obtained']} to {$data['mark_obtained']}.";
                }
            }

            // Allow changing enrollment_id or component_id (though rare for existing marks)
            if (isset($data['enrollment_id'])) {
                // Re-validate enrollment_id to ensure it's valid
                $stmtEnrollment = $this->pdo->prepare("SELECT enrollment_id FROM enrollments WHERE enrollment_id = ?");
                $stmtEnrollment->execute([$data['enrollment_id']]);
                if (!$stmtEnrollment->fetch()) {
                    $this->pdo->rollBack(); // Rollback on validation failure
                    $response->getBody()->write(json_encode(['error' => 'Invalid new enrollment ID.']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
                $setClauses[] = 'enrollment_id = ?';
                $params[] = $data['enrollment_id'];
            }
            if (isset($data['component_id'])) {
                // Re-validate component_id to ensure it's valid
                $stmtComponent = $this->pdo->prepare("SELECT component_id FROM assessment_components WHERE component_id = ?");
                $stmtComponent->execute([$data['component_id']]);
                if (!$stmtComponent->fetch()) {
                    $this->pdo->rollBack(); // Rollback on validation failure
                    $response->getBody()->write(json_encode(['error' => 'Invalid new component ID.']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
                $setClauses[] = 'component_id = ?';
                $params[] = $data['component_id'];
            }

            if (empty($setClauses)) {
                $this->pdo->rollBack(); // Rollback if no changes were attempted
                $response->getBody()->write(json_encode(['message' => 'No valid fields provided for update.']));
                return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
            }

            // Corrected parameter order: recorded_by first, then mark_id for WHERE clause
            $query = "UPDATE student_marks SET " . implode(', ', $setClauses) . ", recorded_by = ? WHERE mark_id = ?"; 
            $params[] = $recordedBy; // Add recordedBy to params for the SET clause
            $params[] = $markId;     // Add the mark ID for the WHERE clause

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                // Check if the record exists to avoid returning 404 unnecessarily
                $checkStmt = $this->pdo->prepare("SELECT 1 FROM student_marks WHERE mark_id = ?");
                $checkStmt->execute([$markId]);
                if (!$checkStmt->fetch()) {
                    $this->pdo->rollBack(); // Rollback if mark not found
                    $response->getBody()->write(json_encode(['error' => 'Student mark not found.']));
                    return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
                }
                $this->pdo->commit(); // Commit even if no changes were made but record exists
                $response->getBody()->write(json_encode(['message' => 'Student mark updated successfully (or no changes made).']));
                return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
            }

            // Send notification to student if applicable
            if ($notifyStudent) {
                $this->notificationController->createNotification(
                    $existingMark['student_id'],
                    "Mark Updated: {$existingMark['component_name']} in {$existingMark['course_code']}",
                    $notificationMessage,
                    "mark_update",
                    $markId
                );
            }

            $this->pdo->commit(); // Commit transaction

            $response->getBody()->write(json_encode(['message' => 'Student mark updated successfully']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            $this->pdo->rollBack(); // Rollback transaction on error
            if ($e->getCode() == '23000') {
                $errorMessage = 'A mark for this enrollment and component already exists.';
            } else {
                $errorMessage = 'Database error: Could not update student mark.';
            }
            error_log("Error updating student mark ID {$markId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $errorMessage]));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Delete a student mark.
     * Accessible to lecturers (for their courses) and admin.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., mark ID).
     * @return Response The response object with success message or error.
     */
    public function deleteStudentMark(Request $request, Response $response, array $args): Response
    {
        $markId = $args['id'];
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        if (!is_numeric($markId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid mark ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $this->pdo->beginTransaction(); // Start transaction

            // Fetch mark details to check authorization and get student/course info for notification
            $stmtMark = $this->pdo->prepare("
                SELECT sm.mark_id, e.student_id, u.full_name AS student_name, c.lecturer_id,
                       ac.component_name, c.course_name, c.course_code
                FROM student_marks sm
                JOIN enrollments e ON sm.enrollment_id = e.enrollment_id
                JOIN users u ON e.student_id = u.user_id
                JOIN courses c ON e.course_id = c.course_id
                JOIN assessment_components ac ON sm.component_id = ac.component_id
                WHERE sm.mark_id = ?
            ");
            $stmtMark->execute([$markId]);
            $existingMark = $stmtMark->fetch(PDO::FETCH_ASSOC);

            if (!$existingMark) {
                $this->pdo->rollBack(); // Rollback if mark not found
                $response->getBody()->write(json_encode(['error' => 'Student mark not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Authorization check
            if ($userRole === 'lecturer' && (string)$existingMark['lecturer_id'] !== (string)$userId) {
                $this->pdo->rollBack(); // Rollback on authorization failure
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only delete marks for your assigned courses.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole !== 'admin' && $userRole !== 'lecturer') {
                $this->pdo->rollBack(); // Rollback on authorization failure
                $response->getBody()->write(json_encode(['error' => 'Access denied: Only admins and lecturers can delete student marks.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $stmt = $this->pdo->prepare("DELETE FROM student_marks WHERE mark_id = ?");
            $stmt->execute([$markId]);

            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack(); // Rollback if no row was deleted
                $response->getBody()->write(json_encode(['error' => 'Student mark not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Notify the student that their mark was deleted
            $this->notificationController->createNotification(
                $existingMark['student_id'],
                "Mark Deleted: {$existingMark['component_name']} in {$existingMark['course_code']}",
                "Your mark for '{$existingMark['component_name']}' in '{$existingMark['course_name']}' has been deleted by {$jwt->user}.",
                "mark_deleted",
                $markId
            );

            $this->pdo->commit(); // Commit transaction

            $response->getBody()->write(json_encode(['message' => 'Student mark deleted successfully']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            $this->pdo->rollBack(); // Rollback transaction on error
            error_log("Error deleting student mark ID {$markId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not delete student mark.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    public function getStudentMarksByStudentId(Request $request, Response $response, array $args): Response
    {
        $studentId = $args['id'];
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        error_log("Backend Debug: Received studentId for marks: " . $studentId);

        // Authorization check
        if ($userRole === 'advisor') {
            $stmtCheckAdvisee = $this->pdo->prepare("SELECT COUNT(*) FROM advisor_student WHERE advisor_id = ? AND student_id = ?");
            $stmtCheckAdvisee->execute([$userId, $studentId]);
            if ($stmtCheckAdvisee->fetchColumn() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only view marks for your assigned advisees.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }
        } elseif ($userRole === 'lecturer') {
            $stmtCheckLecturer = $this->pdo->prepare("
                SELECT COUNT(*)
                FROM enrollments e
                JOIN courses c ON e.course_id = c.course_id
                WHERE e.student_id = ? AND c.lecturer_id = ?
            ");
            $stmtCheckLecturer->execute([$studentId, $userId]);

            if ($stmtCheckLecturer->fetchColumn() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You are not authorized to view this student\'s marks.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }
        } elseif ($userRole !== 'admin' && (string)$studentId !== (string)$userId) {
            $response->getBody()->write(json_encode(['error' => 'Access denied: You can only view your own marks or you are not authorized to view this student\'s marks.']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }


        try {
            $stmtEnrollments = $this->pdo->prepare("SELECT enrollment_id FROM enrollments WHERE student_id = ?");
            $stmtEnrollments->execute([$studentId]);
            $enrollmentIds = $stmtEnrollments->fetchAll(PDO::FETCH_COLUMN);

            error_log("Backend Debug: Enrollment IDs found for student " . $studentId . ": " . print_r($enrollmentIds, true));

            if (empty($enrollmentIds)) {
                error_log("Backend Debug: No enrollment IDs found for student " . $studentId . ". Returning empty marks array.");
                $response->getBody()->write(json_encode([]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $inClausePlaceholders = implode(',', array_fill(0, count($enrollmentIds), '?'));

            $query = "
                SELECT sm.mark_id, sm.enrollment_id, sm.component_id, sm.mark_obtained, sm.recorded_by,
                             ac.max_mark, ac.weight_percentage,
                             c.course_id, c.course_name, c.course_code, c.credit_hours
                FROM student_marks sm
                JOIN assessment_components ac ON sm.component_id = ac.component_id
                JOIN enrollments e ON sm.enrollment_id = e.enrollment_id
                JOIN courses c ON e.course_id = c.course_id
                WHERE sm.enrollment_id IN (" . $inClausePlaceholders . ")
            ";

            error_log("Backend Debug: Executing marks query: " . $query . " with parameters: " . print_r($enrollmentIds, true));

            $stmtMarks = $this->pdo->prepare($query);
            $stmtMarks->execute($enrollmentIds);
            $marks = $stmtMarks->fetchAll(PDO::FETCH_ASSOC);

            error_log("Backend Debug: Fetched marks for studentId " . $studentId . ": " . print_r($marks, true));

            if (!$marks) {
                $marks = [];
            }

            $response->getBody()->write(json_encode($marks));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (PDOException $e) {
            error_log("Backend Error: Failed to fetch student marks for student_id {$studentId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to fetch student marks.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Get a student's course summaries (total marks and GPA).
     * Accessible to students (for their own summaries) and admins.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @return Response The response object with course summaries or an error.
     */
    public function getStudentCourseSummaries(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        // Authorization: Only students and admins can view course summaries via this method
        if ($userRole !== 'student' && $userRole !== 'admin') {
            $response->getBody()->write(json_encode(['error' => 'Access denied: Only students and administrators can view course summaries.']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        try {
            // SQL query to get all courses and related assessment components for a specific student
            $query = "SELECT
                         c.course_id,
                         c.course_code,
                         c.course_name,
                         c.credit_hours,
                         ac.component_id,
                         ac.component_name,
                         ac.max_mark,
                         ac.weight_percentage,
                         sm.mark_obtained
                     FROM
                         courses c
                     JOIN
                         enrollments e ON c.course_id = e.course_id
                     JOIN
                         assessment_components ac ON c.course_id = ac.course_id
                     LEFT JOIN
                         student_marks sm ON e.enrollment_id = sm.enrollment_id AND ac.component_id = sm.component_id
                     WHERE
                         e.student_id = ?";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$userId]);
            $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Group data by course to calculate totals
            $courseSummaries = [];
            foreach ($rawData as $row) {
                $courseId = $row['course_id'];

                // Initialize course data if it doesn't exist
                if (!isset($courseSummaries[$courseId])) {
                    $courseSummaries[$courseId] = [
                        'course_id' => $courseId,
                        'course_code' => $row['course_code'],
                        'course_name' => $row['course_name'],
                        'credit_hours' => $row['credit_hours'],
                        'total_mark' => 0,
                        'total_weighted_percentage' => 0,
                        'total_gpa' => 0,
                        'components' => []
                    ];
                }

                // Add component details
                $courseSummaries[$courseId]['components'][] = [
                    'component_id' => $row['component_id'],
                    'component_name' => $row['component_name'],
                    'max_mark' => $row['max_mark'],
                    'weight_percentage' => $row['weight_percentage'],
                    'mark_obtained' => $row['mark_obtained']
                ];

                // Calculate weighted mark for this component
                if ($row['mark_obtained'] !== null && $row['max_mark'] > 0) {
                    $weightedMark = ($row['mark_obtained'] / $row['max_mark']) * $row['weight_percentage'];
                    $courseSummaries[$courseId]['total_mark'] += $weightedMark;
                }

                $courseSummaries[$courseId]['total_weighted_percentage'] += $row['weight_percentage'];
            }

            // Calculate GPA for each course
            foreach ($courseSummaries as &$summary) {
                if ($summary['total_weighted_percentage'] > 0) {
                    $overallPercentage = $summary['total_mark'] / $summary['total_weighted_percentage'] * 100;
                    $summary['overall_percentage'] = round($overallPercentage, 2);
                    $summary['gpa_grade'] = $this->calculateGpaGrade($overallPercentage);
                } else {
                    $summary['overall_percentage'] = 0;
                    $summary['gpa_grade'] = 'N/A';
                }
            }
            unset($summary); // Break the reference

            // Convert to a simple array for the JSON response
            $responseBody = array_values($courseSummaries);

            $response->getBody()->write(json_encode($responseBody));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (PDOException $e) {
            error_log("Error fetching student course summaries for user {$userId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to fetch course summaries.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    private function calculateGpaGrade(float $percentage): string
    {
        if ($percentage >= 90) return 'A';
        if ($percentage >= 80) return 'B';
        if ($percentage >= 70) return 'C';
        if ($percentage >= 60) return 'D';
        return 'F';
    }

    /**
     * Update or add multiple student marks in a batch.
     * Accessible to lecturers (for their courses) and admin.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @return Response The response object with success message or error.
     */
    public function batchUpdateStudentMarks(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');
        $recordedBy = $jwt->user_id;
        $userRole = $jwt->role;
        $recorderName = $jwt->user; // Get the name of the user performing the update

        $data = json_decode($request->getBody()->getContents(), true);

        if (!is_array($data) || empty($data)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid or empty data provided for batch update.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $this->pdo->beginTransaction();
        $successCount = 0;
        $failCount = 0;
        $failedMarks = [];
        $notificationsToSend = []; // Array to store notifications to send after commit

        try {
            // Prepared statements for efficiency inside the loop
            $stmtCheckComponent = $this->pdo->prepare("SELECT course_id, max_mark, component_name FROM assessment_components WHERE component_id = ?");
            $stmtCheckEnrollment = $this->pdo->prepare("SELECT student_id, course_id FROM enrollments WHERE enrollment_id = ?");
            $stmtCheckMark = $this->pdo->prepare("SELECT mark_id, mark_obtained FROM student_marks WHERE enrollment_id = ? AND component_id = ?");
            $stmtInsertMark = $this->pdo->prepare("INSERT INTO student_marks (enrollment_id, component_id, mark_obtained, recorded_by) VALUES (?, ?, ?, ?)");
            $stmtUpdateMark = $this->pdo->prepare("UPDATE student_marks SET mark_obtained = ?, recorded_by = ? WHERE mark_id = ?");
            
            // For fetching student and course names for notifications
            $stmtGetStudentCourseInfo = $this->pdo->prepare("
                SELECT u.user_id AS student_id, u.full_name AS student_name, c.course_name, c.course_code
                FROM enrollments e
                JOIN users u ON e.student_id = u.user_id
                JOIN courses c ON e.course_id = c.course_id
                WHERE e.enrollment_id = ?
            ");


            foreach ($data as $markData) {
                $markObtained = $markData['mark_obtained'] ?? null;
                $enrollmentId = $markData['enrollment_id'] ?? null;
                $assessmentId = $markData['assessment_id'] ?? null;

                if (!is_numeric($markObtained) || $markObtained < 0 || empty($enrollmentId) || empty($assessmentId)) {
                    $failCount++;
                    $failedMarks[] = ['data' => $markData, 'reason' => 'Invalid data provided.'];
                    continue;
                }

                // Get component details
                $stmtCheckComponent->execute([$assessmentId]);
                $componentDetails = $stmtCheckComponent->fetch(PDO::FETCH_ASSOC);
                if (!$componentDetails || $markObtained > $componentDetails['max_mark']) {
                    $failCount++;
                    $failedMarks[] = ['data' => $markData, 'reason' => 'Invalid assessment component or mark exceeds max mark.'];
                    continue;
                }

                // Get enrollment details
                $stmtCheckEnrollment->execute([$enrollmentId]);
                $enrollmentDetails = $stmtCheckEnrollment->fetch(PDO::FETCH_ASSOC);
                if (!$enrollmentDetails || $enrollmentDetails['course_id'] !== $componentDetails['course_id']) {
                    $failCount++;
                    $failedMarks[] = ['data' => $markData, 'reason' => 'Enrollment and assessment component do not belong to the same course.'];
                    continue;
                }

                // Check authorization
                if ($userRole === 'lecturer') {
                    $stmtCheckLecturer = $this->pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_id = ? AND lecturer_id = ?");
                    $stmtCheckLecturer->execute([$enrollmentDetails['course_id'], $recordedBy]);
                    if ($stmtCheckLecturer->fetchColumn() === 0) {
                        $failCount++;
                        $failedMarks[] = ['data' => $markData, 'reason' => 'Access denied: Lecturer not assigned to this course.'];
                        continue;
                    }
                } elseif ($userRole !== 'admin') {
                    $failCount++;
                    $failedMarks[] = ['data' => $markData, 'reason' => 'Access denied: Insufficient privileges.'];
                    continue;
                }

                // Get student and course info for notification
                $stmtGetStudentCourseInfo->execute([$enrollmentId]);
                $studentCourseInfo = $stmtGetStudentCourseInfo->fetch(PDO::FETCH_ASSOC);
                
                $studentId = $studentCourseInfo['student_id'];
                $studentName = $studentCourseInfo['full_name']; // Corrected to full_name
                $courseName = $studentCourseInfo['course_name'];
                $courseCode = $studentCourseInfo['course_code'];
                $componentName = $componentDetails['component_name'];
                $maxMark = $componentDetails['max_mark'];

                // Check if a mark for this student/component already exists
                $stmtCheckMark->execute([$enrollmentId, $assessmentId]);
                $existingMarkRow = $stmtCheckMark->fetch(PDO::FETCH_ASSOC);
                $existingMarkId = $existingMarkRow['mark_id'] ?? null;
                $oldMarkObtained = $existingMarkRow['mark_obtained'] ?? null;

                if ($existingMarkId) {
                    // Mark exists, so update it
                    $stmtUpdateMark->execute([$markObtained, $recordedBy, $existingMarkId]);
                    if ((float)$markObtained !== (float)$oldMarkObtained) {
                        $notificationsToSend[] = [
                            'userId' => $studentId,
                            'title' => "Mark Updated: {$componentName} in {$courseCode}",
                            'message' => "Your mark for '{$componentName}' in '{$courseName}' has been updated from {$oldMarkObtained} to {$markObtained} by {$recorderName}.",
                            'type' => "mark_update",
                            'relatedId' => $existingMarkId
                        ];
                    }
                } else {
                    // Mark does not exist, so insert it
                    $stmtInsertMark->execute([$enrollmentId, $assessmentId, $markObtained, $recordedBy]);
                    $newMarkId = $this->pdo->lastInsertId();
                    $notificationsToSend[] = [
                        'userId' => $studentId,
                        'title' => "New Mark Recorded: {$componentName} in {$courseCode}",
                        'message' => "A new mark of {$markObtained}/{$maxMark} has been recorded for your '{$componentName}' component in '{$courseName}' by {$recorderName}.",
                        'type' => "new_mark",
                        'relatedId' => $newMarkId
                    ];
                }
                $successCount++;
            }

            $this->pdo->commit();

            // Send all collected notifications after successful commit
            foreach ($notificationsToSend as $notification) {
                $this->notificationController->createNotification(
                    $notification['userId'],
                    $notification['title'],
                    $notification['message'],
                    $notification['type'],
                    $notification['relatedId']
                );
            }

            $response->getBody()->write(json_encode([
                'message' => "Batch update completed. {$successCount} marks processed successfully. {$failCount} marks failed.",
                'failed_marks' => $failedMarks
            ]));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Batch update failed: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database transaction failed. No marks were updated.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
