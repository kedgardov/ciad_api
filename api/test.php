<?php

require '../../vendor/autoload.php';

try {
    // Placeholder array for tesis_mini
    $tesis_mini = [
        // Example data (you can customize or fetch from the database)
        ["id" => 1, "titulo" => "Sample Tesis 1", "fecha" => "2024-01-01", "checked" => true],
        ["id" => 2, "titulo" => "Sample Tesis 2", "fecha" => "2024-02-01", "checked" => false]
    ];

    // Return JSON response
    echo json_encode([
        'success' => true,
        'message' => 'Tesis mini obtenidas',
        'tesis_mini' => $tesis_mini,
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'tesis_mini' => [],
    ]);
    error_log($e->getMessage());
}
?>

