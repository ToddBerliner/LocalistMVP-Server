<?php
namespace App\Shell;

use App\Util\QuickBlox;
use Cake\Console\ConsoleOptionParser;
use Cake\Console\Shell;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

class SendPushNotificationsShell extends Shell {

    public function main($alertId) {
        Log::debug("Sending push notification for alertId: " . $alertId);
        // Get alert
        $LocalistAlerts = TableRegistry::getTableLocator()->get('LocalistAlerts');
        $Users = TableRegistry::getTableLocator()->get('Users');
        // Get payloads for alert
        $payloads = $LocalistAlerts->generatePushPayloadsForAlertId($alertId);
        Log::debug("Got payloads for alert: " . json_encode($payloads));
        foreach($payloads as $userId => $payload) {
            $user = $Users->get($userId);
            if (! $user) {
                Log::error("Couldn't get user for alert: " . $alertId);
                continue;
            }
            QuickBlox::sendPush($user, $payload);
        }
    }

    public static function sendPushForAlertId($alertId) {
        $LocalistAlerts = TableRegistry::getTableLocator()->get('LocalistAlerts');
        $cmd = ROOT . DS . 'bin' . DS .  "cake SendPushNotifications " .
            "$alertId --quiet >> " .
            LOGS . 'debug.log 2>&1 &';
        shell_exec($cmd);
    }
}