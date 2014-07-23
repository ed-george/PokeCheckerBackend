<?php
/**
 * Emailer.php
 * User: edgeorge
 * Date: 23/07/2014
 * Time: 23:11
 * Copyright PokéChecker 2014
 */

class Emailer{

    public function getEmailFromTemplate($variables, $template_location){

        $template = file_get_contents($template_location);

        foreach($variables as $key => $value)
        {
            $template = str_replace('{{ '.$key.' }}', $value, $template);
        }

        return $template;

    }

    public function sendVerifyEmail($email, $username, $verification){

        include_once 'config.php';

        $variables = array();

        $variables['name'] = $username;
        $variables['url'] = HOST;
        $variables['verification'] = $verification;

        $template = "../mail_templates/welcome.html";

        $this->mailUser($email, "Verify your Pokéchecker account", $template);


    }

    private function mailUser($email, $subject, $content){
        $to = $email;
        $message = $content;
        $from = "no-reply@pokechecker.com";
        $headers = "From: ". $from . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=ISO-8859-1\r\n";
        mail($to,$subject,$message,$headers);
    }

}

