<?php
/* $Id */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//
function fax_getdest($exten) {
	return array("ext-fax,$exten,1");
}

function fax_getdestinfo($dest) {
	global $amp_conf;
	if (substr(trim($dest),0,8) == 'ext-fax,') {
		$usr = explode(',',$dest);
		$usr = $usr[1];
		$thisusr = fax_get_user($usr);
		if (empty($thisusr)) {
			return array();
		} else {
			return array('description' => sprintf(_("Fax user %s"),$usr),
			             'edit_url' => 'config.php?display=userman&action=showuser&user='.urlencode($usr),
								  );
		}
	} else {
		return false;
	}
}

function fax_check_destinations($dest=true) {
	global $active_modules;
	global $version;

	$ast_lt_18 = version_compare($version, '1.8', 'lt');
	$fax=fax_detect();
	if(!$fax['module'] || ($fax['module'] && (!$fax['ffa'] && !$fax['spandsp'])) || !$ast_lt_18){
		return false;
	}elseif($fax['ffa'] && $fax['license'] < 1){//missing license
		return false;
	}

	$destlist = array();
	if (is_array($dest) && empty($dest)) {
		return $destlist;
	}
	$sql = "SELECT a.extension, a.cidnum, b.description, a.destination FROM fax_incoming a JOIN incoming b ";
	$sql .= "WHERE a.extension = b.extension AND a.cidnum = b.cidnum AND a.legacy_email IS NULL ";
	if ($dest !== true) {
		$sql .= "AND a.destination in ('".implode("','",$dest)."') ";
	}
	$sql .= "ORDER BY extension, cidnum";
	$results = sql($sql,"getAll",DB_FETCHMODE_ASSOC);

	//$type = isset($active_modules['announcement']['type'])?$active_modules['announcement']['type']:'setup';

	foreach ($results as $result) {
		$thisdest = $result['destination'];
		$thisid   = $result['extension'].'/'.$result['cidnum'];
		$destlist[] = array(
			'dest' => $thisdest,
			'description' => sprintf(_("Inbound Fax Detection: %s (%s)"),$result['description'],$thisid),
			'edit_url' => 'config.php?display=userman&action=showuser&user='.urlencode($thisid),
		);
	}
	return $destlist;
}

function fax_change_destination($old_dest, $new_dest) {
	$sql = 'UPDATE fax_incoming SET destination = "' . $new_dest . '" WHERE destination = "' . $old_dest . '"';
	sql($sql, "query");
}

function fax_dahdi_faxdetect(){
	/*
	 * kepping this always set to true for freepbx 2.7 as we cant currently properly detect this - MB
	 *
	 */
	return true;
}

function fax_delete_incoming($extdisplay){
	global $db;
	$opts		= explode('/', $extdisplay);
	$extension	= $opts['0'];
	$cidnum		= $opts['1']; //set vars
	sql("DELETE FROM fax_incoming WHERE cidnum = '"
		. $db->escapeSimple($cidnum)
		. "' and extension = '"
		. $db->escapeSimple($extension)
		. "'");
}

function fax_delete_user($faxext) {
	global $db;
	$faxext = $db->escapeSimple($faxext);
	sql('DELETE FROM fax_users where user = "' . $faxext . '"');
}

function fax_destinations(){
	global $module_page;
	$recip = fax_get_destinations();
	usort($recip, function($a,$b){ return ($a['uname'] < $b['uname']) ? -1 : 1;});
	foreach ( $recip as $row) {
		$extens[] = array('destination' => 'ext-fax,' . $row['user'] . ',1', 'description' => $row['name'].' ('.$row['uname'].')', 'category' => _('Fax Recipient'));
	}
	return isset($extens)?$extens:null;
}

//check to see if any fax modules and licenses are loaded in to asterisk
function fax_detect($astver=null){
	global $amp_conf;
	global $astman;

	if ($astver === null) {
		$engineinfo = engine_getinfo();
		$astver =  $engineinfo['version'];
	}
	$ast_ge_14 = version_compare($astver, '1.4', 'ge');
	$ast_ge_18 = version_compare($astver, '1.8', 'ge');

	$fax=null;
	$appfax = $receivefax = false;//return false by default in case asterisk isnt reachable
	if ($amp_conf['AMPENGINE'] == 'asterisk' && isset($astman) && $astman->connected()) {
		//check for fax modules
		$module_show_command = $ast_ge_14 ? 'module show like ' : 'show modules like ';
		$app = $astman->send_request('Command', array('Command' => $module_show_command.'res_fax'));
		if (preg_match('/[1-9] modules loaded/', $app['data'])){
			$fax['module']='res_fax';
		} else {
			$receive = $astman->send_request('Command', array('Command' => $module_show_command.'app_fax'));
			if (preg_match('/[1-9] modules loaded/', $receive['data'])) {
				$fax['module']='app_fax';
			}
		}
		if (!isset($fax['module'])) {
			$app = $astman->send_request('Command', array('Command' => $module_show_command.'app_rxfax'));
			$fax['module'] = preg_match('/[1-9] modules loaded/', $app['data']) ? 'app_rxfax': null;
		}
		$response = $astman->send_request('Command', array('Command' => $module_show_command.'app_nv_faxdetect'));
		$fax['nvfax']= preg_match('/[1-9] modules loaded/', $response['data']) ? true : false;

		$response = $astman->send_request('Command', array('Command' => $module_show_command.'res_fax_digium'));
		$fax['ffa']= preg_match('/[1-9] modules loaded/', $response['data']) ? true : false;

		if ($ast_ge_18) {
			if ($fax['ffa']) {
				$fax['spandsp'] = false;
			} else {
				$response = $astman->send_request('Command', array('Command' => $module_show_command.'res_fax_spandsp'));
				$fax['spandsp'] = preg_match('/[1-9] modules loaded/', $response['data']) ? true : false;
			}
		}

		switch($fax['module']) {
		case 'res_fax':
			$fax['receivefax'] = 'receivefax';
			break;
		case 'app_rxfax':
			$fax['receivefax'] = 'rxfax';
			break;
		case 'app_fax':
			$application_show_command = $ast_ge_14 ? 'core show applications like ' : 'show applications like ';
			$response = $astman->send_request('Command', array('Command' => $application_show_command.'receivefax'));
			if (preg_match('/1 Applications Matching/', $response['data'])) {
				$fax['receivefax'] = 'receivefax';
			} else {
				$response = $astman->send_request('Command', array('Command' => $application_show_command.'rxfax'));
				if (preg_match('/1 Applications Matching/', $response['data'])) {
					$fax['receivefax'] = 'rxfax';
				} else {
					$fax['receivefax'] = 'none';
				}
			}
			break;
		}

		// get license count
		$lic = $astman->send_request('Command', array('Command' => 'fax show stats'));
		foreach(explode("\n",$lic['data']) as $licdata){
			$d=explode(':',$licdata);
			$data[trim($d['0'])]=isset($d['1'])?trim($d['1']):null;
		}
		$fax['license']=isset($data['Licensed Channels']) ? $data['Licensed Channels'] : '';
	}
	return $fax;
}

function fax_get_config($engine){
	global $version;
	global $ext;
	global $amp_conf;
	global $core_conf;
	global $astman;

	$ast_ge_11 = version_compare($version, '11', 'ge');
	$ast_ge_10 = version_compare($version, '10', 'ge');
	$ast_lt_18 = version_compare($version, '1.8', 'lt');
	$ast_ge_16 = version_compare($version, '1.6', 'ge');
	$fax=fax_detect($version);
	$astman->database_deltree("FAX");
	// do not continue unless we have a fax module in asterisk
	if($fax['module'] && ($ast_lt_18 || $fax['ffa'] || $fax['spandsp'])) {
		$t38_fb = $ast_ge_16 ? ',f' : '';
		$context='ext-fax';
		$dests=fax_get_destinations();

		if($dests){
			foreach ($dests as $row) {
				$exten=$row['user'];
				$astman->database_put("FAX/".$exten,"attachformat",$row['faxattachformat']);
				$astman->database_put("FAX/".$exten,"email",$row['faxemail']);
				$ext->add($context, $exten, '', new ext_set('FAX_FOR',$row['name'].' ('.$row['user'].')'));
				$ext->add($context, $exten, '', new ext_noop('Receiving Fax for: ${FAX_FOR}, From: ${CALLERID(all)}'));
				$ext->add($context, $exten, '', new ext_set('FAX_ATTACH_FORMAT', '${DB(FAX/'.$exten.'/attachformat)}'));
				$ext->add($context, $exten, '', new ext_set('FAX_RX_EMAIL', '${DB(FAX/'.$exten.'/email)}'));
				$ext->add($context, $exten, 'receivefax', new ext_goto('receivefax','s'));
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
		$ext->add($context, $exten, '', new ext_macro('user-callerid')); // $cmd,n,Macro(user-callerid)
		$ext->add($context, $exten, '', new ext_noop('Receiving Fax for: ${FAX_RX_EMAIL} , From: ${CALLERID(all)}'));
		$ext->add($context, $exten, 'receivefax', new ext_stopplaytones(''));
		switch ($fax['module']) {
		case 'app_rxfax':
			$ext->add($context, $exten, '', new ext_rxfax('${ASTSPOOLDIR}/fax/${UNIQUEID}.tif')); //receive fax, then email it on
			break;
		case 'app_fax':
			// $fax['receivefax'] should be rxfax or receivefax, it could be none in which case we don't know. We'll just make it
			// ReceiveFAX in that case since it will fail anyhow.
			if ($fax['receivefax'] == 'rxfax') {
				$ext->add($context, $exten, '', new ext_rxfax('${ASTSPOOLDIR}/fax/${UNIQUEID}.tif')); //receive fax, then email it on
			} elseif ($fax['receivefax'] == 'receivefax') {
				$ext->add($context, $exten, '', new ext_receivefax('${ASTSPOOLDIR}/fax/${UNIQUEID}.tif'.$t38_fb)); //receive fax, then email it on
			} else {
				$ext->add($context, $exten, '', new ext_noop('ERROR: NO Receive FAX application detected, putting in dialplan for ReceiveFAX as default'));
				$ext->add($context, $exten, '', new ext_receivefax('${ASTSPOOLDIR}/fax/${UNIQUEID}.tif'.$t38_fb)); //receive fax, then email it on
				$ext->add($context, $exten, '', new ext_execif('$["${FAXSTATUS}" = ""]','Set','FAXSTATUS=${IF($["${FAXOPT(error)}" = ""]?"FAILED LICENSE EXCEEDED":"FAILED FAXOPT: error: ${FAXOPT(error)} status: ${FAXOPT(status)} statusstr: ${FAXOPT(statusstr)}")}'));
			}
			break;
		case 'res_fax':
			$ext->add($context, $exten, '', new ext_receivefax('${ASTSPOOLDIR}/fax/${UNIQUEID}.tif'.$t38_fb)); //receive fax, then email it on
			if ($ast_ge_16) {
				if ($fax['ffa']) {
					$ext->add($context, $exten, '', new ext_execif('$["${FAXSTATUS}"="" | "${FAXSTATUS}" = "FAILED" & "${FAXERROR}" = "INIT_ERROR"]','Set','FAXSTATUS=FAILED LICENSE MAY BE EXCEEDED check log errors'));
				}
				$ext->add($context, $exten, '', new ext_execif('$["${FAXSTATUS:0:6}"="FAILED" && "${FAXERROR}"!="INIT_ERROR"]','Set','FAXSTATUS="FAILED: error: ${FAXERROR} statusstr: ${FAXOPT(statusstr)}"'));
			} else {
				// Some versions or settings appear to have successful completions continue, so check status and goto hangup code
				if ($fax['ffa']) {
					$ext->add($context, $exten, '', new ext_execif('$["${FAXOPT(error)}"=""]','Set','FAXSTATUS=FAILED LICENSE MAY BE EXCEEDED'));
				}
				$ext->add($context, $exten, '', new ext_execif('$["${FAXOPT(error)}"!="" && "${FAXOPT(error)}"!="NO_ERROR"]','Set','FAXSTATUS="FAILED FAXOPT: error: ${FAXOPT(error)} status: ${FAXOPT(status)} statusstr: ${FAXOPT(statusstr)}"'));
			}
			$ext->add($context, $exten, '', new ext_hangup());
			break;
		default: // unknown
			$ext->add($context, $exten, '', new ext_noop('No Known FAX Technology installed to receive a fax, aborting'));
			$ext->add($context, $exten, '', new ext_set('FAXSTATUS','FAILED No Known Fax Reception Apps available to process'));
			$ext->add($context, $exten, '', new ext_hangup());
		}
		$exten = 'h';

		// if there is a file there, mail it even if we failed:
		$ext->add($context, $exten, '', new ext_gotoif('$[${STAT(e,${ASTSPOOLDIR}/fax/${UNIQUEID}.tif)} = 0]','failed'));
		$ext->add($context, $exten, '', new ext_noop_trace('PROCESSING FAX with status: [${FAXSTATUS}] for: [${FAX_RX_EMAIL}], From: [${CALLERID(all)}]'));
		//delete is a variable so that other modules can prevent it should then need to prosses the file further
		$ext->add($context, $exten, 'delete_opt', new ext_set('DELETE_AFTER_SEND', 'true'));
		$ext->add($context, $exten, 'process', new ext_gotoif('$[${LEN(${FAX_RX_EMAIL})} = 0]','noemail'));

		$ext->add($context, $exten, '', new ext_system('${ASTVARLIBDIR}/bin/fax2mail.php --remotestationid "${FAXOPT(remotestationid)}" --to "${FAX_RX_EMAIL}" --dest "${FROM_DID}" --callerid \'${STRREPLACE(CALLERID(all),\',\\\\\')}\' --file ${ASTSPOOLDIR}/fax/${UNIQUEID}.tif --exten "${FAX_FOR}" --delete "${DELETE_AFTER_SEND}" --attachformat "${FAX_ATTACH_FORMAT}"'));

		$ext->add($context, $exten, 'end', new ext_macro('hangupcall'));

		$ext->add($context, $exten, 'noemail', new ext_noop('ERROR: No Email Address to send FAX: status: [${FAXSTATUS}],  From: [${CALLERID(all)}]'));
		$ext->add($context, $exten, '', new ext_system('${ASTVARLIBDIR}/bin/fax2mail.php --file ${ASTSPOOLDIR}/fax/${UNIQUEID}.tif --delete "${DELETE_AFTER_SEND}"'));
		$ext->add($context, $exten, '', new ext_macro('hangupcall'));

		$ext->add($context, $exten, 'failed', new ext_noop('FAX ${FAXSTATUS} for: ${FAX_RX_EMAIL} , From: ${CALLERID(all)}'),'process',101);
		$ext->add($context, $exten, '', new ext_macro('hangupcall'));

		$modulename = 'fax';
		$fcc = new featurecode($modulename, 'simu_fax');
		$fc_simu_fax = $fcc->getCodeActive();
		unset($fcc);

		if ($fc_simu_fax != '') {
			$default_address = sql('SELECT value FROM fax_details WHERE `key` = \'FAX_RX_EMAIL\'','getRow');
			$ext->addInclude('from-internal-additional', 'app-fax'); // Add the include from from-internal
			$ext->add('app-fax', $fc_simu_fax, '', new ext_setvar('FAX_RX_EMAIL', $default_address[0]));
			$ext->add('app-fax', $fc_simu_fax, '', new ext_goto('1', 's', 'ext-fax'));
			$ext->add('app-fax', 'h', '', new ext_macro('hangupcall'));
		}
		// This is not really needed but is put here in
		// case some ever accidently switches the order below
		// when checking for this setting since $fax['module']
		// will be set there and the 2nd part never checked
		$fax_settings['force_detection'] = 'yes';
	} else {
		$fax_settings=fax_get_settings();
	}
	if (($fax['module'] && ($ast_lt_18 || $fax['ffa'] || $fax['spandsp'])) || $fax_settings['force_detection'] == 'yes') {
		if ($ast_ge_11 && isset($core_conf) && is_a($core_conf, "core_conf")) {
			$core_conf->addSipGeneral('faxdetect','no');
		} else if ($ast_ge_16 && isset($core_conf) && is_a($core_conf, "core_conf")) {
			$core_conf->addSipGeneral('faxdetect','yes');
		}

		$ext->add('ext-did-0001', 'fax', '', new ext_setvar('__DIRECTION',($amp_conf['INBOUND_NOTRANS'] ? 'INBOUND' : '')));
		$ext->add('ext-did-0001', 'fax', '', new ext_goto('${CUT(FAX_DEST,^,1)},${CUT(FAX_DEST,^,2)},${CUT(FAX_DEST,^,3)}'));
		$ext->add('ext-did-0002', 'fax', '', new ext_setvar('__DIRECTION',($amp_conf['INBOUND_NOTRANS'] ? 'INBOUND' : '')));
		$ext->add('ext-did-0002', 'fax', '', new ext_goto('${CUT(FAX_DEST,^,1)},${CUT(FAX_DEST,^,2)},${CUT(FAX_DEST,^,3)}'));

		// Add fax extension to ivr and announcement as inbound controle may be passed quickly to them and still detection is desired
		if (function_exists('ivr_get_details')) {
			$ivrlist = ivr_get_details();
			if(is_array($ivrlist)) foreach($ivrlist as $item) {
				$ext->add("ivr-".$item['id'], 'fax', '', new ext_goto('${CUT(FAX_DEST,^,1)},${CUT(FAX_DEST,^,2)},${CUT(FAX_DEST,^,3)}'));
			}
		}
		if (function_exists('announcement_list')) foreach (announcement_list() as $row) {
			$ext->add('app-announcement-'.$row['announcement_id'], 'fax', '', new ext_goto('${CUT(FAX_DEST,^,1)},${CUT(FAX_DEST,^,2)},${CUT(FAX_DEST,^,3)}'));
		}
	}
}

function fax_get_destinations(){
	global $db;
	$sql = "SELECT fax_users.user,fax_users.faxemail,fax_users.faxattachformat FROM fax_users where fax_users.faxenabled = 'true' ORDER BY fax_users.user";
	$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	if(DB::IsError($results)) {
		die_freepbx($results->getMessage()."<br><br>Error selecting from fax");
	}
	$final = array();
	$warning = array();
	foreach($results as $res) {
		$o = \FreePBX::Userman()->getUserByID($res['user']);
		if(!empty($o)) {
			if(empty($o['email'])) {
				$warning[] = $o['username'];
			}
			$res['uname'] = $o['username'];
			$res['name'] = !empty($o['displayname']) ? $o['displayname'] : $o['fname'] . " " . $o['lname'];
			$res['name'] = trim($res['name']);
			$res['name'] = !empty($res['name']) ? $res['name'] : $o['username'];
			$final[] = $res;
		}
	}
	$nt = \notifications::create();
	if(!empty($warning)) {
		$nt->add_warning("fax", "invalid_email", _("Invalid Email for Inbound Fax"), sprintf(_("User Manager users '%s' have the ability to receive faxes but have no email address defined so they will not be able to receive faxes over email,"),implode(",",$warning)), "", true, true);
	} else {
		$nt->delete("fax", "invalid_email");
	}
	return $final;
}

function fax_get_incoming($extension=null,$cidnum=null){
	global $db;
	if($extension !== null || $cidnum !== null){
		$sql="SELECT * FROM fax_incoming WHERE extension = ? AND cidnum = ?";
		$settings = $db->getRow($sql, array($extension, $cidnum), DB_FETCHMODE_ASSOC);
		if(isset($settings['legacy_email'])&&$settings['legacy_email']=='NULL'){$settings['legacy_email']=null;}//convert string to real value
	}else{
		$sql="SELECT fax_incoming.*, incoming.pricid FROM fax_incoming, incoming where fax_incoming.cidnum=incoming.cidnum and fax_incoming.extension=incoming.extension;";
		$settings=$db->getAll($sql, DB_FETCHMODE_ASSOC);

	}
	return $settings;
}

function fax_get_user($faxext = ''){
	global $db;
	if ($faxext) {
		$sql		= "SELECT * FROM fax_users WHERE user = ?";
		$settings	= $db->getRow($sql, array($faxext), DB_FETCHMODE_ASSOC);
		db_e($settings);
		if(is_array($settings)) {
			$o = \FreePBX::Userman()->getUserByID($settings['user']);
			if(empty($o)) {
				return array();
			}
		} else {
			return array();
		}
	} else {
		$sql		= "SELECT * FROM fax_users";
		$settings	= $db->getAll($sql, DB_FETCHMODE_ASSOC);
		db_e($settings);
		$final = array();
		if(is_array($settings)) {
			foreach($settings as $setting) {
				if(!empty($setting)) {
					$o = \FreePBX::Userman()->getUserByID($setting['user']);
					if(!empty($o)) {
						$final[] = $setting;
					}
				}
			}
			$settings = $final;
		} else {
			return array();
		}
	}
	return $settings;
}

function fax_get_settings(){
	return Freepbx::Fax()->getSettings();
}

/** Moved to BMO hook
function fax_hook_core($viewing_itemid, $target_menuid){}
*/


function fax_hookGet_config($engine){
	global $version;

	$ast_ge_11 = version_compare($version, '11', 'ge');

	$fax=fax_detect($version);
	if ($fax['module']) {
		$fax_settings['force_detection'] = 'yes';
	} else {
		$fax_settings=fax_get_settings();
	}
	if($fax_settings['force_detection'] == 'yes'){ //dont continue unless we have a fax module in asterisk
		global $ext;
		global $engine;
		$routes=fax_get_incoming();
		foreach($routes as $current => $route){
			if ($route['detection'] == 'nvfax' && !$fax['nvfax']) {
				//TODO: add notificatoin to notification panel that this was skipped because NVFaxdetec not present
				continue; // skip this one if there is no NVFaxdetect installed on this system
			}
			if($route['extension']=='' && $route['cidnum']){//callerID only
				$extension='s/'.$route['cidnum'];
				$context=($route['pricid']=='CHECKED')?'ext-did-0001':'ext-did-0002';
			}else{
				if(($route['extension'] && $route['cidnum'])||($route['extension']=='' && $route['cidnum']=='')){//callerid+did / any/any
					$context='ext-did-0001';
				}else{//did only
					$context='ext-did-0002';
				}
				$extension=($route['extension']!=''?$route['extension']:'s').($route['cidnum']==''?'':'/'.$route['cidnum']);
			}
			if ($route['legacy_email'] === null) {
				$ext->splice($context, $extension, 'dest-ext', new ext_setvar('FAX_DEST',str_replace(',','^',$route['destination'])));
			} else {
				$ext->splice($context, $extension, 'dest-ext', new ext_setvar('FAX_DEST','ext-fax^s^1'));
				if ($route['legacy_email']) {
					$fax_rx_email = $route['legacy_email'];
				} else {
					if (!isset($default_fax_rx_email)) {
						$default_address = sql('SELECT value FROM fax_details WHERE `key` = \'fax_rx_email\'','getRow');
						$default_fax_rx_email = $default_address[0];
					}
					$fax_rx_email = $default_fax_rx_email;
				}
				$ext->splice($context, $extension, 'dest-ext', new ext_setvar('FAX_RX_EMAIL',$fax_rx_email));
			}
			//If we have fax incoming, we need to set fax detection to yes if we are on Asterisk 11 or newer
			if ($ast_ge_11) {
				$ext->splice($context, $extension, 'dest-ext', new ext_setvar('FAXOPT(faxdetect)', 'yes'));
			}
			$ext->splice($context, $extension, 'dest-ext', new ext_answer(''));
			if(!empty($route['ring'])) {
				$ext->splice($context, $extension, 'dest-ext', new ext_playtones('ring'));
			}

			if ($route['detection'] == 'nvfax') {
				$ext->splice($context, $extension, 'dest-ext', new ext_nvfaxdetect($route['detectionwait'].",t"));
			} else {
				$ext->splice($context, $extension, 'dest-ext', new ext_wait($route['detectionwait']));
			}
		}
	}
}
/** Moved in to BMO Class
function fax_hookProcess_core()
*/

function fax_save_incoming($cidnum,$extension,$enabled,$detection,$detectionwait,$dest,$legacy_email,$ring=1){
	global $db;
	$legacy_email =  $legacy_email === null ? 'NULL' : "'".$db->escapeSimple("$legacy_email")."'";
	$ring = ($ring == 'yes') ? 1 : 0;
	sql("INSERT INTO fax_incoming (cidnum, extension, detection, detectionwait, destination, legacy_email, ring) VALUES ('".$db->escapeSimple($cidnum)."', '".$db->escapeSimple($extension)."', '".$db->escapeSimple($detection)."', '".$db->escapeSimple($detectionwait)."', '".$db->escapeSimple($dest)."',".$legacy_email.",".$ring.")");
}

function fax_save_settings($settings){
	global $db;
	if (is_array($settings)) foreach($settings as $key => $value){
		sql("REPLACE INTO fax_details (`key`, `value`) VALUES ('".$key."','".$db->escapeSimple($value)."')");
	}

	needreload();
}

function fax_save_user($faxext,$faxenabled,$faxemail = '',$faxattachformat = 'pdf') {
	return FreePBX::Fax()->saveUser($faxext, $faxenabled, $faxemail, $faxattachformat);
}

function fax_sip_faxdetect(){
	global $asterisk_conf;
		return true;
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
function fax_file_convert($type, $in, $out = '', $keep_orig = false, $opts = array()) {
	global $amp_conf;
	//ensure file exists
	if (!is_file($in)) {
		return false;
	}

	//set out filename if not specified
	if (!$out) {
		switch ($type) {
			case 'pdf2tif':
			case 'ps2tif':
				$ext = '.tif';
				break;
			case 'tif2pdf':
				$ext = '.pdf';
				break;
		}
		$pathinfo = pathinfo($in);

		//php < 5.2 doesnt provide filename
		if (!isset($pathinfo['filename'])) {
			$pathinfo['filename']
				= substr($pathinfo['basename'], 0,
						strrpos($pathinfo['basename'],
							'.' . $pathinfo['extension']
						)
				);
		}

		$out = $pathinfo['dirname']
					. '/'
					. $pathinfo['filename']
					. $ext;
	}

	//if file exists, assume its been converted already
	if (file_exists($out)) {
		return $out;
	}

	//ensure cli command exists
	switch ($type) {
		case 'pdf2tif':
		case 'ps2tif':
			$gs = fpbx_which('gs');
			if (!$gs) {
				dbug('gs not found, not converting ' . $in);
				return $in;
			}
			$res = isset($opts['res']) ? $opts['res'] : "204x98";
			//http://www.soft-switch.org/spandsp_faq/ar01s14.html
			$gs = $gs . ' -q -dNOPAUSE -dBATCH -dAutoRotatePages=/All -dFIXEDMEDIA -dPDFFitPage -sColorConversionStrategy=Gray -dProcessColorModel=/DeviceGray -dCompatibilityLevel=1.4 -r'.$res.' ';
			break;
		case 'tif2pdf':
			$tiff2pdf = fpbx_which('tiff2pdf');
			if (!$tiff2pdf) {
				dbug('tiff2pdf not found, not converting ' . $in);
				return $in;
			}
			break;
	}

	//convert!
	switch ($type) {
		case 'pdf2tif':
		case 'ps2tif':
			$cmd = $gs
				. '-sDEVICE=tiffg4 '
				. '-sOutputFile=' . $out . ' ' . $in;
			break;
		case 'tif2pdf':
			$cmd = $tiff2pdf
					. ' -z '
					. '-c "PBXact by Schmooze Communications" '
					. '-a "' . $amp_conf['PDFAUTHOR'] . '" '
					. (isset($opts['title']) ? '-t "' . $opts['title'] . '" ' : '')
					. '-o ' . $out . ' ' . $in;
			break;
		default:
			break;
	}

	exec($cmd, $ret, $status);

	//remove original
	if ($status === 0 && !$keep_orig) {
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
function fax_tiffinfo($file, $opt = '') {
	//ensure file exists
	if (!is_file($file)) {
		return false;
	}

	$tiffinfo	= fpbx_which('tiffinfo');
	$info		= array();

	if (!$tiffinfo) {
		return false;
	}
	exec($tiffinfo . ' ' . $file, $output);

	if ($output && strpos($output[0], 'Not a TIFF or MDI file') === 0) {
		return false;
	}

	foreach ($output as $out) {
		$o = explode(':', $out, 2);
		$info[trim($o[0])] = isset($o[1]) ? trim($o[1]) : '';
	}

	if (!$info) {
		return false;
	}

	//special case prossesing
	//Page Number: defualt format = 0-0. Use only first set of digits, increment by 1
	$info['Page Number'] = explode('-', $info['Page Number']);
	$info['Page Number'] = $info['Page Number'][0] + 1;

	if ($opt) {
		return isset($info[$opt]) ? $info[$opt] : false;
	}

	return $info;
}
?>
