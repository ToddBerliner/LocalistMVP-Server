<?php
namespace App\Model\Table;
use Cake\ORM\Table;
use Cake\Log\Log;

class UserSyncsTable extends Table {
    public function initialize(array $config) {
        parent::initialize($config);
        $this->addBehavior('Timestamp');
        $this->belongsTo('Users');
    }
    public function recordSync($userId) {
        try {
            $this->save($this->newEntity(['user_id' => $userId]));
        } catch (\Exception $e) {
            Log::error("Exception saving UserSync: " . $e->getMessage()
                . $e->getTraceAsString());
        }
    }
}