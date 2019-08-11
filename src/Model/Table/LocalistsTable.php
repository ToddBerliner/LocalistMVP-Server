<?php
namespace App\Model\Table;
use Cake\ORM\Table;

class LocalistsTable extends Table {
    public function initialize(array $config) {
        parent::initialize($config);
        $this->addBehavior('Timestamp');
        $this->hasMany('UserLocalists');
        $this->hasMany('LocalistAlerts');
        $this->hasMany('UserAlerts');
    }
}