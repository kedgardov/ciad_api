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

    // Extract the JWT from the Authorization header
    $jwt = str_replace('Bearer ', '', $authHeader);

    $dotenv = Dotenv::createImmutable(__DIR__.'/../../../');
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

    $sql = "SELECT id, titulo, resumen, id_coordinacion_2 FROM tesis";

    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $connection->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false) {
        throw new Exception('Get result failed: ' . $stmt->error);
    }


    $catalogo_tesis = [];
    while( $row = $result->fetch_assoc() ){
        $catalogo_tesis[] = $row;
    }

    $stmt->close();
    $connection->close();

    echo json_encode([
        'success' => true,
        'message' => 'Tesis obtenidas',
        'catalogo_tesis' => $catalogo_tesis,
    ]);

} catch (Exception $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'catalogo_tesis' => [],
    ]);
    error_log($e->getMessage());
}
?>
