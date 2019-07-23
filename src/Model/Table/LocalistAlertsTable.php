<?php
namespace App\Model\Table;
use Cake\ORM\Table;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;

class LocalistAlertsTable extends Table {

    const MEMBER_ADDED = 100;
    const MEMBER_REMOVED = 101;
    const RETAILERS_UPDATED = 200;
    const ITEMS_UPDATED = 300;
    const TITLE_UPDATED = 400;
    const LIST_DELETED = 500;

    const ACTIVITY_CATEGORY = 'ACTIVITY_CATEGORY';
    const DATA_UPDATE_CATEGORY = 'DATA_UPDATE_CATEGORY';
    const LIST_CATEGORY = 'LIST_CATEGORY';

    public function initialize(array $config) {
        parent::initialize($config);
        $this->addBehavior('Timestamp');
        $this->belongsTo('Users');
        $this->belongsTo('Localists');
    }

    public function generatePushPayloadsForAlertId($alertId) {
        // get alert
        $alert = $this->get($alertId);
        $Localists = TableRegistry::getTableLocator()->get('Localists');
        try {
            // get list
            $list = $Localists->get($alert->localist_id);
            $list = json_decode($list->json, true);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), $e->getTraceAsString());
            return false;
        }

        switch($alert->action) {
            case LocalistAlertsTable::MEMBER_ADDED:
                return $this->_getMemberAddedPayload($alert, $list);
            case LocalistAlertsTable::MEMBER_REMOVED:
                return $this->_getMemberRemovedPayload($alert, $list);
            case LocalistAlertsTable::LIST_DELETED:
                return $this->_getListDeletedPayloads($alert, $list);
            case LocalistAlertsTable::RETAILERS_UPDATED:
                return $this->_getListLocationsUpdatedPayloads($alert, $list);
            case LocalistAlertsTable::ITEMS_UPDATED:
                return $this->_getListItemsUpdatedPayloads($alert, $list);
            default:
                return null;
        }
    }

    public function getSamplePayload($type, $listId = null) {
        switch ($type) {
            case 'data':
                return [
                    'aps' => [
                        'badge' => 123,
                        'category' => self::DATA_UPDATE_CATEGORY
                    ]
                ];
            case 'activity':
                return [
                    'aps' => [
                        'badge' => 456,
                        'category' => self::ACTIVITY_CATEGORY,
                        'alert' => [
                            'title' => 'Locations updated in a list! ' . json_decode('"\ud83d\uddfa"'),
                            'body' => 'The "Demo" list has updated locations.'
                        ]
                    ]
                ];
            case 'list':
                return [
                    'list_id' => $listId,
                    'aps' => [
                        'badge' => 789,
                        'category' => self::LIST_CATEGORY,
                        'alert' => [
                            'title' => 'List Alert Notification',
                            'body' => 'The "Demo" list has updated locations.'
                        ]
                    ]
                ];
            default:
                return null;
        }
    }

    /*
     * Meta
     * MemberAdded = userId (list created or list updated)
     * MemberRemoved = userId
     * ListDeleted = userIds, comma separated
     */
    private function _getListLocationsUpdatedPayloads($alert, $list) {
        $users = $this->_getUsersForAlert($alert);
        if ($users === false) {
            return false;
        }
        $listName = $list['title'];
        $payloads = [];
        foreach($users as $user) {
            $badgeCount = $this->_getBadgeCountForUserId($user->id);
            if ($badgeCount === false) {
                continue;
            }
            $payload = [
                'aps' => [
                    'badge' => $badgeCount,
                    'category' => self::ACTIVITY_CATEGORY,
                    'alert' => [
                        'title' => 'Locations updated in a list! ' . json_decode('"\ud83d\uddfa"'),
                        'body' => 'The "' . $listName . '" list has updated locations.'
                    ]
                ]
            ];
            $payloads[$user->id] = $payload;
        }
        return empty($payloads)
            ? false
            : $payloads;
    }

    private function _getListDeletedPayloads($alert, $list) {
        $users = $this->_getUsersForAlert($alert);
        if ($users === false) {
            return false;
        }
        $listName = $list['title'];
        $payloads = [];
        foreach($users as $user) {
            $badgeCount = $this->_getBadgeCountForUserId($user->id);
            if ($badgeCount === false) {
                continue;
            }
            $payload = [
                'aps' => [
                    'badge' => $badgeCount,
                    'category' => self::ACTIVITY_CATEGORY,
                    'alert' => [
                        'title' => 'A list has been deleted! ' . json_decode('"\ud83e\udd2f"'),
                        'body' => 'The "' . $listName . '" list was deleted by another member.'
                    ]
                ]
            ];
            $payloads[$user->id] = $payload;
        }
        return empty($payloads)
            ? false
            : $payloads;
    }

    private function _getMemberAddedPayload($alert, $list) {
        $user = $this->_getUsersForAlert($alert);
        if ($user === false) {
            return false;
        }
        $badgeCount = $this->_getBadgeCountForUserId($user->id);
        if ($badgeCount === false) {
            return false;
        }
        $listName = $list['title'];
        $payload = [
            'aps' => [
                'badge' => $badgeCount,
                'category' => self::ACTIVITY_CATEGORY,
                'alert' => [
                    'title' => 'You\'ve been added to a list! ' . json_decode('"\ud83e\udd18"'),
                    'body' => 'Welcome to the "' . $listName . '" list.'
                ]
            ]
        ];
        return [
            $user->id => $payload
        ];
    }

    private function _getMemberRemovedPayload($alert, $list) {
        $user = $this->_getUsersForAlert($alert); // this alert has a single user
        if ($user === false) {
            return false;
        }
        $badgeCount = $this->_getBadgeCountForUserId($user->id);
        if ($badgeCount === false) {
            return false;
        }
        $listName = $list['title'];
        $payload = [
            'aps' => [
                'badge' => $badgeCount,
                'category' => self::ACTIVITY_CATEGORY,
                'alert' => [
                    'title' => 'You\'ve been removed from a list. ' . json_decode('"\ud83d\ude14"'),
                    'body' => 'You\'ve been removed from the "' . $listName . '" list.'
                ]
            ]
        ];
        return [
            $user->id => $payload
        ];
    }

    private function _getListItemsUpdatedPayloads($alert, $list) {
        $users = $this->_getUsersForAlert($alert);
        if ($users === false) {
            return false;
        }
        $listName = $list['title'];
        $payloads = [];
        foreach($users as $user) {
            $badgeCount = $this->_getBadgeCountForUserId($user->id);
            if ($badgeCount === false) {
                continue;
            }
            $payload = [
                'aps' => [
                    'badge' => $badgeCount,
                    'category' => self::DATA_UPDATE_CATEGORY
                ]
            ];
            $payloads[$user->id] = $payload;
        }
        return empty($payloads)
            ? false
            : $payloads;
    }

    private function _getUsersForAlert($alert) {
        $Users = TableRegistry::getTableLocator()->get('Users');
        switch($alert->action) {
            case self::MEMBER_REMOVED:
            case self::MEMBER_ADDED:
                try {
                    return $Users->get($alert->meta);
                } catch (\Exception $e) {
                    Log::error($e->getMessage(), $e->getTraceAsString());
                    return false;
                }
            case self::LIST_DELETED:
                $userIds = explode(',', $alert->meta);
                try {
                    return $Users->find()
                        ->where([
                            'id IN' => $userIds
                        ])->all();
                } catch (\Exception $e) {
                    Log::error($e->getMessage(), $e->getTraceAsString());
                    return false;
                }
            case self::RETAILERS_UPDATED:
            case self::ITEMS_UPDATED:
                $alertingUserId = $alert->meta;
                try {
                    $UserLocalists =
                        TableRegistry::getTableLocator()->get('UserLocalists');
                    $userLocalists = $UserLocalists->find()
                        ->contain(['Users'])
                        ->where(['localist_id' => $alert->localist_id])
                        ->all();
                    $users = [];
                    $userLocalists->each(function($userLocalist) use (&$users, $alertingUserId) {
                        if ($userLocalist->user->id != $alertingUserId) {
                            $users[] = $userLocalist->user;
                        }
                    });
                    return $users;
                }  catch (\Exception $e) {
                    Log::error($e->getMessage(), $e->getTraceAsString());
                    return false;
                }
            default:
                return null;
        }
    }

    private function _getBadgeCountForUserId($userId) {
        $Users = TableRegistry::getTableLocator()->get('Users');
        try {
            $user = $Users->get($userId, [
                'contain' => ['UserLocalists' => ['Localists']]
            ]);
            $badge = 0;
            foreach($user->user_localists as $userLocalist) {
                $list = json_decode($userLocalist->localist->json, true);
                $badge += count($list['items']);
            }
            return $badge;
        } catch (\Exception $e) {
            Log::error($e->getMessage(), $e->getTraceAsString());
            return false;
        }
    }
}