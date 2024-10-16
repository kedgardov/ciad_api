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

    // Leer la entrada JSON
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null) {
        throw new Exception('Entrada JSON inválida');
    }

    // Extraer el objeto 'criterio'
    $criterio = $input['criterio'] ?? null;
    if (!$criterio) {
        throw new Exception('Datos de criterio inválidos');
    }

    // Validar y sanitizar los campos
    $id_criterio = filter_var($criterio['id'] ?? 0, FILTER_VALIDATE_INT);
    $id_curso = filter_var($criterio['id_curso'] ?? 0, FILTER_VALIDATE_INT);
    $criterio_text = trim(htmlspecialchars($criterio['criterio'] ?? '', ENT_QUOTES, 'UTF-8'));
    $valor = filter_var($criterio['valor'] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);

    // Asegurar que los datos sean válidos
    if ($id_criterio === 0 || $id_curso === 0 || empty($criterio_text) || $valor === false) {
        throw new Exception('Datos inválidos');
    }

    // Decodificar el JWT
    $jwt = str_replace('Bearer ', '', $authHeader);
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];

    try {
        $decoded_jwt = JWT::decode($jwt, new Key($secretKey, 'HS256'));
    } catch (Exception $e) {
        throw new Exception('Token de autenticación inválido o expirado');
    }

    // Conectar a la base de datos
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

    // Lógica basada en roles para la actualización
    switch ($decoded_jwt->rol) {
        case 'docente':
            // Solo actualizar si el docente está vinculado al curso
            $sql = "
                UPDATE criterios c
                INNER JOIN roles_cursos rc ON c.id_curso = rc.id_curso
                SET c.criterio = ?, c.valor = ?
                WHERE c.id = ? AND rc.id_maestro = ?
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Falló la preparación de la consulta: ' . $connection->error);
            }

            // Vincular parámetros: (criterio, valor, id_criterio, id_maestro)
            $stmt->bind_param('siii', $criterio_text, $valor, $id_criterio, $decoded_jwt->sub);
            break;

        case 'god':
            // Actualizar sin restricciones para 'god'
            $sql = "
                UPDATE criterios
                SET criterio = ?, valor = ?
                WHERE id = ?
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Falló la preparación de la consulta: ' . $connection->error);
            }

            // Vincular parámetros: (criterio, valor, id_criterio)
            $stmt->bind_param('sii', $criterio_text, $valor, $id_criterio);
            break;

        default:
            // Denegar acceso para otros roles
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

    // Verificar si se actualizó alguna fila
    if ($stmt->affected_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró el criterio o no se realizó ningún cambio',
        ]);
        exit();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Criterio actualizado exitosamente',
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
