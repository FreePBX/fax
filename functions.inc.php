<?php 
/* $Id */

function fax_applyhooks() {
	global $currentcomponent;
	// Add the 'process' function - this gets called when the page is loaded, to hook into 
	// displaying stuff on the page.
	$currentcomponent->addguifunc('fax_configpageload');
}

// This is called before the page is actually displayed, so we can use addguielem(). draws hook on the extensions/users page
function fax_configpageload() {
	global $currentcomponent;
	global $display;
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	if ($display == 'extensions' || $display == 'users') {
		if($extdisplay){extract(fax_get_user($extdisplay));}//get settings in to variables
		$section = _('Fax');
		$toggleemail='if($(this).attr(\'checked\')){$(\'[id^=fax]\').removeAttr(\'disabled\');}else{$(\'[id^=fax]\').attr(\'disabled\',\'true\');$(this).removeAttr(\'disabled\');}';
		//check for fax prequsits, and alert the user if something is amiss
		$fax=fax_detect();
		if(!$fax['module']){//missing modules
			$currentcomponent->addguielem($section, new gui_label('error',_('<font color="red">'._('ERROR: Fax modules missing! Fax-related dialplan will <strong>NOT</strong> be generated! Please contact your vendor for more information.').'</font>')));
		}elseif($fax['module'] == 'res_fax' && $fax['license'] < 1){//missing licese
			$currentcomponent->addguielem($section, new gui_label('error',_('<font color="red">'._('ERROR: Fax license missing! Fax-related dialplan will <strong>NOT</strong> be generated! Please contact your vendor for more information.').'</font>')));
		}
		
		$currentcomponent->addguielem($section, new gui_checkbox('faxenabled',$faxenabled,_('Enabled'), _('Enable this user to recive faxes'),'true','',$toggleemail));
		$currentcomponent->addguielem($section, new gui_textbox('faxemail', $faxemail, _('Fax Email'), _('Enter an email address where faxes sent to this extension will be delivered.'), '!isEmail()', _('Please Enter a valid email address for fax delivery.'), TRUE, '', ($faxenabled == 'true')?'':'true'));
	}
}

function fax_configpageinit($pagename) {
	global $currentcomponent;
	// On a 'new' user, 'tech_hardware' is set, and there's no extension. 
	if (($_REQUEST['display'] == 'users'||$_REQUEST['display'] == 'extensions')&& isset($_REQUEST['extdisplay']) && $_REQUEST['extdisplay'] != '') {
	$currentcomponent->addprocessfunc('fax_configpageload', 1);
	$currentcomponent->addprocessfunc('fax_configprocess', 1);
	}
}

//prosses recived arguments
function fax_configprocess() {
	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$ext = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:$_REQUEST['extension'];
	$faxenabled = isset($_REQUEST['faxenabled'])?$_REQUEST['faxenabled']:null;
	$faxemail = isset($_REQUEST['faxemail'])?$_REQUEST['faxemail']:null;
	if ($action == 'edit'){fax_save_user($ext,$faxenabled,$faxemail);}
}

function fax_dahdi_faxdetect(){
	global $asterisk_conf;
	$faxdetect=false;
	extract(parse_ini_file($asterisk_conf['astetcdir'].'/'.(ast_with_dahdi()?'chan_dahdi':'zapata').'.conf'));
	return (isset($faxdetect) && ($faxdetect == 'incoming' || $faxdetect == 'both'));
}

function fax_delete_incoming($extdisplay){
	$opts=explode('/', $extdisplay);$extension=$opts['0'];$cidnum=$opts['1']; //set vars
	sql("DELETE FROM fax_incoming WHERE cidnum = '".mysql_real_escape_string($cidnum)."' and extension = '".mysql_real_escape_string($extension)."'");
}

function fax_destinations(){
	global $module_page;

	foreach (fax_get_destinations() as $row) {
		$extens[] = array('destination' => 'ext-fax,' . $row['user'] . ',1', 'description' => $row['name'].' ('.$row['user'].')', 'category' => _('Fax Recipient'));
	}
	return isset($extens)?$extens:null;
}

//check to see if any fax modules and licenses are loaded in to asterisk
function fax_detect(){
	global $amp_conf;
	global $astman;
	$fax=array();
	$appfax = $recivefax = false;//return false by default in case asterisk isnt reachable
	if ($amp_conf['AMPENGINE'] == 'asterisk' && isset($astman) && $astman->connected()) {
		//xhexk for fax modules
		$app = $astman->send_request('Command', array('Command' => 'module show like app_fax.so'));
		if (preg_match('/1 modules loaded/', $app['data'])){$fax['module']='app_fax';}
		$recive = $astman->send_request('Command', array('Command' => 'module show like res_fax.so'));
		if (preg_match('/1 modules loaded/', $recive['data'])){$fax['module']='res_fax';}
		//get license count
		$lic = $astman->send_request('Command', array('Command' => 'fax show stats'));
		foreach(explode("\n",$lic['data']) as $licdata){
		$d=explode(':',$licdata);
		$data[trim($d['0'])]=isset($d['1'])?trim($d['1']):null;
		}
		$fax['license']=$data['Licensed Channels'];
	}
	return $fax;
}

function fax_get_config($engine){
	$fax=fax_detect();
	if($fax['module'] == 'app_fax' || $fax['module'] == 'res_fax'){ //dont continue unless we have a fax module in asterisk
		global $ext;
		global $amp_conf;
		
		$sender_address=sql('SELECT value FROM fax_details WHERE `key` = \'sender_address\'','getRow');

		$context='ext-fax';
		foreach (fax_get_destinations() as $row) {
			$exten=$row['user'];
			$ext->add($context, $exten, '', new ext_noop('Reciving Fax for Fax Recipient: '.$row['name'].' ('.$row['user'].'), From: ${CALLERID(all)}'));
			$ext->add($context, $exten, '', new ext_set('TO', '"'.$row['faxemail'].'"'));			
			$ext->add($context, $exten, 'receivefax', new ext_receivefax('${ASTSPOOLDIR}/fax/${UNIQUEID}.tif')); //recive fax, then email it on
		}
		$ext->add($context, 'h', '', new ext_execif('$["${TO}" != ""]','system','"${ASTVARLIBDIR}/bin/fax-process.pl --to ${TO} --from '.$sender_address['0'].' --dest ${FROM_DID} --subject New fax from ${URIENCODE(${CALLERID(all)})} --attachment fax_${URIENCODE(${CALLERID(number)})}.pdf --type application/pdf --file ${ASTSPOOLDIR}/fax/${UNIQUEID}.tif"'));
		//legacy fax extensions for all incoming contexts
		$ext->add('ext-did-0001', 'fax', '', new ext_goto('${FAX_DEST}'));
		$ext->add('ext-did-0002', 'fax', '', new ext_goto('${FAX_DEST}'));
		$ext->add('ext-did', 'fax', '', new ext_goto('${FAX_DEST}'));
		//write out res_fax.conf and res_fax_digium.conf
		fax_write_conf();
	}
}


function fax_get_destinations(){
	global $db;
	$sql = "SELECT fax_users.user,fax_users.faxemail,users.name FROM fax_users, users where fax_users.faxenabled = 'true' and users.extension = fax_users.user";
	$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	if(DB::IsError($results)) {
		die_freepbx($results->getMessage()."<br><br>Error selecting from fax");	
	}
	/*
	 *
	 * this may or may not work to include a system default destination
	 * Rememebr to reenable defautl destination in the gui as well
	 *	 	 	 
	//get system default fax destination
	$sql='SELECT * FROM fax_details WHERE `key` = ? OR `key` = ?';
	$system = $db->getAssoc($sql,  false, array('system_instance', 'system_fax2email'), DB_FETCHMODE_ASSOC);
	if ($system_fax2email != 'disabled'){// if system default is enabled
		if($system_fax2email == 'system'){
			$sys=array('user' => 'system', 'faxemail' => $systemfax2email, 'name' => 'System Default');
		}else{//find user, loop thru the $result of the previous fetch which lists all users/emails
			foreach($result as $res => $user){
				if ($user['user'] == $system_instance){
					$systemfax2email = $user['faxemail'];
					break;
				}
			} 
			$sys=array('user' => 'system', 'faxemail' => $systemfax2email, 'name' => 'System Default');
		}
		array_unshift($results, $sys);
	}
	*
	*
	*/
	return $results;
}

function fax_get_incoming($extension=null,$cidnum=null){
	global $db;
	if($extension || $cidnum){
		$sql="SELECT * FROM fax_incoming WHERE extension = ? AND cidnum = ?";
		$settings = $db->getRow($sql, array($extension, $cidnum), DB_FETCHMODE_ASSOC);		
	}else{
		$sql="SELECT * FROM fax_incoming";
		$settings = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	}
	return $settings;
}

function fax_get_user($faxext){
	global $db;
	if($faxext){
		$sql="SELECT * FROM fax_users WHERE user = '".$faxext."'";
		$settings = $db->getRow($sql, DB_FETCHMODE_ASSOC);
	}else{
		$sql="SELECT * FROM fax_users";
		$settings = $db->getAll($sql, DB_FETCHMODE_ASSOC);
	}
	if(!is_array($settings)){$settings=array();}//make sure were retuning an array (even if its blank)
	return $settings;
}

function fax_get_settings(){
	$settings = sql('SELECT * FROM fax_details', 'getAssoc', 'DB_FETCHMODE_ASSOC');
	foreach($settings as $setting => $value){
		$set[$setting]=$value['0'];
	}
	if(!is_array($set)){$set=array();}//never return a null value
	return $set;
}


function fax_hook_core($viewing_itemid, $target_menuid){
	//hmm, not sure why engine_getinfo() isnt being called here?! should probobly read: $info=engine_getinfo();
	//this is what serves fax code to inbound routing
	$tabindex=null;
	extract($_REQUEST);
	//if were editing, get save parms
	if (isset($type) && $type != 'setup'){
		if(!$extension && !$cidnum){//set $extension,$cidnum if we dont already have them
			$opts=explode('/', $extdisplay);$extension=$opts['0'];$cidnum=$opts['1'];
		}
		extract(fax_get_incoming($extension,$cidnum));
	}else{
	$faxenabled=$faxdetection=$faxdetectionwait=$faxdestination='';
	}
	//test to ensure detection type is still valid - clear varaible otherwise
	if($faxdetection == 'dahdi'){
		if(!fax_dahdi_faxdetect()){
			$faxenabled=$faxdetection=$faxdetectionwait=$faxdestination='';
		}
	}
	if($faxdetection == 'sip'){
		if(!($info['version'] >= "1.6.2") || !fax_sip_faxdetect()){
			$faxenabled=$faxdetection=$faxdetectionwait=$faxdestination='';
		}
	}

	$html = '';
	if ($target_menuid == 'did')	{
		//kill legacyfax2.5 gui, if its still in for some reason
		$html='<script type="text/javascript">$(document).ready(function(){
		$("tr:has(h5:contains(Fax Handling))").nextAll(":lt(5)").andSelf().hide();
		$("input[name=Submit]").click(function(){
			if($("input[name=faxenabled]:checked").val()=="true" && !$("input[name=gotoFAX]:checked").val()){//ensure the user selected a fax destination
			alert('._('"You have selected Fax Detection on this route. Please select a valid destination to route calls detected as faxes to."').');return false;
			}
		})
		});
		</script>';
		$html .= '<tr><td colspan="2"><h5>';
		$html .= _("Fax Detect");
		$html .= '<hr></h5></td></tr>';
		$fax=fax_detect();
		if(!$fax['module']){//error message if there are no modules loaded in asterisk
			$html .= '<table><tr><td><style>.faxerror{color:red; '.(($faxenabled == 'false')?'':'display: none;').'}</style><span class=faxerror>'._('It seems that you dont have fax receving modules installed in Asterisk or Asterisk is unreachable.<br/> Fax-related dialplan will <strong>NOT</strong> be generated!<br /> Please contact your vendor for more information.').'</span></td></tr></table>';
		}
		if($fax['module'] == 'res_fax' && $fax['license'] < 1){//error message if there are no fax licenses
			$html .= '<table><tr><td><style>.faxerror{color:red; '.(($faxenabled == 'false')?'':'display: none;').'}</style><span class=faxerror>'._('It seems that you dont have any fax licenses on this system. Fax-related dialplan will <strong>NOT</strong> be generated!<br /> Please contact your vendor for more information.').'</span></td></tr></table>';
		}
		$html .= '<tr>';
		$html .= '<td><a href="#" class="info">';
		$html .= _("Detect Faxes").'<span>'._("Attemp to detect faxes on this DID.<ul><li>No: No attempts are made to auto-determain the call type; all calls sent to destination below. Use this option if this DID is used exclusevly for voice OR fax.</li><li>Yes: try to auto determain the type of call; route to the fax destination if call is a fax, otherwise send to regular destination. Use this option if you receive both voice and fax calls on this line</li></ul>").'.</span></a>:</td>';
		//js to show/hide the detection type/time/destination
		$js = "if ($('input[name=extension]').val() == ''){//disable if there is no did
							alert('"._('Fax detection can only be enabled when the Inbound Route contains a DID')."');return false;
					}
					if ($(this).val() == 'true'){
						$('.faxdetect').slideDown();
						$('select[name=faxdetection]').trigger('click');
					}else{
						$('.faxdetect').slideUp();
						$('select[name=faxdetection]').trigger('click');
					}
					if(\$(this).val() == 'true'){\$('.faxerror').show();}else{\$('.faxerror').hide();//show error notice if it exists, only when using faxdetect
					}";
		$html .= '<td><input type="radio" name="faxenabled" value="false" CHECKED onclick="'.$js.'"/>No';
		$html .= '<input type="radio" name="faxenabled" value="true" '.(($faxenabled == 'true')?'CHECKED':'').' onclick="'.$js.'"/>Yes</td></tr>';
		$html .= '</table>';
		
		//fax detection+destinations
		$html .= '<table class=faxdetect '.($faxdetection?'':'style="display: none;"').'>';	
		$info =	engine_getinfo();
		$html .= '<tr><td width="156px"><a href="#" class="info">'._("Fax Detection type").'<span>'._("Type of fax detection to use.<ul><li>".(ast_with_dahdi()?"Dahdi":"Zaptel").": use ".(ast_with_dahdi()?"Dahdi":"Zaptel")." fax detection; requires 'faxdetect=' to be set to 'incoming' or 'both' in ".(ast_with_dahdi()?"dahdi":"zaptel").".conf</li><li>Sip: use sip fax detection (t38). Requires asterisk 1.6.2 or greater and 'faxdetect=yes' in the sip config files</li></ul>").'.</span></a>:</td>';
		$html .= '<td><select name="faxdetection" tabindex="'.++$tabindex.'">';
		//$html .= '<option value="Auto"'.($faxdetection == 'auto' ? 'SELECTED' : '').'>'. _("Auto").'</option>';<li>Auto: allow the system to chose the best fax detection method</li>
		$html .= '<option value="dahdi" '.($faxdetection == 'dahdi' ? 'SELECTED' : '').' '.(fax_dahdi_faxdetect()?'':'disabled').'>'. _((ast_with_dahdi()?"Dahdi":"Zaptel")).'</option>';
		$html .= '<option value="sip" '.($faxdetection == 'sip' ? 'SELECTED' : '').' '.((($info['version'] >= "1.6.2") && fax_sip_faxdetect())?'':'disabled').'>'. _("Sip").'</option>';
/*
 * code for nvfaxdetect. I'm not sure if we should be offering this, 
 * although it probobly works. its here in case someone wants to test/include it
 * 		//check for nvfaxdetect
		if ($amp_conf['AMPENGINE'] == 'asterisk' && version_compare($version, '1.4', 'le') && isset($astman) && $astman->connected()) {
			$response = $astman->send_request('Command', array('Command' => 'module show like app_nv_faxdetect.so'));
			if (preg_match('/1 modules loaded/', $response['data'])) {
				$html .= '<option value="nvfax"'.($faxdetection == 'nvfax' ? 'SELECTED' : '').'>'. _("NVFax").'</option>';
			}
		}
		$html .= '</select></td></tr>';
*/	
		//if we cant find ANY valid faxdetect type, then dont show options	
		$html .= '<tr class=faxdetectionwait '.(fax_detect()&& fax_dahdi_faxdetect()?'':'style="display: none;"').'><td><a href="#" class="info">'._("Fax Detection Time").'<span>'._('How long to wait and try to detect fax. Please note that callers to a '.(ast_with_dahdi()?"dahdi":"zaptel").' channel will hear ringing for this amount of time (i.e. the system wont "answer" the call, it will just play ringing)').'.</span></a>:</td>';
		$html .= '<td><select name="faxdetectionwait" tabindex="'.++$tabindex.'">';
		if(!$faxdetectionwait){$faxdetectionwait=4;}//default wait time is 4 second
		for($i=2;$i < 11; $i++){
			$html .= '<option value="'.$i.'" '. ($faxdetectionwait == $i ? 'SELECTED' : '').'>'. $i.'</option>';	
		}
		//if we cant find ANY valid faxdetect type, then dont show options	
		$html .= '</select></td></tr>';
		$html .= '<tr class=faxdestination '.(fax_detect()?'':'style="display: none;"').'><td><a href="#" class="info">'._("Fax Destination").'<span>'._('Where to send the call if we detect that its a fax').'.</span></a>:</td>';
		$html .= '</tr>';
		$html .= fax_detect()?drawselects(isset($faxdestination)?$faxdestination:null,'FAX'):'';
		$html .= '</table>';
		$html .= '<table>';
	}
	return $html;
}

function fax_hookGet_config($engine){
	$fax=fax_detect();
	if($fax['module'] == 'app_fax' || $fax['module'] == 'res_fax'){ //dont continue unless we have a fax module in asterisk
		global $ext;
		global $engine;
		$routes=fax_get_incoming();
		foreach($routes as $current => $route){
			if($route['faxenabled'] == 'true'){
				if($route['cidnum']){
					$context='ext-did-0001';
					$extension=$route['extension'].'/'.$route['cidnum'];
				}else{
					$context='ext-did-0002';
					$extension=$route['extension'];
				}
				$ext->splice($context, $extension, 'dest-ext', new ext_setvar('FAX_DEST','"'.$route['faxdestination'].'"'));
				$ext->splice($context, $extension, 'dest-ext', new ext_answer(''));
				$ext->splice($context, $extension, 'dest-ext', new ext_noop('Waiting for '.$route['faxdetectionwait'].' seconds, trying to detect fax'));
				$ext->splice($context, $extension, 'dest-ext', new ext_wait($route['faxdetectionwait']));
				$ext->remove($context, $extension, 'dest-ext'); 
			}
		}
	}
}

function fax_hookProcess_core(){
	extract($_REQUEST);//get all the variables passed on submit/page load
	if ($display == 'did'){
		//remove mature entry on edit or delete
		if (isset($action) && (($action == 'edtIncoming')||($action == 'delIncoming')) ){fax_delete_incoming($extdisplay);}
		if (isset($faxenabled, $extension) && $faxenabled == 'true'){
			$dest=$gotoFAX.'FAX';
			fax_save_incoming($cidnum,$extension,$faxenabled,$faxdetection,$faxdetectionwait,$$dest);
		}
	}
}


function fax_save_incoming($cidnum,$extension,$faxenabled,$faxdetection,$faxdetectionwait,$dest){
	sql("INSERT INTO fax_incoming (cidnum, extension, faxenabled, faxdetection, faxdetectionwait, faxdestination) VALUES ('".mysql_real_escape_string($cidnum)."', '".mysql_real_escape_string($extension)."', '".mysql_real_escape_string($faxenabled)."', '".mysql_real_escape_string($faxdetection)."', '".mysql_real_escape_string($faxdetectionwait)."', '".mysql_real_escape_string($dest)."')");
}

function fax_save_settings($settings){
	foreach($settings as $key => $value){
		sql("REPLACE INTO fax_details (`key`, `value`) VALUES ('".$key."','".mysql_real_escape_string($value)."')");
	}
}

function fax_save_user($faxext,$faxenabled,$faxemail){
	$faxext=mysql_real_escape_string($faxext);
	$faxenabled=mysql_real_escape_string($faxenabled);
	$faxemail=mysql_real_escape_string($faxemail);
	sql('REPLACE INTO fax_users (user, faxenabled, faxemail) VALUES ("'.$faxext.'","'.$faxenabled.'","'.$faxemail.'")');
}

function fax_sip_faxdetect(){
	global $asterisk_conf;
	//these files probobly shouldnt be hardcoded
	$files=array('sip_general_additional.conf','sip_general_custom.conf','sip_custom.conf');
	foreach($files as $file){$set.=parse_ini_file($file);}//read setting from files
	return ($set['faxdetect'] == 'yes');
}

//write out res_fax.conf and res_fax_digium.conf
function fax_write_conf(){
	global $amp_conf, $WARNING_BANNER;
	$set=fax_get_settings();
	//res_fax.conf
	$data=$WARNING_BANNER;
	$data.="[general]\n";
	$data.="#include res_fax_custom.conf\n";
	$data.='minrate='.$set['minrate']."\n";
	$data.='maxrate='.$set['maxrate']."\n";
	$file=fopen($amp_conf['ASTETCDIR'].'/res_fax.conf','w');
	fwrite($file, $data);
	fclose($file);
	
	//res_fax_digium.conf
	$data=$WARNING_BANNER;
	$data.="[general]\n";
	$data.="#include res_fax_digium_custom.conf\n";
	$data.='ecm='.$set['ecm']."\n";
	$file=fopen($amp_conf['ASTETCDIR'].'/res_fax_digium.conf','w');
	fwrite($file, $data);
	fclose($file);
}
?>