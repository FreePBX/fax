<?php /* $Id */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
$request = $_REQUEST;
$engineinfo = engine_getinfo();
$version =  $engineinfo['version'];
$ast_ge_13 = version_compare($version, '13', 'ge');
?>
<div class="container-fluid">
	<h1><?php echo _('Fax Options')?></h1>
	<div class = "display full-border">
		<div class="row">
			<div class="col-sm-12">
				<div class="fpbx-container">
					<div class="display full-border">
						<?php echo load_view(__DIR__.'/views/form.php', array('request' => $request))?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
