<?php
/**
 * DbHandler.php
 * User: edgeorge
 * Date: 22/05/2014
 * Time: 08:18
 * Copyright PokéChecker 2014
 */

class DbHandler {

    private $conn;

    function __construct() {
        require_once dirname(__FILE__) . '/DbConnect.php';
        // opening db connection
        $db = new DbConnect();
        $this->conn = $db->connect();
    }

    /**
     * Creating new user
     * @param String $username User's username
     * @param String $email User login email
     * @param String $password User login password
     * @return int as defined in config.php
     */
    public function createUser($username, $email, $password) {
        require_once 'PassHash.php';
        require_once 'Emailer.php';

        // First check if user already existed in db
        if (!$this->doesUserExists($username, $email)) {
            // Generating password hash
            $password_hash = PassHash::hash($password);

            // Generating API key
            $api_key = $this->generateAPIKey();
            $verification_code = $this->generateAPIKey();

            // insert query
            $stmt = $this->conn->prepare("INSERT INTO users(user_name, email, password_hash, api_key, verified, verification_code) values(?, ?, ?, ?, 0, ?)");
            $stmt->bind_param("sssss", $username, $email, $password_hash, $api_key, $verification_code);

            $emailer = new Emailer();
            $emailer->sendVerifyEmail($email, $username, $verification_code);

            $result = $stmt->execute();

            $stmt->close();

            // Check for successful insertion
            if ($result) {
                // User successfully inserted
                return $api_key;
            } else {
                // Failed to create user
                return CREATE_FAILED;
            }
        } else {
            // User with same email/username already existed in the db
            return ALREADY_EXISTED;
        }

    }

    /**
     * Update accounts as being verified
     * @param $verification_code
     * @return bool
     */
    public function checkVerification($verification_code){
        $stmt = $this->conn->prepare("SELECT id from users WHERE verification_code = ? AND verified = 0");
        $stmt->bind_param("s", $verification_code);
        $stmt->execute();
        $stmt->store_result();
        $num_rows= $stmt->num_rows;

        if($num_rows > 0){

            $stmt->bind_result($id);

            while($stmt->fetch()){
                $new_stmt = $this->conn->prepare("UPDATE users SET verified = 1  WHERE id = ?;");
                $new_stmt->bind_param("s", $id);
                $new_stmt->execute();
                $new_stmt->close();
            }

            $stmt->close();
            return true;
        }

        $stmt->close();
        return false;

    }

    /**
     * Checking user login
     * @param String $username User login username
     * @param String $password User login password
     * @return boolean User login status success/fail
     */
    public function checkLogin($username, $password) {
        // Fetch user from email
        $stmt = $this->conn->prepare("SELECT password_hash FROM users WHERE user_name = ?");

        $stmt->bind_param("s", $username);

        $stmt->execute();

        $stmt->bind_result($password_hash);

        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            // Found user with the email
            // Now verify the password

            $stmt->fetch();

            $stmt->close();

            if (PassHash::check_password($password_hash, $password)) {
                // User password is correct
                return TRUE;
            } else {
                // user password is incorrect
                return FALSE;
            }
        } else {
            $stmt->close();

            // user not existed with the email
            return FALSE;
        }
    }

    /**
     * Checking for duplicate of user by email address/username
     * @param String $username username to check in db
     * @param String $email email to check in db
     * @return boolean
     */
    private function doesUserExists($username, $email) {
        $stmt = $this->conn->prepare("SELECT id from users WHERE email = ? OR user_name = ?");
        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    public function updateUser($user_id, $firstname, $lastname, $iscollector, $istrader){
        $stmt = $this->conn->prepare("UPDATE users SET firstname = ?, lastname = ?, is_collector = ?, is_trader = ?  WHERE id = ?");
        $stmt->bind_param("ssssi", $firstname, $lastname, $iscollector, $istrader, $user_id);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    /**
     * Fetching user by email
     * @param String $email User email id
     * @return Object User object
     */
    public function getUserByEmail($email) {
        $stmt = $this->conn->prepare("SELECT user_name, firstname, lastname, email, api_key, is_collector, is_trader FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) {
            $stmt->bind_result($username, $firstname, $lastname, $email, $api_key, $collector, $trader);
            $stmt->fetch();
            $user["email"] = $email;
            $user["first_name"] = $firstname;
            $user["last_name"] = $lastname;
            $user["user_name"] = $username;
            $user["api_key"] = $api_key;
            $user["is_collector"] = (bool) $collector;
            $user["is_trader"] = (bool) $trader;
            $stmt->close();
            return $user;
        } else {
            return NULL;
        }
    }

    /**
     * Fetching user by email
     * @param String $email User email id
     * @return Object User object
     */
    public function getUserById($id) {
        $stmt = $this->conn->prepare("SELECT user_name, firstname, lastname, email, api_key, is_collector, is_trader FROM users WHERE id = ?");
        $stmt->bind_param("s", $id);
        if ($stmt->execute()) {
            $stmt->bind_result($username, $firstname, $lastname, $email, $api_key, $collector, $trader);
            $stmt->fetch();
            $user["email"] = $email;
            $user["first_name"] = $firstname;
            $user["last_name"] = $lastname;
            $user["user_name"] = $username;
            $user["api_key"] = $api_key;
            $user["is_collector"] = (bool) $collector;
            $user["is_trader"] = (bool) $trader;
            $stmt->close();
            return $user;
        } else {
            return NULL;
        }
    }

    public function getUserStats($user_id, $limit){

        if($limit <= 0){
            $this->$limit = 10;
        }

        $stmt = $this->conn->prepare("SELECT set_raw, COUNT(*) tot FROM user_cards uc JOIN cards c WHERE uc.card_id = c.id AND uc.user_id = ? GROUP BY set_raw ORDER BY tot DESC LIMIT ?");
        $stmt->bind_param("ii", $user_id, $limit);
        $stmt->execute();
        $stmt->bind_result($set_raw, $count);

        $response = array();

        while($result = $stmt->fetch()){
            $tmp = array();
            $tmp["set_name"] = $set_raw;
            $tmp["card_count"] = $count;
            array_push($response, $tmp);
        }
        $stmt->close();
        return $response;
    }

    /**
     * Fetching user by username
     * @param String $username Username
     * @return Object User object
     */
    public function getUserByUsername($username) {
        $stmt = $this->conn->prepare("SELECT user_name, firstname, lastname, email, api_key, is_collector, is_trader FROM users WHERE user_name = ?");
        $stmt->bind_param("s", $username);
        if ($stmt->execute()) {
            $stmt->bind_result($username, $firstname, $lastname, $email, $api_key, $collector, $trader);
            $stmt->fetch();
            $user["email"] = $email;
            $user["first_name"] = $firstname;
            $user["last_name"] = $lastname;
            $user["user_name"] = $username;
            $user["api_key"] = $api_key;
            $user["is_collector"] = (bool) $collector;
            $user["is_trader"] = (bool) $trader;
            $stmt->close();
            return $user;
        } else {
            return NULL;
        }
    }

    /**
     * Fetching user api key
     * @param String $user_id user id primary key in user table
     * @return String api Key for user
     */
    public function getAPIKeyById($user_id) {
        $stmt = $this->conn->prepare("SELECT api_key FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $stmt->bind_result($key);
            $stmt->fetch();
            $api_key["api_key"] = $key;
            $stmt->close();
            return $api_key;
        } else {
            return NULL;
        }
    }

    /**
     * Fetching user id by api key
     * @param String $api_key user api key
     * @return int User id
     */
    public function getUserId($api_key) {
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE api_key = ?");
        $stmt->bind_param("s", $api_key);
        if ($stmt->execute()) {
            $stmt->bind_result($id);
            $stmt->fetch();
            $user_id["id"] = $id;
            $stmt->close();
            return $user_id;
        } else {
            return NULL;
        }
    }

    /**
     * Validating user api key
     * If the api key is there in db, it is a valid key
     * @param String $api_key user api key
     * @return boolean
     */
    public function isValidAPIKey($api_key) {
        $stmt = $this->conn->prepare("SELECT id from users WHERE api_key = ?");
        $stmt->bind_param("s", $api_key);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
        $stmt->close();
        return $num_rows > 0;
    }

    /**
     * Generating random Unique MD5 String for API key
     * @return String
     */
    private function generateAPIKey() {
        return md5(uniqid(rand(), true));
    }

    /* ----------------- helper functions ------------------- */

    private function getImageUrlFromHost($scan_url){

        if($scan_url == null){
            return null;
        }

        $url = HOST . $scan_url;
        return $url;
    }

    /* ------------- `cards` table methods ------------------ */

    /**
     * Get all cards from set
     * @param int set id
     * @return array
     */

    public function getCardsFromSet($set_id){
        $stmt = $this->conn->prepare("SELECT * FROM cards WHERE card_set_id = ?");
        $stmt->bind_param("i", $set_id);
        $stmt->execute();
        $stmt->bind_result($id, $title, $scan_url, $card_type_id, $card_rarity_id, $card_set_id, $type_id, $card_number, $set_raw, $card_type_raw, $pokemon_type_raw, $rarity_raw);

        $response = array();

        while($result = $stmt->fetch()){
            $tmp = array();
            $tmp["card_id"] = $id;
            $tmp["title"] = utf8_encode($title);
            $tmp["scan_url"] = $this->getImageUrlFromHost($scan_url);
            $tmp["card_number"] = (int) $card_number;
            $tmp["card_type"] = utf8_encode($card_type_raw);
            $tmp["pokemon_type"] = $pokemon_type_raw;
            $tmp["rarity"] = $rarity_raw;
            array_push($response, $tmp);
        }

        $stmt->close();
        return $response;
    }

    /* ------------- `sets` table methods ------------------ */

    /**
     * Get all sets
     * @return String
     */

    public function getAllSets(){
        $stmt = $this->conn->prepare("SELECT * FROM card_set cs ORDER BY cs.id DESC");

        $stmt->execute();
        $stmt->bind_result($id, $set_name, $image_url, $set_icon, $release_date, $is_legal, $series_id, $cards_in_set, $is_subset);

        $response = array();

        while($result = $stmt->fetch()){
            //TODO return subsets on request
            if($is_subset){
                continue;
            }
            $tmp = array();
            $tmp["id"] = $id;
            $tmp["set_name"] = $set_name;
            $tmp["image_url"] = $this->getImageUrlFromHost($image_url);
            $tmp["set_icon"] = $this->getImageUrlFromHost($set_icon);
            $tmp["release_date"] = $release_date;
            $tmp["is_legal"] = (bool) $is_legal;
            $tmp["cards_in_set"] = (int) $cards_in_set;
            $tmp["series_id"] = $series_id;
            array_push($response, $tmp);
        }
        $stmt->close();
        return $response;
    }

    public function getSet($set_id){
        $stmt = $this->conn->prepare("SELECT * FROM card_set cs WHERE id = ?");
        $stmt->bind_param("i", $set_id);
        $stmt->execute();
        $stmt->bind_result($id, $set_name, $image_url, $set_icon, $release_date, $is_legal, $series_id, $cards_in_set, $is_subset);

        $stmt->fetch();

        //TODO: Fix this hack
        if($set_name == null){
            $stmt->close();
            return null;
        }

        $response["id"] = $id;
        $response["set_name"] = $set_name;
        $response["image_url"] = $this->getImageUrlFromHost($image_url);
        $response["set_icon"] = $this->getImageUrlFromHost($set_icon);
        $response["release_date"] = $release_date;
        $response["is_legal"] = (bool) $is_legal;
        $response["cards_in_set"] = (int) $cards_in_set;
        $response["series_id"] = $series_id;

        $stmt->close();
        return $response;
    }

    /* ------------- `series` table methods ------------------ */

    /**
     * Get all series
     * @return String
     */

    public function getAllSeries(){
        $stmt = $this->conn->prepare("SELECT * FROM card_series cs ORDER BY cs.id DESC");

        $stmt->execute();
        $stmt->bind_result($id, $series_name);

        $response = array();

        while($result = $stmt->fetch()){
            $tmp = array();
            $tmp["id"] = $id;
            $tmp["series_name"] = $series_name;
            array_push($response, $tmp);
        }
        $stmt->close();
        return $response;
    }

    public function getAllSeriesWithSets(){
        $stmt = $this->conn->prepare("SELECT * FROM card_series cs, card_set sets WHERE cs.id = sets.series_id");

        $stmt->execute();
        $stmt->bind_result($id, $series_name, $set_id, $set_name, $image_url, $set_icon, $release_date, $is_legal, $series_id, $cards_in_set, $is_subset);

        $response = array();

        $sets = array();
        $tmp = array();
        $curr_id = 1;
        $last_id = 1;
        while($result = $stmt->fetch()){

            $curr_id = $id;

            if($curr_id != $last_id){

                $tmp["sets"] = $sets;
                array_push($response, $tmp);
                $sets = array();
                $last_id = $id;
            }else{
                $tmp["id"] = $id;
                $tmp["series_name"] = $series_name;
            }

            $set = array();
            $set["id"] = $set_id;
            $set["set_name"] = $set_name;
            $set["image_url"] = $this->getImageUrlFromHost($image_url);
            $set["set_icon"] = $this->getImageUrlFromHost($set_icon);
            $set["release_date"] = $release_date;
            $set["is_legal"] = (bool) $is_legal;

            array_push($sets, $set);

        }

        $tmp["sets"] = $sets;
        array_push($response, $tmp);

        $stmt->close();
        return $response;
    }

    public function getAllSetsFromSeries($series_id){
        $stmt = $this->conn->prepare("SELECT sets.* FROM card_series cs, card_set sets WHERE cs.id = sets.series_id AND cs.id = ?");
        $stmt->bind_param("i", $series_id);
        $stmt->execute();
        $stmt->bind_result($id, $set_name, $image_url, $set_icon, $release_date, $is_legal, $series_id, $cards_in_set, $is_subset);

        $response = array();

        while($result = $stmt->fetch()){
            $tmp = array();
            $tmp["id"] = $id;
            $tmp["set_name"] = $set_name;
            $tmp["image_url"] = $this->getImageUrlFromHost($image_url);
            $tmp["set_icon"] = $this->getImageUrlFromHost($set_icon);
            $tmp["release_date"] = $release_date;
            $tmp["is_legal"] = (bool) $is_legal;
            array_push($response, $tmp);
        }
        $stmt->close();
        return $response;
    }


    /* ------------- `user_sets` table methods ------------------ */

    /**
     * Add new set to user
     * @param String $user_id user id to whom set belongs to
     * @param String $set_id task text
     * @return String
     */
    public function assignSetToUser($user_id, $set_id) {
        $stmt = $this->conn->prepare("INSERT INTO user_sets(user_id, card_set_id) values(?, ?)");
        $stmt->bind_param("ii", $user_id, $set_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }

    /**
     * Get all sets for user
     * @param String $user_id
     * @return String
     */

    public function getAllUserAssignedSets($user_id){
        $stmt = $this->conn->prepare("SELECT cs.* FROM card_set cs, user_sets us WHERE cs.id = us.card_set_id AND us.user_id = ? ORDER BY cs.id DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($id, $set_name, $image_url, $set_icon, $release_date, $is_legal, $series_id, $cards_in_set, $is_subset);

        $response = array();

        while($result = $stmt->fetch()){
            $tmp = array();
            $tmp["id"] = $id;
            $tmp["set_name"] = $set_name;
            $tmp["image_url"] = $this->getImageUrlFromHost($image_url);
            $tmp["set_icon"] = $this->getImageUrlFromHost($set_icon);
            $tmp["release_date"] = $release_date;
            $tmp["is_legal"] = (bool) $is_legal;
            $tmp["cards_in_set"] = (int) $cards_in_set;
            $tmp["series_id"] = $series_id;
            array_push($response, $tmp);
        }
        $stmt->close();
        return $response;
    }

    /**
     * Deleting a set associated to a user
     * @param String $user_id id of the user
     * @param String $set_id id of the set
     * @return int
     */
    public function deleteUserAssignedSet($user_id, $set_id) {
        $stmt = $this->conn->prepare("DELETE us FROM user_sets us WHERE us.user_id = ? AND us.card_set_id = ?");
        $stmt->bind_param("ii", $user_id, $set_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }

    public function isUserAssignedToSet($user_id, $set_id){
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM user_sets us WHERE us.user_id = ? AND us.card_set_id = ? LIMIT 1");
        $stmt->bind_param("ii", $user_id, $set_id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
        return $count > 0;
    }

    /* ------------- `user_cards` table methods ------------------ */

    /**
     * Get all cards owned by user
     * @param String $user_id the user id to whom cards belong
     * @return int
     */

    public function getAllUserCards($user_id){
        $stmt = $this->conn->prepare("SELECT c.*, uc.quantity FROM cards c, user_cards uc WHERE c.id = uc.card_id AND uc.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->bind_result($id, $set_name, $image_url, $set_icon, $release_date, $is_legal, $series_id);

        $response = array();

        while($result->fetch()){
            $tmp = array();
            $tmp["id"] = $id;
            $tmp["set_name"] = $set_name;
            array_push($response["cards"], $tmp);
        }
        $stmt->close();
        return $response;
    }

    public function getUserCardsFromSet($user_id, $set_id){

        $stmt = $this->conn->prepare("SELECT c.*, uc.quantity FROM user_cards uc, cards c WHERE uc.user_id = ? AND uc.card_id = c.id AND c.card_set_id = ?");
        $stmt->bind_param("ii", $user_id, $set_id);
        $stmt->execute();
        $stmt->bind_result($id, $title, $scan_url, $card_type_id, $card_rarity_id, $card_set_id, $type_id, $card_number, $set_raw, $card_type_raw, $pokemon_type_raw, $rarity_raw, $quantity);

        $response = array();

        while($result = $stmt->fetch()){
            $tmp = array();
            $tmp["card_id"] = $id;
            if(DEBUG_MODE){
                //TODO: remove this line
                $tmp["title"] = utf8_encode($title);
                $tmp["scan_url"] = $this->getImageUrlFromHost($scan_url);
                $tmp["card_number"] = (int) $card_number;
                $tmp["card_type"] = utf8_encode($card_type_raw);
                $tmp["pokemon_type"] = $pokemon_type_raw;
                $tmp["rarity"] = $rarity_raw;
            }
            $tmp["quantity"] = (int) $quantity;
            array_push($response, $tmp);
        }

        $stmt->close();
        return $response;
    }

    /**
     * Updating user cards
     * @param String $user_id id of the user
     * @param String $card_id id of card
     * @param String $quantity quant of cards
     * @return int
     */
    public function updateUserAssignedCard($user_id, $card_id, $quantity) {
        $stmt = $this->conn->prepare("UPDATE user_cards uc SET uc.quantity = ?  WHERE uc.card_id = ? AND uc.user_id = ?");
        $stmt->bind_param("iii", $quantity, $card_id, $user_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }

    /**
     * Deleting a user card
     * @param String $user_id id of the user
     * @param String $card_id id of the card
     * @return int
     */
    public function deleteUserCard($user_id, $card_id) {
        $stmt = $this->conn->prepare("DELETE uc FROM user_cards uc WHERE uc.user_id = ? AND uc.card_id = ?");
        $stmt->bind_param("ii", $user_id, $card_id);
        $stmt->execute();
        $num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
    }

//    public static function getLog(){
//        $log = new \Pokechecker\Logger('../poke_logs', Pokechecker\LogLevel::INFO, true);
//        return self::$log;
//    }




} 