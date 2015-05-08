<?php
class Fax implements BMO {
	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new Exception("Not given a FreePBX Object");
		}

		$this->FreePBX = $freepbx;
		$this->db = $freepbx->Database;
		$this->userman = $freepbx->Userman;
	}

	public function doConfigPageInit($page) {
		$request = $_REQUEST;
		$get_vars = array(
			'ecm'				=> '',
			'fax_rx_email'		=> '',
			'force_detection'	=> 'no',
			'headerinfo'		=> '',
			'legacy_mode'		=> 'no',
			'localstationid'	=> '',
			'maxrate'			=> '',
			'minrate'			=> '',
			'modem'				=> '',
			'sender_address'	=> '',
			'papersize' 		=> 'letter',

		);
		foreach($get_vars as $k => $v){
			$fax[$k] = isset($request[$k]) ? $request[$k] : $v;
		}
		// get/put options
		if (isset($request['action']) &&  $request['action'] == 'edit'){
			fax_save_settings($fax);
		}
	}

	public function getQuickCreateDisplay() {
		return array(
			1 => array(
				array(
					'html' => load_view(__DIR__.'/views/quickCreate.php',array())
				)
			)
		);
	}

	public function usermanShowPage() {
		global $version;
		if(isset($_REQUEST['action'])) {
			$error = "";
			$faxStatus = $this->faxDetect();
			if(!$faxStatus['module'] || ($faxStatus['module'] && (!$faxStatus['ffa'] && !$faxStatus['spandsp']))){//missing modules
				$error = _('ERROR: No FAX modules detected!<br>Fax-related dialplan will <b>NOT</b> be generated.<br>This module requires Fax for Asterisk (res_fax_digium.so) or spandsp based app_fax (res_fax_spandsp.so) to function.');
			}elseif($faxStatus['ffa'] && $faxStatus['license'] < 1){//missing license
				$error = _('ERROR: No Fax license detected.<br>Fax-related dialplan will <b>NOT</b> be generated!<br>This module has detected that Fax for Asterisk is installed without a license.<br>At least one license is required (it is available for free) and must be installed.');
			}
			switch($_REQUEST['action']) {
				case 'addgroup':
				case 'showgroup':
					$enabled = ($_REQUEST['action'] == "addgroup") ? true : $this->userman->getModuleSettingByGID($_REQUEST['group'],'fax','enabled');
					$attachformat = ($_REQUEST['action'] == "addgroup") ? 'pdf' : $this->userman->getModuleSettingByGID($_REQUEST['group'],'fax','attachformat');
					return array(
						array(
							"title" => _("Fax"),
							"rawname" => "fax",
							"content" => load_view(__DIR__.'/views/fax.php',array("mode" => "group", "error" => $error, "enabled" => $enabled, "attachformat" => $attachformat))
						)
					);
				break;
				case 'adduser':
				case 'showuser':
					$user = $this->userman->getUserByID($_REQUEST['user']);
					if(!empty($user) && $user['default_extension'] !== "none") {
						$fax = $this->getUser($user['default_extension']);
						$enabled = $this->userman->getModuleSettingByID($_REQUEST['user'],'fax','enabled',true);
						$attachformat = $this->userman->getModuleSettingByID($_REQUEST['user'],'fax','attachformat',true);
						return array(
							array(
								"title" => _("Fax"),
								"rawname" => "fax",
								"content" => load_view(__DIR__.'/views/fax.php',array("mode" => "user", "error" => $error, "enabled" => $enabled, "attachformat" => $attachformat))
							)
						);
					}
				break;
			}
			return array();
		}
	}

	public function usermanDelGroup($id,$display,$data) {
	}

	public function usermanAddGroup($id, $display, $data) {
		$this->usermanUpdateGroup($id,$display,$data);
	}

	public function usermanUpdateGroup($id,$display,$data) {
		if($_POST['faxenabled'] == "true") {
			$this->userman->setModuleSettingByGID($id,'fax','enabled',true);
			$this->userman->setModuleSettingByGID($id,'fax','attachformat',$_POST['faxattachformat']);
		} else {
			$this->userman->setModuleSettingByGID($id,'fax','enabled',false);
			$this->userman->setModuleSettingByGID($id,'fax','attachformat',"pdf");
		}
		$group = $this->userman->getGroupByGID($id);
		foreach($group['users'] as $user) {
			$enabled = $this->userman->getCombinedModuleSettingByID($user, 'fax', 'enabled');
			$attachformat = $this->userman->getCombinedModuleSettingByID($user, 'fax', 'attachformat');
			$userData = $this->userman->getUserByID($user);
			if($userData['default_extension'] !== "none" && $display == "userman") {
				$this->saveUser($userData['default_extension'],($enabled ? "true" : "false"),$userData['email'],$attachformat);
			}
		}
	}

	/**
	 * Hook functionality from userman when a user is deleted
	 * @param {int} $id      The userman user id
	 * @param {string} $display The display page name where this was executed
	 * @param {array} $data    Array of data to be able to use
	 */
	public function usermanDelUser($id, $display, $data) {
		$user = $this->FreePBX->Userman->getUserByID($id);
		if($user['default_extension'] !== "none") {
			$this->deleteUser($user['default_extension']);
		}
	}

	/**
	 * Hook functionality from userman when a user is added
	 * @param {int} $id      The userman user id
	 * @param {string} $display The display page name where this was executed
	 * @param {array} $data    Array of data to be able to use
	 */
	public function usermanAddUser($id, $display, $data) {
		if($_POST['faxenabled'] == "true") {
			$this->userman->setModuleSettingByID($id,'fax','enabled',true);
			$this->userman->setModuleSettingByID($id,'fax','attachformat',$_POST['faxattachformat']);
		} elseif($_POST['faxenabled'] == "false") {
			$this->userman->setModuleSettingByID($id,'fax','enabled',false);
		} else {
			$this->userman->setModuleSettingByID($id,'fax','enabled',null);
		}
		$enabled = $this->userman->getCombinedModuleSettingByID($id, 'fax', 'enabled');
		$attachformat = $this->userman->getCombinedModuleSettingByID($id, 'fax', 'attachformat');

		$user = $this->FreePBX->Userman->getUserByID($id);
		if($user['default_extension'] !== "none" && $display == "userman" && isset($_POST['faxenabled'])) {
			$this->saveUser($user['default_extension'],($enabled ? "true" : "false"),$user['email'],$attachformat);
		}
	}

	/**
	 * Hook functionality from userman when a user is updated
	 * @param {int} $id      The userman user id
	 * @param {string} $display The display page name where this was executed
	 * @param {array} $data    Array of data to be able to use
	 */
	public function usermanUpdateUser($id, $display, $data) {
		if($_POST['faxenabled'] == "true") {
			$this->userman->setModuleSettingByID($id,'fax','enabled',true);
			$this->userman->setModuleSettingByID($id,'fax','attachformat',$_POST['faxattachformat']);
		} elseif($_POST['faxenabled'] == "false") {
			$this->userman->setModuleSettingByID($id,'fax','enabled',false);
		} else {
			$this->userman->setModuleSettingByID($id,'fax','enabled',null);
		}
		$enabled = $this->userman->getCombinedModuleSettingByID($id, 'fax', 'enabled');
		$attachformat = $this->userman->getCombinedModuleSettingByID($id, 'attachformat', 'enabled');

		$user = $this->FreePBX->Userman->getUserByID($id);
		if($user['default_extension'] !== "none" && $display == "userman" && isset($_POST['faxenabled'])) {
			$this->saveUser($user['default_extension'],($enabled ? "true" : "false"),$user['email'],$attachformat);
		}
	}

	/**
	* Quick Create hook
	* @param string $tech      The device tech
	* @param int $extension The extension number
	* @param array $data      The associated data
	*/
	public function processQuickCreate($tech, $extension, $data) {
		if($data['faxenabled'] == "yes") {
			$this->saveUser($extension, "true", $data['email'], "pdf");
		}
	}

	public function install() {

	}
	public function uninstall() {

	}
	public function backup(){

	}
	public function restore($backup){

	}
	public function genConfig() {
		global $version;
		$conf = array();

		$fax = $this->faxDetect();
		$ast_lt_18 = version_compare($version, '1.8', 'lt');
		if($fax['module'] && ($ast_lt_18 || $fax['ffa'] || $fax['spandsp'])){ //dont continue unless we have a fax module in asterisk

			$settings = $this->getSettings();
			$conf['res_fax.conf']['general'][] = "#include res_fax_custom.conf";
			if(!empty($settings['minrate'])) {
				$conf['res_fax.conf']['general']['minrate'] = $settings['minrate'];
			}
			if(!empty($settings['maxrate'])) {
				$conf['res_fax.conf']['general']['maxrate'] = $settings['maxrate'];
			}

			$conf['res_fax_digium.conf']['general'][] = "#include res_fax_digium_custom.conf";
			if(!empty($settings['ecm'])) {
				$conf['res_fax_digium.conf']['general']['ecm'] = $settings['ecm'];
			}
		}

		return $conf;
	}
	public function writeConfig($conf){
		$this->FreePBX->WriteConfig($conf);
	}

	public function getSettings() {
		$settings = sql('SELECT * FROM fax_details', 'getAssoc', 'DB_FETCHMODE_ASSOC');
		foreach($settings as $setting => $value){
			$set[$setting]=$value['0'];
		}
		if(!is_array($set)){$set=array();}//never return a null value
		return $set;
	}

	public function deleteUser($faxext) {
		$sth = $this->db->prepare('DELETE FROM fax_users where user = ?');
		$sth->execute(array($faxext));
	}

	public function saveUser($faxext,$faxenabled,$faxemail = '',$faxattachformat = 'pdf') {
		$sth = $this->db->prepare('REPLACE INTO fax_users (user, faxenabled, faxemail, faxattachformat) VALUES (?, ?, ?, ?)');
		try {
			$sth->execute(array($faxext, $faxenabled, $faxemail, $faxattachformat));
		} catch(\Exception $e) {
			return false;
		}
		return true;
	}

	public function getUser($user) {
		$sth = $this->db->prepare('SELECT * FROM fax_users WHERE user = ?');
		$sth->execute(array($user));
		$out = $sth->fetchAll(PDO::FETCH_ASSOC);
		return (!empty($out[0]) && $out[0]['faxenabled']) ? $out[0] : false;
	}

	public function faxDetect() {
		$fax=null;
		$appfax = $receivefax = false;//return false by default in case asterisk isnt reachable
		if (isset($this->FreePBX->astman) && $this->FreePBX->astman->connected()) {
			//check for fax modules
			switch(true) {
				case $this->FreePBX->astman->mod_loaded('res_fax.so'):
					$fax['module']='res_fax';
				break;
				case $this->FreePBX->astman->mod_loaded('app_fax.so'):
					$fax['module']='app_fax';
				break;
				case $this->FreePBX->astman->mod_loaded('app_rxfax.so'):
					$fax['module']='app_rxfax';
				break;
				default:
					$fax['module'] = null;
				break;
			}

			$fax['nvfax'] = $this->FreePBX->astman->mod_loaded('app_nv_faxdetect.so');
			$fax['ffa'] = $this->FreePBX->astman->mod_loaded('res_fax_digium.so');

			if ($fax['ffa']) {
				$fax['spandsp'] = false;
			} else {
				$fax['spandsp'] = $this->FreePBX->astman->mod_loaded('res_fax_spandsp.so');
			}

			switch($fax['module']) {
				case 'res_fax':
					$fax['receivefax'] = 'receivefax';
				break;
				case 'app_rxfax':
					$fax['receivefax'] = 'rxfax';
				break;
				case 'app_fax':
					switch(true) {
						case $this->FreePBX->astman->app_exists('receivefax'):
							$fax['receivefax'] = 'receivefax';
						break;
						case $this->FreePBX->astman->app_exists('rxfax'):
							$fax['receivefax'] = 'rxfax';
						break;
						default:
							$fax['receivefax'] = 'none';
						break;
					}
				break;
				default:
					$fax['receivefax'] = 'none';
				break;
			}

			//get license count
			$lic = $this->FreePBX->astman->send_request('Command', array('Command' => 'fax show stats'));
			foreach(explode("\n",$lic['data']) as $licdata){
				$d = explode(':',$licdata);
				$data[trim($d['0'])] = isset($d['1']) ? trim($d['1']) : null;
			}
			$fax['license'] = isset($data['Licensed Channels']) ? $data['Licensed Channels'] : '';
		}
		return $fax;
	}
	public function getActionBar($request) {
		switch ($request['display']) {
			case 'fax':
				$buttons = array(
						'submit' => array(
							'name' => 'submit',
							'id' => 'submit',
							'value' => _("Submit")
						),
						'reset' => array(
							'name' => 'reset',
							'id' => 'reset',
							'value' => _("Reset")
						),
					);
				return $buttons;
			break;
		}
	}
}
