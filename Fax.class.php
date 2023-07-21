<?php
namespace FreePBX\modules;
use FreePBX_Helpers;
use BMO;

class Fax extends FreePBX_Helpers implements BMO
{
	final public const ASTERISK_SECTION = 'ext-fax';

	private $FreePBX;
	private $db;
	private $astman;
	private $userman;
	private $config;

	public $tables = ['users' 		=> 'fax_users', 'incoming' 		=> 'fax_incoming', 'details' 		=> 'fax_details', 'incoming_core'	=> 'incoming'];

	public $default_settings = ['ecm' => ['default' => 'no', 'type' 	  => 'yesno'], 'fax_rx_email' => ['default' => 'yes', 'type' 	  => 'email'], 'force_detection' => ['default' => 'no', 'type' 	  => 'yesno'], 'headerinfo' => ['default' => '', 'type' 	  => 'text'], 'legacy_mode' => ['default' => 'no', 'type' 	  => 'yesno'], 'localstationid' => ['default' => '', 'type' 	  => 'text'], 'maxrate' => ['default' => '14400', 'type' 	  => 'numeric'], 'minrate' => ['default' => '9600', 'type' 	  => 'numeric'], 'modem'	=> [
     //TODO: It is not used anywhere, can it be deleted?
     'default' => '',
     'type' 	  => 'text',
 ], 'sender_address' => ['default' => '', 'type' 	  => 'text'], 'papersize' => ['default' => 'letter', 'type' 	  => 'list', 'options' => ['latter', 'a4']]];
	
	public function __construct($freepbx = null)
	{
		if ($freepbx == null) 
		{
			throw new \RuntimeException('Not given a FreePBX Object');
		}

		$this->FreePBX 	= $freepbx;
		$this->db 		= $freepbx->Database;
		$this->astman 	= $freepbx->astman;
		$this->userman 	= $freepbx->Userman;
		$this->config 	= $freepbx->Config;

		// We call applications to register in the system apps section.
		fpbx_which('gs');
		fpbx_which('tiff2pdf');
		fpbx_which('tiffinfo');
	}

	public function setDatabase($pdo)
	{
		$this->db = $pdo;
		return $this;
	}

	public function resetDatabase()
	{
		$this->db = $this->FreePBX->Database;
		return $this;
	}

	public static function myConfigPageInits()
	{
		return ["did"];
	}

	public function doConfigPageInit($page)
	{
		$request = freepbxGetSanitizedRequest();
		switch ($page)
		{
			case 'fax':
				foreach($this->default_settings as $k => $v)
				{
					$input = $request[$k] ?? $v['default'];
					$input = trim((string) $input);

					switch ($v['type'])
					{
						case 'yesno':
							$input = strtolower($input);
							if (! in_array($input, ['yes', 'no']) )
							{
								$input = $v['default'];
							}
						break;
						
						case 'list':
							$input = strtolower($input);
							if (! in_array($input, $v['options']) )
							{
								$input = $v['default'];
							}
						break;

						case 'numeric':
							if (! is_numeric($input))
							{
								$input = $v['default'];
							}
						break;

						case 'text':
							$input = htmlspecialchars($input);
							break;

						case 'email':
							$input = htmlspecialchars($input);
							if (! filter_var($input, FILTER_VALIDATE_EMAIL))
							{
								$input = $v['default'];
							}
							break;
	
						default:
							continue 2;
					}
					$fax[$k] = $input;
				}
				if (isset($request['action']) &&  $request['action'] == 'edit')
				{
					$this->setSettings($fax);
				}
			break;

			case "did":
				$action			= $request['action'] ?? '';
				$cidnum			= $request['cidnum'] ?? '';
				$extension		= $request['extension'] ?? '';
				$extdisplay		= $request['extdisplay'] ?? '';
				$enabled		= $request['faxenabled'] ?? 'false';
				$detection		= $request['faxdetection'] ?? '';
				$ring			= $request['faxring'] ?? '';
				$detectionwait	= $request['faxdetectionwait'] ?? '';
				$dest			= (isset($request['gotoFAX'])			? $request['gotoFAX'].'FAX'		: null);
				$dest			= $request[$dest] ?? '';
				if ($enabled != 'legacy')
				{
					$legacy_email = null;
				}
				else
				{
					$legacy_email = $request['legacy_email'] ?? '';
				}

				if (! is_numeric($detectionwait) or ($detectionwait < 0))
				{
				 	$detectionwait = '4';
				}

				switch($action)
				{
					case "edtIncoming":
						$this->deleteIncoming($extdisplay);
						// fall through to next level on purpose
						
					case "addIncoming":
						if($enabled != 'false')
						{
							$this->saveIncoming($cidnum, $extension, $enabled, $detection, $detectionwait, $dest, $legacy_email, $ring);
						}
					break;

					case "delIncoming":
						$this->deleteIncoming($extdisplay);
                    break;

                    default:
                    break;
				}
            break;
            default:
            break;
		}
	}

	public function usermanShowPage()
	{
		$request = $_REQUEST;
		if(isset($request['action']))
		{
			$error = "";
			$faxStatus = $this->faxDetect();
			if( ! $faxStatus['module'] || ($faxStatus['module'] && ((isset($faxStatus['ffa']) && !$faxStatus['ffa']) && !$faxStatus['spandsp'])))
			{
				//missing modules
				$error = _('ERROR: No FAX modules detected!<br>Fax-related dialplan will <b>NOT</b> be generated.<br>This module requires spandsp based app_fax (res_fax_spandsp.so) to function.');
			}
			elseif(isset($faxStatus['ffa']) && $faxStatus['ffa'] && $faxStatus['license'] < 1)
			{
				//missing license
				$error = _('ERROR: No Fax license detected.<br>Fax-related dialplan will <b>NOT</b> be generated!<br>This module has detected that Fax for Asterisk is installed without a license.<br>At least one license is required (it is available for free) and must be installed.');
			}
			$data_return = [];
			switch($request['action'])
			{
				case 'addgroup':
				case 'showgroup':
					$enabled 	   = ($request['action'] == "addgroup") ? true : $this->userman->getModuleSettingByGID($request['group'],'fax','enabled');
					$attachformat  = ($request['action'] == "addgroup") ? 'pdf' : $this->userman->getModuleSettingByGID($request['group'],'fax','attachformat');
					$data_return[] = ["title"   => _("Fax"), "rawname" => "fax", "content" => $this->showPage('userman_showpage', ["mode" => "group", "error" => $error, "enabled" => $enabled, "attachformat" => $attachformat])];
				break;
					
				case 'adduser':
				case 'showuser':
					$enabled 	  = null;
					$attachformat = 'pdf';

					if(isset($request['user']))
					{
						$user 		  = $this->userman->getUserByID($request['user']);
						$enabled 	  = $this->userman->getModuleSettingByID($user['id'],'fax','enabled',true);
						$attachformat = $this->userman->getModuleSettingByID($user['id'],'fax','attachformat',true);
					}
					$data_return[] = ["title"   => _("Fax"), "rawname" => "fax", "content" => $this->showPage('userman_showpage', ["mode" => "user", "error" => $error, "enabled" => $enabled, "attachformat" => $attachformat])];
				break;
			}
			return $data_return;
		}
	}

	public function usermanDelGroup($id, $display, $data)
	{
		foreach($data['users'] as $user)
		{
			$enabled 	  = $this->userman->getCombinedModuleSettingByID($user, 'fax', 'enabled');
			$attachformat = $this->userman->getCombinedModuleSettingByID($user, 'fax', 'attachformat');
			$userData 	  = $this->userman->getUserByID($user);
			if(!empty($userData))
			{
				$this->saveUser($userData['id'], ($enabled ? "true" : "false"), $userData['email'], $attachformat);
			}
		}
	}

	public function usermanAddGroup($id, $display, $data)
	{
		$this->usermanUpdateGroup($id, $display, $data);
	}

	public function usermanUpdateGroup($id,$display,$data) 
	{
		$post = $_POST;
		if($display == 'userman' && isset($post['type']) && $post['type'] == 'group')
		{
			if(isset($post['faxenabled']))
			{
				if($post['faxenabled'] == "true")
				{
					$this->userman->setModuleSettingByGID($id, 'fax', 'enabled', true);
					$this->userman->setModuleSettingByGID($id, 'fax', 'attachformat', $post['faxattachformat']);
				}
				else
				{
					$this->userman->setModuleSettingByGID($id, 'fax', 'enabled', false);
					$this->userman->setModuleSettingByGID($id, 'fax', 'attachformat', "pdf");
				}
			}
		}

		$group = $this->userman->getGroupByGID($id);
		foreach($group['users'] as $user)
		{
			$enabled 	  = $this->userman->getCombinedModuleSettingByID($user, 'fax', 'enabled');
			$attachformat = $this->userman->getCombinedModuleSettingByID($user, 'fax', 'attachformat');
			$userData 	  = $this->userman->getUserByID($user);
			if(!empty($userData))
			{
				$this->saveUser($userData['id'], ($enabled ? "true" : "false"), $userData['email'], $attachformat);
			}
		}
	}

	/**
	 * Hook functionality from userman when a user is deleted
	 * @param {int} $id      The userman user id
	 * @param {string} $display The display page name where this was executed
	 * @param {array} $data    Array of data to be able to use
	 */
	public function usermanDelUser($id, $display, $data)
	{
		$this->deleteUser($id);
	}

	/**
	 * Hook functionality from userman when a user is added
	 * @param {int} $id      The userman user id
	 * @param {string} $display The display page name where this was executed
	 * @param {array} $data    Array of data to be able to use
	 */
	public function usermanAddUser($id, $display, $data)
	{
		$post = $_POST;
		if($display == 'userman' && isset($post['type']) && $post['type'] == 'user')
		{
			if(isset($post['faxenabled']))
			{
				switch($post['faxenabled'])
				{
					case 'true':
						$this->userman->setModuleSettingByID($id, 'fax', 'enabled', true);
						$this->userman->setModuleSettingByID($id, 'fax', 'attachformat', !empty($post['faxattachformat']) ? $post['faxattachformat']: null);
					break;
					
					case "false":
						$this->userman->setModuleSettingByID($id, 'fax', 'enabled', false);
					break;

					default:
						$this->userman->setModuleSettingByID($id, 'fax', 'enabled', null);
					break;
				}
			}
		}

		$enabled 	  = $this->userman->getCombinedModuleSettingByID($id, 'fax', 'enabled');
		$attachformat = $this->userman->getCombinedModuleSettingByID($id, 'fax', 'attachformat');
		$user 		  = $this->FreePBX->Userman->getUserByID($id);
		if(!empty($user))
		{
			$this->saveUser($id, ($enabled ? "true" : "false"), $user['email'], $attachformat);
		}
	}

	/**
	 * Hook functionality from userman when a user is updated
	 * @param {int} $id      The userman user id
	 * @param {string} $display The display page name where this was executed
	 * @param {array} $data    Array of data to be able to use
	 */
	public function usermanUpdateUser($id, $display, $data)
	{
		$post = $_POST;
		if($display == 'userman' && isset($post['type']) && $post['type'] == 'user')
		{
			if(isset($post['faxenabled']))
			{
				if($post['faxenabled'] == "true")
				{
					$this->userman->setModuleSettingByID($id, 'fax', 'enabled', true);
					$this->userman->setModuleSettingByID($id, 'fax', 'attachformat', !empty($post['faxattachformat']) ? $post['faxattachformat'] : null);
				}
				elseif($post['faxenabled'] == "false")
				{
					$this->userman->setModuleSettingByID($id,'fax','enabled',false);
				}
				else
				{
					$this->userman->setModuleSettingByID($id, 'fax', 'enabled', null);
					$this->userman->setModuleSettingByID($id, 'fax', 'attachformat', null);
				}
			}
		}

		$enabled 	  = $this->userman->getCombinedModuleSettingByID($id, 'fax', 'enabled');
		$attachformat = $this->userman->getCombinedModuleSettingByID($id, 'fax', 'attachformat');
		$user 		  = $this->FreePBX->Userman->getUserByID($id);
		if(!empty($user))
		{
			$this->saveUser($id,($enabled ? "true" : "false"),$user['email'],$attachformat);
		}
	}

	public function install()
	{
		$fcc = new \featurecode('fax', 'simu_fax');
		$fcc->setDescription(_('Dial System FAX'));
		$fcc->setDefault('666');
		$fcc->setProvideDest();
		$fcc->update();
		unset($fcc);

		outn(_("Upgrading configs.."));
		$set = [];
		$set['value'] = 'www.freepbx.org';
		$set['defaultval'] =& $set['value'];
		$set['readonly'] = 1;
		$set['hidden'] = 1;
		$set['module'] = '';
		$set['category'] = 'Styling and Logos';
		$set['emptyok'] = 0;
		$set['name'] = 'tiff2pdf Author';
		$set['description'] = _("Author to pass to tiff2pdf's -a option");
		$set['type'] = CONF_TYPE_TEXT;
		$this->config->define_conf_setting('PDFAUTHOR', $set, true);
		unset($set);
		out(_("Done!"));
	}

	public function uninstall() {

	}

	public function showPage($page, $params = [])
	{
		$request = $_REQUEST;
		$data = ["fax" 	  => $this, 'request' => $request, 'page' 	  => $page];
		$data = array_merge($data, $params);
		$data_return = match ($page) {
      'main' => load_view(__DIR__."/views/page.main.php", $data),
      'form_options' => load_view(__DIR__."/views/view.form_options.php", $data),
      'core_DIDHook' => load_view(__DIR__."/views/view.coreDIDHook.php", $data),
      'userman_showpage' => load_view(__DIR__.'/views/view.userman.showpage.php', $data),
      default => sprintf(_("Page Not Found (%s)!!!!"), $page),
  };
		return $data_return;
	}

	public function genConfig()
	{
		global $version;
		$conf = [];

		$fax = $this->faxDetect();
		$ast_lt_18 = version_compare($version, '1.8', 'lt');
		if($fax['module'] && ($ast_lt_18 || (isset($fax['ffa']) && $fax['ffa']) || $fax['spandsp']))
		{
			//dont continue unless we have a fax module in asterisk

			$settings = $this->getSettings();
			$conf['res_fax.conf']['general'][] = "#include res_fax_custom.conf";
			if(!empty($settings['minrate']))
			{
				$conf['res_fax.conf']['general']['minrate'] = $settings['minrate'];
			}
			if(!empty($settings['maxrate']))
			{
				$conf['res_fax.conf']['general']['maxrate'] = $settings['maxrate'];
			}

			$conf['res_fax_digium.conf']['general'][] = "#include res_fax_digium_custom.conf";
			if(!empty($settings['ecm']))
			{
				$conf['res_fax_digium.conf']['general']['ecm'] = $settings['ecm'];
			}
		}
		return $conf;
	}

	public function writeConfig($conf)
	{
		$this->FreePBX->WriteConfig($conf);
	}

	public function restore_fax_settings($fax_details)
	{
		$this->setSettings($fax_details);
	}
	
	public function getSetting($key, $default = null)
	{
		$return_data = $default;
		if (! empty($key))
		{
			$key 	  = strtolower((string) $key);
			$settings = array_change_key_case($this->getSettings());
			if (array_key_exists($key, $settings))
			{
				$return_data = $settings[$key];
			}
		}
		return $return_data;
	}

	public function getSettings()
	{
		//TODO: migrate settings to kvstore
		$set 	  = [];
		$sql 	  = sprintf('SELECT * FROM %s', $this->tables['details']);
		$settings = sql($sql, 'getAssoc', 'DB_FETCHMODE_ASSOC');
		foreach($settings as $setting => $value)
		{
			$set[$setting] = $value['0'];
		}
		$set = array_change_key_case($set);
		return $set;
	}

	public function setSettings($settings)
	{
		//TODO: migrate settings to kvstore
		if (is_array($settings))
		{
			foreach($settings as $key => $value)
			{
				$key = strtolower($key);
				$sql = sprintf('REPLACE INTO fax_details (`key`, `value`) VALUES (LOWER(?), ?)', $this->tables['details']);
				$sth = $this->db->prepare($sql);
				$sth->execute([$key, $value]);
				// $db->escapeSimple($value)
			}
			needreload();
		}
	}

	public function deleteUser($faxext)
	{
		$sql = sprintf('DELETE FROM %s where user = ?', $this->tables['users']);
		$sth = $this->db->prepare($sql);
		$sth->execute([$faxext]);
	}

	public function saveUser($faxext, $faxenabled, $faxemail = '', $faxattachformat = 'pdf')
	{
		$sql = sprintf('REPLACE INTO %s (user, faxenabled, faxemail, faxattachformat) VALUES (?, ?, ?, ?)', $this->tables['users']);
		$sth = $this->db->prepare($sql);
		try
		{
			$sth->execute([$faxext, $faxenabled, $faxemail, $faxattachformat]);
		}
		catch(\Exception)
		{
			return false;
		}
		return true;
	}

	public function getUser($user)
	{
		$sql = sprintf('SELECT * FROM %s WHERE user = ?', $this->tables['users']);
		$sth = $this->db->prepare($sql);
		$sth->execute([$user]);
		$out = $sth->fetchAll(\PDO::FETCH_ASSOC);
		return (!empty($out[0]) && $out[0]['faxenabled']) ? $out[0] : false;
    }

    public function listUsers()
	{
		$sql = sprintf('SELECT * FROM %s', $this->tables['users']);
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

	public function faxDetect()
	{
		$fax = null;
		if (isset($this->astman) && $this->astman->connected())
		{
			$fax = [];
			$fax['module'] = match (true) {
       $this->astman->mod_loaded('res_fax.so') => 'res_fax',
       default => null,
   };

			$fax['spandsp'] = $this->astman->mod_loaded('res_fax_spandsp.so');

			$fax['receivefax'] = match ($fax['module']) {
       'res_fax' => 'receivefax',
       default => 'none',
   };

			//get license count
			$lic = $this->astman->send_request('Command', ['Command' => 'fax show stats']);
			foreach(explode("\n",(string) $lic['data']) as $licdata)
			{
				$d = explode(':',$licdata);
				$data[trim($d['0'])] = isset($d['1']) ? trim($d['1']) : null;
			}
			$fax['license'] = $data['Licensed Channels'] ?? '';
		}
		return $fax;
    }

    public function getIncoming($extension=null, $cidnum=null)
	{
        if (null !== $extension || null !== $cidnum)
		{
            $sql = sprintf('SELECT * FROM %s WHERE extension = :extension AND cidnum = :cidnum LIMIT 1', $this->tables['incoming']);
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':extension' => $extension, ':cidnum' => $cidnum]);
            $settings = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (isset($settings['legacy_email']) && 'NULL' == $settings['legacy_email'])
			{
                $settings['legacy_email'] = null;
			}
            return $settings;
        }

		$sql = sprintf("SELECT tFax.*, tCore.pricid FROM %s as tFax, %s as tCore where tFax.cidnum=tCore.cidnum and tFax.extension=tCore.extension;", $this->tables['incoming'], $this->tables['incoming_core']);
        return $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function saveIncoming($cidnum, $extension, $enabled, $detection, $detectionwait, $destination, $legacy_email, $ring = 1)
	{
        $legacy_email ??= 'NULL';
        $ring = ($ring == 'yes') ? 1 : 0;
        $sql = sprintf("INSERT INTO %s (cidnum, extension, detection, detectionwait, destination, legacy_email, ring) VALUES (:cidnum, :extension, :detection, :detectonwait, :destination,:legacy_email, :ring)", $this->tables['incoming']);
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':cidnum' 		=> $cidnum,
            ':extension' 	=> $extension,
            ':detection' 	=> $detection,
            ':detectonwait'	=> $detectionwait,
            ':destination'	=> $destination,
            ':legacy_email'	=> $legacy_email,
            ':ring' 		=> $ring,
        ]);
        return $this;
    }

    public function deleteIncoming($extdisplay)
	{
        $opts = explode('/', (string) $extdisplay);
        $extension = $opts['0'];
        $cidnum = $opts['1']; //set vars
        $sql = sprintf('DELETE FROM %s WHERE cidnum = :cidnum AND extension = :extension', $this->tables['incoming']);
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
			':cidnum' 	 => $cidnum,
			':extension' => $extension
		]);
        return $this;
    }

	public function getActionBar($request)
	{
		$data_return = [];
        if ($request['display'] === 'fax')
		{
            $data_return = ['submit' => ['name' => 'submit', 'id' => 'submit', 'value' => _("Submit")], 'reset' => ['name' => 'reset', 'id' => 'reset', 'value' => _("Reset")]];
        }
		return $data_return;
	}

	public function coreDIDHook($page)
	{
		$request = $_REQUEST;
		if($page == 'did')
		{
			$target_menuid 	= $page;
			$extension		= $request['extension'] ?? '';
			$cidnum			= $request['cidnum'] ?? '';
			$extdisplay		= $request['extdisplay'] ?? '';

			//if were editing, get save parms. Get parms

			if(!$extension && !$cidnum)	//set $extension,$cidnum if we dont already have them
			{
				if ($extdisplay)
				{
					$opts		= explode('/', (string) $extdisplay);
					$extension	= $opts['0'];
					$cidnum		= $opts['1'] ?? '';
				}
				else
				{
					$extension = $cidnum = '';
				}
			}

			$fax = $this->getIncoming($extension, $cidnum);

			$html = '';
			if($target_menuid == 'did')
			{
				$data = ['fax_dahdi_faxdetect' => fax_dahdi_faxdetect(), 'fax_sip_faxdetect'	  => fax_sip_faxdetect(), 'dahdi'				  => ast_with_dahdi() ? _('Dahdi'): _('Zaptel'), 'fax_detect'		  => $this->faxDetect(), 'fax_settings'		  => $this->getSettings(), 'info'				  => engine_getinfo(), 'faxing' 			  => !empty($fax), 'fax_incoming' 		  => $fax];
				$html = $this->showPage('core_DIDHook', $data);
			}
			$ret = [];
			$ret[] = ['title' => _("Fax"), 'rawname' => 'fax', 'content' => $html];
			return $ret;
		}
	}

	public function bulkhandlerGetHeaders($type)
	{
        if($type === 'dids')
		{
			return ['fax_enable' => ['identifier' => _('Fax Enabled'), 'description' => _('Fax Enabled')], 'fax_detection' => ['identifier' => _('Fax Detection'), 'description' => _('Type of fax detection to use (e.g. SIP or DAHDI)')], 'fax_detectionwait' => ['identifier' => _('Fax Detection Wait'), 'description' => _('How long to wait and try to detect fax')], 'fax_destination' => ['identifier' => _('Fax Destination'), 'description' => _('Where to send the faxes')]];
		}
	}

	public function bulkhandlerExport($type)
	{
		$data = NULL;
		switch ($type)
		{
			case 'usermanusers':
				$users = $this->userman->getAllUsers();
				foreach ($users as $user)
				{
					$en = $this->userman->getModuleSettingByID($user['id'], 'fax', 'enabled', true);
					$data[$user['id']] = ['fax_enabled' 		=> is_null($en) ? "inherit" : (empty($en) ? 'no' : 'yes'), 'fax_attachformat'	=> $this->userman->getModuleSettingByID($user['id'], 'fax', 'attachformat')];
				}
			break;
			case 'usermangroups':
				$groups = $this->userman->getAllGroups();
				foreach ($groups as $group)
				{
					$en = $this->userman->getModuleSettingByGID($group['id'],'fax','enabled');
					$data[$group['id']] = ['fax_enabled' 		=> empty($en) ? 'no' : 'yes', 'fax_attachformat' 	=> $this->userman->getModuleSettingByGID($group['id'],'fax','attachformat')];
				}
			break;
			case "dids":
				$dids = $this->FreePBX->Core->getAllDIDs();
				$data = [];
				$this->FreePBX->Modules->loadFunctionsInc("fax");
				foreach($dids as $did)
				{
					$key = sprintf("%s/%s", $did['extension'], $did["cidnum"]);
					$fax = $this->getIncoming($did['extension'], $did["cidnum"]);
					if(!empty($fax))
					{
						$data[$key] = ["fax_enable" 		=> "yes", "fax_detection" 	=> $fax['detection'], "fax_detectionwait" => $fax['detectionwait'], "fax_destination" 	=> $fax['destination']];
					}
					else
					{
						$data[$key] = ["fax_enable" 		=> "", "fax_detection" 	=> "", "fax_detectionwait" => "", "fax_destination" 	=> ""];
					}
				}
			break;
		}
		return $data;
	}

	public function bulkhandlerImport($type, $rawData, $replaceExisting = false)
	{
		$ret = NULL;
		switch ($type)
		{
			case 'usermanusers':
				foreach ($rawData as $data)
				{
					$user = $this->FreePBX->Userman->getUserByUsername($data['username']);
					if(isset($data['fax_enabled']))
					{
						$en = ($data['fax_enabled'] == "yes") ? true : ($data['fax_enabled'] == "no" ? false : null);
						$this->userman->setModuleSettingByID($user['id'],'fax','enabled',$en);
					}
					if(isset($data['fax_attachformat']))
					{
						$this->userman->setModuleSettingByID($user['id'],'fax','attachformat',$data['fax_attachformat']);
					}
				};
			break;
			case 'usermangroups':
				foreach ($rawData as $data)
				{
					$group = $this->FreePBX->Userman->getGroupByUsername($data['groupname']);
					if(isset($data['fax_enabled']))
					{
						$en = ($data['fax_enabled'] == "yes") ? true : false;
						$this->userman->setModuleSettingByGID($group['id'],'fax','enabled',$en);
					}
					if(isset($data['fax_attachformat']))
					{
						$this->userman->setModuleSettingByGID($group['id'],'fax','attachformat',$data['fax_attachformat']);
					}
				};
			break;
			case 'dids':
				$this->FreePBX->Modules->loadFunctionsInc("fax");
				foreach ($rawData as $data)
				{
					$settings = [];
					foreach ($data as $key => $value)
					{
						if (str_starts_with((string) $key, 'fax_'))
						{
							$settingname = substr((string) $key, 4);
							$settings[$settingname] = $value;
						}
					}
					$extdisplay = sprintf("%s/%s", $data['extension'], $data["cidnum"]);
					$this->deleteIncoming($extdisplay);
					if(!empty($settings['enable']))
					{
						$this->saveIncoming($data["cidnum"], $data['extension'], true, $settings['detection'], $settings['detectionwait'], $settings['destination'], null);
					}
				}
			break;
		}
	}

	/**
	 * Chown hook for freepbx fwconsole
	 */
	public function chownFreepbx()
	{
		$webroot = $this->FreePBX->Config->get('AMPWEBROOT');
		$modulebindir = sprintf('%s/admin/modules/fax/bin', $webroot);
		$files = [];
		$files[] = ['type' => 'file', 'path' => sprintf('%s/fax2mail.php', $modulebindir), 'perms' => 0755];
		return $files;
	}

	/**
	 * Destinations hooks
	 */
	public function getDest($exten)
	{
		return sprintf('%s,%s,1', self::ASTERISK_SECTION, $exten);
	}

	public function destinations()
	{
		$extens = [];
		$recip  = $this->get_destinations();
		usort($recip, fn($a, $b) => ($a['uname'] < $b['uname']) ? -1 : 1);
		foreach ($recip as $row)
		{
			$extens[] = ['destination' => $this->getDest($row['user']), 'description' => sprintf("%s (%s)", $row['name'], $row['uname']), 'category' 	  => _('Fax Recipient')];
		}
		if (! empty($extens)) { return $extens; }
		else 				  { return null; }
	}

	public function destinations_check($dest=true)
	{
		$fax = $this->faxDetect();
		if (!$fax['module'] || ($fax['module'] && (isset($fax['ffa']) && !$fax['ffa'] && !$fax['spandsp'])))
		{
			return false;
		}
		elseif (isset($fax['ffa']) && $fax['ffa'] && $fax['license'] < 1)	//missing license
		{
			return false;
		}
	
		$destlist = [];
		if (is_array($dest) && empty($dest)) { return $destlist; }
		$sql = sprintf("SELECT a.extension, a.cidnum, b.description, a.destination FROM %s a JOIN %s b WHERE a.extension = b.extension AND a.cidnum = b.cidnum AND a.legacy_email IS NULL %s ORDER BY extension, cidnum",
			$this->tables['incoming'],
			$this->tables['incoming_core'],
			($dest !== true) ? sprintf("AND a.destination in ('%s') ", implode("','", $dest)) : ''
		);		
		$results = $this->db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);	
		foreach ($results as $result)
		{
			$thisdest 	= $result['destination'];
			$thisid   	= sprintf("%s/%s", $result['extension'], $result['cidnum']);
			$destlist[] = ['dest' 		  => $thisdest, 'description' => sprintf(_("Inbound Fax Detection: %s (%s)"), $result['description'], $thisid), 'edit_url' 	  => 'config.php?display=userman&action=showuser&user='.urlencode($thisid)];
		}
		return $destlist;
	}

	public function destinations_change($old_dest, $new_dest)
	{
		$sql = sprintf('UPDATE %s SET destination = ? WHERE destination = ?', $this->tables['incoming']);
		$sth = $this->db->prepare($sql);
		$sth->execute([$new_dest, $old_dest]);
	}

	public function destinations_getdestinfo($dest)
	{
		$srt_section = sprintf("%s,", self::ASTERISK_SECTION);
		if (str_starts_with(trim((string) $dest), $srt_section))
		{
			$usr = explode(',', (string) $dest);
			$usr = $usr[1];
			$thisusr = $this->getUser($usr);
			if (! empty($thisusr))
			{
				return ['description' => sprintf(_("Fax user %s"), $usr), 'edit_url' 	  => 'config.php?display=userman&action=showuser&user='.urlencode($usr)];
			}
			return [];
		}
		return false;
	}

	public function destinations_identif($dests)
	{
		if (! is_array($dests)) {
			$dests = [$dests];
		}
		$return_data = [];
		foreach ($dests as $target)
		{
			$info = $this->destinations_getdestinfo($target);
			if (!empty($info))
			{
				$return_data[$target] = $info;
			}
		}
		return $return_data;
	}

	public function get_destinations()
	{
		$final = [];
		$warning = [];
		foreach($this->listUsers() as $res)
		{
			if ($res['faxenabled'] != 'true') { continue; }

			$o = $this->userman->getUserByID($res['user']);
			if(!empty($o))
			{
				if(empty($o['email']))
				{
					$warning[] = $o['username'];
				}
				$res['uname'] = $o['username'];
				$res['name']  = !empty($o['displayname']) ? $o['displayname'] : sprintf("%s %s", $o['fname'], $o['lname']);
				$res['name']  = trim((string) $res['name']);
				$res['name']  = !empty($res['name']) ? $res['name'] : $o['username'];
				$final[] = $res;
			}
		}

		$nt = \notifications::create();
		if(!empty($warning))
		{
			$nt->add_warning("fax", "invalid_email", _("Invalid Email for Inbound Fax"), sprintf(_("User Manager users '%s' have the ability to receive faxes but have no email address defined so they will not be able to receive faxes over email,"), implode(",", $warning)), "", true, true);
		}
		else
		{
			$nt->delete("fax", "invalid_email");
		}
		return $final;
	}




	/**
	 * Converts a file to different format
	 * @param string - conversion type in the format of 'from2to'
	 * @param string - path to origional file
	 * @param string - path to save new file
	 * @param bool - wether to keep or delete the orgional file
	 *
	 * @return string - path to fresh pdf
	 *
	 * Supported conversions:
	 *	- pdf2tif
	*	- tif2pdf
	*	- ps2tif
	*/
	public function fax_file_convert($type, $in, $out = '', $keep_orig = false, $opts = [])
	{
		$ext = null;
  $cmd = null;
  //ensure file exists
		if (! is_file($in))
		{
			return false;
		}

		//check is format supported
		if (! in_array($type, ['pdf2tif', 'tif2pdf', 'ps2tif']) )
		{
			return $in;
		}

		//set out filename if not specified
		if (!$out) 
		{
			switch ($type)
			{
				case 'pdf2tif':
				case 'ps2tif':
					$ext = 'tif';
				break;

				case 'tif2pdf':
					$ext = 'pdf';
				break;
			}
			$pathinfo = pathinfo((string) $in);

			$out = sprintf('%s/%s.%s', $pathinfo['dirname'], $pathinfo['filename'], $ext);
		}

		//if file exists, assume its been converted already
		if (file_exists($out))
		{
			return $out;
		}

		//ensure cli command exists
		switch ($type)
		{
			case 'pdf2tif':
			case 'ps2tif':
				$gs = fpbx_which('gs');
				if (!$gs)
				{
					freepbx_log(FPBX_LOG_ERROR, sprintf(_('gs not found, not converting %s'), $in));
					return $in;
				}
				$res = $opts['res'] ?? "204x98";
				//http://www.soft-switch.org/spandsp_faq/ar01s14.html
				$gs = sprintf('%s -q -dNOPAUSE -dBATCH -dAutoRotatePages=/All -dFIXEDMEDIA -dPDFFitPage -sColorConversionStrategy=Gray -dProcessColorModel=/DeviceGray -dCompatibilityLevel=1.4 -r%s ', $gs, $res);
			break;

			case 'tif2pdf':
				$tiff2pdf = fpbx_which('tiff2pdf');
				if (!$tiff2pdf)
				{
					freepbx_log(FPBX_LOG_ERROR, sprintf(_('tiff2pdf not found, not converting %s'), $in));
					return $in;
				}
			break;
		}

		//convert!
		switch ($type)
		{
			case 'pdf2tif':
			case 'ps2tif':
				$cmd = sprintf('%s -sDEVICE=tiffg4 -sOutputFile=%s %s', $gs, $out, $in);
				break;

			case 'tif2pdf':
				$creator = $this->config->get('DASHBOARD_FREEPBX_BRAND');
				$author  = $this->config->get('PDFAUTHOR');
				$title 	 = (isset($opts['title']) ? sprintf('-t "%s"', $opts['title']) : '');

				$cmd = sprintf('%s -z -c "%s" -a "%s" %s -o %s %s', $tiff2pdf, $creator, $author, $title, $out, $in);
				break;
		}
		exec($cmd, $ret, $status);

		//remove original
		if ($status === 0 && !$keep_orig)
		{
			unlink($in);
		}

		return $status === 0 ? $out : $in;
	}

	/**
	 * Get info on a tiff file. Require tiffinfo
	 * @param string - absolute path to file
	 * @param string - specifc option to receive
	 *
	 * @return mixed - if $opt & exists returns a string, else bool false,
	 * otherwise an array of details
	 */
	public function fax_tiffinfo($file, $opt = '')
	{
		//ensure file exists
		if (!is_file($file))
		{
			return false;
		}

		$tiffinfo	= fpbx_which('tiffinfo');
		if (!$tiffinfo)
		{
			return false;
		}

		$cmd = sprintf('%s %s', $tiffinfo, $file);
		exec($cmd, $output);

		if ($output && str_starts_with($output[0], 'Not a TIFF or MDI file'))
		{
			return false;
		}

		$info = [];
		foreach ($output as $out)
		{
			$o = explode(':', $out, 2);
			$info[trim($o[0])] = isset($o[1]) ? trim($o[1]) : '';
		}

		if (!$info)
		{
			return false;
		}

		//special case prossesing
		//Page Number: defualt format = 0-0. Use only first set of digits, increment by 1
		$info['Page Number'] = explode('-', $info['Page Number']);
		$info['Page Number'] = $info['Page Number'][0] + 1;

		if ($opt)
		{
			return $info[$opt] ?? false;
		}

		return $info;
	}

}
