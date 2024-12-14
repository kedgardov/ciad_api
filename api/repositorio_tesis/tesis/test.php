<?php
// Define the file path
$filePath = 'lista_tesis/sample.pdf';

// Define the command with escapeshellarg for safety
$command = 'venv/bin/python python/main_script.py ' . escapeshellarg($filePath);

// Set up pipes for stdout and stderr
$descriptorspec = [
    1 => ['pipe', 'w'], // stdout
    2 => ['pipe', 'w']  // stderr
];

// Open the process
$process = proc_open($command, $descriptorspec, $pipes);

// Initialize variables to capture output
$stdout = '';
$stderr = '';

if (is_resource($process)) {
    // Read stdout and stderr
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);

    // Close pipes
    fclose($pipes[1]);
    fclose($pipes[2]);

    // Close the process
    proc_close($process);
}

// Decode the JSON response from stdout
$response = json_decode($stdout, true);

// Prepare the final response based on stdout and stderr
if (!empty($stderr)) {
    // If there's an error, include it in the JSON response
    $output = [
        "success" => false,
        "message" => "Error during processing",
        //"error" => $stderr
        "error" => 'Algo Salio Mal'
    ];
} else if ($response && isset($response['success']) && $response['success']) {
    // If no errors, output the JSON response from Python
    $output = $response;
} else {
    $output = [
        "success" => false,
        "message" => $response['message'] ?? "Unknown error",
    ];
}

// Output the final JSON response
header('Content-Type: application/json');
echo json_encode($output);
?>
