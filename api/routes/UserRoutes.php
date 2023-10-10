<?php

Flight::route('POST /register', function() {
    $entity = Flight::request()->data->getData();
    Flight::json(Flight::userService()->register($entity));
});

Flight::route('POST /login', function() {
    $entity = Flight::request()->data->getData();
    Flight::json(Flight::userService()->login($entity));
});

Flight::route('GET /confirm', function() {
    $token = Flight::request()->query['token'];
    Flight::json(Flight::userService()->confirm_email($token));
});

Flight::route('POST /check-otp', function() {
    $entity = Flight::request()->data->getData();
    Flight::json(Flight::userService()->check_OTP_status($entity['username']));
});

Flight::route('POST /otp', function() {
    $entity = Flight::request()->data->getData();
    Flight::json(Flight::userService()->validate_otp($entity));
});

Flight::route('POST /sms', function() {
    $entity = Flight::request()->data->getData();
    Flight::json(Flight::userService()->send_sms($entity['username']));
});

Flight::route('POST /check_sms', function() {
    $entity = Flight::request()->data->getData();
    Flight::json(Flight::userService()->check_sms_code($entity));
});

Flight::route('POST /recover', function() {
    $entity = Flight::request()->data->getData();
    Flight::json(Flight::userService()->forgot_password($entity));
});

Flight::route('POST /change-password', function() {
    $token = Flight::request()->query['token'];
    $entity = Flight::request()->data->getData();
    Flight::json(Flight::userService()->change_password($entity, $token));
});





?>
