#!/usr/bin/php
<?php
//include freepbx configuration 
$restrict_mods = array('fax' => true);
if (!@include_once(getenv('FREEPBX_CONF') ? getenv('FREEPBX_CONF') : '/etc/freepbx.conf')) {
    include_once('/etc/asterisk/freepbx.conf');
}

$var['hostname'] 	= gethostname();
$var['from']		= sql('SELECT value FROM fax_details WHERE `key` = "sender_address"','getOne');
$var['from']		= $var['from'] ? $var['from'] : 'fax@freepbx.pbx';
$var['subject']		= '';
$var 				= array_merge($var, get_opt());
$var['callerid']	= $var['callerid'] === true ? '' : $var['callerid'];//prevent callerid from being blank
$var['keep_file']	= $var['delete'] == 'true' ? false : true;

//double check some of the options
foreach ($var as $k => $v) {
	switch ($k) {
		case 'file':
			if (!file_exists($var['file'])) {
				die_fax('email-fax dying, file ' . $file . ' not found!');
			}
			break;
		case 'to':
			if(!$var['to']) {
				die_fax('email-fax dying, no destination found ($var[\'to\'] is empty)');
			}
			break;
		case 'subject':
			if (!$var['subject']) {
				if ($var['callerid']) {
					$var['subject'] = 'New fax from: ' . $var['callerid'];
				} else {
					$var['subject'] = 'New fax received';
				}
				
			}
			break;
	}
}

//if file is a tif, try to convert it to a pdf
$var['file'] = fax_file_convert('tif2pdf', $var['file'], '', $var['keep_file']);

$msg = 'Enclosed, please find a new fax ';
if ($var['callerid']) {
	$msg .= 'from: ' . $var['callerid'] ;
} 
$msg .= "\n";
$msg .= 'Received & processed: ' . date('r') . "\n";
$msg .= 'On: ' . $var['hostname'] . "\n";
$msg .= 'Via: ' . $var['dest'] . "\n";
if ($var['exten']) {
	$msg .= 'For extension: ' . $var['exten'] . "\n";
}


//build email
$email = new CI_Email();

$email->from($var['from']);
$email->to($var['to']);
$email->subject($var['subject']);
$email->message($msg);
$email->attach($var['file']);
$email->send();

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