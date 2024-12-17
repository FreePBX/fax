#!/usr/bin/env php
<?php
//include freepbx configuration
$restrict_mods = ['fax' => true, 'userman' => true];
include_once '/etc/freepbx.conf';
\modgettext::push_textdomain("fax");

$mod_fax = \FreePBX::Fax();


$from = $mod_fax->getSetting("sender_address");
$var  = ['hostname' => gethostname(), 'fromuser' => _("Fax Service"), 'subject'  => ''];

if (!$from)
{
	$var['from_dn'] = "fax@freepbx.pbx";
}
elseif (preg_match( '/(.*)\s+\<(.*)\>/', (string) $from, $match))
{
	$var['fromuser'] = $match[1];
	$var['from_dn']  = $match[2];
}
else
{
	$var['from_dn'] = $from;
}

$var = array_merge($var, get_opt());

$var['callerid'] 		= isset($var['callerid']) ? base64_decode($var['callerid']) : '';
$var['callerid']		= empty($var['callerid']) || $var['callerid'] === true ? '' : $var['callerid'];//prevent callerid from being blank
$var['keep_file']		= !empty($var['delete']) && $var['delete'] == 'true' ? false : true;
$var['remotestationid'] = !empty($var['remotestationid']) ? $var['remotestationid'] : '';

if (empty($var['sendto']))
{
	$user = \FreePBX::Userman()->getUserByID($var['user']);
	if(empty($user['email']) && !$var['keep_file'])
	{
		die_fax(_('Email-fax dying, no destination found (User has no email!) and we arent keeping the file!'));
	}

	$var['to'] 			 = $user['email'];
	$var['attachformat'] = \FreePBX::Userman()->getCombinedModuleSettingByID($var['user'], 'fax', 'attachformat');
}
else
{
	$var['to'] = $var['sendto'];
	$user = ["displayname" => _("Fax Recipient")];
}

$var['attachformat'] = !empty($var['attachformat']) ? $var['attachformat'] : 'none';

//Condition for "Dial System Fax" feature code
if(empty($var['user']))
{
	$var['attachformat'] = 'pdf';
}

//double check some of the options
foreach ($var as $k => $v)
{
	if (!is_string($k))
	{
		continue;
	}
	switch ($k)
	{
		case 'file':
			if (!file_exists($var['file']))
			{
				die_fax(sprintf(_('Email-fax dying, file %s not found!'), $var['file']));
			}
		break;
		case 'subject':
			if (!$var['subject'])
			{
				if (isset($var['direction']) && $var['direction'] == 'outgoing')
				{
					if (isset($var['custom_subject']) && !empty($var['custom_subject'])) {
						$var['subject'] = $var['custom_subject'];
					} else {
						$var['subject'] = _('Outgoing fax results');
					}
				}
				else
				{
					if ($var['callerid'])
					{
						$var['subject'] = sprintf(_('New fax from: %s'), $var['callerid']);
					}
					else
					{
						$var['subject'] = _('New fax received');
					}
				}
			}
		break;
	}
}

if (isset($var['direction']) && $var['direction'] == 'outgoing')
{
	//TODO: Create template
	$msg  = sprintf(_('Sent to %s'), $var['dest']). "\n";
	$msg .= sprintf(_('Status: %s'), $var['status']). "\n";
	$msg .= sprintf(_('At: %s'), date('r')). "\n";
	$msg .= sprintf(_('On: %s'), $var['hostname']). "\n";
	if (!empty($user['displayname']))
	{
		$msg .= sprintf(_('For: %s'), $user['displayname']). "\n";
	}
}
else
{
	//TODO: Create template
	$callerid = !empty($var['callerid']) && !preg_match('/""\s*<>/',$var['callerid']) ? $var['callerid'] : $var['remotestationid'];

	if (!empty($callerid))
	{
		$msg = sprintf(_('Enclosed, please find a new fax from: %s'), $callerid);
	}
	else
	{
		$msg = _('Enclosed, please find a new fax');
	}
	$msg .= "\n";
	$msg .= sprintf(_('Received & processed: %s'), date('r')) . "\n";
	$msg .= sprintf(_('On: %s'), $var['hostname']) . "\n";
	$msg .= sprintf(_('Via: %s'), $var['dest']) . "\n";
	if (!empty($user['displayname'])) 
	{
		$msg .= sprintf(_('For: %s'), $user['displayname']) . "\n";
	}
}

$tif = $var['file'];
if(!empty($var['to']))
{
	//build email
	$email = new \CI_Email();

	$email->from($var['from_dn'], $var['fromuser']);
	$email->to($var['to']);
	$email->subject($var['subject']);
	$email->message($msg);

	switch ($var['attachformat'])
	{
		case 'both':
			$pdf = $mod_fax->fax_file_convert('tif2pdf', $var['file'], '', true);
			$email->attach($pdf);
			$email->attach($tif);
			break;
		case 'tif':
			$email->attach($tif);
			break;
		case 'pdf':
			$pdf = $mod_fax->fax_file_convert('tif2pdf', $var['file'], '', true);
			$email->attach($pdf);
			break;
		case 'none':
			break;
	}
	$email->send();
}

if ($var['keep_file'] === false)
{
	unlink($tif);
	if(isset($pdf))
	{
		unlink($pdf);
	}
}

function die_fax($error): never
{
	dbug('email-fax', $error);
	freepbx_log(FPBX_LOG_ERROR, $error);
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
function get_opt($noopt = []) {
	$result = [];
	$params = $GLOBALS['argv'];

	for ($i = 0; $i < $GLOBALS['argc']; $i++) {
		$p = $params[$i];
		if (isset($p[0]) && $p[0] == '-') {
			$pname = substr((string) $p, 1);
			$value = true;
			if ($pname[0] == '-') {
				// long-opt (--<param>)
				$pname = substr($pname, 1);
				if (str_contains((string) $p, '=')) {
					// value specified inline (--<param>=<value>)
					[$pname, $value] = explode('=', substr((string) $p, 2), 2);
				}
			}
			// check if next parameter is a descriptor or a value
			$nextparm = $params[$i + 1] ?? [];
			if (!in_array($pname, $noopt) && $value === true && $nextparm !== false && isset($nextparm[0]) && $nextparm[0] != '-') {
				$value = $params[++$i];
			}
			$result[$pname] = $value;
		} else {
			// param doesn't belong to any option
			$result[] = $p;
		}
	}
	return $result;
}
