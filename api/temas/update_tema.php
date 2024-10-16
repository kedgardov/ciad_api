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

    // Extraer el objeto 'tema'
    $tema = $input['tema'] ?? null;
    if (!$tema) {
        throw new Exception('Datos de tema inválidos');
    }

    // Validar y sanitizar los campos
    $id_tema = filter_var($tema['id'] ?? 0, FILTER_VALIDATE_INT);
    $titulo = trim(htmlspecialchars($tema['tema'] ?? '', ENT_QUOTES, 'UTF-8'));
    $id_unidad = filter_var($tema['id_unidad'] ?? 0, FILTER_VALIDATE_INT);

    // Asegurar que los datos sean válidos
    if ($id_tema === 0 || empty($titulo) || $id_unidad === 0) {
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
            // Solo actualizar si el docente está vinculado al curso
            $sql = "
                UPDATE temas t
                INNER JOIN unidades u ON t.id_unidad = u.id
                INNER JOIN roles_cursos rc ON u.id_curso = rc.id_curso
                SET t.tema = ?
                WHERE t.id = ? AND rc.id_maestro = ?
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Falló la preparación de la consulta: ' . $connection->error);
            }

            // Vincular parámetros: (tema, id_tema, id_maestro)
            $stmt->bind_param('sii', $titulo, $id_tema, $decoded_jwt->sub);
            break;

        case 'god':
            // Actualización sin restricciones para 'god'
            $sql = "UPDATE temas SET tema = ? WHERE id = ?";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Falló la preparación de la consulta: ' . $connection->error);
            }

            // Vincular parámetros: (tema, id_tema)
            $stmt->bind_param('si', $titulo, $id_tema);
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
            'message' => 'No se encontró el tema o no se realizó ningún cambio',
        ]);
        exit();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Tema actualizado exitosamente',
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
