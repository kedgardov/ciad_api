<?php

// if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
//     http_response_code(200);
//     exit();
// }

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

class Curso {
    public $id;
    public $clave;
    public $nombre;
    public $nombre_ingles;
    public $horas_teoricas;
    public $horas_practicas;
    public $horas_independientes;
    public $horas_semana;
    public $horas_semestre;
    public $vinculo_objetivos_posgrado;

    public function __construct($input) {
        if (!isset($input['id']) || !isset($input['clave']) || !isset($input['nombre'])) {
            throw new Exception('Missing required fields');
        }

        $this->id = filter_var($input['id'], FILTER_VALIDATE_INT);
        $this->clave = filter_var($input['clave'], FILTER_SANITIZE_STRING);
        $this->nombre = filter_var($input['nombre'], FILTER_SANITIZE_STRING);
        $this->nombre_ingles = isset($input['nombre_ingles']) ? filter_var($input['nombre_ingles'], FILTER_SANITIZE_STRING) : null;
        $this->horas_teoricas = isset($input['horas_teoricas']) ? filter_var($input['horas_teoricas'], FILTER_VALIDATE_INT) : null;
        $this->horas_practicas = isset($input['horas_practicas']) ? filter_var($input['horas_practicas'], FILTER_VALIDATE_INT) : null;
        $this->horas_independientes = isset($input['horas_independientes']) ? filter_var($input['horas_independientes'], FILTER_VALIDATE_INT) : null;
        $this->horas_semana = isset($input['horas_semana']) ? filter_var($input['horas_semana'], FILTER_VALIDATE_INT) : null;
        $this->horas_semestre = isset($input['horas_semestre']) ? filter_var($input['horas_semestre'], FILTER_VALIDATE_INT) : null;
        $this->vinculo_objetivos_posgrado = isset($input['vinculo_objetivos_posgrado']) ? filter_var($input['vinculo_objetivos_posgrado'], FILTER_SANITIZE_STRING) : null;
    }

    public function getId() {
        return $this->id;
    }

    public function getClave() {
        return $this->clave;
    }

    public function getNombre() {
        return $this->nombre;
    }

    public function getNombreIngles() {
        return $this->nombre_ingles;
    }

    public function getHorasTeoricas() {
        return $this->horas_teoricas;
    }

    public function getHorasPracticas() {
        return $this->horas_practicas;
    }

    public function getHorasIndependientes() {
        return $this->horas_independientes;
    }

    public function getHorasSemana() {
        return $this->horas_semana;
    }

    public function getHorasSemestre() {
        return $this->horas_semestre;
    }

    public function getVinculoObjetivosPosgrado() {
        return $this->vinculo_objetivos_posgrado;
    }
}

try {
    $headers = apache_request_headers();
    $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : null;
    if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        throw new Exception('Authentication token is missing');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null) {
        throw new Exception('Invalid JSON input');
    }

    $curso = new Curso($input['curso']);

    $jwt = str_replace('Bearer ', '', $authHeader);
    $dotenv = Dotenv::createImmutable(__DIR__.'/../../');
    $dotenv->load();
    $secretKey = $_ENV['JWT_SECRET'];
    $decoded_jwt = JWT::decode($jwt, new Key($secretKey, 'HS256'));

    $SERVER_NAME = $_ENV['MY_SERVERNAME'];
    $USERNAME = $_ENV['MY_USERNAME'];
    $PASSWORD = $_ENV['MY_PASSWORD'];
    $DATABASE_NAME = $_ENV['MY_DB_NAME'];

    $connection = new mysqli($SERVER_NAME, $USERNAME, $PASSWORD, $DATABASE_NAME);

    if ($connection->connect_error) {
        throw new Exception('Cannot connect to database: ' . $connection->connect_error);
    }

    $sql = "UPDATE cursos SET
            nombre_ingles = ?,
            horas_teoricas = ?,
            horas_practicas = ?,
            horas_independientes = ?,
            horas_semana = ?,
            horas_semestre = ?
            WHERE id = ?";

    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $connection->error);
    }

    $stmt->bind_param(
        'siiiiii',
        $curso->getNombreIngles(),
        $curso->getHorasTeoricas(),
        $curso->getHorasPracticas(),
        $curso->getHorasIndependientes(),
        $curso->getHorasSemana(),
        $curso->getHorasSemestre(),
        $curso->getId()
    );

    $stmt->execute();

    $stmt->close();
    $connection->close();
    sleep(2);

    echo json_encode([
        'success' => true,
        'message' => 'Curso actualizado',
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
    error_log($e->getMessage());
}

?>
