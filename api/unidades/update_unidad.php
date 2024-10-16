<?php

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

try {
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? null;

    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        throw new Exception('Authentication token is missing');
    }

    $jwt = str_replace('Bearer ', '', $authHeader);
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];
    $decoded_jwt = JWT::decode($jwt, new Key($secretKey, 'HS256'));

    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null) {
        throw new Exception('Invalid JSON input');
    }

    // Extract 'unidad' object from input
    $unidad = $input['unidad'] ?? null;
    if (!$unidad) {
        throw new Exception('Missing unidad data');
    }

    // Extract and validate individual fields from 'unidad'
    $id = filter_var($unidad['id'] ?? 0, FILTER_VALIDATE_INT);
    if ($id === 0) {
        throw new Exception('Invalid or missing id');
    }

    $objetivo = htmlspecialchars($unidad['objetivo'] ?? '', ENT_QUOTES, 'UTF-8');
    $id_habilidad = filter_var($unidad['id_habilidad'] ?? null, FILTER_VALIDATE_INT);
    $id_verbo = filter_var($unidad['id_verbo'] ?? null, FILTER_VALIDATE_INT);
    $id_actividad_presencial = filter_var($unidad['id_actividad_presencial'] ?? null, FILTER_VALIDATE_INT);
    $id_actividad_tarea = filter_var($unidad['id_actividad_tarea'] ?? null, FILTER_VALIDATE_INT);
    $descripcion_actividad_presencial = htmlspecialchars($unidad['descripcion_actividad_presencial'] ?? '', ENT_QUOTES, 'UTF-8');
    $descripcion_actividad_tarea = htmlspecialchars($unidad['descripcion_actividad_tarea'] ?? '', ENT_QUOTES, 'UTF-8');
    $evidencia_presencial = htmlspecialchars($unidad['evidencia_presencial'] ?? '', ENT_QUOTES, 'UTF-8');
    $evidencia_tarea = htmlspecialchars($unidad['evidencia_tarea'] ?? '', ENT_QUOTES, 'UTF-8');

    // Database connection
    $connection = new mysqli(
        $_ENV['MY_SERVERNAME'],
        $_ENV['MY_USERNAME'],
        $_ENV['MY_PASSWORD'],
        $_ENV['MY_DB_NAME']
    );

    if ($connection->connect_error) {
        throw new Exception('Cannot connect to database: ' . $connection->connect_error);
    }

    // Role-based logic for the update
    switch ($decoded_jwt->rol) {
        case 'docente':
            // Update only if the docente is linked to the course via roles_cursos
            $sql = "
                UPDATE unidades u
                INNER JOIN roles_cursos rc ON u.id_curso = rc.id_curso
                SET u.objetivo = ?,
                    u.id_habilidad = ?,
                    u.id_verbo = ?,
                    u.id_actividad_presencial = ?,
                    u.id_actividad_tarea = ?,
                    u.descripcion_actividad_presencial = ?,
                    u.descripcion_actividad_tarea = ?,
                    u.evidencia_presencial = ?,
                    u.evidencia_tarea = ?
                WHERE u.id = ? AND rc.id_maestro = ?
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Prepare statement failed: ' . $connection->error);
            }

            $stmt->bind_param(
                'siiiissssii',
                $objetivo,
                $id_habilidad,
                $id_verbo,
                $id_actividad_presencial,
                $id_actividad_tarea,
                $descripcion_actividad_presencial,
                $descripcion_actividad_tarea,
                $evidencia_presencial,
                $evidencia_tarea,
                $id,
                $decoded_jwt->sub
            );
            break;

        case 'god':
            // Unconditional update for 'god' role
            $sql = "
                UPDATE unidades
                SET objetivo = ?,
                    id_habilidad = ?,
                    id_verbo = ?,
                    id_actividad_presencial = ?,
                    id_actividad_tarea = ?,
                    descripcion_actividad_presencial = ?,
                    descripcion_actividad_tarea = ?,
                    evidencia_presencial = ?,
                    evidencia_tarea = ?
                WHERE id = ?
            ";
            $stmt = $connection->prepare($sql);
            if ($stmt === false) {
                throw new Exception('Prepare statement failed: ' . $connection->error);
            }

            $stmt->bind_param(
                'siiiissssi',
                $objetivo,
                $id_habilidad,
                $id_verbo,
                $id_actividad_presencial,
                $id_actividad_tarea,
                $descripcion_actividad_presencial,
                $descripcion_actividad_tarea,
                $evidencia_presencial,
                $evidencia_tarea,
                $id
            );
            break;

        default:
            // Deny access for other roles
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Falta de permisos',
            ]);
            exit();
    }

    // Execute the statement
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    // Check if any row was affected
    if ($stmt->affected_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No record found or no changes made',
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Unidad updated successfully',
        ]);
    }

    $stmt->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
    error_log($e->getMessage());

} finally {
    if (isset($connection) && $connection) {
        $connection->close();
    }
}
