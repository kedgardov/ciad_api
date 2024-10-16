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
    $objetivo = isset($input['objetivo']) ? $input['objetivo'] : null;
    if (!$objetivo) {
        throw new Exception('Datos del objetivo inválidos');
    }

    // Use isset check and filter inputs, assign default 0 if not set
    $id_objetivo = isset($objetivo['id']) ? filter_var($objetivo['id'], FILTER_VALIDATE_INT) : 0;
    $id_curso = isset($objetivo['id_curso']) ? filter_var($objetivo['id_curso'], FILTER_VALIDATE_INT) : 0;
    $nuevo_objetivo = isset($objetivo['objetivo']) ? filter_var($objetivo['objetivo'], FILTER_SANITIZE_STRING) : null;

    if ($id_objetivo === 0 || $id_curso === 0) {
        throw new Exception('Invalid id or id_curso');
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
                UPDATE objetivos_cursos
                INNER JOIN roles_cursos AS rc
                ON rc.id_curso = objetivos_cursos.id_curso
                SET objetivos_cursos.objetivo = ?
                WHERE objetivos_cursos.id = ? AND rc.id_maestro = ? AND objetivos_cursos.id_curso = ?
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Prepare statement failed: ' . $connection->error);
            }

            // Bind the parameters (nuevo_objetivo, id_objetivo, decoded_jwt->sub, id_curso)
            $stmt->bind_param('siii', $nuevo_objetivo, $id_objetivo, $decoded_jwt->sub, $id_curso);
            break;

        case 'god':
            // Update unconditionally for 'god'
            $sql = "
                UPDATE objetivos_cursos
                SET objetivo = ?
                WHERE id = ?
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Prepare statement failed: ' . $connection->error);
            }

            // Bind the parameters for god (nuevo_objetivo, id_objetivo)
            $stmt->bind_param('si', $nuevo_objetivo, $id_objetivo);
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
            'message' => 'Objetivo actualizado exitosamente',
        ]);
    }

    // Close the statement
    $stmt->close();

} catch (Exception $e) {
    // Handle any exceptions and respond with error details
    echo json_encode([
        'success' => false,
        'message' => $jwt,
    ]);

} finally {
    // Ensure the connection is closed, even in case of an error
    if (isset($connection) && $connection) {
        $connection->close();
    }
}
