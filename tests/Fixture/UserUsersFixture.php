<?php
namespace App\Test\Fixture;
use Cake\TestSuite\Fixture\TestFixture;

class UserUsersFixture extends TestFixture {
    public $import = ['model' => 'UserUsers'];

    public $records = [
        [
            'user_id' => UsersFixture::HANK,
            'other_user_id' => UsersFixture::DANIEL
        ]
    ];
}