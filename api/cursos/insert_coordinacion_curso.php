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

    // Read input
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null) {
        throw new Exception('Invalid JSON input');
    }

    // Validate and sanitize input data
    $coordinacionCurso = isset($input['coordinacionCurso']) ? $input['coordinacionCurso'] : null;
    if (!$coordinacionCurso) {
        throw new Exception('Coordinacion Inválida');
    }

    // Use isset check and filter inputs, assign default 0 if not set
    $id_curso = isset($coordinacionCurso['id_curso']) ? filter_var($coordinacionCurso['id_curso'], FILTER_VALIDATE_INT) : 0;
    $id_coordinacion = isset($coordinacionCurso['id_coordinacion']) ? filter_var($coordinacionCurso['id_coordinacion'], FILTER_VALIDATE_INT) : 0;

    if ($id_curso === 0 || $id_coordinacion === 0) {
        throw new Exception('Invalid id_curso or id_coordinacion');
    }

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
            // Insert only if the docente is linked to the course via roles_cursos
            $sql = "
                INSERT INTO coordinaciones_cursos (id_curso, id_coordinacion)
                SELECT ?, ?
                WHERE EXISTS (
                    SELECT id FROM roles_cursos WHERE id_curso = ? AND id_maestro = ?
                )
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Prepare statement failed: ' . $connection->error);
            }

            // Bind the parameters (id_curso, id_coordinacion, id_curso, id_maestro)
            $stmt->bind_param('iiii', $id_curso, $id_coordinacion, $id_curso, $decoded_jwt->sub);
            break;

        case 'god':
            // Insert unconditionally for god
            $sql = "
                INSERT INTO coordinaciones_cursos (id_curso, id_coordinacion)
                VALUES (?, ?)
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Prepare statement failed: ' . $connection->error);
            }

            // Bind the parameters for god (id_curso, id_coordinacion)
            $stmt->bind_param('ii', $id_curso, $id_coordinacion);
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

    // Get the inserted ID or handle no rows affected
    if ($stmt->affected_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Permiso denegado o no se pudo insertar.',
        ]);
        http_response_code(403); // 403 Forbidden when no insert due to permission
    } else {
        $id = $connection->insert_id;
        echo json_encode([
            'success' => true,
            'message' => 'Coordinacion del Curso Insertada',
            'id' => $id,
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
    error_log($e->getMessage());

} finally {
    // Ensure the connection is closed, even in case of an error
    if (isset($connection) && $connection) {
        $connection->close();
    }
}
?>
