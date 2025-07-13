<?php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use PDO;
use PDOException;

class AssessmentComponentController
{
    private PDO $pdo;
    private NotificationController $notificationController; // Add this property

    public function __construct(PDO $pdo, NotificationController $notificationController) // Inject it here
    {
        $this->pdo = $pdo;
        $this->notificationController = $notificationController;
    }

    // ... (Your existing getAllAssessmentComponents, getAssessmentComponentById methods) ...

    /**
     * Add a new assessment component.
     * Accessible to lecturers (for their courses) and admin.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @return Response The response object with success message or error.
     */
    public function addAssessmentComponent(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;
        $userName = $jwt->full_name ?? $jwt->username ?? 'An Administrator/Lecturer';

        $data = json_decode($request->getBody()->getContents(), true);

        // Basic validation for required fields
        if (empty($data['course_id']) || empty($data['component_name']) || !isset($data['max_mark']) || !isset($data['weight_percentage'])) {
            $response->getBody()->write(json_encode(['error' => 'Course ID, component name, max mark, and weight percentage are required.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (!is_numeric($data['max_mark']) || $data['max_mark'] <= 0) {
            $response->getBody()->write(json_encode(['error' => 'Max mark must be a positive number.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        if (!is_numeric($data['weight_percentage']) || $data['weight_percentage'] < 0 || $data['weight_percentage'] > 100) {
            $response->getBody()->write(json_encode(['error' => 'Weight percentage must be between 0 and 100.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Initialize variables for notifications
        $courseName = '';
        $courseCode = '';
        $lecturerId = null;

        try {
            // Validate course_id exists and get its details (lecturer_id, name, code)
            $stmtCourse = $this->pdo->prepare("SELECT course_name, course_code, lecturer_id FROM courses WHERE course_id = ?");
            $stmtCourse->execute([$data['course_id']]);
            $course = $stmtCourse->fetch(PDO::FETCH_ASSOC);

            if (!$course) {
                $response->getBody()->write(json_encode(['error' => 'Invalid course ID.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $courseName = $course['course_name'];
            $courseCode = $course['course_code'];
            $lecturerId = $course['lecturer_id'];

            // Authorization check: Admin or Lecturer assigned to this course
            if ($userRole === 'lecturer' && (string)$lecturerId !== (string)$userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only add components to courses you are assigned to.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole !== 'admin' && $userRole !== 'lecturer') {
                $response->getBody()->write(json_encode(['error' => 'Access denied: Only admins and lecturers can add assessment components.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $stmt = $this->pdo->prepare("INSERT INTO assessment_components (course_id, component_name, max_mark, weight_percentage, is_final_exam) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $data['course_id'],
                $data['component_name'],
                $data['max_mark'],
                $data['weight_percentage'],
                $data['is_final_exam'] ?? false // Default to false if not provided
            ]);

            $newComponentId = $this->pdo->lastInsertId();
            $componentName = $data['component_name'];
            $maxMark = $data['max_mark'];
            $weightPercentage = $data['weight_percentage'];

            // --- Send Notifications ---

            // 1. Notify the Lecturer of the Course
            $this->notificationController->createNotification(
                $lecturerId,
                "New Assessment Component Added: {$courseCode}",
                "{$userName} has added a new assessment component '{$componentName}' ({$weightPercentage}%, Max: {$maxMark}) to your course **{$courseName} ({$courseCode})**.",
                "new_assessment_component",
                $newComponentId
            );

            // 2. Notify all Students enrolled in the course
            $stmtEnrolledStudents = $this->pdo->prepare("SELECT student_id FROM enrollments WHERE course_id = ?");
            $stmtEnrolledStudents->execute([$data['course_id']]);
            $enrolledStudentIds = $stmtEnrolledStudents->fetchAll(PDO::FETCH_COLUMN);

            $studentNotificationMessage = "A new assessment component '{$componentName}' ({$weightPercentage}%, Max: {$maxMark}) has been added to **{$courseName} ({$courseCode})**. Please check the updated assessment breakdown.";
            foreach ($enrolledStudentIds as $student_id) {
                $this->notificationController->createNotification(
                    $student_id,
                    "New Assessment Component: {$courseCode}",
                    $studentNotificationMessage,
                    "new_assessment_component",
                    $newComponentId
                );
            }


            $response->getBody()->write(json_encode(['message' => 'Assessment component added successfully and notifications sent.', 'component_id' => $newComponentId]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error adding assessment component: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not add assessment component.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Update an existing assessment component.
     * Accessible to lecturers (for their courses) and admin.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., component ID).
     * @return Response The response object with success message or error.
     */
    public function updateAssessmentComponent(Request $request, Response $response, array $args): Response
    {
        $componentId = $args['id'];
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;
        $userName = $jwt->full_name ?? $jwt->username ?? 'An Administrator/Lecturer';


        if (!is_numeric($componentId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid component ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $data = json_decode($request->getBody()->getContents(), true);

        if (empty($data)) {
            $response->getBody()->write(json_encode(['message' => 'No data provided for update.']));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json'); // Return 200 if nothing to update
        }

        // --- 1. Fetch ORIGINAL component details, course details, and lecturer ID ---
        $originalComponent = null;
        $courseId = null;
        $courseName = '';
        $courseCode = '';
        $lecturerId = null;

        try {
            $stmtComponent = $this->pdo->prepare("
                SELECT ac.*, c.course_name, c.course_code, c.lecturer_id
                FROM assessment_components ac
                JOIN courses c ON ac.course_id = c.course_id
                WHERE ac.component_id = ?
            ");
            $stmtComponent->execute([$componentId]);
            $originalComponent = $stmtComponent->fetch(PDO::FETCH_ASSOC);

            if (!$originalComponent) {
                $response->getBody()->write(json_encode(['error' => 'Assessment component not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $courseId = $originalComponent['course_id'];
            $courseName = $originalComponent['course_name'];
            $courseCode = $originalComponent['course_code'];
            $lecturerId = $originalComponent['lecturer_id'];


            // Authorization check
            if ($userRole === 'lecturer' && (string)$lecturerId !== (string)$userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only update components for your courses.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole !== 'admin' && $userRole !== 'lecturer') {
                $response->getBody()->write(json_encode(['error' => 'Access denied: Only admins and lecturers can update assessment components.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

        } catch (PDOException $e) {
            error_log("Error fetching original assessment component ID {$componentId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve assessment component details.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        // --- 2. Prepare update clauses ---
        $setClauses = [];
        $params = [];
        $changesMade = []; // To track what actually changed for notification message

        $fieldsToCheck = ['component_name', 'max_mark', 'weight_percentage', 'is_final_exam'];
        foreach ($fieldsToCheck as $field) {
            if (isset($data[$field]) && $data[$field] !== $originalComponent[$field]) {
                if ($field === 'max_mark' && (!is_numeric($data[$field]) || $data[$field] <= 0)) {
                    $response->getBody()->write(json_encode(['error' => 'Max mark must be a positive number.']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
                if ($field === 'weight_percentage' && (!is_numeric($data[$field]) || $data[$field] < 0 || $data[$field] > 100)) {
                    $response->getBody()->write(json_encode(['error' => 'Weight percentage must be between 0 and 100.']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
                $setClauses[] = "{$field} = ?";
                $params[] = ($field === 'is_final_exam') ? (bool)$data[$field] : $data[$field];
                $changesMade[] = ucfirst(str_replace('_', ' ', $field)); // For notification message
            }
        }

        if (empty($setClauses)) {
            $response->getBody()->write(json_encode(['message' => 'No valid fields provided for update or no changes detected.']));
            return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
        }

        $params[] = $componentId; // Add the component ID for the WHERE clause
        $query = "UPDATE assessment_components SET " . implode(', ', $setClauses) . " WHERE component_id = ?";

        // --- 3. Perform the update ---
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['message' => 'Assessment component updated successfully (no changes applied as data was identical).']));
                return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
            }

            // --- 4. Send Notifications ---

            // 1. Notify the Lecturer of the Course (if they are not the one updating, or if it's an admin)
            if ((string)$lecturerId !== (string)$userId || $userRole === 'admin') {
                $lecturerNotificationMessage = "{$userName} has updated the assessment component '{$originalComponent['component_name']}' for your course **{$courseName} ({$courseCode})**. ";
                if (!empty($changesMade)) {
                    $lecturerNotificationMessage .= "Changes include: " . implode(', ', $changesMade) . ".";
                }
                $this->notificationController->createNotification(
                    $lecturerId,
                    "Assessment Component Updated: {$courseCode}",
                    $lecturerNotificationMessage,
                    "assessment_component_update",
                    $componentId
                );
            }

            // 2. Notify all Students enrolled in the course
            $stmtEnrolledStudents = $this->pdo->prepare("SELECT student_id FROM enrollments WHERE course_id = ?");
            $stmtEnrolledStudents->execute([$courseId]);
            $enrolledStudentIds = $stmtEnrolledStudents->fetchAll(PDO::FETCH_COLUMN);

            $studentNotificationMessage = "An assessment component for **{$courseName} ({$courseCode})** has been updated by {$userName}. ";
            if (!empty($changesMade)) {
                $studentNotificationMessage .= "The '{$originalComponent['component_name']}' component has changes to its " . implode(', ', $changesMade) . ".";
            }
            $studentNotificationMessage .= " Please review the updated assessment details.";

            foreach ($enrolledStudentIds as $student_id) {
                $this->notificationController->createNotification(
                    $student_id,
                    "Assessment Component Updated: {$courseCode}",
                    $studentNotificationMessage,
                    "assessment_component_update",
                    $componentId
                );
            }

            $response->getBody()->write(json_encode(['message' => 'Assessment component updated successfully and notifications sent.']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error updating assessment component ID {$componentId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not update assessment component.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Delete an assessment component.
     * Accessible to lecturers (for their courses) and admin.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., component ID).
     * @return Response The response object with success message or error.
     */
    public function deleteAssessmentComponent(Request $request, Response $response, array $args): Response
    {
        $componentId = $args['id'];
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;
        $userName = $jwt->full_name ?? $jwt->username ?? 'An Administrator/Lecturer';

        if (!is_numeric($componentId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid component ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // --- 1. Fetch component details, course details, and lecturer ID BEFORE deletion ---
        $componentName = '';
        $courseId = null;
        $courseName = '';
        $courseCode = '';
        $lecturerId = null;

        try {
            $stmtComponent = $this->pdo->prepare("
                SELECT ac.component_name, ac.course_id, c.course_name, c.course_code, c.lecturer_id
                FROM assessment_components ac
                JOIN courses c ON ac.course_id = c.course_id
                WHERE ac.component_id = ?
            ");
            $stmtComponent->execute([$componentId]);
            $componentDetails = $stmtComponent->fetch(PDO::FETCH_ASSOC);

            if (!$componentDetails) {
                $response->getBody()->write(json_encode(['error' => 'Assessment component not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $componentName = $componentDetails['component_name'];
            $courseId = $componentDetails['course_id'];
            $courseName = $componentDetails['course_name'];
            $courseCode = $componentDetails['course_code'];
            $lecturerId = $componentDetails['lecturer_id'];

            // Authorization check
            if ($userRole === 'lecturer' && (string)$lecturerId !== (string)$userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only delete components from courses you are assigned to.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole !== 'admin' && $userRole !== 'lecturer') {
                $response->getBody()->write(json_encode(['error' => 'Access denied: Only admins and lecturers can delete assessment components.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            // Get all students enrolled in this course for notifications
            $stmtEnrolledStudents = $this->pdo->prepare("SELECT student_id FROM enrollments WHERE course_id = ?");
            $stmtEnrolledStudents->execute([$courseId]);
            $enrolledStudentIds = $stmtEnrolledStudents->fetchAll(PDO::FETCH_COLUMN);

        } catch (PDOException $e) {
            error_log("Error fetching assessment component details for deletion (ID: {$componentId}): " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve assessment component details for deletion.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        // --- 2. Perform the deletion ---
        try {
            $stmt = $this->pdo->prepare("DELETE FROM assessment_components WHERE component_id = ?");
            $stmt->execute([$componentId]);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Assessment component not found or already deleted.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // --- 3. Send Notifications ---

            // 1. Notify the Lecturer of the Course
            $this->notificationController->createNotification(
                $lecturerId,
                "Assessment Component Deleted: {$courseCode}",
                "{$userName} has deleted the assessment component '{$componentName}' from your course **{$courseName} ({$courseCode})**.",
                "assessment_component_deleted",
                $componentId
            );

            
            $studentDeletionMessage = "The assessment component '{$componentName}' for **{$courseName} ({$courseCode})** has been deleted by {$userName}. Please check the updated assessment breakdown.";
            foreach ($enrolledStudentIds as $student_id) {
                $this->notificationController->createNotification(
                    $student_id,
                    "Assessment Component Deleted: {$courseCode}",
                    $studentDeletionMessage,
                    "assessment_component_deleted",
                    $componentId
                );
            }

            $response->getBody()->write(json_encode(['message' => 'Assessment component deleted successfully and notifications sent.']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error deleting assessment component ID {$componentId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not delete assessment component.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Helper function to check if a student is enrolled in a given course.
     * This is used for student access control to assessment components and marks.
     *
     * @param int $studentId The ID of the student.
     * @param int $courseId The ID of the course.
     * @return bool True if the student is enrolled, false otherwise.
     */
    private function isStudentEnrolledInCourse(int $studentId, int $courseId): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND course_id = ?");
            $stmt->execute([$studentId, $courseId]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Error checking student enrollment: " . $e->getMessage());
            return false;
        }
    }
}