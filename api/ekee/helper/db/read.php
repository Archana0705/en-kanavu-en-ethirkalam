<?php

$read_hostname = 'household-survey-aurora-proxy-read-only.endpoint.proxy-cfoq6gskifi8.ap-south-2.rds.amazonaws.com';
$read_database = 'enn_kanavu_enn_ethirkalam';
$read_username = 'household_admin';
$read_password = '!AC-bC5x0F>ew2u2vDLTaybyOy4~';
$read_port = 5432;

try {
    $dsn = "pgsql:host=$read_hostname;port=$read_port;dbname='" . addslashes($read_database) . "'";
    $read_db = new PDO($dsn, $read_username, $read_password);
    $read_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Couldn't connect to Read Database '$read_database' because of " . $e->getMessage());
}
