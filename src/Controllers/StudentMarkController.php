<?php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use PDO;
use PDOException;

class StudentMarkController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
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
        $currentUser = $request->getAttribute('user'); // Assuming middleware sets this
        $userRole = $currentUser['role'] ?? 'student';
        $currentStudentId = $currentUser['user_id'] ?? null;
        
        $query = "SELECT sm.*, 
                         u.user_id as student_id,
                         u.full_name AS student_name, 
                         c.course_name, 
                         ac.component_name, 
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
                    unset($mark['student_id']);
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

        $query = "SELECT sm.*, u.full_name AS student_name, c.course_name, ac.component_name, recorded_by_user.full_name AS recorded_by_name
                  FROM student_marks sm
                  JOIN enrollments e ON sm.enrollment_id = e.enrollment_id
                  JOIN users u ON e.student_id = u.user_id
                  JOIN courses c ON e.course_id = c.course_id
                  JOIN assessment_components ac ON sm.component_id = ac.component_id
                  JOIN users recorded_by_user ON sm.recorded_by = recorded_by_user.user_id";
        $params = [];

        if ($userRole === 'student') {
            $query .= " WHERE e.student_id = ?";
            $params[] = $userId;
        } elseif ($userRole === 'lecturer') {
            $query .= " WHERE c.lecturer_id = ?";
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

        $query = "SELECT sm.*, e.student_id, c.lecturer_id, u.full_name AS student_name, c.course_name, ac.component_name, recorded_by_user.full_name AS recorded_by_name
                  FROM student_marks sm
                  JOIN enrollments e ON sm.enrollment_id = e.enrollment_id
                  JOIN users u ON e.student_id = u.user_id
                  JOIN courses c ON e.course_id = c.course_id
                  JOIN assessment_components ac ON sm.component_id = ac.component_id
                  JOIN users recorded_by_user ON sm.recorded_by = recorded_by_user.user_id
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
            } elseif ($userRole !== 'admin' && $userRole !== 'student' && $userRole !== 'lecturer') {
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
            // Validate enrollment and component, and check lecturer's authority over the course
            $stmtCheck = $this->pdo->prepare("
                SELECT 
                    e.student_id, 
                    ac.course_id, 
                    c.lecturer_id, 
                    ac.max_mark
                FROM enrollments e
                JOIN assessment_components ac ON ac.course_id = e.course_id
                JOIN courses c ON e.course_id = c.course_id
                WHERE e.enrollment_id = ? AND ac.component_id = ?
            ");
            $stmtCheck->execute([$data['enrollment_id'], $data['component_id']]);
            $checkResult = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if (!$checkResult) {
                $response->getBody()->write(json_encode(['error' => 'Invalid enrollment ID or component ID for this course.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Authorization check: Admin or Lecturer assigned to this course
            if ($userRole === 'lecturer' && (string)$checkResult['lecturer_id'] !== (string)$recordedBy) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only record marks for your assigned courses.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole !== 'admin' && $userRole !== 'lecturer') {
                $response->getBody()->write(json_encode(['error' => 'Access denied: Only admins and lecturers can add student marks.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            // Check if mark_obtained exceeds max_mark for the component
            if ($data['mark_obtained'] > $checkResult['max_mark']) {
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

            $response->getBody()->write(json_encode(['message' => 'Student mark added successfully', 'mark_id' => $this->pdo->lastInsertId()]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
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
            // Fetch mark details to check authorization and max_mark
            $stmtMark = $this->pdo->prepare("
                SELECT 
                    sm.enrollment_id, 
                    sm.component_id, 
                    e.student_id, 
                    ac.course_id, 
                    c.lecturer_id, 
                    ac.max_mark
                FROM student_marks sm
                JOIN enrollments e ON sm.enrollment_id = e.enrollment_id
                JOIN assessment_components ac ON sm.component_id = ac.component_id
                JOIN courses c ON e.course_id = c.course_id
                WHERE sm.mark_id = ?
            ");
            $stmtMark->execute([$markId]);
            $existingMark = $stmtMark->fetch(PDO::FETCH_ASSOC);

            if (!$existingMark) {
                $response->getBody()->write(json_encode(['error' => 'Student mark not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Authorization check
            if ($userRole === 'lecturer' && (string)$existingMark['lecturer_id'] !== (string)$recordedBy) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only update marks for your assigned courses.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole !== 'admin' && $userRole !== 'lecturer') {
                $response->getBody()->write(json_encode(['error' => 'Access denied: Only admins and lecturers can update student marks.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $setClauses = [];
            $params = [];

            if (isset($data['mark_obtained'])) {
                if (!is_numeric($data['mark_obtained']) || $data['mark_obtained'] < 0) {
                    $response->getBody()->write(json_encode(['error' => 'Mark obtained must be a non-negative number.']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
                // Validate against max_mark
                if ($data['mark_obtained'] > $existingMark['max_mark']) {
                    $response->getBody()->write(json_encode(['error' => "Mark obtained ({$data['mark_obtained']}) exceeds the maximum mark allowed ({$existingMark['max_mark']}) for this component."]));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
                $setClauses[] = 'mark_obtained = ?';
                $params[] = $data['mark_obtained'];
            }

            // Allow changing enrollment_id or component_id (though rare for existing marks)
            if (isset($data['enrollment_id'])) {
                // Re-validate enrollment_id to ensure it's valid
                $stmtEnrollment = $this->pdo->prepare("SELECT enrollment_id FROM enrollments WHERE enrollment_id = ?");
                $stmtEnrollment->execute([$data['enrollment_id']]);
                if (!$stmtEnrollment->fetch()) {
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
                    $response->getBody()->write(json_encode(['error' => 'Invalid new component ID.']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
                $setClauses[] = 'component_id = ?';
                $params[] = $data['component_id'];
            }

            if (empty($setClauses)) {
                $response->getBody()->write(json_encode(['error' => 'No valid fields provided for update.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $params[] = $markId; // Add the mark ID for the WHERE clause

            $query = "UPDATE student_marks SET " . implode(', ', $setClauses) . " WHERE mark_id = ?";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['message' => 'Student mark updated successfully (or no changes made).']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Student mark updated successfully']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
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
            // Fetch mark details to check authorization
            $stmtMark = $this->pdo->prepare("
                SELECT sm.mark_id, c.lecturer_id
                FROM student_marks sm
                JOIN enrollments e ON sm.enrollment_id = e.enrollment_id
                JOIN courses c ON e.course_id = c.course_id
                WHERE sm.mark_id = ?
            ");
            $stmtMark->execute([$markId]);
            $existingMark = $stmtMark->fetch(PDO::FETCH_ASSOC);

            if (!$existingMark) {
                $response->getBody()->write(json_encode(['error' => 'Student mark not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Authorization check
            if ($userRole === 'lecturer' && (string)$existingMark['lecturer_id'] !== (string)$userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only delete marks for your assigned courses.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole !== 'admin' && $userRole !== 'lecturer') {
                $response->getBody()->write(json_encode(['error' => 'Access denied: Only admins and lecturers can delete student marks.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $stmt = $this->pdo->prepare("DELETE FROM student_marks WHERE mark_id = ?");
            $stmt->execute([$markId]);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Student mark not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Student mark deleted successfully']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error deleting student mark ID {$markId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not delete student mark.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    // Inside your StudentMarkController.php (or similar file handling student marks)

// Example endpoint handler for GET /student-marks/{studentId}
// Inside your StudentMarkController.php
// Inside your StudentMarkController.php
public function getStudentMarksByStudentId(Request $request, Response $response, array $args): Response
    {
        // CORRECTED LINE: Access 'id' from $args, not 'studentId'
        $studentId = $args['id']; 
        error_log("Backend Debug: Received studentId for marks: " . $studentId);

        try {
            // Step 1: Get all enrollment_ids for this student
            $stmtEnrollments = $this->pdo->prepare("SELECT enrollment_id FROM enrollments WHERE student_id = ?");
            $stmtEnrollments->execute([$studentId]);
            $enrollmentIds = $stmtEnrollments->fetchAll(PDO::FETCH_COLUMN);
            
            error_log("Backend Debug: Enrollment IDs found for student " . $studentId . ": " . print_r($enrollmentIds, true));

            if (empty($enrollmentIds)) {
                error_log("Backend Debug: No enrollment IDs found for student " . $studentId . ". Returning empty marks array.");
                $response->getBody()->write(json_encode([]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Step 2: Prepare the IN clause for the main query
            $inClausePlaceholders = implode(',', array_fill(0, count($enrollmentIds), '?'));
            
            // Step 3: Fetch marks using the obtained enrollment IDs
            $query = "
                SELECT sm.mark_id, sm.enrollment_id, sm.component_id, sm.mark_obtained, sm.recorded_by,
                       ac.max_mark, ac.weight_percentage
                FROM student_marks sm
                JOIN assessment_components ac ON sm.component_id = ac.component_id
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
     * Get student marks for a specific course and assessment component.
     * Accessible to lecturers of that course or admin.
     * Includes all students enrolled in the course, even if they don't have a mark yet.
     * Endpoint: GET /student-marks/course/{course_id}/assessment/{assessment_id}
     */
    public function getStudentMarksByCourseAndAssessment(Request $request, Response $response, array $args): Response
    {
        $courseId = $args['course_id'];
        $assessmentId = $args['assessment_id'];
        $jwt = $request->getAttribute('jwt');
        $requesterRole = $jwt->role ?? null;
        $lecturerId = $jwt->user_id ?? null;

        if (!is_numeric($courseId) || !is_numeric($assessmentId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid course ID or assessment ID.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            // Authorization check: Admin can view any, Lecturer must teach the course
            if ($requesterRole === 'lecturer') {
                $stmtCourse = $this->pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_id = ? AND lecturer_id = ?");
                $stmtCourse->execute([$courseId, $lecturerId]);
                if ($stmtCourse->fetchColumn() == 0) {
                    $response->getBody()->write(json_encode(['error' => 'Access denied: You do not teach this course.']));
                    return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
                }
            } elseif ($requesterRole !== 'admin') {
                $response->getBody()->write(json_encode(['error' => 'Access denied: Insufficient privileges.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            // Fetch all students enrolled in the course, and their marks for the specific assessment if they exist
            $stmt = $this->pdo->prepare("
                SELECT 
                    u.user_id AS student_id, 
                    u.full_name, 
                    u.matric_number,
                    sm.mark_obtained AS mark,  
                    sm.mark_id
                FROM users u
                JOIN enrollments e ON u.user_id = e.student_id
                LEFT JOIN student_marks sm ON e.enrollment_id = sm.enrollment_id 
                    AND sm.component_id = ?
                WHERE e.course_id = ? AND u.role = 'student'
                ORDER BY u.full_name
            ");
            
            $stmt->execute([$assessmentId, $courseId]);  // Two parameters: $assessmentId and $courseId
            $studentMarks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            

            if (!$studentMarks) {
                $studentMarks = [];
            }

            $response->getBody()->write(json_encode(['studentMarks' => $studentMarks]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (PDOException $e) {
            error_log("Error fetching student marks for course {$courseId}, assessment {$assessmentId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to fetch student marks.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Batch update or insert student marks.
     * Accessible to lecturers of the course or admin.
     * Endpoint: POST /student-marks/batch-update
     * Payload: { marks: [{ student_id, assessment_component_id, course_id, mark, student_mark_id (optional) }] }
     */
    public function batchUpdateStudentMarks(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        $marks = $data['marks'] ?? [];

        if (empty($marks) || !is_array($marks)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid or empty marks array provided.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $jwt = $request->getAttribute('jwt');
        $requesterRole = $jwt->role ?? null;
        $lecturerId = $jwt->user_id ?? null;

        try {
            $this->pdo->beginTransaction();
            $updatedCount = 0;
            $insertedCount = 0;
            $errors = [];

            foreach ($marks as $markData) {
                $studentId = $markData['student_id'] ?? null;
                $assessmentComponentId = $markData['assessment_component_id'] ?? null;
                $courseId = $markData['course_id'] ?? null;
                $mark = $markData['mark'] ?? null;
                $studentMarkId = $markData['student_mark_id'] ?? null;

                // Basic validation for each mark entry
                if (!is_numeric($studentId) || !is_numeric($assessmentComponentId) || !is_numeric($courseId) || (!is_numeric($mark) && $mark !== null)) {
                    $errors[] = "Invalid data for student ID {$studentId}, assessment ID {$assessmentComponentId}.";
                    continue;
                }

                // Authorization check for each mark: Lecturer must teach the course
                if ($requesterRole === 'lecturer') {
                    $stmtCourse = $this->pdo->prepare("SELECT COUNT(*) FROM courses WHERE course_id = ? AND lecturer_id = ?");
                    $stmtCourse->execute([$courseId, $lecturerId]);
                    if ($stmtCourse->fetchColumn() == 0) {
                        $errors[] = "Access denied for course {$courseId}: You do not teach this course.";
                        continue; // Skip this mark if lecturer doesn't teach the course
                    }
                } elseif ($requesterRole !== 'admin') {
                    $errors[] = "Access denied: Insufficient privileges to update marks.";
                    continue;
                }

                // Check if a mark already exists for this student, course, and assessment component
                $stmtCheck = $this->pdo->prepare("SELECT student_mark_id FROM student_marks WHERE student_id = ? AND assessment_component_id = ? AND course_id = ?");
                $stmtCheck->execute([$studentId, $assessmentComponentId, $courseId]);
                $existingMark = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($existingMark) {
                    // Update existing mark
                    $stmtUpdate = $this->pdo->prepare("UPDATE student_marks SET mark = ? WHERE student_mark_id = ?");
                    $stmtUpdate->execute([$mark, $existingMark['student_mark_id']]);
                    $updatedCount++;
                } else {
                    // Insert new mark
                    $stmtInsert = $this->pdo->prepare("INSERT INTO student_marks (student_id, assessment_component_id, course_id, mark) VALUES (?, ?, ?, ?)");
                    $stmtInsert->execute([$studentId, $assessmentComponentId, $courseId, $mark]);
                    $insertedCount++;
                }
            }

            $this->pdo->commit();
            $response->getBody()->write(json_encode([
                'message' => "Grades updated successfully. Inserted: {$insertedCount}, Updated: {$updatedCount}.",
                'errors' => $errors
            ]));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');

        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error batch updating student marks: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Failed to save grades due to a database error.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
