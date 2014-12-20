<?php
define('BASE_PATH', dirname(__FILE__) . '/src');

require 'vendor/autoload.php';

$config = new \CKIUM\config()

function json_output($out)
{
	global $app;
	$app->response->headers->set('Content-Type', 'application/json');
	$app->response->setBody(json_encode($out));
}

$app = new \Slim\Slim();

$app->config = new \CKIUM\Config();

$db = new PDO(
	'mysql:host=' . $app->config->get('db_host', 'localhost') . ';dbname=' . $app->config->db_name .';charset=utf8',
	$app->config->db_user, $app->config->db_pass
);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$app->db = $db;

$app->current_year = date('Y');
if (date('m') < 4) {
	$app->current_year -= 1;
}

//TODO encrypt cookies (and set all the other settings)

$app->add(new \Slim\Middleware\ContentTypes());
$app->add(new \CKIUM\Middleware\Me());

require(BASE_PATH . '/validators.php');
require(BASE_PATH . '/routes/auth.php');
require(BASE_PATH . '/routes/users.php');
require(BASE_PATH . '/routes/events.php');

$app->run();
