<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PostAccounts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $account;
    protected $division;
    protected $access_token;

    /**
     * Create a new job instance.
     */
    public function __construct($account, $division, $access_token)
    {
        $this->account = $account;
        $this->division = $division;
        $this->access_token = $access_token;
    }

    /**
     * Execute the job.
     */
    public function handle() : void
    {
        $account = $this->account;
        $division = $this->division;
        $access_token = $this->access_token;

        $postData = [
            "Code" => $account->klantcode,
            "Name" => $account->naam,
            "AddressLine1" => $account->straat,
            "Postcode" => $account->postcode,
            "City" => $account->gemeente,
            "VATNumber" => $account->btwnummer,
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
    }
}
