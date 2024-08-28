<?php

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null) {
        throw new Exception('Invalid JSON input');
    }

    if (!isset($input['username']) || !isset($input['password'])) {
        throw new Exception('Invalid input data');
    }

    $username = $input['username'];
    $password = $input['password'];

    $dotenv = Dotenv::createImmutable(__DIR__.'/../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];
    $SERVER_NAME = $_ENV['MY_SERVERNAME'];
    $USERNAME = $_ENV['MY_USERNAME'];
    $PASSWORD = $_ENV['MY_PASSWORD'];
    $DATABASE_NAME = $_ENV['MY_DB_NAME'];

    $connection = new mysqli($SERVER_NAME, $USERNAME, $PASSWORD, $DATABASE_NAME);

    if ($connection->connect_error) {
        throw new Exception('Cannot connect to database: ' . $connection->connect_error);
    }

    $sql = "SELECT id, grado, nombre, apellido FROM maestros WHERE usuario = ? AND password = ?";
    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $connection->error);
    }

    $stmt->bind_param('ss', $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        echo json_encode([
            'success' => false,
            'message' => 'Usuario o contraseña incorrecta',
        ]);
        $stmt->close();
        $connection->close();
        exit();
    }

    $row = $result->fetch_assoc();
    $label = $row['grado'].' '.$row['nombre'].' '.$row['apellido'];
    $id_maestro = $row['id'];

    $stmt->close();
    $connection->close();

    $payload = [
        'iss' => 'example.com',
        'sub' => $id_maestro,
        'exp' => time() + 24 * 3600, // 24 hours expiration
        'rol' => 'admin',
    ];

    $jwt = JWT::encode($payload, $secretKey, 'HS256');

    setcookie('authToken', $jwt, [
        'expires' => $payload['exp'],
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Strict',
     ]);

    echo json_encode([
        "success" => true,
        "message" => "Login successful",
        'label' => $label,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'label' => '',
    ]);
    error_log($e->getMessage());
}
?>
