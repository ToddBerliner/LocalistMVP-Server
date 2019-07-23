<?php

namespace App\Model\Entity;

use Cake\ORM\Entity;

class User extends Entity {
    protected $_hidden = [
        'created',
        'modified',
        'chat_user',
        'chat_login',
        'chat_password',
        'chat_registered'
    ];
}