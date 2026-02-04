<?php
$cron_hostname = $_ENV['CRON_DB_HOST'];
$cron_database = $_ENV['CRON_DB_NAME'];
$cron_username = $_ENV['CRON_DB_USER'];
$cron_password = $_ENV['CRON_DB_PASS'];
$cron_port = $_ENV['CRON_DB_PORT'];
$appName = 'SFDB_SURVEY_CRON_WEB';
try {
	$cron_db_connect = new PDO("pgsql:host=$cron_hostname;port=$cron_port;dbname=$cron_database", $cron_username, $cron_password);
    $cron_db_connect->exec("SET application_name = '$appName'");
} catch (PDOException $e) {
	die("Coluldn't able to connect to Write Database because of " . $e->getMessage());
}

