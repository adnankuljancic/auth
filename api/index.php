<?php

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require '../vendor/autoload.php';

// import and register all business logic files (services) to FlightPHP
require_once __DIR__ . '/services/UserService.php';


Flight::register('userService', "UserService");


// import all routes
require_once __DIR__ . '/routes/UserRoutes.php';

Flight::route('GET /', function () {
    echo "Hello";
});


// Flight::route('/', function(){
//     echo "homepage";

// });

// Flight::route('POST /register', function(){

//     $username = Flight::request()->data->username;
//     $email = Flight::request()->data->email;
//     $password = Flight:: request()->data->password;
//     $phone = Flight::request()->data->phone;
    
//     if(strlen($username) <= 3) {
//         echo "Username must be longer than 3 characters.";
//         exit;
//     }
//     if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
//         echo "Invalid email format.";
//         exit;
//     }
//     echo "Welcome $username";
// });

// Flight::route('POST /login', function() {
//     echo 'Login';
// });

Flight::start();

?>