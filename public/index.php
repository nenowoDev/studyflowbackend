<?php
// Set CORS headers to allow requests from any origin
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

// Autoload Composer dependencies
require __DIR__ . '/../vendor/autoload.php';
// Include database connection function
require __DIR__ . '/../src/db.php';
// Include JWT Middleware for authentication
require __DIR__ . '/../src/jwtMiddleware.php';

// Include controllers for different entities
require __DIR__ . '/../src/Controllers/UserController.php'; // Existing
require __DIR__ . '/../src/Controllers/CourseController.php'; // Existing
require __DIR__ . '/../src/Controllers/EnrollmentController.php'; // Existing
require __DIR__ . '/../src/Controllers/AssessmentComponentController.php'; // Existing
require __DIR__ . '/../src/Controllers/StudentMarkController.php'; // Existing

// New controllers
require __DIR__ . '/../src/Controllers/RemarkRequestController.php';
require __DIR__ . '/../src/Controllers/AdvisorStudentController.php';
require __DIR__ . '/../src/Controllers/AdvisorNoteController.php';


use Slim\Factory\AppFactory;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Firebase\JWT\JWT;
use App\Controllers\ProductController;
use App\Controllers\UserController;
use App\Controllers\CourseController;
use App\Controllers\EnrollmentController;
use App\Controllers\AssessmentComponentController;
use App\Controllers\StudentMarkController;
// New controllers
use App\Controllers\RemarkRequestController;
use App\Controllers\AdvisorStudentController;
use App\Controllers\AdvisorNoteController;


// Define the secret key for JWT (should be in a secure config in production)
$secretKey = "my-secret-key";

// Create a new Slim app instance
$app = AppFactory::create();

// Instantiate the JWT Middleware with the secret key
$jwtMiddleware = new JwtMiddleware($secretKey);

// Get the PDO instance for database connection
$pdo = getPDO();

// Instantiate all controllers, passing the PDO instance
$userController = new UserController($pdo);
$courseController = new CourseController($pdo);
$enrollmentController = new EnrollmentController($pdo);
$assessmentComponentController = new AssessmentComponentController($pdo);
$studentMarkController = new StudentMarkController($pdo);
// New controller instances
$remarkRequestController = new RemarkRequestController($pdo);
$advisorStudentController = new AdvisorStudentController($pdo);
$advisorNoteController = new AdvisorNoteController($pdo);


// --- Publicly accessible route ---
// Login route: handles user authentication and JWT generation
$app->post('/login', function (Request $request, Response $response) use ($secretKey, $pdo) {
    $data = json_decode($request->getBody()->getContents(), true);

    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    // Validate if username and password are provided
    if (!$username || !$password) {
        $response->getBody()->write(json_encode(['error' => 'Username and password required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Prepare and execute statement to find user by username
    $stmt = $pdo->prepare("SELECT user_id, username, password_hash, role FROM users WHERE username = ?"); // Fetch password_hash
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // Verify user and password (using plain text for simplicity as in original,
    // but password_verify() with hashed passwords is highly recommended for production)
    // if ($user && password_verify($password, $user['password_hash'])) { // Use this in production
    if ($user && $password === $user['password_hash']) { // For current example, matches provided schema
        $issuedAt = time();
        $expire = $issuedAt + 3600; // Token expires in 1 hour

        // Create JWT payload
        $payload = [
            'user_id' => $user['user_id'], // Include user_id in JWT
            'user' => $user['username'],
            'role' => $user['role'],
            'iat' => $issuedAt, // Issued At time
            'exp' => $expire    // Expiration time
        ];

        // Encode the payload into a JWT token
        $token = JWT::encode($payload, $secretKey, 'HS256');

        $response->getBody()->write(json_encode(['token' => $token]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // Return error for invalid credentials
    $response->getBody()->write(json_encode(['error' => 'Invalid credentials']));
    return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
});


// --- User Routes ---
$app->group('/users', function ($app) use ($userController) {
    $app->get('', [$userController, 'getAllUsers']); // Admin only
    $app->get('/{id}', [$userController, 'getUserById']); // Admin or self
    $app->post('', [$userController, 'addUser']); // Admin only
    $app->put('/{id}', [$userController, 'updateUser']); // Admin only
    $app->delete('/{id}', [$userController, 'deleteUser']); // Admin only
})->add($jwtMiddleware);

// --- Course Routes ---
$app->group('/courses', function ($app) use ($courseController) {
    $app->get('', [$courseController, 'getAllCourses']); // All authenticated
    $app->get('/{id}', [$courseController, 'getCourseById']); // All authenticated
    $app->post('', [$courseController, 'addCourse']); // Admin or Lecturer (self-assign)
    $app->put('/{id}', [$courseController, 'updateCourse']); // Admin or Lecturer (own course)
    $app->delete('/{id}', [$courseController, 'deleteCourse']); // Admin only
})->add($jwtMiddleware);

// --- Enrollment Routes ---
$app->group('/enrollments', function ($app) use ($enrollmentController) {
    $app->get('', [$enrollmentController, 'getAllEnrollments']); // Admin, Student (self), Lecturer (own courses)
    $app->get('/{id}', [$enrollmentController, 'getEnrollmentById']); // Admin, Student (self), Lecturer (own courses)
    $app->post('', [$enrollmentController, 'addEnrollment']); // Admin only
    $app->put('/{id}', [$enrollmentController, 'updateEnrollment']); // Admin only
    $app->delete('/{id}', [$enrollmentController, 'deleteEnrollment']); // Admin only
})->add($jwtMiddleware);

// --- Assessment Component Routes ---
$app->group('/assessment-components', function ($app) use ($assessmentComponentController) {
    $app->get('', [$assessmentComponentController, 'getAllAssessmentComponents']); // All authenticated
    $app->get('/{id}', [$assessmentComponentController, 'getAssessmentComponentById']); // All authenticated
    $app->post('', [$assessmentComponentController, 'addAssessmentComponent']); // Admin or Lecturer (own course)
    $app->put('/{id}', [$assessmentComponentController, 'updateAssessmentComponent']); // Admin or Lecturer (own course)
    $app->delete('/{id}', [$assessmentComponentController, 'deleteAssessmentComponent']); // Admin or Lecturer (own course)
})->add($jwtMiddleware);

// --- Student Mark Routes ---
$app->group('/student-marks', function ($app) use ($studentMarkController) {
    $app->get('', [$studentMarkController, 'getAllStudentMarks']); // Admin, Lecturer (own course), Student (self)
    $app->get('/{id}', [$studentMarkController, 'getStudentMarkById']); // Admin, Lecturer (own course), Student (self)
    $app->post('', [$studentMarkController, 'addStudentMark']); // Admin or Lecturer (own course)
    $app->put('/{id}', [$studentMarkController, 'updateStudentMark']); // Admin or Lecturer (own course)
    $app->delete('/{id}', [$studentMarkController, 'deleteStudentMark']); // Admin or Lecturer (own course)
})->add($jwtMiddleware);

// --- Remark Request Routes  ---
$app->group('/remark-requests', function ($app) use ($remarkRequestController) {
    $app->get('', [$remarkRequestController, 'getAllRemarkRequests']); // Admin, Lecturer (own courses), Student (self), Advisor (advisees)
    $app->get('/{id}', [$remarkRequestController, 'getRemarkRequestById']); // Admin, Lecturer (own courses), Student (self), Advisor (advisees)
    $app->post('', [$remarkRequestController, 'addRemarkRequest']); // Student only (for their own marks)
    $app->put('/{id}', [$remarkRequestController, 'updateRemarkRequest']); // Admin, Lecturer (own courses)
    $app->delete('/{id}', [$remarkRequestController, 'deleteRemarkRequest']); // Admin, Student (own pending), Lecturer (own courses)
})->add($jwtMiddleware);

// --- Advisor-Student Routes  ---
$app->group('/advisor-student', function ($app) use ($advisorStudentController) {
    $app->get('', [$advisorStudentController, 'getAllAdvisorStudents']); // Admin, Advisor (self), Student (self)
    $app->get('/{id}', [$advisorStudentController, 'getAdvisorStudentById']); // Admin, Advisor (self), Student (self)
    $app->post('', [$advisorStudentController, 'addAdvisorStudent']); // Admin only
    $app->put('/{id}', [$advisorStudentController, 'updateAdvisorStudent']); // Admin only
    $app->delete('/{id}', [$advisorStudentController, 'deleteAdvisorStudent']); // Admin only
})->add($jwtMiddleware);

// --- Advisor Notes Routes  ---
$app->group('/advisor-notes', function ($app) use ($advisorNoteController) {
    $app->get('', [$advisorNoteController, 'getAllAdvisorNotes']); // Admin, Advisor (self), Student (self)
    $app->get('/{id}', [$advisorNoteController, 'getAdvisorNoteById']); // Admin, Advisor (self), Student (self)
    $app->post('', [$advisorNoteController, 'addAdvisorNote']); // Admin, Advisor (own advisee)
    $app->put('/{id}', [$advisorNoteController, 'updateAdvisorNote']); // Admin, Advisor (own notes for advisee)
    $app->delete('/{id}', [$advisorNoteController, 'deleteAdvisorNote']); // Admin, Advisor (own notes for advisee)
})->add($jwtMiddleware);


// Run the Slim application
$app->run();