<?php

// Delete the old code if still there
//
$fcc = new featurecode('fax', 'simu_fax');
$fcc->delete();
unset($fcc);	

sql('DROP TABLE IF EXISTS fax_details, fax_incoming, fax_users');
?>
