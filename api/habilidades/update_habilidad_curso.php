<?php

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

try {
    // Obtener encabezado de autorización
    $headers = apache_request_headers();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;

    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        throw new Exception('Falta el token de autenticación');
    }

    // Extraer y decodificar JWT
    $jwt = str_replace('Bearer ', '', $authHeader);
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];
    $decoded_jwt = JWT::decode($jwt, new Key($secretKey, 'HS256'));

    // Leer la entrada desde el cuerpo de la solicitud PATCH
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null) {
        throw new Exception('Entrada JSON inválida');
    }

    // Validar y sanitizar la entrada
    $habilidadCurso = isset($input['habilidadCurso']) ? $input['habilidadCurso'] : null;
    if (!$habilidadCurso) {
        throw new Exception('Faltan los datos de habilidadCurso');
    }

    // Usar isset y filtrar entradas
    $id = isset($habilidadCurso['id']) ? filter_var($habilidadCurso['id'], FILTER_VALIDATE_INT) : 0;
    $id_curso = isset($habilidadCurso['id_curso']) ? filter_var($habilidadCurso['id_curso'], FILTER_VALIDATE_INT) : 0;
    $id_habilidad = isset($habilidadCurso['id_habilidad']) ? filter_var($habilidadCurso['id_habilidad'], FILTER_VALIDATE_INT) : 0;
    $id_grupo_habilidad = isset($habilidadCurso['id_grupo_habilidad']) ? filter_var($habilidadCurso['id_grupo_habilidad'], FILTER_VALIDATE_INT) : 0;

    if ($id === 0 || $id_curso === 0 || $id_habilidad === 0 || $id_grupo_habilidad === 0) {
        throw new Exception('Datos inválidos: id, id_curso, id_habilidad o id_grupo_habilidad');
    }

    // Variables de conexión a la base de datos
    $SERVER_NAME = $_ENV['MY_SERVERNAME'];
    $USERNAME = $_ENV['MY_USERNAME'];
    $PASSWORD = $_ENV['MY_PASSWORD'];
    $DATABASE_NAME = $_ENV['MY_DB_NAME'];

    $connection = new mysqli($SERVER_NAME, $USERNAME, $PASSWORD, $DATABASE_NAME);
    if ($connection->connect_error) {
        throw new Exception('No se puede conectar a la base de datos: ' . $connection->connect_error);
    }

    // Control de acceso basado en el rol
    switch ($decoded_jwt->rol) {
        case 'docente':
            // Actualizar solo si el docente está vinculado al curso a través de roles_cursos
            $sql = "
                UPDATE habilidades_cursos hc
                INNER JOIN roles_cursos rc ON hc.id_curso = rc.id_curso
                SET hc.id_habilidad = ?, hc.id_grupo_habilidad = ?
                WHERE hc.id = ? AND rc.id_maestro = ?
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Error al preparar la declaración: ' . $connection->error);
            }

            // Vincular parámetros (id_habilidad, id_grupo_habilidad, id, id_maestro)
            $stmt->bind_param('iiii', $id_habilidad, $id_grupo_habilidad, $id, $decoded_jwt->sub);
            break;

        case 'god':
            // Actualización incondicional para el rol 'god'
            $sql = "
                UPDATE habilidades_cursos
                SET id_habilidad = ?, id_grupo_habilidad = ?
                WHERE id = ?
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Error al preparar la declaración: ' . $connection->error);
            }

            // Vincular los parámetros para el rol 'god' (id_habilidad, id_grupo_habilidad, id)
            $stmt->bind_param('iii', $id_habilidad, $id_grupo_habilidad, $id);
            break;

        case 'admin':
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Falta de permisos',
            ]);
            http_response_code(403); // 403 Prohibido
            exit();
            break;
    }

    // Ejecutar la declaración y verificar errores
    if (!$stmt->execute()) {
        throw new Exception('Error al ejecutar: ' . $stmt->error);
    }

    // Verificar si se afectó alguna fila
    if ($stmt->affected_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró ningún registro o no se realizaron cambios',
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Habilidad del curso actualizada con éxito',
        ]);
    }

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
    // Asegurarse de cerrar la conexión
    if (isset($connection) && $connection) {
        $connection->close();
    }
}
