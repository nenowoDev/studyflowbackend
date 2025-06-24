<?php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use PDO;
use PDOException;

class AssessmentComponentController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get all assessment components.
     * Accessible to all authenticated users.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @return Response The response object with component data or error.
     */
    public function getAllAssessmentComponents(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        $query = "SELECT ac.*, c.course_name, c.course_code, c.lecturer_id FROM assessment_components ac JOIN courses c ON ac.course_id = c.course_id";
        $params = [];

        // Lecturers can only see components for their courses
        if ($userRole === 'lecturer') {
            $query .= " WHERE c.lecturer_id = ?";
            $params[] = $userId;
        } elseif ($userRole === 'student') {
            // Students can only see components for courses they are enrolled in
            $query .= " JOIN enrollments e ON ac.course_id = e.course_id WHERE e.student_id = ?";
            $params[] = $userId;
        } elseif ($userRole !== 'admin') {
             $response->getBody()->write(json_encode(['error' => 'Access denied for this role.']));
             return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $components = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode($components));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error fetching all assessment components: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve assessment components.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Get a single assessment component by ID.
     * Accessible to lecturers (for their courses) and admin.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., component ID).
     * @return Response The response object with component data or error.
     */
    public function getAssessmentComponentById(Request $request, Response $response, array $args): Response
    {
        $componentId = $args['id'];
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        if (!is_numeric($componentId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid component ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $query = "SELECT ac.*, c.course_name, c.course_code, c.lecturer_id 
                  FROM assessment_components ac 
                  JOIN courses c ON ac.course_id = c.course_id 
                  WHERE ac.component_id = ?";
        $params = [$componentId];

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $component = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$component) {
                $response->getBody()->write(json_encode(['error' => 'Assessment component not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Authorization check
            if ($userRole === 'lecturer' && (string)$component['lecturer_id'] !== (string)$userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only view components for your courses.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole === 'student' && !$this->isStudentEnrolledInCourse($userId, $component['course_id'])) {
                 $response->getBody()->write(json_encode(['error' => 'Access denied: You can only view components for courses you are enrolled in.']));
                 return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole !== 'admin' && $userRole !== 'lecturer' && $userRole !== 'student') {
                $response->getBody()->write(json_encode(['error' => 'Access denied for this role.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode($component));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error fetching assessment component by ID {$componentId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve assessment component.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

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

        try {
            // Validate course_id exists and get its lecturer_id
            $stmtCourse = $this->pdo->prepare("SELECT lecturer_id FROM courses WHERE course_id = ?");
            $stmtCourse->execute([$data['course_id']]);
            $course = $stmtCourse->fetch(PDO::FETCH_ASSOC);

            if (!$course) {
                $response->getBody()->write(json_encode(['error' => 'Invalid course ID.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Authorization check: Admin or Lecturer assigned to this course
            if ($userRole === 'lecturer' && (string)$course['lecturer_id'] !== (string)$userId) {
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

            $response->getBody()->write(json_encode(['message' => 'Assessment component added successfully', 'component_id' => $this->pdo->lastInsertId()]));
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

        if (!is_numeric($componentId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid component ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $data = json_decode($request->getBody()->getContents(), true);

        if (empty($data)) {
            $response->getBody()->write(json_encode(['error' => 'No data provided for update.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            // First, fetch the component to check its course's lecturer_id for authorization
            $stmtComponent = $this->pdo->prepare("SELECT ac.course_id, c.lecturer_id FROM assessment_components ac JOIN courses c ON ac.course_id = c.course_id WHERE ac.component_id = ?");
            $stmtComponent->execute([$componentId]);
            $component = $stmtComponent->fetch(PDO::FETCH_ASSOC);

            if (!$component) {
                $response->getBody()->write(json_encode(['error' => 'Assessment component not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Authorization check
            if ($userRole === 'lecturer' && (string)$component['lecturer_id'] !== (string)$userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only update components for your courses.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole !== 'admin' && $userRole !== 'lecturer') {
                $response->getBody()->write(json_encode(['error' => 'Access denied: Only admins and lecturers can update assessment components.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $setClauses = [];
            $params = [];

            if (isset($data['component_name'])) {
                $setClauses[] = 'component_name = ?';
                $params[] = $data['component_name'];
            }
            if (isset($data['max_mark'])) {
                if (!is_numeric($data['max_mark']) || $data['max_mark'] <= 0) {
                    $response->getBody()->write(json_encode(['error' => 'Max mark must be a positive number.']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
                $setClauses[] = 'max_mark = ?';
                $params[] = $data['max_mark'];
            }
            if (isset($data['weight_percentage'])) {
                if (!is_numeric($data['weight_percentage']) || $data['weight_percentage'] < 0 || $data['weight_percentage'] > 100) {
                    $response->getBody()->write(json_encode(['error' => 'Weight percentage must be between 0 and 100.']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
                $setClauses[] = 'weight_percentage = ?';
                $params[] = $data['weight_percentage'];
            }
            if (isset($data['is_final_exam'])) {
                $setClauses[] = 'is_final_exam = ?';
                $params[] = (bool)$data['is_final_exam'];
            }

            if (empty($setClauses)) {
                $response->getBody()->write(json_encode(['error' => 'No valid fields provided for update.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $params[] = $componentId; // Add the component ID for the WHERE clause

            $query = "UPDATE assessment_components SET " . implode(', ', $setClauses) . " WHERE component_id = ?";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['message' => 'Assessment component updated successfully (or no changes made).']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Assessment component updated successfully']));
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

        if (!is_numeric($componentId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid component ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            // First, fetch the component to check its course's lecturer_id for authorization
            $stmtComponent = $this->pdo->prepare("SELECT ac.course_id, c.lecturer_id FROM assessment_components ac JOIN courses c ON ac.course_id = c.course_id WHERE ac.component_id = ?");
            $stmtComponent->execute([$componentId]);
            $component = $stmtComponent->fetch(PDO::FETCH_ASSOC);

            if (!$component) {
                $response->getBody()->write(json_encode(['error' => 'Assessment component not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Authorization check
            if ($userRole === 'lecturer' && (string)$component['lecturer_id'] !== (string)$userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only delete components from courses you are assigned to.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole !== 'admin' && $userRole !== 'lecturer') {
                $response->getBody()->write(json_encode(['error' => 'Access denied: Only admins and lecturers can delete assessment components.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $stmt = $this->pdo->prepare("DELETE FROM assessment_components WHERE component_id = ?");
            $stmt->execute([$componentId]);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Assessment component not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Assessment component deleted successfully']));
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
