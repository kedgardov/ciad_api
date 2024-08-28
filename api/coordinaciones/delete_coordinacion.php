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

    $id = isset($input['id']) ? filter_var($input['id'], FILTER_VALIDATE_INT) : 0;
    if ($id === 0 ) {
        throw new Exception('Invalid id');
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

    $sql = "DELETE FROM coordinaciones_cursos WHERE id = ?";

    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $connection->error);
    }

    $stmt->bind_param('i', $id);
    $stmt->execute();

    $stmt->close();
    $connection->close();
    sleep(2);

    echo json_encode([
        'success' => true,
        'message' => 'Coordinacion eliminada',
    ]);

} catch (Exception $e) {
    //http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}

?>
