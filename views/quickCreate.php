<div class="element-container">
	<div class="row">
		<div class="col-md-12">
			<div class="row">
				<div class="form-group">
					<div class="col-md-3">
						<label class="control-label" for="faxenabled"><?php echo _('Enable Fax')?></label>
						<i class="fa fa-question-circle fpbx-help-icon" data-for="faxenabled"></i>
					</div>
					<div class="col-md-9">
						<span class="radioset">
							<input type="radio" name="faxenabled" id="fm_on" value="yes">
							<label for="fm_on"><?php echo _('Yes')?></label>
							<input type="radio" name="faxenabled" id="fm_off" value="no" checked>
							<label for="fm_off"><?php echo _('No')?></label>
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
