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

    // Obtener el ID de unidad de la solicitud GET
    $id_unidad = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
    if ($id_unidad === 0) {
        throw new Exception('ID de unidad inválido');
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

    // Lógica basada en roles para la eliminación
    switch ($decoded_jwt->rol) {
        case 'docente':
            // Eliminar solo si el docente está vinculado al curso
            $sql = "
                DELETE u
                FROM unidades u
                INNER JOIN roles_cursos rc ON u.id_curso = rc.id_curso
                WHERE u.id = ? AND rc.id_maestro = ?
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Falló la preparación de la consulta: ' . $connection->error);
            }

            // Vincular parámetros: (id_unidad, id_maestro)
            $stmt->bind_param('ii', $id_unidad, $decoded_jwt->sub);
            break;

        case 'god':
            // Eliminar sin restricciones para el rol 'god'
            $sql = "DELETE FROM unidades WHERE id = ?";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Falló la preparación de la consulta: ' . $connection->error);
            }

            // Vincular parámetro: (id_unidad)
            $stmt->bind_param('i', $id_unidad);
            break;

        default:
            // Acceso denegado para otros roles
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Falta de permisos',
            ]);
            exit();
    }

    // Ejecutar la consulta
    if (!$stmt->execute()) {
        throw new Exception('Falló la ejecución: ' . $stmt->error);
    }

    // Verificar si se eliminó una fila
    if ($stmt->affected_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró la unidad o no se realizó ningún cambio',
        ]);
        exit();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Unidad eliminada exitosamente',
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
