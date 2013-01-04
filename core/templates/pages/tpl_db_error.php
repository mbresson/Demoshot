<?php

/*
 * This is a template file.
 * It is used to compose each page sent to a client by the server.
 * The template pages can make use of the variables stored in the array
 * $demoshot_var to retrieve all the information they need.
 * (see PAGE_VAR_* constants in utils/pages.php)
 */

assert(isset($demoshot));
assert(isset($demoshot_var));


/*
 * DEPENDENCIES
 */

require_once('core/utils/pages.php');
use \Demoshot\Pages;

?>

<div class="container">

<h1><?php echo _("Database error :("); ?></h1>

<h3>
<?php

	$conf = $demoshot->get_conf();
	$conf = $conf['manager'];
	
	$email_local_part = $conf['email_local_part'];
	$email_domain_part = $conf['email_domain_part'];

	printf(
		_("Please, contact our services at <span class='dm_eml_show'>%s</span>%s."),
		$email_local_part, $email_domain_part
	);

?>
</h3>

<h4>
<?php
	echo $demoshot->get_errmsg();
?>
</h4>

</div>
