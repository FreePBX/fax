<?php
class Fax implements BMO {
	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new Exception("Not given a FreePBX Object");
		}

		$this->FreePBX = $freepbx;
		$this->db = $freepbx->Database;
	}

	public function doConfigPageInit($page) {

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

	}

	public function getSettings() {
		$settings = sql('SELECT * FROM fax_details', 'getAssoc', 'DB_FETCHMODE_ASSOC');
		foreach($settings as $setting => $value){
			$set[$setting]=$value['0'];
		}
		if(!is_array($set)){$set=array();}//never return a null value
		return $set;
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
}
