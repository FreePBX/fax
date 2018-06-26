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



$fcc = new featurecode('fax', 'simu_fax');
$fcc->setDescription('Dial System FAX');
$fcc->setDefault('666');
$fcc->setProvideDest();
$fcc->update();
unset($fcc);


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
