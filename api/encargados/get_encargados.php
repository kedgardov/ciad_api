<?php

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

try {
    $headers = apache_request_headers();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;

    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        throw new Exception('Authentication token is missing', 401);
    }

    $id_curso = isset($_GET['id']) ? filter_var($_GET['id'], FILTER_VALIDATE_INT) : 0;
    if ($id_curso === 0) {
        throw new Exception('Invalid get parameters', 400);
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
        throw new Exception('Cannot connect to database: ' . $connection->connect_error, 500);
    }

    $sql = "SELECT roles_cursos.*, maestros.grado, maestros.nombre, maestros.apellido, catalogo_roles.rol
            FROM roles_cursos
            INNER JOIN maestros ON roles_cursos.id_maestro = maestros.id
            INNER JOIN catalogo_roles ON roles_cursos.id_rol = catalogo_roles.id
            WHERE roles_cursos.id_curso = ? AND maestros.nombre IS NOT NULL AND maestros.apellido IS NOT NULL";

    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $connection->error, 500);
    }

    $stmt->bind_param('i', $id_curso);
    $stmt->execute();
    $result = $stmt->get_result();
    $encargados = [];
    while ($row = $result->fetch_assoc()) {
        $encargados[] = $row;
    }

    $stmt->close();
    $connection->close();

    echo json_encode([
        'success' => true,
        'message' => 'Encargados obtenidos',
        'encargados' => $encargados,
    ]);

} catch (Exception $e) {
    $status_code = $e->getCode() ?: 500;
    http_response_code($status_code);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'encargados' => [],
    ]);
    error_log($e->getMessage());
}
?>
