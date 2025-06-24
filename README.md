Course Mark Management API Documentation

This document outlines the available endpoints for the Course Mark Management System API. All API interactions are expected to use JSON for request and response bodies, unless otherwise specified.

Base URL: http://localhost:8000 (or your configured server address)
Authentication

All protected endpoints require a JSON Web Token (JWT) provided in the Authorization header as a Bearer token.

Header:
Authorization: Bearer <YOUR_JWT_TOKEN>
1. Login

Authenticates a user and returns a JWT token.

    Endpoint: /login

    Method: POST

    Description: Allows users of any role to log in and obtain an authentication token.

    Access Control: Publicly accessible.

    Request Headers:

        Content-Type: application/json

    Request Body (JSON):

    {
        "username": "user_username",
        "password": "user_password"
    }

    Success Response (200 OK):

    {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
    }

    Error Responses:

        400 Bad Request: {"error": "Username and password required"}

        401 Unauthorized: {"error": "Invalid credentials"}

2. Users Management

Endpoints for managing user accounts.
2.1. Get All Users

    Endpoint: /users

    Method: GET

    Description: Retrieves a list of all registered users.

    Access Control: admin only.

    Request Headers:

        Authorization: Bearer <Admin_JWT_Token>

    Success Response (200 OK):

    [
        {
            "user_id": 1,
            "username": "adminuser",
            "role": "admin",
            "email": "admin@example.com",
            "full_name": "Admin User",
            "matric_number": null,
            "pin": null
        },
        {
            "user_id": 2,
            "username": "student1",
            "role": "student",
            "email": "student1@example.com",
            "full_name": "Bob Johnson",
            "matric_number": "S12345",
            "pin": "1234"
        }
    ]

    Error Responses:

        401 Unauthorized: {"error": "Authorization header missing"} or {"error": "Invalid token"}

        403 Forbidden: {"error": "Access denied: admin only"}

        500 Internal Server Error: {"error": "Database error: Could not retrieve users."}

2.2. Get User by ID

    Endpoint: /users/{id}

    Method: GET

    Description: Retrieves details for a specific user.

    Access Control: admin (any user) or user viewing self.

    Path Parameters:

        id (integer): The user_id.

    Request Headers:

        Authorization: Bearer <JWT_Token>

    Success Response (200 OK):

    {
        "user_id": 2,
        "username": "student1",
        "role": "student",
        "email": "student1@example.com",
        "full_name": "Bob Johnson",
        "matric_number": "S12345",
        "pin": "1234"
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid user ID"}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: You can only view your own profile."}

        404 Not Found: {"error": "User not found"}

        500 Internal Server Error: {"error": "Database error: Could not retrieve user."}

2.3. Add New User

    Endpoint: /users

    Method: POST

    Description: Creates a new user account.

    Access Control: admin only.

    Request Headers:

        Content-Type: application/json

        Authorization: Bearer <Admin_JWT_Token>

    Request Body (JSON):

    {
        "username": "newstudentuser",
        "password": "securepassword",
        "role": "student",
        "email": "new.student@example.com",
        "full_name": "New Student",
        "matric_number": "S98765",
        "pin": "9999"
    }

        role can be one of: lecturer, student, advisor, admin.

        email, matric_number, pin are optional (can be null).

    Success Response (201 Created):

    {
        "message": "User added successfully",
        "user_id": 5
    }

    Error Responses:

        400 Bad Request: {"error": "Username, password, role, and full_name are required."} or {"error": "Invalid role specified."}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: admin only"}

        409 Conflict: {"error": "A user with this username, email, or matric number already exists."}

        500 Internal Server Error: {"error": "Database error: Could not add user."}

2.4. Update User

    Endpoint: /users/{id}

    Method: PUT

    Description: Updates details of an existing user.

    Access Control: admin only.

    Path Parameters:

        id (integer): The user_id to update.

    Request Headers:

        Content-Type: application/json

        Authorization: Bearer <Admin_JWT_Token>

    Request Body (JSON):

        Provide only the fields you wish to update.

    {
        "email": "updated.student@example.com",
        "pin": "1010"
    }

    Success Response (200 OK):

    {
        "message": "User updated successfully"
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid user ID"} or {"error": "No data provided for update."} or {"error": "No valid fields provided for update."} or {"error": "Invalid role specified."}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: admin only"}

        404 Not Found: {"error": "User not found or no changes made."}

        409 Conflict: {"error": "A user with this username, email, or matric number already exists."}

        500 Internal Server Error: {"error": "Database error: Could not update user."}

2.5. Delete User

    Endpoint: /users/{id}

    Method: DELETE

    Description: Deletes a user account.

    Access Control: admin only.

    Path Parameters:

        id (integer): The user_id to delete.

    Request Headers:

        Authorization: Bearer <Admin_JWT_Token>

    Success Response (200 OK):

    {
        "message": "User deleted successfully"
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid user ID"}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: admin only"}

        404 Not Found: {"error": "User not found."}

        500 Internal Server Error: {"error": "Database error: Could not delete user."}

3. Courses Management

Endpoints for managing course offerings.
3.1. Get All Courses

    Endpoint: /courses

    Method: GET

    Description: Retrieves a list of all courses.

    Access Control: All authenticated users.

    Request Headers:

        Authorization: Bearer <JWT_Token>

    Success Response (200 OK):

    [
        {
            "course_id": 1,
            "course_code": "CS101",
            "course_name": "Introduction to Computer Science",
            "lecturer_id": 2,
            "created_at": "2024-01-01 10:00:00",
            "updated_at": "2024-01-01 10:00:00",
            "lecturer_name": "Dr. Alice Smith"
        }
    ]

    Error Responses:

        401 Unauthorized: (JWT errors)

        500 Internal Server Error: {"error": "Database error: Could not retrieve courses."}

3.2. Get Course by ID

    Endpoint: /courses/{id}

    Method: GET

    Description: Retrieves details for a specific course.

    Access Control: All authenticated users.

    Path Parameters:

        id (integer): The course_id.

    Request Headers:

        Authorization: Bearer <JWT_Token>

    Success Response (200 OK):

    {
        "course_id": 1,
        "course_code": "CS101",
        "course_name": "Introduction to Computer Science",
        "lecturer_id": 2,
        "created_at": "2024-01-01 10:00:00",
        "updated_at": "2024-01-01 10:00:00",
        "lecturer_name": "Dr. Alice Smith"
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid course ID"}

        401 Unauthorized: (JWT errors)

        404 Not Found: {"error": "Course not found"}

        500 Internal Server Error: {"error": "Database error: Could not retrieve course."}

3.3. Add New Course

    Endpoint: /courses

    Method: POST

    Description: Creates a new course.

    Access Control: admin or lecturer (lecturer can only assign themselves as lecturer_id).

    Request Headers:

        Content-Type: application/json

        Authorization: Bearer <Admin_JWT_Token> or Bearer <Lecturer_JWT_Token>

    Request Body (JSON):

    {
        "course_code": "MA201",
        "course_name": "Calculus II",
        "lecturer_id": 3 // Must be an existing user with role 'lecturer'. If 'lecturer' token, must match their user_id.
    }

    Success Response (201 Created):

    {
        "message": "Course added successfully",
        "course_id": 2
    }

    Error Responses:

        400 Bad Request: {"error": "Course code, name, and lecturer ID are required."} or {"error": "Invalid lecturer ID or user is not a lecturer."}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: Only admins and lecturers can add courses."} or {"error": "Access denied: Lecturers can only assign courses to themselves."}

        409 Conflict: {"error": "A course with this course code already exists."}

        500 Internal Server Error: {"error": "Database error: Could not add course."}

3.4. Update Course

    Endpoint: /courses/{id}

    Method: PUT

    Description: Updates details of an existing course.

    Access Control: admin or lecturer (lecturer can only update courses they are assigned to, and cannot change lecturer_id to another lecturer).

    Path Parameters:

        id (integer): The course_id to update.

    Request Headers:

        Content-Type: application/json

        Authorization: Bearer <Admin_JWT_Token> or Bearer <Lecturer_JWT_Token>

    Request Body (JSON):

        Provide only the fields you wish to update.

    {
        "course_name": "Advanced Calculus",
        "lecturer_id": 3 // Admin can change this. Lecturer must match their own user_id.
    }

    Success Response (200 OK):

    {
        "message": "Course updated successfully"
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid course ID"} or {"error": "No data provided for update."} or {"error": "Invalid new lecturer ID or user is not a lecturer."}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: You can only update courses you are assigned to."} or {"error": "Access denied: Lecturers cannot change course lecturer to someone else."}

        404 Not Found: {"error": "Course not found."}

        409 Conflict: {"error": "A course with this course code already exists."}

        500 Internal Server Error: {"error": "Database error: Could not update course."}

3.5. Delete Course

    Endpoint: /courses/{id}

    Method: DELETE

    Description: Deletes a course.

    Access Control: admin only.

    Path Parameters:

        id (integer): The course_id to delete.

    Request Headers:

        Authorization: Bearer <Admin_JWT_Token>

    Success Response (200 OK):

    {
        "message": "Course deleted successfully"
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid course ID"}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: admin only"}

        404 Not Found: {"error": "Course not found."}

        500 Internal Server Error: {"error": "Database error: Could not delete course."}

4. Enrollments Management

Endpoints for managing student enrollments in courses.
4.1. Get All Enrollments

    Endpoint: /enrollments

    Method: GET

    Description: Retrieves a list of all enrollments.

    Access Control: admin (all), student (their own enrollments), lecturer (enrollments for their courses).

    Request Headers:

        Authorization: Bearer <JWT_Token>

    Success Response (200 OK):

    [
        {
            "enrollment_id": 1,
            "student_id": 2,
            "course_id": 1,
            "enrollment_date": "2024-09-01",
            "created_at": "2024-01-01 10:00:00",
            "updated_at": "2024-01-01 10:00:00",
            "student_name": "Bob Johnson",
            "course_name": "Introduction to Computer Science",
            "course_code": "CS101"
        }
    ]

    Error Responses:

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied for this role."}

        500 Internal Server Error: {"error": "Database error: Could not retrieve enrollments."}

4.2. Get Enrollment by ID

    Endpoint: /enrollments/{id}

    Method: GET

    Description: Retrieves details for a specific enrollment.

    Access Control: admin (any), student (their own), lecturer (for their courses).

    Path Parameters:

        id (integer): The enrollment_id.

    Request Headers:

        Authorization: Bearer <JWT_Token>

    Success Response (200 OK):

    {
        "enrollment_id": 1,
        "student_id": 2,
        "course_id": 1,
        "enrollment_date": "2024-09-01",
        "created_at": "2024-01-01 10:00:00",
        "updated_at": "2024-01-01 10:00:00",
        "student_name": "Bob Johnson",
        "course_name": "Introduction to Computer Science",
        "course_code": "CS101",
        "lecturer_id": 3
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid enrollment ID"}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: You can only view your own enrollments."} or {"error": "Access denied: You can only view enrollments for your courses."} or {"error": "Access denied for this role."}

        404 Not Found: {"error": "Enrollment not found"}

        500 Internal Server Error: {"error": "Database error: Could not retrieve enrollment."}

4.3. Add New Enrollment

    Endpoint: /enrollments

    Method: POST

    Description: Creates a new student enrollment.

    Access Control: admin only.

    Request Headers:

        Content-Type: application/json

        Authorization: Bearer <Admin_JWT_Token>

    Request Body (JSON):

    {
        "student_id": 2, // Must be an existing user with role 'student'
        "course_id": 1,  // Must be an existing course
        "enrollment_date": "2024-09-01"
    }

    Success Response (201 Created):

    {
        "message": "Enrollment added successfully",
        "enrollment_id": 2
    }

    Error Responses:

        400 Bad Request: {"error": "Student ID, Course ID, and enrollment date are required."} or {"error": "Invalid student ID or user is not a student."} or {"error": "Invalid course ID."}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: admin only"}

        409 Conflict: {"error": "This student is already enrolled in this course."}

        500 Internal Server Error: {"error": "Database error: Could not add enrollment."}

4.4. Update Enrollment

    Endpoint: /enrollments/{id}

    Method: PUT

    Description: Updates details of an existing enrollment.

    Access Control: admin only.

    Path Parameters:

        id (integer): The enrollment_id to update.

    Request Headers:

        Content-Type: application/json

        Authorization: Bearer <Admin_JWT_Token>

    Request Body (JSON):

        Provide only the fields you wish to update.

    {
        "enrollment_date": "2024-09-15"
    }

    Success Response (200 OK):

    {
        "message": "Enrollment updated successfully"
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid enrollment ID"} or {"error": "No data provided for update."} or {"error": "No valid fields provided for update."}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: admin only"}

        404 Not Found: {"error": "Enrollment not found or no changes made."}

        409 Conflict: {"error": "This student is already enrolled in this course."}

        500 Internal Server Error: {"error": "Database error: Could not update enrollment."}

4.5. Delete Enrollment

    Endpoint: /enrollments/{id}

    Method: DELETE

    Description: Deletes an enrollment.

    Access Control: admin only.

    Path Parameters:

        id (integer): The enrollment_id to delete.

    Request Headers:

        Authorization: Bearer <Admin_JWT_Token>

    Success Response (200 OK):

    {
        "message": "Enrollment deleted successfully"
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid enrollment ID"}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: admin only"}

        404 Not Found: {"error": "Enrollment not found."}

        500 Internal Server Error: {"error": "Database error: Could not delete enrollment."}

5. Assessment Components Management

Endpoints for managing assessment components within courses.
5.1. Get All Assessment Components

    Endpoint: /assessment-components

    Method: GET

    Description: Retrieves a list of all assessment components.

    Access Control: admin (all), lecturer (components for their courses), student (components for courses they are enrolled in).

    Request Headers:

        Authorization: Bearer <JWT_Token>

    Success Response (200 OK):

    [
        {
            "component_id": 1,
            "course_id": 1,
            "component_name": "Quiz 1",
            "max_mark": "20.00",
            "weight_percentage": "10.00",
            "is_final_exam": 0,
            "created_at": "2024-01-01 10:00:00",
            "updated_at": "2024-01-01 10:00:00",
            "course_name": "Introduction to Computer Science",
            "course_code": "CS101",
            "lecturer_id": 3
        }
    ]

    Error Responses:

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied for this role."}

        500 Internal Server Error: {"error": "Database error: Could not retrieve assessment components."}

5.2. Get Assessment Component by ID

    Endpoint: /assessment-components/{id}

    Method: GET

    Description: Retrieves details for a specific assessment component.

    Access Control: admin (any), lecturer (for their courses), student (for courses they are enrolled in).

    Path Parameters:

        id (integer): The component_id.

    Request Headers:

        Authorization: Bearer <JWT_Token>

    Success Response (200 OK):

    {
        "component_id": 1,
        "course_id": 1,
        "component_name": "Quiz 1",
        "max_mark": "20.00",
        "weight_percentage": "10.00",
        "is_final_exam": 0,
        "created_at": "2024-01-01 10:00:00",
        "updated_at": "2024-01-01 10:00:00",
        "course_name": "Introduction to Computer Science",
        "course_code": "CS101",
        "lecturer_id": 3
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid component ID"}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: You can only view components for your courses."} or {"error": "Access denied: You can only view components for courses you are enrolled in."} or {"error": "Access denied for this role."}

        404 Not Found: {"error": "Assessment component not found"}

        500 Internal Server Error: {"error": "Database error: Could not retrieve assessment component."}

5.3. Add New Assessment Component

    Endpoint: /assessment-components

    Method: POST

    Description: Creates a new assessment component for a course.

    Access Control: admin or lecturer (for courses they are assigned to).

    Request Headers:

        Content-Type: application/json

        Authorization: Bearer <Admin_JWT_Token> or Bearer <Lecturer_JWT_Token>

    Request Body (JSON):

    {
        "course_id": 1, // Must be an existing course ID
        "component_name": "Assignment 1",
        "max_mark": 100.00,
        "weight_percentage": 25.00,
        "is_final_exam": false
    }

        is_final_exam is optional (defaults to false).

    Success Response (201 Created):

    {
        "message": "Assessment component added successfully",
        "component_id": 2
    }

    Error Responses:

        400 Bad Request: {"error": "Course ID, component name, max mark, and weight percentage are required."} or {"error": "Max mark must be a positive number."} or {"error": "Weight percentage must be between 0 and 100."} or {"error": "Invalid course ID."}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: You can only add components to courses you are assigned to."} or {"error": "Access denied: Only admins and lecturers can add assessment components."}

        500 Internal Server Error: {"error": "Database error: Could not add assessment component."}

5.4. Update Assessment Component

    Endpoint: /assessment-components/{id}

    Method: PUT

    Description: Updates details of an existing assessment component.

    Access Control: admin or lecturer (for courses they are assigned to).

    Path Parameters:

        id (integer): The component_id to update.

    Request Headers:

        Content-Type: application/json

        Authorization: Bearer <Admin_JWT_Token> or Bearer <Lecturer_JWT_Token>

    Request Body (JSON):

        Provide only the fields you wish to update.

    {
        "max_mark": 120.00,
        "weight_percentage": 30.00
    }

    Success Response (200 OK):

    {
        "message": "Assessment component updated successfully"
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid component ID"} or {"error": "No data provided for update."} or {"error": "No valid fields provided for update."} or {"error": "Max mark must be a positive number."} or {"error": "Weight percentage must be between 0 and 100."}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: You can only update components for your courses."} or {"error": "Access denied: Only admins and lecturers can update assessment components."}

        404 Not Found: {"error": "Assessment component not found."}

        500 Internal Server Error: {"error": "Database error: Could not update assessment component."}

5.5. Delete Assessment Component

    Endpoint: /assessment-components/{id}

    Method: DELETE

    Description: Deletes an assessment component.

    Access Control: admin or lecturer (for courses they are assigned to).

    Path Parameters:

        id (integer): The component_id to delete.

    Request Headers:

        Authorization: Bearer <Admin_JWT_Token> or Bearer <Lecturer_JWT_Token>

    Success Response (200 OK):

    {
        "message": "Assessment component deleted successfully"
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid component ID"}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: You can only delete components from courses you are assigned to."} or {"error": "Access denied: Only admins and lecturers can delete assessment components."}

        404 Not Found: {"error": "Assessment component not found."}

        500 Internal Server Error: {"error": "Database error: Could not delete assessment component."}

6. Student Marks Management

Endpoints for managing student marks for assessment components.
6.1. Get All Student Marks

    Endpoint: /student-marks

    Method: GET

    Description: Retrieves a list of all student marks.

    Access Control: admin (all), lecturer (marks for their courses), student (their own marks).

    Request Headers:

        Authorization: Bearer <JWT_Token>

    Success Response (200 OK):

    [
        {
            "mark_id": 1,
            "enrollment_id": 1,
            "component_id": 1,
            "mark_obtained": "18.50",
            "recorded_by": 3,
            "recorded_at": "2024-01-01 10:00:00",
            "updated_at": "2024-01-01 10:00:00",
            "student_name": "Bob Johnson",
            "course_name": "Introduction to Computer Science",
            "component_name": "Quiz 1",
            "recorded_by_name": "Dr. Alice Smith"
        }
    ]

    Error Responses:

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied for this role."}

        500 Internal Server Error: {"error": "Database error: Could not retrieve student marks."}

6.2. Get Student Mark by ID

    Endpoint: /student-marks/{id}

    Method: GET

    Description: Retrieves details for a specific student mark.

    Access Control: admin (any), lecturer (marks for their courses), student (their own mark).

    Path Parameters:

        id (integer): The mark_id.

    Request Headers:

        Authorization: Bearer <JWT_Token>

    Success Response (200 OK):

    {
        "mark_id": 1,
        "enrollment_id": 1,
        "component_id": 1,
        "mark_obtained": "18.50",
        "recorded_by": 3,
        "recorded_at": "2024-01-01 10:00:00",
        "updated_at": "2024-01-01 10:00:00",
        "student_id": 2,
        "lecturer_id": 3,
        "student_name": "Bob Johnson",
        "course_name": "Introduction to Computer Science",
        "component_name": "Quiz 1",
        "recorded_by_name": "Dr. Alice Smith"
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid mark ID"}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: You can only view your own marks."} or {"error": "Access denied: You can only view marks for your courses."} or {"error": "Access denied for this role."}

        404 Not Found: {"error": "Student mark not found"}

        500 Internal Server Error: {"error": "Database error: Could not retrieve student mark."}

6.3. Add New Student Mark

    Endpoint: /student-marks

    Method: POST

    Description: Records a new mark for a student in a specific assessment component.

    Access Control: admin or lecturer (for courses they are assigned to).

    Request Headers:

        Content-Type: application/json

        Authorization: Bearer <Admin_JWT_Token> or Bearer <Lecturer_JWT_Token>

    Request Body (JSON):

    {
        "enrollment_id": 1, // Must be an existing enrollment ID
        "component_id": 1,  // Must be an existing component ID associated with the course in the enrollment
        "mark_obtained": 18.50
    }

    Success Response (201 Created):

    {
        "message": "Student mark added successfully",
        "mark_id": 2
    }

    Error Responses:

        400 Bad Request: {"error": "Enrollment ID, Component ID, and mark obtained are required."} or {"error": "Mark obtained must be a non-negative number."} or {"error": "Invalid enrollment ID or component ID for this course."} or {"error": "Mark obtained (X) exceeds the maximum mark allowed (Y) for this component."}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: You can only record marks for your assigned courses."} or {"error": "Access denied: Only admins and lecturers can add student marks."}

        409 Conflict: {"error": "A mark for this student and assessment component already exists. Use PUT to update."}

        500 Internal Server Error: {"error": "Database error: Could not add student mark."}

6.4. Update Student Mark

    Endpoint: /student-marks/{id}

    Method: PUT

    Description: Updates an existing student mark.

    Access Control: admin or lecturer (for courses they are assigned to).

    Path Parameters:

        id (integer): The mark_id to update.

    Request Headers:

        Content-Type: application/json

        Authorization: Bearer <Admin_JWT_Token> or Bearer <Lecturer_JWT_Token>

    Request Body (JSON):

        Provide only the fields you wish to update.

    {
        "mark_obtained": 19.00
    }

    Success Response (200 OK):

    {
        "message": "Student mark updated successfully"
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid mark ID"} or {"error": "No data provided for update."} or {"error": "Mark obtained must be a non-negative number."} or {"error": "Mark obtained (X) exceeds the maximum mark allowed (Y) for this component."}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: You can only update marks for your assigned courses."} or {"error": "Access denied: Only admins and lecturers can update student marks."}

        404 Not Found: {"error": "Student mark not found or no changes made."}

        409 Conflict: {"error": "A mark for this enrollment and component already exists."}

        500 Internal Server Error: {"error": "Database error: Could not update student mark."}

6.5. Delete Student Mark

    Endpoint: /student-marks/{id}

    Method: DELETE

    Description: Deletes a student mark.

    Access Control: admin or lecturer (for courses they are assigned to).

    Path Parameters:

        id (integer): The mark_id to delete.

    Request Headers:

        Authorization: Bearer <Admin_JWT_Token> or Bearer <Lecturer_JWT_Token>

    Success Response (200 OK):

    {
        "message": "Student mark deleted successfully"
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid mark ID"}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: You can only delete marks for your assigned courses."} or {"error": "Access denied: Only admins and lecturers can delete student marks."}

        404 Not Found: {"error": "Student mark not found."}

        500 Internal Server Error: {"error": "Database error: Could not delete student mark."}

7. Remark Requests Management

Endpoints for managing student remark requests.
7.1. Get All Remark Requests

    Endpoint: /remark-requests

    Method: GET

    Description: Retrieves a list of all remark requests.

    Access Control: admin (all), lecturer (requests for their courses), student (their own requests), advisor (requests for their advisee students).

    Request Headers:

        Authorization: Bearer <JWT_Token>

    Success Response (200 OK):

    [
        {
            "request_id": 1,
            "mark_id": 1,
            "student_id": 2,
            "justification": "I believe there was an error in grading question 3.",
            "request_date": "2024-06-24 10:00:00",
            "status": "pending",
            "lecturer_notes": null,
            "resolved_by": null,
            "resolved_at": null,
            "created_at": "2024-06-24 10:00:00",
            "updated_at": "2024-06-24 10:00:00",
            "student_name": "Bob Johnson",
            "matric_number": "S12345",
            "mark_obtained": "18.50",
            "component_name": "Quiz 1",
            "max_mark": "20.00",
            "course_name": "Introduction to Computer Science",
            "course_code": "CS101",
            "course_lecturer_id": 3,
            "resolved_by_name": null
        }
    ]

    Error Responses:

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied for this role."}

        500 Internal Server Error: {"error": "Database error: Could not retrieve remark requests."}

7.2. Get Remark Request by ID

    Endpoint: /remark-requests/{id}

    Method: GET

    Description: Retrieves details for a specific remark request.

    Access Control: admin (any), lecturer (requests for their courses), student (their own), advisor (for their advisees).

    Path Parameters:

        id (integer): The request_id.

    Request Headers:

        Authorization: Bearer <JWT_Token>

    Success Response (200 OK):

    {
        "request_id": 1,
        "mark_id": 1,
        "student_id": 2,
        "justification": "I believe there was an error in grading question 3.",
        "request_date": "2024-06-24 10:00:00",
        "status": "pending",
        "lecturer_notes": null,
        "resolved_by": null,
        "resolved_at": null,
        "created_at": "2024-06-24 10:00:00",
        "updated_at": "2024-06-24 10:00:00",
        "student_name": "Bob Johnson",
        "matric_number": "S12345",
        "mark_obtained": "18.50",
        "component_name": "Quiz 1",
        "max_mark": "20.00",
        "course_name": "Introduction to Computer Science",
        "course_code": "CS101",
        "course_lecturer_id": 3
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid request ID"}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: You can only view your own remark requests."} or {"error": "Access denied: You can only view remark requests for your courses."} or {"error": "Access denied: You can only view remark requests for your advisees."} or {"error": "Access denied for this role."}

        404 Not Found: {"error": "Remark request not found"}

        500 Internal Server Error: {"error": "Database error: Could not retrieve remark request."}

7.3. Add New Remark Request

    Endpoint: /remark-requests

    Method: POST

    Description: Students submit a new remark request for a specific mark.

    Access Control: student only (for their own marks).

    Request Headers:

        Content-Type: application/json

        Authorization: Bearer <Student_JWT_Token>

    Request Body (JSON):

    {
        "mark_id": 1, // Must be an existing mark ID that belongs to the authenticated student
        "justification": "I believe there was an error in grading question 3. My answer was correct because X."
    }

    Success Response (201 Created):

    {
        "message": "Remark request submitted successfully",
        "request_id": 2
    }

    Error Responses:

        400 Bad Request: {"error": "Mark ID and justification are required."} or {"error": "Invalid mark ID or mark does not belong to your account."}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: Only students can submit remark requests."}

        409 Conflict: {"error": "A remark request for this mark already exists from this student."}

        500 Internal Server Error: {"error": "Database error: Could not submit remark request."}

7.4. Update Remark Request

    Endpoint: /remark-requests/{id}

    Method: PUT

    Description: Updates an existing remark request. Primarily used by lecturers to change status and add notes.

    Access Control: admin (any) or lecturer (for requests in their courses). Students cannot update.

    Path Parameters:

        id (integer): The request_id to update.

    Request Headers:

        Content-Type: application/json

        Authorization: Bearer <Admin_JWT_Token> or Bearer <Lecturer_JWT_Token>

    Request Body (JSON):

        Provide only the fields you wish to update. status update will automatically set resolved_by and resolved_at.

    {
        "status": "approved",
        "lecturer_notes": "Upon review, the student's point is valid. Mark will be updated."
    }

        status can be pending, approved, rejected.

    Success Response (200 OK):

    {
        "message": "Remark request updated successfully"
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid request ID"} or {"error": "No data provided for update."} or {"error": "Invalid status provided."}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: You can only update remark requests for your assigned courses."} or {"error": "Access denied: Students cannot update remark requests."} or {"error": "Access denied for this role."} or {"error": "Only lecturers or admins can resolve remark requests."}

        404 Not Found: {"error": "Remark request not found."}

        500 Internal Server Error: {"error": "Database error: Could not update remark request."}

7.5. Delete Remark Request

    Endpoint: /remark-requests/{id}

    Method: DELETE

    Description: Deletes a remark request.

    Access Control: admin (any), student (their own pending requests), lecturer (requests for their courses, any status).

    Path Parameters:

        id (integer): The request_id to delete.

    Request Headers:

        Authorization: Bearer <JWT_Token>

    Success Response (200 OK):

    {
        "message": "Remark request deleted successfully"
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid request ID"}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: You can only delete your own pending remark requests."} or {"error": "Access denied: You can only delete remark requests for your assigned courses."} or {"error": "Access denied for this role."}

        404 Not Found: {"error": "Remark request not found or not authorized to delete."}

        500 Internal Server Error: {"error": "Database error: Could not delete remark request."}

8. Advisor-Student Assignments Management

Endpoints for managing which students are assigned to which academic advisors.
8.1. Get All Advisor-Student Assignments

    Endpoint: /advisor-student

    Method: GET

    Description: Retrieves a list of all advisor-student assignments.

    Access Control: admin (all), advisor (their own assigned students), student (their assigned advisor).

    Request Headers:

        Authorization: Bearer <JWT_Token>

    Success Response (200 OK):

    [
        {
            "advisor_student_id": 1,
            "advisor_id": 4,
            "student_id": 2,
            "assigned_at": "2024-06-24 10:00:00",
            "advisor_name": "Ms. Carol White",
            "advisor_email": "advisor1@example.com",
            "student_name": "Bob Johnson",
            "matric_number": "S12345",
            "student_email": "student1@example.com"
        }
    ]

    Error Responses:

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied for this role."}

        500 Internal Server Error: {"error": "Database error: Could not retrieve advisor-student assignments."}

8.2. Get Advisor-Student Assignment by ID

    Endpoint: /advisor-student/{id}

    Method: GET

    Description: Retrieves details for a specific advisor-student assignment.

    Access Control: admin (any), advisor (their own assigned students), student (their assigned advisor).

    Path Parameters:

        id (integer): The advisor_student_id.

    Request Headers:

        Authorization: Bearer <JWT_Token>

    Success Response (200 OK):

    {
        "advisor_student_id": 1,
        "advisor_id": 4,
        "student_id": 2,
        "assigned_at": "2024-06-24 10:00:00",
        "advisor_name": "Ms. Carol White",
        "advisor_email": "advisor1@example.com",
        "student_name": "Bob Johnson",
        "matric_number": "S12345",
        "student_email": "student1@example.com"
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid assignment ID"}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: You can only view your own assigned students."} or {"error": "Access denied: You can only view your own advisor assignment."} or {"error": "Access denied for this role."}

        404 Not Found: {"error": "Advisor-student assignment not found"}

        500 Internal Server Error: {"error": "Database error: Could not retrieve advisor-student assignment."}

8.3. Add New Advisor-Student Assignment

    Endpoint: /advisor-student

    Method: POST

    Description: Assigns a student to an academic advisor.

    Access Control: admin only.

    Request Headers:

        Content-Type: application/json

        Authorization: Bearer <Admin_JWT_Token>

    Request Body (JSON):

    {
        "advisor_id": 4, // Must be an existing user with role 'advisor'
        "student_id": 2  // Must be an existing user with role 'student'
    }

    Success Response (201 Created):

    {
        "message": "Advisor-student assignment added successfully",
        "advisor_student_id": 2
    }

    Error Responses:

        400 Bad Request: {"error": "Advisor ID and Student ID are required."} or {"error": "Invalid advisor ID or user is not an advisor."} or {"error": "Invalid student ID or user is not a student."}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: admin only"}

        409 Conflict: {"error": "This advisor is already assigned to this student."}

        500 Internal Server Error: {"error": "Database error: Could not add advisor-student assignment."}

8.4. Update Advisor-Student Assignment

    Endpoint: /advisor-student/{id}

    Method: PUT

    Description: Updates an existing advisor-student assignment.

    Access Control: admin only.

    Path Parameters:

        id (integer): The advisor_student_id to update.

    Request Headers:

        Content-Type: application/json

        Authorization: Bearer <Admin_JWT_Token>

    Request Body (JSON):

        Provide only the fields you wish to update.

    {
        "advisor_id": 5 // New advisor ID (must be a valid 'advisor' user)
    }

    Success Response (200 OK):

    {
        "message": "Advisor-student assignment updated successfully"
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid assignment ID"} or {"error": "No data provided for update."} or {"error": "Invalid new advisor ID or user is not an advisor."}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: admin only"}

        404 Not Found: {"error": "Advisor-student assignment not found or no changes made."}

        409 Conflict: {"error": "This advisor is already assigned to this student with the new IDs."}

        500 Internal Server Error: {"error": "Database error: Could not update advisor-student assignment."}

8.5. Delete Advisor-Student Assignment

    Endpoint: /advisor-student/{id}

    Method: DELETE

    Description: Deletes an advisor-student assignment.

    Access Control: admin only.

    Path Parameters:

        id (integer): The advisor_student_id to delete.

    Request Headers:

        Authorization: Bearer <Admin_JWT_Token>

    Success Response (200 OK):

    {
        "message": "Advisor-student assignment deleted successfully"
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid assignment ID"}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: admin only"}

        404 Not Found: {"error": "Advisor-student assignment not found."}

        500 Internal Server Error: {"error": "Database error: Could not delete advisor-student assignment."}

9. Advisor Notes Management

Endpoints for managing private notes added by academic advisors about their advisees.
9.1. Get All Advisor Notes

    Endpoint: /advisor-notes

    Method: GET

    Description: Retrieves a list of all advisor notes.

    Access Control: admin (all), advisor (their own notes for their advisees), student (notes about themselves by their advisor).

    Request Headers:

        Authorization: Bearer <JWT_Token>

    Success Response (200 OK):

    [
        {
            "note_id": 1,
            "advisor_student_id": 1,
            "note_content": "Student discussed academic performance and plans to improve.",
            "meeting_date": "2024-06-24",
            "created_at": "2024-06-24 10:00:00",
            "updated_at": "2024-06-24 10:00:00",
            "advisor_id": 4,
            "student_id": 2,
            "advisor_name": "Ms. Carol White",
            "student_name": "Bob Johnson",
            "matric_number": "S12345"
        }
    ]

    Error Responses:

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied for this role."}

        500 Internal Server Error: {"error": "Database error: Could not retrieve advisor notes."}

9.2. Get Advisor Note by ID

    Endpoint: /advisor-notes/{id}

    Method: GET

    Description: Retrieves details for a specific advisor note.

    Access Control: admin (any), advisor (their own notes), student (notes about themselves).

    Path Parameters:

        id (integer): The note_id.

    Request Headers:

        Authorization: Bearer <JWT_Token>

    Success Response (200 OK):

    {
        "note_id": 1,
        "advisor_student_id": 1,
        "note_content": "Student discussed academic performance and plans to improve.",
        "meeting_date": "2024-06-24",
        "created_at": "2024-06-24 10:00:00",
        "updated_at": "2024-06-24 10:00:00",
        "advisor_id": 4,
        "student_id": 2,
        "advisor_name": "Ms. Carol White",
        "student_name": "Bob Johnson",
        "matric_number": "S12345"
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid note ID"}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: You can only view your own advisor notes."} or {"error": "Access denied: You can only view notes about yourself."} or {"error": "Access denied for this role."}

        404 Not Found: {"error": "Advisor note not found"}

        500 Internal Server Error: {"error": "Database error: Could not retrieve advisor note."}

9.3. Add New Advisor Note

    Endpoint: /advisor-notes

    Method: POST

    Description: Adds a new private note about a student by their advisor.

    Access Control: admin or advisor (for their own advisees).

    Request Headers:

        Content-Type: application/json

        Authorization: Bearer <Admin_JWT_Token> or Bearer <Advisor_JWT_Token>

    Request Body (JSON):

    {
        "advisor_student_id": 1, // Must be an existing advisor-student assignment ID, and for advisor, must be their own
        "note_content": "Follow-up meeting scheduled for next week to review course selection.",
        "meeting_date": "2024-07-01" // Optional
    }

    Success Response (201 Created):

    {
        "message": "Advisor note added successfully",
        "note_id": 2
    }

    Error Responses:

        400 Bad Request: {"error": "Advisor-student assignment ID and note content are required."} or {"error": "Invalid advisor-student assignment ID."}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: You can only add notes for your assigned students."} or {"error": "Access denied: Only admins and advisors can add notes."}

        500 Internal Server Error: {"error": "Database error: Could not add advisor note."}

9.4. Update Advisor Note

    Endpoint: /advisor-notes/{id}

    Method: PUT

    Description: Updates an existing advisor note.

    Access Control: admin or advisor (for their own notes).

    Path Parameters:

        id (integer): The note_id to update.

    Request Headers:

        Content-Type: application/json

        Authorization: Bearer <Admin_JWT_Token> or Bearer <Advisor_JWT_Token>

    Request Body (JSON):

        Provide only the fields you wish to update.

    {
        "note_content": "Follow-up meeting successful. Student seems more confident.",
        "meeting_date": "2024-07-01"
    }

    Success Response (200 OK):

    {
        "message": "Advisor note updated successfully"
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid note ID"} or {"error": "No data provided for update."} or {"error": "No valid fields provided for update."}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: You can only update your own advisor notes."} or {"error": "Access denied: You can only reassign notes to your own assigned students."} or {"error": "Access denied: Only admins and advisors can update notes."}

        404 Not Found: {"error": "Advisor note not found or no changes made."}

        500 Internal Server Error: {"error": "Database error: Could not update advisor note."}

9.5. Delete Advisor Note

    Endpoint: /advisor-notes/{id}

    Method: DELETE

    Description: Deletes an advisor note.

    Access Control: admin or advisor (for their own notes).

    Path Parameters:

        id (integer): The note_id to delete.

    Request Headers:

        Authorization: Bearer <Admin_JWT_Token> or Bearer <Advisor_JWT_Token>

    Success Response (200 OK):

    {
        "message": "Advisor note deleted successfully"
    }

    Error Responses:

        400 Bad Request: {"error": "Invalid note ID"}

        401 Unauthorized: (JWT errors)

        403 Forbidden: {"error": "Access denied: You can only delete your own advisor notes."} or {"error": "Access denied: Only admins and advisors can delete notes."}

        404 Not Found: {"error": "Advisor note not found."}

        500 Internal Server Error: {"error": "Database error: Could not delete advisor note."}
