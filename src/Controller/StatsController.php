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
            'active_users' => $this->_getActiveUsers(),
            'active_users_by_day' => $this->_getActiveUsersPerDay(),
            'user_syncs_by_day' => $this->_getUserSyncsPerDay(),
            'activities_by_day' => $this->_getActiviesPerDay(),
            'items_by_day' => $this->_getItemUpdatesPerDay()
        ];
        $this->set('data', $data);
    }

    private function _getActiveUsers() {
        $UserSyncs = TableRegistry::getTableLocator()->get('UserSyncs');
        $userSyncs = $UserSyncs->find()
            ->select(['UserSyncs.user_id'])
            ->distinct(['UserSyncs.user_id'])
            ->where(['DATE(created)' => date('Y-m-d')]);
        $activeUsers = [];
        if (!$userSyncs->isEmpty()) {
            $activeUserIds = $userSyncs->extract('user_id')->toArray();
            $Users = TableRegistry::getTableLocator()->get('Users');
            $activeUsers = $Users->find()
                ->where(['id IN' => $activeUserIds])
                ->all();
        }
        return $activeUsers;
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

    private static function _countsByDate(ResultSet $resultsCollection) {
        $results = [];
        $resultsCollection->each(function ($result) use (&$results) {
            $results[$result['thedate']] = $result['count'];
        });
        return $results;
    }
}