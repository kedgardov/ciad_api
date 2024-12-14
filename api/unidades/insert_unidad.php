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
    $unidad = isset($input['unidad']) ? $input['unidad'] : null;
    if (!$unidad) {
        throw new Exception('Unidad data inválida');
    }

    $numero = isset($unidad['numero']) ? filter_var($unidad['numero'], FILTER_VALIDATE_INT) : 0;
    $titulo = isset($unidad['unidad']) ? trim(htmlspecialchars($unidad['unidad'], ENT_QUOTES, 'UTF-8')) : '';
    $id_curso = isset($unidad['id_curso']) ? filter_var($unidad['id_curso'], FILTER_VALIDATE_INT) : 0;

    if ($numero === 0 || $titulo === '' || $id_curso === 0) {
        throw new Exception('Datos invalidos');
    }

    $jwt = str_replace('Bearer ', '', $authHeader);
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];

    try {
        $decoded_jwt = JWT::decode($jwt, new Key($secretKey, 'HS256'));
    } catch (Exception $e) {
        throw new Exception('Invalid or expired authentication token');
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $connection = new mysqli(
        $_ENV['MY_SERVERNAME'],
        $_ENV['MY_USERNAME'],
        $_ENV['MY_PASSWORD'],
        $_ENV['MY_DB_NAME']
    );

    switch ($decoded_jwt->rol) {
        case 'docente':
            $sql = "
                INSERT INTO unidades (numero, unidad, id_curso)
                SELECT ?, ?, ?
                FROM roles_cursos
                WHERE id_curso = ? AND id_maestro = ?
                LIMIT 1;
            ";
            $stmt = $connection->prepare($sql);
            $stmt->bind_param('isiii', $numero, $titulo, $id_curso, $id_curso, $decoded_jwt->sub);
            break;

        case 'god':
            $sql = "
                INSERT INTO unidades (numero, unidad, id_curso)
                VALUES (?, ?, ?)
            ";
            $stmt = $connection->prepare($sql);
            $stmt->bind_param('isi', $numero, $titulo, $id_curso);
            break;

        default:
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Falta de permisos',
            ]);
            exit();
    }

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
        'message' => 'Encargado del curso insertado exitosamente',
        'id' => $connection->insert_id,
    ]);

    $stmt->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'id' => 0,
    ]);
    error_log($e->getMessage());

} finally {
    if (isset($connection) && $connection) {
        $connection->close();
    }
}
