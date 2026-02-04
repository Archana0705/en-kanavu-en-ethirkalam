<?php
$write_hostname = $_ENV['WRITE_DB_HOST'];
$write_database = $_ENV['WRITE_DB_NAME'];
$write_username = $_ENV['WRITE_DB_USER'];
$write_password = $_ENV['WRITE_DB_PASS'];
$write_port = $_ENV['WRITE_DB_PORT'];
$appName = 'SFDB_SURVEY_WRITE_WEB';

try {
	$write_db = new PDO("pgsql:host=$write_hostname;port=$write_port;dbname=$write_database", $write_username, $write_password);
    $write_db->exec("SET application_name = '$appName'");
} catch (PDOException $e) {
	die("Coluldn't able to connect to Write Database because of " . $e->getMessage());
}
