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

    switch($decoded_jwt->rol){
    case 'docente':
        $sql = "SELECT cursos.id, cursos.clave, cursos.nombre, roles_cursos.id_rol
            FROM cursos
            INNER JOIN roles_cursos ON roles_cursos.id_curso = cursos.id
            INNER JOIN catalogo_roles ON catalogo_roles.id = roles_cursos.id_rol
            INNER JOIN maestros ON maestros.id = roles_cursos.id_maestro
            WHERE maestros.id = ?";

        $stmt = $connection->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Prepare statement failed: ' . $connection->error);
        }

        $stmt->bind_param('i', $decoded_jwt->sub);
        break;

    case 'god':
        $sql = "SELECT cursos.id, cursos.clave, cursos.nombre FROM cursos";
        $stmt = $connection->prepare($sql);
        if ($stmt === false) {
            throw new Exception('Prepare statement failed: ' . $connection->error);
        }           
        break;

    default:
        echo json_encode([
            'success' => false,
            'message' => 'Falta de permisos',
        ]);
        http_response_code(403);
        exit();
        break;
    }

    $stmt->execute();
    $result = $stmt->get_result();
     $cursos = [];
     while ($row = $result->fetch_assoc()) {
         if( $decoded_jwt->rol === 'god'){
             $row['id_rol'] = 3;
         }
         $cursos[] = $row;
    }

    $stmt->close();
    $connection->close();

    echo json_encode([
        'success' => true,
        'message' => 'key',
        'cursos_mini' => $cursos,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'cursos_mini' => [],
    ]);
    error_log($e->getMessage());
}
?>
