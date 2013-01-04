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

require_once('core/utils/security.php');

require_once('core/utils/user.php');
use \Demoshot\User;

require_once('core/utils/tag.php');
use \Demoshot\Tag;

require_once('core/utils/picture.php');
use \Demoshot\Picture;


/*
 * MAIN
 */

$guest = $demoshot_var[PAGE_VAR_USERID] === -1; // see index.php
$username = $demoshot_var[PAGE_VAR_USERNAME];

/*
 * Check whether the user has tried to signup.
 */
$signup_attempt = false;
$signup_success = false;
$username_needle = FORBIDDEN_CHARACTERS; // the username mustn't contain these characters

if(
	isset($_POST['su_username']) && $_POST['su_username'] !== '' &&
	isset($_POST['su_password']) && $_POST['su_password'] !== '' &&
	isset($_POST['su_email']) && $_POST['su_email'] !== ''
) {
	$signup_attempt = true;
	
	/*
	 * Make sure the username doesn't contain illegal characters.
	 */
	$valid_username = !preg_match("/[" . preg_quote($username_needle) . "]{1,}/", $_POST['su_username']);
	
	$description = (isset($_POST['su_description']) && $_POST['su_description'] !== '') ? $_POST['su_description'] : NULL;
	
	if($valid_username && $_POST['su_password'] === $_POST['su_repeat_password']) {
		
		// try to create the account
		$signup_success = User\signup(
			$demoshot,
			$_POST['su_username'], $_POST['su_password'],
			TYPE_USER, $_POST['su_email'],
			isset($_POST['su_private_pictures']),
			isset($_POST['su_private_comments']),
			$description
		);
		
	} else {
		
		// if the two passwords do not match, don't event try to create an account
		$signup_success = false;
	}
	
}

?>

<div class="container">

<?php if(!$signup_attempt || !$signup_success): ?>

<h1>
<?php

	if($signup_attempt) {
		echo _("Signup failed, try again!");
	} else {
		echo _("Please sign up");
	}
?>
</h1>

<?php

	// if signup failed, display warnings
	if($signup_attempt) {
		echo "<h3 class='dm_wrong_input'>";
		
		switch($demoshot->get_errcode()) {
			case SIGNUP_USERNAME_EXISTS:
				echo _("This username is already in use");
				break;
			
			case SIGNUP_EMAIL_EXISTS:
				echo _("This email address is already in use");
				break;
			
			case SIGNUP_WRONG_MKDIR:
				echo _("Your directory couldn't be created, please contact the webmaster");
				break;
			
			default:
				if($_POST['su_password'] != $_POST['su_repeat_password']) {
					echo _("The passwords don't match");
				} else if(!$valid_username) {
					echo _("Your username contains illegal characters");
				} else {
					printf(
						_("The server ran into a strange error! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
						$demoshot->get_errcode(), $demoshot->get_errmsg()
					);
				}
				
				break;
		}
		
		echo "</h3>";
	}

	// needed for the form action
	$signup_url = "index.php" . "?page_id=" . PAGE_ID_SIGNUP . "&amp;signup";

?>

<form enctype="multipart/form-data" class="form-horizontal" method="post" action="<?php echo $signup_url; ?>">
	
	<fieldset>
		<legend><?php echo _("Mandatory") ?></legend>
		
		<div class="control-group">
			<label class="control-label" for="username">
				<?php echo _("Username"); ?>
			</label>
			
			<div class="controls">
				<?php
				
					$input = '<input type="text" class="input-large" id="username" maxlength="20" name="su_username"';
					
					// if signup failed, pre-fill the corresponding input field
					if($signup_attempt && $demoshot->get_errcode() != SIGNUP_USERNAME_EXISTS && $valid_username) {
						$input .= " value='" . $_POST['su_username'] . "' ";
					}
					
					$input .= ' placeholder="' . _('Foobar') . '" required autofocus />';
					
					echo $input;
				
				?>
				
				<p class="help-block">
				<?php
				
					$illegal_chars = '';
					for($it = 0, $c = strlen($username_needle); $it < $c; $it++) {
						$illegal_chars .= '&nbsp;&nbsp;&nbsp;';
						
						$illegal_chars .= "<strong>";
						$illegal_chars .= $username_needle[$it];
						$illegal_chars .= "</strong>";
					}
				
					printf(
						_("Your username cannot contain these characters: %s."),
						$illegal_chars
					);
				?>
				</p>
			</div>
		</div>
		
		<div class="control-group">
			<label class="control-label" for="email">
				<?php echo _("Email address"); ?>
			</label>
			
			<div class="controls">
				<?php
				
				
					$input = '<input type="email" class="input-large" id="email" name="su_email"';
					
					// if signup failed, pre-fill the corresponding input field
					if($signup_attempt && $demoshot->get_errcode() != SIGNUP_EMAIL_EXISTS) {
						$input .= " value='" . $_POST['su_email'] . "' ";
					}
					
					$input .= ' placeholder="' . _('foo@bar.com') . '" required />';
					
					echo $input;
				
				?>
			</div>
		</div>
		
		<div class="control-group">
			<label class="control-label" for="password">
				<?php echo _("Password"); ?>
			</label>
			
			<div class="controls">
				<input type="password" class="input-large" id="password" name="su_password" required />
			</div>
		</div>
	
		<div class="control-group">
			<label class="control-label" for="repeat_password">
				<?php echo _("Password (again)"); ?>
			</label>
			
			<div class="controls">
				<input type="password" class="input-large" id="repeat_password" name="su_repeat_password" required />
			</div>
		</div>
	</fieldset>
		
	<fieldset>
		<legend><?php echo _("Optional") ?></legend>
		
		<div class="control-group">
			<label class="control-label" for="interests">
				<?php echo _("My interests") ?>
			</label>
			
			<div class="controls">
				<?php
				
					// get all the tags the user is interested in
					$tags = '';
					
					if(isset($_POST['su_interests'])) {
						$tags = $_POST['su_interests'];
					}
					
					$tags_placeholder = _("programming, South Korea, holidays");
				
				?>
				
				<input type="text" class="input-xlarge" id="interests" name="su_interests" placeholder='<?php echo $tags_placeholder ?>' value='<?php echo $tags ?>' />
				
				<span class="help-inline">
					<?php echo _("a list of comma-separated keywords") ?>
				</span>
			</div>
		</div>
		
		<div class="control-group">
			<label class="control-label" for="description">
				<?php echo _("Who I am") ?>
			</label>
			
			<div class="controls">
				<?php
				
					// we need to retrieve the max length of a description
					$conf = $demoshot->get_conf();
					$conf = $conf['database'];
					
					if(!isset($conf['max_description_length'])) {
						$maxlength = 255;
					} else {
						$maxlength = $conf['max_description_length'];
					}
					
					$description_value = '';
					
					if(isset($_POST['su_description'])) {
						$description_value = $_POST['su_description'];
					}
					
				?>
				
				<textarea class="input-xlarge" id="description" name="su_description" maxlength="<?php echo $maxlength ?>" placeholder="<?php echo _("Describe yourself in a few words..."); ?>"><?php echo $description_value ?></textarea>
			</div>
		</div>
		
		<div class="control-group">
			<label class="control-label" for="avatar">
				<?php echo _("What do I look like?") ?>
			</label>
			
			<div class="controls">
				<?php
					$max_size = Picture\get_max_size($demoshot); // bytes
				?>
				<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $max_size; ?>" />
				
				<input class="input-file" type="file" id="avatar" name="su_avatar" />
				
				<p class="help-block">
				<?php
					printf(
						_("A PNG or JPG image, not bigger than %d MiB."),
						$max_size / 1024 / 1024
					);
				?>
				</p>
			</div>
		</div>
		
		<div class="control-group">
			<label class="control-label" for="avatar_noresize">
				<?php echo _("Don't scale my picture") ?>
			</label>
			
			<div class="controls">
				<input type="checkbox" id="avatar_noresize" name="su_avatar_noresize" <?php if(isset($_POST['su_avatar_noresize'])) echo 'checked' ?> />
				
				<p class="help-block">
				<?php
					printf(
						_("If you check this box, your picture will be centered and cropped to fit in a %dx%d image."),
						AVATAR_WIDTH, AVATAR_HEIGHT
					);
				?>
				</p>
			</div>
		</div>
	</fieldset>
	
	<fieldset>
		<legend><?php echo _("Privacy") ?></legend>
		
		<div class="control-group">
			<label class="control-label" for="private_pictures">
				<?php echo _("Pictures are private") ?>
			</label>
			
			<div class="controls">
				<input type="checkbox" id="private_pictures" name="su_private_pictures" <?php if(isset($_POST['su_private_pictures'])) echo 'checked' ?> />
				
				<p class="help-block">
				<?php
					echo _("If you check this box, the pictures you may upload will be private by default.");
				?>
				</p>
			</div>
		</div>
		
		<div class="control-group">
			<label class="control-label" for="private_comments">
				<?php echo _("Comments are private") ?>
			</label>
			
			<div class="controls">
				<input type="checkbox" id="private_comments" name="su_private_comments" <?php if(isset($_POST['su_private_comments'])) echo 'checked' ?> />
				
				<p class="help-block">
				<?php
					echo _("If you check this box, the comments you may post will be private by default.");
				?>
				</p>
			</div>
		</div>
	</fieldset>
	
	<div class="form-actions">
		<button type="submit" class="btn btn-primary">
			<?php echo _("I'm done, sign me up!"); ?>
		</button>
	</div>
</form>

<?php else: ?>

<h1>

<?php

	/*
	 * We are here  because the user has just successfully signed up.
	 */

	echo _("Congratulations! You can log in now.");

?>

</h1>

<?php

	/*
	 * As the final part of the registering process,
	 * - we create the user's avatar if he has chosen one and
	 * - we add the tags he has input to his profile.
	 */
	
	$user_id = User\get_user_id($demoshot, $_POST['su_username']);
	
	// create the avatar
	if(isset($_FILES['su_avatar']) && $_FILES['su_avatar']['error'] != UPLOAD_ERR_NO_FILE) {
		$tmp_path = $_FILES['su_avatar']['tmp_name'];
		$tmp_dest_path = 'data/' . $_FILES['su_avatar']['name'];
		
		if($_FILES['su_avatar']['error'] !== UPLOAD_ERR_OK) {
			echo '<h3 class="dm_wrong_input">';
			
			echo _("Your avatar was refused because its size exceeded the limit");
			
			echo '</h3>';
			
		} else {
			
			move_uploaded_file($tmp_path, $tmp_dest_path);
			
			$avatar_method = isset($_POST['su_avatar_noresize']) ? AVATAR_METHOD_CENTER : AVATAR_METHOD_RESIZE;
			
			if(!User\create_avatar($demoshot, $user_id, $tmp_dest_path, $avatar_method)) {
				echo '<h3 class="dm_wrong_input">';
				
				switch($demoshot->get_errcode()) {
					case ERROR_UNHANDLED_CASE:
						echo _("Your avatar was refused because its file format is not allowed (only PNG and JPG are)");
						break;
					
					case ERROR_NO_WRITE_PERMISSION:
						printf(
							_("Your avatar was refused because the program has no write permission on the data/%d directory, please contact the webmaster"),
							$user_id
						);
						break;
					
					default:
						printf(
							_("The server ran into a strange error! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
							$demoshot->get_errcode(), $demoshot->get_errmsg()
						);
						break;
				}
				
				echo '</h3>';
			}
			
			unlink($tmp_dest_path);
		}
	}
	
	// add the tags
	if(isset($_POST['su_interests'])) {
		$new_tags = Tag\split_tags($_POST['su_interests']);
		
		if(count($new_tags) > 0) {
			/*
			 * For each $added_tag:
			 * - If the tag doesn't exist, create it.
			 * - Link the user to the tag.
			 */
			foreach($new_tags as $tag_title) {
				$tag_id = Tag\get_tag_id($demoshot, $tag_title);
				
				if($tag_id === ERROR_NOT_FOUND) {
					$demoshot->clear_error();
					$tag_id = Tag\create_tag($demoshot, $tag_title);
					
				} else if($tag_id === ERROR_ON_QUERY) {
					break;
				}
				
				Tag\link_tag($demoshot, $tag_id, $user_id, TABLE_CODE_USER);
			}
		}
	}

	/*
	 * The user has just signed up,
	 * we guess he want's to log in,
	 * so we display a login button.
	 */
	$login_url = "index.php" . "?page_id=" . PAGE_ID_LOGIN;
	
	echo "<p><a class='btn btn-large btn-success' href='$login_url'>";
	echo '<i class="icon-white icon-arrow-down"></i> ' . _("Login");
	echo '</a></p>';

	endif;

?>

</div>
