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

    $sql = "SELECT catalogo_pronaces.* FROM tesis
            INNER JOIN catalogo_pronaces ON tesis.id_prediccion = catalogo_pronaces.id
            WHERE tesis.id = ?";
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
            'prediccion' => null,
        ]);
        return; // Exit to prevent further execution
    }

    $prediccion = $result->fetch_assoc();
    //$prediccion['id_prediccion'] = $prediccion['id_prediccion_2']? $prediccion['id_preddiccion_2'] : $prediccion['id_prediccion'];

    $stmt->close();
    $connection->close();

    echo json_encode([
        'success' => true,
        'message' => 'Prediccion obtenida',
        'prediccion' => $prediccion,
    ]);

} catch (Exception $e) {
    //http_response_code($e->getCode() > 0 ? $e->getCode() : 500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'prediccion' => null,
    ]);
    error_log($e->getMessage());
}
?>
