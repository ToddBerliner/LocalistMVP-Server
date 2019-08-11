<?php
namespace App\Test\TestCase\Controller;

use App\Model\Table\LocalistAlertsTable;
use App\Test\Fixture\LocalistsFixture;
use App\Test\Fixture\UsersFixture;
use App\Test\TestCase\Util\QuickbloxUtilTest;
use Cake\Core\Configure;
use Cake\ORM\Table;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Cake\Cache\Cache;
use Cake\Utility\Hash;
use App\Test\TestCase\CommonTrait;
use Cake\Log\Log;

class ApiControllerTest extends TestCase {

    use IntegrationTestTrait;
    use CommonTrait;

    public $fixtures = [
        'app.Users',
        'app.Localists',
        'app.UserLocalists',
        'app.LocalistAlerts',
        'app.UserLocalistAlerts',
        'app.UserUsers',
        'app.UserSyncs'
    ];

    public function setUp() {
        parent::setUp();
        Cache::clear(false);
        $this->configRequest([
            'headers' => [
                'Accept' => 'application/json',
                'ContentType' => 'application/json; charset=utf-8'
            ]
        ]);
    }

    public function tearDown() {
        parent::tearDown();
        Cache::clear(false);
    }

    public function testSyncDataNewUser() {

        // Send new user and empty data
        $udid = QuickbloxUtilTest::UDID;
        $apns = QuickbloxUtilTest::APNS;
        $jsonData = <<<JSON
{
	"People": [],
	"Lists": [],
	"User": {
		"id": "",
		"imageName": "Contact",
		"phone": "5555228243",
		"name": "Anna Haro",
		"first_name": "Anna",
		"udid": "$udid"
	}
}
JSON;
        $this->post('/api/syncdata.json', json_decode($jsonData, true));
        $this->assertResponseSuccess();

        // Assert data returned with new user id
        $data = json_decode($this->_response->getBody(), true);
        $this->assertEquals($data['data']['User']['id'], $data['data']['People'][0]['id']);
        $this->assertEmpty($data['data']['Lists']);
        $this->assertInternalType('int', $data['data']['User']['id']);
        $userId = $data['data']['User']['id'];

        // Check for chat_* fields since they are hidden fields
        // Chat user creation is simulated, this assertion is just to confirm
        // the the background process was called
        $Users = TableRegistry::getTableLocator()->get('Users');
        $user = $Users->get($userId);
        $this->assertNotNull($user->chat_user);
        $this->assertNotNull($user->chat_login);
        $this->assertNotNull($user->chat_password);
        $this->assertFalse($user->chat_registered);

        // Repost data with APNS set
        $data['data']['User']['apns'] = $apns;
        $this->post('/api/syncdata.json', $data['data']);
        $this->assertResponseSuccess();

        // Check the user again for chat_registered to simulate APNS subscription
        $user = $Users->get($userId);
        $this->assertTrue($user->chat_registered);

        // Ensure UserSyncs records created
        $UserSyncs = TableRegistry::getTableLocator()->get('UserSyncs');
        $userSyncs = $UserSyncs->find()
            ->where(['user_id' => $userId]);
        $this->assertEquals(2, $userSyncs->count());
    }

    public function testUpdatedApns() {

        $Users = TableRegistry::getTableLocator()->get('Users');
        $hank = $Users->get(UsersFixture::HANK);
        $hank->set([
            'chat_user' => '123',
            'chat_login' => '456',
            'chat_password' => '789',
            'chat_registered' => true
        ]);
        $Users->save($hank);

        // change APNS for HANK & post
        $postData = [
            'People' => [],
            'Lists' => [],
            'User' => [
                'id' => '',
                'name' => UsersFixture::HANK_NAME,
                'first_name' => UsersFixture::HANK_FNAME,
                'phone' => UsersFixture::HANK_PHONE,
                'image_name' => 'Contact',
                'apns' => 'abcd',
                'udid' => 'abcd'
            ]
        ];
        $this->post('/api/syncdata.json', $postData);
        $this->assertResponseSuccess();

        // Look for output to confirm the transient state of the $user->chat_registered flag
        // because the posting of the updated data would result in the updated subscription
        // and therefore the $user->chat_registered would be back to true by this point

        $hank = $Users->get(UsersFixture::HANK);
        $this->assertEquals('abcd', $hank->apns);
        $this->assertEquals('abcd', $hank->udid);
        $this->assertTrue($hank->chat_registered);
    }

    public function testSyncDataLoggedOutUser() {

        // Create list for existing user
        $list = $this->_createListForUserIds([UsersFixture::HANK]);

        // Post data with matching phone number
        $postData = [
            'People' => [],
            'Lists' => [],
            'User' => [
                'id' => '',
                'name' => UsersFixture::HANK_NAME,
                'first_name' => UsersFixture::HANK_FNAME,
                'phone' => UsersFixture::HANK_PHONE,
                'image_name' => 'Contact'
            ]
        ];
        $this->post('/api/syncdata.json', $postData);
        $this->assertResponseSuccess();

        // Assert no new record created
        $Users = TableRegistry::getTableLocator()->get('Users');
        $users = $Users->find()->all();
        $this->assertEquals(3, $users->count());

        // Assert user comes back w/ existing user id
        $response = json_decode($this->_response->getBody(), true);
        $data = $response['data'];

        $this->assertEquals([
            'People' => [
                [
                    'id' => UsersFixture::HANK,
                    'udid' => 'abc',
                    'apns' => 'abc',
                    'name' => UsersFixture::HANK_NAME,
                    'first_name' => UsersFixture::HANK_FNAME,
                    'image_name' => 'Contact',
                    'phone' => UsersFixture::HANK_PHONE
                ],
                [
                    'id' => UsersFixture::DANIEL,
                    'udid' => 'abc',
                    'apns' => 'abc',
                    'name' => UsersFixture::DANIEL_NAME,
                    'first_name' => UsersFixture::DANIEL_FNAME,
                    'image_name' => 'Contact',
                    'phone' => UsersFixture::DANIEL_PHONE
                ]
            ],
            'Lists' => [
                [
                    'foo' => 'bear',
                    'updated' => floatval(123.456),
                    'id' => 1,
                    'members' => [
                        [
                            'id' => UsersFixture::HANK,
                            'udid' => 'abc',
                            'apns' => 'abc',
                            'name' => UsersFixture::HANK_NAME,
                            'first_name' => UsersFixture::HANK_FNAME,
                            'image_name' => 'Contact',
                            'phone' => UsersFixture::HANK_PHONE
                        ]
                    ]
                ]
            ],
            'User' => [
                'id' => UsersFixture::HANK,
                'udid' => 'abc',
                'apns' => 'abc',
                'name' => UsersFixture::HANK_NAME,
                'first_name' => UsersFixture::HANK_FNAME,
                'image_name' => 'Contact',
                'phone' => UsersFixture::HANK_PHONE
            ]
        ], $data);

    }

    public function testGetListsForUser() {
        $user = $this->_createUser();
        $this->_createListForUserIds([$user->id]);
        $this->_createListForUserIds([$user->id]);
        $postData = [
            'People' => [],
            'Lists' => [],
            'User' => [
                'id' => $user->id,
                'name' => 'Does not matter', // will return stored name
                'first_name' => 'Does',
                'phone' => $user->phone,
                'image_name' => 'Contact'
            ]
        ];
        $this->post('/api/syncdata.json', $postData);
        $this->assertResponseSuccess();
        $data = json_decode($this->_response->getBody(), true);
        $data = $data['data'];
        $this->assertEquals([
            'id' => $user->id,
            'udid' => $user->udid,
            'apns' => $user->apns,
            'name' => 'Does not matter',
            'first_name' => 'Does',
            'phone' => $user->phone,
            'image_name' => 'Contact'
        ], $data['User']);
        $this->assertEquals(2, count($data['Lists']));
    }

    public function testSyncDataCreateList() {

        $user = $this->_createUser();
        $data = $this->_getFullDataWithList($user);

        $expectedList = json_decode($this->_getListJson($user), true);

        // Send up new list w/o list id
        $this->post('/api/syncData.json', $data);

        // Assert list comes back w/ new list id
        $response = json_decode($this->_response->getBody(), true);
        $data = $response['data'];
        $list = $data['Lists'][0];
        $this->assertNotEmpty($list['id']);

        // Assert People (UserUsers) were recorded and returned (user + 3 other list members)
        $this->assertEquals(4, count($data['People']));

        // Assert list is decoded correctly
        $expectedList['id'] = $list['id'];
        $expectedList['updated'] = $list['updated']; // hack due to datetime conversion not matching exactly
        $this->assertEquals($expectedList, $list);

        // Assert other member gets new list
        $postData = [
            'People' => [],
            'Lists' => [],
            'User' => [
                'id' => UsersFixture::HANK,
                'udid' => 'abc',
                'apns' => 'abc',
                'name' => 'Does not matter',
                'phone' => UsersFixture::HANK_PHONE,
                'image_name' => 'Contact'
            ]
        ];
        $this->post('/api/syncdata.json', $postData);
        $this->assertResponseSuccess();
        $otherResponse = json_decode($this->_response->getBody(), true);
        $otherData = $otherResponse['data'];

        $otherMemberList = $otherData['Lists'][0];
        $this->assertEquals($list['id'], $otherMemberList['id']);

        // Assert People are returned
        $this->assertEquals(2, count($otherData['People']));

        // Assert new member alerts created
        $LocalistAlerts = TableRegistry::getTableLocator()->get('LocalistAlerts');
        $alerts = $LocalistAlerts->find()
            ->where([
                'localist_id' => $list['id'],
                'action' => LocalistAlertsTable::MEMBER_ADDED
            ]);
        $this->assertEquals(3, $alerts->count());
        $alertedIds = $alerts->extract('meta')->toArray();
        $this->assertEmpty(array_diff([
            UsersFixture::HANK,
            UsersFixture::DANIEL,
            UsersFixture::JOHN
        ], $alertedIds));
    }

    public function testSyncDataUnchangedList() {
        // Setup
        $user = $this->_createUser();
        $data = $this->_getFullDataWithList($user);

        $this->post('/api/syncdata.json', $data);
        $this->assertResponseSuccess();
        $response = json_decode($this->_response->getBody(), true);
        $data = $response['data'];
        $origList = $data['Lists'][0];

        $this->post('/api/syncdata.json', $data);
        $response = json_decode($this->_response->getBody(), true);
        $data = $response['data'];
        $updatedList = $data['Lists'][0];

        $this->assertEquals($origList, $updatedList);
    }

    public function testSyncDataFromOtherMember() {
        // Setup
        $user = $this->_createUser();
        $data = $this->_getFullDataWithList($user);

        $this->post('/api/syncdata.json', $data);
        $this->assertResponseSuccess();
        $response = json_decode($this->_response->getBody(), true);
        $data = $response['data'];
        $listId = $data['Lists'][0]['id'];

        $Localists = TableRegistry::getTableLocator()->get('Localists');
        $UserLocalists = TableRegistry::getTableLocator()->get('UserLocalists');
        $this->assertEquals(1, $Localists->find()->all()->count());
        $this->assertEquals(4, $UserLocalists->find()->all()->count());

        // Change the user and reorder the members
        $origUser = $data['User'];
        $data['User'] = $data['Lists'][0]['members'][2];
        shuffle($data['Lists'][0]['members']);
        $this->post('/api/syncdata.json', $data);
        $this->assertResponseSuccess();
        // Assert no changed to records
        $this->assertEquals(1, $Localists->find()->all()->count());
        $this->assertEquals(4, $UserLocalists->find()->all()->count());
    }

    public function testSyncDataUpdateListUpdatedItems() {

        // Setup
        $user = $this->_createUser();
        $data = $this->_getFullDataWithList($user);

        $this->post('/api/syncdata.json', $data);
        $this->assertResponseSuccess();
        $response = json_decode($this->_response->getBody(), true);
        $data = $response['data'];
        $origList = $data['Lists'][0];

        // send up list with new items
        sleep(1);
        $data['Lists'][0]['updated'] = microtime(true);
        $data['Lists'][0]['items'] = [];

        $this->post('/api/syncdata.json', $data);
        $response = json_decode($this->_response->getBody(), true);
        $data = $response['data'];
        $updatedList = $data['Lists'][0];

        $this->assertEquals($origList['id'], $updatedList['id']);
        $this->assertEmpty($updatedList['items']);

        // assert new user_alerts records (ITEMS_UPDATED)
        $LocalistAlerts = TableRegistry::getTableLocator()->get('LocalistAlerts');
        $alerts = $LocalistAlerts->find()
            ->where([
                'localist_id' => $updatedList['id'],
                'action' => LocalistAlertsTable::ITEMS_UPDATED,
                'meta' => $user->id
            ]);
        $this->assertEquals(1, $alerts->count());
    }

    public function testSyncDataUpdateListUpdatedRetailers() {

        // Setup
        $user = $this->_createUser();
        $data = $this->_getFullDataWithList($user);

        $this->post('/api/syncdata.json', $data);
        $this->assertResponseSuccess();
        $response = json_decode($this->_response->getBody(), true);
        $data = $response['data'];
        $origList = $data['Lists'][0];

        // send up list with new locations
        sleep(1);
        $data['Lists'][0]['updated'] = microtime(true);
        $data['Lists'][0]['retailers'][0]['locations'][] = [
            'radius' => 100,
            'address' => 'New Street',
            'longitude' => -122.27419227361679,
            'identifier' => 'Cooku-New Street',
            'latitude' => 42,
            'imageName' => '',
            'name' => 'Cooku'
        ];

        $this->post('/api/syncdata.json', $data);
        $response = json_decode($this->_response->getBody(), true);
        $data = $response['data'];
        $updatedList = $data['Lists'][0];

        $this->assertEquals($origList['id'], $updatedList['id']);
        $this->assertEquals(4,
            count($updatedList['retailers'][0]['locations']));

        // Assert new user_alerts records (RETAILERS_UPDATED)
        $LocalistAlerts = TableRegistry::getTableLocator()->get('LocalistAlerts');
        $alerts = $LocalistAlerts->find()
            ->where([
                'localist_id' => $updatedList['id'],
                'action' => LocalistAlertsTable::RETAILERS_UPDATED
            ]);
        $this->assertEquals(1, $alerts->count());
        $alert = $alerts->first();
        $this->assertEquals($user->id, $alert->meta);
    }

    public function testSyncDataUpdateListUpdatedMembers() {

        // Setup
        $user = $this->_createUser();
        $data = $this->_getFullDataWithList($user);
        $user2 = $this->_createUser();

        // Sync the list so it's stored
        $this->post('/api/syncdata.json', $data);
        $this->assertResponseSuccess();
        $response = json_decode($this->_response->getBody(), true);
        $data = $response['data'];
        $origList = $data['Lists'][0];

        // send up list with updated members
        // first, remove 3 members
        sleep(1);
        $data['Lists'][0]['updated'] = microtime(true);
        $data['Lists'][0]['members'] =
            array_slice($data['Lists'][0]['members'], -1, 1);
        // second, add a new member
        $data['Lists'][0]['members'][] = [
            'id' => $user2->id,
            'phone' => $user2->phone,
            'image_name' => 'Contact'
        ];

        $this->post('/api/syncdata.json', $data);
        $response = json_decode($this->_response->getBody(), true);
        $data = $response['data'];
        $updatedList = $data['Lists'][0];

        // Assert that the list JSON is correct
        $this->assertEquals($origList['id'], $updatedList['id']);
        $this->assertEquals(2, count($updatedList['members']));
        $listJsonMemberIds = Hash::extract($updatedList['members'], '{n}.id');
        $this->assertEquals([$user->id, $user2->id], $listJsonMemberIds);

        // Assert UserLocalists records updated
        $UserLocalists = TableRegistry::getTableLocator()->get('UserLocalists');
        $userLocalists = $UserLocalists->find()
            ->where([
                'localist_id' => $updatedList['id']
            ]);
        $this->assertEquals(2, $userLocalists->count());
        $storedMemberIds = $userLocalists->all()->extract('user_id')->toArray();
        $this->assertEquals($listJsonMemberIds, $storedMemberIds);

        // Assert new user_alerts records (MEMBER_REMOVED + MEMBER ADDED)
        $LocalistAlerts = TableRegistry::getTableLocator()->get('LocalistAlerts');
        $alerts = $LocalistAlerts->find()
            ->where([
                'localist_id' => $updatedList['id'],
                'action' => LocalistAlertsTable::MEMBER_REMOVED
            ]);
        $this->assertEquals(3, $alerts->count());
        $alerts = $LocalistAlerts->find()
            ->where([
                'localist_id' => $updatedList['id'],
                'action' => LocalistAlertsTable::MEMBER_ADDED
            ]);
        $this->assertEquals(4, $alerts->count());
    }

    public function testDeleteList() {
        // http://localhost:8808/api/deletelist.json?user_id=20=&id=6

        // Setup
        $user = $this->_createUser();
        $data = $this->_getFullDataWithList($user);
        $this->post('/api/syncdata.json', $data);
        $response = json_decode($this->_response->getBody(), true);
        $data = $response['data'];
        $list = $data['Lists'][0];

        // Delete list
        $this->get("/api/deletelist.json?user_id={$user->id}&id={$list['id']}");
        $response = json_decode($this->_response->getBody(), true);
        $data = $response['data'];
        $this->assertEmpty($data['Lists']);

        $UserLocalists = TableRegistry::getTableLocator()->get('UserLocalists');
        $LocalistAlerts = TableRegistry::getTableLocator()->get('LocalistAlerts');

        $this->assertTrue($UserLocalists->find()->all()->isEmpty());
        $alerts = $LocalistAlerts->find()
            ->where([
                'localist_id' => $list['id'],
                'action' => LocalistAlertsTable::LIST_DELETED
            ]);
        $this->assertEquals(1, $alerts->count());
        $deletedAlert = $alerts->first();
        $expectedMeta = [
            UsersFixture::HANK,
            UsersFixture::JOHN,
            UsersFixture::DANIEL
        ];
        $actualMeta = explode(',', $deletedAlert['meta']);
        $this->assertEmpty(array_diff($expectedMeta, $actualMeta));
        $this->assertEmpty(array_diff($actualMeta, $expectedMeta));
    }

    public function testLogFromDevice() {
        $data = [
            'user_id' => UsersFixture::HANK,
            'message' => 'This is a test message',
            'error' => 'Unbalanced calls to begin/end appearance transitions for <UINavigationController: 0x10181c800>.'
        ];

        $this->post('/api/logfromdevice.json', $data);
        $this->assertResponseSuccess();
        // Find log and look for line

    }

    public function testRecordLocalistAlert() {
        $data = [
            "localistId" => 123,
            "userId" => UsersFixture::JOHN,
            "action" => "presented"
        ];
        $this->post('/api/recordlocalistalert.json', $data);
        $this->assertResponseSuccess();

        $data = [
            "localistId" => 456,
            "userId" => UsersFixture::HANK,
            "action" => "viewed"
        ];
        $this->post('/api/recordlocalistalert.json', $data);
        $this->assertResponseSuccess();

        // Get and check the UserLocalistAlerts records
        $UserLocalistAlerts = TableRegistry::getTableLocator()
            ->get('UserLocalistAlerts');

        $johnAlert = $UserLocalistAlerts->find()
            ->where([
                'user_id' => UsersFixture::JOHN
            ])
            ->all();
        $this->assertEquals(1, $johnAlert->count());
        $johnAlert = $johnAlert->first();
        $this->assertEquals(123, $johnAlert->localist_id);
        $this->assertEquals('presented', $johnAlert->action);

        $hankAlert = $UserLocalistAlerts->find()
            ->where([
                'user_id' => UsersFixture::HANK
            ])
            ->all();
        $this->assertEquals(1, $hankAlert->count());
        $hankAlert = $hankAlert->first();
        $this->assertEquals(456, $hankAlert->localist_id);
        $this->assertEquals('viewed', $hankAlert->action);
    }
}