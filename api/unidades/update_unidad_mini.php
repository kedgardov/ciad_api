<?php

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

try {
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? null;

    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        throw new Exception('Authentication token is missing');
    }

    $jwt = str_replace('Bearer ', '', $authHeader);
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];
    $decoded_jwt = JWT::decode($jwt, new Key($secretKey, 'HS256'));

    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null) {
        throw new Exception('Invalid JSON input');
    }

    $unidadMini = $input['unidad'] ?? null;
    if (!$unidadMini) {
        throw new Exception('Missing unidad data');
    }

    $id = filter_var($unidadMini['id'] ?? 0, FILTER_VALIDATE_INT);
    $unidad = trim(htmlspecialchars($unidadMini['unidad'] ?? '', ENT_QUOTES, 'UTF-8'));

    if ($id === 0 || empty($unidad)) {
        throw new Exception('Invalid id or unidad');
    }

    $connection = new mysqli(
        $_ENV['MY_SERVERNAME'],
        $_ENV['MY_USERNAME'],
        $_ENV['MY_PASSWORD'],
        $_ENV['MY_DB_NAME']
    );

    if ($connection->connect_error) {
        throw new Exception('Cannot connect to database: ' . $connection->connect_error);
    }

    switch ($decoded_jwt->rol) {
        case 'docente':
            $sql = "
                UPDATE unidades u
                INNER JOIN roles_cursos rc ON u.id_curso = rc.id_curso
                SET u.unidad = ?
                WHERE u.id = ? AND rc.id_maestro = ?
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Prepare statement failed: ' . $connection->error);
            }

            $stmt->bind_param('sii', $unidad, $id, $decoded_jwt->sub);
            break;

        case 'god':
            $sql = "
                UPDATE unidades
                SET unidad = ?
                WHERE id = ?
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Prepare statement failed: ' . $connection->error);
            }

            $stmt->bind_param('si', $unidad, $id);
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
        echo json_encode([
            'success' => false,
            'message' => 'No record found or no changes made',
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Unidad updated successfully',
        ]);
    }

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
