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

require_once('core/utils/errors.php');

require_once('core/utils/pages.php');
use \Demoshot\Pages;

require_once('core/utils/user.php');
use \Demoshot\User;


/*
 * MAIN
 */

$guest = $demoshot_var[PAGE_VAR_USERID] === -1; // see index.php
$reset_url = "index.php" . "?page_id=" . PAGE_ID_RESET_PASSWD;
$reset_attempt = isset($_POST['rp_username']);

if($reset_attempt) {
	$ok = false;
	
	if(isset($_POST['rp_username']) && $_POST['rp_username'] !== '') {
		$ok = User\reset_password($demoshot, $_POST['rp_username']);
	}
}

?>

<div class="container">
	
<?php if($reset_attempt): ?>

<h1>
<?php

	$error_code = $demoshot->get_errcode();

	if(!$ok) {
		if($error_code == ERROR_NOT_FOUND) {
			echo _("There is no account with this username!");
		} else {
			echo _("The email couldn't be sent!");
		}
	} else {
		echo _("Your password has been reset!");
	}
?>
</h1>

<?php else: ?>

<h1><?php echo _("Forgot your password?"); ?></h1>

<form class="form-horizontal" method="post" action="<?php echo $reset_url; ?>">
	<div class="control-group">
		<label class="control-label" for="username">
			<?php echo _("Username"); ?>
		</label>
		
		<div class="controls">
		<?php
		
			/*
			 * All input fields' names begin with rp_,
			 * to be not mistaken for values coming from another form (e.g. the signup form).
			 */
		
			$input = '<input type="text" class="input-large" id="username" name="rp_username"';
			$input .= ' placeholder="' . _('Foobar') . '" required autofocus />';
			
			echo $input;
		
		?>
		</div>
	</div>
	
	<div class="form-actions">
		<button type="submit" class="btn btn-primary">
			<?php echo _("Send me a new one"); ?>
		</button>
	</div>
</form>

<?php endif; ?>

</div>
