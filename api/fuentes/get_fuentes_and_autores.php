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
    $idCurso = filter_var($_GET['id'], FILTER_SANITIZE_NUMBER_INT);

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
    // SQL queries
    $sqlFuentes = "SELECT * FROM fuentes WHERE id_curso = ?";
    $sqlAutores = "SELECT autores_fuentes.* FROM fuentes INNER JOIN autores_fuentes ON fuentes.id = autores_fuentes.id_fuente WHERE id_curso = ?";

    // Prepare the first statement
    $stmtFuentes = $connection->prepare($sqlFuentes);
    if ($stmtFuentes === false) {
        throw new Exception('Prepare statement failed for fuentes: ' . $connection->error);
    }
    $stmtFuentes->bind_param('i', $idCurso);

    // Execute and process the first statement
    $stmtFuentes->execute();
    $resultFuentes = $stmtFuentes->get_result();
    if ($resultFuentes === false) {
        throw new Exception('Get result failed for fuentes: ' . $stmtFuentes->error);
    }

    $fuentes = [];
    while ($row = $resultFuentes->fetch_assoc()) {
        $fuentes[] = $row;
    }

    // Free the result set and close the first statement
    $resultFuentes->free();
    $stmtFuentes->close();

    // Prepare the second statement
    $stmtAutores = $connection->prepare($sqlAutores);
    if ($stmtAutores === false) {
        throw new Exception('Prepare statement failed for autores: ' . $connection->error);
    }
    $stmtAutores->bind_param('i', $idCurso);

    // Execute and process the second statement
    $stmtAutores->execute();
    $resultAutores = $stmtAutores->get_result();
    if ($resultAutores === false) {
        throw new Exception('Get result failed for autores: ' . $stmtAutores->error);
    }

    $autores = [];
    while ($row = $resultAutores->fetch_assoc()) {
        $autores[] = $row;
    }

    // Free the result set and close the second statement
    $resultAutores->free();
    $stmtAutores->close();

    // Close the connection
    $connection->close();

    // Return the result as JSON
    echo json_encode([
        'success' => true,
        'message' => 'Información obtenida con éxito',
        'fuentes' => $fuentes,
        'autores' => $autores,
    ]);

} catch (Exception $e) {
    // Handle errors
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'fuentes' => [],
        'autores' => [],
    ]);
    // Optionally log the error
    // error_log($e->getMessage());
}
