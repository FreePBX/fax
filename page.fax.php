<?php /* $Id */
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
$request = $_REQUEST;
$engineinfo = engine_getinfo();
$version =  $engineinfo['version'];
$ast_ge_13 = version_compare($version, '13', 'ge');
?>
<div class="container-fluid">
	<h1><?php echo _('Fax Options')?></h1>
	<div class="well well-info">
		<?php echo _('Fax drivers supported by this module are:')?>
		<ul>
			<li <?php echo $ast_ge_13?' class="hidden" ':''?>><?php echo _("Fax for Asterisk (res_fax_digium.so) with licence")?></li>
			<li><?php echo _("Spandsp based app_fax (res_fax_spandsp.so)")?></li>
		</ul>
	</div>
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
