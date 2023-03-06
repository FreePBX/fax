<?php
namespace FreePBX\modules;
use FreePBX_Helpers;
use BMO;

class Fax extends FreePBX_Helpers implements BMO
{
	const ASTERISK_SECTION = 'ext-fax';

	private $FreePBX;
	private $db;
	private $astman;
	private $userman;
	private $config;

	public $tables = array(
		'users' 		=> 'fax_users',
		'incoming' 		=> 'fax_incoming',
		'details' 		=> 'fax_details',
		'incoming_core'	=> 'incoming',
	);

	public $default_settings = array(
		'ecm' => array(
			'default' => 'no',
			'type' 	  => 'yesno',
		),
		'fax_rx_email' => array(
			'default' => 'yes',
			'type' 	  => 'email',
		),
		'force_detection' => array(
			'default' => 'no',
			'type' 	  => 'yesno',
		),
		'headerinfo' => array(
			'default' => '',
			'type' 	  => 'text',
			// texto
		),
		'legacy_mode' => array(
			'default' => 'no',
			'type' 	  => 'yesno',
		),
		'localstationid' => array(
			'default' => '',
			'type' 	  => 'text',
		),
		'maxrate' => array(
			'default' => '14400',
			'type' 	  => 'numeric',
		),
		'minrate' => array(
			'default' => '9600',
			'type' 	  => 'numeric',
		),
		'modem'	=> array(
			//TODO: It is not used anywhere, can it be deleted?
			'default' => '',
			'type' 	  => 'text',
		),
		'sender_address' => array(
			'default' => '',
			'type' 	  => 'text',	// email or name and email => Fax <fax@example.com>
		),
		'papersize' => array(
			'default' => 'letter',
			'type' 	  => 'list',
			'options' => array('latter', 'a4'),
		),
	);
	
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
		return array("did");
	}

	public function doConfigPageInit($page)
	{
		$request = freepbxGetSanitizedRequest();
		switch ($page)
		{
			case 'fax':
				foreach($this->default_settings as $k => $v)
				{
					$input = isset($request[$k]) ? $request[$k] : $v['default'];
					$input = trim($input);

					switch ($v['type'])
					{
						case 'yesno':
							$input = strtolower($input);
							if (! in_array($input, array('yes', 'no')) )
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
				$action			= isset($request['action']) 			? $request['action']			: '';
				$cidnum			= isset($request['cidnum']) 			? $request['cidnum']			: '';
				$extension		= isset($request['extension'])			? $request['extension']			: '';
				$extdisplay		= isset($request['extdisplay'])			? $request['extdisplay']		: '';
				$enabled		= isset($request['faxenabled'])			? $request['faxenabled']		: 'false';
				$detection		= isset($request['faxdetection'])		? $request['faxdetection']		: '';
				$ring			= isset($request['faxring'])			? $request['faxring']			: '';
				$detectionwait	= isset($request['faxdetectionwait'])	? $request['faxdetectionwait']	: '';
				$dest			= (isset($request['gotoFAX'])			? $request['gotoFAX'].'FAX'		: null);
				$dest			= isset($request[$dest])				? $request[$dest]				: '';
				if ($enabled != 'legacy')
				{
					$legacy_email = null;
				}
				else
				{
					$legacy_email = isset($request['legacy_email']) ? $request['legacy_email'] : '';
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
					$data_return[] = array(
						"title"   => _("Fax"),
						"rawname" => "fax",
						"content" => $this->showPage('userman_showpage', array("mode" => "group", "error" => $error, "enabled" => $enabled, "attachformat" => $attachformat))
					);
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
					$data_return[] = array(
						"title"   => _("Fax"),
						"rawname" => "fax",
						"content" => $this->showPage('userman_showpage', array("mode" => "user", "error" => $error, "enabled" => $enabled, "attachformat" => $attachformat))
					);
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
		$set = array();
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

	public function showPage($page, $params = array())
	{
		$request = $_REQUEST;
		$data = array(
			"fax" 	  => $this,
			'request' => $request,
			'page' 	  => $page,
		);
		$data = array_merge($data, $params);
		switch ($page) 
		{
			case 'main':
				$data_return = load_view(__DIR__."/views/page.main.php", $data);
				break;

			case 'form_options':
				$data_return = load_view(__DIR__."/views/view.form_options.php", $data);
				break;

			case 'core_DIDHook':
				$data_return = load_view(__DIR__."/views/view.coreDIDHook.php", $data);
				break;

			case 'userman_showpage':
				$data_return = load_view(__DIR__.'/views/view.userman.showpage.php', $data);
				break;

			default:
				$data_return = sprintf(_("Page Not Found (%s)!!!!"), $page);
		}
		return $data_return;
	}

	public function genConfig()
	{
		global $version;
		$conf = array();

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
			$key 	  = strtolower($key);
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
		$set 	  = array();
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
				$sth->execute(array($key, $value));
				// $db->escapeSimple($value)
			}
			needreload();
		}
	}

	public function deleteUser($faxext)
	{
		$sql = sprintf('DELETE FROM %s where user = ?', $this->tables['users']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($faxext));
	}

	public function saveUser($faxext, $faxenabled, $faxemail = '', $faxattachformat = 'pdf')
	{
		$sql = sprintf('REPLACE INTO %s (user, faxenabled, faxemail, faxattachformat) VALUES (?, ?, ?, ?)', $this->tables['users']);
		$sth = $this->db->prepare($sql);
		try
		{
			$sth->execute(array($faxext, $faxenabled, $faxemail, $faxattachformat));
		}
		catch(\Exception $e)
		{
			return false;
		}
		return true;
	}

	public function getUser($user)
	{
		$sql = sprintf('SELECT * FROM %s WHERE user = ?', $this->tables['users']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($user));
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
			$fax = array();
			//check for fax modules
			switch(true)
			{
				case $this->astman->mod_loaded('res_fax.so'):
					$fax['module'] = 'res_fax';
				break;
				default:
					$fax['module'] = null;
				break;
			}

			$fax['spandsp'] = $this->astman->mod_loaded('res_fax_spandsp.so');

			switch($fax['module'])
			{
				case 'res_fax':
					$fax['receivefax'] = 'receivefax';
				break;
				default:
					$fax['receivefax'] = 'none';
				break;
			}

			//get license count
			$lic = $this->astman->send_request('Command', array('Command' => 'fax show stats'));
			foreach(explode("\n",$lic['data']) as $licdata)
			{
				$d = explode(':',$licdata);
				$data[trim($d['0'])] = isset($d['1']) ? trim($d['1']) : null;
			}
			$fax['license'] = isset($data['Licensed Channels']) ? $data['Licensed Channels'] : '';
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
        $legacy_email = $legacy_email === null ? 'NULL' : $legacy_email;
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
        $opts = explode('/', $extdisplay);
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
		$data_return = array();
        if ($request['display'] === 'fax')
		{
            $data_return = array(
				'submit' => array(
					'name' => 'submit',
					'id' => 'submit',
					'value' => _("Submit")
				),
				'reset' => array(
					'name' => 'reset',
					'id' => 'reset',
					'value' => _("Reset"),
				),
            );
        }
		return $data_return;
	}

	public function coreDIDHook($page)
	{
		$request = $_REQUEST;
		if($page == 'did')
		{
			$target_menuid 	= $page;
			$extension		= isset($request['extension'])	? $request['extension']	:'';
			$cidnum			= isset($request['cidnum'])		? $request['cidnum']	:'';
			$extdisplay		= isset($request['extdisplay'])	? $request['extdisplay']:'';

			//if were editing, get save parms. Get parms

			if(!$extension && !$cidnum)	//set $extension,$cidnum if we dont already have them
			{
				if ($extdisplay)
				{
					$opts		= explode('/', $extdisplay);
					$extension	= $opts['0'];
					$cidnum		= isset($opts['1']) ? $opts['1'] : '';
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
				$data = array(
					'fax_dahdi_faxdetect' => fax_dahdi_faxdetect(),
		    		'fax_sip_faxdetect'	  => fax_sip_faxdetect(),
		    		'dahdi'				  => ast_with_dahdi() ? _('Dahdi'): _('Zaptel'),
		    		'fax_detect'		  => $this->faxDetect(),
		    		'fax_settings'		  => $this->getSettings(),
					'info'				  => engine_getinfo(),
					'faxing' 			  => !empty($fax),
					'fax_incoming' 		  => $fax,
				);
				$html = $this->showPage('core_DIDHook', $data);
			}
			$ret = array();
			$ret[] = array(
				'title' => _("Fax"),
				'rawname' => 'fax',
				'content' => $html,
			);
			return $ret;
		}
	}

	public function bulkhandlerGetHeaders($type)
	{
        if($type === 'dids')
		{
			return array(
                'fax_enable' => array(
                    'identifier' => _('Fax Enabled'),
                    'description' => _('Fax Enabled'),
                ),
                'fax_detection' => array(
                    'identifier' => _('Fax Detection'),
                    'description' => _('Type of fax detection to use (e.g. SIP or DAHDI)'),
                ),
                'fax_detectionwait' => array(
                    'identifier' => _('Fax Detection Wait'),
                    'description' => _('How long to wait and try to detect fax'),
                ),
                'fax_destination' => array(
                    'identifier' => _('Fax Destination'),
                    'description' => _('Where to send the faxes'),
                ),
            );
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
					$data[$user['id']] = array(
						'fax_enabled' 		=> is_null($en) ? "inherit" : (empty($en) ? 'no' : 'yes'),
						'fax_attachformat'	=> $this->userman->getModuleSettingByID($user['id'], 'fax', 'attachformat'),
					);
				}
			break;
			case 'usermangroups':
				$groups = $this->userman->getAllGroups();
				foreach ($groups as $group)
				{
					$en = $this->userman->getModuleSettingByGID($group['id'],'fax','enabled');
					$data[$group['id']] = array(
						'fax_enabled' 		=> empty($en) ? 'no' : 'yes',
						'fax_attachformat' 	=> $this->userman->getModuleSettingByGID($group['id'],'fax','attachformat'),
					);
				}
			break;
			case "dids":
				$dids = $this->FreePBX->Core->getAllDIDs();
				$data = array();
				$this->FreePBX->Modules->loadFunctionsInc("fax");
				foreach($dids as $did)
				{
					$key = sprintf("%s/%s", $did['extension'], $did["cidnum"]);
					$fax = $this->getIncoming($did['extension'], $did["cidnum"]);
					if(!empty($fax))
					{
						$data[$key] = array(
							"fax_enable" 		=> "yes",
							"fax_detection" 	=> $fax['detection'],
							"fax_detectionwait" => $fax['detectionwait'],
							"fax_destination" 	=> $fax['destination']
						);
					}
					else
					{
						$data[$key] = array(
							"fax_enable" 		=> "",
							"fax_detection" 	=> "",
							"fax_detectionwait" => "",
							"fax_destination" 	=> ""
						);
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
					$settings = array();
					foreach ($data as $key => $value)
					{
						if (substr($key, 0, 4) == 'fax_')
						{
							$settingname = substr($key, 4);
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
		$files = array();
		$files[] = array(
			'type' => 'file',
			'path' => sprintf('%s/fax2mail.php', $modulebindir),
			'perms' => 0755
		);
		return $files;
	}




	/**
	 * Dialplan hooks
	 */
	public function myDialplanHooks()
	{
		return 220;
	}
	public function doDialplanHook(&$ext, $engine, $priority)
	{
		global $core_conf;

		if ($engine != "asterisk") { return; }

		$fax = $this->faxDetect();
		$this->astman->database_deltree("FAX");


		///function fax_get_config($engine)
		// do not continue unless we have a fax module in asterisk
		if($fax['module'] && ((isset($fax['ffa']) && $fax['ffa']) || $fax['spandsp']))
		{
			$t38_fb  = ',f';
			$context = self::ASTERISK_SECTION;
			$dests	 = $this->get_destinations();
	
			if($dests)
			{
				foreach ($dests as $row)
				{
					$exten = $row['user'];
					$user  = $this->userman->getUserByID($exten);
					if(!empty($user))
					{
						$name = !empty($user['displayname']) ? $user['displayname'] : trim(sprintf("%s %s", $user['fname'],$user['lname']));
						$name = !empty($name) 				 ? $name : $user['username'];

						$ext->add($context, $exten, '', new \ext_set('FAX_FOR', sprintf('%s (%s)', $name, $exten)));
					}
					else
					{
						$ext->add($context, $exten, '', new \ext_set('FAX_FOR', $exten));
					}
	
					$ext->add($context, $exten, '', new \ext_noop('Receiving Fax for: ${FAX_FOR}, From: ${CALLERID(all)}'));
					$ext->add($context, $exten, '', new \ext_set('FAX_RX_USER', $exten));
					$ext->add($context, $exten, '', new \ext_set('FAX_RX_EMAIL_LEN', strlen($row['faxemail'])));
					$ext->add($context, $exten, 'receivefax', new \ext_goto('receivefax','s'));
				}
			}
			/*
			 FAX Failures are not handled well as of this coding
			 in by ReceiveFAX.  If there is a license available
			 then it provides information. If not, nothing is
			 provided. FAXSTATUS is supported in 1.4 to handle
			 legacy with RxFax(). In order to create dialplan
			 to try and handle all cases, we use FAXSTATUS and
			 set it ourselves as needed. It appears that if a
			 fax fails with ReceiveFAX we can always continue
			 execution and if it succeeds, then execution goes
			 to hangup. So using that information we try to
			 trap and report on all cases.
			*/
	
			$exten = 's';
			$ext->add($context, $exten, '', new \ext_macro('user-callerid')); // $cmd,n,Macro(user-callerid)
			$ext->add($context, $exten, '', new \ext_noop('Receiving Fax for: ${FAX_FOR} , From: ${CALLERID(all)}'));
			$ext->add($context, $exten, 'receivefax', new \ext_stopplaytones(''));
			switch ($fax['module'])
			{
				case 'app_rxfax':
					$ext->add($context, $exten, '', new \ext_rxfax('${ASTSPOOLDIR}/fax/${UNIQUEID}.tif')); //receive fax, then email it on
				break;
				case 'app_fax':
					// $fax['receivefax'] should be rxfax or receivefax, it could be none in which case we don't know. We'll just make it
					// ReceiveFAX in that case since it will fail anyhow.
					if ($fax['receivefax'] == 'rxfax')
					{
						$ext->add($context, $exten, '', new \ext_rxfax('${ASTSPOOLDIR}/fax/${UNIQUEID}.tif')); //receive fax, then email it on
					}
					elseif ($fax['receivefax'] == 'receivefax')
					{
						$ext->add($context, $exten, '', new \ext_receivefax('${ASTSPOOLDIR}/fax/${UNIQUEID}.tif'.$t38_fb)); //receive fax, then email it on
					}
					else
					{
						$ext->add($context, $exten, '', new \ext_noop('ERROR: NO Receive FAX application detected, putting in dialplan for ReceiveFAX as default'));
						$ext->add($context, $exten, '', new \ext_receivefax('${ASTSPOOLDIR}/fax/${UNIQUEID}.tif'.$t38_fb)); //receive fax, then email it on
						$ext->add($context, $exten, '', new \ext_execif('$["${FAXSTATUS}" = ""]','Set','FAXSTATUS=${IF($["${FAXOPT(error)}" = ""]?"FAILED LICENSE EXCEEDED":"FAILED FAXOPT: error: ${FAXOPT(error)} status: ${FAXOPT(status)} statusstr: ${FAXOPT(statusstr)}")}'));
					}
				break;
				case 'res_fax':
					$localstationid = $this->getSetting('localstationid');
					if(!empty($localstationid))
					{
						$ext->add($context, $exten, '', new \ext_set('FAXOPT(localstationid)', $localstationid));
					}
					$ext->add($context, $exten, '', new \ext_receivefax('${ASTSPOOLDIR}/fax/${UNIQUEID}.tif'.$t38_fb)); //receive fax, then email it on
					if (isset($fax['ffa']) && $fax['ffa'])
					{
						$ext->add($context, $exten, '', new \ext_execif('$["${FAXSTATUS}"="" | "${FAXSTATUS}" = "FAILED" & "${FAXERROR}" = "INIT_ERROR"]','Set','FAXSTATUS=FAILED LICENSE MAY BE EXCEEDED check log errors'));
					}
					$ext->add($context, $exten, '', new \ext_execif('$["${FAXSTATUS:0:6}"="FAILED" && "${FAXERROR}"!="INIT_ERROR"]','Set','FAXSTATUS="FAILED: error: ${FAXERROR} statusstr: ${FAXOPT(statusstr)}"'));
		
					$ext->add($context, $exten, '', new \ext_hangup());
				break;
				default: // unknown
					$ext->add($context, $exten, '', new \ext_noop('No Known FAX Technology installed to receive a fax, aborting'));
					$ext->add($context, $exten, '', new \ext_set('FAXSTATUS','FAILED No Known Fax Reception Apps available to process'));
					$ext->add($context, $exten, '', new \ext_hangup());
			}
			$exten = 'h';
	
			// if there is a file there, mail it even if we failed:
			$ext->add($context, $exten, '', new \ext_gotoif('$[${STAT(e,${ASTSPOOLDIR}/fax/${UNIQUEID}.tif)} = 0]','failed'));
			$ext->add($context, $exten, '', new \ext_noop_trace('PROCESSING FAX with status: [${FAXSTATUS}] for: [${FAX_FOR}], From: [${CALLERID(all)}]'));
			//delete is a variable so that other modules can prevent it should then need to prosses the file further
			$ext->add($context, $exten, 'delete_opt', new \ext_set('DELETE_AFTER_SEND', 'true'));
			$ext->add($context, $exten, 'process', new \ext_gotoif('$[ "${FAX_RX_EMAIL_LEN}" = "0" | "${FAX_RX_EMAIL_LEN}" = "" ]','noemail'));
	
			$ext->add($context, $exten, 'sendfax', new \ext_system('${AMPBIN}/fax2mail.php --remotestationid "${FAXOPT(remotestationid)}" --user "${FAX_RX_USER}" --dest "${FROM_DID}" --callerid "${BASE64_ENCODE(${CALLERID(all)})}" --file ${ASTSPOOLDIR}/fax/${UNIQUEID}.tif --delete "${DELETE_AFTER_SEND}"'));
	
			$ext->add($context, $exten, 'end', new \ext_macro('hangupcall'));
	
			$ext->add($context, $exten, 'noemail', new \ext_noop('ERROR: No Email Address to send FAX: status: [${FAXSTATUS}],  From: [${CALLERID(all)}], trying system fax destination'));
			$ext->add($context, $exten, '', new \ext_gotoif('$[ "${FAX_RX_EMAIL}" = "" ]', 'delfax'));
	
			// We can send a fax to the system dest!
			$ext->add($context, $exten, '', new \ext_system('${AMPBIN}/fax2mail.php --remotestationid "${FAXOPT(remotestationid)}" --sendto "${FAX_RX_EMAIL}" --dest "${FROM_DID}" --callerid "${BASE64_ENCODE(${CALLERID(all)})}" --file ${ASTSPOOLDIR}/fax/${UNIQUEID}.tif --delete "${DELETE_AFTER_SEND}"'));
			$ext->add($context, $exten, '', new \ext_macro('hangupcall'));
	
			// No system dest. Just delete.
			$ext->add($context, $exten, 'delfax', new \ext_system('${AMPBIN}/fax2mail.php --file ${ASTSPOOLDIR}/fax/${UNIQUEID}.tif --delete "${DELETE_AFTER_SEND}"'));
			$ext->add($context, $exten, '', new \ext_macro('hangupcall'));
	
			$ext->add($context, $exten, 'failed', new \ext_noop('FAX ${FAXSTATUS} for: ${FAX_FOR} , From: ${CALLERID(all)}'),'process',101);
			$ext->add($context, $exten, '', new \ext_macro('hangupcall'));
	
			$modulename = 'fax';
			$fcc = new \featurecode($modulename, 'simu_fax');
			$fc_simu_fax = $fcc->getCodeActive();
			unset($fcc);
	
			if ($fc_simu_fax != '')
			{
				$ext->addInclude('from-internal-additional', 'app-fax'); // Add the include from from-internal
				$ext->add('app-fax', $fc_simu_fax, '', new \ext_setvar('FAX_RX_EMAIL', $this->getSetting('fax_rx_email', '')));
				$ext->add('app-fax', $fc_simu_fax, '', new \ext_goto('1', 's', self::ASTERISK_SECTION));
				$ext->add('app-fax', 'h', '', new \ext_macro('hangupcall'));
			}
			// This is not really needed but is put here in
			// case some ever accidently switches the order below
			// when checking for this setting since $fax['module']
			// will be set there and the 2nd part never checked
			$fax_settings['force_detection'] = 'yes';
		}
		else
		{
			$fax_settings = $this->getSettings();
		}

		if (($fax['module'] && ((isset($fax['ffa']) && $fax['ffa']) || $fax['spandsp'])) || $fax_settings['force_detection'] == 'yes')
		{
			//TODO: review this thoroughly as it doesn't make sense
			if (isset($core_conf) && is_a($core_conf, "core_conf"))
			{
				$core_conf->addSipGeneral('faxdetect','no');
			}
			else if (isset($core_conf) && is_a($core_conf, "core_conf"))
			{
				$core_conf->addSipGeneral('faxdetect','yes');
			}
	
			$ext->add('ext-did-0001', 'fax', '', new \ext_setvar('__DIRECTION',($this->config->get('INBOUND_NOTRANS') ? 'INBOUND' : '')));
			$ext->add('ext-did-0001', 'fax', '', new \ext_goto('${CUT(FAX_DEST,^,1)},${CUT(FAX_DEST,^,2)},${CUT(FAX_DEST,^,3)}'));
			$ext->add('ext-did-0002', 'fax', '', new \ext_setvar('__DIRECTION',($this->config->get('INBOUND_NOTRANS') ? 'INBOUND' : '')));
			$ext->add('ext-did-0002', 'fax', '', new \ext_goto('${CUT(FAX_DEST,^,1)},${CUT(FAX_DEST,^,2)},${CUT(FAX_DEST,^,3)}'));
	
			// Add fax extension to ivr and announcement as inbound controle may be passed quickly to them and still detection is desired
			if ($this->FreePBX->Modules->checkStatus("ivr"))
			{
				$ivrlist = $this->FreePBX->Ivr->getDetails();
				if(is_array($ivrlist))
				{
					foreach($ivrlist as $item)
					{
						$ext->add("ivr-".$item['id'], 'fax', '', new \ext_goto('${CUT(FAX_DEST,^,1)},${CUT(FAX_DEST,^,2)},${CUT(FAX_DEST,^,3)}'));
					}
				}
				unset($ivrlist);
			}
			if (function_exists('announcement_list'))
			{
				foreach (announcement_list() as $row)
				{
					$ext->add('app-announcement-'.$row['announcement_id'], 'fax', '', new \ext_goto('${CUT(FAX_DEST,^,1)},${CUT(FAX_DEST,^,2)},${CUT(FAX_DEST,^,3)}'));
				}
			}
		}


		// function fax_hookGet_config($engine){}
		if($fax_settings['force_detection'] == 'yes')	//dont continue unless we have a fax module in asteriskd
		{
			foreach($this->getIncoming() as $current => $route)
			{
				if(isset($route['legacy_email']) && $route['legacy_email'] === 'NULL')
				{
					$route['legacy_email'] = null;
				}

				if($route['extension']=='' && $route['cidnum'])		//callerID only
				{
					$extension = sprintf('s/%s', $route['cidnum']);
					$context = ($route['pricid'] == 'CHECKED') ? 'ext-did-0001' : 'ext-did-0002';
				}
				else
				{
					if(($route['extension'] && $route['cidnum'])||($route['extension']=='' && $route['cidnum']==''))	//callerid+did / any/any
					{
						$context='ext-did-0001';
					}
					else	//did only
					{
						$context='ext-did-0002';
					}
					$extension = ($route['extension'] != '' ? $route['extension'] : 's').($route['cidnum'] == ''? '' : sprintf('/%s',$route['cidnum']));
				}
				if ($route['legacy_email'] === null)
				{
					$ext->splice($context, $extension, 'dest-ext', new \ext_setvar('FAX_DEST', str_replace(',','^', $route['destination'])));
				}
				else
				{
					$ext->splice($context, $extension, 'dest-ext', new \ext_setvar('FAX_DEST','ext-fax^s^1'));
					if ($route['legacy_email'])
					{
						$fax_rx_email = $route['legacy_email'];
					}
					else
					{
						$fax_rx_email = $this->getSetting('fax_rx_email', '');
					}
					$ext->splice($context, $extension, 'dest-ext', new \ext_setvar('FAX_RX_EMAIL', $fax_rx_email));
				}

				//If we have fax incoming, we need to set fax detection to yes if we are on Asterisk 11 or newer
				$ext->splice($context, $extension, 'dest-ext', new \ext_setvar('FAXOPT(faxdetect)', 'yes'));
				$ext->splice($context, $extension, 'dest-ext', new \ext_answer(''));
				if(!empty($route['ring']))
				{
					$ext->splice($context, $extension, 'dest-ext', new \ext_playtones('ring'));
				}

				$ext->splice($context, $extension, 'dest-ext', new \ext_wait($route['detectionwait']));
			}
		}
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
		$extens = array();
		$recip  = $this->get_destinations();
		usort($recip, function($a,$b){ return ($a['uname'] < $b['uname']) ? -1 : 1;});
		foreach ($recip as $row)
		{
			$extens[] = array(
				'destination' => $this->getDest($row['user']),
				'description' => sprintf("%s (%s)", $row['name'], $row['uname']),
				'category' 	  => _('Fax Recipient'),
			);
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
	
		$destlist = array();
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
			$destlist[] = array(
				'dest' 		  => $thisdest,
				'description' => sprintf(_("Inbound Fax Detection: %s (%s)"), $result['description'], $thisid),
				'edit_url' 	  => 'config.php?display=userman&action=showuser&user='.urlencode($thisid),
			);
		}
		return $destlist;
	}

	public function destinations_change($old_dest, $new_dest)
	{
		$sql = sprintf('UPDATE %s SET destination = ? WHERE destination = ?', $this->tables['incoming']);
		$sth = $this->db->prepare($sql);
		$sth->execute(array($new_dest, $old_dest));
	}

	public function destinations_getdestinfo($dest)
	{
		$srt_section = sprintf("%s,", self::ASTERISK_SECTION);
		if (substr(trim($dest),0, strlen($srt_section)) == $srt_section)
		{
			$usr = explode(',', $dest);
			$usr = $usr[1];
			$thisusr = $this->getUser($usr);
			if (! empty($thisusr))
			{
				return array(
					'description' => sprintf(_("Fax user %s"), $usr),
					'edit_url' 	  => 'config.php?display=userman&action=showuser&user='.urlencode($usr),
				);
			}
			return array();
		}
		return false;
	}

	public function destinations_identif($dests)
	{
		if (! is_array($dests)) {
			$dests = array($dests);
		}
		$return_data = array();
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
		$final = array();
		$warning = array();
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
				$res['name']  = trim($res['name']);
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
	public function fax_file_convert($type, $in, $out = '', $keep_orig = false, $opts = array())
	{
		//ensure file exists
		if (! is_file($in))
		{
			return false;
		}

		//check is format supported
		if (! in_array($type, array('pdf2tif', 'tif2pdf', 'ps2tif')) )
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
			$pathinfo = pathinfo($in);

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
				$res = isset($opts['res']) ? $opts['res'] : "204x98";
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

		if ($output && strpos($output[0], 'Not a TIFF or MDI file') === 0)
		{
			return false;
		}

		$info = array();
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
			return isset($info[$opt]) ? $info[$opt] : false;
		}

		return $info;
	}

}
