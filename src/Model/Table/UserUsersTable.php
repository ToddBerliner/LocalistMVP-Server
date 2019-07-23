<?php
namespace App\Model\Table;
use Cake\ORM\Table;

class UserUsersTable extends Table {
    public function initialize(array $config) {
        parent::initialize($config);
        $this->addBehavior('Timestamp');
        $this->belongsTo('Users');
        $this->belongsTo('Users')
            ->setForeignKey('other_user_id');
    }
}