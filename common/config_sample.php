<?php
$sql = array(
    'server' => 'localhost',
    'user' => 'username',
    'database' => 'database',
    'password' => 'password',
);

$dbprefix = 'mit_';
$identityPrefix = 'meldeliste_';

$conn = mysqli_connect($sql['server'], $sql['user'], $sql['password']) or die(mysqli_connect_error());
global $conn;
mysqli_select_db($GLOBALS['conn'], $sql['database']) or die(mysqli_error($conn));
mysqli_set_charset($GLOBALS['conn'], 'utf8mb4');
@mysqli_query($GLOBALS['conn'], 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

global $dbprefix;
global $identityPrefix;
global $sql;
?>
