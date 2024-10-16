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
    $id_curso = filter_var($criterio['id_curso'] ?? 0, FILTER_VALIDATE_INT);
    $criterio_text = trim(htmlspecialchars($criterio['criterio'] ?? '', ENT_QUOTES, 'UTF-8'));
    $valor = filter_var($criterio['valor'] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);

    // Asegurar que los datos sean válidos
    if ($id_curso === 0 || empty($criterio_text) || $valor === false) {
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

    // Lógica basada en roles
    switch ($decoded_jwt->rol) {
        case 'docente':
            // Insertar solo si el docente está vinculado al curso
            $sql = "
                INSERT INTO criterios (id_curso, criterio, valor)
                SELECT ?, ?, ?
                FROM roles_cursos
                WHERE id_curso = ? AND id_maestro = ?
                LIMIT 1;
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Falló la preparación de la consulta: ' . $connection->error);
            }

            // Vincular parámetros: (id_curso, criterio, valor, id_curso, id_maestro)
            $stmt->bind_param('isiii', $id_curso, $criterio_text, $valor, $id_curso, $decoded_jwt->sub);
            break;

        case 'god':
            // Inserción sin restricciones para 'god'
            $sql = "
                INSERT INTO criterios (id_curso, criterio, valor)
                VALUES (?, ?, ?)
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Falló la preparación de la consulta: ' . $connection->error);
            }

            // Vincular parámetros: (id_curso, criterio, valor)
            $stmt->bind_param('isi', $id_curso, $criterio_text, $valor);
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

    // Verificar si se insertó una fila
    if ($stmt->affected_rows === 0) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Permiso denegado o no se pudo insertar.',
        ]);
        exit();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Criterio insertado exitosamente',
        'id' => $connection->insert_id,
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
