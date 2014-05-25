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
        require_once dirname(__FILE__) . './DbConnect.php';
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

        // First check if user already existed in db
        if (!$this->doesUserExists($username, $email)) {
            // Generating password hash
            $password_hash = PassHash::hash($password);

            // Generating API key
            $api_key = $this->generateAPIKey();

            // insert query
            $stmt = $this->conn->prepare("INSERT INTO users(user_name, email, password_hash, api_key) values(?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $email, $password_hash, $api_key);

            $result = $stmt->execute();

            $stmt->close();

            // Check for successful insertion
            if ($result) {
                // User successfully inserted
                return CREATED_SUCCESSFULLY;
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
     * Checking user login
     * @param String $username User login username
     * @param String $password User login password
     * @return boolean User login status success/fail
     */
    public function checkLogin($username, $password) {
        // Fetch user from email
        $stmt = $this->conn->prepare("SELECT password_hash FROM users WHERE username = ?");

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
        $stmt = $this->conn->prepare("SELECT id from users WHERE email = ? OR username = ?");
        $stmt->bind_param("ss", $email, $username);
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
        $stmt = $this->conn->prepare("SELECT name, email, api_key, status, created_at FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        if ($stmt->execute()) {
            $user = $stmt->get_result()->fetch_assoc();
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
            $api_key = $stmt->get_result()->fetch_assoc();
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
            $user_id = $stmt->get_result()->fetch_assoc();
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

    /* ------------- `user_sets` table methods ------------------ */

    /**
     * Add new set to user
     * @param String $user_id user id to whom set belongs to
     * @param String $set_id task text
     * @return String
     */
    public function assignSetToUser($user_id, $set_id) {
        $stmt = $this->conn->prepare("INSERT INTO user_sets(user_id, set_id) values(?, ?)");
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
        $stmt = $this->conn->prepare("SELECT cs.* FROM card_set cs, user_sets us WHERE cs.id = us.card_set_id AND us.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
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


    /* ------------- `user_cards` table methods ------------------ */

    /**
     * Get all cards owned by user
     * @param String $user_id the user id to whom cards belong
     * @return int
     */

    public function getAllUserCards($user_id){
        $stmt = $this->conn->prepare("SELECT c.*, uc.quantity FROM cards c, user_cards uc WHERE c.id = uc.card_id AND uc.user_id = ?");
        $stmt->bind_param("i", $user_id);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
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


} 