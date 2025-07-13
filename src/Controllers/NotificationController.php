<?php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use PDO;
use PDOException;

class NotificationController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get notifications for the authenticated user.
     * Users can only see their own notifications.
     * Admins can retrieve all notifications.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @return Response The response object with notification data or error.
     */
    public function getUserNotifications(Request $request, Response $response): Response
    {
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        $query = "SELECT * FROM notifications";
        $params = [];

        // Admins can see all notifications, others can only see their own
        if ($userRole !== 'admin') {
            $query .= " WHERE user_id = ?";
            $params[] = $userId;
        }

        $query .= " ORDER BY created_at DESC";

        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $response->getBody()->write(json_encode($notifications));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error fetching notifications for user {$userId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not retrieve notifications.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Mark a specific notification as read.
     * Users can only mark their own notifications as read.
     * Admin can mark any notification as read.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., notification ID).
     * @return Response The response object with success message or error.
     */
    public function markNotificationAsRead(Request $request, Response $response, array $args): Response
    {
        $notificationId = $args['id'];
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        if (!is_numeric($notificationId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid notification ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            // First, get the notification to check ownership if not admin
            $stmt = $this->pdo->prepare("SELECT user_id FROM notifications WHERE notification_id = ?");
            $stmt->execute([$notificationId]);
            $notification = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$notification) {
                $response->getBody()->write(json_encode(['error' => 'Notification not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            if ($userRole !== 'admin' && (string) $notification['user_id'] !== (string) $userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only mark your own notifications as read.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            // Mark as read
            $stmtUpdate = $this->pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE notification_id = ?");
            $stmtUpdate->execute([$notificationId]);

            if ($stmtUpdate->rowCount() === 0) {
                // This might happen if already read or no actual change occurred
                $response->getBody()->write(json_encode(['message' => 'Notification not found or already marked as read.']));
                return $response->withStatus(200)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Notification marked as read successfully.']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error marking notification ID {$notificationId} as read: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not update notification status.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Delete a specific notification.
     * Users can only delete their own notifications.
     * Admin can delete any notification.
     *
     * @param Request $request The request object.
     * @param Response $response The response object.
     * @param array $args Route arguments (e.g., notification ID).
     * @return Response The response object with success message or error.
     */
    public function deleteNotification(Request $request, Response $response, array $args): Response
    {
        $notificationId = $args['id'];
        $jwt = $request->getAttribute('jwt');
        $userId = $jwt->user_id;
        $userRole = $jwt->role;

        if (!is_numeric($notificationId)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid notification ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            // First, get the notification to check ownership if not admin
            $stmt = $this->pdo->prepare("SELECT user_id FROM notifications WHERE notification_id = ?");
            $stmt->execute([$notificationId]);
            $notification = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$notification) {
                $response->getBody()->write(json_encode(['error' => 'Notification not found.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            if ($userRole !== 'admin' && (string) $notification['user_id'] !== (string) $userId) {
                $response->getBody()->write(json_encode(['error' => 'Access denied: You can only delete your own notifications.']));
                return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
            }

            // Delete the notification
            $stmtDelete = $this->pdo->prepare("DELETE FROM notifications WHERE notification_id = ?");
            $stmtDelete->execute([$notificationId]);

            if ($stmtDelete->rowCount() === 0) {
                $response->getBody()->write(json_encode(['error' => 'Notification not found or already deleted.']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $response->getBody()->write(json_encode(['message' => 'Notification deleted successfully.']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (PDOException $e) {
            error_log("Error deleting notification ID {$notificationId}: " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error: Could not delete notification.']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Internal method to create a new notification.
     * This method is intended to be called by other controllers (e.g., StudentMarksController, RemarkRequestController)
     * when an event occurs that requires a notification.
     * Not directly exposed as an API endpoint.
     *
     * @param int $userId The ID of the user to notify.
     * @param string $title The title of the notification.
     * @param string $message The full message of the notification.
     * @param string|null $type The type of notification (e.g., 'remark_request', 'new_mark').
     * @param int|null $relatedId The ID of the related record (e.g., remark_request_id, mark_id).
     * @return bool True on success, false on failure.
     */
    public function createNotification(int $userId, string $title, string $message, ?string $type = null, ?int $relatedId = null): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO notifications (user_id, title, message, type, related_id) 
                 VALUES (?, ?, ?, ?, ?)"
            );
            return $stmt->execute([$userId, $title, $message, $type, $relatedId]);
        } catch (PDOException $e) {
            error_log("Error creating notification for user {$userId}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Internal method to create notifications for all users of specific roles.
     * This is useful for system-wide announcements like new courses.
     *
     * @param array $roles An array of roles (e.g., ['student', 'lecturer', 'admin']) to notify.
     * @param string $title The title of the notification.
     * @param string $message The full message of the notification.
     * @param string|null $type The type of notification (e.g., 'new_course_available').
     * @param int|null $relatedId The ID of the related record (e.g., course_id).
     * @return array An associative array with 'success' (boolean) and 'count' (number of notifications created).
     */
    public function notifyRoles(array $roles, string $title, string $message, ?string $type = null, ?int $relatedId = null): array
    {
        if (empty($roles)) {
            return ['success' => false, 'count' => 0, 'error' => 'No roles provided for notification.'];
        }

        // Validate roles against allowed ENUM values to prevent SQL injection
        $allowedRoles = ['lecturer', 'student', 'advisor', 'admin'];
        $validRoles = array_intersect($roles, $allowedRoles);

        if (empty($validRoles)) {
            return ['success' => false, 'count' => 0, 'error' => 'Invalid roles provided.'];
        }

        $placeholders = implode(',', array_fill(0, count($validRoles), '?'));

        try {
            // Fetch all user_ids for the specified roles
            $stmtUsers = $this->pdo->prepare("SELECT user_id FROM users WHERE role IN ({$placeholders})");
            $stmtUsers->execute($validRoles);
            $userIds = $stmtUsers->fetchAll(PDO::FETCH_COLUMN);

            if (empty($userIds)) {
                return ['success' => true, 'count' => 0, 'message' => 'No users found for the specified roles.'];
            }

            $count = 0;
            // Batch insert for performance or individual inserts for robust error handling
            // For simplicity, we'll do individual inserts here. For very large user bases, consider a single INSERT ... SELECT or multi-value INSERT.
            $insertStmt = $this->pdo->prepare(
                "INSERT INTO notifications (user_id, title, message, type, related_id) 
                 VALUES (?, ?, ?, ?, ?)"
            );

            foreach ($userIds as $userId) {
                if ($insertStmt->execute([$userId, $title, $message, $type, $relatedId])) {
                    $count++;
                } else {
                    error_log("Failed to notify user {$userId} with title '{$title}': " . implode(" ", $insertStmt->errorInfo()));
                }
            }

            return ['success' => true, 'count' => $count];

        } catch (PDOException $e) {
            error_log("Error notifying roles: " . $e->getMessage());
            return ['success' => false, 'count' => 0, 'error' => 'Database error during mass notification.'];
        }
    }
}