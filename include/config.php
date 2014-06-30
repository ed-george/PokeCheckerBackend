<?php
/**
 * config.php
 * User: edgeorge
 * Date: 21/05/2014
 * Time: 10:09
 * Copyright PokéChecker 2014
 */

define('DEBUG_MODE', true);

define('HOST', 'http://localhost:8888');

define('DB_USERNAME', 'root');
define('DB_PASSWORD', 'root');
define('DB_HOST', 'localhost');
define('DB_NAME', 'poke_checker_db_main');

define('AUTHORIZATION_HEADER', 'X-Authorization');

define('HASH', 'p0kEch3CKeR');

define('CREATED_SUCCESSFULLY', 0);
define('CREATE_FAILED', 1);
define('ALREADY_EXISTED', 2);

function get_client_ip()
{

    if (getenv('HTTP_CLIENT_IP'))
        $ipaddress = getenv('HTTP_CLIENT_IP');
    else if(getenv('HTTP_X_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    else if(getenv('HTTP_X_FORWARDED'))
        $ipaddress = getenv('HTTP_X_FORWARDED');
    else if(getenv('HTTP_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    else if(getenv('HTTP_FORWARDED'))
        $ipaddress = getenv('HTTP_FORWARDED');
    else if(getenv('REMOTE_ADDR'))
        $ipaddress = getenv('REMOTE_ADDR');
    else
        $ipaddress = 'UNKNOWN';

    return $ipaddress;
}