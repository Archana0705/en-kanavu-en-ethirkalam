<?php
$c_hostname = "household-survey-aurora-proxy.proxy-cfoq6gskifi8.ap-south-2.rds.amazonaws.com";
$c_database = 'household_survey_prod';
$c_username = 'household_admin';
$c_password = '!AC-bC5x0F>ew2u2vDLTaybyOy4~';
$c_port = 5432;
$appName = 'SFDB_SURVEY_CRON_WEB_REJECTED_UPDATED';
try {
	$cron_db_execute = new PDO("pgsql:host=$c_hostname;port=$c_port;dbname=$c_database", $c_username, $c_password);
    $cron_db_execute->exec("SET application_name = '$appName'");
} catch (PDOException $e) {
	die("Coluldn't able to connect to Write Database because of " . $e->getMessage());
}
