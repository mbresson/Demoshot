<?php

/*
 * This is a template file.
 * It is used to compose each page sent to a client by the server.
 * The template pages can make use of the variables stored in the array
 * $demoshot_var to retrieve all the information they need.
 */

assert(isset($demoshot));
assert(isset($demoshot_var));


/*
 * DEPENDENCIES
 */

require_once('core/utils/pages.php');

?>

<!doctype html>
<html lang="<?php echo $demoshot->get_lang() ?>">

<head>
	<meta charset="UTF-8" />
	
	<link rel="stylesheet" href="res/css/bootstrap.css" />
	<link rel="stylesheet" href="res/css/custom.css" />
	
	<link href='res/img/Demoshot-favicon.ico' rel='icon' type='image/x-icon' />
	
	<title><?php echo $demoshot_var[PAGE_VAR_TITLE]; ?></title>
</head>

<body>
