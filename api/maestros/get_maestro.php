<?php

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

try {
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? null;

    // Validate id_maestro as an integer using filter_var
    $id_maestro = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]);

    if ($id_maestro === false) {
        throw new Exception('Maestro inválido. El ID debe ser un número entero positivo.');
    }

    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        throw new Exception('Authentication token is missing.');
    }

    // Extract the JWT from the Authorization header
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
    $DATABASE_NAME = $_ENV['MY_DB_NAME'];

    $connection = new mysqli($SERVER_NAME, $USERNAME, $PASSWORD, $DATABASE_NAME);

    if ($connection->connect_error) {
        throw new Exception('Cannot connect to database: ' . $connection->connect_error);
    }

    // SQL statement
    $sql = "SELECT id, grado, nombre, apellido, email, institucion_trabajo FROM maestros WHERE id = ?";
    $stmt = $connection->prepare($sql);

    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $connection->error);
    }

    $stmt->bind_param('i', $id_maestro);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false) {
        throw new Exception('Get result failed: ' . $stmt->error);
    }

    // Check if the result is empty
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró el maestro.',
            'maestro' => null,
        ]);
        exit();
    }

    // Fetch the maestro data
    $maestro = $result->fetch_assoc();

    $stmt->close();
    $connection->close();

    $maestro['label'] = $maestro['grado'] . ' ' . $maestro['nombre'] . ' ' . $maestro['apellido'];

    // Successful response
    echo json_encode([
        'success' => true,
        'message' => 'Maestro obtenido.',
        'maestro' => $maestro,
    ]);

} catch (Exception $e) {
    // Log error and return response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'maestro' => null,
    ]);
    error_log($e->getMessage());
}
?>
