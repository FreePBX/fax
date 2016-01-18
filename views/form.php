<?php
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2015 Sangoma Technologies.
//

$fax = fax_get_settings();
$fax_detect = fax_detect();
$trans_rates = array(
			'9600'	=> '9600',
			'12000'	=> '12000',
			'14400'	=> '14400'
			);
$minrateopts = $maxrateopts = '';

foreach($trans_rates as $rate){
	$minrateopts .= '<option value='.$rate.' '.(($rate == $fax['minrate'])?"SELECTED":"").'>'.$rate.'</option>';
	$maxrateopts .= '<option value='.$rate.' '.(($rate == $fax['maxrate'])?"SELECTED":"").'>'.$rate.'</option>';
}
$aghtml = '';
if(!$fax_detect['module']){
	$aghtml = '
		<!--Always Generate Detection Code-->
		<div class="element-container">
			<div class="row">
				<div class="col-md-12">
					<div class="row">
						<div class="form-group">
							<div class="col-md-3">
								<label class="control-label" for="force_detection">'. _("Always Generate Detection Code").'</label>
								<i class="fa fa-question-circle fpbx-help-icon" data-for="force_detection"></i>
							</div>
							<div class="col-md-9 radioset">
								<input type="radio" class="form-control" id="force_detection_yes" name="force_detection" value="yes"'. (($fax['force_detection'] == 'yes')?'checked':'').'>
								<label for="force_detection_yes">'._("Yes").'</label>
								<input type="radio" class="form-control" id="force_detection_no" name="force_detection" value="no" '.(($fax['force_detection'] == 'no')?'checked':'').'>
								<label for="force_detection_no">'. _("No").'</label>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="row">
				<div class="col-md-12">
					<span id="force_detection-help" class="help-block fpbx-help-block">'. _("When no fax modules are detected the module will not generate any detection dialplan by default. If the system is being used with phyical FAX devices, hylafax + iaxmodem, or other outside fax setups you can force the dialplan to be generated here.").'</span>
				</div>
			</div>
		</div>
		<!--END Always Generate Detection Code-->
	';
}else{
	$aghtml ='';
}
$fax['papersize'] = isset($fax['papersize'])?$fax['papersize']:'letter';
?>
<?php if($fax['minrate'] == 2400) { ?>
	<div class="alert alert-warning" role="alert"><?php echo _("Your minimum transfer rate is set to 2400 in certain circumstances this can break faxing")?></div>
<?php } ?>
<?php if($fax['mmaxrate'] == 2400) { ?>
	<div class="alert alert-warning" role="alert"><?php echo _("Your maximum transfer rate is set to 2400 in certain circumstances this can break faxing")?></div>
<?php } ?>
<form name="edit" id="edit" class="fpbx-submit" action="" method="POST">
<input type="hidden" value="fax" name="display"/>
<input type="hidden" name="action" value="edit">
<!--Default Fax header-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="headerinfo"><?php echo _("Default Fax header") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="headerinfo"></i>
					</div>
					<div class="col-md-9">
						<input type="text" class="form-control" id="headerinfo" name="headerinfo" value="<?php  echo $fax['headerinfo']; ?>">
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="headerinfo-help" class="help-block fpbx-help-block"><?php echo _("Header information that is passed to remote side of the fax transmission and is printed on top of every page. This usually contains the name of the person or entity sending the fax.")?></span>
		</div>
	</div>
</div>
<!--END Default Fax header-->
<!--Default Local Station Identifier-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="localstationid"><?php echo _("Default Local Station Identifier") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="localstationid"></i>
					</div>
					<div class="col-md-9">
						<input type="text" class="form-control" id="localstationid" name="localstationid" value="<?php  echo $fax['localstationid']; ?>">
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="localstationid-help" class="help-block fpbx-help-block"><?php echo _("The outgoing Fax Machine Identifier. This is usually your fax number.")?></span>
		</div>
	</div>
</div>
<!--END Default Local Station Identifier-->
<!--Outgoing Email address-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="sender_address"><?php echo _("Outgoing Email address") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="sender_address"></i>
					</div>
					<div class="col-md-9">
						<input type="text" class="form-control" id="sender_address" name="sender_address" value="<?php  echo htmlspecialchars($fax['sender_address'])?>">
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="sender_address-help" class="help-block fpbx-help-block"><?php echo _("Email address that faxes appear to come from if 'system default' has been chosen as the default fax extension.")?></span>
		</div>
	</div>
</div>
<!--END Outgoing Email address-->
<!--Email address-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="fax_rx_email"><?php echo _("Email address") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="fax_rx_email"></i>
					</div>
					<div class="col-md-9">
						<input type="text" class="form-control" id="fax_rx_email" name="fax_rx_email" value="<?php  echo htmlspecialchars($fax['fax_rx_email'])?>">
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="fax_rx_email-help" class="help-block fpbx-help-block"><?php echo _("Email address that faxes are sent to when using the \"Dial System Fax\" feature code. This is also the default email for fax detection in legacy mode, if there are routes still running in this mode that do not have email addresses specified.")?></span>
		</div>
	</div>
</div>
<!--END Email address-->
<!--Error Correction Mode-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="ecm"><?php echo _("Error Correction Mode") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="ecm"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" class="form-control" id="ecmyes" name="ecm" value="yes" <?php echo (($fax['ecm'] == 'yes')?'checked':'')?>>
						<label for="ecmyes"><?php echo _("Yes")?></label>
						<input type="radio" class="form-control" id="ecmno" name="ecm" value="no" <?php echo (($fax['ecm'] == 'no')?'checked':'')?>>
						<label for="ecmno"><?php echo _("No")?></label>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="ecm-help" class="help-block fpbx-help-block"><?php echo _("Error Correction Mode (ECM) option is used to specify whether
			 to use ecm mode or not.")?></span>
		</div>
	</div>
</div>
<!--END Error Correction Mode-->
<!--Maximum transfer rate-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="maxrate"><?php echo _("Maximum transfer rate") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="maxrate"></i>
					</div>
					<div class="col-md-9">
						<select class="form-control" id="maxrate" name="maxrate">
							<?php echo $maxrateopts ?>
						</select>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="maxrate-help" class="help-block fpbx-help-block"><?php echo _("Maximum transfer rate used during fax rate negotiation.")?></span>
		</div>
	</div>
</div>
<!--END Maximum transfer rate-->
<!--Minimum transfer rate-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="minrate"><?php echo _("Minimum transfer rate") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="minrate"></i>
					</div>
					<div class="col-md-9">
						<select class="form-control" id="minrate" name="minrate">
							<?php echo $minrateopts ?>
						</select>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="minrate-help" class="help-block fpbx-help-block"><?php echo _("Minimum transfer rate used during fax rate negotiation.")?></span>
		</div>
	</div>
</div>
<!--END Minimum transfer rate-->
<!--Default Paper Size-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="papersize"><?php echo _("Default Paper Size") ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="papersize"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" class="form-control" id="papersizeletter" name="papersize" value="letter" <?php echo (($fax['papersize'] == 'letter')?'checked':'')?>>
						<label for="papersizeletter"><?php echo _("Letter")?></label>
						<input type="radio" class="form-control" id="papersizea4" name="papersize" value="a4" <?php echo (($fax['papersize'] == 'a4')?'checked':'')?>>
						<label for="papersizea4"><?php echo _("A4")?></label>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="papersize-help" class="help-block fpbx-help-block"><?php echo _("Select the default paper size.<br/>This specifies the size that should be used if the document does not specify a size.<br/> If the document does specify a size that size will be used.")?></span>
		</div>
	</div>
</div>
<!--END Default Paper Size-->
<!--Always Allow Legacy Mode-->
<input type="hidden" id="legacy_mode" name="legacy_mode" value="<?php echo isset($fax['legacy_mode'])?$fax['legacy_mode']:'no'?>">

<?php echo $aghtml //if not fax_detect ?>
<?php
//add hooks
$module_hook = moduleHook::create();
echo $module_hook->hookHtml;
?>
</form>
