<?php

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

try {
    // Get Authorization header
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? null;
    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        throw new Exception('Authentication token is missing');
    }

    // Read input
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null) {
        throw new Exception('Invalid JSON input');
    }

    // Extract 'tema' object
    $tema = $input['tema'] ?? null;
    if (!$tema) {
        throw new Exception('Tema data inválida');
    }

    // Validate and sanitize fields
    $numero = filter_var($tema['numero'] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
    $titulo = trim(htmlspecialchars($tema['tema'] ?? '', ENT_QUOTES, 'UTF-8'));
    $id_unidad = filter_var($tema['id_unidad'] ?? 0, FILTER_VALIDATE_INT);

    // Ensure valid data
    if ($numero === false || empty($titulo) || $id_unidad === 0) {
        throw new Exception('Datos invalidos');
    }

    // Decode JWT
    $jwt = str_replace('Bearer ', '', $authHeader);
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];

    try {
        $decoded_jwt = JWT::decode($jwt, new Key($secretKey, 'HS256'));
    } catch (Exception $e) {
        throw new Exception('Invalid or expired authentication token');
    }

    // Database connection
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $connection = new mysqli(
        $_ENV['MY_SERVERNAME'],
        $_ENV['MY_USERNAME'],
        $_ENV['MY_PASSWORD'],
        $_ENV['MY_DB_NAME']
    );

    if ($connection->connect_error) {
        throw new Exception('Cannot connect to database: ' . $connection->connect_error);
    }

    // Role-based logic
    switch ($decoded_jwt->rol) {
        case 'docente':
            $sql = "
                INSERT INTO temas (numero, tema, id_unidad)
                SELECT ?, ?, ?
                FROM roles_cursos
                INNER JOIN unidades ON unidades.id_curso = roles_cursos.id_curso
                WHERE unidades.id = ? AND roles_cursos.id_maestro = ?
                LIMIT 1;
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Prepare statement failed: ' . $connection->error);
            }

            $stmt->bind_param('isiii', $numero, $titulo, $id_unidad, $id_unidad, $decoded_jwt->sub);
            break;

        case 'god':
            $sql = "
                INSERT INTO temas (numero, tema, id_unidad)
                VALUES (?, ?, ?)
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Prepare statement failed: ' . $connection->error);
            }

            $stmt->bind_param('isi', $numero, $titulo, $id_unidad);
            break;

        default:
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Falta de permisos',
            ]);
            exit();
    }

    // Execute query and handle results
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    if ($stmt->affected_rows === 0) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Permiso denegado o no se pudo insertar.',
        ]);
        exit();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Tema inserted successfully',
        'id' => $connection->insert_id,
    ]);

    $stmt->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
    error_log($e->getMessage());

} finally {
    if (isset($connection) && $connection) {
        $connection->close();
    }
}
