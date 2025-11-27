<?php
if (!defined('SECURE_AI')) {
    die("Direct access not allowed");
}
require_once __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();
$OPENAI_API_KEY = getenv('OPENAI_API_KEY');
?>