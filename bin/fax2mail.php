#!/usr/bin/php
<?php
//include freepbx configuration
$restrict_mods = array('fax' => true);
if (!@include_once(getenv('FREEPBX_CONF') ? getenv('FREEPBX_CONF') : '/etc/freepbx.conf')) {
	include_once('/etc/asterisk/freepbx.conf');
}
\modgettext::push_textdomain("fax");

$var['hostname'] 	= gethostname();
$var['from']		= sql('SELECT value FROM fax_details WHERE `key` = "sender_address"','getOne');
$var['from']		= $var['from'] ? $var['from'] : 'fax@freepbx.pbx';
$var['subject']		= '';
$var 				= array_merge($var, get_opt());
$var['callerid']	= empty($var['callerid']) || $var['callerid'] === true ? '' : $var['callerid'];//prevent callerid from being blank
$var['keep_file']	= !empty($var['delete']) && $var['delete'] == 'true' ? false : true;
$var['attachformat']	= !empty($var['attachformat']) ? $var['attachformat'] : 'pdf';
$var['remotestationid'] = !empty($var['remotestationid']) ? $var['remotestationid'] : '';

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
		case 'to':
			if(empty($var['to'])) {
				die_fax('email-fax dying, no destination found ($var[\'to\'] is empty)');
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
	if ($var['exten']) {
		$msg .= 'For extension: ' . $var['exten'] . "\n";
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
	if ($var['exten']) {
		$user = \FreePBX::Userman()->getUserByID($var['exten']);
		if(!empty($user)) {
			$name = !empty($user['displayname']) ? $user['displayname'] : trim($user['fname'] . " " . $user['lname']);
			$name  = !empty($name) ? $name  : $user['username'];
		} else {
			$name = $var['exten'];
		}

		$msg .= _('For User').': ' . $name . "\n";
	}
}


//build email
$email = new CI_Email();

$email->from($var['from']);
$email->to($var['to']);
$email->subject($var['subject']);
$email->message($msg);

$tif = $var['file'];
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
