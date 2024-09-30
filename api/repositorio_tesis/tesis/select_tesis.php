<?php

require '../../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

try {
    $headers = apache_request_headers();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;

    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        throw new Exception('Authentication token is missing');
    }

    $id_tesis = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id_tesis === false) {
        throw new Exception('Invalid get parameters', 400);
    }

    // Extract the JWT from the Authorization header
    $jwt = str_replace('Bearer ', '', $authHeader);

    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];
    $decoded_jwt = JWT::decode($jwt, new Key($secretKey, 'HS256'));

    $SERVER_NAME = $_ENV['MY_SERVERNAME'];
    $USERNAME = $_ENV['MY_USERNAME'];
    $PASSWORD = $_ENV['MY_PASSWORD'];
    $DATABASE_NAME = $_ENV['MY_DB_TESIS_REPO'];

    $connection = new mysqli($SERVER_NAME, $USERNAME, $PASSWORD, $DATABASE_NAME);

    if ($connection->connect_error) {
        throw new Exception('Cannot connect to database: ' . $connection->connect_error);
    }

    $sql = " SELECT
    id,
    id_autor,
    id_coordinacion,
    id_pronace,
    id_grado,
    id_file,
    id_opcion_terminal,
    titulo,
    fecha,
    palabras_clave,
    resumen,
    checked,
    resumen_filtered,
    id_prediccion,
    id_prediccion_2
    FROM tesis WHERE id = ?";
    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $connection->error);
    }

    $stmt->bind_param('i', $id_tesis);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false) {
        throw new Exception('Get result failed: ' . $stmt->error);
    }

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No encontrada',
            'tesis' => null,
        ]);
        return; // Exit to prevent further execution
    }

    $tesis = $result->fetch_assoc();
    $tesis['checked'] = isset($tesis['checked'])? (bool)$tesis['checked'] : false;

    $stmt->close();
    $connection->close();

    echo json_encode([
        'success' => true,
        'message' => 'Tesis obtenida',
        'tesis' => $tesis,
    ]);

} catch (Exception $e) {
    http_response_code($e->getCode() > 0 ? $e->getCode() : 500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'tesis' => null,
    ]);
    error_log($e->getMessage());
}
?>
