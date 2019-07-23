<?php
namespace App\Shell;
use App\Util\QuickBlox;
use Cake\ORM\TableRegistry;
use Cake\Console\Shell;

class QbTokenShell extends Shell {

    function getOptionParser() {
        $parser = parent::getOptionParser();
        $parser->addArgument('user', [
            'required' => false,
            'help' => 'User id'
        ]);
        return $parser;
    }

    public function main() {
        $user = null;
        if (! empty($this->args[0])) {
            $Users = TableRegistry::getTableLocator()->get('Users');
            $user = $Users->get($this->args[0]);
            $this->out("Getting session for user: " . $user->id);
        }
        $session = QuickBlox::getSession($user);
        $this->out($session);
    }
}
