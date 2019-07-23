<?php
namespace App\Shell;
use App\Util\QuickBlox;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\TableRegistry;
use Cake\Console\Shell;
use DebugKit\Model\Behavior\TimedBehavior;
use App\Model\Table\LocalistAlertsTable;
use Cake\Log\Log;

class QbShell extends Shell {

    public function getOptionParser() {
        $parser = parent::getOptionParser();
        $parser->addArgument('operation', [
            'required' => true,
            'choices' => ['create', 'update', 'subscribe', 'push']
        ]);
        $parser->addArgument('user id', [
            'required' => true
        ]);
        return $parser;
    }

    public function create($userId) {

        $Users = TableRegistry::getTableLocator()->get('Users');
        $user = $Users->get($userId);

        $user = QuickBlox::createUser($user);
        if (!isset($user->chat_user)) {
            Log::error("Creation failed for user: " . $userId);
            return;
        } else {
            // User returns with new chat_user, login & password values
            if ($Users->save($user)) {
                Log::debug("Chat user created for user: " . $userId);
            };
        }
    }

    public function subscribe($userId) {
        $Users = TableRegistry::getTableLocator()->get('Users');
        $user = $Users->get($userId);

        $subscribed = QuickBlox::setAPNSToken($user);
        if (! $subscribed) {
            Log::error("Subscription failed for user: " . $userId);
            return;
        } else {
            $user->set('chat_registered', true);
            if ($Users->save($user)) {
                Log::debug("Chat user subscribed for user: " . $userId);
                Log::debug(QuickBlox::sendPush($user, [
                    'aps' => [
                        'category' => LocalistAlertsTable::DATA_UPDATE_CATEGORY,
                        'alert' => [
                            'title' => 'Welcome to Localist!',
                            'body' => 'We are stoked your here.'
                        ]
                    ]
                ]));
            };
        }
    }

    public function push($userId) {
        $Users = TableRegistry::getTableLocator()->get('Users');
        $user = $Users->get($userId);
        $payload = [
            'aps' => [
                'alert' => [
                    'title' => 'Hey You!',
                    'body' => 'Nada mucho...'
                ]
            ]
        ];

        $pushed = QuickBlox::sendPush($user, $payload);
    }

    public static function createUser($userId) {
        Log::debug("Creating chat user in background for $userId");
        $cmd = ROOT . DS . 'bin' . DS .  "cake Qb create " .
            "$userId --quiet >> " .
            LOGS . 'debug.log 2>&1 &';
        shell_exec($cmd);
        // status not useful since the command doesn't produce output
    }

    public static function subscribeUser($userId) {
        Log::debug("Subscribing chat user in background for $userId");
        $cmd = ROOT . DS . 'bin' . DS .  "cake Qb subscribe " .
            "$userId --quiet >> " .
            LOGS . 'debug.log 2>&1 &';
        shell_exec($cmd);
        // status not useful since the command doesn't produce output
    }
}
