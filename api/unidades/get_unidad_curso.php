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
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];

    // Decode the JWT
    $decoded_jwt = JWT::decode($jwt, new Key($secretKey, 'HS256'));

    // Check if idUnidad is provided in GET request and sanitize it
    if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
        throw new Exception('Invalid or missing unidad ID');
    }
    $idUnidad = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

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

    // SQL query to select a single unidad by idUnidad
    $sql = "SELECT * FROM unidades WHERE id = ?";

    // Prepare the SQL statement
    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $connection->error);
    }

    // Bind the unidad ID to the query
    $stmt->bind_param('i', $idUnidad);

    // Execute the statement
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false) {
        throw new Exception('Get result failed: ' . $stmt->error);
    }

    // Fetch the single unidad row, or set as null if not found
    $unidad = $result->fetch_assoc() ?: null;

    // Close statement and connection
    $stmt->close();
    $connection->close();

    // Return the result as JSON
    echo json_encode([
        'success' => true,
        'message' => $unidad ? 'Unidad obtenida con éxito' : 'No se encontró la unidad',
        'unidad' => $unidad,
    ]);

} catch (Exception $e) {
    // Handle errors
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'unidad' => null,
    ]);
    error_log($e->getMessage());
}
