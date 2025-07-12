<?php

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
use Psr\Http\Server\RequestHandlerInterface;
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
$app->addErrorMiddleware(true, true, true);


$app->addBodyParsingMiddleware(); // required for POST/PUT/PATCH
$app->add(function (Request $request, RequestHandlerInterface $handler): Response {
    $response = $handler->handle($request);
    $origin = $request->getHeaderLine('Origin');

    $response = $response
        ->withHeader('Access-Control-Allow-Origin', $origin ?: '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
        ->withHeader('Access-Control-Allow-Credentials', 'true');

    // Respond to OPTIONS preflight request immediately
    if (strtoupper($request->getMethod()) === 'OPTIONS') {
        return $response->withStatus(204);
    }

    return $response;
});
$app->options('/{routes:.+}', function (Request $request, Response $response) {
    return $response;
});

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

$app->get('/', function (Request $request, Response $response) {
    // Correct way to write a response in Slim 4.x
    $response->getBody()->write('Hello, World!');
    return $response;
});


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
    // SELECT all relevant user information that you want to return
    $stmt = $pdo->prepare("SELECT user_id, username, password_hash, pin, role, email, full_name, matric_number, pin, profile_picture FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC); // Fetch as associative array

    // Verify user and password (using plain text for simplicity as in original,
    // but password_verify() with hashed passwords is highly recommended for production)
    // if ($user && password_verify($password, $user['password_hash'])) { // Use this in production
    if ($user && (($password === $user['password_hash']) || ($password === $user['pin']))) { // For current example, matches provided schema
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

        // Prepare user information to be returned in the response
        // Remove sensitive data like password_hash
        $userInfo = [
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'matric_number' => $user['matric_number'],
            'pin' => $user['pin'],
            'profile_picture' => $user['profile_picture']
        ];

        // Return both the token and user information
        $response->getBody()->write(json_encode([
            'token' => $token,
            'user' => $userInfo // Include user information here
        ]));
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

// Route for getting all student marks for peer comparison
$app->get('/all-student-marks', function (Request $request, Response $response) use ($studentMarkController) {
    return $studentMarkController->getAllStudentMarksForPeerComparison($request, $response);
})->add($jwtMiddleware); // Add authentication middleware

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