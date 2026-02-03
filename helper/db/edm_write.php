<?php

$read_hostname = '192.168.4.250';
$read_database = 'enn_kanavu_enn_ethirkalam';
$read_username = 'postgres';
$read_password = 'postgres';
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