<?php
namespace FreePBX\modules\Fax;
use FreePBX\modules\Backup as Base;
class Backup Extends Base\BackupBase{
	public function runBackup($id,$transaction){
		$configs = [
				'incoming' => $this->FreePBX->Fax->getIncoming(),
				'users' => $this->FreePBX->Fax->listUsers(),
				'settings' => $this->dumpKVStore(),
				'features' => $this->dumpFeatureCodes()
		];
		$this->addDependency('userman');
		$this->addConfigs($configs);
	}
}
