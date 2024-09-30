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

    // Get the ID from the request (assuming this is passed via GET or DELETE parameters)
    if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
        throw new Exception('Invalid ID');
    }
    $id = $_GET['id'];

    // Extract and decode JWT
    $jwt = str_replace('Bearer ', '', $authHeader);
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];
    $decoded_jwt = JWT::decode($jwt, new Key($secretKey, 'HS256'));

    // Database connection
    $SERVER_NAME = $_ENV['MY_SERVERNAME'];
    $USERNAME = $_ENV['MY_USERNAME'];
    $PASSWORD = $_ENV['MY_PASSWORD'];
    $DATABASE_NAME = $_ENV['MY_DB_NAME'];

    $connection = new mysqli($SERVER_NAME, $USERNAME, $PASSWORD, $DATABASE_NAME);
    if ($connection->connect_error) {
        throw new Exception('Cannot connect to database: ' . $connection->connect_error);
    }

    // Define the SQL query based on user role
    switch ($decoded_jwt->rol) {
        case 'docente':
            // Delete only if the docente has permission based on the EXISTS condition
            $sql = "
                DELETE FROM roles_cursos
                WHERE id = ? AND id_maestro = ?
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Prepare statement failed: ' . $connection->error);
            }

            // Bind the parameters for docente (id, id_curso, decoded_jwt->sub)
            $stmt->bind_param('ii', $id, $decoded_jwt->sub);
            break;

        case 'god':
            // Delete unconditionally for god
            $sql = "
                DELETE FROM roles_cursos WHERE id = ?
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Prepare statement failed: ' . $connection->error);
            }

            // Bind the parameter for god (id)
            $stmt->bind_param('i', $id);
            break;

        case 'admin':
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Falta de permisos',
            ]);
            http_response_code(403);
            exit();
            break;
    }

    // Execute the statement and check for errors
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    // Check if any rows were affected
    if ($stmt->affected_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudo eliminar. Es posible que no tenga permisos o que no exista el registro.',
        ]);
        http_response_code(403); // Return 403 Forbidden when deletion fails due to permission
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Encargado del curso eliminado exitosamente',
        ]);
    }

    // Close the statement
    $stmt->close();

} catch (Exception $e) {
    // Handle any exceptions and respond with error details
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);

} finally {
    // Ensure the connection is closed, even in case of an error
    if (isset($connection) && $connection) {
        $connection->close();
    }
}
?>
