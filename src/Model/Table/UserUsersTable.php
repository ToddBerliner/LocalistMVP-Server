<?php
namespace App\Model\Table;
use Cake\ORM\Table;
use Cake\Log\Log;

class UserUsersTable extends Table {
    public function initialize(array $config) {
        parent::initialize($config);
        $this->addBehavior('Timestamp');
        $this->belongsTo('Users');
        $this->belongsTo('Users')
            ->setForeignKey('other_user_id');
    }

    public function recordSync($userId) {
        try {
            $this->save($this->newEntity([
                'user_id' => $userId
            ]));
        } catch (\Exception $e) {
            Log::error("Exception recording UserSync: "
                . $e->getMessage() . "\n"
                . $e->getTraceAsString());
        }
    }
}