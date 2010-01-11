<?php

global $db;

$sql[]='CREATE TABLE IF NOT EXISTS `fax_details` (
  `key` varchar(50) default NULL,
  `value` varchar(510) default NULL,
  UNIQUE KEY `key` (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;';


$sql[]='CREATE TABLE IF NOT EXISTS `fax_incoming` (
  `cidnum` varchar(20) default NULL,
  `extension` varchar(20) default NULL,
  `faxenabled` varchar(15) default NULL,
  `faxdetection` varchar(20) default NULL,
  `faxdetectionwait` varchar(5) default NULL,
  `faxdestination` varchar(50) default NULL
)';

$sql[]='CREATE TABLE IF NOT EXISTS `fax_users` (
  `user` varchar(15) default NULL,
  `faxenabled` varchar(10) default NULL,
  `faxemail` varchar(50) default NULL,
  UNIQUE KEY `user` (`user`)
)';


foreach ($sql as $statement){
	$check = $db->query($statement);
	if (DB::IsError($check)){
		die_freepbx( "Can not execute $statement : " . $check->getMessage() .  "\n");
	}
}

//check to make sure that min/maxrate and ecm are set; if not set them to defaults
$settings=sql('SELECT * FROM fax_details', 'getAssoc', 'DB_FETCHMODE_ASSOC');
foreach($settings as $setting => $value){$set[$setting]=$value['0'];}
if(!is_array($set)){$set=array();}//never return a null value
if(!$set['minrate']){$sql[]='REPLACE INTO fax_details (`key`, `value`) VALUES ("minrate","14400")';}
if(!$set['maxrate']){$sql[]='REPLACE INTO fax_details (`key`, `value`) VALUES ("maxrate","14400")';}
if(!$set['ecm']){$sql[]='REPLACE INTO fax_details (`key`, `value`) VALUES ("ecm","yes")';}

foreach ($sql as $statement){
	$check = $db->query($statement);
	if (DB::IsError($check)){
		die_freepbx( "Can not execute $statement : " . $check->getMessage() .  "\n");
	}
}
?>
