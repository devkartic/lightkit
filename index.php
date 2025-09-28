<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';

//use DevKartic\LightKit\LightKit;
//
//$env = LightKit::env();
//
//$env::load(__DIR__ . '/.env');
//
//var_dump($env::get('DB_USER'));


use DevKartic\LightKit\Config\ConfigManager;
use DevKartic\LightKit\Database\DB;


 ConfigManager::init();

// Now you can run queries
$users = DB::table('users')->where('id', 1)->get();

echo '<pre>';
var_dump($users);