<?php

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

try {
    // Fetch headers and extract the Authorization token
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? null;
    $id_maestro = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    // Validate the maestro ID
    if ($id_maestro === false) {
        throw new Exception('ID del maestro inválido. Debe ser un número entero positivo.');
    }

    // Validate the JWT token
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

    // Check for connection errors
    if ($connection->connect_error) {
        throw new Exception('Cannot connect to database: ' . $connection->connect_error);
    }

    // SQL query to retrieve tesis related to the specified maestro
    $sql = "SELECT tesis.id, tesis.id_autor, tesis.id_coordinacion, tesis.id_pronace, tesis.id_grado, tesis.titulo, tesis.fecha, tesis.checked, comites_directivos.id_rol_tesis, comites_directivos.id AS id_directivo
        FROM tesis
        INNER JOIN comites_directivos ON comites_directivos.id_tesis = tesis.id
        WHERE comites_directivos.id_maestro = ?";

    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $connection->error);
    }

    // Bind parameters and execute the query
    $stmt->bind_param('i', $id_maestro);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false) {
        throw new Exception('Get result failed: ' . $stmt->error);
    }

    // Fetch results into an array
    $tesis_maestro = [];
    while ($row = $result->fetch_assoc()) {
        // Convert checked to boolean
        $row['checked'] = isset($row['checked']) ? (bool)$row['checked'] : false;
        $tesis_maestro[] = $row;
    }

    // Close statement and connection
    $stmt->close();
    $connection->close();

    // Successful response
    echo json_encode([
        'success' => true,
        'message' => 'Tesis del maestro obtenidas.',
        'tesis_maestro' => $tesis_maestro,
    ]);

} catch (Exception $e) {
    // Error handling response
    //http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'tesis_maestro' => [],
    ]);
    error_log($e->getMessage());
}
?>
