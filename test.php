<?php

require __DIR__ . '/vendor/autoload.php';

//use DevKartic\LightKit\LightKit;
//
//$env = LightKit::env();
//
//$env::load(__DIR__ . '/.env');
//
//var_dump($env::get('DB_USER'));


use DevKartic\LightKit\Env\EnvManager;
use DevKartic\LightKit\Database\DB;


$env = new EnvManager();
$env->load(__DIR__.'/.env');

DB::fromEnv($env); // Initializes QueryBuilder via Connection::fromEnv

// Now you can run queries
$users = DB::table('users')->where('id', 1)->get();

var_dump($users);