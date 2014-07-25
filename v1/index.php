<?php

require_once '../include/DbHandler.php';
require_once '../include/PassHash.php';
require '../libs/Slim/Slim/Slim.php';

include '../include/Logger.php';
include '../include/LogLevel.php';

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();
$log = new \Pokechecker\Logger('../poke_logs', Pokechecker\LogLevel::DEBUG, true);
// User id from db - Global Variable
$user_id = NULL;


/**
 * Verifying required params posted or not
 */
function verifyRequiredParams($required_fields) {
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }

    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response["error"] = true;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoResponse(400, $response);
        $app->stop();
    }
}

/**
 * Validating email address
 */
function validateEmail($email) {
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["error"] = true;
        $response["message"] = 'Email address is not valid';
        echoResponse(400, $response);
        $app->stop();
    }
}

/**
 * Echoing json response to client
 * @param Int $status_code Http response code
 * @param String $response Json response
 */
function echoResponse($status_code, $response) {
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);

    //add timestamp to header
    $app->response->headers->set('timestamp', time());

    // setting response content type to json
    $app->contentType('application/json');

    echo json_encode($response);
}

function authenticate(\Slim\Route $route) {

    include_once '../include/config.php';

    // Getting request headers
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();

    global $log;

    // Verifying Authorization Header
    if (isset($headers[AUTHORIZATION_HEADER])) {
        $db = new DbHandler();

        // get the api key
        $api_key = $headers[AUTHORIZATION_HEADER];
        // validating api key
        if (!$db->isValidApiKey($api_key)) {
            // api key is not present in users table
            $response["error"] = true;
            $response["message"] = "Access Denied. Invalid API key";
            $log->warning("Attempted unauthorized access of " . $route->getPattern() . " from IP " . get_client_ip());
            echoResponse(401, $response);
            $app->stop();
        } else {
            global $user_id;
            // get user primary key id
            $user = $db->getUserId($api_key);
            if ($user != NULL)
                $user_id = $user["id"];
        }
    } else {
        // api key is missing in header
        $response["error"] = true;
        $response["message"] = "Authentication is required: " . AUTHORIZATION_HEADER . " missing";
        echoResponse(401, $response);
        $app->stop();
    }
}

//-----------------------------------------//
//DEBUG

/**
 * Test service is alive
 * url - /isAlive
 * method - GET
 */

$app->get('/isAlive', function(){
    echo "true";
});

//-----------------------------------------//


/**
 * User Registration
 * url - /register
 * method - POST
 * params - username, email, password
 */
$app->post('/register', function() use ($app) {
    // check for required params
    verifyRequiredParams(array('username', 'email', 'password'));

    $response = array();

    // reading post params
    $name = strtolower($app->request->post('username'));
    $email = strtolower($app->request->post('email'));
    $password = $app->request->post('password');

    if (preg_match('/\s/',$name)){
        $response["error"] = true;
        $response["message"] = "Your username must not contain any spaces.";
        echoResponse(200, $response);
        return;
    }

    if(strlen($name) < 2 || strlen($name) > 32){
        $response["error"] = true;
        $response["message"] = "Your username should be between 2 and 32 characters !";
        echoResponse(200, $response);
        return;
    }

    if(strlen($password) < 8){
        $response["error"] = true;
        $response["message"] = "Your password should be longer than 8 characters!";
        echoResponse(200, $response);
        return;
    }

    if(strlen($password) > 32){
        $response["error"] = true;
        $response["message"] = "Your password should be no longer than 32 characters!";
        echoResponse(200, $response);
        return;
    }

    // validating email address
    validateEmail($email);

    $db = new DbHandler();
    $res = $db->createUser($name, $email, $password);

    if ($res == CREATE_FAILED) {
        $response["error"] = true;
        $response["message"] = "Oops! An error occurred while registering";
        echoResponse(400, $response);
    } else if ($res == ALREADY_EXISTED) {
        $response["error"] = true;
        $response["message"] = "Sorry, these credentials already existed";
        echoResponse(200, $response);
    }else{
        $response["error"] = false;
        $response["message"] = "You are successfully registered";
        $response["api_key"] = $res;
        echoResponse(201, $response);
    }

});

/**
 * User Login
 * url - /login
 * method - POST
 * params - username, password
 */
$app->post('/login', function() use ($app) {
    // check for required params
    verifyRequiredParams(array('username', 'password'));

    // reading post params
    $username = strtolower($app->request()->post('username'));
    $password = $app->request()->post('password');
    $response = array();

    $db = new DbHandler();
    // check for correct email and password
    if ($db->checkLogin($username, $password)) {
        // get the user by email
        $user = $db->getUserByUsername($username);

        if ($user != NULL) {
            $response["error"] = false;
            $response['user_name'] = $user['user_name'];
            $response['email'] = $user['email'];
            $response['api_key'] = $user['api_key'];
        } else {
            // unknown error occurred
            $response['error'] = true;
            $response['message'] = "An error occurred. Please try again";
        }
    } else {
        // user credentials are wrong
        $response['error'] = true;
        $response['message'] = 'Login failed. Incorrect credentials';
    }

    echoResponse(200, $response);
});

/**
 * Verify Account
 * url - /verify/:verification_code
 * method - GET
 */
$app->get('/verify/:verification_code', function($verification_code) use ($app){

    $db = new DbHandler();
    $response = array();

    if($db->checkVerification($verification_code)){
        $response['error'] = false;
        $response['message'] = "Thank you, your account has been verified.";
    }else{
        $response['error'] = true;
        $response['message'] = "Sorry, we could not verify your email address";
    }

    echoResponse(200, $response);

});


$app->get('/web/verify/:verification_code', function($verification_code) use ($app){
    include_once "../include/Emailer.php";

    $db = new DbHandler();
    $vars = array();
    $vars["url"] = "app release url";
    if($db->checkVerification($verification_code)){
     echo Emailer::getEmailFromTemplate($vars ,"../mail_templates/verification_accepted.html");
    }else{
        echo "You accidentally the whole Pok&eacute;checker.";
    }

});


//-----------------------------------------//
//PUBLIC CALLS

/**
 * Get all sets
 * method GET
 * url - /sets
 */
$app->get('/sets', function(){
    $response = array();
    $db = new DbHandler();

    $result = $db->getAllSets();
    $response["error"] = false;
    $response["sets"] = $result;

    echoResponse(200, $response);
});

/**
 * Get all series
 * method GET
 * url - /series
 */
$app->get('/series', function(){
    $response = array();
    $db = new DbHandler();

    $result = $db->getAllSeries();
    $response["error"] = false;
    $response["series"] = $result;

    echoResponse(200, $response);
});

/**
 * Get all sets from series id
 * method GET
 * url - /series
 */
$app->get('/sets/series/:id', function($series_id){
    $response = array();
    $db = new DbHandler();

    $result = $db->getAllSetsFromSeries($series_id);
    $response["error"] = false;
    $response["series_id"] = (int) $series_id;
    $response["sets"] = $result;

    echoResponse(200, $response);
});

/**
 * Get all cards from a set
 * method GET
 * url - /sets/:id/cards
 */
$app->get('/sets/:id/cards', function ($set_id){
    $response = array();
    $db = new DbHandler();


    $set = $db->getSet($set_id);
    if($set != null){
        $result = $db->getCardsFromSet($set_id);
        $response["error"] = false;
        $response["set"] = $set;
        $response["count"] = count($result);
        $response["cards"] = $result;
    }else{
        $response["error"] = true;
        $response["message"] = "Sorry that set does not exist.";
    }
    echoResponse(200, $response);
});

//-----------------------------------------//
//AUTH CALLS

/**
 * Assigning new Set to user
 * method POST
 * params - set
 * url - /user/set
 */
$app->post('/user/set', 'authenticate', function() use ($app) {
    // check for required params
    verifyRequiredParams(array('set_id'));

    $response = array();
    $set = $app->request->post('set_id');

    global $user_id;
    $db = new DbHandler();

    // creating new user set
    $set_created = $db->assignSetToUser($user_id, $set);

    if ($set_created) {
        $response["error"] = false;
        $response["message"] = "Set added successfully";
    } else {
        $response["error"] = true;
        $response["message"] = "Failed to assign set. Please try again";
    }
    echoResponse(201, $response);
});

/**
 * Get all user sets
 * method GET
 * params - set
 * url - /user/set
 */
$app->get('/user/set', 'authenticate', function(){
    global $user_id;
    $response = array();
    $db = new DbHandler();

    $result = $db->getAllUserAssignedSets($user_id);
    $response["error"] = false;
    $response["sets"] = $result;

    echoResponse(200, $response);
});


/**
 * Delete user sets
 * method DELETE
 * params - id
 * url - /user/set/:id
 */
$app->delete('/user/set/:id', 'authenticate', function($set_id) use ($app){

    $response = array();

    global $user_id;
    $db = new DbHandler();

    $set_deleted = $db->deleteUserAssignedSet($user_id, $set_id);

    if ($set_deleted) {
        $response["error"] = false;
        $response["message"] = "Set removed successfully";
    } else {
        $response["error"] = true;
        $response["message"] = "Failed to remove set. Please try again";
    }
    echoResponse(200, $response);
});

/**
 * Get user cards from sets
 * method GET
 * params - id
 * url - /user/set/:id/cards
 */
$app->get('/user/set/:id/cards', 'authenticate', function($set_id) use ($app){

    $response = array();

    global $user_id;
    $db = new DbHandler();
    $isUserAssignedSet = $db->isUserAssignedToSet($user_id, $set_id);
    if($isUserAssignedSet){

        $cards = $db->getUserCardsFromSet($user_id, $set_id);
        $set = $db->getSet($set_id);
        $response["error"] = false;
        $response["set"] = $set;
        $response["count"] = count($cards);
        $response["cards"] = $cards;

    }else{
        $response["error"] = true;
        $response["message"] = "Sorry, you aren't currently subscribed to this set.";
    }
    echoResponse(200, $response);
});

$app->run();

?>
