<?php

$read_hostname = '192.168.5.67';
$read_database = 'Indian Medicine';
$read_username = 'postgres';
$read_password = 'postgres';
$read_port = 5432;

try {
    $dsn = "pgsql:host=$read_hostname;port=$read_port;dbname='" . addslashes($read_database) . "'";
    $edm_read_db = new PDO($dsn, $read_username, $read_password);
    $edm_read_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Couldn't connect to Read Database '$read_database' because of " . $e->getMessage());
}
