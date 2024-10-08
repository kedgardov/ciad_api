<?php

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

try {
    $headers = apache_request_headers();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;
    $id_curso = isset($_GET['id']) ? $_GET['id'] : 0;

    if($id_curso === 0) {
        throw new Exception('Curso invalido');
    }

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
    $sql = "SELECT
                cursos.*, roles_cursos.id_rol FROM cursos
            INNER JOIN
                roles_cursos
            ON
                roles_cursos.id_curso = cursos.id
            WHERE
                cursos.id = ?
           ";

    
    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $connection->error);
    }

    $stmt->bind_param('i', $id_curso);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result === false) {
        throw new Exception('Get result failed: ' . $stmt->error);
    }


    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontro el curso',
            'curso' => null,
        ]);
        exit();
    }
    $curso = $result->fetch_assoc();


    $stmt->close();
    $connection->close();
    echo json_encode([
        'success' => true,
        'message' => 'Cursos obtenidos',
        'curso' => $curso,
    ]);

} catch (Exception $e) {
    //http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'curso' => null,
    ]);
    error_log($e->getMessage());
}
?>
