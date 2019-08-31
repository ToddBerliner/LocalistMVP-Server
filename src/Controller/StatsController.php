<?php
namespace App\Controller;
use App\Controller\AppController;
use App\Model\Table\LocalistAlertsTable;
use Cake\Collection\Collection;
use Cake\Datasource\ConnectionManager;
use Cake\Log\Log;
use Cake\ORM\ResultSet;
use Cake\ORM\TableRegistry;

class StatsController extends AppController {
    public function getStats() {
        /*
         *
>>> Only current metric - Calculate from table Localists
- [ ] Distribution of list item count

>>> Need cron to set up job to record stats
- [ ] Num users [[day: number of users w/ lists]]
- [ ] Num lists [[day: number of lists w/ users]]

>>> Calculate from data tables - UserSyncs and LocalistAlerts
- [ ] Daily num of active users [[day: count of unique user_ids from syncs]]
- [ ] Daily num of user syncs [[day: count of syncs]]
- [ ] Daily activities bar charts [[day: number of activity alerts]]
- [ ] Daily activities item changes [[day: number of data alerts]]
         */
        $data = [
            'active_lists' => $this->_getActiveLists(),
            'active_users' => $this->_getActiveUsers(),
            'active_users_by_day' => $this->_getActiveUsersPerDay(),
            'user_syncs_by_day' => $this->_getUserSyncsPerDay(),
            'activities_by_day' => $this->_getActiviesPerDay(),
            'items_by_day' => $this->_getItemUpdatesPerDay(),
            'list_item_counts' => $this->_getItemCountsPerList(),
            'user_alerts_by_day' => $this->_getUserAlertsPerDay()
        ];
        $this->set('data', $data);
    }

    private function _getActiveUsers() {
        $Users = TableRegistry::getTableLocator()->get('Users');
        $activeUsers = $Users->find()
            ->distinct('Users.id')
            ->innerJoinWith('UserSyncs', function($q) {
                return $q->where([
                    'DATE(UserSyncs.created)' => date('Y-m-d')
                ]);
            })
            ->all();
        return $activeUsers;
    }

    private function _getActiveLists() {
        $Localists = TableRegistry::getTableLocator()->get('Localists');
        $localists = $Localists->find()
            ->distinct('Localists.id')
            ->contain(['UserLocalists' => 'Users'])
            ->innerJoinWith('LocalistAlerts', function ($q) {
                return $q->where([
                    'DATE(LocalistAlerts.created)' => date('Y-m-d')
                ]);
            }) // ensure list has activity
            ->all();
        return $localists;
    }

    private function _getActiveUsersPerDay() {
        $UserSyncs = TableRegistry::getTableLocator()->get('UserSyncs');
        $activeUsersPerDay = $UserSyncs->find()
            ->select([
                'thedate' => 'DATE(created)',
                'count' => 'COUNT(DISTINCT(user_id))'
            ])
            ->group(['DATE(created)']);
        return self::_countsByDate($activeUsersPerDay->all());
    }

    private function _getUserSyncsPerDay() {
        $UserSyncs = TableRegistry::getTableLocator()->get('UserSyncs');
        $userSyncsPerDay = $UserSyncs->find()
            ->select([
                'thedate' => 'DATE(created)',
                'count' => 'COUNT(*)'
            ])
            ->group(['DATE(created)']);
        return self::_countsByDate($userSyncsPerDay->all());
    }

    private function _getActiviesPerDay() {
        $LocalistAlerts = TableRegistry::getTableLocator()->get('LocalistAlerts');
        $activitesPerDay = $LocalistAlerts->find()
            ->select([
                'thedate' => 'DATE(created)',
                'count' => 'COUNT(*)'
            ])
            ->where(['LocalistAlerts.action !=' => LocalistAlertsTable::ITEMS_UPDATED])
            ->group(['DATE(created)']);
        return self::_countsByDate($activitesPerDay->all());
    }

    private function _getItemUpdatesPerDay() {
        $LocalistAlerts = TableRegistry::getTableLocator()->get('LocalistAlerts');
        $itemUpdatesPerDay = $LocalistAlerts->find()
            ->select([
                'thedate' => 'DATE(created)',
                'count' => 'COUNT(*)'
            ])
            ->where(['LocalistAlerts.action' => LocalistAlertsTable::ITEMS_UPDATED])
            ->group(['DATE(created)']);
        return self::_countsByDate($itemUpdatesPerDay->all());
    }

    private function _getUserAlertsPerDay() {
        $UserLocalistAlerts = TableRegistry::getTableLocator()->get('UserLocalistAlerts');
        $alertsPerDay = $UserLocalistAlerts->find()
            ->select([
                'thedate' => 'Date(created)',
                'count' => 'COUNT(*)'
            ])
            ->group(['Date(created)']);
        return self::_countsByDate($alertsPerDay->all());
    }

    private function _getItemCountsPerList() {
        $Localists = TableRegistry::getTableLocator()->get('Localists');
        $activeLists = $Localists->find()
            ->contain(['UserLocalists'])
            ->all();
        $itemCounts = [];
        $activeLists->each(function($list) use (&$itemCounts) {
            if (!empty($list->user_localists)) {
                $listJson = json_decode($list, true);
                $itemCounts[] = count($listJson['items']);
            }
        });
        return $itemCounts;
    }

    private static function _countsByDate(ResultSet $resultsCollection) {
        $results = [];
        $resultsCollection->each(function ($result) use (&$results) {
            $results[$result['thedate']] = intval($result['count']);
        });
        return $results;
    }
}