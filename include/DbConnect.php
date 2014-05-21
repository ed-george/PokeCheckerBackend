<?php
/**
 * Created by PhpStorm.
 * User: edgeorge
 * Date: 21/05/2014
 * Time: 10:14
 */

class DbConnect {

    private $conn;

    function __construct() {}

    /**
     * Establish connection to database
     */
    function connect(){
        include_once dirname(__FILE__) . './config.php';

        $this->conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

        if(mysqli_connect_errno()){
            echo "Failed to connect to MySQL: " . mysqli_connect_error();
        }

        return $this->conn;
    }

} 