<?php

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

try {
    // Get Authorization header
    $headers = apache_request_headers();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;

    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        throw new Exception('Authentication token is missing');
    }

    // Extract and decode JWT
    $jwt = str_replace('Bearer ', '', $authHeader);
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];
    $decoded_jwt = JWT::decode($jwt, new Key($secretKey, 'HS256'));

    // Check if idObjetivo is provided in GET request and sanitize it
    if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
        throw new Exception('Invalid or missing idObjetivo');
    }
    $idObjetivo = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

    // Database connection variables
    $SERVER_NAME = $_ENV['MY_SERVERNAME'];
    $USERNAME = $_ENV['MY_USERNAME'];
    $PASSWORD = $_ENV['MY_PASSWORD'];
    $DATABASE_NAME = $_ENV['MY_DB_NAME'];

    // Create database connection
    $connection = new mysqli($SERVER_NAME, $USERNAME, $PASSWORD, $DATABASE_NAME);
    if ($connection->connect_error) {
        throw new Exception('Cannot connect to database: ' . $connection->connect_error);
    }

    // Role-based access control
    switch ($decoded_jwt->rol) {
        case 'docente':
            // Allow deletion only if the docente is linked to the course via roles_cursos
            $sql = "
                DELETE o
                FROM objetivos_cursos o
                INNER JOIN roles_cursos rc ON o.id_curso = rc.id_curso
                WHERE o.id = ? AND rc.id_maestro = ?
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Prepare statement failed: ' . $connection->error);
            }
            // Bind parameters (idObjetivo, id_maestro)
            $stmt->bind_param('ii', $idObjetivo, $decoded_jwt->sub);
            break;

        case 'god':
            // Allow deletion unconditionally for 'god' role
            $sql = "DELETE FROM objetivos_cursos WHERE id = ?";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Prepare statement failed: ' . $connection->error);
            }
            // Bind the idObjetivo parameter
            $stmt->bind_param('i', $idObjetivo);
            break;

        case 'admin':
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Falta de permisos',
            ]);
            http_response_code(403); // 403 Forbidden
            exit();
            break;
    }

    // Execute the statement and check for errors
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    // Check if a row was affected
    if ($stmt->affected_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No record found or no changes made',
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Objetivo deleted successfully',
        ]);
    }

    // Close the statement
    $stmt->close();

} catch (Exception $e) {
    // Handle exceptions and respond with error details
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
    error_log($e->getMessage());

} finally {
    // Ensure the connection is closed
    if (isset($connection) && $connection) {
        $connection->close();
    }
}
?>
