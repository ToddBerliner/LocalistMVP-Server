<?php
namespace App\Util;

use Cake\Log\Log;
use Cake\Http\Client;
use Cake\ORM\TableRegistry;

class QuickBlox {
    const APPLICATION_ID = '77470';
    const AUTHORIZATION_KEY = 'ScFYDdD6bpGsQga';
    const AUTHORIZATION_SECRET = 'vyvBrGNm2RwncYr';

    const QB_API_ENDPOINT = 'https://api.quickblox.com/';
    const QB_PATH_SESSION = 'session.json';
    const QB_PATH_USER = 'users.json';
    const QB_PATH_USER_UPDATE = 'users/%d.json';
    const QB_PATH_SUBSCRIBE = 'subscriptions.json';
    const QB_PATH_SUBSCRIBE_UPDATE = 'subscriptions/%d.json';
    const QB_PATH_EVENTS = 'events.json';

    public static function getSession($user = null) {

        $params = [];
        if ($user !== null) {
            // user session rather than app session
            $params += ['user' => [
                'login' => $user->chat_login,
                'password' => $user->chat_password
            ]];
        }
        $params = self::_addParams($params);
        $http = new Client();
        try {
            $response = $http->post(
                self::QB_API_ENDPOINT . self::QB_PATH_SESSION,
                json_encode($params),
                self::_baseOptions());
            if ($response->isOk()) {
                $token = $response->json['session']['token'];
                Log::debug("Got token " . $token);
                return $token;
            }
            // else
            Log::error('Couldn\'t get session from QuickBlox, code ' .
                $response->getStatusCode());
            Log::error('Response: ' . print_r($response->json, true));
            Log::error('Params: ' . print_r($params, true));
        } catch (\Exception $e) {
            Log::error('Couldn\'t get session from QuickBlox, ' .
                $e->getMessage());
        }
        return null;
    }

    public static function createUser($user) {
        if (! empty($user->chat_user)) {
            // already done
            return $user;
        }
        $session = self::getSession();
        if (! isset($session)) {
            return $user;
        }

        $payload = [
            'user' => [
                'full_name' => "{$user->name}",
                'login' => 'localist_' . $user->id,
                'password' => md5('localist' . $user->id),
                'external_user_id' => $user->id
            ]
        ];
        $quickBloxUser = self::_createUser($session, $payload);
        if ($quickBloxUser) {
            $quickBloxUser = $quickBloxUser['user'];
            $user->chat_user =
                strval($quickBloxUser['id']);
            $user->chat_login = $payload['user']['login'];
            $user->chat_password = $payload['user']['password'];
            Log::debug("Created QB User: " . $user->chat_user
                . " with password: " . $user->chat_password
                . " and login: " . $user->chat_login);
        }

        return $user;
    }

    public static function deleteUser($user) {

        if (empty($user->chat_user)) {
            // already done
            return $user;
        }
        $session = self::getSession($user);

        if (! isset($session)) {
            return $user;
        }

        $userParams = self::_addParams();
        try {

            $http = new Client();
            $url = self::QB_API_ENDPOINT . sprintf(self::QB_PATH_USER_UPDATE,
                    $user->chat_user);
            $response = $http->delete($url,
                json_encode($userParams),
                self::_baseOptions($session));

            if ($response->isOk()) {
                $user->chat_user = null;
                $user->chat_login = null;
                $user->chat_password = null;
                Log::debug("Deleted QB user for " . $user->id);
            } else {
                Log::error('Couldn\'t delete user in QuickBlox, code ' .
                    $response->getStatusCode());
                Log::error('Response: ' . print_r($response->json, true));
                Log::error('Params: ' . print_r($userParams, true));
            }
        } catch (\Exception $e) {

            Log::error('Couldn\'t delete user in QuickBlox, ' .
                $e->getMessage());
        }

        return $user;
    }

    public static function setAPNSToken($user) {

        if (! isset($user->chat_user) || ! isset($user->chat_password)) {
            Log::error('Couldn\'t set APNS token for ' . $user->id .
                ', chat user not set');
            return false;
        }

        if (! isset($user->apns)) {
            Log::error('Couldn\'t set APNS token for ' . $user->id .
                ', APNS not set');
            return false;
        }

        $session = self::getSession($user);
        if (! isset($session)) {
            return false;
        }

        $http = new Client();

        // subscribe the user to push notifications
        $env = 'development';
        $params = self::_addParams([
            'notification_channels' => 'apns',
            'push_token' => [
                'environment' => $env,
                'client_identification_sequence' => $user->apns
            ],
            'device' => [
                'platform' => 'ios',
                'udid' => $user->udid
            ]
        ]);
        try {
            $url = self::QB_API_ENDPOINT . self::QB_PATH_SUBSCRIBE;
            $response = $http->post($url,
                json_encode($params),
                self::_baseOptions($session));
            if (! $response->isOk()) {
                Log::error('Couldn\'t subscribe to pushes for user in QuickBlox, code ' .
                    $response->getStatusCode());
                Log::error('Params: ' . print_r($params, true));
                Log::error('Response: ' . print_r($response->json, true));
                return false;
            } else {
                Log::debug("Set APNS token for user: " . $user->id);
            }
        } catch (\Exception $e) {
            Log::error('Couldn\'t subscribe to pushes for user in QuickBlox, ' .
                $e->getMessage());
            return false;
        }

        // made it through
        Log::debug('Registered APNS token for user ' . $user->id .
            " in QuickBlox: $user->apns");
        return true;
    }

    public static function sendPush($user, $message) {

        if (! isset($user->apns)) {
            return false;
        }
        $session = self::getSession($user);

        if (! isset($session)) {
            return false;
        }

        $http = new Client();

        $payload = [];
        if (isset($message)) {
            $payload['message'] = $message;
        }
        $env = 'development';
        $params = self::_addParams([
            'event' => [
                'notification_type' => 'push',
                'push_type' => 'apns',
                'environment' => $env,
                'user' => ['ids' => $user->chat_user],
                'message' => 'payload=' . base64_encode(json_encode($message))
            ]
        ]);

        try {
            $url = self::QB_API_ENDPOINT . self::QB_PATH_EVENTS;
            $response = $http->post($url,
                json_encode($params),
                self::_baseOptions($session));

            if (! $response->isOk()) {
                Log::error("Couldn\'t send push to user {$user->id} in QuickBlox, code " .
                    $response->getStatusCode());
                Log::error($params);
                // When this happens, QuickBlox deletes the APNS token(s)
                // for the user.  Clear it here too so the user gets
                // reregistered later
                return false;
            } else {
                Log::debug("Sent push to: " . $user->id . " - " . json_encode($message));
            }
        } catch (\Exception $e) {
            Log::error("Couldn\'t send push to user {$user->id} in QuickBlox, " .
                $e->getMessage());
            return false;
        }

        // made it through
        return true;
    }

    private static function _addParams($params = []) {
        $params += [
            'application_id' => self::APPLICATION_ID,
            'auth_key' => self::AUTHORIZATION_KEY,
            'nonce' => rand(),
            'timestamp' => time()
        ];
        $params = self::_addSignature($params);
        return $params;
    }

    private static function _addSignature($params) {
        // params are sorted alphabetically first
        self::_ksortRecursive($params);
        $signature = http_build_query($params);
        $signature = urldecode($signature);
        $params['signature'] = hash_hmac('sha1', $signature,
            self::AUTHORIZATION_SECRET);
        return $params;
    }

    private static function _ksortRecursive(&$array) {
        ksort($array);
        foreach ($array as &$arr) {
            if (is_array($arr)) {
                self::_ksortRecursive($arr);
            }
        }
    }

    private static function _baseOptions($session = null) {
        $options = [
            'type' => 'json',
            'redirect' => 3,
            'timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
                'QuickBlox-REST-API-Version' => '0.1.0'
            ]
        ];
        if ($session !== null) {
            $options['headers']['QB-Token'] = $session;
        }
        return $options;
    }

    private static function _createUser($session, $payload) {
        $url = self::QB_API_ENDPOINT . self::QB_PATH_USER;
        return self::_post($session, $url, $payload);
    }

    private static function _post($session, $url, $payload) {
        try {
            $http = new Client();
            $response = $http->post(
                $url,
                json_encode($payload),
                self::_baseOptions($session));
            if (! $response->isOk()) {
                Log::error("Couldn\'t POST payload in QuickBlox:"
                    . $url . ' '
                    . print_r($session, true) . ' '
                    . print_r($payload, true) . ' '
                    . $response->getStatusCode());
                return false;
            } else {
                return $response->json;
            }
        } catch (\Exception $e) {
            Log::error("Exception in QB POST: " .
                $e->getMessage());
            return false;
        }
    }

    private static function _put($session, $url, $payload) {
        try {
            $http = new Client();
            $response = $http->put(
                $url,
                json_encode($payload),
                self::_baseOptions($session));
            if (! $response->isOk()) {
                if ($response->getStatusCode() == 429) {
                    sleep(1);
                    Log::debug("Hit QB throttle, waiting a sec.");
                    return self::_put($session, $url, $payload);
                }
                Log::error("Couldn\'t PUT payload in QuickBlox:"
                    . $url . ' '
                    . print_r($session, true) . ' '
                    . print_r($payload, true) . ' '
                    . $response->getStatusCode());
                return false;
            } else {
                return $response->json;
            }
        } catch (\Exception $e) {
            Log::error("Exception in QB PUT: " .
                $e->getMessage());
            return false;
        }
    }

    private static function _delete($session, $url) {
        try {
            $http = new Client();
            $response = $http->delete($url, null, self::_baseOptions($session));
            if (! $response->isOk()) {

                if ($response->getStatusCode() == 429) {
                    sleep(1);
                    Log::debug("Hit QB throttle, waiting a sec.");
                    return self::_delete($session, $url);
                }
                Log::error("Couldn\'t POST payload in QuickBlox:"
                    . $url . ' '
                    . print_r($session, true) . ' '
                    . $response->getStatusCode());
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Exception in QB DELETE: " .
                $e->getMessage());
            return false;
        }
        return true;
    }
}