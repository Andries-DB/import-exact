<?php
// TO connect to Exact Online
class access {


	public $client_id = '';
	public $client_secret = '';
	public $division = '';
	
	/* eerst setup, erna access customer. */

 
    public function access_customer(){

        $refresh_token = $this->result["refresh_token"];

        // Do the authentication if there is no refresh token. (setup)
        // No check yet on validation refresh token time. (30 days)
        If (empty($refresh_token) || $refresh_token == ""){

           $access_token = $this->setup();

        } else {

            $access_token = $this->access_token($refresh_token);

        }
    
        $result = $this->result;

        $result["access_token"] = $access_token;

        return $result;
    }

    public function setup(){
        
        // after redirect, we receive an authentication code to authorize
        if (!empty($_GET['code'])) {
            
            $code = rawurldecode($_GET['code']);

            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, $this->result["exact_api_url"] . '/api/oauth2/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'code' => $code,
                'client_id' => $this->result["client_id"],
                'client_secret' => $this->result["client_secret"],
                'redirect_uri' => $this->result["redirect_uri"],
                'grant_type' => 'authorization_code',
            ]);

            $response = curl_exec($ch); 
            $response = json_decode($response);  
            
            if (!isset($response->refresh_token)){

                echo "We didn't receive an access. Please check your credentials and user rights <br>";

                return "";

            }

            /* Update refresh token and access token in portal */
            $portal = new portal(); 

            // Get refresh and access token from response
            $refresh_token = $response->refresh_token;
            $access_token = $response->access_token;
            $expires_in = $response->expires_in;
            $now = gmdate("Y-m-d G:i:s");

            $portal->update_param($this->custkey, 'refresh_token', $refresh_token);
            $portal->update_param($this->custkey, 'access_token', $access_token);
            $portal->update_param($this->custkey, 'access_token_time', $now);
            $portal->update_param($this->custkey, 'expires_in', $expires_in);


            return $response->access_token;

        } else {

            $database = new database($this->result);
            $error = array("error_code" => 0, "error_message" => "OAuth2", "call_type" => "GET", "message" => "Setup");
            $database->sync_error($error);

            // Get the authorization code. 
            session_start();

            $_SESSION["customer"]=$this->custkey;

            $query = [
                'client_id' => $this->result["client_id"],
                'response_type' => 'code',
                'redirect_uri' => $this->result["redirect_uri"]
                
            ];
            
            header('Location: ' . $this->result["exact_api_url"] . '/api/oauth2/auth?' . http_build_query($query));
        }
    }


    public function access_token($refreshToken){

        // check if params access token & access token timestamp are present.
        if(isset($this->result["access_token"]) && isset($this->result["access_token_time"])){

            // check if params are not empty
            if ($this->result["access_token_time"] != NULL && $this->result["access_token"] != NULL){

                /* 
                Check if access token is still valid.
                Compare with datetime last access token.
                Marge of 30 seconds.
                If valid, return with same access token.
                */
                $now = strtotime(gmdate("Y-m-d G:i:s"));
                $token = strtotime($this->result["access_token_time"]);
                $diff = round(abs($now - $token));
                $expires = isset($this->result["expires_in"]) ? intval($this->result["expires_in"]) : 600;
                $expires = $expires - 30;

                if ($diff < $expires){

                    return $this->result["access_token"];

                }
            }
        } 


        // get new access and refresh token
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->result["exact_api_url"] . '/api/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'client_id' => $this->result["client_id"],
            'client_secret' => $this->result["client_secret"],
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ]);

        $response = curl_exec($ch); 
        $response = json_decode($response);

        // Connection portal to update the values
        $portal = new portal();   


        // On error auth, log error and abort script.
        if (isset($response->error)){

            $error_description = isset($response->error_description) ? $response->error_description : "";

            $database = new database($this->result);
            $error = array("error_code" => 401, "error_message" => $response->error, "call_type" => "Auth", "message" => $error_description);
            $database->sync_error($error);

        }

/*      // Updated, no automatic removal of refresh token.
        // On error or no result, return to setup for new authentication
        if (!isset($response->refresh_token) || $response->refresh_token == ""){

            // clear the failed refresh token on portal for a new setup
             if ($this->result["refresh_token"] != ""){

                $portal->update_param($this->custkey, 'refresh_token', "");

            }

            $this->setup();

        } */



        /* Update refresh token and access token in portal */

        // Get refresh and access token from response
        $refresh_token = $response->refresh_token;
        $access_token = $response->access_token;
        $expires_in = $response->expires_in;
        $now = gmdate("Y-m-d G:i:s");

        $portal->update_param($this->custkey, 'refresh_token', $refresh_token);
        $portal->update_param($this->custkey, 'access_token', $access_token);
        $portal->update_param($this->custkey, 'access_token_time', $now);
        $portal->update_param($this->custkey, 'expires_in', $expires_in);

        
        return $response->access_token;

    }


}


// Do calls to Exact Online

include_once('auth.php');


class api {


    public $custkey;
    public $access;
    public $access_token;



    public function __construct ($custkey){

        $this->custkey = $custkey;

        $access = new access($custkey);
        $access = $access->access_customer();

        // define access token & general access
        $this->access_token = $access["access_token"];
        $this->access = $access;


    }


    public function customer(){

        return $this->access;

    }

    // custom api exact
    public function get_data($table, $timestamp, $limit, $pagination){

        // check if access token is still valid
        $now = strtotime(gmdate("Y-m-d G:i:s"));
        $token = strtotime($this->access["access_token_time"]);
        $diff = round(abs($now - $token));
        $expires = isset($this->access["expires_in"]) ? intval($this->access["expires_in"]) : 600;
        $expires = $expires - 30;

        if ($diff > $expires){

            return array("expired" => true);

        }


        // check limits, stop api if limit is reached
        if ($limit <> ''){

            $limit_day             = isset($limit["limit_day"]) ? $limit["limit_day"] : 1000;
            $limit_day_reset       = isset($limit["limit_day_reset"]) ? substr($limit["limit_day_reset"],0,10) : '946681200';
            $limit_minute          = isset($limit["limit_minute"]) ? $limit["limit_minute"] : 1000;
            $limit_minute_reset    = isset($limit["limit_minute_reset"]) ? substr($limit["limit_minute_reset"],0,10) : '946681200';
            $now = new DateTime();
            $now = $now->format('Y-m-d H:i:s.u');
            $now = strtotime($now);

            if ($limit_minute < 10){

                if ($limit_minute_reset > $now){

                    return array("stop_limit" => $limit);

                }

            } elseif ($limit_day < 10){

                if ($limit_day_reset > $now){

                    return array("stop_limit" => $limit);

                }

            }

        }


        // if pagination is defined, add it to url call
        if ($pagination <> ''){

            $resource_url = $pagination;


        } else {

            // Build url conform method
            if (isset($table["method"])){

                if ($table["method"] == 'sync'){

                    $resource_url = $table["api_url"] . "?\$filter=Timestamp gt " . $timestamp . "L&\$select=";

                } elseif ($table["method"] == 'modified'){

                    $resource_url = $table["api_url"] . "?\$filter=Modified ge datetime'" . $timestamp . "'&\$select=";

                } elseif ($table["method"] == 'filter') {

                    if (isset($table["filter"]) && isset($this->access[$table["filter"]])) {

                        $resource_url = $table["api_url"] . "?\$filter=" . $table["filter"] . " ge " . $this->access[$table["filter"]]     . "&\$select=";

                    } else {

                        $resource_url = $table["api_url"] . "?\$select=";

                    }

                } else {

                    $resource_url = $table["api_url"] . "?\$select=";

                }



            } else {

                $resource_url = $table["api_url"] . "?\$select=";

            }

            foreach($table["fields"] as $field => $value){
            
                $resource_url .= $field . ",";
                        
            }
            
            $resource_url = rtrim($resource_url, ",");

            $resource_url = $this->access["exact_api_url"] . '/api/v1/'  . $this->access["division"] . $resource_url;

        }

        // build http header
        $header = array(
            'Content-Type:application/json',
            'Authorization: Bearer '. $this->access_token, 
            'Accept: application/json'	
        );

        // call api
        $response = $this->curl_get($resource_url, $header);

        // handle limits from header
        $header = isset($response["header"]) ? $response["header"] : '';

        $limit = array(

            "limit_day"             => isset($header["x-ratelimit-remaining"]) ? $header["x-ratelimit-remaining"] : 0,
            "limit_day_reset"       => isset($header["x-ratelimit-reset"]) ? $header["x-ratelimit-reset"] : '',
            "limit_minute"          => isset($header["x-ratelimit-minutely-remaining"]) ? $header["x-ratelimit-minutely-remaining"] : 0,
            "limit_minute_reset"    => isset($header["x-ratelimit-minutely-reset"]) ? $header["x-ratelimit-minutely-reset"] : 0,

        );

        // check for errors
        $error = isset($response["error"]) ? $response["error"] : '';

        // check if response returned
        $response = isset($response["response"]) ? $response["response"] : '';

        $response = json_decode($response, true);

        $pagination = isset($response["d"]["__next"]) ? $response["d"]["__next"] : '';

        $response = isset($response["d"]["results"]) ? $response["d"]["results"] : '';

        return array("response" => $response, "limit"  => $limit, "pagination" => $pagination, "error"  => $error);

    }

    public function curl_get($resource_url, $header){


        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $resource_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $response = curl_exec($ch);

        $errorcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);        
        $error = curl_error($ch);

        if($response === FALSE){

            echo '<br> Error!';
            echo '<br> Url: ' . $resource_url;
            echo '<br> Response: '; 
            var_dump($response);
            echo '<br> Error: ' . $error;
            echo '<br> Header: ' . $errorcode . '<br>';

            $error = array( "error" =>  
                                        array(
                                            "error_code"    => $errorcode,
                                            "call_type" => "GET",
                                            "url" => $resource_url,
                                            "message"   => $response
                                        )    
                        );

            return $error;

        } elseif($errorcode > 299){

            echo '<br> Error!';
            echo '<br> Url: ' . $resource_url;
            echo '<br> Response: '; 
            var_dump($response);
            echo '<br> Error: ' . $error;
            echo '<br> Header: ' . $errorcode ;
            echo '<br> Header message: ' . $this->http_error($errorcode) . '<br>';

            $error = array("error" =>   array(
                                    "error_code"    => $errorcode,
                                    "error_message" => $this->http_error($errorcode),
                                    "call_type" => "GET",
                                    "url" => $resource_url,
                                    "message"   => $response
                                    )    
            );

            return $error;

        }

        if (isset($response["error"])){

            echo "url: " . $resource_url . '<br>';
            echo "error:" . '<br>';
            var_dump($response);

            $error = array("error" => array(
                                        "call_type" => "GET",
                                        "url" => $resource_url,
                                        "message"   => $response
                                        )    
                            );

            return $error;

        }

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        $header = substr($response, 0, $header_size);
        $response = substr($response, $header_size);
        $array_header = $this->string_to_array($header);

        return array("response" => $response, "header"  => $array_header);


    }


    public function string_to_array($header){  
         
        $result = array();

        foreach (explode("\n", $header) as $i=>$line) { 

            $temp = explode(":",$line); 

            $temp = array_map('trim',$temp);  //trim each element  

            if ( isset($temp[0]) and isset($temp[1]) ){  

                // process only the data separated by ”:” 
                $result[$temp[0]] = $temp[1];

            } 
        }

        return $result; 

    }



    public function http_error($errorcode){

        $text = '';

        switch ($errorcode) {
            case 100: $text = 'Continue'; break;
            case 101: $text = 'Switching Protocols'; break;
            case 200: $text = 'OK'; break;
            case 201: $text = 'Created'; break;
            case 202: $text = 'Accepted'; break;
            case 203: $text = 'Non-Authoritative Information'; break;
            case 204: $text = 'No Content'; break;
            case 205: $text = 'Reset Content'; break;
            case 206: $text = 'Partial Content'; break;
            case 300: $text = 'Multiple Choices'; break;
            case 301: $text = 'Moved Permanently'; break;
            case 302: $text = 'Moved Temporarily'; break;
            case 303: $text = 'See Other'; break;
            case 304: $text = 'Not Modified'; break;
            case 305: $text = 'Use Proxy'; break;
            case 400: $text = 'Bad Request'; break;
            case 401: $text = 'Unauthorized'; break;
            case 402: $text = 'Payment Required'; break;
            case 403: $text = 'Forbidden'; break;
            case 404: $text = 'Not Found'; break;
            case 405: $text = 'Method Not Allowed'; break;
            case 406: $text = 'Not Acceptable'; break;
            case 407: $text = 'Proxy Authentication Required'; break;
            case 408: $text = 'Request Time-out'; break;
            case 409: $text = 'Conflict'; break;
            case 410: $text = 'Gone'; break;
            case 411: $text = 'Length Required'; break;
            case 412: $text = 'Precondition Failed'; break;
            case 413: $text = 'Request Entity Too Large'; break;
            case 414: $text = 'Request-URI Too Large'; break;
            case 415: $text = 'Unsupported Media Type'; break;
            case 500: $text = 'Internal Server Error'; break;
            case 501: $text = 'Not Implemented'; break;
            case 502: $text = 'Bad Gateway'; break;
            case 503: $text = 'Service Unavailable'; break;
            case 504: $text = 'Gateway Time-out'; break;
            case 505: $text = 'HTTP Version not supported'; break;
            default: $text = '';          
            break;
        }


        return $text;


    }







}