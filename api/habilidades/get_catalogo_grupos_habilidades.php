<?php

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

try {
    // Get the Authorization header
    $headers = apache_request_headers();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;

    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        throw new Exception('Authentication token is missing');
    }

    // Extract the JWT from the Authorization header
    $jwt = str_replace('Bearer ', '', $authHeader);

    // Load environment variables
    $dotenv = Dotenv::createImmutable(__DIR__.'/../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];

    // Decode the JWT
    $decoded_jwt = JWT::decode($jwt, new Key($secretKey, 'HS256'));

    // Database connection variables
    $SERVER_NAME = $_ENV['MY_SERVERNAME'];
    $USERNAME = $_ENV['MY_USERNAME'];
    $PASSWORD = $_ENV['MY_PASSWORD'];
    $DATABASE_NAME = $_ENV['MY_DB_NAME'];

    // Create database connection
    $connection = new mysqli($SERVER_NAME, $USERNAME, $PASSWORD, $DATABASE_NAME);

    // Check for connection error
    if ($connection->connect_error) {
        throw new Exception('Cannot connect to database: ' . $connection->connect_error);
    }

    // SQL query to select from catalogo_grupos_habilidades
    $sql = "SELECT * FROM catalogo_grupos_habilidades";

    // Prepare the SQL statement
    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $connection->error);
    }

    // Execute the statement
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false) {
        throw new Exception('Get result failed: ' . $stmt->error);
    }

    // Fetch results
    $catalogo_grupos_habilidades = [];
    while( $row = $result->fetch_assoc() ){
        $catalogo_grupos_habilidades[] = $row;
    }

    // Close statement and connection
    $stmt->close();
    $connection->close();

    // Return the result as JSON
    echo json_encode([
        'success' => true,
        'message' => 'Grupos de habilidades obtenidos',
        'catalogo_grupos_habilidades' => $catalogo_grupos_habilidades,
    ]);

} catch (Exception $e) {
    // Handle errors
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'catalogo_grupos_habilidades' => [],
    ]);
    error_log($e->getMessage());
}
?>
