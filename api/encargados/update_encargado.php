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
    $encargadoCurso = isset($input['encargadoCurso']) ? $input['encargadoCurso'] : null;
    if (!$encargadoCurso) {
        throw new Exception('Encargado data inválida');
    }

    // Use isset check and filter inputs, assign default 0 if not set
    $id = isset($encargadoCurso['id']) ? filter_var($encargadoCurso['id'], FILTER_VALIDATE_INT) : 0;
    $id_rol = isset($encargadoCurso['id_rol']) ? filter_var($encargadoCurso['id_rol'], FILTER_VALIDATE_INT) : 0;
    $id_maestro = isset($encargadoCurso['id_maestro']) ? filter_var($encargadoCurso['id_maestro'], FILTER_VALIDATE_INT) : 0;
    $id_curso = isset($encargadoCurso['id_curso']) ? filter_var($encargadoCurso['id_curso'], FILTER_VALIDATE_INT) : 0;

    if ($id === 0 || $id_rol === 0 || $id_maestro === 0 || $id_curso === 0) {
        throw new Exception('Invalid id, id_rol, id_maestro, or id_curso');
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
            // Update only if the docente is linked to the course via roles_cursos
            $sql = "
                UPDATE roles_cursos
                INNER JOIN roles_cursos AS rc
                ON rc.id_curso = roles_cursos.id_curso
                SET roles_cursos.id_rol = ?, roles_cursos.id_maestro = ?
                WHERE roles_cursos.id = ? AND rc.id_maestro = ?
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Prepare statement failed: ' . $connection->error);
            }

            // Bind the parameters (id_rol, id_maestro, id, decoded_jwt->sub)
            $stmt->bind_param('iiii', $id_rol, $id_maestro, $id, $decoded_jwt->sub);
            break;

        case 'god':
            // Update unconditionally for god
            $sql = "
                UPDATE roles_cursos
                SET id_rol = ?, id_maestro = ?
                WHERE id = ?
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Prepare statement failed: ' . $connection->error);
            }

            // Bind the parameters for god (id_rol, id_maestro, id)
            $stmt->bind_param('iii', $id_rol, $id_maestro, $id);
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
            'message' => 'No se pudo actualizar. Es posible que no tenga permisos o que no exista el registro.',
        ]);
        http_response_code(403); // Return 403 Forbidden when update fails due to permission
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Encargado del curso actualizado exitosamente',
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
