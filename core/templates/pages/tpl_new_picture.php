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

require_once('core/utils/pages.php');

require_once('core/utils/database.php');

require_once('core/utils/user.php');
use \Demoshot\User;

require_once('core/utils/picture.php');
use \Demoshot\Picture;

require_once('core/utils/tag.php');
use \Demoshot\Tag;


/*
 * MAIN
 */
$user_id = $demoshot_var[PAGE_VAR_USERID];
$action_url = "index.php?page_id=" . PAGE_ID_NEW_PICTURE;
$title_needle = FORBIDDEN_CHARACTERS; // the title mustn't contain these characters

$creation_attempt = false;

/*
 * Initialize a $messages variable.
 * This is an array whose elements are array(bool, string).
 * The bool value indicates if the operation was successful or a failure.
 * The string value is the message.
 */
$messages = array();

?>

<div class="container">

<?php
	
	if(
		isset($_POST['np_title']) && $_POST['np_title'] !== '' &&
		isset($_FILES['np_file'])
	) {
		$creation_attempt = true;
		
		/*
		 * If a step fails, it will set $creation_success to false.
		 * The next steps will check it to make sure the picture can be added.
		 */
		$creation_success = true;
		
		
		/*
		 * Make sure the title doesn't contain illegal characters.
		 */
		$valid_title = !preg_match("/[" . preg_quote($title_needle) . "]{1,}/", $_POST['np_title']);
		
		if(!$valid_title) {
			$messages[] = array(
				false,
				_("The title contains illegal characters")
			);
			
			$creation_success = false;
			
		}
		
		
		/*
		 * Make sure there is no existing picture
		 * with the same title by the same user.
		 */
		if($creation_success) {
			$existing_picture = Picture\get_picture_id($demoshot, $_POST['np_title']);
			if($existing_picture < 0) {
				$demoshot->clear_error();
			} else {
				$valid_title = Picture\get_picture_author($demoshot, $existing_picture) < 0;
				
				if($valid_title) {
					$demoshot->clear_error();
				} else {
					// there is a picture with the same title by the same user
					$messages[] = array(
						false,
						_("This title is already used for one of your pictures")
					);
					
					$creation_success = false;
				}
			}
		}
		
		
		/*
		 * Make sure the picture has been successfully uploaded on the server.
		 */
		if($creation_success) {
			if($_FILES['np_file']['error'] == UPLOAD_ERR_OK) {
				/*
				 * We move the file to a temporary place because
				 * we need to create it in the database first.
				 */
				
				$tmp_path = $_FILES['np_file']['tmp_name'];
				$tmp_dest_path = 'data/' . $_FILES['np_file']['name'];
				
				move_uploaded_file($tmp_path, $tmp_dest_path);
				
			} else {
				switch($_FILES['np_file']['error']) {
					case UPLOAD_ERR_INI_SIZE:
					case UPLOAD_ERR_FORM_SIZE:
						$messages[] = array(
							false,
							_("Your file was refused because its size exceeded the limit")
						);
						
						break;
					
					case UPLOAD_ERR_PARTIAL:
						$messages[] = array(
							false,
							_("Your file was not entirely uploaded, please try again")
						);
						
						break;
						
					case UPLOAD_ERR_NO_FILE:
						$messages[] = array(
							false,
							_("You must choose a picture")
						);
						
						break;
					
					default:
						$messages[] = array(
							false,
							sprintf(
								_("Your file couldn't be uploaded, please report it to the webmaster (errcode: %d, errmsg: %s)"),
								$_FILES['np_file']['error'], $_FILES['np_file']['tmp_name']
							)
						);
				}
				
				$creation_success = false;
			}
			
			
			/*
			 * Create the picture in the database.
			 */
			if($creation_success) {
				$title = $_POST['np_title'];
				$private = isset($_POST['np_private']);
				
				$description = NULL;
				$license = NULL;
				
				if(isset($_POST['np_description']) && $_POST['np_description'] !== '') {
					$description = $_POST['np_description'];
				}
				
				if(isset($_POST['np_license']) && $_POST['np_license'] !== '') {
					$license = $_POST['np_license'];
				}
				
				$type = strtolower(pathinfo($tmp_dest_path, PATHINFO_EXTENSION));
				
				if($type == "png") {
					$type = PICTURE_TYPE_PNG;
				} else if($type == "jpg" || $type == "jpeg") {
					$type = PICTURE_TYPE_JPG;
				} else {
					$type = NULL;
					
					$messages[] = array(
						false,
						_("Your picture was refused because its file format is not allowed (only PNG and JPG are)")
					);
					
					$creation_success = false;
				}
				
				if(!is_null($type)) {
					$picture_id = Picture\create_picture(
						$demoshot, $title, $demoshot_var[PAGE_VAR_USERID],
						$private, $type, $description, $license
					);
					
					switch($picture_id) {
						case ERROR_ON_QUERY:
							$messages[] = array(
								false,
								sprintf(
									_("The server ran into a database error ! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
									$demoshot->get_errcode(), $demoshot->get_errmsg()
								)
							);
							
							$creation_success = false;
							break;
						
						case ERROR_TEXT_OVERFLOW:
						case ERROR_MISSING_INFORMATION:
							$messages[] = array(
								false,
								sprintf(
									_("The server ran into a strange error! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
									$demoshot->get_errcode(), $demoshot->get_errmsg()
								)
							);
							
							$creation_success = false;
							break;
					}
				}
			}
			
			
			/*
			 * Create the thumbnail.
			 */
			if($creation_success) {
				$extension = $type == PICTURE_TYPE_JPG ? ".jpg" : ".png";
				$user_dir = 'data/' . $demoshot_var[PAGE_VAR_USERID] . '/';
				$final_path = $user_dir . "$picture_id" . $extension;
				
				if(!copy($tmp_dest_path, $final_path)) {
					Picture\delete_picture($demoshot, $picture_id);
					
					$messages[] = array(
						false,
						sprintf(
							_("Your file was refused because the program has no write permission on the %s directory, please contact the webmaster"),
							$user_dir
						)
					);
					echo "</h3>";
					
					$creation_success = false;
				} else {
					$ok = Picture\create_thumbnail($demoshot, $picture_id);
					
					if(!$ok) {
						Picture\delete_picture($demoshot, $picture_id);
						
						$messages[] = array(
							false,
							sprintf(
								_("The server ran into a strange error! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
								$demoshot->get_errcode(), $demoshot->get_errmsg()
							)
						);
						
						$creation_success = false;
					}
				}
			}
			
			
			// remove the temporary file
			if(isset($tmp_dest_path) && file_exists($tmp_dest_path)) {
				unlink($tmp_dest_path);
			}
			
			
			/*
			 * Add the tags
			 */
			if($creation_success && isset($_POST['np_tags']) && $_POST['np_tags'] !== '') {
				$new_tags = Tag\split_tags($_POST['np_tags']);
				
				if(count($new_tags) > 0) {
					/*
					 * For each $added_tag:
					 * - If the tag doesn't exist, create it.
					 * - Link the picture to the tag.
					 */
					foreach($new_tags as $tag_title) {
						$tag_id = Tag\get_tag_id($demoshot, $tag_title);
						
						if($tag_id === ERROR_NOT_FOUND) {
							$demoshot->clear_error();
							$tag_id = Tag\create_tag($demoshot, $tag_title);
							
						} else if($tag_id === ERROR_ON_QUERY) {
							break;
						}
						
						Tag\link_tag($demoshot, $tag_id, $picture_id, TABLE_CODE_PICTURE);
					}
				}
			}
			
			// end of the process!
		}
	}
?>

<?php if($creation_attempt && $creation_success):

	/*
	 * Notify the followers that the visitor has added a new picture.
	 */
	
	User\notify_for_new_picture($demoshot, $demoshot_var[PAGE_VAR_USERID], $picture_id);


	/*
	 * Display a button to go to the page of the new picture.
	 */

	echo "<h1>";
	echo _("The picture was successfully added to your album");
	echo "</h1>";
	
	$picture_url = "index.php?page_id=" . PAGE_ID_PICTURE;
	$picture_url .= "&amp;" . PAGE_ARG_PICTURE_ID . "=$picture_id";
	
	$picture_text = _("See the new picture");
	
	echo "<a class='btn btn-success' href='$picture_url'>";
	echo "<i class='icon-white icon-arrow-right'></i> $picture_text</a>";
	
else: ?>

<h1><?php echo $demoshot_var[PAGE_VAR_TITLE] ?></h1>

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

<form enctype="multipart/form-data" class="form-horizontal" method="post" action='<?php echo $action_url ?>'>
	<fieldset>
		<legend><?php echo _("Mandatory") ?></legend>
		
		<div class="control-group">
			<label class="control-label" for="title">
				<?php echo _("Title") ?>
			</label>
			
			<div class="controls">
			<?php
				$input = '<input type="text" class="input-xlarge" id="title" maxlength="50" name="np_title"';
				if($creation_attempt && $valid_title) {
					$input .= " value='" . $_POST['np_title'] . "' ";
				}
				$input .= ' placeholder="' . _("My grandma in the kitchen") . '" required autofocus />';
				
				echo $input;
			?>
			
				<p class="help-block">
				<?php
					$illegal_chars = '';
					for($it = 0, $c = strlen($title_needle); $it < $c; $it++) {
						$illegal_chars .= '&nbsp;&nbsp;&nbsp;';
						
						$illegal_chars .= "<strong>";
						$illegal_chars .= $title_needle[$it];
						$illegal_chars .= "</strong>";
					}
				
					printf(
						_("The title cannot contain these characters: %s."),
						$illegal_chars
					);
				?>
				</p>
			</div>
		</div>
		
		<div class="control-group">
			<label class="control-label" for="file">
				<?php echo _("File") ?>
			</label>
			
			<div class="controls">
				<?php
					$max_size = Picture\get_max_size($demoshot); // bytes
				?>
				<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $max_size; ?>" />
				
				<input class="input-file" type="file" id="file" name="np_file" required />
				
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
			<label class="control-label" for="private">
				<?php echo _("Picture is private"); ?>
			</label>
			
			<div class="controls">
				<?php
					/*
					 * The checkbox will be checked if the user settings are
					 * to keep his pictures private by default.
					 */
					
					$user = User\get_user($demoshot, $user_id);
					$private = $user[GET_USER_PRIVATE_PICTURES];
					
					if($creation_attempt) {
						$private = isset($_POST['np_private']);
					}
					
				?>
				
				<input type="checkbox" id="private" name="np_private" <?php if($private) echo 'checked' ?> />
				
				<p class="help-block">
				<?php
					echo _("If you check this box, your picture will only be visible to logged in users.");
				?>
				</p>
			</div>
		</div>
	</fieldset>
	
	<fieldset>
		<legend><?php echo _("Optional") ?></legend>
		
		<div class="control-group">
			<label class="control-label" for="tags">
				<?php echo _("Tags") ?>
			</label>
			
			<div class="controls">
				<?php
				
					// get all the tags the user is interested in
					$tags = '';
					
					if(isset($_POST['np_tags'])) {
						$tags = $_POST['np_tags'];
					}
					
					$tags_placeholder = _("sunny, friends, vintage");
				
				?>
				
				<input type="text" class="input-xlarge" id="tags" name="np_tags" placeholder='<?php echo $tags_placeholder ?>' value='<?php echo $tags ?>' />
				
				<span class="help-block">
					<?php echo _("a list of comma-separated keywords") ?>
				</span>
			</div>
		</div>
		
		<div class="control-group">
			<label class="control-label" for="description">
				<?php echo _("Description") ?>
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
					
					if(isset($_POST['np_description'])) {
						$description_value = $_POST['np_description'];
					}
					
				?>
				
				<textarea class="input-xlarge" id="description" name="np_description" maxlength="<?php echo $maxlength ?>" placeholder="<?php echo _("Describe the picture in a few words..."); ?>"><?php echo $description_value ?></textarea>
			</div>
		</div>
		
		<div class="control-group">
			<label class="control-label" for="license">
				<?php echo _("Picture license") ?>
			</label>
			
			<div class="controls">
				<?php
				
					$license_value = '';
					
					if(isset($_POST['np_license'])) {
						$license_value = $_POST['np_license'];
					}
					
					$license_fill = _("What can the other people do of your photo?");
					
				?>
				
				<textarea class="input-xlarge" id="license" name="np_license" maxlength="<?php echo $maxlength ?>" placeholder="<?php echo $license_fill ?>"><?php echo $license_value ?></textarea>
			</div>
		</div>
	</fieldset>
	
	<div class="form-actions">
		<button type="submit" class="btn btn-primary">
			<?php echo _("Add to my album"); ?>
		</button>
	</div>
</form>

<?php endif; ?>

</div>
