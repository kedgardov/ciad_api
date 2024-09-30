<?php

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

try {
    $headers = getallheaders();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;

    // Sanitize and validate the id_curso input
    $id_curso = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
        'options' => [
            'default' => 0, // Default value if the input is not valid
            'min_range' => 1 // Ensuring only positive integers
        ]
    ]);

    if ($id_curso === 0) {
        throw new Exception('Curso invalido');
    }

    // Check if Authorization header is present and valid
    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        throw new Exception('Authentication token is missing or invalid');
    }

    // Extract the JWT from the Authorization header
    $jwt = str_replace('Bearer ', '', $authHeader);

    // Validate JWT format (simple check for presence of three segments)
    if (count(explode('.', $jwt)) !== 3) {
        throw new Exception('Invalid JWT format');
    }

    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];

    // Decode the JWT
    try {
        $decoded_jwt = JWT::decode($jwt, new Key($secretKey, 'HS256'));
    } catch (Exception $e) {
        throw new Exception('Failed to decode JWT: ' . $e->getMessage());
    }

    $SERVER_NAME = $_ENV['MY_SERVERNAME'];
    $USERNAME = $_ENV['MY_USERNAME'];
    $PASSWORD = $_ENV['MY_PASSWORD'];
    $DATABASE_NAME = $_ENV['MY_DB_NAME'];

    $connection = new mysqli($SERVER_NAME, $USERNAME, $PASSWORD, $DATABASE_NAME);

    if ($connection->connect_error) {
        throw new Exception('Cannot connect to database: ' . $connection->connect_error);
    }

    $sql = "SELECT opciones_terminales_cursos.* FROM opciones_terminales_cursos
            WHERE opciones_terminales_cursos.id_curso = ?";

    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $connection->error);
    }

    // Bind parameters with validated and sanitized inputs
    $stmt->bind_param('i', $id_curso);

    if (!$stmt->execute()) {
        throw new Exception('Execution failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    if ($result === false) {
        throw new Exception('Get result failed: ' . $stmt->error);
    }

    $opciones_terminales = [];
    while ($row = $result->fetch_assoc()) {
        $opciones_terminales[] = $row;
    }

    $stmt->close();
    $connection->close();

    echo json_encode([
        'success' => true,
        'message' => 'Opciones Terminales obtenidas'.$id_curso.$decoded_jwt->sub,
        'opciones_terminales' => $opciones_terminales,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'opciones_terminales' => null,
    ]);
    error_log($e->getMessage());
}
?>
