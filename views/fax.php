<script>
$(function() {
	$("input[name=faxenabled]").click(function(){
		if($(this).val() == "true") {
			$(".fpbx-fax").prop("disabled",false);
		} else {
			$(".fpbx-fax").prop("disabled",true);
		}
	});
});
</script>
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-4 control-label">
						<label for="faxenabled"><?php echo _('Enabled')?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="faxenabled"></i>
					</div>
					<div class="col-md-8">
						<span class="radioset">
							<input type="radio" name="faxenabled" class="form-control " id="faxenabled0" tabindex="" value="true" <?php echo ($fax['faxenabled'] == "true") ? 'checked' : ''?>><label for="faxenabled0"><?php echo _('Yes')?></label>
							<input type="radio" name="faxenabled" class="form-control " id="faxenabled1" tabindex="" value="false" <?php echo ($fax['faxenabled'] != "true") ? 'checked' : ''?>><label for="faxenabled1"><?php echo _('No')?></label>
						</span>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="faxenabled-help" class="help-block fpbx-help-block"><?php echo _('Enable this user to receive faxes')?></span>
		</div>
	</div>
</div>
<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-4 control-label">
						<label for="faxattachformat"><?php echo _('Attachment Format')?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="faxattachformat"></i>
					</div>
					<div class="col-md-8">
						<select name="faxattachformat" class="form-control fpbx-fax" id="faxattachformat" tabindex="" <?php echo $disabled ? 'disabled' : ''?>>
							<option value="pdf" <?php echo ($fax['faxattachformat'] == "pdf") ? 'selected' : ''?>><?php echo _('PDF')?></option>
							<option value="tif" <?php echo ($fax['faxattachformat'] == "tiff") ? 'selected' : ''?>><?php echo _('TIFF')?></option>
							<option value="both" <?php echo ($fax['faxattachformat'] == "both") ? 'selected' : ''?>><?php echo _('Both')?></option>
						</select>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-12">
			<span id="faxattachformat-help" class="help-block fpbx-help-block"><?php echo _('Formats to convert incoming fax files to before emailing.')?></span>
		</div>
	</div>
</div>
