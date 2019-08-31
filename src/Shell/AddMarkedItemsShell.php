<?php
namespace App\Shell;
use Cake\ORM\TableRegistry;
use Cake\Console\Shell;

class AddMarkedItemsShell extends Shell {
    public function main() {
        $Localists = TableRegistry::getTableLocator()->get('Localists');
        $localists = $Localists->find()->all();
        $localists->each(function($localist) use ($Localists) {
            $json = json_decode($localist['json'], true);
            if (!array_key_exists('markedItems', $json)) {
                $json['markedItems'] = [];
                $localist['json'] = json_encode($json);
                $Localists->save($localist);
                $this->out("Updated {$localist['id']}");
            }
        });
    }
}
