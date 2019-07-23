<?php
/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Controller\Controller;
use Cake\Event\Event;

class AppController extends Controller
{

    const V_STATUS = 'status';
    const V_MESSAGE = 'message';
    const S_SUCCESS = 'success';
    const S_FAILURE = 'failure';

    public function initialize()
    {
        parent::initialize();

        $this->response = $this->response->withDisabledCache();
        $this->set('_jsonOptions', JSON_HEX_TAG | JSON_HEX_APOS |
            JSON_HEX_AMP | JSON_HEX_QUOT |
            JSON_PARTIAL_OUTPUT_ON_ERROR |
            JSON_UNESCAPED_SLASHES);

        $this->loadComponent('RequestHandler');
    }

    public function beforeRender(Event $event) {
        parent::beforeRender($event);
        $this->set('_serialize', true);
    }
}
