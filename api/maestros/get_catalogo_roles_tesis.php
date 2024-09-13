<?php

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

try {
    // Fetch headers and extract the Authorization token
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? null;

    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        throw new Exception('Authentication token is missing.');
    }

    // Extract and decode the JWT
    $jwt = str_replace('Bearer ', '', $authHeader);

    // Load environment variables
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];
    $decoded_jwt = JWT::decode($jwt, new Key($secretKey, 'HS256'));

    // Database connection setup
    $SERVER_NAME = $_ENV['MY_SERVERNAME'];
    $USERNAME = $_ENV['MY_USERNAME'];
    $PASSWORD = $_ENV['MY_PASSWORD'];
    $DATABASE_NAME = $_ENV['MY_DB_TESIS_REPO'];

    $connection = new mysqli($SERVER_NAME, $USERNAME, $PASSWORD, $DATABASE_NAME);

    if ($connection->connect_error) {
        throw new Exception('Cannot connect to database: ' . $connection->connect_error);
    }

    // SQL query to retrieve catalogo_roles_tesis
    $sql = "SELECT * FROM catalogo_roles_tesis";
    $stmt = $connection->prepare($sql);

    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $connection->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false) {
        throw new Exception('Get result failed: ' . $stmt->error);
    }

    // Fetching the results into an array
    $catalogo_roles_tesis = [];
    while ($row = $result->fetch_assoc()) {
        $catalogo_roles_tesis[] = $row;
    }

    $stmt->close();
    $connection->close();

    // Successful response
    echo json_encode([
        'success' => true,
        'message' => 'Catálogo de roles de tesis obtenido.',
        'catalogo_roles_tesis' => $catalogo_roles_tesis,
    ]);

} catch (Exception $e) {
    // Sending the error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'catalogo_roles_tesis' => [],
    ]);
    error_log($e->getMessage());  // Log the error message
}
?>
