<?php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use PDO;
use PDOException;

class UserController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get all users.
     * Accessible to admin users only.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @return Response The response object with user data or error.
     */
    public function getAllUsers(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');

        // Check if the JWT token has an 'admin' role
        if (!isset($jwt->role) || $jwt->role !== 'admin') {
            $response->getBody()->write(json_encode(['error' => 'Access denied: admin only']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        try {
            $stmt = $this->pdo->query("SELECT user_id, username, role, email, full_name, matric_number, pin FROM users");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode($users));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error fetching all users: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve users.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Get a single user by ID.
     * Admin can view any user; other roles can only view themselves.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., user ID).
     * @return Response The response object with user data or error.
     */
    public function getUserById(Request $request, Response $response, array $args): Response
    {
        $userId = $args['id'];
        $jwt = $request->getAttribute('jwt');

        // Ensure the ID is numeric
        if (!is_numeric($userId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid user ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Authorization check: Admin can view any user, others can only view their own profile.
        if (!isset($jwt->role) || ($jwt->role !== 'admin' && (string) $userId !== (string) $jwt->user_id)) {
            $response->getBody()->write(json_encode(['error' => 'Access denied: You can only view your own profile.']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        try {
            $stmt = $this->pdo->prepare("SELECT user_id, username, role, email, full_name, matric_number, pin FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $response->getBody()->write(json_encode(['error' => 'User not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode($user));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error fetching user by ID {$userId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve user.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Add a new user.
     * Accessible to admin users only.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @return Response The response object with success message or error.
     */
    public function addUser(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');

        if (!isset($jwt->role) || $jwt->role !== 'admin') {
            $response->getBody()->write(json_encode(['error' => 'Access denied: admin only']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        $data = json_decode($request->getBody()->getContents(), true);

        // Basic validation for required fields
        if (empty($data['username']) || empty($data['password']) || empty($data['role']) || empty($data['full_name'])) {
            $response->getBody()->write(json_encode(['error' => 'Username, password, role, and full_name are required.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Validate role enum
        $allowedRoles = ['lecturer', 'student', 'advisor', 'admin'];
        if (!in_array($data['role'], $allowedRoles)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid role specified.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Hash the password for security (recommended practice)
        // $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        // Using plain password as per original ProductController example, but hashing is crucial for production.
        $passwordHash = $data['password']; // IMPORTANT: Replace with password_hash() in production

        try {
            $stmt = $this->pdo->prepare("INSERT INTO users (username, password_hash, role, email, full_name, matric_number, pin, profile_picture) VALUES (?, ?, ?, ?, ?, ? ,? ,?)");
            $stmt->execute([
                $data['username'],
                $passwordHash,
                $data['role'],
                $data['email'] ?? null,
                $data['full_name'],
                $data['matric_number'] ?? null,
                $data['pin'] ?? null,
                $data['profile_picture'] ?? null
            ]);

            $response->getBody()->write(json_encode(['message' => 'User added successfully', 'user_id' => $this->pdo->lastInsertId()]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            // Check for unique constraint violation (username or email/matric_number if unique)
            if ($e->getCode() == '23000') { // SQLSTATE for Integrity Constraint Violation
                $errorMessage = 'A user with this username, email, or matric number already exists.';
            } else {
                $errorMessage = 'Database error: Could not add user.';
            }
            error_log("Error adding user: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $errorMessage]));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json'); // 409 Conflict
        }
    }

    /**
     * Update an existing user.
     * Accessible to admin users only.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., user ID).
     * @return Response The response object with success message or error.
     */
    public function updateUser(Request $request, Response $response, array $args): Response
    {
        $userId = $args['id'];
        $jwt = $request->getAttribute('jwt');

        if (!isset($jwt->role) || $jwt->role !== 'admin' || 'lecturer') {
            $response->getBody()->write(json_encode(['error' => 'Access denied: admin only']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        if (!is_numeric($userId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid user ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $data = json_decode($request->getBody()->getContents(), true);

        if (empty($data)) {
            $response->getBody()->write(json_encode(['error' => 'No data provided for update.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Build the update query dynamically based on provided fields
        $setClauses = [];
        $params = [];

        if (isset($data['username'])) {
            $setClauses[] = 'username = ?';
            $params[] = $data['username'];
        }
        if (isset($data['password'])) {
            // $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
            $passwordHash = $data['password']; // IMPORTANT: Replace with password_hash() in production
            $setClauses[] = 'password_hash = ?';
            $params[] = $passwordHash;
        }
        if (isset($data['role'])) {
            $allowedRoles = ['lecturer', 'student', 'advisor', 'admin'];
            if (!in_array($data['role'], $allowedRoles)) {
                $response->getBody()->write(json_encode(['error' => 'Invalid role specified.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            $setClauses[] = 'role = ?';
            $params[] = $data['role'];
        }
        if (isset($data['email'])) {
            $setClauses[] = 'email = ?';
            $params[] = $data['email'];
        }
        if (isset($data['full_name'])) {
            $setClauses[] = 'full_name = ?';
            $params[] = $data['full_name'];
        }
        if (isset($data['matric_number'])) {
            $setClauses[] = 'matric_number = ?';
            $params[] = $data['matric_number'];
        }
        if (isset($data['pin'])) {
            $setClauses[] = 'pin = ?';
            $params[] = $data['pin'];
        }
        if (isset($data['profile_picture'])) {
            $setClauses[] = 'profile_picture = ?';
            $params[] = $data['profile_picture'];
        }

        if (empty($setClauses)) {
            $response->getBody()->write(json_encode(['error' => 'No valid fields provided for update.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $params[] = $userId; // Add the user ID for the WHERE clause

        $query = "UPDATE users SET " . implode(', ', $setClauses) . " WHERE user_id = ?";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'User not found or no changes made.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'User updated successfully']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $errorMessage = 'A user with this username, email, or matric number already exists.';
            } else {
                $errorMessage = 'Database error: Could not update user.';
            }
            error_log("Error updating user ID {$userId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => $errorMessage]));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Delete a user.
     * Accessible to admin users only.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., user ID).
     * @return Response The response object with success message or error.
     */
    public function deleteUser(Request $request, Response $response, array $args): Response
    {
        $userId = $args['id'];
        $jwt = $request->getAttribute('jwt');

        if (!isset($jwt->role) || $jwt->role !== 'admin') {
            $response->getBody()->write(json_encode(['error' => 'Access denied: admin only']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        if (!is_numeric($userId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid user ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'User not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'User deleted successfully']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error deleting user ID {$userId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not delete user.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
    /**
 * Get students assigned to a specific lecturer.
 *
 * @param Request $request The request object.
 * @param Response $response The response object.
 * @param array $args Route arguments (e.g., username).
 * @return Response The response object with student data or error.
 */
public function getStudentsByLecturer(Request $request, Response $response, array $args): Response
{
    $username = $args['username']; // Get lecturer username
    try {
        // Query to get the lecturer's user ID based on their username
        $stmt = $this->pdo->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $lecturer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lecturer) {
            $response->getBody()->write(json_encode(['error' => 'Lecturer not found.']));
            return $response->withStatus(404);
        }

        $lecturerId = $lecturer['user_id'];

        // Query to fetch all students who are enrolled in courses taught by this lecturer
        $stmt = $this->pdo->prepare("
    SELECT u.user_id, u.username, u.full_name, u.matric_number, c.course_code
    FROM users u
    JOIN enrollments e ON u.user_id = e.student_id
    JOIN courses c ON e.course_id = c.course_id
    WHERE c.lecturer_id = ? AND u.role = 'student'
");
        $stmt->execute([$lecturerId]);

        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$students) {
            $response->getBody()->write(json_encode(['error' => 'No students found for this lecturer.']));
            return $response->withStatus(404);
        }

        $response->getBody()->write(json_encode(['students' => $students]));
        return $response->withHeader('Content-Type', 'application/json');

    } catch (PDOException $e) {
        error_log("Error fetching students for lecturer {$username}: " . $e->getMessage());
        $response->getBody()->write(json_encode(['error' => 'Failed to fetch students.'])); 
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
}



}
