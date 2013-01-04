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

require_once('core/utils/security.php');

require_once('core/utils/pages.php');

require_once('core/utils/database.php');

require_once('core/utils/picture.php');
use \Demoshot\Picture;

require_once('core/utils/tag.php');
use \Demoshot\Tag;


/*
 * MAIN
 */

$picture_id = (int) $_GET[PAGE_ARG_PICTURE_ID];
$action_url = "index.php?page_id=" . PAGE_ID_EDIT_PICTURE;
$action_url .= "&amp;" . PAGE_ARG_PICTURE_ID . "=$picture_id";
$title_needle = FORBIDDEN_CHARACTERS; // the title mustn't contain these characters

$edit_attempt = false;

/*
 * We detect if the form was sent by check $_POST['ep_title'],
 * because ep_title is the only field that cannot be empty.
 */
if(isset($_POST['ep_title'])) {
	$edit_attempt = true;
	$edit_success = true;
}

$picture = Picture\get_picture($demoshot, $picture_id);
if(is_null($picture)) {
	printf(
		_("Fatal error (core/utils/user.php\get_picture(demoshot, %d)) => NULL (errcode: %d)"),
		$picture_id, $demoshot->get_errcode()
	);
	
	die;
}

?>

<div class="container">

<?php if($edit_attempt):

$new_title = NULL; $valid_title = true;
$new_description = NULL;
$new_license = NULL;
$new_private = NULL;

/*
 * Has the title been modified?
 */

if(
	isset($_POST['ep_title']) && $_POST['ep_title'] !== '' &&
	htmlspecialchars($_POST['ep_title'], ENT_QUOTES | ENT_HTML5) != $picture[GET_PICTURE_TITLE]
) {
	
	/*
	 * Make sure the username doesn't contain illegal characters.
	 */
	$valid_title = !preg_match("/[" . preg_quote($title_needle) . "]{1,}/", $_POST['ep_title']);
	
	if($valid_title) {
		$new_title = $_POST['ep_title'];
	} else {
		$edit_success = false;
	}
	
	$existing_title = false;
	
	/*
	 * Make sure there is no existing picture
	 * with the same title by the same user.
	 */
	if($edit_success) {
		$existing_picture = Picture\get_picture_id($demoshot, $_POST['ep_title']);
		if($existing_picture < 0) {
			$demoshot->clear_error();
		} else {
			$valid_title = Picture\get_picture_author($demoshot, $existing_picture) < 0;
			
			if($valid_title) {
				$demoshot->clear_error();
			} else {
				// there is a picture with the same title by the same user
				$existing_title = true;
				$edit_success = false;
			}
		}
	}
}


if($edit_success) {
	
	/*
	 * Check whether the description and the license have changed.
	 */
	
	if(isset($_POST['ep_description'])) {
		if($_POST['ep_description'] != $picture[GET_PICTURE_DESCRIPTION]) {
			$new_description = $_POST['ep_description'];
		}
	}
	
	if(isset($_POST['ep_license'])) {
		if($_POST['ep_license'] != $picture[GET_PICTURE_LICENSE]) {
			$new_license = $_POST['ep_license'];
		}
	}
	
	/*
	 * Check whether the picture is (no longer) private.
	 */
	
	if(isset($_POST['ep_private'])) {
		if(!$picture[GET_PICTURE_PRIVATE]) {
			$new_private = true;
		}
	} else {
		if($picture[GET_PICTURE_PRIVATE]) {
			$new_private = false;
		}
	}
	
	
	/*
	 * Update the whole thing.
	 */
	
	$edit_success = Picture\edit_picture(
		$demoshot, $picture_id,
		$new_title, $new_description,
		$new_private, $new_license
	);
	
	/*
	 * Update the tags.
	 */
	
	if($edit_success && isset($_POST['ep_tags'])) {
		$new_tags = Tag\split_tags($_POST['ep_tags']);
		$old_tags = Tag\enumerate_tags($demoshot, $picture_id, TABLE_CODE_PICTURE, false);
		
		/*
		 * The tags that must be added.
		 */
		$added_tags = array_diff($new_tags, $old_tags);
		
		/*
		 * The tags that must be removed.
		 */
		$removed_tags = array_diff($old_tags, $new_tags);
		
		
		/*
		 * For each $added_tag:
		 * - If the tag doesn't exist, create it.
		 * - Link the picture to the tag.
		 */
		foreach($added_tags as $tag_title) {
			$tag_id = Tag\get_tag_id($demoshot, $tag_title);
			
			if($tag_id === ERROR_NOT_FOUND) {
				$demoshot->clear_error();
				
				$tag_id = Tag\create_tag($demoshot, $tag_title);
			} else if($tag_id === ERROR_ON_QUERY) {
				printf(
					_("The server ran into a database error ! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
					$demoshot->get_errcode(), $demoshot->get_errmsg()
				);
				
				break;
			}
			
			$ok = Tag\link_tag($demoshot, $tag_id, $picture_id, TABLE_CODE_PICTURE);
			
			if(!$ok) {
				printf(
					_("The server ran into a database error ! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
					$demoshot->get_errcode(), $demoshot->get_errmsg()
				);
				
				break;
			}
		}
		
		
		/*
		 * For each $removed_tag:
		 * - Disconnect the picture from the tag.
		 */
		foreach($removed_tags as $tag_title) {
			$tag_id = Tag\get_tag_id($demoshot, $tag_title);
			
			if($tag_id === ERROR_NOT_FOUND || $tag_id === ERROR_ON_QUERY) {
				printf(
					_("The server ran into a database error ! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
					$demoshot->get_errcode(), $demoshot->get_errmsg()
				);
				
				break;
				
			}
			
			$ok = Tag\unlink_tag($demoshot, $picture_id, TABLE_CODE_PICTURE, $tag_id);
			
			if(!$ok) {
				printf(
					_("The server ran into a database error ! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
					$demoshot->get_errcode(), $demoshot->get_errmsg()
				);
				
				break;
			}
		}
	}
}

endif; ?>

<?php if($edit_attempt && $edit_success):

	echo "<h1>";
	echo _("Your picture has been successfully updated");
	echo "</h1>";
	
	$picture_url = "index.php?page_id=" . PAGE_ID_PICTURE;
	$picture_url .= "&amp;" . PAGE_ARG_PICTURE_ID . "=$picture_id";
	
	$picture_text = _("Go back to the picture page");
	
	echo "<h3><a class='btn btn-success' href='$picture_url'>";
	echo "<i class='icon-white icon-arrow-right'></i> $picture_text</a></h3>";
	
else: ?>

<h1><?php echo $demoshot_var[PAGE_VAR_TITLE] ?></h1>

<?php

	if($edit_attempt) {
		echo "<h3 class='dm_wrong_input'>";
		
		if(!$valid_title) {
			if($existing_title) {
				echo _("This title is already used for one of your pictures");
			} else {
				echo _("The title contains illegal characters");
			}
			
		} else {
			if($demoshot->get_errcode() == ERROR_ON_QUERY) {
				printf(
					_("The server ran into a database error ! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
					$demoshot->get_errcode(), $demoshot->get_errmsg()
				);
			} else {
				printf(
					_("The server ran into a strange error! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
					$demoshot->get_errcode(), $demoshot->get_errmsg()
				);
			}
		}
		
		echo "</h3>";
	}

?>

<div class="row">
	<div class="span5"><!-- fake div to have the thumbnail centered --></div>
	
	<div class="span2">
	<?php
		$path = Picture\get_picture_path($demoshot, $picture_id);
		$tn_path = Picture\get_thumbnail_path($demoshot, $path);
		
		echo "<img class='thumbnail' src='$tn_path' alt='' />";
	?>
	</div>
</div>

<form class="form-horizontal" method="post" action='<?php echo $action_url ?>'>
	<div class="control-group">
		<label class="control-label" for="title">
			<?php echo _("Title") ?>
		</label>
		
		<div class="controls">
		<?php
			$title = $picture[GET_PICTURE_TITLE];
			
			$input = '<input type="text" class="input-xlarge" id="title" maxlength="50" name="ep_title"';
			$input .= " value='$title' ";
			$input .= ' placeholder="' . _("My grandma in the kitchen") . '" autofocus />';
			
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
		<label class="control-label" for="tags">
			<?php echo _("Tags") ?>
		</label>
		
		<div class="controls">
			<?php
			
				// get all the tags the user is interested in
				$tags = Tag\enumerate_tags($demoshot, $picture_id, TABLE_CODE_PICTURE);
				
				if(isset($_POST['ep_tags'])) {
					$tags = $_POST['ep_tags'];
				}
				
				$tags_placeholder = _("sunny, friends, vintage");
			
			?>
			
			<input type="text" class="input-xlarge" id="tags" name="ep_tags" placeholder='<?php echo $tags_placeholder ?>' value='<?php echo $tags ?>' />
			
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
				
				$description_value = $picture[GET_PICTURE_DESCRIPTION];
				
				if(isset($_POST['ep_description'])) {
					$description_value = $_POST['ep_description'];
				}
				
			?>
			
			<textarea class="input-xlarge" id="description" name="ep_description" maxlength="<?php echo $maxlength ?>" placeholder="<?php echo _("Describe the picture in a few words..."); ?>"><?php echo $description_value ?></textarea>
		</div>
	</div>
	
	<div class="control-group">
		<label class="control-label" for="license">
			<?php echo _("Picture license") ?>
		</label>
		
		<div class="controls">
			<?php
			
				$license_value = $picture[GET_PICTURE_LICENSE];
				
				if(isset($_POST['ep_license'])) {
					$license_value = $_POST['ep_license'];
				}
				
				$license_fill = _("What can the other people do of your photo?");
				
			?>
			
			<textarea class="input-xlarge" id="license" name="ep_license" maxlength="<?php echo $maxlength ?>" placeholder="<?php echo $license_fill ?>"><?php echo $license_value ?></textarea>
		</div>
	</div>
	
	<div class="control-group">
		<label class="control-label" for="private">
			<?php echo _("Picture is private"); ?>
		</label>
		
		<div class="controls">
			<?php
				$private = $picture[GET_PICTURE_PRIVATE];
				
				if($edit_attempt) {
					$private = isset($_POST['ep_private']);
				}
			?>
			
			<input type="checkbox" id="private" name="ep_private" <?php if($private) echo 'checked' ?> />
			
			<p class="help-block">
			<?php
				echo _("If you check this box, your picture will only be visible to logged in users.");
			?>
			</p>
		</div>
	</div>

	<div class="form-actions">
		<button type="submit" class="btn btn-primary">
			<?php echo _("Apply changes"); ?>
		</button>
	</div>
</form>

<?php endif; ?>

</div>
