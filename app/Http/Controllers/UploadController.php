<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function get(Request $r) {
        session_start();
        $refresh_token = $_SESSION['refresh_token'];

        if(empty($refresh_token) || $refresh_token == "") {
            $access_token = $this->setup();
        } else {
            $access_token = $this->access_token($refresh_token);
        }

        $result['access_token'] = $access_token;

        $url = explode('/', $r->url());
        $url = end($url);
        switch($url) {
            case 'crm-account':
                $result['get'] = $this->getAccount($access_token);
                break;
            case 'booking':
                $result['get'] = $this->getBooking($access_token);
                break;
            default:
                break;
        }

        return response()->json($result['get']);
    }

    public function post(Request $r) {
        session_start();
        $refresh_token = $_SESSION['refresh_token'];

        if(empty($refresh_token) || $refresh_token == "") {
            $access_token = $this->setup();
        } else {
            $access_token = $this->access_token($refresh_token);
        }

        $result['access_token'] = $access_token;

        $url = explode('/', $r->url());
        $url = end($url);
       
        switch($url) {
            case 'crm-account':
                $result['post'] = $this->postAccount($access_token);
                break;
            case 'booking':
                $result['post'] = $this->postBooking($access_token);
                break;
            default:
                break;
        }

        return response()->json($result['post']);
    }

    public function setup() {
 
        if(!empty($_GET['code'])) {
            $code = rawurldecode($_GET['code']);
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, env('EXACT_API_URL') . '/api/oauth2/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'code' => $code,
                'client_id' => env('CLIENT_ID'),
                'client_secret' => env('CLIENT_SECRET'),
                'redirect_uri' => env('REDIRECT_URI'),
                'grant_type' => 'authorization_code',
            ]);

            $response = curl_exec($ch); 
            $response = json_decode($response);  
            
            if (!isset($response->refresh_token)){
                echo "We didn't receive an access. Please check your credentials and user rights <br>";
                return "";
            }
 
            $_SESSION['refresh_token'] = $response->refresh_token;
            $_SESSION['access_token'] = $response->access_token;
            $_SESSION['access_token_time'] = gmdate('Y-m-d H:i:s');
            $_SESSION['expires_in'] = $response->expires_in;
            return $response->access_token;

        } else {
            $query = [
                'client_id' => env('CLIENT_ID'),
                'response_type' => 'code',
                'redirect_uri' => env('REDIRECT_URI'),
            ];
            
            header('Location: ' . env('EXACT_API_URL') . '/api/oauth2/auth?' . http_build_query($query));
            exit;
        }   
    }

    public function access_token($refresh_token) {
        $access_token = $_SESSION['access_token'];
        $access_token_time = $_SESSION['access_token_time'];
        $expires_in = $_SESSION['expires_in'];

        if(isset($access_token) && isset($access_token_time)){
            if ($access_token_time != NULL && $access_token != NULL) {
                $now = strtotime(gmdate('Y-m-d H:i:s'));
                $token = strtotime($access_token_time);
                $diff = round(abs($now - $token));

                $expires = $expires_in ? intval($expires_in) : 600;

                $expires = $expires - 30;

                if ($diff < $expires){
                    return $access_token;
                }
            }
        }
        
        // get new access and refresh token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, env('EXACT_API_URL') . '/api/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'refresh_token' => $refresh_token,
            'grant_type' => "refresh_token",
            'client_id' => env('CLIENT_ID'),
            'client_secret' => env('CLIENT_SECRET'),
        ]);

        $response = curl_exec($ch); 
        $response = json_decode($response);

        // On error auth, log error and abort script.
        if (isset($response->error)){
            $error_description = isset($response->error_description) ? $response->error_description : "";
            dd('Error: ' . $response->error . ' - ' . $error_description);
        }

        if (!isset($response->refresh_token) || $response->refresh_token == ""){
            $this->setup();
        } 

        $_SESSION['refresh_token'] = $response->refresh_token;
        $_SESSION['access_token'] = $response->access_token;
        $_SESSION['access_token_time'] = date('Y-m-d H:i:s');
        $_SESSION['expires_in'] = $response->expires_in;

        return $response->access_token;
    }

    public function getAccount($access_token) {
        $division = env('DIVISION');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, env('EXACT_API_URL') . '/api/v1/' . $division . '/crm/Accounts?$select=ID,Name,Website');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Accept: application/json',
        ]);

        $response = curl_exec($ch); 
        $response = json_decode($response);
        return $response;
    }

    public function getBooking($access_token) {
        $division = env('DIVISION');
        $customer = '209c49b5-805a-480b-b974-a234d445c71f';

        $ch = curl_init(); 
        curl_setopt($ch, CURLOPT_URL, env('EXACT_API_URL') . '/api/v1/' . $division . '/salesentry/SalesEntries?$select=Journal,EntryNumber,Customer,AmountDC&$filter=Customer%20eq%20guid%27' . $customer . '%27');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Accept: application/json',
        ]);

        $response = curl_exec($ch); 

        $response = json_decode($response);
        return $response;
    }

    public function getGUIDGLAccount($access_token , $code) {
        $division = env('DIVISION');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, env('EXACT_API_URL') . '/api/v1/' . $division . '/financial/GLAccounts?$select=ID&$filter=Code+eq+%27' . $code . '%27');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Accept: application/json',
        ]);

        $response = curl_exec($ch); 
        $data = json_decode($response);
        curl_close($ch);

       
        if (isset($data->d->results[0]->ID)){
            return $data->d->results[0]->ID;
        }
        return false;
    }

    public function postAccount($access_token) {
        $division = env('DIVISION');
        $postData = [
            'Name' => 'Okappi Account',
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, env('EXACT_API_URL') . '/api/v1/' . $division . '/crm/Accounts');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Accept: application/json',
            'Content-Type: application/json',
        ]);

        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            echo 'cURL-fout: ' . curl_error($ch);
        }

        curl_close($ch);
        $decodedResponse = json_decode($response);
        return $decodedResponse;
    }

    public function postBooking($access_token) {
        $division = env('DIVISION');

        $postData = [
            "Journal" => "700",
            "Customer" => "209c49b5-805a-480b-b974-a234d445c71f",
            "SalesEntryLines" => [
                [
                    "Description" => "Test",
                    "VATCode" => "5",
                    "AmountFC" => "5.0",
                    "GLAccount" => $this->getGUIDGLAccount($access_token, '700000'),
                ]
            ],
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, env('EXACT_API_URL') . '/api/v1/' . $division . '/salesentry/SalesEntries');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Accept: application/json',
            'Content-Type: application/json',
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo 'cURL-fout: ' . curl_error($ch);
        }

        curl_close($ch);
        $decodedResponse = json_decode($response);
        return $decodedResponse;
    }

}
