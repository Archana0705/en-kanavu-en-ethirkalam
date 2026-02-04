<?php

$read_hostname = 'household-survey-aurora-proxy.proxy-cfoq6gskifi8.ap-south-2.rds.amazonaws.com';
$read_database = 'enn_kanavu_enn_ethirkalam';
$read_username = 'household_admin';
$read_password = '!AC-bC5x0F>ew2u2vDLTaybyOy4~';
$read_port = 5432;

try {
	$write_db = new PDO("pgsql:host=$read_hostname;port=$read_port;dbname=$read_database", $read_username, $read_password, [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	]);
} catch (PDOException $e) {
	die("Coluldn't able to connect to Write Database $read_database because of " . $e->getMessage());
}