<?php
namespace FreePBX\modules\Fax;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase
{
	public function runRestore()
	{
		$configs = $this->getConfigs();
		$this->processConfigs($configs);
	}

	public function processConfigs($configs)
	{
		foreach (($configs['users'] ?? []) as $user)
		{
			if (!isset($user['user'], $user['faxenabled']))
			{
				continue;
			}
			$this->FreePBX->Fax->saveUser($user['user'], $user['faxenabled'], $user['faxemail'] ?? '', $user['faxattachformat'] ?? 'pdf');
		}
		foreach (($configs['incoming'] ?? []) as $incoming)
		{
			if (!isset($incoming['cidnum'], $incoming['extension'], $incoming['detection'], $incoming['detectionwait'], $incoming['destination']))
			{
				continue;
			}
			$this->FreePBX->Fax->saveIncoming($incoming['cidnum'], $incoming['extension'], $incoming['enabled'] ?? 'false', $incoming['detection'], $incoming['detectionwait'], $incoming['destination'], $incoming['legacy_email'] ?? null, $incoming['ring'] ?? 1);
		}

		$this->importKVStore($configs['settings'] ?? []);
		$this->FreePBX->Fax->restore_fax_settings($configs['fax_details'] ?? []);
		$this->importFeatureCodes($configs['features'] ?? []);
	}

	public function processLegacy($pdo, $data, $tables, $unknownTables){
		$this->restoreLegacyDatabaseKvstore($pdo);
		$this->restoreLegacyFeatureCodes($pdo);
	}
}
