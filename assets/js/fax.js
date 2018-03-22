$(document).ready(function() {
	if ($('#system_instance').val() == 'disabled') {
		$('#defaultmail').hide();
	}
	$('#system_instance').click(function(){
		if ($(this).val() == 'disabled'){
			$('#defaultmail').hide();
		}else{
			$('#defaultmail').show();
		}
	});
});

$("#edit").submit(function() {
	var maxrate = $("#maxrate").val();
	var minrate = $("#minrate").val();

	if(parseInt(maxrate) < parseInt(minrate)) {
		return warnInvalid($("#maxrate"),_("Maximum transfer rate can not be less than Minimum transfer rate"));
	}
});
