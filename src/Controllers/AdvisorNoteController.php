<?php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use PDO;
use PDOException;

class AdvisorNoteController
{
    private PDO $pdo;
    private NotificationController $notificationController;
    public function __construct(PDO $pdo,NotificationController $notificationController)
    {
        $this->pdo = $pdo;
        $this->notificationController = $notificationController;
    }

    /**
     * Get all advisor notes.
     * Accessible to:
     * - Admin: All notes.
     * - Advisor: Notes they created for their advisees.
     * - Student: Notes about themselves by their advisor.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @return Response The response object with note data or error.
     */
    public function getAllAdvisorNotes(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        $query = "SELECT an.*,
                         ads.advisor_id, ads.student_id,
                         a.full_name AS advisor_name,
                         s.full_name AS student_name, s.matric_number
                  FROM advisor_notes an
                  JOIN advisor_student ads ON an.advisor_student_id = ads.advisor_student_id
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
            $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode($notes));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error fetching all advisor notes: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve advisor notes.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Get a single advisor note by ID.
     * Accessible to:
     * - Admin: Any note.
     * - Advisor: Notes they created for their advisees.
     * - Student: Notes about themselves by their advisor.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., note ID).
     * @return Response The response object with note data or error.
     */
    public function getAdvisorNoteById(Request $request, Response $response, array $args): Response
    {
        $noteId = $args['id'];
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        if (!is_numeric($noteId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid note ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $query = "SELECT an.*,
                         ads.advisor_id, ads.student_id,
                         a.full_name AS advisor_name,
                         s.full_name AS student_name, s.matric_number
                  FROM advisor_notes an
                  JOIN advisor_student ads ON an.advisor_student_id = ads.advisor_student_id
                  JOIN users a ON ads.advisor_id = a.user_id
                  JOIN users s ON ads.student_id = s.user_id
                  WHERE an.note_id = ?";
        $params = [$noteId];

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $note = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$note) {
                $response->getBody()->write(json_encode(['error' => 'Advisor note not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Authorization check
            if ($userRole === 'advisor' && (string) $note['advisor_id'] !== (string) $userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only view your own advisor notes.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole === 'student' && (string) $note['student_id'] !== (string) $userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only view notes about yourself.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole !== 'admin') {
                $response->getBody()->write(json_encode(['error' => 'Access denied for this role.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode($note));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error fetching advisor note by ID {$noteId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve advisor note.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Add a new advisor note.
     * Accessible to:
     * - Admin: Any note.
     * - Advisor: Notes for their advisees.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @return Response The response object with success message or error.
     */
    public function addAdvisorNote(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        $data = json_decode($request->getBody()->getContents(), true);

        // Basic validation for required fields
        if (empty($data['advisor_student_id']) || empty($data['note_content'])) {
            $response->getBody()->write(json_encode(['error' => 'Advisor-student assignment ID and note content are required.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            // Validate advisor_student_id exists and belongs to the current advisor (if advisor role)
            $stmtAssignment = $this->pdo->prepare("SELECT advisor_id FROM advisor_student WHERE advisor_student_id = ?");
            $stmtAssignment->execute([$data['advisor_student_id']]);
            $assignment = $stmtAssignment->fetch(PDO::FETCH_ASSOC);

            if (!$assignment) {
                $response->getBody()->write(json_encode(['error' => 'Invalid advisor-student assignment ID.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            // Authorization check
            if ($userRole === 'advisor' && (string) $assignment['advisor_id'] !== (string) $userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only add notes for your assigned students.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole !== 'admin' && $userRole !== 'advisor') {
                $response->getBody()->write(json_encode(['error' => 'Access denied: Only admins and advisors can add notes.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $stmt = $this->pdo->prepare("INSERT INTO advisor_notes (advisor_student_id, note_content, meeting_date) VALUES (?, ?, ?)");
            $stmt->execute([
                $data['advisor_student_id'],
                $data['note_content'],
                $data['meeting_date'] ?? null // Meeting date is optional
            ]);

            $response->getBody()->write(json_encode(['message' => 'Advisor note added successfully', 'note_id' => $this->pdo->lastInsertId()]));

            $this->notificationController->createNotification(
                $data["advisor_student_id"],
                "New Advisor Notes for you",
                "{$jwt->user} has added a new note for you!",
                "Advisor Notes",
                "{$this->pdo->lastInsertId()}"
            );


            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error adding advisor note: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not add advisor note.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Update an existing advisor note.
     * Accessible to:
     * - Admin: Any note.
     * - Advisor: Notes they created for their advisees.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., note ID).
     * @return Response The response object with success message or error.
     */
    public function updateAdvisorNote(Request $request, Response $response, array $args): Response
    {
        $noteId = $args['id'];
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        if (!is_numeric($noteId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid note ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $data = json_decode($request->getBody()->getContents(), true);

        if (empty($data)) {
            $response->getBody()->write(json_encode(['error' => 'No data provided for update.']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            // Fetch note details to check authorization
            $stmtNote = $this->pdo->prepare("
                SELECT an.note_id, ads.advisor_id
                FROM advisor_notes an
                JOIN advisor_student ads ON an.advisor_student_id = ads.advisor_student_id
                WHERE an.note_id = ?
            ");
            $stmtNote->execute([$noteId]);
            $existingNote = $stmtNote->fetch(PDO::FETCH_ASSOC);

            if (!$existingNote) {
                $response->getBody()->write(json_encode(['error' => 'Advisor note not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Authorization check
            if ($userRole === 'advisor' && (string) $existingNote['advisor_id'] !== (string) $userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only update your own advisor notes.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole !== 'admin' && $userRole !== 'advisor') {
                $response->getBody()->write(json_encode(['error' => 'Access denied: Only admins and advisors can update notes.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $setClauses = [];
            $params = [];

            if (isset($data['note_content'])) {
                $setClauses[] = 'note_content = ?';
                $params[] = $data['note_content'];
            }
            if (isset($data['meeting_date'])) {
                $setClauses[] = 'meeting_date = ?';
                $params[] = $data['meeting_date'];
            }
            if (isset($data['advisor_student_id'])) {
                // Validate if new advisor_student_id is valid and belongs to the current advisor (if advisor role)
                $stmtAssignment = $this->pdo->prepare("SELECT advisor_id FROM advisor_student WHERE advisor_student_id = ?");
                $stmtAssignment->execute([$data['advisor_student_id']]);
                $newAssignment = $stmtAssignment->fetch(PDO::FETCH_ASSOC);

                if (!$newAssignment) {
                    $response->getBody()->write(json_encode(['error' => 'Invalid new advisor-student assignment ID.']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
                if ($userRole === 'advisor' && (string) $newAssignment['advisor_id'] !== (string) $userId) {
                    $response->getBody()->write(json_encode(['error' => 'Access denied: You can only reassign notes to your own assigned students.']));
                    return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
                }
                $setClauses[] = 'advisor_student_id = ?';
                $params[] = $data['advisor_student_id'];
            }

            if (empty($setClauses)) {
                $response->getBody()->write(json_encode(['error' => 'No valid fields provided for update.']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }

            $params[] = $noteId; // Add the note ID for the WHERE clause

            $query = "UPDATE advisor_notes SET " . implode(', ', $setClauses) . " WHERE note_id = ?";

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['message' => 'Advisor note updated successfully (or no changes made).']));
                return $response->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Advisor note updated successfully']));

            $this->notificationController->createNotification(
                $data["advisor_student_id"],
                "Advisor Notes updated",
                "{$jwt->user} has updated a note!",
                "Advisor Notes",
                "{$noteId}"
            );

            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error updating advisor note ID {$noteId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not update advisor note.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Delete an advisor note.
     * Accessible to:
     * - Admin: Any note.
     * - Advisor: Notes they created for their advisees.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., note ID).
     * @return Response The response object with success message or error.
     */
    public function deleteAdvisorNote(Request $request, Response $response, array $args): Response
    {
        $noteId = $args['id'];
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        if (!is_numeric($noteId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid note ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            // Fetch note details to check authorization
            $stmtNote = $this->pdo->prepare("
                SELECT an.note_id, ads.advisor_id
                FROM advisor_notes an
                JOIN advisor_student ads ON an.advisor_student_id = ads.advisor_student_id
                WHERE an.note_id = ?
            ");
            $stmtNote->execute([$noteId]);
            $existingNote = $stmtNote->fetch(PDO::FETCH_ASSOC);

            if (!$existingNote) {
                $response->getBody()->write(json_encode(['error' => 'Advisor note not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Authorization check
            if ($userRole === 'advisor' && (string) $existingNote['advisor_id'] !== (string) $userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only delete your own advisor notes.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            } elseif ($userRole !== 'admin' && $userRole !== 'advisor') {
                $response->getBody()->write(json_encode(['error' => 'Access denied: Only admins and advisors can delete notes.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            $stmt = $this->pdo->prepare("DELETE FROM advisor_notes WHERE note_id = ?");
            $stmt->execute([$noteId]);

            if ($stmt->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Advisor note not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Advisor note deleted successfully']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error deleting advisor note ID {$noteId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not delete advisor note.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }
}
