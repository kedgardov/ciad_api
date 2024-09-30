<?php

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

try {
    // Read input
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null) {
        throw new Exception('Invalid JSON input');
    }

    if (!isset($input['username']) || !isset($input['password'])) {
        throw new Exception('Invalid input data');
    }

    $username = $input['username'];
    $password = $input['password'];

    // Load environment variables
    $dotenv = Dotenv::createImmutable(__DIR__.'/../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];
    $SERVER_NAME = $_ENV['MY_SERVERNAME'];
    $USERNAME = $_ENV['MY_USERNAME'];
    $PASSWORD = $_ENV['MY_PASSWORD'];
    $DATABASE_NAME = $_ENV['MY_DB_NAME'];

    // Establish connection
    $connection = new mysqli($SERVER_NAME, $USERNAME, $PASSWORD, $DATABASE_NAME);
    if ($connection->connect_error) {
        throw new Exception('Cannot connect to database: ' . $connection->connect_error);
    }

    // Unified query with roles
    $sql = "
        SELECT id, grado, nombre, apellido, 'docente' AS role FROM maestros WHERE usuario = ? AND password = ?
        UNION
        SELECT id, grado, nombre, apellido, 'admin' AS role FROM administrativos WHERE usuario = ? AND password = ?
        UNION
        SELECT id, grado, nombre, apellido, 'god' AS role FROM gods WHERE usuario = ? AND password = ?
    ";

    // Prepare the statement
    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $connection->error);
    }

    // Bind parameters (binding for all three union queries)
    $stmt->bind_param('ssssss', $username, $password, $username, $password, $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        // User not found
        echo json_encode([
            'success' => false,
            'message' => 'Usuario o contraseña incorrecta',
        ]);
        $stmt->close();
        $connection->close();
        exit();
    }

    // Get the user data
    $row = $result->fetch_assoc();
    $id = $row['id'];
    $rol = $row['role'];
    $label = $row['grado'].' '.$row['nombre'].' '.$row['apellido'];

    $stmt->close();
    $connection->close();

    // Create JWT payload
    $payload = [
        'iss' => 'course-tools',
        'sub' => $id,
        'exp' => time() + 24 * 3600, // 24 hours expiration
        'rol' => $rol,
    ];

    // Encode the JWT
    $jwt = JWT::encode($payload, $secretKey, 'HS256');

    // Set the cookie
    setcookie('authToken', $jwt, [
        'expires' => $payload['exp'],
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'label' => $label,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'label' => '',
    ]);
    error_log($e->getMessage());
}
