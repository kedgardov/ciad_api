<?php

require '../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Dotenv\Dotenv;

class LGACCurso {
    public $id;
    public $id_curso;
    public $id_lgac;
    public $id_nivel_curricular;
    public $id_programa;

    public function __construct($input) {
        if (!isset($input['id']) || !isset($input['id_curso']) || !isset($input['id_lgac']) || !isset($input['id_nivel_curricular'])  || !isset($input['id_programa'])){
            throw new Exception('Missing required fields');
        }
        $this->id = filter_var($input['id'], FILTER_VALIDATE_INT);
        $this->id_curso = filter_var($input['id_curso'], FILTER_VALIDATE_INT);
        $this->id_lgac = filter_var($input['id_lgac'], FILTER_VALIDATE_INT);
        $this->id_nivel_curricular = filter_var($input['id_nivel_curricular'], FILTER_VALIDATE_INT);
        $this->id_programa = filter_var($input['id_programa'], FILTER_VALIDATE_INT);
    }

    // Getter methods...
    public function getId() { return $this->id; }
    public function getIdCurso() { return $this->id_curso; }
    public function getIdLGAC() { return $this->id_lgac; }
    public function getIdNivelCurricular() { return $this->id_nivel_curricular; }
    public function getIdPrograma() { return $this->id_programa; }
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

    $LGACCurso = new LGACCurso($input['lgac']);

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

    $sql = "UPDATE lgacs_cursos
            INNER JOIN roles_cursos ON roles_cursos.id_curso = lgacs_cursos.id_curso
            SET lgacs_cursos.id_lgac = ?,
                lgacs_cursos.id_nivel_curricular = ?,
                lgacs_cursos.id_programa = ?
            WHERE roles_cursos.id_maestro = ?
            AND lgacs_cursos.id_curso = ?";

    $stmt = $connection->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Prepare statement failed: ' . $connection->error);
    }

    $stmt->bind_param(
        'iiiii',
        $LGACCurso->getIdLGAC(),
        $LGACCurso->getIdNivelCurricular(),
        $LGACCurso->getIdPrograma(),
        $decoded_jwt->sub,
        $LGACCurso->getIdCurso()
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
