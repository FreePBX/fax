<?php /* $Id */
/* 
 * Copyright (C) 2009 Moshe Brevda
 * 
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of version 2 the GNU General Public
 * License as published by the Free Software Foundation.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 */

$tabindex = 0;
// get/put options
if (isset($_REQUEST['action']) &&  $_REQUEST['action'] == 'edit'){
	needreload();
	$options=array("headerinfo", "localstationid", "ecm", "maxrate", "minrate", "modem", "sender_address", "fax_rx_email");
	foreach($options as $option){
		$fax[$option] = isset($_REQUEST[$option]) ? $_REQUEST[$option] : '';
	}
	$fax['legacy_mode'] = isset($_REQUEST['legacy_mode']) ? $_REQUEST['legacy_mode'] : 'no';
	$fax['force_detection'] = isset($_REQUEST['force_detection']) ? $_REQUEST['force_detection'] : 'no';
	fax_save_settings($fax);
}else{
	$fax=fax_get_settings();
	$fax['legacy_mode'] = isset($fax['legacy_mode']) ? $fax['legacy_mode'] : 'no';
	$fax['force_detection'] = isset($fax['force_detection']) ? $fax['force_detection'] : 'no';
	$action='';//no action to do
}
$fax_detect=fax_detect();
?>

<h2><?php echo _("Fax Options")?></h2>
<form name=edit enctype="multipart/form-data" action="<?php echo $_SERVER['PHP_SELF']; ?>" method=POST>
	<table id="faxoptionstable">		
		<tbody>			
			<tr><td colspan="3"><h5><?php echo _("Fax Presentation Options")?><hr/></h5></td></tr>			
			<tr>
				<td><a href="#" class="info"><?php echo _("Default Fax header")?>:<span><?php echo _("Header information that is passed to remote side of the fax transmission and is printed on top of every page. This usually contains the name of the person or entity sending the fax.")?></span></a></td>
				<td><input size="30" type="text" name="headerinfo" value="<?php  echo $fax['headerinfo']; ?>" tabindex="<?php echo ++$tabindex;?>"></td>	
			</tr>			<tr>
				<td><a href="#" class="info"><?php echo _("Default Local Station Identifier")?>:<span><?php echo _("The outgoing Fax Machine Identifier. This is usually your fax number.")?></span></a></td>
				<td><input size="30" type="text" name="localstationid" value="<?php  echo $fax['localstationid']; ?>" tabindex="<?php echo ++$tabindex;?>"></td>					
			</tr>
			<tr>
				<td><a class="info" href="#"><?php echo _("Outgoing Email address:")?><span><?php echo _("Email address that faxes appear to come from if 'system default' has been chosen as the default fax extension.")?></span></a></td>
				<td><input type="text" size="30" name="sender_address" value="<?php  echo htmlspecialchars($fax['sender_address'])?>" tabindex="<?php echo ++$tabindex;?>"/></td>
			</tr>
			<tr><td colspan="3"><h5><?php echo _("Fax Feature Code Options")?><hr/></h5></td></tr>			
			<tr>
				<td><a class="info" href="#"><?php echo _("Email address:")?><span><?php echo _("Email address that faxes are sent to when using the \"Dial System Fax\" feature code. This is also the default email for fax detection in legacy mode, if there are routes still running in this mode that do not have email addresses specified.")?></span></a></td>
				<td><input type="text" size="30" name="fax_rx_email" value="<?php  echo htmlspecialchars($fax['fax_rx_email'])?>" tabindex="<?php echo ++$tabindex;?>"/></td>
			</tr>			
			
			<tr><td colspan="3"><h5><?php echo _("Fax Transport Options")?><hr/></h5></td></tr>
			<tr>
				<td><a href="#" class="info"><?php echo _("Error Correction Mode")?>:<span><?php echo _("Error Correction Mode (ECM) option is used to specify whether
				 to use ecm mode or not.")?></span></a></td>
       <td><input type="radio" name="ecm" value="yes" <?php echo (($fax['ecm'] == 'yes')?'checked':''); ?> tabindex="<?php echo ++$tabindex;?>"><?php echo _("Yes")?>
       <input type="radio" name="ecm" value="no" <?php echo (($fax['ecm'] == 'no')?'checked':''); ?> tabindex="<?php echo ++$tabindex;?>"><?php echo _("No")?></td>
			</tr>				
			<tr>
				<td><a href="#" class="info"><?php echo _("Maximum transfer rate")?>:<span><?php echo _("Maximum transfer rate used during fax rate negotiation.")?></span></a></td>
				<td><select name="maxrate" tabindex="<?php echo ++$tabindex;?>">
										<option value="2400"  <?php echo (($fax['maxrate'] == '2400')?'selected="yes"':'');?>  >2400</option>
										<option value="4800"  <?php echo (($fax['maxrate'] == '4800')?'selected="yes"':'');?>  >4800</option>	
										<option value="7200"  <?php echo (($fax['maxrate'] == '7200')?'selected="yes"':'');?>  >7200</option>	
										<option value="9600"  <?php echo (($fax['maxrate'] == '9600')?'selected="yes"':'');?>  >9600</option>	
										<option value="12200" <?php echo (($fax['maxrate'] == '12200')?'selected="yes"':'');?> >12200</option>	
										<option value="14400" <?php echo (($fax['maxrate'] == '14400')?'selected="yes"':'');?> >14400</option>
				</select></td>		
			</tr>	
			<tr>
				<td><a href="#" class="info"><?php echo _("Minimum transfer rate")?>:<span><?php echo _("Minimum transfer rate used during fax rate negotiation.")?></span></a></td>
				<td><select name="minrate" tabindex="<?php echo ++$tabindex;?>">
										<option value="2400"  <?php echo (($fax['minrate'] == '2400')?'selected="yes"':'');?>  >2400</option>
										<option value="4800"  <?php echo (($fax['minrate'] == '4800')?'selected="yes"':'');?>  >4800</option>	
										<option value="7200"  <?php echo (($fax['minrate'] == '7200')?'selected="yes"':'');?>  >7200</option>	
										<option value="9600"  <?php echo (($fax['minrate'] == '9600')?'selected="yes"':'');?>  >9600</option>	
										<option value="12200" <?php echo (($fax['minrate'] == '12200')?'selected="yes"':'');?> >12200</option>	
										<option value="14400" <?php echo (($fax['minrate'] == '14400')?'selected="yes"':'');?> >14400</option>
				</select></td>				
			</tr>
			<!--
			<tr>
				<td><a href="#" class="info"><?php echo _("Modem")?>:<span><?php echo _("Modem Type.")?></span></a></td>
				<td><select name="modem" tabindex="<?php echo ++$tabindex;?>">
										<option value="V17" <?php echo (($fax['modem'] == 'V17')?'selected="yes"':'');?> >V17</option>
										<option value="V27" <?php echo (($fax['modem'] == 'V27')?'selected="yes"':'');?> >V27</option>	
										<option value="V29" <?php echo (($fax['modem'] == 'V29')?'selected="yes"':'');?> >V29</option>	
										<option value="V34" <?php echo (($fax['modem'] == 'V34')?'selected="yes"':'');?> >V34</option>	
				</select></td>						
			</tr> -->
			<tr>
			
			</tr>		
	
<!-- php echo'd variables are commented out as well!
		<tr><td colspan="3"><h5><?php echo _("Fax Receive Options")?><hr/></h5></td></tr>
		<tr>
			<td><a class="info" href="#"><?php echo _("Default fax instance:")?><span><?php echo _("Select 'system default' to have the system act as a fax instance. You can then route faxes to this instance and avoid having to route faxes to an instance associated with a specific entity. This can be the system using Asterisk's internal capabilities or it can be an external extension, such as a physical fax machine")?></span></a></td>
				<td><select name="system_instance" id="system_instance" tabindex="<?php echo ++$tabindex;?>">
				<option value="disabled" <?php  //echo ($system_instance == 'disabled' ? 'SELECTED' : '')?>><?php echo _("disabled")?>
				<option value="system" <?php  //echo ($system_instance == 'system' ? 'SELECTED' : '')?>><?php echo _("system default")?>
					<?php //get unique devices
						$devices = core_devices_list();
						if (isset($devices)) {
							foreach ($devices as $device) {
								//echo '<option value="'.$device[0].'" '.($system_instance == $device[0] ? 'SELECTED' : '').'>'.$device[1].' &lt;'.$device[0].'&gt;';
							}
						}	?>
			</select></td>
		</tr>
		<tr id="defaultmail">
			<td><a class="info" href="#"><?php echo _("Default Recipient Email address:")?><span><?php echo _("Email address used if 'system default' has been chosen as the default fax extension.")?></span></a></td>
			<td><input type="text" size="30" name="system_fax2email" value="<?php  //echo htmlspecialchars($system_fax2email)?>" tabindex="<?php echo ++$tabindex;?>"/></td>
		</tr> 
		-->
			<tr><td colspan="3"><h5><?php echo _("Fax Module Options")?><hr/></h5></td></tr>
			<tr>
				<td><a href="#" class="info"><?php echo _("Always Allow Legacy Mode")?>:<span><?php echo _("In earlier versions, it was possible to provide an email address with the incoming FAX detection to route faxes that were being handled by fax-to-email detection. This has been deprecated in favor of Extension/User FAX destinations where an email address can be provided. During migration, the old email address remains present for routes configured this way but goes away once 'properly' configured. This options forces the Legacy Mode to always be present as an option.")?></span></a></td>
        <td><input type="radio" name="legacy_mode" value="yes" <?php echo (($fax['legacy_mode'] == 'yes')?'checked':''); ?> tabindex="<?php echo ++$tabindex;?>"><?php echo _("Yes")?>
        <input type="radio" name="legacy_mode" value="no" <?php echo (($fax['legacy_mode'] == 'no')?'checked':''); ?> tabindex="<?php echo ++$tabindex;?>"><?php echo _("No")?></td>			
			</tr>				

<?php if(!$fax_detect['module']){ ?>
			<tr>
				<td><a href="#" class="info"><?php echo _("Always Generate Detection Code")?>:<span><?php echo _("When no fax modules are detected the module will not generate any detection dialplan by default. If the system is being used with phyical FAX devices, hylafax + iaxmodem, or other outside fax setups you can force the dialplan to be generated here.")?></span></a></td>
        <td><input type="radio" name="force_detection" value="yes" <?php echo (($fax['force_detection'] == 'yes')?'checked':''); ?> tabindex="<?php echo ++$tabindex;?>"><?php echo _("Yes")?>
        <input type="radio" name="force_detection" value="no" <?php echo (($fax['force_detection'] == 'no')?'checked':''); ?> tabindex="<?php echo ++$tabindex;?>"><?php echo _("No")?></td>			
			</tr>				
<?php } ?>
	</tbody>
	</table>
	<br />

	<input type="hidden" value="fax" name="display"/>
	<input type="hidden" name="action" value="edit">
	<input type=submit value="<?php echo _("Submit")?>">

</form>
<?php
//add hooks
echo $module_hook->hookHtml;
?>
<script type="text/javascript">
$(document).ready(function() {
	if ($('#system_instance').val() == 'disabled'){$('#defaultmail').hide();}
	$('#system_instance').click(function(){
		if ($(this).val() == 'disabled'){
			$('#defaultmail').hide();
		}else{
			$('#defaultmail').show();
		}
	});
});

</script>
