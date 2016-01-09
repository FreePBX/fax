<?php
class Fax extends \FreePBX_Helpers implements \BMO {
	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new Exception("Not given a FreePBX Object");
		}
		$this->FreePBX = $freepbx;
		$this->db = $freepbx->Database;
		$this->userman = $freepbx->Userman;
	}
	public static function myConfigPageInits() { return array("did"); }
	public function doConfigPageInit($page) {
		switch ($page) {
			case 'fax':
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
			break;
			case "did":
				$action=isset($_REQUEST['action'])?$_REQUEST['action']:'';
				$cidnum=isset($_REQUEST['cidnum'])?$_REQUEST['cidnum']:'';
				$extension=isset($_REQUEST['extension'])?$_REQUEST['extension']:'';
				$extdisplay=isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:'';
				$enabled=isset($_REQUEST['faxenabled'])?$_REQUEST['faxenabled']:'false';
				$detection=isset($_REQUEST['faxdetection'])?$_REQUEST['faxdetection']:'';
				$detectionwait=isset($_REQUEST['faxdetectionwait'])?$_REQUEST['faxdetectionwait']:'';
				$dest=(isset($_REQUEST['gotoFAX'])?$_REQUEST['gotoFAX'].'FAX':null);
				$dest=isset($_REQUEST[$dest])?$_REQUEST[$dest]:'';
				if ($enabled != 'legacy') {
					$legacy_email = null;
				} else {
					$legacy_email=isset($_REQUEST['legacy_email'])?$_REQUEST['legacy_email']:'';
				}

				switch($action) {
					case "edtIncoming":
						fax_delete_incoming($extdisplay);
					//fall through to next level on purpose
					case "addIncoming":
						if($enabled != 'false') {
							fax_save_incoming($cidnum,$extension,$enabled,$detection,$detectionwait,$dest,$legacy_email);
						}
					break;
					case "delIncoming":
						fax_delete_incoming($extdisplay);
					break;
				}
			break;
		}
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
					if(isset($_REQUEST['user'])) {
						$user = $this->userman->getUserByID($_REQUEST['user']);
						$enabled = $this->userman->getModuleSettingByID($user['id'],'fax','enabled',true);
						$attachformat = $this->userman->getModuleSettingByID($user['id'],'fax','attachformat',true);
					} else {
						$enabled = null;
						$attachformat = 'pdf';
					}
					return array(
						array(
							"title" => _("Fax"),
							"rawname" => "fax",
							"content" => load_view(__DIR__.'/views/fax.php',array("mode" => "user", "error" => $error, "enabled" => $enabled, "attachformat" => $attachformat))
						)
					);
				break;
			}
			return array();
		}
	}

	public function usermanDelGroup($id,$display,$data) {
		foreach($data['users'] as $user) {
			$enabled = $this->userman->getCombinedModuleSettingByID($user, 'fax', 'enabled');
			$attachformat = $this->userman->getCombinedModuleSettingByID($user, 'fax', 'attachformat');
			$userData = $this->userman->getUserByID($user);
			if(!empty($userData) && $display == "userman") {
				$this->saveUser($userData['id'],($enabled ? "true" : "false"),$userData['email'],$attachformat);
			}
		}
	}

	public function usermanAddGroup($id, $display, $data) {
		$this->usermanUpdateGroup($id,$display,$data);
	}

	public function usermanUpdateGroup($id,$display,$data) {
		if($display == 'userman' && isset($_POST['type']) && $_POST['type'] == 'group') {
			if(isset($_POST['faxenabled'])) {
				if($_POST['faxenabled'] == "true") {
					$this->userman->setModuleSettingByGID($id,'fax','enabled',true);
					$this->userman->setModuleSettingByGID($id,'fax','attachformat',$_POST['faxattachformat']);
				} else {
					$this->userman->setModuleSettingByGID($id,'fax','enabled',false);
					$this->userman->setModuleSettingByGID($id,'fax','attachformat',"pdf");
				}
			}
		}

		$group = $this->userman->getGroupByGID($id);
		foreach($group['users'] as $user) {
			$enabled = $this->userman->getCombinedModuleSettingByID($user, 'fax', 'enabled');
			$attachformat = $this->userman->getCombinedModuleSettingByID($user, 'fax', 'attachformat');
			$userData = $this->userman->getUserByID($user);
			if(!empty($userData) && $display == "userman") {
				$this->saveUser($userData['id'],($enabled ? "true" : "false"),$userData['email'],$attachformat);
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
		$this->deleteUser($id);
	}

	/**
	 * Hook functionality from userman when a user is added
	 * @param {int} $id      The userman user id
	 * @param {string} $display The display page name where this was executed
	 * @param {array} $data    Array of data to be able to use
	 */
	public function usermanAddUser($id, $display, $data) {
		if($display == 'userman' && isset($_POST['type']) && $_POST['type'] == 'user') {
			if(isset($_POST['faxenabled'])) {
				if($_POST['faxenabled'] == "true") {
					$this->userman->setModuleSettingByID($id,'fax','enabled',true);
					$this->userman->setModuleSettingByID($id,'fax','attachformat',$_POST['faxattachformat']);
				} elseif($_POST['faxenabled'] == "false") {
					$this->userman->setModuleSettingByID($id,'fax','enabled',false);
				} else {
					$this->userman->setModuleSettingByID($id,'fax','enabled',null);
				}
			}
		}

		$enabled = $this->userman->getCombinedModuleSettingByID($id, 'fax', 'enabled');
		$attachformat = $this->userman->getCombinedModuleSettingByID($id, 'fax', 'attachformat');
		$user = $this->FreePBX->Userman->getUserByID($id);
		if(!empty($user) && $display == "userman" && isset($_POST['faxenabled'])) {
			$this->saveUser($id,($enabled ? "true" : "false"),$user['email'],$attachformat);
		}
	}

	/**
	 * Hook functionality from userman when a user is updated
	 * @param {int} $id      The userman user id
	 * @param {string} $display The display page name where this was executed
	 * @param {array} $data    Array of data to be able to use
	 */
	public function usermanUpdateUser($id, $display, $data) {
		if($display == 'userman' && isset($_POST['type']) && $_POST['type'] == 'user') {
			if(isset($_POST['faxenabled'])) {
				if($_POST['faxenabled'] == "true") {
					$this->userman->setModuleSettingByID($id,'fax','enabled',true);
					$this->userman->setModuleSettingByID($id,'fax','attachformat',$_POST['faxattachformat']);
				} elseif($_POST['faxenabled'] == "false") {
					$this->userman->setModuleSettingByID($id,'fax','enabled',false);
				} else {
					$this->userman->setModuleSettingByID($id,'fax','enabled',null);
				}
			}
		}

		$enabled = $this->userman->getCombinedModuleSettingByID($id, 'fax', 'enabled');
		$attachformat = $this->userman->getCombinedModuleSettingByID($id, 'fax', 'attachformat');

		$user = $this->FreePBX->Userman->getUserByID($id);
		if(!empty($user) && $display == "userman" && isset($_POST['faxenabled'])) {
			$this->saveUser($id,($enabled ? "true" : "false"),$user['email'],$attachformat);
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
	public function coreDIDHook($page){
		if($page == 'did'){
			$target_menuid = $page;
			$tabindex=null;
			$type=isset($_REQUEST['type'])?$_REQUEST['type']:'';
			$extension=isset($_REQUEST['extension'])?$_REQUEST['extension']:'';
			$cidnum=isset($_REQUEST['cidnum'])?$_REQUEST['cidnum']:'';
			$extdisplay=isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:'';

			//if were editing, get save parms. Get parms

			if(!$extension && !$cidnum){//set $extension,$cidnum if we dont already have them
				if ($extdisplay) {
					$opts		= explode('/', $extdisplay);
					$extension	= $opts['0'];
					$cidnum		= isset($opts['1']) ? $opts['1'] : '';
				} else {
					$extension = $cidnum = '';
				}

			}
			$fax=fax_get_incoming($extension,$cidnum);

			$html=$fdinput='';
			if($target_menuid == 'did'){
		    $fax_dahdi_faxdetect=fax_dahdi_faxdetect();
		    $fax_sip_faxdetect=fax_sip_faxdetect();
		    $dahdi=ast_with_dahdi()?_('Dahdi'):_('Zaptel');
		    $fax_detect=fax_detect();
		    $fax_settings=fax_get_settings();
		    //ensure that we are using destination for both fax detect and the regular calls
				$html='<script type="text/javascript">$(document).ready(function(){
				$("input[name=Submit]").click(function(){
					if($("input[name=faxenabled]:checked").val()=="true" && !$("[name=gotoFAX]").val()){//ensure the user selected a fax destination
					alert('._('"You have selected Fax Detection on this route. Please select a valid destination to route calls detected as faxes to."').');return false; }	}) });</script>';
				$fdhelp = _("Attempt to detect faxes on this DID.");
				$fdhelp .= '<ul>';
				$fdhelp .= '<li>'._("No: No attempts are made to auto-determine the call type; all calls sent to destination set in the 'General' tab. Use this option if this DID is used exclusively for voice OR fax.").'</li>';
				$fdhelp .= '<li>'._("Yes: try to auto determine the type of call; route to the fax destination if call is a fax, otherwise send to regular destination. Use this option if you receive both voice and fax calls on this line").'</li>';
				if($fax_settings['legacy_mode'] == 'yes' || $fax['legacy_email']!==null){
		    		$fdhelp .= '<li>'._('Legacy: Same as YES, only you can enter an email address as the destination. This option is ONLY for supporting migrated legacy fax routes. You should upgrade this route by choosing YES, and selecting a valid destination!').'</li>';
				}
				$fdhelp .= '</ul>';
						//dont allow detection to be set if we have no valid detection types
				if(!$fax_dahdi_faxdetect && !$fax_sip_faxdetect && !$fax_detect['nvfax']){
					$js="if ($(this).val() == 'true'){alert('"._('No fax detection methods found or no valid license. Faxing cannot be enabled.')."');return false;}";
					$fdinput.='<input type="radio" name="faxenabled" id="faxenabled_yes" value="true"  onclick="'.$js.'"/><label for="faxenabled_yes">Yes</label></span>';
					$fdinput.='<input type="radio" id="faxenabled_no" name="faxenabled" value="false" CHECKED /><label for="faxenabled_no">No</label>';
				}else{
					/*
					 * show detection options
					 *
					 * js to show/hide the detection settings. Second slide is always in a
					 * callback so that we ait for the fits animation to complete before
					 * playing the second
					 */
					$faxing = !empty($fax);
					$fdinput.= '<input type="radio" name="faxenabled" id="faxenabled_yes" value="true" '.($faxing?'CHECKED':'').' /><label for="faxenabled_yes">' . _('Yes') . '</label>';
					$fdinput .= '<input type="radio" name="faxenabled" id="faxenabled_no" value="false" '.(!$faxing?'CHECKED':'').'/><label for="faxenabled_no">' . _('No') . '</label>';
					if($fax['legacy_email']!==null || $fax_settings['legacy_mode'] == 'yes'){
						$fdinput .= '<input type="radio" name="faxenabled" id="faxenabled_legacy" value="legacy"'.($fax['legacy_email'] !== null ? ' CHECKED ':'').'onclick="'.$jslegacy.'"/><label for="faxenabled_legacy">' . _('Legacy');
					}
				}
				$html .='
					<!--Detect Faxes-->
					<div class="element-container">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="faxenabled">'._("Detect Faxes").'</label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="faxenabled"></i>
										</div>
										<div class="col-md-9 radioset">
											'.$fdinput.'
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="faxenabled-help" class="help-block fpbx-help-block">'.$fdhelp.'</span>
							</div>
						</div>
					</div>
					<!--END Detect Faxes-->
				';
				$info=engine_getinfo();
				$fdthelp = _("Type of fax detection to use.");
				$fdthelp .= '<ul>';
				$fdthelp .= '<li>'.$dahdi.': '._("use ").$dahdi._(" fax detection; requires 'faxdetect=' to be set to 'incoming' or 'both' in ").$dahdi.'.conf</li>';
				$fdthelp .= '<li>'._("Sip: use sip fax detection (t38). Requires asterisk 1.6.2 or greater and 'faxdetect=yes' in the sip config files").'</li>';
				$fdthelp .= '<li>'._("NV Fax Detect: Use NV Fax Detection; Requires NV Fax Detect to be installed and recognized by asterisk").'</li>';
				$fdthelp .= '</ul>';
				$html .='
				<!--Fax Detection type-->
				<div class="element-container '.($faxing?'':"hidden").'" id="fdtype">
					<div class="row">
						<div class="col-md-12">
							<div class="row">
								<div class="form-group">
									<div class="col-md-3">
										<label class="control-label" for="faxdetection">'._("Fax Detection type").'</label>
										<i class="fa fa-question-circle fpbx-help-icon" data-for="faxdetection"></i>
									</div>
									<div class="col-md-9 radioset">
										<input type="radio" name="faxdetection" id="faxdetectiondahdi" value="dahdi" '. ($fax['detection'] == "dahdi"?"CHECKED":"").' '.($fax_dahdi_faxdetect?'':'disabled').'>
										<label for="faxdetectiondahdi">'. _("Dahdi").'</label>
										<input type="radio" name="faxdetection" id="faxdetectionnvfax" value="nvfax" '. ($fax['detection'] == "nvfax"?"CHECKED":"").' '.($fax_detect['nvfax']?'':'disabled').'>
										<label for="faxdetectionnvfax">'. _("NVFax").'</label>
										<input type="radio" name="faxdetection" id="faxdetectionsip" value="sip" '. ($fax['detection'] == "sip"?"CHECKED":"").' '.((($info['version'] >= "1.6.2") && $fax_sip_faxdetect)?'':'disabled').'>
										<label for="faxdetectionsip">'. _("SIP").'</label>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-12">
							<span id="faxdetection-help" class="help-block fpbx-help-block">'.$fdthelp.'</span>
						</div>
					</div>
				</div>
				<!--END Fax Detection type-->
				';
				if(!$fax['detectionwait']){$fax['detectionwait']=4;}//default wait time is 4 second
				$fdthelp = _('How long to wait and try to detect fax. Please note that callers to a Dahdi channel will hear ringing for this amount of time (i.e. the system wont "answer" the call, it will just play ringing).');
				$html .='
				<!--Fax Detection Time-->
				<div class="element-container '.($faxing?'':"hidden").'" id="fdtime">
					<div class="row">
						<div class="col-md-12">
							<div class="row">
								<div class="form-group">
									<div class="col-md-3">
										<label class="control-label" for="faxdetectionwait">'._("Fax Detection Time").'</label>
										<i class="fa fa-question-circle fpbx-help-icon" data-for="faxdetectionwait"></i>
									</div>
									<div class="col-md-9">
										<input type="number" min="2" max="11" class="form-control" id="faxdetectionwait" name="faxdetectionwait" value="'.$fax['detectionwait'].'">
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-12">
							<span id="faxdetectionwait-help" class="help-block fpbx-help-block">'.$fdthelp.'</span>
						</div>
					</div>
				</div>
				<!--END Fax Detection Time-->
				';
				if(!empty($fax['legacy_email']) || $fax_settings['legacy_mode'] == 'yes'){
					$fedhelp = _("Address to email faxes to on fax detection.<br />PLEASE NOTE: In this version of FreePBX, you can now set the fax destination from a list of destinations. Extensions/Users can be fax enabled in the user/extension screen and set an email address there. This will create a new destination type that can be selected. To upgrade this option to the full destination list, select YES to Detect Faxes and select a destination. After clicking submit, this route will be upgraded. This Legacy option will no longer be available after the change, it is provided to handle legacy migrations from previous versions of FreePBX only.");
					$html .= '
					<!--Fax Email Destination-->
					<div class="element-container '.($faxing?'':"hidden").'" id="fdemail">
						<div class="row">
							<div class="col-md-12">
								<div class="row">
									<div class="form-group">
										<div class="col-md-3">
											<label class="control-label" for="legacy_email"><?php echo _("Fax Email Destination") ?></label>
											<i class="fa fa-question-circle fpbx-help-icon" data-for="legacy_email"></i>
										</div>
										<div class="col-md-9">
											<input type="text" class="form-control" id="legacy_email" name="legacy_email" value="'.$fax['legacy_email'].'">
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<span id="legacy_email-help" class="help-block fpbx-help-block">'.$fedhelp.'</span>
							</div>
						</div>
					</div>
					<!--END Fax Email Destination-->
					';
				}
				$faxdesthelp = _('Where to send the faxes');
				$html .='
				<!--Fax Destination-->
				<div class="element-container '.($faxing?'':"hidden").'" id="fddest">
					<div class="row">
						<div class="col-md-12">
							<div class="row">
								<div class="form-group">
									<div class="col-md-3">
										<label class="control-label" for="gotofax">'. _("Fax Destination").'</label>
										<i class="fa fa-question-circle fpbx-help-icon" data-for="gotofax"></i>
									</div>
									<div class="col-md-9">';
									$html .=$fax_detect?drawselects(isset($fax['destination'])?$fax['destination']:null,'FAX',false,false):'';
									$html .= '
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-12">
							<span id="gotofax-help" class="help-block fpbx-help-block">'.$faxdesthelp.'</span>
						</div>
					</div>
				</div>
				<!--END Fax Destination-->
				<script type="text/javascript">
				$("[name=\'faxenabled\']").change(function(){
					if($(this).val() == \'true\'){
						$("#fdtype").removeClass("hidden");
						$("#fdtime").removeClass("hidden");
						$("#fddest").removeClass("hidden");
					}else{
						$("#fdtype").addClass("hidden");
						$("#fdtime").addClass("hidden");
						$("#fddest").addClass("hidden");
					}
				});
				</script>
				';
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

	public function bulkhandlerGetHeaders($type) {
		switch ($type) {
			case 'dids':
				$headers = array(
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
				return $headers;
			break;
		}
	}

	public function bulkhandlerExport($type) {
		$data = NULL;

		switch ($type) {
			case 'usermanusers':
				$users = $this->userman->getAllUsers();
				foreach ($users as $user) {
					$en = $this->userman->getModuleSettingByID($user['id'],'fax','enabled',true);
					$data[$user['id']] = array(
						'fax_enabled' => is_null($en) ? "inherit" : (empty($en) ? 'no' : 'yes'),
						'fax_attachformat' => $this->userman->getModuleSettingByID($user['id'],'fax','attachformat'),
					);
				}
			break;
			case 'usermangroups':
				$groups = $this->userman->getAllGroups();
				foreach ($groups as $group) {
					$en = $this->userman->getModuleSettingByGID($group['id'],'fax','enabled');
					$data[$group['id']] = array(
						'fax_enabled' => empty($en) ? 'no' : 'yes',
						'fax_attachformat' => $this->userman->getModuleSettingByGID($group['id'],'fax','attachformat'),
					);
				}
			break;
			case "dids":
				$dids = $this->FreePBX->Core->getAllDIDs();
				$data = array();
				$this->FreePBX->Modules->loadFunctionsInc("fax");
				foreach($dids as $did) {
					$key = $did['extension']."/".$did["cidnum"];
					$fax = fax_get_incoming($did['extension'],$did["cidnum"]);
					if(!empty($fax)) {
						$data[$key] = array(
							"fax_enable" => "yes",
							"fax_detection" => $fax['detection'],
							"fax_detectionwait" => $fax['detectionwait'],
							"fax_destination" => $fax['destination']
						);
					} else {
						array(
							"fax_enable" => "",
							"fax_detection" => "",
							"fax_detectionwait" => "",
							"fax_destination" => ""
						);
					}
				}
			break;
		}

		return $data;
	}

	public function bulkhandlerImport($type, $rawData, $replaceExisting = false) {
		$ret = NULL;

		switch ($type) {
			case 'usermanusers':
				foreach ($rawData as $data) {
					$user = $this->FreePBX->Userman->getUserByUsername($data['username']);
					if(isset($data['fax_enabled'])) {
						$en = ($data['fax_enabled'] == "yes") ? true : ($data['fax_enabled'] == "no" ? false : null);
						$this->userman->setModuleSettingByID($user['id'],'fax','enabled',$en);
					}
					if(isset($data['fax_attachformat'])) {
						$this->userman->setModuleSettingByID($user['id'],'fax','attachformat',$data['fax_attachformat']);
					}
				};
			break;
			case 'usermangroups':
				foreach ($rawData as $data) {
					$group = $this->FreePBX->Userman->getGroupByUsername($data['groupname']);
					if(isset($data['fax_enabled'])) {
						$en = ($data['fax_enabled'] == "yes") ? true : false;
						$this->userman->setModuleSettingByGID($group['id'],'fax','enabled',$en);
					}
					if(isset($data['fax_attachformat'])) {
						$this->userman->setModuleSettingByGID($group['id'],'fax','attachformat',$data['fax_attachformat']);
					}
				};
			break;
			case 'dids':
				$this->FreePBX->Modules->loadFunctionsInc("fax");
				foreach ($rawData as $data) {
					$settings = array();
					foreach ($data as $key => $value) {
						if (substr($key, 0, 4) == 'fax_') {
							$settingname = substr($key, 4);
							switch ($settingname) {
								default:
									$settings[$settingname] = $value;
								break;
							}
						}
					}
					fax_delete_incoming($data['extension']."/".$data["cidnum"]);
					if(!empty($settings['enable'])) {
						fax_save_incoming($data["cidnum"],$data['extension'],true,$settings['detection'],$settings['detectionwait'],$settings['destination'],null);
					}
				}
			break;
		}
	}

	/**
	 * Chown hook for freepbx fwconsole
	 */
	public function chownFreepbx() {
		$webroot = \FreePBX::Config()->get('AMPWEBROOT');
		$modulebindir = $webroot . '/admin/modules/fax/bin/';
		$files = array();
		$files[] = array('type' => 'file',
												'path' => $modulebindir.'fax2mail.php',
												'perms' => 0755);
		return $files;
	}
}
