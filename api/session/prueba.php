<?php
// Ejecuta el script de Python y captura la salida
$comando = escapeshellcmd('python /var/www/html/course_tools_api/api/session/pruebapython.py');
$salida = shell_exec($comando);

// Utiliza la salida en tu función PHP
echo $salida;
?>
