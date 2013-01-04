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

require_once('core/utils/user.php');
use \Demoshot\User;

require_once('core/utils/picture.php');
use \Demoshot\Picture;

require_once('core/utils/mark.php');
use \Demoshot\Mark;


/*
 * MAIN
 */

$user_id = $demoshot_var[PAGE_VAR_USERID];
$picture_id = (int) $_GET[PAGE_ARG_PICTURE_ID];

$mark_attempt = false;


/*
 * This page can be used either to create a new mark or to change an existing one.
 * It checks whether there is already an existing mark in the database.
 */
$existing_mark = Mark\get_mark($demoshot, array($user_id, $picture_id));
if(is_null($existing_mark)) {
	$demoshot->clear_error();
}

// check whether a new mark has been submitted or edited
if(isset($_POST['nm_value']) && isset($_POST['nm_comment'])) {
	$mark_attempt = true;
}

?>

<div class="container">

<?php if($mark_attempt):

$picture_url = "index.php?page_id=" . PAGE_ID_PICTURE;
$picture_url .= "&amp;" . PAGE_ARG_PICTURE_ID . "=$picture_id";


/*
 * Gather all information input by the user.
 */

$mark_value = (int) $_POST['nm_value'];

$mark_comment = $_POST['nm_comment'];

$mark_private = isset($_POST['nm_private']);

$mark_id = array($user_id, $picture_id);


/*
 * Create the new mark or edit the existing one.
 */

if(is_null($existing_mark)) {
	
	if($mark_comment == '') {
		$mark_comment = NULL;
	}
	
	$ok = Mark\create_mark($demoshot, $mark_id, $mark_value, $mark_comment, $mark_private);
	
	if(!$ok) {
		echo "<h1 class='dm_wrong_input'>";
		printf(
			_("Your mark couldn't be created! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
			$demoshot->get_errcode(), $demoshot->get_errmsg()
		);
		echo "</h1>";
	} else {
		
		echo "<h1>";
		echo _("Your mark has been successfully recorded");
		echo "</h1>";
	}
} else { // update the existing mark
	$ok = Mark\edit_mark($demoshot, $mark_id, $mark_value, $mark_comment, $mark_private);
	
	if(!$ok) {
		echo "<h1 class='dm_wrong_input'>";
		printf(
			_("Your mark couldn't be changed! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
			$demoshot->get_errcode(), $demoshot->get_errmsg()
		);
		echo "</h1>";
	} else {
		
		echo "<h1>";
		echo _("Your mark has been successfully changed");
		echo "</h1>";
	}
}

?>

<h3>
	<?php
		$picture_text = _("go back to the picture page");
	?>
	
	<a class="btn" href='<?php echo $picture_url ?>'>
		<i class="icon-arrow-left"></i> <?php echo $picture_text ?>
	</a>
</h3>

<?php else:

$action_url = "index.php?page_id=" . PAGE_ID_MARK;
$action_url .= "&amp;" . PAGE_ARG_PICTURE_ID . "=$picture_id";

?>


<h1><?php echo _("So, what do you think?"); ?></h1>

<form class="form-horizontal" method="post" action="<?php echo $action_url ?>">
	<div class="control-group">
		<label class="control-label" for="value">
			<?php echo _("Value"); ?>
		</label>
		
		<div class="controls">
			<?php
			
				$init_value = 0;
				if(!is_null($existing_mark)) { // if we're editing an existing mark
					$init_value = $existing_mark[GET_MARK_VALUE];
				}
				
				$input = '<input type="number" class="input-mini" id="value" name="nm_value"';
				$input .= " min='0' max='5' value='$init_value' onchange='set(this.value-1)' autofocus />";
				
				echo $input;
				
				echo "<span class='help-inline' style='margin-left: 20px'>";
				
				$path_to_empty_star = 'res/img/empty_star_mark.png';
				$path_to_full_star = 'res/img/full_star_mark.png';
				
				for($it = 0; $it < 5; $it++) {
					if($it < $init_value) {
						$path = $path_to_full_star;
					} else {
						$path = $path_to_empty_star;
					}
					
					echo "<img id='nm_v_$it' src='$path' alt='' ";
					echo "onmouseover='react($it)' onmouseout='react($it, true)' ";
					echo "onclick='set($it)' />";
				}
				
				echo "</span>";
			
			?>
		</div>
	</div>
	
	<div class="control-group">
		<label class="control-label" for="comment">
			<?php echo _("Comment"); ?>
		</label>
		
		<div class="controls">
			<?php
				
				// we need to retrieve the max length of a description
				$conf = $demoshot->get_conf();
				$conf = $conf['database'];
				
				if(!isset($conf['max_comment_length'])) {
					$maxlength = 255;
				} else {
					$maxlength = $conf['max_comment_length'];
				}
				
				$comment_content = '';
				if(!is_null($existing_mark)) { // if we're editing an existing mark
					$comment_content = $existing_mark[GET_MARK_COMMENT];
				}
				
			?>
			
			<textarea class="input-xlarge" id="comment" name="nm_comment" maxlength="<?php echo $maxlength ?>" placeholder="<?php echo _("Why I (dis)like this picture..."); ?>"><?php echo $comment_content ?></textarea>
			</p>
		</div>
	</div>
	
	<div class="control-group">
		<label class="control-label" for="private">
			<?php echo _("Comment is private"); ?>
		</label>
		
		<div class="controls">
			<?php
				/*
				 * The checkbox will be checked if the user settings are
				 * to keep his comments private by default.
				 */
				
				$user = User\get_user($demoshot, $user_id);
				$private = $user[GET_USER_PRIVATE_COMMENTS];
				
				if(!is_null($existing_mark)) { // if we're editing an existing mark
					$private = $existing_mark[GET_MARK_PRIVATE];
				}
			?>
			
			<input type="checkbox" id="private" name="nm_private" <?php if($private) echo 'checked' ?> />
			
			<p class="help-block">
			<?php
				echo _("If you check this box, your comment will only be visible to logged in users.");
			?>
			</p>
		</div>
	</div>

	<div class="form-actions">
		<button type="submit" class="btn btn-primary">
			<?php
				if(is_null($existing_mark)) { // if we're creating new mark
					echo _("Create my mark");
				} else {
					echo _("Change my mark");
				}
			?>
		</button>
	</div>
</form>

<?php endif; ?>

</div>

<script>
	var path_to_empty_star = "res/img/empty_star_mark.png";
	var path_to_full_star = "res/img/full_star_mark.png";
	
	var val = document.getElementById("value").value;
	
	/*
	 * This function is called when the mouse hovers over one star or get out.
	 * If a star is hovered, it fills all stars up to it and adjust the value of the input field.
	 * If the mouse has got out of the stars, reset the default value for the input field.
	 */
	
	function react(id, out) {
		out = typeof(out) == "boolean" ? out : false;
		
		if(out) {
			id = val - 1;
		}
		
		var new_path = null;
		var star_img = null;
		
		for(var it = 0; it < 5; it++) {
			star_img = document.getElementById("nm_v_" + it);
			
			new_path = it <= id ? path_to_full_star : path_to_empty_star;
			
			star_img.src = new_path;
		}
		
		var field = document.getElementById("value");
		
		field.value = id + 1;
	}
	
	/*
	 * This function is called when the mouse clicks one star.
	 * It sets the new default number of the value input field as the number of the star + 1.
	 * If refresh is asked, it updates the look of the five stars to that they fit the new value.
	 *
	 * This function is also called when the value of the first input field changes.
	 */
	
	function set(id, refresh) {
		refresh = typeof(refresh) == "boolean" ? refresh : true;
		
		val = id + 1;
		
		if(refresh) {
			react(id, true);
		}
	}
</script>
