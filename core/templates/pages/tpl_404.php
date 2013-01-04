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

?>

<div class="container">
	
<h1><?php echo $demoshot_var[PAGE_VAR_TITLE] . ' :('; ?></h1>

<h3><?php
	echo _("This page is probably not the page you were expecting to see.");
?></h3>
	
</div>
