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

    // Extract the JWT from the Authorization header
    $jwt = str_replace('Bearer ', '', $authHeader);

    $dotenv = Dotenv::createImmutable(__DIR__.'/../../');
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

    $sql = "SELECT cursos.id, cursos.clave, cursos.nombre FROM cursos";

    $stmt = $connection->prepare($sql);
     if ($stmt === false) {
         throw new Exception('Prepare statement failed: ' . $connection->error);
     }

     $stmt->execute();
     $result = $stmt->get_result();
     $cursos = [];
     while ($row = $result->fetch_assoc()) {
         $row['id_rol'] = 0;
         $cursos[] = $row;
    }

    $stmt->close();
    $connection->close();

    echo json_encode([
        'success' => true,
        'message' => 'Catalogo Obtenido',
        'catalogo_cursos_mini' => $cursos,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'catalogo_cursos_mini' => [],
    ]);
    error_log($e->getMessage());
}
?>
