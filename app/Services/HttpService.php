<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Http related service function class
 */
class HttpService
{
    /**
     * GET REQUEST: Get report data from url as string
     *
     * @param obj $client
     * @param string $url
     *
     * @return string
     */
    public static function get($client, $url)
    {
        try {
            $res = $client->request("GET", $url, [
                'headers' => [
                    'User-Agent'   => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.122 Safari/537.36',
                ]
            ]);
            return $res->getBody()->getContents();
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());
        }
        return "";
    }

    /**
     * POST REQUEST: Get report data from url using post request
     *
     * @param obj $client
     * @param string $url
     * @param string $id
     *
     * @return string
     */
    public static function post($client, $url, $id)
    {
        try {
            $res = $client->request("POST", $url, [
                'headers' => [
                    'User-Agent'   => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.122 Safari/537.36',
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode(
                    [
                        'id' => $id
                    ]
                )
            ]);
            return $res->getBody()->getContents();
        } catch (\Exception $ex) {
            Log::error($ex->getMessage());
        }
        return "";
    }
}
