<?php
namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Log\Log;

class UserLocalistAlertsTable extends Table {
    public function initialize(array $config) {
        parent::initialize($config);
        $this->addBehavior('Timestamp');
    }

    public function recordUserLocalistAlert(int $localistId,
                                            int $userId,
                                            string $action) {
        try {
            $this->save($this->newEntity([
                'user_id' => $userId,
                'localist_id' => $localistId,
                'action' => $action
            ]));
        } catch (\Exception $e) {
            Log::error("Exception recording UserLocalistAlert: "
                . $e->getMessage() . "\n"
                . $e->getTraceAsString());
        }
    }
}