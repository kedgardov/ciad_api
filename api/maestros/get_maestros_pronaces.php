<?php

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

try {
    // Fetch headers and extract the Authorization token
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? null;

    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        throw new Exception('Authentication token is missing');
    }

    // Extract and decode the JWT
    $jwt = str_replace('Bearer ', '', $authHeader);

    $dotenv = Dotenv::createImmutable(__DIR__.'/../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];
    $decoded_jwt = JWT::decode($jwt, new Key($secretKey, 'HS256'));

    // Database credentials from environment
    $SERVER_NAME = $_ENV['MY_SERVERNAME'];
    $USERNAME = $_ENV['MY_USERNAME'];
    $PASSWORD = $_ENV['MY_PASSWORD'];
    $DATABASE_NAME = $_ENV['MY_DB_TESIS_REPO'];

    // Establish database connection
    $connection = new mysqli($SERVER_NAME, $USERNAME, $PASSWORD, $DATABASE_NAME);
    if ($connection->connect_error) {
        throw new Exception('Cannot connect to database: ' . $connection->connect_error);
    }

    // SQL query for maestros pronaces count
    $sql = "
    SELECT 
        comites_directivos.id_maestro AS id,
        COUNT(*) AS count_total,
        SUM(CASE WHEN tesis.id_pronace = 1 THEN 1 ELSE 0 END) AS count_pronace_1,
        SUM(CASE WHEN tesis.id_pronace = 2 THEN 1 ELSE 0 END) AS count_pronace_2,
        SUM(CASE WHEN tesis.id_pronace = 3 THEN 1 ELSE 0 END) AS count_pronace_3,
        SUM(CASE WHEN tesis.id_pronace = 4 THEN 1 ELSE 0 END) AS count_pronace_4,
        SUM(CASE WHEN tesis.id_pronace = 5 THEN 1 ELSE 0 END) AS count_pronace_5,
        SUM(CASE WHEN tesis.id_pronace = 6 THEN 1 ELSE 0 END) AS count_pronace_6,
        SUM(CASE WHEN tesis.id_pronace = 7 THEN 1 ELSE 0 END) AS count_pronace_7,
        SUM(CASE WHEN tesis.id_pronace = 8 THEN 1 ELSE 0 END) AS count_pronace_8,
        SUM(CASE WHEN tesis.id_pronace = 9 THEN 1 ELSE 0 END) AS count_pronace_9,
        SUM(CASE WHEN tesis.id_pronace = 10 THEN 1 ELSE 0 END) AS count_pronace_10,
        SUM(CASE WHEN tesis.id_pronace = 11 THEN 1 ELSE 0 END) AS count_pronace_11,
        SUM(CASE WHEN tesis.id_pronace = 12 THEN 1 ELSE 0 END) AS count_pronace_12,
        SUM(CASE WHEN tesis.id_pronace = 13 THEN 1 ELSE 0 END) AS count_pronace_13
    FROM comites_directivos
    INNER JOIN tesis ON comites_directivos.id_tesis = tesis.id
    GROUP BY comites_directivos.id_maestro
    ORDER BY comites_directivos.id_maestro;
    ";

    // Execute the SQL query
    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $connection->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    if ($result === false) {
        throw new Exception('Get result failed: ' . $stmt->error);
    }

    // Fetch results into an array and cast counts to integers
    $maestros_pronaces = [];
    while ($row = $result->fetch_assoc()) {
        $maestros_pronaces[] = [
            'id' => (int) $row['id'],
            'count_total' => (int) $row['count_total'],
            'count_pronace_1' => (int) $row['count_pronace_1'],
            'count_pronace_2' => (int) $row['count_pronace_2'],
            'count_pronace_3' => (int) $row['count_pronace_3'],
            'count_pronace_4' => (int) $row['count_pronace_4'],
            'count_pronace_5' => (int) $row['count_pronace_5'],
            'count_pronace_6' => (int) $row['count_pronace_6'],
            'count_pronace_7' => (int) $row['count_pronace_7'],
            'count_pronace_8' => (int) $row['count_pronace_8'],
            'count_pronace_9' => (int) $row['count_pronace_9'],
            'count_pronace_10' => (int) $row['count_pronace_10'],
            'count_pronace_11' => (int) $row['count_pronace_11'],
            'count_pronace_12' => (int) $row['count_pronace_12'],
            'count_pronace_13' => (int) $row['count_pronace_13'],
        ];
    }

    $stmt->close();
    $connection->close();

    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'Maestros Pronaces obtenidos',
        'maestros_pronaces' => $maestros_pronaces,
    ]);

} catch (Exception $e) { 
    //http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'maestros_pronaces' => [],
    ]);
}

?>
