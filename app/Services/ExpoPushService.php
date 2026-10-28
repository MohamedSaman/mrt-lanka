<?php

namespace App\Services;

use GuzzleHttp\Client;

class ExpoPushService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://exp.host/--/api/v2/',
            'timeout' => 10,
        ]);
    }

    /**
     * Send array of messages to Expo push endpoint. Each message should include: to, sound, title, body, data
     * @param array $messages
     * @return array responses
     */
    public function send(array $messages)
    {
        $responses = [];

        // Chunk to 100 messages per Expo recommendation
        $chunks = array_chunk($messages, 100);
        foreach ($chunks as $chunk) {
            try {
                $res = $this->client->post('push/send', [
                    'json' => $chunk,
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ],
                ]);

                $body = json_decode((string)$res->getBody(), true);
                $responses[] = $body;
            } catch (\Exception $e) {
                // Log and continue
                logger()->error('Expo push send error: ' . $e->getMessage());
            }
        }

        return $responses;
    }
}
