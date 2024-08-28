<?php

try {
    // Delete the authToken cookie
    setcookie('authToken', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Cerraste sesión correctamente',
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    error_log($e->getMessage());
}
?>
