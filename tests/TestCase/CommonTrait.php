<?php
namespace App\Test\TestCase;

use App\Test\TestCase\Util\QuickbloxUtilTest;
use Cake\ORM\TableRegistry;
use App\Test\Fixture\UsersFixture;

trait CommonTrait {

    private function _createUser() {
        $Users = TableRegistry::getTableLocator()->get('Users');
        return $Users->save($Users->newEntity([
            'udid' => QuickbloxUtilTest::UDID,
            'apns' => QuickbloxUtilTest::APNS,
            'phone' => '5555228243',
            'name' => 'Test Bear',
            'first_name' => 'Test',
            'image_name' => 'Contact'
        ]));
    }

    private function _createListForUserIds($userIds) {
        $Lists = TableRegistry::getTableLocator()->get('Localists');
        $UserLists = TableRegistry::getTableLocator()->get('UserLocalists');
        $newList = $Lists->newEntity([
            'updated' => microtime(true),
            'name' => 'Test List',
            'json' => '{"foo":"bear", "updated":"123.456"}'
        ]);
        $list = $Lists->save($newList);
        foreach($userIds as $userId) {
            $UserLists->save($UserLists->newEntity([
                'user_id' => $userId,
                'localist_id' => $list->id
            ]));
        }
        return $list;
    }

    private function _getFullDataWithList($user) {
        $hankId = UsersFixture::HANK;
        $hankName = UsersFixture::HANK_NAME;
        $hankFname = UsersFixture::HANK_FNAME;
        $hankPhone = UsersFixture::HANK_PHONE;
        $johnId = UsersFixture::JOHN;
        $johnName = UsersFixture::JOHN_NAME;
        $johnFname = UsersFixture::JOHN_FNAME;
        $johnPhone = UsersFixture::JOHN_PHONE;
        $danielId = UsersFixture::DANIEL;
        $danielName = UsersFixture::DANIEL_NAME;
        $danielFname = UsersFixture::DANIEL_FNAME;
        $danielPhone = UsersFixture::DANIEL_PHONE;
        $listJson = $this->_getListJson($user);
        $data = <<<JSON
{
	"People": [{
	    "id": $user->id,
	    "udid": "abc",
	    "apns": "abc",
	    "name": "$user->name",
	    "first_name": "$user->first_name",
	    "image_name": "Contact",
	    "phone": "$user->phone"
	},{
		"id": $danielId,
		"udid": "abc",
		"apns": "abc",
		"name": "$danielName",
		"first_name": "$danielFname",
		"image_name": "Contact",
		"phone": "$danielPhone"
	}, {
		"id": $johnId,
		"udid": "abc",
		"apns": "abc",
		"name": "$johnName",
		"first_name": "$johnFname",
		"image_name": "Contact",
		"phone": "$johnPhone"
	}, {
		"id": $hankId,
		"udid": "abc",
		"apns": "abc",
		"name": "$hankName",
		"first_name": "$hankFname",
		"image_name": "Contact",
		"phone": "$hankPhone"
	}],
	"Lists": [$listJson],
	"User": {
		"id": $user->id,
		"udid": "abc",
		"apns": "abc",
		"name": "$user->name",
		"first_name": "$user->first_name",
		"image_name": "Contact",
		"phone": "$user->phone"
	}
}
JSON;
        return json_decode($data, true);
    }

    private function _getSingleMemberListJson($user, $numItems = 1) {
        $listJson = $this->_getListJson($user, $numItems);
        // remove all members but user
        $list = json_decode($listJson, true);
        $list['members'] = [];
        $list['members'][] = [
            'id' => $user->id,
            'udid' => 'abc',
            'apns' => 'abc',
            'image_name' => 'Contact',
            'phone' => $user->phone,
            'name' => $user->name,
            'first_name' => $user->first_name
        ];

        return json_encode($list);
    }

    private function _getListJson($user, $numItems = 1) {
        $hankId = UsersFixture::HANK;
        $hankName = UsersFixture::HANK_NAME;
        $hankFname = UsersFixture::HANK_FNAME;
        $johnId = UsersFixture::JOHN;
        $johnName = UsersFixture::JOHN_NAME;
        $johnFname = UsersFixture::JOHN_FNAME;
        $danielId = UsersFixture::DANIEL;
        $danielName = UsersFixture::DANIEL_NAME;
        $danielFname = UsersFixture::DANIEL_FNAME;
        $time = microtime(true);

        $items = [];
        while (count($items) < $numItems) {
            $items[] = '{"title":"Beer"}';
        }
        $itemsJson = implode(',', $items);

        $json = <<<JSON
{
		"updated": $time,
		"title": "Costco",
		"items": [$itemsJson],
		"retailers": [{
			"name": "Costco Wholesale",
			"logoImageName": "",
			"locations": [{
				"radius": 100,
				"address": "1001 Metro Center Blvd, Foster City",
				"longitude": -122.27419227361679,
				"identifier": "Costco Wholesale-1001 Metro Center Blvd, Foster City",
				"latitude": 37.561858751872002,
				"imageName": "",
				"name": "Costco Wholesale"
			}, {
				"radius": 100,
				"address": "2300 Middlefield Rd, Redwood City",
				"longitude": -122.21625924110413,
				"identifier": "Costco Wholesale-2300 Middlefield Rd, Redwood City",
				"latitude": 37.478425626897902,
				"imageName": "",
				"name": "Costco Wholesale"
			}, {
				"radius": 100,
				"address": "451 S Airport Blvd, South San Francisco",
				"longitude": -122.40066647529602,
				"identifier": "Costco Wholesale-451 S Airport Blvd, South San Francisco",
				"latitude": 37.642492783473728,
				"imageName": "",
				"name": "Costco Wholesale"
			}]
		}],
		"members": [{
			"id": $hankId,
			"udid": "abc",
			"apns": "abc",
			"image_name": "Contact",
			"phone": "5557664823",
			"name": "$hankName",
			"first_name": "$hankFname"
		}, {
			"id": $johnId,
			"udid": "abc",
			"apns": "abc",
			"image_name": "Contact",
			"phone": "8885551212",
			"name": "$johnName",
			"first_name": "$johnFname"
		}, {
			"id": $danielId,
			"udid": "abc",
			"apns": "abc",
			"image_name": "Contact",
			"phone": "5554787672",
			"name": "$danielName",
			"first_name": "$danielFname"
		}, {
		    "id": $user->id,
		    "udid": "abc",
		    "apns": "abc",
		    "image_name": "Contact",
		    "phone": "$user->phone",
		    "name": "$user->name",
		    "first_name": "$user->first_name"
		}]
	}
JSON;
        return $json;
    }
}