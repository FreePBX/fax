<?php
/* $Id */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//

// TODO: There is no hook on the _redirect_standard_helper function in the view.functions.php file.
function fax_getdest($exten) {
	return array(\FreePBX::Fax()->getDest($exten));
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

// Migrate to doDialplanHook
//function fax_get_config($engine){}

// Migrate to doDialplanHook
//function fax_hookGet_config($engine){}


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

function fax_file_convert($type, $in, $out = '', $keep_orig = false, $opts = array())
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