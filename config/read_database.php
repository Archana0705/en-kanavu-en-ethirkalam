<?php

$read_hostname = $_ENV['READ_DB_HOST'];
$read_database = $_ENV['READ_DB_NAME'];
$read_username = $_ENV['READ_DB_USER'];
$read_password = $_ENV['READ_DB_PASS'];
$read_port = $_ENV['READ_DB_PORT'];
$appName = 'SFDB_SURVEY_READ_WEB';
try {
	$read_db = new PDO("pgsql:host=$read_hostname;port=$read_port;dbname=$read_database", $read_username, $read_password);
    $read_db->exec("SET application_name = '$appName'");
} catch (PDOException $e) {
	die("Coluldn't able to connect to Read Database because of " . $e->getMessage());
}
