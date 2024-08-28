<?php

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

try {
    $headers = apache_request_headers();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;
    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        throw new Exception('Authentication token is missing');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null) {
        throw new Exception('Invalid JSON input');
    }

    $id_curso = isset($input['id_curso']) ? filter_var($input['id_curso'], FILTER_VALIDATE_INT) : 0;
    $id_coordinacion = isset($input['id_coordinacion']) ? filter_var($input['id_coordinacion'], FILTER_VALIDATE_INT) : 0;
    if ($id_curso === 0 || $id_coordinacion === 0) {
        throw new Exception('Invalid coordinacionData fields');
    }

    $jwt = str_replace('Bearer ', '', $authHeader);
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];
    $decoded_jwt = JWT::decode($jwt, new Key($secretKey, 'HS256'));

    $SERVER_NAME = $_ENV['MY_SERVERNAME'];
    $USERNAME = $_ENV['MY_USERNAME'];
    $PASSWORD = $_ENV['MY_PASSWORD'];
    $DATABASE_NAME = $_ENV['MY_DB_NAME'];

    $connection = new mysqli($SERVER_NAME, $USERNAME, $PASSWORD, $DATABASE_NAME);

    if ($connection->connect_error) {
        throw new Exception('Cannot connect to database: ' . $connection->connect_error);
    }

    $sql = "INSERT INTO coordinaciones_cursos (id_curso, id_coordinacion) VALUES (?, ?)";

    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $connection->error);
    }

    $stmt->bind_param('ii', $id_curso, $id_coordinacion);
    $stmt->execute();

    $inserted_id = $connection->insert_id;

    $stmt->close();
    $connection->close();
    sleep(2);

    echo json_encode([
        'success' => true,
        'message' => 'Coordinacion Agregada',
        'id' => $inserted_id,
    ]);

} catch (Exception $e) {
    //http_response_code(500);
    echo json_encode([
        'success' => true,
        'message' => $e->getMessage(),
        'id' => 0,
    ]);
}

?>
