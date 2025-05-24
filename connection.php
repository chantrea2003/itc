<?php
$mysqli = new mysqli("localhost", "root", "mysql", "blood_donation", "3306");

if ($mysqli->connect_errno) {
    die("Failed to connect to MySQL: " . $mysqli->connect_error);
}
