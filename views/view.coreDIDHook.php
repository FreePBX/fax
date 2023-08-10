<?php
	//default wait time is 4 second
	if(!is_array($fax_incoming) || !$fax_incoming['detectionwait'])
	{
		$fax_incoming = [];
		$fax_incoming['detectionwait'] = 4;
	}

	//set default is not definded
	if (!isset($fax_incoming['detection']) || !$fax_incoming['detection'])
	{
		$fax_incoming['detection'] = 'dahdi';
	}
?>
<!--Detect Faxes-->
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="faxenabled"><?php echo _("Detect Faxes"); ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="faxenabled"></i>
					</div>
					<div class="col-md-9 radioset">
						<!-- dont allow detection to be set if we have no valid detection types -->
						<?php if (!$fax_dahdi_faxdetect && !$fax_sip_faxdetect): ?>
							<input type="radio" name="faxenabled" id="faxenabled_yes" value="true"  onclick="DetectFaxInputYes(this);"><label for="faxenabled_yes"><?php echo _('Yes'); ?></label></span>';
							<input type="radio" id="faxenabled_no" name="faxenabled" value="false" CHECKED ><label for="faxenabled_no"><?php echo _('No'); ?></label>
						<?php else: ?>
							<!--
							/*
							* show detection options
							*
							* js to show/hide the detection settings. Second slide is always in a
							* callback so that we ait for the fits animation to complete before
							* playing the second
							*/
							-->
							<input type="radio" name="faxenabled" id="faxenabled_yes" value="true" <?php echo ($faxing ? 'CHECKED' : ''); ?> ><label for="faxenabled_yes"><?php echo _('Yes'); ?></label>
							<input type="radio" name="faxenabled" id="faxenabled_no" value="false" <?php echo ($faxing ? '' : 'CHECKED'); ?> ><label for="faxenabled_no"><?php echo _('No'); ?></label>
							<?php if (isset($fax_incoming['legacy_email']) && ($fax_incoming['legacy_email'] !== null || $fax_settings['legacy_mode'] == 'yes')): ?>
								<input type="radio" name="faxenabled" id="faxenabled_legacy" value="legacy" <?php ($fax_incoming['legacy_email'] !== null ? ' CHECKED ' : ''); ?>><label for="faxenabled_legacy"><?php echo _('Legacy'); ?></label>
							<?php endif ?>
						<?php endif ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="faxenabled-help" class="help-block fpbx-help-block">
				<?php echo _("Attempt to detect faxes on this DID."); ?>
				<ul>
					<?php
					$fdhelp_list = [_("No: No attempts are made to auto-determine the call type; all calls sent to destination set in the 'General' tab. Use this option if this DID is used exclusively for voice OR fax."), _("Yes: try to auto determine the type of call; route to the fax destination if call is a fax, otherwise send to regular destination. Use this option if you receive both voice and fax calls on this line"), (isset($fax_incoming['legacy_email']) && ($fax_settings['legacy_mode'] == 'yes' || $fax_incoming['legacy_email']!==null)) ? _('Legacy: Same as YES, only you can enter an email address as the destination. This option is ONLY for supporting migrated legacy fax routes. You should upgrade this route by choosing YES, and selecting a valid destination!') : ''];
					foreach ($fdhelp_list as $txt)
					{
						if (empty($txt)) { continue; }
						echo sprintf('<li>%s</li>', $txt);
					}
					unset($fdhelp_list);
					?>
				</ul>
			</span>
		</div>
	</div>
</div>
<!--END Detect Faxes-->
<!--Fax Detection type-->
<div class="element-container <?php echo ($faxing ? '' : "hidden"); ?>" id="fdtype">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="faxdetection"><?php echo _("Fax Detection type"); ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="faxdetection"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" name="faxdetection" id="faxdetectiondahdi" value="dahdi" <?php echo ($fax_incoming['detection'] == "dahdi" ? "CHECKED" : "").' '.(($fax_dahdi_faxdetect) ? '' : 'disabled'); ?> >
						<label for="faxdetectiondahdi"><?php echo _("Dahdi"); ?></label>
						<input type="radio" name="faxdetection" id="faxdetectionsip" value="sip" <?php echo ($fax_incoming['detection'] == "sip" ? "CHECKED" : "").' '.((($info['version'] >= "1.6.2") && $fax_sip_faxdetect)?'':'disabled'); ?> >
						<label for="faxdetectionsip"><?php echo _("SIP"); ?></label>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="faxdetection-help" class="help-block fpbx-help-block">
				<?php echo _("Type of fax detection to use."); ?>
				<ul>
					<li>
						<?php echo sprintf('%1$s : use %1$s fax detection; requires \'faxdetect=\' to be set to \'incoming\' or \'both\' in %1$s.conf', $dahdi); ?>
					</li>
					<li><?php echo _("Sip: use sip fax detection (t38). Requires asterisk 1.6.2 or greater and 'faxdetect=yes' in the sip config files"); ?></li>
				</ul>
			</span>
		</div>
	</div>
</div>
<!--END Fax Detection type-->
<!--Fax Play Tones-->
<div class="element-container <?php echo ($faxing? '' : "hidden"); ?>" id="fdring">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="faxring"><?php echo _("Fax Ring"); ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="faxring"></i>
					</div>
					<div class="col-md-9 radioset">
						<input type="radio" name="faxring" id="faxringyes" value="yes" <?php echo ((isset($fax_incoming['ring']) && $fax_incoming['ring'] == "1")?"CHECKED":"") ?> >
						<label for="faxringyes"><?php echo  _("Yes"); ?></label>
						<input type="radio" name="faxring" id="faxringno" value="no" <?php echo (!isset($fax_incoming['ring']) || empty($fax_incoming['ring']) || ($fax_incoming['ring'] == "0") ? "CHECKED" : ""); ?> >
						<label for="faxringno"><?php echo _("No"); ?></label>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="faxring-help" class="help-block fpbx-help-block"><?php echo _('Whether to ring while attempting to detect fax. If set to no silence will be heard'); ?></span>
		</div>
	</div>
</div>
<!--END Fax Play Tones-->
<!--Fax Detection Time-->
<div class="element-container <?php echo ($faxing?'':"hidden"); ?>" id="fdtime">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="faxdetectionwait"><?php echo _("Fax Detection Time"); ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="faxdetectionwait"></i>
					</div>
					<div class="col-md-9">
						<input type="number" min="2" max="11" class="form-control" id="faxdetectionwait" name="faxdetectionwait" value="<?php echo $fax_incoming['detectionwait'] ?? ''; ?>">
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="faxdetectionwait-help" class="help-block fpbx-help-block"><?php echo _('How long to wait and try to detect fax. Please note that callers to a Dahdi channel will hear ringing for this amount of time (i.e. the system wont "answer" the call, it will just play ringing).'); ?></span>
		</div>
	</div>
</div>
<!--END Fax Detection Time-->
				
<?php if (isset($fax_incoming['legacy_email']) && (!empty($fax_incoming['legacy_email']) || $fax_settings['legacy_mode'] == 'yes')): ?>
	<!--Fax Email Destination-->
	<div class="element-container <?php echo ($faxing ? '' : "hidden"); ?>" id="fdemail">
		<div class="row">
			<div class="col-md-12">
				<div class="row">
					<div class="form-group">
						<div class="col-md-3">
							<label class="control-label" for="legacy_email"><?php echo _("Fax Email Destination"); ?></label>
							<i class="fa fa-question-circle fpbx-help-icon" data-for="legacy_email"></i>
						</div>
						<div class="col-md-9">
							<input type="text" class="form-control" id="legacy_email" name="legacy_email" value="<?php echo $fax_incoming['legacy_email']; ?>">
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="row">
			<div class="col-md-12">
				<span id="legacy_email-help" class="help-block fpbx-help-block"><?php echo _("Address to email faxes to on fax detection.<br />PLEASE NOTE: In this version of FreePBX, you can now set the fax destination from a list of destinations. Extensions/Users can be fax enabled in the user/extension screen and set an email address there. This will create a new destination type that can be selected. To upgrade this option to the full destination list, select YES to Detect Faxes and select a destination. After clicking submit, this route will be upgraded. This Legacy option will no longer be available after the change, it is provided to handle legacy migrations from previous versions of FreePBX only."); ?></span>
			</div>
		</div>
	</div>
	<!--END Fax Email Destination-->
<?php endif ?>
<!--Fax Destination-->
<div class="element-container <?php echo ($faxing ? '' : "hidden"); ?>" id="fddest">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="gotofax"><?php echo _("Fax Destination"); ?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="gotofax"></i>
					</div>
					<div class="col-md-9">
						<?php echo $fax_detect ? drawselects($fax_incoming['destination'] ?? null, 'FAX', false, false) : ''; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="gotofax-help" class="help-block fpbx-help-block"><?php echo _('Where to send the faxes'); ?></span>
		</div>
	</div>
</div>
<!--END Fax Destination-->

<script type="text/javascript">
	$("[name='faxenabled']").change(function()
	{
		if($(this).val() == 'true')
		{
			$("#fdtype").removeClass("hidden");
			$("#fdtime").removeClass("hidden");
			$("#fddest").removeClass("hidden");
			$("#fdring").removeClass("hidden");
		}
		else
		{
			$("#fdtype").addClass("hidden");
			$("#fdtime").addClass("hidden");
			$("#fddest").addClass("hidden");
			$("#fdring").addClass("hidden");
		}
	});

	// ensure that we are using destination for both fax detect and the regular calls
	$(document).ready(function()
	{
		$("input[name=Submit]").click(function()
		{
			if($("input[name=faxenabled]:checked").val()=="true" && !$("[name=gotoFAX]").val())
			{
				//ensure the user selected a fax destination
				alert('<?php echo _("You have selected Fax Detection on this route. Please select a valid destination to route calls detected as faxes to."); ?>');
				return false; 
			}	
		}) 
	});

	function DetectFaxInputYes(obj)
	{
		if ($(obj).val() == 'true')
		{
			alert('<?php echo _('No fax detection methods found or no valid license. Faxing cannot be enabled.'); ?>');
			return false;
		}
	}
</script>