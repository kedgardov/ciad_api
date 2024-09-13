<?php

// Add these lines at the top of your PHP file
//header('Access-Control-Allow-Origin: http://localhost:3000'); // Allow requests from any origin (change * to specific origin in production)
header('Access-Control-Allow-Methods: GET,HEAD, POST, PATCH, OPTIONS'); // Allow specific HTTP methods
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Allow specific headers


//pendiente



// Handle the preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require '../../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

try {
    $headers = apache_request_headers();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;

    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        throw new Exception('Authentication token is missing');
    }

    // Extract the JWT from the Authorization header
    $jwt = str_replace('Bearer ', '', $authHeader);

    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];
    $decoded_jwt = JWT::decode($jwt, new Key($secretKey, 'HS256'));

 
   
    $id_tesis = isset($_GET['id_tesis']) ? filter_var($_GET['id_tesis'], FILTER_VALIDATE_INT) : false;
    if ($id_tesis === false || !$tesisData) {
        throw new Exception('Invalid input parameters', 400);
    }

    $SERVER_NAME = $_ENV['MY_SERVERNAME'];
    $USERNAME = $_ENV['MY_USERNAME'];
    $PASSWORD = $_ENV['MY_PASSWORD'];
    $DATABASE_NAME = $_ENV['MY_DB_TESIS_REPO'];

    $connection = new mysqli($SERVER_NAME, $USERNAME, $PASSWORD, $DATABASE_NAME);

    if ($connection->connect_error) {
        throw new Exception('Cannot connect to database: ' . $connection->connect_error);
    }

    // Prepare the SQL UPDATE statement
    $sql = "SELECT pdf_blob FROM tesis WHERE id = ?";
    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $connection->error);
    }

    $stmt->bind_param('i',$id_tesis);
    $stmt->execute();
    $stmt->bind_result($pdfData);
    $stmt->fetch();

    if ( !$pdfData ){
        throw new Exception('No se encontro tesis', 404);
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="tesis_' . $id_tesis . '.pdf"');

    // Output the PDF data
    echo $pdfData;

    $stmt->close();
    $connection->close();

} catch (Exception $e) {
    header('Content-Type: application/json'); // Ensure the response is JSON
    http_response_code($e->getCode() > 0 ? $e->getCode() : 500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
?>
