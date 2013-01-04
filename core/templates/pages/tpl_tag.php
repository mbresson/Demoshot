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

require_once('core/utils/tag.php');
use \Demoshot\Tag;

require_once('core/utils/picture.php');
use \Demoshot\Picture;

require_once('core/utils/user.php');
use \Demoshot\User;


/*
 * MAIN
 */

$tag_id = (int) $_GET[PAGE_ARG_TAG_ID];
$tag_title = Tag\get_tag_title($demoshot, $tag_id);
$tag_exists = true; // set to false later if the tag has just been deleted

$user_id = $demoshot_var[PAGE_VAR_USERID];
if($user_id != -1) {
	// for latter use
	$user_tags = Tag\enumerate_tags($demoshot, $user_id, TABLE_CODE_USER, false);
}

$limit = 5; // display a maximum of 5 users and pictures related to the tag

?>

<div class="container">

<?php
	// does the user wants to add this tag to his interests?
	if(isset($_GET['add'])) {
		
		if($user_id == -1) { // is the user not logged in?
			echo "<h1 class='dm_wrong_input'>";
			echo _("You cannot add this tag to your interests unless you're logged in!");
			echo "</h1>";
			
		} else {
			
			// make sure the user and the tag are not linked yet
			if(!in_array($tag_title, $user_tags)) {
				// all OK, we add the tag
				Tag\link_tag($demoshot, $tag_id, $user_id, TABLE_CODE_USER);
			}
			
			$user_tags[] = $tag_title;
		}
	} else if(isset($_GET['remove'])) {
		
		if($user_id == -1) { // is the user not logged in?
			echo "<h1 class='dm_wrong_input'>";
			echo _("You cannot remove this tag from your interests unless you're logged in!");
			echo "</h1>";
			
		} else {
			
			// make sure the user and the tag are linked
			if(in_array($tag_title, $user_tags)) {
				// all OK, we remove the tag
				Tag\unlink_tag($demoshot, $user_id, TABLE_CODE_USER, $tag_id);
			}
			
			$user_tags = Tag\enumerate_tags($demoshot, $user_id, TABLE_CODE_USER, false);
		}
	}
	
	/*
	 * We set this variable because
	 * after the user has been disconnected from the tag,
	 * the tag may have been deleted and thus it cannot be displayed anymore.
	 */
	$tag_exists = !is_null(Tag\get_tag_title($demoshot, $tag_id));
	
	if(!$tag_exists) {
		echo "<p class='alert alert-error'>";
		echo _("This tag is no longer used");
		echo "</p>";
	}
?>

<?php if($tag_exists): ?>

<h1><?php echo $tag_title ?></h1>

<?php endif; if($tag_exists && $demoshot_var[PAGE_VAR_USERID] != -1): ?>

<h3>
<?php

	/*
	 * Display buttons to allow a logged in user to (un)follow the tag.
	 */
	
	if(in_array($tag_title, $user_tags)) {
		// the visitor is already following the tag
		
		$removetag_url = "index.php?page_id=" . PAGE_ID_TAG;
		$removetag_url .= "&amp;" . PAGE_ARG_TAG_ID . "=$tag_id&amp;remove";
		
		echo "<a class='btn btn-danger' href='$removetag_url'>";
		echo '<i class="icon-white icon-ban-circle"></i> ' . _("remove this tag from your interests");
		echo '</a>';
		
	} else {
		// the visitor may be interested in following the tag
		
		$addtag_url = "index.php?page_id=" . PAGE_ID_TAG;
		$addtag_url .= "&amp;" . PAGE_ARG_TAG_ID . "=$tag_id&amp;add";
		
		echo "<a class='btn btn-success' href='$addtag_url'>";
		echo '<i class="icon-white icon-heart"></i> ' . _("add this tag to your interests");
		echo '</a>';
		
	}

?>
</h3>

<?php endif; if($tag_exists): // this part is visible to everybody

	/*
	 * Display the statistics of this tag (how many pictures, how many users).
	 */
	
	$tag_stats = Tag\get_tag_statistics($demoshot, $tag_id);
	list($num_of_pictures, $num_of_users) = $tag_stats;
?>

<h3>
<?php
	if($num_of_pictures == 0) {
		echo _("There is no picture related to this tag.");
	} else if($num_of_pictures == 1) {
		echo _("1 picture is related to this tag.");
	} else {
		printf(
			_("%d pictures are related to this tag."),
			$num_of_pictures
		);
	}
?>
</h3>

<p>
<?php
	$picture_it = new Picture\PictureRetriever(
		$demoshot, $tag_id, RETRIEVE_PICTURES_BY_TAG,
		PICTURE_SORT_BY_RANDOM,
		true, $demoshot_var[PAGE_VAR_USERID] == -1 ? false : NULL, $limit
	);
	
	echo "<ul class='row thumbnails'>";
	
	foreach($picture_it as $picture_id) {
		$picture_path = Picture\get_picture_path($demoshot, $picture_id);
		$tn_path = Picture\get_thumbnail_path($demoshot, $picture_path);
		$picture = Picture\get_picture($demoshot, $picture_id);
		$picture_title = $picture[GET_PICTURE_TITLE];
		
		$picture_url = "index.php?page_id=" . PAGE_ID_PICTURE;
		$picture_url .= "&amp;" . PAGE_ARG_PICTURE_ID . "=$picture_id";
		
		echo "<a class='span1 thumbnail' href='$picture_url' title='";
		echo _("Visit the page of this picture");
		echo "'><img src='$tn_path' alt='$picture_title' /></a>";
	}
	
	echo "</ul>";
?>
</p>

<?php
	if($num_of_pictures > $limit) {
		/*
		 * A link to the search page,
		 * to look for all the pictures related to this tag.
		 */
		$search_pictures_url = "index.php?page_id=" . PAGE_ID_SEARCH_RESULTS .
			"&amp;" . SEARCH_TARGET . "=" . TABLE_CODE_PICTURE .
			"&amp;" . SEARCH_CRIT_TYPE . "=" . RETRIEVE_PICTURES_BY_TAG .
			"&amp;" . SEARCH_CRIT_VALUE . "=$tag_title";
		
		echo "<h4><a href='$search_pictures_url' title=''>";
		echo _("See all the pictures related to this tag.");
		echo "</a></h4>";
	}
?>


<h3>
<?php
	if($num_of_users == 0) {
		echo _("There are no users interested in this tag.");
	} else if($num_of_users == 1) {
		
		if($user_id != -1 && in_array($tag_title, $user_tags)) {
			echo _("You are the only one interested in this tag.");
		} else {
			echo _("1 user is interested in this tag.");
		}
	} else {
		printf(
			_("%d users are interested in this tag."),
			$num_of_users
		);
	}
?>
</h3>

<p>
<?php
	$user_it = new User\UserRetriever(
		$demoshot, $tag_id, RETRIEVE_USERS_BY_TAG,
		USER_SORT_BY_RANDOM, true, $limit
	);
	
	echo "<ul class='row thumbnails'>";
	
	foreach($user_it as $user_id) {
		$avatar_path = User\get_user_avatar($user_id, true);
		$username = User\get_user_name($demoshot, $user_id);
		
		$profile_url = "index.php?page_id=" . PAGE_ID_PROFILE;
		$profile_url .= "&amp;" . PAGE_ARG_USER_ID . "=$user_id";
		
		echo "<li class='span1'><a class='thumbnail' href='$profile_url' title='";
		printf(
			_("Visit %s&#39;s profile"),
			$username
		);
		echo "'><img src='$avatar_path' alt='$username' /></a></li>";
	}
	
	echo "</ul>";
?>
</p>

<?php
	if($num_of_users > $limit) {
		/*
		 * A link to the search page,
		 * to look for all the users related to this tag.
		 */
		$search_users_url = "index.php?page_id=" . PAGE_ID_SEARCH_RESULTS .
			"&amp;" . SEARCH_TARGET . "=" . TABLE_CODE_USER .
			"&amp;" . SEARCH_CRIT_TYPE . "=" . RETRIEVE_PICTURES_BY_TAG .
			"&amp;" . SEARCH_CRIT_VALUE . "=$tag_title";
		
		echo "<h4><a href='$search_users_url' title=''>";
		echo _("See all the users interested in this tag.");
		echo "</a></h4>";
	}

endif; ?>

</div>
