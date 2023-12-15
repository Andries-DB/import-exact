<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PostBookings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $booking;
    protected $division;
    protected $access_token;

    /**
     * Create a new job instance.
     */
    public function __construct($booking, $division, $access_token)
    {
        $this->booking = $booking;
        $this->division = $division;
        $this->access_token = $access_token;
    }
    

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $booking = $this->booking;
        $division = $this->division;
        $access_token = $this->access_token;

        $salesEntryLines = [];

            foreach($booking->lines as $line) {
                $amount = floatval($line->bedrag);
                $salesEntryLines[] = [
                    "AmountFC" => $amount,
                    "GLAccount" => $this->getGUIDGLAccount($access_token, $line->grootboekrekening),
                    'VATCode' => $line->btwcode ? $line->btwcode : '0',
                ];
            }
            $amount = floatval($line->bedrag);
            // $booking->datum is a unix timestamp, we need to convert it to a date
            $datum = date('Y-m-d', $booking->datum);
            $postData = [
                "Customer" => $this->getGUIDCustomer($access_token, $booking->klantcode),
                "Journal" => '700',
                "EntryDate" => $datum,
                "Description" => $booking->omschrijving,
                "SalesEntryLines" => $salesEntryLines,
                "AmountFC" => $amount
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
        }

        protected function getGUIDCustomer($access_token, $customerCode)
        {
            $division = env('DIVISION');
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, env('EXACT_API_URL') . '/api/v1/' . $division . '/crm/Accounts?$select=ID,Code');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $access_token,
                'Accept: application/json',
            ]);

            $response = curl_exec($ch); 
            $data = json_decode($response);
            curl_close($ch);

            
            if(isset($data->d->results)){
                // Look for the customer with the given code
                foreach($data->d->results as $customer){
                    if($customer->Code == $customerCode){
                        return $customer->ID;
                    }
                }
            }
            return false;
            }

        protected function getGUIDGLAccount($access_token, $glAccountCode)
        {
            $division = env('DIVISION');

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, env('EXACT_API_URL') . '/api/v1/' . $division . '/financial/GLAccounts?$select=ID&$filter=Code+eq+%27' . $glAccountCode . '%27');
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
    }
