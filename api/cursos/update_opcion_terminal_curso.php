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
    $opcionTerminalCurso = isset($input['opcionTerminalCurso']) ? $input['opcionTerminalCurso'] : null;
    if (!$opcionTerminalCurso) {
        throw new Exception('Opción Terminal Inválida');
    }

    // Use isset check and filter inputs, assign default 0 if not set
    $id = isset($opcionTerminalCurso['id']) ? filter_var($opcionTerminalCurso['id'], FILTER_VALIDATE_INT) : 0;
    $id_curso = isset($opcionTerminalCurso['id_curso']) ? filter_var($opcionTerminalCurso['id_curso'], FILTER_VALIDATE_INT) : 0;
    $id_opcion_terminal = isset($opcionTerminalCurso['id_opcion_terminal']) ? filter_var($opcionTerminalCurso['id_opcion_terminal'], FILTER_VALIDATE_INT) : 0;
    $id_programa = isset($opcionTerminalCurso['id_programa']) ? filter_var($opcionTerminalCurso['id_programa'], FILTER_VALIDATE_INT) : 0;
    $id_nivel_curricular = isset($opcionTerminalCurso['id_nivel_curricular']) ? filter_var($opcionTerminalCurso['id_nivel_curricular'], FILTER_VALIDATE_INT) : 0;

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
            $sql = "
                UPDATE
                    opciones_terminales_cursos
                INNER JOIN
                    roles_cursos
                ON
                    roles_cursos.id_curso = opciones_terminales_cursos.id_curso
                SET
                    id_opcion_terminal = ?,
                    id_nivel_curricular = ?,
                    id_programa = ?
                WHERE
                    opciones_terminales_cursos.id = ? AND
                    roles_cursos.id_maestro = ?
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Prepare statement failed: ' . $connection->error);
            }

            $stmt->bind_param(
                'iiiii',
                $id_opcion_terminal,
                $id_nivel_curricular,
                $id_programa,
                $id,
                $decoded_jwt->sub
            );
            break;

        case 'god':
            $sql = "
                UPDATE
                    opciones_terminales_cursos
                SET
                    id_opcion_terminal = ?,
                    id_nivel_curricular = ?,
                    id_programa = ?
                WHERE
                    opciones_terminales_cursos.id = ?
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Prepare statement failed: ' . $connection->error);
            }

            $stmt->bind_param(
                'iiii',
                $id_opcion_terminal,
                $id_nivel_curricular,
                $id_programa,
                $id
            );
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
            'message' => 'Algo salio mal',
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Curso actualizado',
        ]);
    }

    // Close the statement
    $stmt->close();



} catch (Exception $e) {
    // Handle any exceptions and respond with error details
    //http_response_code(500);
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
