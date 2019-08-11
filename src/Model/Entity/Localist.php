<?php

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Localist extends Entity {

    protected $_hidden = [
        'created',
        'modified'
    ];

    public function jsonSerialize() {

        $values = json_decode($this->get('json'), true);
        $values['updated'] = doubleval($values['updated']);
        $values['id'] = $this->get('id');

        // Replace the members with the actual Users from the UserLocalists table
        $values['members'] = [];
        if (! empty($this->user_localists)) {
            foreach($this->user_localists as $user_localist) {
                $values['members'][] = $user_localist->user;
            }
        }

        return $values;
    }
}