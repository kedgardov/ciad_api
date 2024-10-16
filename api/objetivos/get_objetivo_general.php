<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

try {
    $headers = apache_request_headers();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;
    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        throw new Exception('Authentication token is missing');
    }

    $jwt = str_replace('Bearer ', '', $authHeader);
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];
    $decoded_jwt = JWT::decode($jwt, new Key($secretKey, 'HS256'));

    if (!isset($_GET['id'])) {
        throw new Exception('Missing id_curso');
    }

    $id_curso = filter_var($_GET['id'], FILTER_VALIDATE_INT);
    if ($id_curso === false) {
        throw new Exception('Invalid id_curso');
    }

    $SERVER_NAME = $_ENV['MY_SERVERNAME'];
    $USERNAME = $_ENV['MY_USERNAME'];
    $PASSWORD = $_ENV['MY_PASSWORD'];
    $DATABASE_NAME = $_ENV['MY_DB_NAME'];

    $connection = new mysqli($SERVER_NAME, $USERNAME, $PASSWORD, $DATABASE_NAME);
    if ($connection->connect_error) {
        throw new Exception('Cannot connect to database: ' . $connection->connect_error);
    }

    // Check for existing 'general' objetivo
    $sql = "SELECT * FROM objetivos_cursos WHERE id_curso = ? AND tipo = 'general' LIMIT 1";
    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $connection->error);
    }

    $stmt->bind_param('i', $id_curso);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $objetivo_general = $result->fetch_assoc();
    } else {
        // If no objetivo_general is found, insert a new 'general' objetivo
        $stmt->close();

        $insert_sql = "INSERT INTO objetivos_cursos (id_curso, tipo, objetivo) VALUES (?, 'general', NULL)";
        $insert_stmt = $connection->prepare($insert_sql);
        if ($insert_stmt === false) {
            throw new Exception('Prepare insert statement failed: ' . $connection->error);
        }

        $insert_stmt->bind_param('i', $id_curso);
        if ($insert_stmt->execute()) {
            // Fetch the inserted row to return
            $inserted_id = $insert_stmt->insert_id;

            $fetch_inserted_sql = "SELECT * FROM objetivos_cursos WHERE id = ? LIMIT 1";
            $fetch_stmt = $connection->prepare($fetch_inserted_sql);
            if ($fetch_stmt === false) {
                throw new Exception('Prepare fetch inserted statement failed: ' . $connection->error);
            }

            $fetch_stmt->bind_param('i', $inserted_id);
            $fetch_stmt->execute();
            $result = $fetch_stmt->get_result();
            $objetivo_general = $result->fetch_assoc();

            $fetch_stmt->close();
        } else {
            // Insertion failed
            echo json_encode([
                'success' => false,
                'message' => 'Failed to insert new Objetivo General',
                'objetivo_general' => null,
            ]);
            $insert_stmt->close();
            $connection->close();
            exit();
        }

        $insert_stmt->close();
    }

    $connection->close();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => $objetivo_general ? 'Objetivo General encontrado o insertado' : 'No Objetivo General found',
        'objetivo_general' => $objetivo_general,
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'objetivo_general' => null,
    ]);
    error_log($e->getMessage());
}
