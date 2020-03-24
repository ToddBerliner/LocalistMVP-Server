<?php
namespace App\Test\TestCase\Controller;
use App\Model\Table\LocalistAlertsTable;
use App\Test\Fixture\UsersFixture;
use App\Test\TestCase\CommonTrait;
use Cake\TestSuite\TestCase;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\Cache\Cache;
use Cake\Utility\Hash;

class StatsControllerTest extends TestCase {

    use IntegrationTestTrait;

    public $fixtures = [
        'app.Users',
        'app.Localists',
        'app.UserLocalists',
        'app.UserLocalistAlerts',
        'app.LocalistAlerts',
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

        // create a bunch of data for UserSyncs & LocalistAlerts
        $UserSyncs = TableRegistry::getTableLocator()->get('UserSyncs');
        $UserSyncs->saveMany($UserSyncs->newEntities([
            [
                'user_id' => UsersFixture::HANK,
                'created' => '2019-01-01'
            ],
            [
                'user_id' => UsersFixture::JOHN,
                'created' => '2019-01-01'
            ],
            [
                'user_id' => UsersFixture::JOHN,
                'created' => '2019-01-01'
            ],
            [
                'user_id' => UsersFixture::HANK,
                'created' => '2019-01-02'
            ],
            [
                'user_id' => UsersFixture::JOHN,
                'created' => '2019-01-02'
            ],
            [
                'user_id' => UsersFixture::HANK,
                'created' => '2019-01-03'
            ],
            [
                'user_id' => UsersFixture::JOHN,
                'created' => '2019-01-03'
            ],
            [
                'user_id' => UsersFixture::DANIEL,
                'created' => '2019-01-03'
            ],
            [
                'user_id' => UsersFixture::JOHN
            ],
            [
                'user_id' => UsersFixture::DANIEL
            ]
        ]));
        $Localists = TableRegistry::getTableLocator()->get('Localists');
        $localist = $Localists->save($Localists->newEntity([
            'updated' => 123,
            'json' => '{"updated":123, "items":[{"title":"Beer"},{"title":"Beer"},{"title":"Beer"}]}'
        ]));
        $localist2 = $Localists->save($Localists->newEntity([
            'updated' => 123,
            'json' => '{"updated":123, "items":[{"title":"Beer"}]}'
        ]));
        $LocalistAlerts = TableRegistry::getTableLocator()->get('LocalistAlerts');
        $LocalistAlerts->saveMany($LocalistAlerts->newEntities([
            [
                'action' => LocalistAlertsTable::RETAILERS_UPDATED,
                'created' => '2019-01-01',
                'localist_id' => $localist->id
            ],
            [
                'action' => LocalistAlertsTable::RETAILERS_UPDATED,
                'created' => '2019-01-01',
                'localist_id' => $localist->id
            ],
            [
                'action' => LocalistAlertsTable::RETAILERS_UPDATED,
                'created' => '2019-01-02',
                'localist_id' => $localist->id
            ],
            [
                'action' => LocalistAlertsTable::ITEMS_UPDATED,
                'created' => '2019-01-01',
                'localist_id' => $localist->id
            ],
            [
                'action' => LocalistAlertsTable::ITEMS_UPDATED,
                'created' => '2019-01-01',
                'localist_id' => $localist->id
            ],
            [
                'action' => LocalistAlertsTable::ITEMS_UPDATED,
                'created' => '2019-01-02',
                'localist_id' => $localist->id
            ],
            [
                'action' => LocalistAlertsTable::ITEMS_UPDATED,
                'created' => '2019-01-02',
                'localist_id' => $localist->id
            ],
            [
                'action' => LocalistAlertsTable::ITEMS_UPDATED,
                'created' => '2019-01-02',
                'localist_id' => $localist->id
            ],
            [
                'action' => LocalistAlertsTable::ITEMS_UPDATED,
                'created' => '2019-01-02',
                'localist_id' => $localist->id
            ],
            [
                'action' => LocalistAlertsTable::ITEMS_UPDATED,
                'localist_id' => $localist->id
            ],
            [
                'action' => LocalistAlertsTable::ITEMS_UPDATED,
                'localist_id' => $localist2->id
            ],
            [
                'action' => LocalistAlertsTable::ITEMS_UPDATED,
                'localist_id' => $localist->id
            ],
            [
                'action' => LocalistAlertsTable::ITEMS_UPDATED,
                'localist_id' => $localist2->id
            ]
        ]));
        $UserLocalists = TableRegistry::getTableLocator()->get('UserLocalists');
        $UserLocalists->saveMany($UserLocalists->newEntities([
            [
                'localist_id' => $localist->id,
                'user_id' => UsersFixture::DANIEL
            ],
            [
                'localist_id' => $localist2->id,
                'user_id' => UsersFixture::JOHN
            ]
        ]));
        /*
>>> Calculate from data tables - UserSyncs and LocalistAlerts
- [ ] Daily num of active users [[day: count of unique user_ids from syncs]] 1/1 - 2, 1/2 - 2, 1/3 - 3
- [ ] Daily num of user syncs [[day: count of syncs]] 1/1 - 3, 1/2 - 2, 1/3 - 3
- [ ] Daily activities bar charts [[day: number of activity alerts]] 1/1 - 2, 1/2 - 1
- [ ] Daily activities item changes [[day: number of data alerts]] 1/1 - 2, 1/2 - 4
         */
    }

    public function testGetStats() {
        $this->get('/stats/getstats');
        $this->assertResponseSuccess();
        $response = json_decode($this->_response->getBody(), true);
        $data = $response['data'];

        $expectedActiveListIds = [1, 2];
        $expectedActiveUserIds = [2, 3];
        $expectedActiveUsersByDay = [
            '2019-01-01' => 2,
            '2019-01-02' => 2,
            '2019-01-03' => 3,
            date('Y-m-d') => 2
        ];
        $expectedUserSyncsByDay = [
            '2019-01-01' => 3,
            '2019-01-02' => 2,
            '2019-01-03' => 3,
            date('Y-m-d') => 2
        ];
        $expectedActivitiesByDay = [
            '2019-01-01' => 2,
            '2019-01-02' => 1
        ];
        $expectedItemsByDay = [
            '2019-01-01' => 2,
            '2019-01-02' => 4,
            date('Y-m-d') => 4
        ];
        $expectedListItemsCounts = [3, 1];

        $this->assertEquals($expectedActiveListIds, Hash::extract($data['active_lists'], '{n}.id'));
        $this->assertEquals($expectedActiveUserIds, Hash::extract($data['active_users'], '{n}.id'));
        $this->assertEquals($expectedActiveUsersByDay, $data['active_users_by_day']);
        $this->assertEquals($expectedUserSyncsByDay, $data['user_syncs_by_day']);
        $this->assertEquals($expectedActivitiesByDay, $data['activities_by_day']);
        $this->assertEquals($expectedItemsByDay, $data['items_by_day']);
        $this->assertEquals($expectedListItemsCounts, $data['list_item_counts']);
    }
}
