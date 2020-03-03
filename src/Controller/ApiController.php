<?php
namespace App\Controller;

use App\Model\Table\LocalistAlertsTable;
use App\Util\QuickBlox;
use App\Shell\SendPushNotificationsShell;
use App\Test\Fixture\UsersFixture;
use Cake\Controller\Controller;
use Cake\Event\Event;
use Cake\I18n\FrozenTime;
use Cake\Log\Log;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use App\Model\Entity\Localist;
use Cake\Utility\Hash;
use DebugKit\Model\Behavior\TimedBehavior;

class ApiController extends AppController {

    private $_startTime;

    const ALL_FIELDS = [
        'name',
        'first_name',
        'udid',
        'apns',
        'phone',
        'image_name'
    ];

    public function initialize() {
        parent::initialize();
    }

    public function updateLists() {
        $Localists = TableRegistry::getTableLocator()->get('Localists');
        $localists = $Localists->find()->all();
        $localists->each(function($localist) use ($Localists) {
            $json = json_decode($localist['json'], true);
            if (!array_key_exists('markedItems', $json)) {
                $json['markedItems'] = [];
                $localist['json'] = json_encode($json);
                $Localists->save($localist);
                var_dump("Updated {$localist['id']}");
            } else {
                var_dump("{$localist['id']} already updated.");
            }
        });
    }

    public function getServerPeople() {
        $Users = TableRegistry::getTableLocator()->get('Users');
        $data = $Users->find()->all()->toArray();
        $this->set('people', $data);
    }

    public function syncData() {

        $this->_startTime = microtime();

        $data = $this->request->getData();

        // sync the data
        $user = $this->_syncUser($data['User']);
        $this->_syncPeopleForUser($user, $data['People']);
        $this->_syncListsForUser($user, $data['Lists']);

        $this->set('data', $this->_getDataForUser($user));

        Log::debug("SyncData for " . $user->first_name
            . " in " . self::_elapsedMS($this->_startTime) . "ms");

    }

    public function deleteList() {
        $params = $this->request->getQueryParams();
        $userId = $params['user_id'];
        $listId = $params['id'];

        $Users = TableRegistry::getTableLocator()->get('Users');
        $Lists = TableRegistry::getTableLocator()->get('Localists');
        $UserLocalists = TableRegistry::getTableLocator()->get('UserLocalists');
        $LocalistAlerts = TableRegistry::getTableLocator()->get('LocalistAlerts');

        $user = $Users->get($userId);
        $list = $Lists->get($listId);

        try {

            // Get list members to save as meta in alert
            $listMembers = $UserLocalists->find()
                ->where(['localist_id' => $listId]);
            $memberIds = [];
            $listMembers->each(function($member) use (&$memberIds, $userId) {
                if ($member->user_id != $userId) {
                    $memberIds[] = $member->user_id;
                }
            });

            // Un-associating from any user is an effective delete
            $UserLocalists->deleteAll([
                'localist_id' => $listId
            ]);
            $localistAlert = $LocalistAlerts->save($LocalistAlerts->newEntity([
                'localist_id' => $list->id,
                'action' => LocalistAlertsTable::LIST_DELETED,
                'meta' => implode(',', $memberIds)
            ]));
            if ($localistAlert) {
                $this->_sendPushForAlertId($localistAlert->id);
            }

        } catch (\Exception $e) {
            Log::error("Exception deleting list: " . $e->getMessage()
                . "\n" . $e->getTraceAsString());
            // TODO: figure out how to blow up my phone!
        }

        $this->set('data', $this->_getDataForUser($user));
    }

    private function _syncUser($syncUser) {

        $Users = TableRegistry::getTableLocator()->get('Users');
        $UserSyncs = TableRegistry::getTableLocator()->get('UserSyncs');

        // If ID provided, get that user
        if (! empty($syncUser['id'])) {
            $user = $Users->get($syncUser['id']);
        } else {
            // Look for existing user by phone
            $user = $Users->find()
                ->where(['phone' => $syncUser['phone']])
                ->first();
        }

        if (! $user) {
            // Create a new user record
            $user = $Users->newEntity($syncUser);
        } else {
            // Update the existing user
            foreach(self::ALL_FIELDS as $field) {
                if (! empty($syncUser[$field])
                    && $syncUser[$field] != $user->get($field)) {
                    $user->set($field, $syncUser[$field]);
                }
            }
        }

        if ($user->isDirty()) {

            // check for specific dirty fields
            if ($user->isDirty('apns') || $user->isDirty('udid')) {
                $user->set('chat_registered', false);
            }

            $user = $Users->save($user);
        }

        // Kick off chat user creation if not done
        if (! isset($user->chat_user)) {

            Log::debug("Chat user not set, trying to create chat user");

            // Support unit tests
            if (PHP_SAPI === 'cli') {
                // Simulate chat user creation
                $user->set([
                    'chat_user' => 'abc',
                    'chat_login' => 'abc',
                    'chat_password' => 'abc'
                ]);
                $Users->save($user);
            } else {
                $this->_createQbUser($user);
            }
        }

        if (
            isset($user->chat_user)
            && isset($user->udid)
            && isset($user->apns)
            && ! $user->chat_registered
        ) {
            // Handle unit tests
            if (PHP_SAPI === 'cli') {
                // Simulate success
                Log::debug("CLI subscribing user");
                $user->set('chat_registered', true);
                $Users->save($user);
            } else {

                Log::debug("Chat user not subscribed, trying to subscribe chat user");

                $this->_subscribeQbUser($user);
            }
        }

        // record sync
        $UserSyncs->recordSync($user->id);

        return $user;
    }

    private function _syncPeopleForUser($user, $postedPeople) {
        $UserUsers = TableRegistry::getTableLocator()->get('UserUsers');
        try {
            foreach($postedPeople as $person) {
                if ($user->id == $person['id']) {
                    continue;
                }
                $UserUsers->findOrCreate([
                    'user_id' => $user->id,
                    'other_user_id' => $person['id']
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Exception saving list: " . $e->getMessage()
                . "\n" . $e->getTraceAsString());
            // TODO: figure out how to blow up my phone!
        }
    }

    private function _syncListsForUser($user, $postedLists) {

        $Lists = TableRegistry::getTableLocator()->get('Localists');
        $UserLocalists = TableRegistry::getTableLocator()->get('UserLocalists');
        $LocalistAlerts = TableRegistry::getTableLocator()->get('LocalistAlerts');

        // Check lists against database
        foreach($postedLists as $postedList) {
            // Create if new
            if (! isset($postedList['id'])) {

                // Create the list
                try {

                    $newList = $Lists->save($Lists->newEntity([
                        'updated' => intval($postedList['updated']),
                        'json' => json_encode($postedList)
                    ]));

                    // Create the UserList records - NOTE: creator is included as member
                    // from client. The user must be created as part of the login in the client
                    // so their ID will be set by the time we get the new list posted
                    foreach($postedList['members'] as $member) {
                        $UserLocalists->save($UserLocalists->newEntity([
                            'user_id' => $member['id'],
                            'localist_id' => $newList->id
                        ]));
                        // Create MEMBER_ADDED alerts for other members
                        if ($user->id != $member['id']) {
                            $localistAlert = $LocalistAlerts->save($LocalistAlerts->newEntity([
                                'localist_id' => $newList->id,
                                'action' => LocalistAlertsTable::MEMBER_ADDED,
                                'meta' => $member['id']
                            ]));
                            if ($localistAlert) {
                                $this->_sendPushForAlertId($localistAlert->id);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error("Exception saving list: " . $e->getMessage()
                        . "\n" . $e->getTraceAsString());
                    // TODO: figure out how to blow up my phone!
                }
            } else {

                // Update if existing and more recently updated
                try {

                    $existingList = $Lists->get($postedList['id']);
                    $postedListUpdated = intval($postedList['updated']);

                    if ($postedListUpdated > $existingList->updated) {

                        // Make copies to work with
                        $origList = unserialize(serialize($existingList));

                        // store the updated list
                        $existingList->set([
                            'updated' => $postedListUpdated,
                            'json' => json_encode($postedList)
                        ]);
                        $Lists->save($existingList);

                        // Create the LocalistAlerts based on what changed
                        $this->_syncListUpdates($user->id, $origList, $postedList);

                    }
                } catch (\Exception $e) {
                    Log::error("Exception updating list: " . $e->getMessage()
                        . "\n" . $e->getTraceAsString());
                    // TODO: figure out how to blow up my phone!
                }
            }
        }
    }

    private function _syncListUpdates($userId, $existingList, $postedList) {
        $UserLocalists = TableRegistry::getTableLocator()->get('UserLocalists');
        $LocalistAlerts = TableRegistry::getTableLocator()->get('LocalistAlerts');

        try {

            $existingListId = $existingList->id;
            $existingList = json_decode($existingList->json, true);

            $alerts = [];

            // TODO: consider whether I should test for same items, vs. just a straight
            // check of equality - if things stay in the same order, which i assume they
            // do - then the equality is sufficient
            if ($existingList['items'] != $postedList['items']) {

                // Create ITEMS_UPDATED alert
                $alerts[] = $LocalistAlerts->save($LocalistAlerts->newEntity([
                    'localist_id' => $existingListId,
                    'action' => LocalistAlertsTable::ITEMS_UPDATED,
                    'meta' => $userId
                ]));

            };
            if ($existingList['retailers'] != $postedList['retailers']) {
                // Create ITEMS_UPDATED alert
                $alerts[] = $LocalistAlerts->save($LocalistAlerts->newEntity([
                    'localist_id' => $existingListId,
                    'action' => LocalistAlertsTable::RETAILERS_UPDATED,
                    'meta' => $userId
                ]));
            }

            // Member comparison should be based on phone, names could be different
            // due to each user's contact information
            $existingMemberIds = Hash::extract($existingList['members'], '{n}.id');
            $postedMemberIds = Hash::extract($postedList['members'], '{n}.id');

            $removedIds = array_diff($existingMemberIds, $postedMemberIds);
            $addedIds = array_diff($postedMemberIds, $existingMemberIds);
            if (! empty($removedIds)) {
                foreach($removedIds as $removedId) {
                    $alerts[] = $LocalistAlerts->save($LocalistAlerts->newEntity([
                        'localist_id' => $existingListId,
                        'action' => LocalistAlertsTable::MEMBER_REMOVED,
                        'meta' => $removedId
                    ]));
                }
                $UserLocalists->deleteAll([
                    'localist_id' => $existingListId,
                    'user_id IN' => $removedIds
                ]);
            }

            foreach($addedIds as $addedId) {
                $alerts[] = $LocalistAlerts->save($LocalistAlerts->newEntity([
                    'localist_id' => $existingListId,
                    'action' => LocalistAlertsTable::MEMBER_ADDED,
                    'meta' => $addedId
                ]));
                $UserLocalists->save($UserLocalists->newEntity([
                    'user_id' => $addedId,
                    'localist_id' => $existingListId
                ]));
            }

            // Send push notifications
            foreach($alerts as $alert) {
                $this->_sendPushForAlertId($alert->id);
            }

        }  catch (\Exception $e) {

            Log::error("Exception _syncListUpdates: " . $e->getMessage()
                . "\n" . $e->getTraceAsString());
            // TODO: figure out how to blow up my phone!
        }
    }

    private function _getListsForUser($user) {

        $Users = TableRegistry::getTableLocator()->get('Users');
        $user = $Users->get($user->id, [
            'contain' => [
                'UserLocalists' => [
                    'Localists' => [
                        'UserLocalists' => 'Users'
                    ]
                ]
            ]
        ]);
        $lists = [];
        foreach($user->user_localists as $userList) {
            $lists[] = $userList->localist;
        }
        return $lists;
    }

    private function _getPeopleForUser($user) {
        $UserUsers = TableRegistry::getTableLocator()->get('UserUsers');
        $otherPeople = $UserUsers->find()
            ->where(['user_id' => $user->id])
            ->contain(['Users'])
            ->all();

        $people = [$user];
        foreach($otherPeople as $otherPerson) {
            $people[] = $otherPerson->user;
        }

        return $people;
    }

    private function _getDataForUser($user) {
        $data = [
            'People' => $this->_getPeopleForUser($user),
            'User' => $user,
            'Lists' => $this->_getListsForUser($user)
        ];
        return $data;
    }

    private function _createQbUser($user) {
        $Users = TableRegistry::getTableLocator()->get('Users');
        $user = QuickBlox::createUser($user);
        if (!isset($user->chat_user)) {
            Log::error("Creation failed for user: " . $user->id);
            return;
        } else {
            // User returns with new chat_user, login & password values
            if ($Users->save($user)) {
                Log::debug("Chat user created for user: " . $user->id);
            };
            // Try to subscribe user now
            $this->_subscribeQbUser($user);
        }
    }

    private function _subscribeQbUser($user) {
        $Users = TableRegistry::getTableLocator()->get('Users');
        $subscribed = QuickBlox::setAPNSToken($user);
        if (! $subscribed) {
            Log::error("Subscription failed for user: " . $user->id);
            return;
        } else {
            $user->set('chat_registered', true);
            if ($Users->save($user)) {
                Log::debug("Chat user subscribed for user: " . $user->id);
                QuickBlox::sendPush($user, [
                    'aps' => [
                        'category' => LocalistAlertsTable::ACTIVITY_CATEGORY,
                        'alert' => [
                            'title' => 'Welcome to Localist!',
                            'body' => 'We hope you find Localist useful.'
                        ]
                    ]
                ]);
            };
        }
    }

    private function _sendPushForAlertId($alertId) {

        $LocalistAlerts = TableRegistry::getTableLocator()->get('LocalistAlerts');
        $Users = TableRegistry::getTableLocator()->get('Users');
        $payloads = $LocalistAlerts->generatePushPayloadsForAlertId($alertId);
        if (! $payloads) {
            return;
        }
        Log::debug("Got payloads for alert: " . json_encode($payloads));
        foreach($payloads as $userId => $payload) {
            $user = $Users->get($userId);
            if (! $user) {
                Log::error("Couldn't get user for alert: " . $alertId);
                continue;
            }
            if (! $user['chat_registered']) {
                Log::error("Can't send to $userId, not chat registered");
                continue;
            }
            // Send silent notification first
            $sent = QuickBlox::sendPush($user, [
                'aps' => [
                    'content-available' => 1
                ]
            ]);
            if ($sent) {
                $sent = QuickBlox::sendPush($user, $payload);
                if (! $sent) {
                    $user->set([
                        'chat_registered' => false,
                        'apns' => null,
                        'udid' => null
                    ]);
                    $Users->save($user);
                }
            }
        }
    }

    // Dev methods
    public function sendDataUpdate($userId) {
        $LocalistAlert = TableRegistry::getTableLocator()->get('LocalistAlerts');
        $Users = TableRegistry::getTableLocator()->get('Users');
        $user = $Users->get($userId);
        if (! $user) {
            $this->set('message', 'No user! FAIL');
            return;
        }
        $payload = $LocalistAlert->getSamplePayload('data');
        if (! $payload) {
            $this->set('message', 'No payload!');
            return;
        }
        $sent = QuickBlox::sendPush($user, $payload);
        $this->set('message', $sent ? 'success' : 'fail');
    }

    public function sendActivityNotification($userId) {
        $LocalistAlert = TableRegistry::getTableLocator()->get('LocalistAlerts');
        $Users = TableRegistry::getTableLocator()->get('Users');
        $user = $Users->get($userId);
        if (! $user) {
            $this->set('message', 'No user! FAIL');
            return;
        }
        $payload = $LocalistAlert->getSamplePayload('activity');
        if (! $payload) {
            $this->set('message', 'No payload!');
            return;
        }
        $sent = QuickBlox::sendPush($user, $payload);
        $this->set('message', $sent ? 'success' : 'fail');
    }

    public function sendListAlertNotification($userId) {
        $LocalistAlert = TableRegistry::getTableLocator()->get('LocalistAlerts');
        $Users = TableRegistry::getTableLocator()->get('Users');
        $user = $Users->get($userId, [
            'contain' => ['UserLocalists']
        ]);
        if (! $user) {
            $this->set('message', 'No user! FAIL');
            return;
        }
        if (empty($user->user_localists)) {
            $this->set('message', 'No lists for user! FAIL');
            return;
        }
        $payload = $LocalistAlert->getSamplePayload('list',
            $user->user_localists[1]->localist_id);
        if (! $payload) {
            $this->set('message', 'No payload!');
            return;
        }
        $sent = QuickBlox::sendPush($user, $payload);
        $this->set('message', $sent ? 'success' : 'fail');
    }

    public function logFromDevice() {
        $data = $this->request->getData();
        try {
            Log::info("Log line from device:" .
                PHP_EOL .
                json_encode($data, JSON_PRETTY_PRINT |
                    JSON_UNESCAPED_SLASHES));
        } catch (\Exception $e) {
            Log::error("Error logging line from device: " . $e->getMessage());
        }
        $this->set('message', 'Does not matter');
    }

    public function recordLocalistAlert() {
        $UserLocalistAlerts = TableRegistry::getTableLocator()
            ->get('UserLocalistAlerts');
        $data = $this->request->getData();
        try {
            $UserLocalistAlerts->recordUserLocalistAlert($data['localistId'],
                $data['userId'], $data['action']);
        } catch (\Exception $e) {
            Log::error("Error recording localist alert from device: "
                . $e->getMessage());
        }
        $this->set('message', 'Does not matter');
    }

    private static function _elapsedMS($microts) {
        if ($microts === null) {
            return 0;
        }
        list($start_usec, $start_sec) = explode(' ', $microts);
        list($now_usec, $now_sec) = explode(' ', microtime());
        $delta = ($now_sec - $start_sec) * 1000;
        $delta += (int) (($now_usec * 1000) - ($start_usec * 1000));
        return $delta;
    }
}
