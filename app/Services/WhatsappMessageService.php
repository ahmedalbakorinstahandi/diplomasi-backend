<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsappMessageService
{


    public static function send(string $number, string $message): array
    {

        return [];

        
        $params = [
            'token' => '3u2tx8mllx3ztblj',
            'to'    => $number,
            'body'  => $message,
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.ultramsg.com/instance120060/messages/chat",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_HTTPHEADER => [
                "content-type: application/x-www-form-urlencoded"
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return [
                'status' => false,
                'msg'    => "cURL Error #: $err",
            ];
        }

        return [
            'status' => true,
            'msg'    => 'Message sent successfully',
            'response' => $response,
        ];
    }
}
