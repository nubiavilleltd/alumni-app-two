<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once FCPATH . 'vendor/autoload.php';

use ExpoSDK\Expo;
use ExpoSDK\ExpoMessage;

function send_notification($title, $body, $pushTokens, $url)
{

    $url = !empty($url) ? "batnfoundation://mobile/" . $url : "";

    log_message("error", "push notification url " . $url);

    if ($title != "" && $body != "" && !empty($pushTokens)) {
        log_message("error", "title, body and pushtokens are set");

        $messages = [];

        foreach ($pushTokens as $token) {
            if (strpos($token, 'ExponentPushToken') === 0) {
                $message = new ExpoMessage([
                    'title' => $title,
                    'body' => $body,
                    'to' => $token,
                    'data' => [
                        'url' => $url
                    ],
                    'channelId' => 'default'
                ]);

                try {
                    $expo = new Expo();
                    $response = $expo->send([$message])->push();
                    $data = $response->getData();

                    log_message('error', "Data for {$token}: " . var_export($data, true));
                } catch (Exception $e) {
                    log_message("error", 'Failed to send notification to token ' . $token . ': ' . $e->getMessage());
                }

            } else {
                echo "Invalid token format: " . $token;
            }
        }

        return true;

        // try {
        // 	log_message("error", 'got here try block');
        //     $expo = new Expo();
        //     $response = $expo->send($messages)->push();
        //     $data = $response->getData();

        //     log_message("error", 'response data ' . var_export($data,true));

        //     //Debugging output
        // 	echo "<pre>";
        //     echo "\nNotification sent successfully.\n";
        //     echo "</pre>";

        //     return $response;

        //     log_message("error", 'resposne ' . var_export($response, true));

        // } catch (Exception $e) {
        // 	log_message("error", 'Failed to send notification: ' . var_export($e->getMessage(), true));
        //     echo "Failed to send notification: " . $e->getMessage();
        // 	return false;
        // }

    } else {
        return "Title, body, and recipient(s) are required.";
    }
}
