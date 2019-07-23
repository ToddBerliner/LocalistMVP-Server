<?php
namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class UsersFixture extends TestFixture {
    public $import = ['model' => 'Users'];

    const HANK = 1;
    const HANK_PHONE = '5557664823';
    const HANK_NAME = 'Hank M. Zakroff';
    const HANK_FNAME = 'Hank';
    const JOHN = 2;
    const JOHN_PHONE = '8885551212';
    const JOHN_NAME = 'John Appleseed';
    const JOHN_FNAME = 'John';
    const DANIEL = 3;
    const DANIEL_PHONE = '5554787672';
    const DANIEL_NAME = 'Daniel Higgins Jr.';
    const DANIEL_FNAME = 'Daniel';

    public $records = [
        [
            'id' => self::HANK,
            'udid' => 'abc',
            'apns' => 'abc',
            'image_name' => 'Contact',
            'phone' => self::HANK_PHONE,
            'name' => self::HANK_NAME,
            'first_name' => self::HANK_FNAME
        ],
        [
            'id' => self::JOHN,
            'udid' => 'abc',
            'apns' => 'abc',
            'image_name' => 'Contact',
            'phone' => self::JOHN_PHONE,
            'name' => self::JOHN_NAME,
            'first_name' => self::JOHN_FNAME
        ],
        [
            'id' => self::DANIEL,
            'udid' => 'abc',
            'apns' => 'abc',
            'image_name' => 'Contact',
            'phone' => self::DANIEL_PHONE,
            'name' => self::DANIEL_NAME,
            'first_name' => self::DANIEL_FNAME
        ],
    ];
}