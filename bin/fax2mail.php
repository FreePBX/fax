#!/usr/bin/env php
<?php
//include freepbx configuration
$restrict_mods = array('fax' => true, 'userman' => true);
include_once '/etc/freepbx.conf';
\modgettext::push_textdomain("fax");

$var['hostname'] 	= gethostname();
$from	 		= sql('SELECT value FROM fax_details WHERE `key` = "sender_address"','getOne');
$var['fromuser']	= "Fax Service";
if (!$from) {
	$var['from_dn'] = "fax@freepbx.pbx";
} elseif (preg_match( '/(.*)\s+\<(.*)\>/', $from, $match)) {
	$var['fromuser'] = $match[1];
	$var['from_dn'] = $match[2];
} else {
	$var['from_dn'] = $from;
}

$var['subject']		= '';
$var			= array_merge($var, get_opt());
$var['callerid'] = base64_decode($var['callerid']);
$var['callerid']	= empty($var['callerid']) || $var['callerid'] === true ? '' : $var['callerid'];//prevent callerid from being blank
$var['keep_file']	= !empty($var['delete']) && $var['delete'] == 'true' ? false : true;
$var['remotestationid'] = !empty($var['remotestationid']) ? $var['remotestationid'] : '';

if (empty($var['sendto'])) {
	$user = FreePBX::Userman()->getUserByID($var['user']);
	if(empty($user['email']) && !$var['keep_file']) {
		die_fax('email-fax dying, no destination found (User has no email!) and we arent keeping the file!');
	}
	$var['to'] = $user['email'];
	$var['attachformat'] = FreePBX::Userman()->getCombinedModuleSettingByID($var['user'], 'fax', 'attachformat');
} else {
	$var['to'] = $var['sendto'];
	$user = array("displayname" => "Fax Recipient");
}

$var['attachformat'] = !empty($var['attachformat']) ? $var['attachformat'] : 'pdf';

//double check some of the options
foreach ($var as $k => $v) {
	if (!is_string($k)) {
		continue;
	}
	switch ($k) {
		case 'file':
			if (!file_exists($var['file'])) {
				die_fax('email-fax dying, file ' . $var['file'] . ' not found!');
			}
		break;
		case 'subject':
			if (!$var['subject']) {
				if (isset($var['direction']) && $var['direction'] == 'outgoing') {
					$var['subject'] = _('Outgoing fax results');
				} else {
					if ($var['callerid']) {
						$var['subject'] = sprintf(_('New fax from: %s'),$var['callerid']);
					} else {
						$var['subject'] = _('New fax received');
					}
				}

			}
			break;
	}
}

if (isset($var['direction']) && $var['direction'] == 'outgoing') {
	$msg = 'Sent to ' . $var['dest'] . "\n";
	$msg .= 'Status: ' . $var['status'] . "\n";
	$msg .= 'At: ' . date('r') . "\n";
	$msg .= 'On: ' . $var['hostname'] . "\n";
	if (!empty($user['displayname'])) {
		$msg .= 'For: ' . $user['displayname'] . "\n";
	}
} else {
	$callerid = !empty($var['callerid']) && !preg_match('/""\s*<>/',$var['callerid']) ? $var['callerid'] : $var['remotestationid'];

	if (!empty($callerid)) {
		$msg = sprintf(_('Enclosed, please find a new fax from: %s'), $callerid);
	} else {
		$msg = _('Enclosed, please find a new fax');
	}
	$msg .= "\n";
	$msg .= sprintf(_('Received & processed: %s'),date('r')) . "\n";
	$msg .= _('On').': ' . $var['hostname'] . "\n";
	$msg .= _('Via').': ' . $var['dest'] . "\n";
	if (!empty($user['displayname'])) {
		$msg .= _('For').': ' . $user['displayname'] . "\n";
	}
}

$tif = $var['file'];
if(!empty($var['to'])) {
	//build email
	$email = new CI_Email();

	$email->from($var['from_dn'], $var['fromuser']);
	$email->to($var['to']);
	$email->subject($var['subject']);
	$email->message($msg);

	switch ($var['attachformat']) {
	case 'both':
		$pdf = fax_file_convert('tif2pdf', $var['file'], '', true);
		$email->attach($pdf);
		$email->attach($tif);
		break;
	case 'tif':
		$email->attach($tif);
		break;
	case 'pdf':
		$pdf = fax_file_convert('tif2pdf', $var['file'], '', true);
		$email->attach($pdf);
		break;
	}

	$email->send();
}

if ($var['keep_file'] === false) {
	unlink($tif);
	if(isset($pdf)) {
		unlink($pdf);
	}
}

function die_fax($error) {
	dbug('email-fax', $error);
	die($error);
}

/**
 * Parses $GLOBALS['argv'] for parameters and assigns them to an array.
 *
 * Supports:
 * -e
 * -e <value>
 * --long-param
 * --long-param=<value>
 * --long-param <value>
 * <value>
 *
 * @param array $noopt List of parameters without values
 */
function get_opt($noopt = array()) {
	$result = array();
	$params = $GLOBALS['argv'];

	while (list($tmp, $p) = each($params)) {
		if ($p{0} == '-') {
			$pname = substr($p, 1);
			$value = true;
			if ($pname{0} == '-') {
				// long-opt (--<param>)
				$pname = substr($pname, 1);
				if (strpos($p, '=') !== false) {
					// value specified inline (--<param>=<value>)
					list($pname, $value) = explode('=', substr($p, 2), 2);
				}
			}
			// check if next parameter is a descriptor or a value
			$nextparm = current($params);
			if (!in_array($pname, $noopt) && $value === true && $nextparm !== false && $nextparm{0} != '-') {
				list($tmp, $value) = each($params);
			}
			$result[$pname] = $value;
		} else {
			// param doesn't belong to any option
			$result[] = $p;
		}
	}
	return $result;
}

?>
