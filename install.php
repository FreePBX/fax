<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }

//for translation only
if (false) {
_("Dial System FAX");
}

global $db;


$table = FreePBX::Database()->migrate("fax_users");
$cols = array(
  "user" => array(
    "type" => "string",
    "length" => 15,
    "notnull" => false
  ),
  "faxenabled" => array(
    "type" => "string",
    "length" => 10,
    "notnull" => false,
  ),
  "faxemail" => array(
    "type" => "text",
    "notnull" => false,
  ),
  "faxattachformat" => array(
    "type" => "string",
    "length" => 10,
    "notnull" => false,
  )
);
$indexes = array(
    "user" => array(
        "type" => "unique",
        "cols" => array(
            "user"
        )
    )
);
$table->modify($cols, $indexes);
unset($table);




/* migrate simu_fax from core to fax module, including in miscdests module in case it is being used as a destination.
   this migration is a bit "messy" but assures that any simu_fax settings or destinations being used in the dialplan
   will migrate silently and continue to work.
 */
outn(_("Moving simu_fax feature code from core.."));
$check = $db->query("UPDATE featurecodes set modulename = 'fax' WHERE modulename = 'core' AND featurename = 'simu_fax'");
if (DB::IsError($check)){
  if ($check->getCode() == DB_ERROR_ALREADY_EXISTS) {
    outn(_("duplicate, removing old from core.."));
    $check = $db->query("DELETE FROM featurecodes WHERE modulename = 'core' AND featurename = 'simu_fax'");
    if (DB::IsError($check)){
      out(_("unknown error"));
    } else {
      out(_("removed"));
    }
  } else {
    out(_("unknown error"));
  }
} else {
  out(_("done"));
}
outn(_("Updating simu_fax in miscdest table.."));
$check = $db->query("UPDATE miscdests set destdial = '{fax:simu_fax}' WHERE destdial = '{core:simu_fax}'");
if (DB::IsError($check)){
  out(_("not needed"));
} else {
  out(_("done"));
}
$fcc = new featurecode('fax', 'simu_fax');
$fcc->setDescription('Dial System FAX');
$fcc->setDefault('666');
$fcc->setProvideDest();
$fcc->update();
unset($fcc);

//check to make sure that min/maxrate and ecm are set; if not set them to defaults
$settings=sql('SELECT * FROM fax_details', 'getAssoc', 'DB_FETCHMODE_ASSOC');
foreach($settings as $setting => $value){$set[$setting]=$value['0'];}
if(!is_array($set)){$set=array();}//never return a null value
if(!$set['minrate']){$sql[]='REPLACE INTO fax_details (`key`, `value`) VALUES ("minrate","14400")';}
if(!$set['maxrate']){$sql[]='REPLACE INTO fax_details (`key`, `value`) VALUES ("maxrate","14400")';}
if(!$set['ecm']){$sql[]='REPLACE INTO fax_details (`key`, `value`) VALUES ("ecm","yes")';}
if(!$set['legacy_mode']){$sql[]='REPLACE INTO fax_details (`key`, `value`) VALUES ("legacy_mode","no")';}
if(!$set['force_detection']){$sql[]='REPLACE INTO fax_details (`key`, `value`) VALUES ("force_detection","no")';}

if(isset($sql)){
	foreach ($sql as $statement){
		$check = $db->query($statement);
		if (DB::IsError($check)){
			die_freepbx( "Can not execute $statement : " . $check->getMessage() .  "\n");
		}
	}
}
/*
incoming columns:

faxexten: disabled
          default (check what global is)
          device_num

determine what default is, if a device then treat as that default device, if system
then treat as it was system here, and if disabled then treat as that.

legacy_email:
  null -> not in legacy mode
  blank or value -> in legacy mode

*/
outn(_("Checking if legacy fax needs migrating.."));
$sql = "SELECT `extension`, `cidnum`, `faxexten`, `faxemail`, `wait`, `answer` FROM `incoming`";
$legacy_settings = $db->getAll($sql, DB_FETCHMODE_ASSOC);
if(!DB::IsError($legacy_settings)) {
	out(_("starting migration"));

  // First step, need to get global settings and if not present use defaults
  //
  $sql = "SELECT variable, value FROM globals WHERE variable IN ('FAX_RX', 'FAX_RX_EMAIL', 'FAX_RX_FROM')";
  $globalvars = $db->getAll($sql, DB_FETCHMODE_ASSOC);

  foreach ($globalvars as $globalvar) {
	  $global[trim($globalvar['variable'])] = $globalvar['value'];
  }
  $fax_rx =          isset($global['FAX_RX'])       ? $global['FAX_RX'] : 'disabled';
  $fax_rx_email =    isset($global['FAX_RX_EMAIL']) ? $global['FAX_RX_EMAIL'] : '';
  $sender_address  = isset($global['FAX_RX_FROM'])  ? $global['FAX_RX_FROM'] : '';

  // Now some sanity settings, can't email the fax if no email present
  if ($fax_rx_email == '') {
    $fax_rx = 'disabled';
  }

  // TODO Update Module Defaults Here
  // insert_general_values()
  //
  $global_migrate = array();
  $global_migrate[] = array('sender_address',$sender_address);
  $global_migrate[] = array('fax_rx_email',$fax_rx_email);

	outn(_("migrating defaults.."));
	$compiled = $db->prepare("REPLACE INTO `fax_details` (`key`, `value`) VALUES (?,?)");
	$result = $db->executeMultiple($compiled,$global_migrate);
	if(DB::IsError($result)) {
    out(_("failed"));
		die_freepbx( "Fatal error during migration: " . $result->getMessage() .  "\n");
	} else {
    out(_("migrated"));
  }

	$detection_type = array(0 => 'dahdi', 1 => 'dahdi', 2 => 'nvfax');
	$non_converts = array();

  if (count($legacy_settings)) {
    foreach($legacy_settings as $row) {
      $legacy_email = null;
      if ($row['faxexten'] == 'default') {
        $row['faxexten'] = $fax_rx;
      } else if ($row['faxexten'] == '') {
        $row['faxexten'] = 'disabled';
			}
			if ($row['wait'] < 2) {
        $detectionwait = '2';
      } elseif ($row['wait'] > 10) {
        $detectionwait = '10';
      } else {
        $detectionwait = $row['wait'];
      }
      $detection = $detection_type[$row['answer']];
      switch ($row['faxexten']) {
        case 'disabled':
          continue; // go back to foreach for now
        break;

        case 'system':
          $legacy_email = $row['faxemail'] ? $row['faxemail'] : $fax_rx_email;

          // Now some sanity, if faxemail is blank then it won't work and we treat as disabled
          //
          if (!$legacy_email) {
            continue;
          }
          $destination = '';
			    $insert_array[] = array($row['extension'], $row['cidnum'], $detection, $detectionwait, $destination, $legacy_email);
        break;

        default:
          if (ctype_digit($row['faxexten'])) {
            $sql = "SELECT `user` FROM `devices` WHERE `id` = '".$row['faxexten']."'";
            $user = $db->getOne($sql);
            if (ctype_digit($user)) {
              $destination = "from-did-direct,$user,1";
            } else {
							$non_converts[] = array('extension' => $row['extension'], 'cidnum' => $row['cidnum'], 'device' => $row['faxexten'], 'user' => $user);
              continue;
            }
          }
			  $insert_array[] = array($row['extension'], $row['cidnum'], $detection, $detectionwait, $destination, $legacy_email);
        break;
      }
    }

    if (!empty($insert_array)) {
		  $compiled = $db->prepare("INSERT INTO `fax_incoming` (`extension`, `cidnum`, `detection`, `detectionwait`, `destination`, `legacy_email`) VALUES (?,?,?,?,?,?)");
		  $result = $db->executeMultiple($compiled,$insert_array);
    }
		if(!empty($insert_array) && DB::IsError($result)) {
      out("Fatal error migrating to fax module..legacy data retained in incoming and globals tables");
		  die_freepbx( "Fatal error during migration: " . $result->getMessage() .  "\n");
		} else {
			$migrate_array = array('faxexten', 'faxemail', 'wait', 'answer');
			foreach ($migrate_array as $field) {
				outn(sprintf(_("Removing field %s from incoming table.."),$field));
				$sql = "ALTER TABLE `incoming` DROP `".$field."`";
				$results = $db->query($sql);
				if (DB::IsError($results)) {
					out(_("not present"));
				} else {
					out(_("removed"));
				}
			}
			outn(_("Removing old globals.."));
      $sql = "DELETE FROM globals WHERE variable IN ('FAX_RX', 'FAX_RX_EMAIL', 'FAX_RX_FROM')";

			$results = $db->query($sql);
			if (DB::IsError($results)) {
				out(_("failed"));
			} else {
				out(_("removed"));
			}

	    $failed_faxes = count($non_converts);
      outn(_("Checking for failed migrations.."));
	    if ($failed_faxes) {
        $notifications = notifications::create($db);
		    $extext = _("The following Inbound Routes had FAX processing that failed migration because they were accessing a device with no associated user. They have been disabled and will need to be updated. Click delete icon on the right to remove this notice.")."<br />";
		    foreach ($non_converts as $did) {
          $didval = trim($did['extension']) == '' ? _("blank") : $did['extension'];
          $cidval = trim($did['cidnum']) == '' ? _("blank") : $did['cidnum'];
			    $extext .= "DID: ".$didval." CIDNUM: ".$cidval." PREVIOUS DEVICE: ".$did['device']."<br />";
		    }
		    $notifications->add_error('fax', 'FAXMIGRATE', sprintf(_('%s FAX Migrations Failed'),$failed_faxes), $extext, '', true, true);
        out(sprintf(_('%s FAX Migrations Failed, check notification panel for details'),$failed_faxes));
	    } else {
        out(_("all migrations succeeded successfully"));
      }
		}
  } else {
	  out(_("No Inbound Routes to migrate"));
  }
} else {
	out(_("already done"));
}

//migrate the faxemail field to allow emails longer than 50 characters
$sql = 'describe fax_users';
$fields = $db->getAssoc($sql);
if (array_key_exists('faxemail',$fields) && $fields['faxemail'][0] == 'varchar(50)') {
	out(_('Migrating faxemail field in the fax_users table to allow longer emails...'));
	$sql = 'ALTER TABLE fax_users CHANGE faxemail faxemail text default NULL';
	$q = $db->query($sql);
	if (DB::isError($q)) {
		out(_('WARNING: Failed migration. Email length is limited to 50 characters.'));
	} else {
		out(_('Successfully migrated faxemail field'));
	}
}



$set['value'] = 'www.freepbx.org';
$set['defaultval'] =& $set['value'];
$set['readonly'] = 1;
$set['hidden'] = 1;
$set['module'] = '';
$set['category'] = 'Styling and Logos';
$set['emptyok'] = 0;
$set['name'] = 'tiff2pdf Author';
$set['description'] = "Author to pass to tiff2pdf's -a option";
$set['type'] = CONF_TYPE_TEXT;
$freepbx_conf =& freepbx_conf::create();
$freepbx_conf->define_conf_setting('PDFAUTHOR', $set, true);

if(!\FreePBX::Fax()->getConfig("usermanMigrate")) {
  $sql = "SELECT fax_users.user,fax_users.faxemail,fax_users.faxattachformat,fax_users.faxenabled FROM fax_users ORDER BY fax_users.user";
  $results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
  if(DB::IsError($results)) {
    die_freepbx($results->getMessage()."<br><br>Error selecting from fax");
  }
  $ma = array();
  if(!empty($results)) {
    out(_("Migrating all fax users to usermanager"));
  }
  foreach($results as $res) {
    $o = \FreePBX::Userman()->getUserByDefaultExtension($res['user']);
    if(empty($o)) {
      //migrate and add for upgrades
      try {
        $user = \FreePBX::Userman()->addUser($res['user'], bin2hex(openssl_random_pseudo_bytes(4)), $res['user'], _("Auto generated migrated user for Fax"), array("email" => $res['faxemail']));
        if($user['status']) {
          \FreePBX::Userman()->setModuleSettingByID($user['id'],'fax','enabled',($res['faxenabled'] == "true"));
          \FreePBX::Userman()->setModuleSettingByID($user['id'],'fax','attachformat',$res['faxattachformat']);
          \FreePBX::Userman()->setModuleSettingByID($user['id'],'fax','migrate',true);
        } else {
          out(sprintf(_("Unable to migrate %s, because [%s]. Please check your 'Fax Recipients' destinations"),$res['user'],$user['message']));
          $sql = "DELETE FROM fax_users WHERE user = ?";
          $sth = \FreePBX::Database()->prepare($sql);
          $sth->execute(array($res['user']));
          continue;
        }
      } catch(\Exception $e) {
        out(sprintf(_("Unable to migrate %s, because [%s]. Please check your 'Fax Recipients' destinations"),$res['user'],$e->getMessage()));
        $sql = "DELETE FROM fax_users WHERE user = ?";
        $sth = \FreePBX::Database()->prepare($sql);
        $sth->execute(array($res['user']));
        continue;
      }
      $o = $user;
    } elseif(empty($o['email'])) {
			//no email set for this user so now update user with the fax email
			\FreePBX::Userman()->updateUserExtraData($o['id'],array("email" => $res['faxemail']));
		} elseif($o['email'] != $res['faxemail']) {
			//email was set in userman and it's different than this extension so we keep the usermanager email
			out(sprintf(_("Migrated user %s but unable to set email address to %s because an email [%s] was already set for User Manager User %s"),$res['user'],$res['faxemail'],$o['email'],$o['username']));
		}

    $sql = "UPDATE fax_users SET user = ? WHERE user = ?";
    $sth = \FreePBX::Database()->prepare($sql);
    try {
      $sth->execute(array("a".$o['id'],$res['user']));
    } catch(\Exception $e) {
      out(sprintf(_("Unable to migrate %s, because [%s]. Please check your 'Fax Recipients' destinations"),$res['user'],$e->getMessage()));
      continue;
    }
    $ma[$res['user']] = $o['id'];

    $sql = "UPDATE fax_incoming SET destination = ? WHERE destination = ?";
    $sth = \FreePBX::Database()->prepare($sql);
    try {
      $sth->execute(array("ext-fax,".$o['id'].",1","ext-fax,".$res['user'].",1"));
    } catch(\Exception $e) {
      out(sprintf(_("Unable to migrate %s, because [%s]. Please check your 'Fax Recipients' destinations"),$res['user'],$e->getMessage()));
      continue;
    }
  }

  foreach($ma as $faxuser => $usermanuser) {
    $sql = "UPDATE fax_users SET user = ? WHERE user = ?";
    $sth = \FreePBX::Database()->prepare($sql);
    $sth->execute(array($usermanuser,"a".$usermanuser));
  }

  if(!empty($results)) {
    out(_("Finished Migrating fax users to usermanager"));
    $nt = notifications::create();
    $nt->add_critical("fax", "usermanMigrate", _("Inbound Fax Destination Change"), _("Inbound faxes now use User Manager users. Therefore you will need to re-assign all of your destinations that used 'Fax Recipients' to point to User Manager users. You may see broken destinations until this is resolved"), "", true, true);
  }
  \FreePBX::Fax()->setConfig("usermanMigrateArray",$ma);
  \FreePBX::Fax()->setConfig("usermanMigrate",true);
}
