<?php

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

try {
    // Obtener el encabezado de autorización
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? null;
    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        throw new Exception('Falta el token de autenticación');
    }

    // Extraer y decodificar el JWT
    $jwt = str_replace('Bearer ', '', $authHeader);
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];

    try {
        $decoded_jwt = JWT::decode($jwt, new Key($secretKey, 'HS256'));
    } catch (Exception $e) {
        throw new Exception('Token de autenticación inválido o expirado');
    }

    // Obtener el ID del tema de la solicitud GET
    $id_tema = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
    if ($id_tema === 0) {
        throw new Exception('ID de tema inválido');
    }

    // Conexión a la base de datos
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

    // Preparar la consulta SQL para eliminar el tema
    $sql = "DELETE FROM temas WHERE id = ?";
    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Falló la preparación de la consulta: ' . $connection->error);
    }

    // Vincular el parámetro: (id_tema)
    $stmt->bind_param('i', $id_tema);

    // Ejecutar la consulta
    if (!$stmt->execute()) {
        throw new Exception('Falló la ejecución: ' . $stmt->error);
    }

    // Verificar si se eliminó una fila
    if ($stmt->affected_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró el tema o no se realizó ningún cambio',
        ]);
        exit();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Tema eliminado exitosamente',
    ]);

    // Cerrar la declaración
    $stmt->close();

} catch (Exception $e) {
    // Manejar excepciones y responder con detalles del error
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
    error_log($e->getMessage());

} finally {
    // Asegurarse de que la conexión esté cerrada
    if (isset($connection) && $connection) {
        $connection->close();
    }
}
