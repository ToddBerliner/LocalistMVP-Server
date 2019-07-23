<?php
namespace App\Test\TestCase\Shell;
use App\Test\Fixture\UsersFixture;
use App\Test\TestCase\CommonTrait;
use Cake\Console\Shell;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Cake\TestSuite\ConsoleIntegrationTestTrait;
use App\Test\TestCase\Controller\ApiControllerTest;
use App\Model\Table\LocalistAlertsTable;

class SendPushNotificationsShellTest extends TestCase {

    use IntegrationTestTrait;
    use ConsoleIntegrationTestTrait;
    use CommonTrait;

    public $_rockEmoji;

    public $fixtures = [
        'app.Users',
        'app.Localists',
        'app.UserLocalists',
        'app.LocalistAlerts',
        'app.UserUsers'
    ];

    public function setUp() {
        parent::setUp();
        $this->_rockEmoji = json_decode('"\ud83e\udd18"');
    }

    public function testListLocationsUpdatedPayloads() {
        $LocalistAlerts = TableRegistry::getTableLocator()->get('LocalistAlerts');

        // Create a new user and list
        $user = $this->_createUser();
        $data = $this->_getFullDataWithList($user);
        $this->post('/api/syncData.json', $data);
        $response = json_decode($this->_response->getBody(), true);
        $data = $response['data'];
        $listId = $data['Lists'][0]['id'];
        // Add a retailer or location - call them all locations in alert
        $data['Lists'][0]['updated'] = ($data['Lists'][0]['updated'] + 1);
        $data['Lists'][0]['retailers'][] = ['name' => 'Foop'];
        $this->post('/api/syncData.json', $data);
        $locationsChangedAlert = $LocalistAlerts->find()
            ->where([
                'localist_id' => $listId,
                'action' => LocalistAlertsTable::RETAILERS_UPDATED
            ]);
        $this->assertEquals(1, $locationsChangedAlert->count());
        $locationsChangedAlert = $locationsChangedAlert->first();
        $expectedPayload = <<<JSON
{
    "aps": {
        "badge": 1,
        "category": "ACTIVITY_CATEGORY",
        "alert": {
            "title": "Locations updated in a list! \ud83d\uddfa",
            "body": "The \"Costco\" list has updated locations."
        }
    }
}
JSON;
        $payloads = $LocalistAlerts
            ->generatePushPayloadsForAlertId($locationsChangedAlert->id);
        $this->assertEquals(3, count($payloads));
        $userIds = array_keys($payloads);
        $this->assertEquals([
            UsersFixture::HANK,
            UsersFixture::JOHN,
            UsersFixture::DANIEL
        ], $userIds);
        $hankPayload = $payloads[UsersFixture::HANK];
        $this->assertEquals(json_decode($expectedPayload, true), $hankPayload);
    }

    public function testListDeletedPayloads() {
        $LocalistAlerts = TableRegistry::getTableLocator()->get('LocalistAlerts');
        // Create a new user and list
        $user = $this->_createUser();
        $data = $this->_getFullDataWithList($user);
        $this->post('/api/syncData.json', $data);
        $response = json_decode($this->_response->getBody(), true);
        $data = $response['data'];
        $listId = $data['Lists'][0]['id'];
        // Post deletion of list
        $this->get("/api/deletelist.json?user_id={$user->id}&id=$listId");
        // Assert deletion payloads (x3 for Hank, John, Daniel)
        $expectedPayload = <<<JSON
{
    "aps": {
        "badge": 0,
        "category": "ACTIVITY_CATEGORY",
        "alert": {
            "title": "A list has been deleted! \ud83e\udd2f",
            "body": "The \"Costco\" list was deleted by another member."
        }
    }
}
JSON;
        $deletedAlert = $LocalistAlerts->find()
            ->where([
                'localist_id' => $listId,
                'action' => LocalistAlertsTable::LIST_DELETED
            ]);
        $this->assertEquals(1, $deletedAlert->count());
        $deletedAlert = $deletedAlert->first();
        $payloads = $LocalistAlerts->generatePushPayloadsForAlertId($deletedAlert->id);
        $this->assertEquals(3, count($payloads));
        $userIds = array_keys($payloads);
        $this->assertEquals([
            UsersFixture::HANK,
            UsersFixture::JOHN,
            UsersFixture::DANIEL
        ], $userIds);
        $hankPayload = $payloads[UsersFixture::HANK];
        $this->assertEquals(json_decode($expectedPayload, true), $hankPayload);
    }

    public function testMemberRemovedPayload() {
        $LocalistAlerts = TableRegistry::getTableLocator()->get('LocalistAlerts');

        // Create a new user and list
        $user = $this->_createUser();
        $data = $this->_getFullDataWithList($user);
        $this->post('/api/syncData.json', $data);
        $response = json_decode($this->_response->getBody(), true);
        $data = $response['data'];
        $listId = $data['Lists'][0]['id'];
        // Remove a member
        $data['Lists'][0]['updated'] = ($data['Lists'][0]['updated'] + 1);
        $removedMember = array_shift($data['Lists'][0]['members']);
        // Post update
        $this->post('/api/syncData.json', $data);
        $this->assertResponseSuccess();

        $expectedHankPayload = <<<JSON
{
    "aps": {
        "badge": 0,
        "category": "ACTIVITY_CATEGORY",
        "alert": {
            "title": "You've been removed from a list. \ud83d\ude14",
            "body": "You've been removed from the \"Costco\" list."
        }
    }
}
JSON;
        $hankAlert = $LocalistAlerts->find()
            ->where([
                'localist_id' => $listId,
                'action' => LocalistAlertsTable::MEMBER_REMOVED,
                'meta' => UsersFixture::HANK
            ])
            ->first();
        $this->assertEquals([
            UsersFixture::HANK => json_decode($expectedHankPayload, true)
        ], $LocalistAlerts->generatePushPayloadsForAlertId($hankAlert->id));
    }

    public function testMemberAddedPayload() {

        // Creates a new user
        $user = $this->_createUser();
        // Gets a full list w/ 3 other members and 1 item
        $data = $this->_getFullDataWithList($user);
        $secondList = json_decode($this->_getListJson($user, 2), true);
        // Add a 2nd list w/ 2 items so 3 items total for each user
        $data['Lists'][] = $secondList;
        $this->post('/api/syncData.json', $data);
        $response = json_decode($this->_response->getBody(), true);
        $data = $response['data'];
        $listId = $data['Lists'][0]['id'];

        // Add additional list and item to Hank so his badge is different
        $Users = TableRegistry::getTableLocator()->get('Users');
        $Localists = TableRegistry::getTableLocator()->get('Localists');
        $UserLocalists = TableRegistry::getTableLocator()->get('UserLocalists');
        $LocalistAlerts = TableRegistry::getTableLocator()->get('LocalistAlerts');

        // create new list and save
        $user = $Users->get(UsersFixture::HANK);
        $hankOnlyListJson = $this->_getSingleMemberListJson($user, 2);
        $hankOnlyList = json_decode($hankOnlyListJson, true);
        $hankOnlyList = $Localists->save($Localists->newEntity([
            'updated' => $hankOnlyList['updated'],
            'json' => $hankOnlyListJson
        ]));
        $UserLocalists->save($UserLocalists->newEntity([
            'user_id' => UsersFixture::HANK,
            'localist_id' => $hankOnlyList->id
        ]));

        // "user" generated list, added 3 members who get the MEMBER_ADDED alert
        // returns 3 payloads, Hank, John & Daniel
        // Assert expected payload count
        // get John & Daniel & assert badge count = 3
        // get Hank and do full assert of payload (with count = 5)
        $listAlerts = $LocalistAlerts->find()
            ->where(['localist_id' => $listId]);
        $this->assertEquals(3, $listAlerts->count());
        $hankAlert = null;
        $johnAlert = null;
        $listAlerts->each(function($listAlert) use (&$hankAlert, &$johnAlert) {
            if ($listAlert->meta == UsersFixture::HANK) {
                $hankAlert = $listAlert;
            }
            if ($listAlert->meta == UsersFixture::JOHN) {
                $johnAlert = $listAlert;
            }
        });

        // Check John
        $expectedPayloadJohn = <<<ENDJSON
{
    "aps": {
        "badge": 3,
        "category": "ACTIVITY_CATEGORY",
        "alert": {
            "title": "You've been added to a list! \ud83e\udd18",
            "body": "Welcome to the \"Costco\" list."
        }
    }
}
ENDJSON;
        $this->assertEquals([
            UsersFixture::JOHN => json_decode($expectedPayloadJohn, true)
        ], $LocalistAlerts->generatePushPayloadsForAlertId($johnAlert->id));

        // Check Hank
        $expectedPayloadHank = <<<ENDJSON
{
    "aps": {
        "badge": 5,
        "category": "ACTIVITY_CATEGORY",
        "alert": {
            "title": "You've been added to a list! \ud83e\udd18",
            "body": "Welcome to the \"Costco\" list."
        }
    }
}
ENDJSON;
        $this->assertEquals([
            UsersFixture::HANK => json_decode($expectedPayloadHank, true)
        ], $LocalistAlerts->generatePushPayloadsForAlertId($hankAlert->id));
    }

    public function testDataUpdatePayload() {

        // Create a new user and list
        $user = $this->_createUser();
        $data = $this->_getFullDataWithList($user);
        $this->post('/api/syncData.json', $data);
        $response = json_decode($this->_response->getBody(), true);
        $data = $response['data'];
        $data['Lists'][0]['updated'] = ($data['Lists'][0]['updated'] + 1);
        $data['Lists'][0]['items'][] = [
            'title' => 'beer'
        ];
        $this->post('/api/syncData.json', $data);
        $listId = $data['Lists'][0]['id'];

        // Assert alerts
        $LocalistAlerts = TableRegistry::getTableLocator()->get('LocalistAlerts');
        $alerts = $LocalistAlerts->find()
            ->where(['localist_id' => $listId]);
        $this->assertEquals(4, $alerts->count());

        // Check a payload
        $expectedPayload = <<<JSON
{
    "aps": {
        "badge": 2,
        "category": "DATA_UPDATE_CATEGORY"
    }
}
JSON;
        $dataAlert = $LocalistAlerts->find()
            ->where([
                'localist_id' => $listId,
                'action' => LocalistAlertsTable::ITEMS_UPDATED
            ]);
        $this->assertEquals(1, $dataAlert->count());
        $payloads = $LocalistAlerts->generatePushPayloadsForAlertId($dataAlert->first()->id);
        $this->assertEquals(3, count($payloads));
        $this->assertEquals(json_decode($expectedPayload, true), array_pop($payloads));

    }
}