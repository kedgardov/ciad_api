<?php

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

class Curso {
    public $id;
    public $clave;
    public $nombre;
    public $nombre_ingles;
    public $horas_teoricas_semana;
    public $horas_practicas_semana;
    public $horas_semana;
    public $horas_teoricas_semestre;
    public $horas_practicas_semestre;
    public $horas_semestre;
    public $creditos;
    public $vinculo_objetivos_posgrado;
    public $id_modalidad;
    public $id_tipo;

    public function __construct($input) {
        if (!isset($input['id']) || !isset($input['clave']) || !isset($input['nombre'])) {
            throw new Exception('Missing required fields');
        }

        $this->id = filter_var($input['id'], FILTER_VALIDATE_INT);
        $this->clave = filter_var($input['clave'], FILTER_SANITIZE_STRING);
        $this->nombre = filter_var($input['nombre'], FILTER_SANITIZE_STRING);
        $this->nombre_ingles = isset($input['nombre_ingles']) ? filter_var($input['nombre_ingles'], FILTER_SANITIZE_STRING) : null;
        $this->horas_teoricas_semana = isset($input['horas_teoricas_semana']) ? filter_var($input['horas_teoricas_semana'], FILTER_VALIDATE_INT) : null;
        $this->horas_practicas_semana = isset($input['horas_practicas_semana']) ? filter_var($input['horas_practicas_semana'], FILTER_VALIDATE_INT) : null;
        $this->horas_semana = isset($input['horas_semana']) ? filter_var($input['horas_semana'], FILTER_VALIDATE_INT) : null;
        $this->horas_teoricas_semestre = isset($input['horas_teoricas_semestre']) ? filter_var($input['horas_teoricas_semestre'], FILTER_VALIDATE_INT) : null;
        $this->horas_practicas_semestre = isset($input['horas_practicas_semestre']) ? filter_var($input['horas_practicas_semestre'], FILTER_VALIDATE_INT) : null;
        $this->horas_semestre = isset($input['horas_semestre']) ? filter_var($input['horas_semestre'], FILTER_VALIDATE_INT) : null;
        $this->creditos = isset($input['creditos']) ? filter_var($input['creditos'], FILTER_VALIDATE_INT) : null;
        $this->vinculo_objetivos_posgrado = isset($input['vinculo_objetivos_posgrado']) ? filter_var($input['vinculo_objetivos_posgrado'], FILTER_SANITIZE_STRING) : null;
        $this->id_modalidad = isset($input['id_modalidad']) ? filter_var($input['id_modalidad'], FILTER_VALIDATE_INT) : null;
        $this->id_tipo = isset($input['id_tipo']) ? filter_var($input['id_tipo'], FILTER_VALIDATE_INT) : null;
    }

    // Getter methods...
    public function getId() { return $this->id; }
    public function getClave() { return $this->clave; }
    public function getNombre() { return $this->nombre; }
    public function getNombreIngles() { return $this->nombre_ingles; }
    public function getHorasTeoricasSemana() { return $this->horas_teoricas_semana; }
    public function getHorasPracticasSemana() { return $this->horas_practicas_semana; }
    public function getHorasSemana() { return $this->horas_semana; }
    public function getHorasTeoricasSemestre() { return $this->horas_teoricas_semestre; }
    public function getHorasPracticasSemestre() { return $this->horas_practicas_semestre; }
    public function getHorasSemestre() { return $this->horas_semestre; }
    public function getCreditos() { return $this->creditos; }
    public function getVinculoObjetivosPosgrado() { return $this->vinculo_objetivos_posgrado; }
    public function getIdModalidad() { return $this->id_modalidad; }
    public function getIdTipo() { return $this->id_tipo; }
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
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
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
            horas_teoricas_semana = ?,
            horas_practicas_semana = ?,
            horas_semana = ?,
            horas_teoricas_semestre = ?,
            horas_practicas_semestre = ?,
            horas_semestre = ?,
            creditos = ?,
            vinculo_objetivos_posgrado = ?,
            id_modalidad = ?,
            id_tipo = ?
            WHERE id = ?";

    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $connection->error);
    }

    $stmt->bind_param(
        'siiiiiiiiiii',
        $curso->getNombreIngles(),
        $curso->getHorasTeoricasSemana(),
        $curso->getHorasPracticasSemana(),
        $curso->getHorasSemana(),
        $curso->getHorasTeoricasSemestre(),
        $curso->getHorasPracticasSemestre(),
        $curso->getHorasSemestre(),
        $curso->getCreditos(),
        $curso->getVinculoObjetivosPosgrado(),
        $curso->getIdModalidad(),
        $curso->getIdTipo(),
        $curso->getId()
    );

    $stmt->execute();

    $stmt->close();
    $connection->close();

    echo json_encode([
        'success' => true,
        'message' => 'Curso actualizado',
    ]);

} catch (Exception $e) {
    //http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
    error_log($e->getMessage());
}

?>
