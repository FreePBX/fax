<?php
namespace FreePBX\modules\Fax;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
  public function runRestore($jobid){
    $configs = reset($this->getConfigs());
    $this->processConfigs($configs);
  }
  public function processLegacy($pdo, $data, $tables, $unknownTables, $tmpfiledir)
  {
    $tables = array_flip($tables + $unknownTables);
    if (!isset($tables['callback'])) {
      return $this;
    }
    $cb = $this->FreePBX->Fax;
    $cb->setDatabase($pdo);
    $configs = [
      'incoming' => $cb->getIncoming(),
      'users' => $cb->listUsers(),
    ];
    $this->processConfigs($configs);
    return $this;
  }

  public function processConfigs($configs){
    foreach ($configs['users'] as $user) {
      $this->FreePBX->Fax->saveUser($user['faxext'], $user['faxenabled'], $user['faxemail'], $user['faxattachformat']);
    }
    foreach ($configs['incoming'] as $incoming) {
      $this->FreePBX->Fax->saveIncoming($incoming['cidnum'], $incoming['extension'], $incoming['enabled'], $incoming['detection'], $incoming['detectionwait'], $incoming['destination'], $incoming['legacy_email'], $incoming['ring']);
    }
  }
}
