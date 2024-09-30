<?php

// Add these lines at the top of your PHP file
//header('Access-Control-Allow-Origin: *'); // Allow requests from any origin (change * to specific origin in production)
header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS'); // Allow specific HTTP methods
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Allow specific headers
header('Content-Type: application/json'); // Ensure the response is JSON

// Handle the preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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

    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];
    $decoded_jwt = JWT::decode($jwt, new Key($secretKey, 'HS256'));

    // Handle incoming data
    $input = json_decode(file_get_contents('php://input'), true);
    $id_tesis = isset($input['id_tesis']) ? filter_var($input['id_tesis'], FILTER_VALIDATE_INT) : false;
    $tesisData = isset($input['tesis']) ? $input['tesis'] : null;

    if ($id_tesis === false || !$tesisData) {
        throw new Exception('Invalid input parameters', 400);
    }

    // Extract individual fields from tesisData
    $id_autor = filter_var($tesisData['id_autor'], FILTER_VALIDATE_INT);
    $id_coordinacion = filter_var($tesisData['id_coordinacion'], FILTER_VALIDATE_INT);
    $id_pronace = filter_var($tesisData['id_pronace'], FILTER_VALIDATE_INT);
    $id_grado = filter_var($tesisData['id_grado'], FILTER_VALIDATE_INT);
    $id_opcion_terminal = filter_var($tesisData['id_opcion_terminal'], FILTER_VALIDATE_INT);
    $titulo = filter_var($tesisData['titulo'], FILTER_SANITIZE_STRING);
    $fecha = filter_var($tesisData['fecha'], FILTER_SANITIZE_STRING);
    $palabras_clave = filter_var($tesisData['palabras_clave'], FILTER_SANITIZE_STRING);
    $resumen = filter_var($tesisData['resumen'], FILTER_SANITIZE_STRING);

    // Check for any validation issues
    if (!$id_autor || !$id_coordinacion || !$id_pronace || !$id_grado || !$id_opcion_terminal || !$titulo || !$fecha || !$palabras_clave || !$resumen) {
        throw new Exception('Invalid tesis data provided', 400);
    }

    $SERVER_NAME = $_ENV['MY_SERVERNAME'];
    $USERNAME = $_ENV['MY_USERNAME'];
    $PASSWORD = $_ENV['MY_PASSWORD'];
    $DATABASE_NAME = $_ENV['MY_DB_TESIS_REPO'];

    $connection = new mysqli($SERVER_NAME, $USERNAME, $PASSWORD, $DATABASE_NAME);

    if ($connection->connect_error) {
        throw new Exception('Cannot connect to database: ' . $connection->connect_error);
    }

    // Prepare the SQL UPDATE statement
    $sql = "UPDATE tesis SET id_autor = ?, id_coordinacion = ?, id_pronace = ?, id_grado = ?, id_opcion_terminal = ?, titulo = ?, fecha = ?, palabras_clave = ?, resumen = ?, checked = 1 WHERE id = ?";
    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $connection->error);
    }

    $stmt->bind_param('iiiiissssi',
                      $id_autor,
                      $id_coordinacion,
                      $id_pronace,
                      $id_grado,
                      $id_opcion_terminal,
                      $titulo,
                      $fecha,
                      $palabras_clave,
                      $resumen,
                      $id_tesis);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception('No rows updated, check if the provided id exists', 404);
    }

    $stmt->close();
    $connection->close();

    echo json_encode([
        'success' => true,
        'message' => 'Tesis updated successfully',
    ]);

} catch (Exception $e) {
    //http_response_code($e->getCode() > 0 ? $e->getCode() : 500);

    echo json_encode([
        'success' => false,
        'message' => $id_tesis . $e->getMessage(),
        //'message' => 'An error occurred while processing your request.', // More generic message
    ]);
    //error_log($e->getMessage());
}
?>
