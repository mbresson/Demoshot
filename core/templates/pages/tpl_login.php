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

require_once('core/utils/user.php');
use \Demoshot\User;


/*
 * MAIN
 */

$guest = $demoshot_var[PAGE_VAR_USERID] === -1; // see index.php
$username = $demoshot_var[PAGE_VAR_USERNAME];

// check whether the user has tried to log in
$login_attempt = false;
if(isset($_POST['ln_username']) && isset($_POST['ln_password'])) {
	$login_attempt = true;
}

?>



<div class="container">

<?php if($guest): ?>

<h1>
<?php

	$error_code = $demoshot->get_errcode(); // in case login failed

	if($login_attempt) {
		echo _("Login failed, try again!");
	} else {
		echo _("Please log in");
	}
?>
</h1>

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

<form class="form-horizontal" method="post" action="<?php echo $login_url; ?>">
	<div class="control-group">
		<label class="control-label" for="username">
			<?php echo _("Username"); ?>
		</label>
		
		<div class="controls">
		<?php
		
			/*
			 * All input fields' names begin with ln_,
			 * so that index.php can recognize them as values coming from the login form.
			 */
		
			$input = '<input type="text" class="input-large" id="username" maxlength="20" name="ln_username"';
			
			// if login failed but the username was right, pre-fill the corresponding input field
			if($login_attempt && $error_code != LOGIN_WRONG_USERNAME) {
				$input .= " value='" . $_POST['ln_username'] . "' ";
			}
			
			$input .= ' placeholder="' . _('Foobar') . '" required autofocus />';
			
			echo $input;
		
		?>
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

<p>
<?php

	$reset_url = "index.php" . "?page_id=" . PAGE_ID_RESET_PASSWD;
	echo "<a href='$reset_url'>" . _("Forgot your password? Reset it.") . "</a>";
	
?>
</p>

<p>
<?php

	$signup_url = "index.php?page_id=" . PAGE_ID_SIGNUP;
	echo "<a href='$signup_url'>" . _("No account? Create one.") . "</a>";

?>
</h4>

<?php else: ?>

<h1>
<?php printf(_("You're already logged in."), $username); ?>
</h1>

<?php

	/*
	 * If the user is already logged in,
	 * we guess he want's to log out to log in as another user,
	 * so we display a logout button.
	 */
	$logout_url = "index.php?logout&page_id=" . PAGE_ID_LOGIN;
	
	echo "<p><a class='btn btn-large btn-danger' href='$logout_url'>";
	echo '<i class="icon-white icon-off"></i> ' . _("Logout");
	echo '</a></p>';

	endif;

?>
	
</div>
