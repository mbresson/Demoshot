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

require_once('core/utils/user.php');
use \Demoshot\User;


/*
 * MAIN
 */

// check whether the webmaster has tried to log in
$login_attempt = false;
if(isset($_POST['ln_username']) && isset($_POST['ln_password'])) {
	$login_attempt = true;
}

?>

<div class="container">
	
	<h1><?php
		echo _("The website is currently undergoing maintenance")
	?></h1>

<?php if($demoshot_var[PAGE_VAR_USERID] == -1 || !$demoshot_var[PAGE_VAR_ADMIN]): ?>
	<h3>
	<?php
		
		$error_code = $demoshot->get_errcode(); // in case login failed
	
		if($login_attempt) {
			echo _("Login failed, try again!");
		} else {
			echo _("If you are the webmaster, use the form below to login:");
		}
	?>
	</h3>
	
	<?php
		
		// if login failed, display warnings
		if($error_code === LOGIN_WRONG_USERNAME) {
			echo "<h3 class='dm_wrong_input'>" . _("Wrong username") . "</h3>";
			
		} else if($error_code === LOGIN_WRONG_PASSWD) {
			echo "<h3 class='dm_wrong_input'>" . _("Wrong password") . "</h3>";
		}
		
		// needed for the form action
		$login_url = "index.php?login";
	
	?>
	
	<form method="post" action="<?php echo $login_url ?>" class="form-horizontal">
		<div class="control-group">
			<label class="control-label" for="username">
				<?php echo _("Username"); ?>
			</label>
			
			<div class="controls">
				<input type="text" class="input-large" id="username" maxlength="20" name="ln_username" placeholder="<?php echo _('Foobar') ?>" required autofocus />
			</div>
		</div>
		
		<div class="control-group">
			<label class="control-label" for="password">
				<?php echo _("Password"); ?>
			</label>
			
			<div class="controls">
				<input type="password" class="input-large" id="password" name="ln_password" required />
			</div>
		</div>
		
		<div class="form-actions">
			<button type="submit" class="btn btn-primary">
				<?php echo _("Connect"); ?>
			</button>
		</div>
	</form>
<?php endif; ?>

</div>
