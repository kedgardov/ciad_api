<?php

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

try {
    // Get Authorization header
    $headers = apache_request_headers();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;
    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        throw new Exception('Token de autenticación ausente');
    }

    // Decode JWT token
    $jwt = str_replace('Bearer ', '', $authHeader);
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];

    try {
        $decoded_jwt = JWT::decode($jwt, new Key($secretKey, 'HS256'));
    } catch (Exception $e) {
        throw new Exception('Token inválido o expirado');
    }

    // Get the idCurso from the query string
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        throw new Exception('ID del curso inválido');
    }
    $id_curso = (int) $_GET['id'];  // Ensure the course ID is an integer

    // Check if rol in token is 'god'
    if ($decoded_jwt->rol === 'god') {
        $roles_curso = [
            ['id' => 0, 'rol' => 'god']
        ];

        echo json_encode([
            'success' => true,
            'message' => 'Talking to a god',
            'roles_curso' => $roles_curso
        ]);
        exit();
    }

    // Database connection
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $connection = new mysqli(
        $_ENV['MY_SERVERNAME'],
        $_ENV['MY_USERNAME'],
        $_ENV['MY_PASSWORD'],
        $_ENV['MY_DB_NAME']
    );

    if ($connection->connect_error) {
        throw new Exception('No se pudo conectar a la base de datos: ' . $connection->connect_error);
    }

    // Prepare the SQL query to get roles from roles_cursos for the specific id_curso
    $id_maestro = $decoded_jwt->sub;  // Assuming 'sub' in token refers to id_maestro
    $stmt = $connection->prepare("SELECT roles_cursos.id_rol AS id, catalogo_roles.rol
                                  FROM roles_cursos
                                  INNER JOIN catalogo_roles ON catalogo_roles.id = roles_cursos.id_rol
                                  WHERE id_maestro = ? AND id_curso = ?");
    if ($stmt === false) {
        throw new Exception('Falló la preparación de la consulta: ' . $connection->error);
    }

    $stmt->bind_param('ii', $id_maestro, $id_curso);
    $stmt->execute();
    $result = $stmt->get_result();

    $roles_curso = [];
    while ($row = $result->fetch_assoc()) {
        $roles_curso[] = [
            'id' => $row['id'],
            'rol' => $row['rol']
        ];
    }

    // Close statement
    $stmt->close(); // Ensure that this is only called once

    echo json_encode([
        'success' => true,
        'message' => 'Roles retrieved successfully',
        'roles_curso' => $roles_curso
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'roles_curso' => []
    ]);
    error_log($e->getMessage());

} finally {
    // Ensure the connection is closed
    if (isset($connection) && $connection) {
        $connection->close();
    }
}
