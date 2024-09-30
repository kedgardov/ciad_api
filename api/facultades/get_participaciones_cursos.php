<?php

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

try {
    $headers = apache_request_headers();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;

    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        throw new Exception('Authentication token is missing or invalid');
    }

    // Extract the JWT from the Authorization header
    $jwt = str_replace('Bearer ', '', $authHeader);

    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];

    // Decode the JWT
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
                ROW_NUMBER() OVER(ORDER BY roles_cursos.id_maestro) AS id,
                roles_cursos.id_maestro,
                roles_cursos.id_rol,
                roles_cursos.id_curso,
                opciones_terminales_cursos.id_opcion_terminal,
                opciones_terminales_cursos.id_programa,
                opciones_terminales_cursos.id_nivel_curricular
            FROM
                roles_cursos
            INNER JOIN
                opciones_terminales_cursos
            ON
                roles_cursos.id_curso = opciones_terminales_cursos.id_curso
     ";

    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $connection->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false) {
        throw new Exception('Get result failed: ' . $stmt->error);
    }

    $participaciones_cursos = [];
    while ($row = $result->fetch_assoc()) {
        $participaciones_cursos[] = $row;
    }

    $stmt->close();
    $connection->close();

    echo json_encode([
        'success' => true,
        'message' => 'Participaciones Cursos obtenidas',
        'participaciones_cursos' => $participaciones_cursos,
    ]);

} catch (Exception $e) {
    //http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'participaciones_cursos' => [],
    ]);
    error_log($e->getMessage());
}
?>
