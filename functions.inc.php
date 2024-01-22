<?php
/* $Id */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//

// TODO: There is no hook on the _redirect_standard_helper function in the view.functions.php file.
function fax_getdest($exten) {
	return [\FreePBX::Fax()->getDest($exten)];
}

function fax_delete_incoming($extdisplay)
{
    \FreePBX::Modules()->deprecatedFunction();
    return \FreePBX::Fax()->deleteIncoming($extdisplay);
}

function fax_delete_user($faxext)
{
    \FreePBX::Modules()->deprecatedFunction();
    return \FreePBX::Fax()->deleteUser($faxext);
}

//check to see if any fax modules and licenses are loaded in to asterisk
function fax_detect($astver=null)
{
    \FreePBX::Modules()->deprecatedFunction();
    return \FreePBX::Fax()->faxDetect($astver);
}

function fax_get_destinations()
{
	\FreePBX::Modules()->deprecatedFunction();
    return \FreePBX::Fax()->get_destinations();
}

function fax_get_incoming($extension=null, $cidnum=null)
{
    \FreePBX::Modules()->deprecatedFunction();
    return \FreePBX::Fax()->getIncoming($extension, $cidnum);

}

function fax_get_user($faxext = '')
{
    \FreePBX::Modules()->deprecatedFunction();
    return \FreePBX::Fax()->getUser($faxext);

}

function fax_get_settings()
{
    \FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Fax()->getSettings();
}


function fax_get_config($engine){
	$fax_settings = [];
 global $version;
	global $ext;
	global $amp_conf;
	global $core_conf;
	global $astman;
	$faxC = \FreePBX::Fax();

	$fax = $faxC->faxDetect($version);
	$astman->database_deltree("FAX");
	// do not continue unless we have a fax module in asterisk
	if($fax['module'] && ((isset($fax['ffa']) && $fax['ffa']) || $fax['spandsp'])) {
		$t38_fb = ',f';
		$context = $faxC::ASTERISK_SECTION;
		$dests = $faxC->get_destinations();

		if($dests){
			foreach ($dests as $row) {
				$exten=$row['user'];
				$user = \FreePBX::Userman()->getUserByID($exten);
				if(!empty($user)) {
					$name = !empty($user['displayname']) ? $user['displayname'] : trim($user['fname'] . " " . $user['lname']);
					$name  = !empty($name) ? $name  : $user['username'];
					$ext->add($context, $exten, '', new ext_set('FAX_FOR',$name.' ('.$exten.')'));
				} else {
					$ext->add($context, $exten, '', new ext_set('FAX_FOR',$exten));
				}

				$ext->add($context, $exten, '', new ext_noop('Receiving Fax for: ${FAX_FOR}, From: ${CALLERID(all)}'));
				$ext->add($context, $exten, '', new ext_set('FAX_RX_USER', $exten));
				$ext->add($context, $exten, '', new ext_set('FAX_RX_EMAIL_LEN', strlen((string) $row['faxemail'])));
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
		$ext->add($context, $exten, '', new ext_noop('Receiving Fax for: ${FAX_FOR} , From: ${CALLERID(all)}'));
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
			$localstationid = $faxC->getSetting('localstationid');
			if(!empty($localstationid[0])) {
				$ext->add($context, $exten, '', new ext_set('FAXOPT(localstationid)', $localstationid[0]));
			}
			$ext->add($context, $exten, '', new ext_receivefax('${ASTSPOOLDIR}/fax/${UNIQUEID}.tif'.$t38_fb)); //receive fax, then email it on
			if (isset($fax['ffa']) && $fax['ffa']) {
				$ext->add($context, $exten, '', new ext_execif('$["${FAXSTATUS}"="" | "${FAXSTATUS}" = "FAILED" & "${FAXERROR}" = "INIT_ERROR"]','Set','FAXSTATUS=FAILED LICENSE MAY BE EXCEEDED check log errors'));
			}
			$ext->add($context, $exten, '', new ext_execif('$["${FAXSTATUS:0:6}"="FAILED" && "${FAXERROR}"!="INIT_ERROR"]','Set','FAXSTATUS="FAILED: error: ${FAXERROR} statusstr: ${FAXOPT(statusstr)}"'));

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
		$ext->add($context, $exten, '', new ext_noop_trace('PROCESSING FAX with status: [${FAXSTATUS}] for: [${FAX_FOR}], From: [${CALLERID(all)}]'));
		//delete is a variable so that other modules can prevent it should then need to prosses the file further
		$ext->add($context, $exten, 'delete_opt', new ext_set('DELETE_AFTER_SEND', 'true'));
		$ext->add($context, $exten, 'process', new ext_gotoif('$[ "${FAX_RX_EMAIL_LEN}" = "0" | "${FAX_RX_EMAIL_LEN}" = "" ]','noemail'));

		$ext->add($context, $exten, 'sendfax', new ext_system('${AMPBIN}/fax2mail.php --remotestationid "${FAXOPT(remotestationid)}" --user "${FAX_RX_USER}" --dest "${FROM_DID}" --callerid "${BASE64_ENCODE(${CALLERID(all)})}" --file ${ASTSPOOLDIR}/fax/${UNIQUEID}.tif --delete "${DELETE_AFTER_SEND}"'));

		$ext->add($context, $exten, 'end', new ext_macro('hangupcall'));

		$ext->add($context, $exten, 'noemail', new ext_noop('ERROR: No Email Address to send FAX: status: [${FAXSTATUS}],  From: [${CALLERID(all)}], trying system fax destination'));
		$ext->add($context, $exten, '', new ext_gotoif('$[ "${FAX_RX_EMAIL}" = "" ]', 'delfax'));

		// We can send a fax to the system dest!
		$ext->add($context, $exten, '', new ext_system('${AMPBIN}/fax2mail.php --remotestationid "${FAXOPT(remotestationid)}" --sendto "${FAX_RX_EMAIL}" --dest "${FROM_DID}" --callerid "${BASE64_ENCODE(${CALLERID(all)})}" --file ${ASTSPOOLDIR}/fax/${UNIQUEID}.tif --delete "${DELETE_AFTER_SEND}"'));
		$ext->add($context, $exten, '', new ext_macro('hangupcall'));

		// No system dest. Just delete.
		$ext->add($context, $exten, 'delfax', new ext_system('${AMPBIN}/fax2mail.php --file ${ASTSPOOLDIR}/fax/${UNIQUEID}.tif --delete "${DELETE_AFTER_SEND}"'));
		$ext->add($context, $exten, '', new ext_macro('hangupcall'));

		$ext->add($context, $exten, 'failed', new ext_noop('FAX ${FAXSTATUS} for: ${FAX_FOR} , From: ${CALLERID(all)}'),'process',101);
		$ext->add($context, $exten, '', new ext_macro('hangupcall'));

		$modulename = 'fax';
		$fcc = new featurecode($modulename, 'simu_fax');
		$fc_simu_fax = $fcc->getCodeActive();
		unset($fcc);

		if ($fc_simu_fax != '') {
			$default_fax_rx_email = $faxC->getSetting('FAX_RX_EMAIL','');
			$ext->addInclude('from-internal-additional', 'app-fax'); // Add the include from from-internal
			$ext->add('app-fax', $fc_simu_fax, '', new ext_setvar('FAX_RX_EMAIL', $default_fax_rx_email));
			$ext->add('app-fax', $fc_simu_fax, '', new ext_goto('1', 's', 'ext-fax'));
			$ext->add('app-fax', 'h', '', new ext_macro('hangupcall'));
		}
		// This is not really needed but is put here in
		// case some ever accidently switches the order below
		// when checking for this setting since $fax['module']
		// will be set there and the 2nd part never checked
		$fax_settings['force_detection'] = 'yes';
	} else {
		$fax_settings = $fax_settings = $faxC->getSettings();
	}
	if (($fax['module'] && ((isset($fax['ffa']) && $fax['ffa']) || $fax['spandsp'])) || 
			(isset($fax_settings['force_detection']) && $fax_settings['force_detection'] == 'yes')) {
		if (isset($core_conf) && is_a($core_conf, "core_conf")) {
			$core_conf->addSipGeneral('faxdetect','no');
		} else if (isset($core_conf) && is_a($core_conf, "core_conf")) {
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


function fax_hookGet_config($engine){
	$fax_settings = [];
 global $version;
	$faxC = \FreePBX::Fax();

	$fax = $faxC->faxDetect($version);
	if ($fax['module']) {
		$fax_settings['force_detection'] = 'yes';
	} else {
		$fax_settings = $faxC->getSettings();
	}
	if($fax_settings['force_detection'] == 'yes'){ //dont continue unless we have a fax module in asterisk
		global $ext;
		global $engine;
		$routes = $faxC->getIncoming();
		foreach($routes as $current => $route){
			if(isset($route['legacy_email']) && $route['legacy_email'] === 'NULL') { $route['legacy_email'] = null; }
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
				$ext->splice($context, $extension, 'dest-ext', new ext_setvar('FAX_DEST',str_replace(',','^',(string) $route['destination'])));
			} else {
				$ext->splice($context, $extension, 'dest-ext', new ext_setvar('FAX_DEST','ext-fax^s^1'));
				if ($route['legacy_email']) {
					$fax_rx_email = $route['legacy_email'];
				} else {
					$fax_rx_email = $faxC->getSetting('fax_rx_email','');;
				}
				$ext->splice($context, $extension, 'dest-ext', new ext_setvar('FAX_RX_EMAIL',$fax_rx_email));
			}
			//If we have fax incoming, we need to set fax detection to yes if we are on Asterisk 11 or newer
			$ext->splice($context, $extension, 'dest-ext', new ext_setvar('FAXOPT(faxdetect)', 'yes'));
			$ext->splice($context, $extension, 'dest-ext', new ext_answer(''));
			if(!empty($route['ring'])) {
				$ext->splice($context, $extension, 'dest-ext', new ext_playtones('ring'));
			}

			$ext->splice($context, $extension, 'dest-ext', new ext_wait($route['detectionwait']));
		}
	}
}


function fax_save_incoming($cidnum,$extension,$enabled,$detection,$detectionwait,$dest,$legacy_email,$ring=1)
{
    \FreePBX::Modules()->deprecatedFunction();
    return \FreePBX::Fax()->saveIncoming($cidnum, $extension, $enabled, $detection, $detectionwait, $dest, $legacy_email, $ring);
}

function fax_save_settings($settings)
{
	\FreePBX::Modules()->deprecatedFunction();
    return \FreePBX::Fax()->setSettings($settings);
}

function fax_save_user($faxext, $faxenabled, $faxemail = '', $faxattachformat = 'pdf')
{
	\FreePBX::Modules()->deprecatedFunction();
	return \FreePBX::Fax()->saveUser($faxext, $faxenabled, $faxemail, $faxattachformat);
}

function fax_file_convert($type, $in, $out = '', $keep_orig = false, $opts = [])
{
	\FreePBX::Modules()->deprecatedFunction();
    return \FreePBX::Fax()->fax_file_convert($type, $in, $out, $keep_orig, $opts);
}

function fax_tiffinfo($file, $opt = '')
{
	\FreePBX::Modules()->deprecatedFunction();
    return \FreePBX::Fax()->fax_tiffinfo($file, $opt);
}



function fax_dahdi_faxdetect(){
	/*
	 * kepping this always set to true for freepbx 2.7 as we cant currently properly detect this - MB
	 *
	 */
	return true;
}

function fax_sip_faxdetect(){
	global $asterisk_conf;
	return true;
}
