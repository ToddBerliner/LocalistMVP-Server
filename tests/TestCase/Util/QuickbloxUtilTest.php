<?php
namespace App\Test\TestCase\Util;

use App\Model\Table\LocalistAlertsTable;
use App\Test\TestCase\CommonTrait;
use App\Util\QuickBlox;
use Cake\Log\Log;
use Cake\Http\Client;
use Cake\TestSuite\TestCase;
use Cake\ORM\TableRegistry;

class QuickbloxUtilTest extends TestCase {
    use CommonTrait;

    const UDID = '42897E47-69FC-4F02-A5FC-9DD883565B3D';
    const APNS = 'af9c0dc20f3a50164adfb248e39e559499eff94a9d97f075b4ffb84e4caa3388';

    public $fixtures = [
        'app.Users'
    ];

    private function _createTestUser($chatUser = null, $chatLogin = null, $chatPassword = null) {
        $Users = TableRegistry::getTableLocator()->get('Users');
        $newUser = $Users->newEntity([
            'udid' => self::UDID,
            'apns' => self::APNS,
            'image_name' => 'Contact',
            'phone' => '4088925630',
            'name' => 'Todd Berliner',
            'first_name' => 'Todd'
        ]);
        if (isset($chatUser)) {
            $newUser->set([
                'chat_user' => $chatUser,
                'chat_login' => $chatLogin,
                'chat_password' => $chatPassword
            ]);
        }
        return $Users->save($newUser);
    }

    public function testAll() {

        $user = $this->_createTestUser();

        // Create the user and ensure we got the chat_user properties set
        $user = QuickBlox::createUser($user);
        $this->assertNotNull($user->chat_user);
        $this->assertNotNull($user->chat_login);
        $this->assertNotNull($user->chat_password);

        $subscribed = QuickBlox::setAPNSToken($user);
        $this->assertTrue($subscribed);

        $message = [
            'aps' => [
                'badge' => 123,
                'category' => LocalistAlertsTable::ACTIVITY_CATEGORY,
                'alert' => [
                    'title' => 'A list has been deleted! ' . json_decode('"\ud83e\udd2f"'),
                    'body' => 'The "Testing" list was deleted by another member.'
                ]
            ]
        ];
        $pushed = QuickBlox::sendPush($user, $message);
        $this->assertTrue($pushed);

        return $user;
    }

}