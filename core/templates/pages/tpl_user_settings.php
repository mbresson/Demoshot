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

require_once('core/utils/database.php');

require_once('core/utils/pages.php');

require_once('core/utils/errors.php');

require_once('core/utils/user.php');
use \Demoshot\User;

require_once('core/utils/tag.php');
use \Demoshot\Tag;

require_once('core/utils/picture.php');
use \Demoshot\Picture;


/*
 * MAIN
 */

/*
 * Set the url of the target page.
 */
$action_url = "index.php?page_id=" . PAGE_ID_USER_SETTINGS;
$action_url .= "&amp;" . PAGE_ARG_USER_ID . "=" . $demoshot_var[PAGE_VAR_USERID];


/*
 * Initialize a $messages variable.
 * This is an array whose elements are array(bool, string).
 * The bool value indicates if the operation was successful or a failure.
 * The string value is the message.
 */
$messages = array();


/*
 * Comb the $_POST variable to check whether the user has sent a form.
 */

// the credentials
if(isset($_POST['us_current_password'])) {
	$ok = User\check_password(
		$demoshot,
		$demoshot_var[PAGE_VAR_USERID],
		$_POST['us_current_password']
	);
	
	if(!$ok) {
		$messages[] = array(
			false,
			_("Wrong password")
		);
	} else {
		$new_passwd = NULL;
		$new_email = NULL;
		
		if(isset($_POST['us_email']) && $_POST['us_email'] !== '') {
			// make sure it is a different email
			$user = User\get_user($demoshot, $demoshot_var[PAGE_VAR_USERID]);
			
			if($_POST['us_email'] != $user[GET_USER_EMAIL]) {
				$new_email = $_POST['us_email'];
			}
		}
		
		if(isset($_POST['us_new_password']) && $_POST['us_new_password'] !== '') {
			$new_passwd = $_POST['us_new_password'];
		}
		
		$ok = User\edit_account(
			$demoshot,
			$demoshot_var[PAGE_VAR_USERID],
			$new_passwd, $new_email
		);
		
		if(!$ok) {
			$messages[] = array(
				false,
				sprintf(
					_("The server ran into a database error ! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
					$demoshot->get_errcode(), $demoshot->get_errmsg()
				)
			);
		} else {
			if(is_string($new_passwd)) {
				$messages[] = array(
					true,
					_("Your password was successfully updated")
				);
			}
			
			if(is_string($new_email)) {
				$messages[] = array(
					true,
					_("Your email was successfully updated")
				);
				
				/*
				 * If the user is the webmaster,
				 * his email address must be updated in the configuration file too.
				 */
				if($demoshot_var[PAGE_VAR_ADMIN]) {
					$demoshot->change_manager_conf(NULL, $new_email);
				}
			}
		}
	}
}

// the account deletion
if(isset($_POST['us_deletion_password'])) {
	/*
	 * If this page is displayed after the account deletion,
	 * it means the deletion has FAILED.
	 * 
	 * index.php takes care of deleting the account.
	 */
	
	$messages[] = array(
		false,
		_("Wrong password")
	);
}

// the privacy settings
if(isset($_POST['us_privacy_change'])) {
	$private_pictures = isset($_POST['us_private_pictures']);
	$private_comments = isset($_POST['us_private_comments']);
	
	$ok = User\edit_account(
		$demoshot, $demoshot_var[PAGE_VAR_USERID],
		NULL, NULL, $private_pictures, $private_comments
	);
	
	if(!$ok) {
		
		switch($demoshot->get_errcode()) {
			case ERROR_ON_QUERY:
			case ERROR_MISSING_INFORMATION:
				$messages[] = array(
					false,
					sprintf(
						_("The server ran into a database error ! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
						$demoshot->get_errcode(), $demoshot->get_errmsg()
					)
				);
				break;
			
			default:
				$messages[] = array(
					false,
					sprintf(
						_("The server ran into a strange error! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
						$demoshot->get_errcode(), $demoshot->get_errmsg()
					)
				);
				break;
		}
	} else {
		$messages[] = array(
			true,
			_("Your privacy settings were successfully updated")
		);
	}
}

// the interests
if(isset($_POST['us_interests'])) {
	$user_id = $demoshot_var[PAGE_VAR_USERID];
	
	$new_tags = Tag\split_tags($_POST['us_interests']);
	$old_tags = Tag\enumerate_tags($demoshot, $user_id, TABLE_CODE_USER, false);
	
	/*
	 * The new tags the user is interested in.
	 */
	$added_tags = array_diff($new_tags, $old_tags);
	
	/*
	 * The tags the user is no longer interested in.
	 */
	$removed_tags = array_diff($old_tags, $new_tags);
	
	
	/*
	 * For each $added_tag:
	 * - If the tag doesn't exist, create it.
	 * - Link the user to the tag.
	 */
	
	foreach($added_tags as $tag_title) {
		if(in_array(strtolower($tag_title), $old_tags)) {
			continue;
		}
		
		$tag_id = Tag\get_tag_id($demoshot, $tag_title);
		
		if($tag_id === ERROR_NOT_FOUND) {
			$demoshot->clear_error();
			
			$tag_id = Tag\create_tag($demoshot, $tag_title);
		} else if($tag_id === ERROR_ON_QUERY) {
			$messages[] = array(
				false,
				sprintf(
					_("The server ran into a database error ! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
					$demoshot->get_errcode(), $demoshot->get_errmsg()
				)
			);
			
			break;
		}
		
		$ok = Tag\link_tag($demoshot, $tag_id, $user_id, TABLE_CODE_USER);
		
		if(!$ok) {
			$messages[] = array(
				false,
				sprintf(
					_("The server ran into a database error ! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
					$demoshot->get_errcode(), $demoshot->get_errmsg()
				)
			);
			break;
		}
	}
	
	
	/*
	 * For each $removed_tag:
	 * - Disconnect the user from the tag.
	 */
	foreach($removed_tags as $tag_title) {
		$tag_id = Tag\get_tag_id($demoshot, $tag_title);
		
		if($tag_id === ERROR_NOT_FOUND || $tag_id === ERROR_ON_QUERY) {
			$messages[] = array(
				false,
				sprintf(
					_("The server ran into a database error ! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
					$demoshot->get_errcode(), $demoshot->get_errmsg()
				)
			);
			break;
			
		}
		
		$ok = Tag\unlink_tag($demoshot, $user_id, TABLE_CODE_USER, $tag_id);
		
		if(!$ok) {
			$messages[] = array(
				false,
				sprintf(
					_("The server ran into a database error ! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
					$demoshot->get_errcode(), $demoshot->get_errmsg()
				)
			);
			break;
		}
	}
}


// the description
if(isset($_POST['us_description'])) {
	
	$ok = User\edit_account(
		$demoshot, $demoshot_var[PAGE_VAR_USERID],
		NULL, NULL, NULL, NULL, $_POST['us_description']
	);
	
	if(!$ok) {
		switch($demoshot->get_errcode()) {
			case ERROR_ON_QUERY:
			case ERROR_MISSING_INFORMATION:
				$messages[] = array(
					false,
					sprintf(
						_("The server ran into a database error ! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
						$demoshot->get_errcode(), $demoshot->get_errmsg()
					)
				);
				break;
			
			case ERROR_TEXT_OVERFLOW:
				// we need to retrieve the max length of a description
				$conf = $demoshot->get_conf();
				$conf = $conf['database'];
				
				if(!isset($conf['max_description_length'])) {
					$maxlength = 255;
				} else {
					$maxlength = (int) $conf['max_description_length'];
				}
			
				$messages[] = array(
					false,
					sprintf(
						_("Your description was too long (%d characters, maximum is %d)"),
						strlen($_POST['us_description']), $maxlength
					)
				);
				break;
			
			default:
				$messages[] = array(
					false,
					sprintf(
						_("The server ran into a strange error! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
						$demoshot->get_errcode(), $demoshot->get_errmsg()
					)
				);
				break;
		}
		
	} else {
		$messages[] = array(
			true,
			_("Your description was successfully updated")
		);
	}
}

// the avatar
if(isset($_FILES['us_avatar']) && $_FILES['us_avatar']['error'] != UPLOAD_ERR_NO_FILE) {
	$tmp_path = $_FILES['us_avatar']['tmp_name'];
	$tmp_dest_path = 'data/' . $_FILES['us_avatar']['name'];
	
	if($_FILES['us_avatar']['error'] !== UPLOAD_ERR_OK) {
		$messages[] = array(false, _("Your avatar was refused because its size exceeded the limit"));
	} else {
		move_uploaded_file($tmp_path, $tmp_dest_path);
		
		$avatar_method = isset($_POST['us_avatar_noresize']) ? AVATAR_METHOD_CENTER : AVATAR_METHOD_RESIZE;
		
		$user_id = $demoshot_var[PAGE_VAR_USERID];
		
		if(!User\create_avatar($demoshot, $user_id, $tmp_dest_path, $avatar_method)) {
			
			switch($demoshot->get_errcode()) {
				case ERROR_UNHANDLED_CASE:
					$messages[] = array(
						false,
						_("Your avatar was refused because its file format is not allowed (only PNG and JPG are)")
					);
					break;
				
				case ERROR_NO_WRITE_PERMISSION:
					$messages[] = array(
						false,
						sprintf(
							_("Your avatar was refused because the program has no write permission on the data/%d directory, please contact the webmaster"),
							$user_id
						)
					);
					break;
				
				default:
					$messages[] = array(
						false,
						sprintf(
							_("The server ran into a strange error! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
							$demoshot->get_errcode(), $demoshot->get_errmsg()
						)
					);
					break;
			}
		} else {
			$messages[] = array(true, _("Your avatar was successfully uploaded"));
		}
		
		unlink($tmp_dest_path);
	}
}


/*
 * Load the information we need to know about the user.
 */
$user = User\get_user($demoshot, $demoshot_var[PAGE_VAR_USERID]);
if(is_null($user)) {
	printf(
		_("Fatal error (core/utils/user.php\get_user(demoshot, %d)) => NULL (errcode: %d)"),
		$demoshot_var[PAGE_VAR_USERID], $demoshot->get_errcode()
	);
	
	die;
}


?>

<div class="container">


<?php

	if(isset($messages)) {
		foreach($messages as $message) {
			list($ok, $content) = $message;
			
			if($ok) {
				echo "<h3>";
			} else {
				echo "<h3 class='dm_wrong_input'>";
			}
			
			echo "$content</h3>";
		}
	}

?>

<ul class="nav nav-tabs">
	<li class="active">
		<a href="#1" data-toggle="tab"><?php echo _("Credentials") ?></a>
	</li>
	
	<li>
		<a href="#2" data-toggle="tab"><?php echo _("Description") ?></a>
	</li>
	
	<li>
		<a href="#3" data-toggle="tab"><?php echo _("Avatar") ?></a>
	</li>
	
	<li>
		<a href="#4" data-toggle="tab"><?php echo _("Privacy") ?></a>
	</li>
	
	<?php if(!$demoshot_var[PAGE_VAR_ADMIN]): ?>
	<li>
		<!--
			The webmaster cannot delete his account, unless he removes the whole website.
		-->
		
		<a href="#5" data-toggle="tab"><?php echo _("Account deletion") ?></a>
	</li>
	<?php endif; ?>
</ul>


<div class="tab-content">
	<div class="tab-pane active" id="1">
		<form class="form-horizontal" method="post" action="<?php echo $action_url ?>">
			<div class="control-group">
				<label class="control-label" for="username">
					<?php echo _("Username"); ?>
				</label>
				
				<div class="controls">
					<?php
					
						$username = $user[GET_USER_NAME];
					
						$input = '<input type="text" class="input-large" id="username"';
						$input .= " readonly='true' value='$username' />";
						
						echo $input;
					
					?>
				</div>
			</div>
			
			<div class="control-group">
				<label class="control-label" for="email">
					<?php echo _("Email address"); ?>
				</label>
				
				<div class="controls">
					<?php
					
						$user_email = $user[GET_USER_EMAIL];
					
						$input = '<input type="email" class="input-large" id="email" name="us_email"';
						$input .= " autofocus value='$user_email' />";
						
						echo $input;
					
					?>
				</div>
			</div>
			
			<div class="control-group">
				<label class="control-label" for="new_password">
					<?php echo _("New password"); ?>
				</label>
				
				<div class="controls">
					<input type="password" class="input-large" id="new_password" name="us_new_password" />
					
					<p class="help-block">
					<?php
						echo _("Only fill this field if you want to change your password.");
					?>
					</p>
				</div>
			</div>
		
			<div class="control-group">
				<label class="control-label" for="current_password">
					<?php echo _("Current password"); ?>
				</label>
				
				<div class="controls">
					<input type="password" class="input-large" id="current_password" name="us_current_password" required />
					
					<p class="help-block"><strong>
					<?php
						echo _("Required for any change pertaining to your credentials.");
					?>
					</strong></p>
				</div>
			</div>
			
			<div class="form-actions">
				<button type="submit" class="btn btn-primary">
					<?php echo _("Save my changes"); ?>
				</button>
			</div>
		</form>
	</div>
	
	<div class="tab-pane" id="2">
		<form class="form-horizontal" method="post" action="<?php echo $action_url ?>">
			<div class="control-group">
				<label class="control-label" for="interests">
					<?php echo _("My interests") ?>
				</label>
				
				<div class="controls">
					<?php
					
						// get all the tags the user is interested in
						$tags = Tag\enumerate_tags($demoshot, $demoshot_var[PAGE_VAR_USERID], TABLE_CODE_USER);
						$placeholder = _("programming, South Korea, holidays");
					
					?>
					
					<input type="text" class="input-xlarge" id="interests" name="us_interests" placeholder='<?php echo $placeholder ?>' value='<?php echo $tags ?>' autofocus />
					
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
						
						$value = '';
						if(is_string($user[GET_USER_DESCRIPTION])) {
							$value = $user[GET_USER_DESCRIPTION];
						}
						
					?>
					
					<textarea class="input-xlarge" id="description" name="us_description" rows="5" maxlength="<?php echo $maxlength ?>" placeholder="<?php echo _("Describe yourself in a few words..."); ?>"><?php echo $value ?></textarea>
				</div>
			</div>
			
			<div class="form-actions">
				<button type="submit" class="btn btn-primary">
					<?php echo _("Save my changes"); ?>
				</button>
			</div>
		</form>
	</div>
	
	<div class="tab-pane" id="3">
		<div class="row">
			<div class="span5"><!-- fake div to have the avatar centered --></div>
			
			<div class="span7">
				<img class="thumbnail" src='<?php echo User\get_user_avatar($demoshot_var[PAGE_VAR_USERID], true) ?>' alt='' />
			</div>
		</div>
		
		<form enctype="multipart/form-data" class="form-horizontal" method="post" action="<?php echo $action_url ?>">
			<div class="control-group">
				<label class="control-label" for="avatar">
					<?php echo _("What do I look like?") ?>
				</label>
				
				<div class="controls">
					<?php
						$max_size = Picture\get_max_size($demoshot); // bytes
					?>
					<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $max_size; ?>" />
					
					<input class="input-file" type="file" id="avatar" name="us_avatar" />
					
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
					<input type="checkbox" id="avatar_noresize" name="us_avatar_noresize" <?php if(isset($_POST['us_avatar_noresize'])) echo 'checked' ?> />
					
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
		
			<div class="form-actions">
				<button type="submit" class="btn btn-primary">
					<?php echo _("Save my changes"); ?>
				</button>
			</div>
		</form>
	</div>
	
	<div class="tab-pane" id="4">
		<p class="alert alert-info">
		<?php
			echo _("A private picture or comment can only be viewed by logged in users.")
		?>
		</p>
		
		<form class="form-horizontal" method="post" action="<?php echo $action_url ?>">
			
			<!-- needed to detect if the user sent this form, because all the input are checkboxes -->
			<input type="hidden" name="us_privacy_change" />
		
			<div class="control-group">
				<label class="control-label" for="private_pictures">
					<?php echo _("Pictures are private") ?>
				</label>
				
				<div class="controls">
					<input type="checkbox" id="private_pictures" name="us_private_pictures" <?php if($user[GET_USER_PRIVATE_PICTURES]) echo 'checked' ?> />
					
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
					<input type="checkbox" id="private_comments" name="us_private_comments" <?php if($user[GET_USER_PRIVATE_COMMENTS]) echo 'checked' ?> />
					
					<p class="help-block">
					<?php
						echo _("If you check this box, the comments you may post will be private by default.");
					?>
					</p>
				</div>
			</div>
			
			<div class="form-actions">
				<button type="submit" class="btn btn-primary">
					<?php echo _("Save my changes"); ?>
				</button>
			</div>
		</form>
	</div>
	
	<?php if(!$demoshot_var[PAGE_VAR_ADMIN]): ?>
	<div class="tab-pane" id="5">
		<p class="alert alert-error">
		<?php
			echo _("Beware! If you delete your account, all your marks and pictures uploaded on this website will be lost forever! There is no turning back.");
		?>
		</p>
		
		<form  class="form-horizontal" method="post" action="<?php echo $action_url ?>">
			<div class="control-group">
				<label class="control-label" for="deletion_password">
					<?php echo _("Password"); ?>
				</label>
				
				<div class="controls">
					<input type="password" class="input-large" id="deletion_password" name="us_deletion_password" required />
					
					<p class="help-block">
					<?php
						echo _("Enter your password if you do want to delete your account.");
					?>
					</p>
				</div>
			</div>
			
			<div class="form-actions">
				<button type="submit" class="btn btn-danger">
					<?php echo _("Delete my account"); ?>
				</button>
			</div>
		</form>
	</div>
	<?php endif; ?>
</div>

</div>

