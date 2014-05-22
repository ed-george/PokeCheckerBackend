<?php
/**
 * PassHash.php
 * User: edgeorge
 * Date: 22/05/2014
 * Time: 07:53
 * Copyright PokéChecker 2014
 */

class PassHash {

    // blowfish
    private static $algo = '$2a';
    // cost parameter
    private static $cost = '$10';


    public static function unique_salt() {
        return substr(sha1(mt_rand()), 0, 22);
    }

    // Generate a hash
    public static function hash($password) {

        return crypt($password, self::$algo .
            self::$cost .
            '$' . self::unique_salt());
    }

    // Compare password against a hash
    public static function check_password($hash, $password) {
        $full_salt = substr($hash, 0, 29);
        $new_hash = crypt($password, $full_salt);
        return ($hash == $new_hash);
    }

} 