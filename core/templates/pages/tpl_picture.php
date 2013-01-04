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
 * CONSTANTS
 */

$enumi = 0;
define("MARK_DELETION_SUCCESS", $enumi++);
define("MARK_DELETION_FAIL_NOT_LOGGED", $enumi++);
define("MARK_DELETION_FAIL_NO_MARK", $enumi++);
define("MARK_DELETION_FAIL_UNKNOWN", $enumi++);


/*
 * DEPENDENCIES
 */

require_once('core/utils/errors.php');

require_once('core/utils/pages.php');
use \Demoshot\Pages;

require_once('core/utils/misc.php');

require_once('core/utils/picture.php');
use \Demoshot\Picture;

require_once('core/utils/user.php');
use \Demoshot\User;

require_once('core/utils/mark.php');
use \Demoshot\Mark;

require_once('core/utils/tag.php');
use \Demoshot\Tag;


/*
 * MAIN
 */

$user_id = $demoshot_var[PAGE_VAR_USERID];
$picture_id = (int) $_GET[PAGE_ARG_PICTURE_ID];


/*
 * Check whether the user wants to remove his mark.
 */
if(isset($_GET['delete_mark'])) {
	$mark_deletion_code = MARK_DELETION_SUCCESS;
	
	if($demoshot_var[PAGE_VAR_USERID] == -1) {
		$mark_deletion_code = MARK_DELETION_FAIL_NOT_LOGGED;
		
	} else {
		
		$admin_deletion = false;
		
		// check whether the user does own a mark to delete
		$mark_id = array($demoshot_var[PAGE_VAR_USERID], $picture_id);
		
		// or check whether the user is the webmaster and hence can delete any mark
		if($demoshot_var[PAGE_VAR_ADMIN]) {
			
			// if he is, he must provide the ID of the author of the mark to delete
			if(isset($_GET['delete_mark_id']) && (int) $_GET['delete_mark_id'] >= 0) {
				$mark_id[0] = (int) $_GET['delete_mark_id'];
				$admin_deletion = true;
			}
		} // if no mark ID was provided, we assume he intends to delete his own mark
		
		$mark_to_delete = Mark\get_mark($demoshot, $mark_id);
		
		if(is_null($mark_to_delete)) {
			
			$mark_deletion_code = MARK_DELETION_FAIL_NO_MARK;
			
		} else {
			$ok = Mark\delete_mark($demoshot, $mark_id);
			
			if(!$ok) {
				$mark_deletion_code = MARK_DELETION_FAIL_UNKNOWN;
			} else if($admin_deletion) {
				// notify the author of the mark of the deletion of his mark
				
				$author_name = User\get_user_name($demoshot, $mark_id[0]);
				$picture_title = $picture[GET_PICTURE_TITLE];
				
				$subject = sprintf(
					_("Demoshot - Your mark on the picture '%s' has been deleted by the webmaster"),
					$picture_title
				);
				
				$message = sprintf(
					_("Hello %s, we are sorry to tell you that the webmaster has deleted your mark on the picture '%s'. Your mark may have been deleted because of inappropriate content found in your comment."),
					$author_name, $picture_title
				);
				
				User\send_email($demoshot, $mark_id[0], $subject, $message);
			}
		}
	}
}


$picture = Picture\get_picture($demoshot, $picture_id);


/*
 * Get pagination and sorting informations.
 */

// display only 5 marks if no other limit is provided
$limit = 5;
if(isset($_GET[PAGE_ARG_LIMIT]) && (int) $_GET[PAGE_ARG_LIMIT] > 0) {
	$limit = (int) $_GET[PAGE_ARG_LIMIT];
}

// display from the mark no 0 to the mark no 4 if no other value is provided
$starting_from = 0;
if(isset($_GET[PAGE_ARG_OFFSET]) && (int) $_GET[PAGE_ARG_OFFSET] > 0) {
	$starting_from = (int) $_GET[PAGE_ARG_OFFSET];
	
	if($starting_from >= $picture[GET_PICTURE_NUM_OF_MARKS]) {
		$starting_from = $picture[GET_PICTURE_NUM_OF_MARKS] - $limit;
		
		if($starting_from < 0) {
			$starting_from = 0;
		}
	}
}

// sort by date if no other sorting method is provided
$sort_by = MARK_SORT_BY_DATE;
if(
	isset($_GET[PAGE_ARG_SORT_BY]) &&
	is_enum((int) $_GET[PAGE_ARG_SORT_BY], MARK_SORT_BY_VALUE, MARK_SORT_BY_RANDOM)
) {
	$sort_by = (int) $_GET[PAGE_ARG_SORT_BY];
}

// sort by descending order if no argument is provided through the URL
$sort_asc = isset($_GET[PAGE_ARG_SORT_ASC]);


/*
 * Retrieve all the marks and store them in an array for latter use.
 */
$marks = new Mark\MarkRetriever(
	$demoshot, $picture_id, TABLE_CODE_PICTURE,
	$sort_by, $sort_asc, NULL, NULL, $limit, $starting_from
);

?>

<div class="container">

<?php

// does the user want to delete his picture?
if(isset($_GET['delete'])) {
	
	// make sure the picture belongs to the visitor or the visitor is the admin
	$right_to_delete = ($user_id == $picture[GET_PICTURE_AUTHOR] || $demoshot_var[PAGE_VAR_ADMIN]);
	
	
	if($right_to_delete) {
		if(!Picture\delete_picture($demoshot, $picture_id)) {
			
			echo "<h1 class='dm_wrong_input'>";
			printf(
				_("The picture couldn't be deleted! Please report it to the webmaster (errcode: %d, errmsg: %s)"),
				$demoshot->get_errcode(), $demoshot->get_errmsg()
			);
			echo "</h1>";
			
			$deleted = false;
			
		} else {
			
			echo "<h1>";
			echo _("The picture was successfully deleted");
			echo "</h1>";
			
			$deleted = true;
			
			// if the admin has deleted the picture,
			if($user_id != $picture[GET_PICTURE_AUTHOR]) {
				
				// notify its owner by email
				
				$author_name = User\get_user_name($demoshot, $picture[GET_PICTURE_AUTHOR]);
				$picture_title = $picture[GET_PICTURE_TITLE];
				
				$subject = sprintf(
					_("Demoshot - Your picture '%s' has been deleted by the webmaster"),
					$picture_title
				);
				
				$message = sprintf(
					_("Hello %s, we are sorry to tell you that the webmaster has deleted your picture '%s'. Your picture may have been deleted because of inappropriate content."),
					$author_name, $picture_title
				);
				
				User\send_email($demoshot, $picture[GET_PICTURE_AUTHOR], $subject, $message);
			}
		}
		
	} else {
		echo "<h1 class='dm_wrong_input'>";
		echo _("You cannot delete this picture for it is not yours!");
		echo "</h1>";
	}
}

if($picture[GET_PICTURE_PRIVATE] && $user_id == -1): 

/*
 * The user has no right to see that picture,
 * because he is not logged in and the picture is private.
 */

?>

<h1>
<?php
	echo _("You cannot see this picture unless you're logged in :(");
?>
</h1>

<p>
<?php
	
	$login_url = "index.php" . "?page_id=" . PAGE_ID_LOGIN;
	
	echo "<p><a class='btn btn-large' href='$login_url'>";
	echo '<i class="icon-arrow-down"></i> ' . _("Login");
	echo '</a></p>';

?>
</p>

<?php elseif(!isset($deleted)): ?>

<?php
	if(isset($mark_deletion_code)) {
		switch($mark_deletion_code) {
			case MARK_DELETION_SUCCESS:
				echo "<h1>";
				echo _("The mark was successfully deleted");
				echo "</h1>";
				break;
			
			case MARK_DELETION_FAIL_NOT_LOGGED:
				echo "<h1 class='dm_wrong_input'>";
				echo _("You cannot delete your mark unless you're logged in!");
				echo "</h1>";
				break;
			
			case MARK_DELETION_FAIL_NO_MARK:
				echo "<h1 class='dm_wrong_input'>";
				echo _("You have no mark to delete!");
				echo "</h1>";
				break;
			
			case MARK_DELETION_FAIL_UNKNOWN:
				echo "<h1 class='dm_wrong_input'>";
				printf(
					_("The mark couldn't be deleted! (errcode: %d, errmsg: %s)"),
					$demoshot->get_errcode(), $demoshot->get_errmsg()
				);
				echo "</h1>";
				break;
		}
	}
?>

<div class="row">
	<div class="span9">
		
		<h1><?php echo $picture[GET_PICTURE_TITLE]; ?></h1>
		
		<p>
		<?php
			// display the average mark with stars
			$picture_av_mark = $picture[GET_PICTURE_AVERAGE_MARK];
			
			$path_to_empty_star = 'res/img/empty_star.png';
			$path_to_full_star = 'res/img/full_star.png';
			
			$empty_stars = 5 - $picture_av_mark;
			
			while($picture_av_mark-- > 0) {
				echo "<img src='$path_to_full_star' alt='' />";
			}
			
			while($empty_stars-- > 0) {
				echo "<img src='$path_to_empty_star' alt='' />";
			}
		?>
		</p>
		
		<h3>
			<?php
				if($picture[GET_PICTURE_AUTHOR] == $user_id) {
					echo _("This is your picture");
				} else {
					$author_name = User\get_user_name($demoshot, $picture[GET_PICTURE_AUTHOR]);
					
					$author_url = "index.php?page_id=" . PAGE_ID_PROFILE;
					$author_url .= "&amp;" . PAGE_ARG_USER_ID . "=" . $picture[GET_PICTURE_AUTHOR];
					
					$author = "<a href='$author_url' title='";
					$author .= sprintf(_("Visit %s&#39;s profile"), $author_name);
					$author .= "'>$author_name</a>";
					
					printf(
						_("Uploaded by %s"),
						$author
					);
				}
			?>
		</h3>
		
		<p>
		<?php
			$path = Picture\get_picture_path($demoshot, $picture_id);
			
			echo " &nbsp;<a class='btn btn-inverse' href='$path'><strong><i class='icon-white icon-zoom-in'></i> ";
			echo _("full size") . "</strong></a>";
		?>
		
		
		<?php
			if($picture[GET_PICTURE_AUTHOR] == $user_id) {
				/*
				 * The owner of the picture can delete it or edit it.
				 * We display buttons to allow him to do so.
				 */
				
				$delete_url = "index.php?page_id=" . PAGE_ID_PICTURE;
				$delete_url .= "&amp;" . PAGE_ARG_PICTURE_ID . "=$picture_id&amp;delete";
				
				echo " &nbsp;<a class='btn btn-danger' href='$delete_url'><i class='icon-remove icon-white'></i> ";
				echo _("delete");
				echo "</a>";
				
				
				$edit_url = "index.php?page_id=" . PAGE_ID_EDIT_PICTURE;
				$edit_url .= "&amp;" . PAGE_ARG_PICTURE_ID . "=$picture_id";
				
				echo " &nbsp;<a class='btn' href='$edit_url'><i class='icon-wrench'></i> ";
				echo _("edit");
				echo "</a>";
			} else if($user_id >= 0) {
				/*
				 * The visitor can mark the picture or edit his mark.
				 */
				
				// has the user already marked it?
				$user_mark = Mark\get_mark($demoshot, array($user_id, $picture_id));
				
				if(is_null($user_mark)) {
					$demoshot->clear_error(); // get_mark set ERROR_NOT_FOUND in $demoshot if no mark was found
					
					$new_mark_url = "index.php?page_id=" . PAGE_ID_MARK;
					$new_mark_url .= "&amp;" . PAGE_ARG_PICTURE_ID . "=$picture_id";
					
					echo " &nbsp;<a class='btn' href='$new_mark_url'><i class='icon-star-empty'></i> ";
					echo _("mark it");
					echo "</a>";
				} else {
					$edit_mark_url = "index.php?page_id=" . PAGE_ID_MARK;
					$edit_mark_url .= "&amp;" . PAGE_ARG_PICTURE_ID . "=$picture_id";
					
					echo " &nbsp;<a class='btn' href='$edit_mark_url'><i class='icon-star'></i> ";
					echo _("change your mark");
					echo "</a>";
					
					$delete_mark_url = "index.php?page_id=" . PAGE_ID_PICTURE;
					$delete_mark_url .= "&amp;" . PAGE_ARG_PICTURE_ID . "=$picture_id&amp;delete_mark";
					
					echo " &nbsp;<a class='btn btn-danger' href='$delete_mark_url'><i class='icon-remove icon-white'></i> ";
					echo _("delete your mark");
					echo "</a>";
					
				}
				
				// if the user is an administrator, he can delete the picture
				if($demoshot_var[PAGE_VAR_ADMIN]) {
					$delete_url = "index.php?page_id=" . PAGE_ID_PICTURE;
					$delete_url .= "&amp;" . PAGE_ARG_PICTURE_ID . "=$picture_id&amp;delete";
					
					$delete_title = _("this action cannot be undone!");
					
					echo "</p><p> &nbsp;<a class='btn btn-warning' href='$delete_url' title='$delete_title'><i class='icon-trash icon-white'></i> ";
					echo "<strong>" . _("remove this picture") . "</strong>";
					echo "</a>";
				}
			}
		?>
		</p>
		
		<?php
			/*
			 * Display the license of the picture, if it is provided.
			 */
		
			$license = $picture[GET_PICTURE_LICENSE];
		
			if(is_string($license) && $license !== '') {
				echo "<h4>" . _("License") . "</h4>";
				echo "<p>$license</p>";
			}
			
			
			/*
			 * If the picture is private, display an info block to warn the visitor.
			 */
			if($picture[GET_PICTURE_PRIVATE]) {
				echo "<h5 class='alert alert-info'>";
				echo _("This picture is <em>private</em>, it means only logged in users can see it.");
				echo "</h5>";
			}
		?>
		
	</div>
	
	<div class="span3">
		<?php
			$tn_path = Picture\get_thumbnail_path($demoshot, $path);
		?>
		
		<div><img class='thumbnail' src='<?php echo $tn_path ?>' alt='' /></div>
		
		
		<h4>
		<?php
			if(is_string($picture[GET_PICTURE_DESCRIPTION])) {
				echo "<blockquote>" . $picture[GET_PICTURE_DESCRIPTION] . "</blockquote>";
			} else {
				echo _("This picture has no description.");
			}
		?>
		</h4>
		
		
		<?php
			/*
			 * Display the tags of the picture.
			 */
		
			$tag_it = new Tag\TagRetriever($demoshot, $picture_id, TABLE_CODE_PICTURE);
			
			if($tag_it->length() > 0) {
				echo "<h5 class='dm_picture_tags'>";
				
				foreach($tag_it as $it => $tag_id) {
					$tag_url = "index.php?page_id=" . PAGE_ID_TAG;
					$tag_url .= "&amp;" . PAGE_ARG_TAG_ID . "=$tag_id";
					
					$tag_title = Tag\get_tag_title($demoshot, $tag_id);
					
					if($it > 0) {
						echo ', ';
					}
					
					echo "<a href='$tag_url' title='$tag_title'>$tag_title</a>";
				}
				
				echo "</h5>";
			}
		?>
	</div>
</div>

<?php
	$num_of_marks = $picture[GET_PICTURE_NUM_OF_MARKS];

	if($num_of_marks == 0) {
		echo "<h4>";
		echo _("This picture has received no mark yet.");
		echo "</h4>";
		
	} else {
		echo "<h4 id='list_of_marks'>";
		
		if($num_of_marks == 1) {
			
			if($user_id != -1) {
				// display a slightly different message if the visitor is the author of the only mark
				$user_mark = Mark\get_mark($demoshot, array($user_id, $picture_id));
				
				if(is_null($user_mark)) {
					$demoshot->clear_error();
					echo _("This picture has been reviewed by one user.");
				} else {
					echo _("You are the only one who has marked this picture.");
				}
			} else {
				echo _("This picture has been reviewed by one user.");
			}
		} else {
			printf(
				_("This picture has been reviewed by %d users."),
				$num_of_marks
			);
		}
		
		echo "</h4>";
		
		if($num_of_marks > 1) {
			/*
			 * Display buttons to change the sorting method and order.
			 */
			
			echo "<div style='margin-bottom: 15px'>" . _("Sort method:");
			
			// the base url to keep the limit and offset arguments
			$sort_by_base_url = "index.php?page_id=" . PAGE_ID_PICTURE .
				"&amp;" . PAGE_ARG_PICTURE_ID . "=$picture_id" .
				"&amp;" . PAGE_ARG_LIMIT . "=$limit" .
				"&amp;" . PAGE_ARG_OFFSET . "=$starting_from" .
				"&amp;" . PAGE_ARG_SORT_BY . "=";
			
			$sort_by_value_url = $sort_by_base_url . MARK_SORT_BY_VALUE;
			$sort_by_value_text = _("sort by value");
			
			$sort_by_date_url = $sort_by_base_url . MARK_SORT_BY_DATE;
			$sort_by_date_text = _("sort by date");
			
			$sort_by_random_url = $sort_by_base_url . MARK_SORT_BY_RANDOM;
			$sort_by_random_text = _("random order");
			
			
			/*
			 * The head of the output,
			 * completed with "btn-inverse'" if it is the current sort method
			 * and an arrow icon if the sort order can be inversed.
			 */
			$sort_by_head = "&nbsp;&nbsp;<a class='btn btn-small";
			
			
			/*
			 * Sort by value.
			 */
			$output = $sort_by_head;
			if($sort_by == MARK_SORT_BY_VALUE) {
				$output .= " btn-inverse' href='$sort_by_value_url";
				
				if(!$sort_asc) { // inverse the sort order
					$output .= '&amp;' . PAGE_ARG_SORT_ASC;
				}
				
				$output .= "'><i class='icon-white ";
				
				if($sort_asc) {
					$output .= "icon-arrow-up'></i> ";
				} else {
					$output .= "icon-arrow-down'></i> ";
				}
				
				$output .= "$sort_by_value_text</a>&nbsp;";
			} else {
				$output .= "' href='$sort_by_value_url'>$sort_by_value_text</a>&nbsp;";
			}
			
			echo $output;
			
			
			/*
			 * Sort by date.
			 */
			$output = $sort_by_head;
			if($sort_by == MARK_SORT_BY_DATE) {
				$output .= " btn-inverse' href='$sort_by_date_url";
				
				if(!$sort_asc) { // inverse the sort order
					$output .= '&amp;' . PAGE_ARG_SORT_ASC;
				}
				
				$output .= "'><i class='icon-white ";
				
				if($sort_asc) {
					$output .= "icon-arrow-up'></i> ";
				} else {
					$output .= "icon-arrow-down'></i> ";
				}
				
				$output .= "$sort_by_date_text</a>&nbsp;";
			} else {
				$output .= "' href='$sort_by_date_url'>$sort_by_date_text</a>&nbsp;";
			}
			
			echo $output;
			
			
			/*
			 * Random order.
			 */
			$output = $sort_by_head;
			if($sort_by == MARK_SORT_BY_RANDOM) {
				$output .= " btn-inverse' href='$sort_by_random_url'>" .
					"$sort_by_random_text</a>&nbsp;";
			} else {
				$output .= "' href='$sort_by_random_url'>$sort_by_random_text</a>&nbsp;";
			}
			
			echo $output;
			
			echo "</div>";
			
		}
		
		/*
		 * Display all the marks and comments
		 * sorted by date (the latest come first).
		 */
		
		/*
		 * If the marks come in a random order,
		 * warn the user that he may see the same mark on several pages.
		 */
		if($sort_by == MARK_SORT_BY_RANDOM) {
			echo "<p class='alert alert-info'>";
			echo _("Because the marks come in random order, you may see the same mark on several pages.");
			echo "</p>";
		}
		
		foreach($marks as $mark_id) {
			$mark = Mark\get_mark($demoshot, $mark_id);
			
			$mark_author_id = $mark_id[GET_MARK_ID_AUTHOR_ID];
			$mark_author = User\get_user_name($demoshot, $mark_author_id);
			
			echo "<div class='row'>";
			
			/*
			 * Display:
			 * - In the left corner, the author of the mark, then the date.
			 * - In the right corner, the value of the mark.
			 * - On the next line, the comment of the mark if any.
			 */
			
			// the author of the mark
			$mark_author_url = "index.php?page_id=" . PAGE_ID_PROFILE;
			$mark_author_url .= "&amp;" . PAGE_ARG_USER_ID . "=$mark_author_id";
			
			echo "<h6 class='span5'><a href='$mark_author_url' title='";
			printf(_("Visit %s&#39;s profile"), $mark_author);
			echo "'>$mark_author</a></h6>";
			
			// the date of the mark
			list($mark_date, $mark_date_hour) = preg_split("/[\s]+/", $mark[GET_MARK_DATE]);
			
			if($demoshot->get_lang() == USER_LANG_FR) {
				$new_date = reformat_date($mark_date, DATE_FORMAT_FR);
				
				if(is_string($new_date)) {
					$mark_date = $new_date;
				}
			}
			
			echo "<h5 class='span5'>$mark_date - $mark_date_hour</h5>";
			
			// the value of the mark
			echo "<div class='span2'>";
			
			$path_to_empty_star_mark = 'res/img/empty_star_mark.png';
			$path_to_full_star_mark = 'res/img/full_star_mark.png';
			
			$mark_value = $mark[GET_MARK_VALUE];
			
			$empty_stars = 5 - $mark_value;
			
			while($mark_value-- > 0) {
				echo "<img src='$path_to_full_star_mark' alt='' />";
			}
			
			while($empty_stars-- > 0) {
				echo "<img src='$path_to_empty_star_mark' alt='' />";
			}
			
			echo "</div>";
			
			// the comment, or a warning if the comment is private and the visitor not logged in
			echo "<div class='span12'><blockquote>";
			
			if(is_null($mark[GET_MARK_COMMENT])) {
				echo _("No comment.");
			} else {
				if($demoshot_var[PAGE_VAR_USERID] == -1 && $mark[GET_MARK_PRIVATE]) {
					echo "<strong class='alert'>";
					echo _("This comment is private. Because you are not logged in, you cannot see it.");
					echo "</strong>";
				} else {
					echo "<em>";
					echo $mark[GET_MARK_COMMENT];
					echo "</em>";
				}
			}
			
			echo "</blockquote></div>";
			
			
			// if the user is an administrator, he can delete the mark
			if($demoshot_var[PAGE_VAR_ADMIN]) {
				
				$delete_mark_url = "index.php?page_id=" . PAGE_ID_PICTURE .
					"&amp;" . PAGE_ARG_PICTURE_ID . "=$picture_id&amp;delete_mark" .
					"&amp;delete_mark_id=$mark_author_id";
				
				$delete_mark_title = _("this action cannot be undone!");
				
				echo "<div class='span12'><a class='btn btn-warning btn-small' href='$delete_mark_url' title='$delete_mark_title'><i class='icon-trash icon-white'></i> ";
				echo _("delete this mark");
				echo "</a></div>";
			}
			
			// close the outer div
			echo "</div>\n\n";
		}
		
		
		/*
		 * Display:
		 * - buttons to change the number of results per page
		 * - pagination (links to other pages)
		 * 
		 * pagination.php takes care of this,
		 * we only have the following information:
		 */
		
		$base_url = "index.php?page_id=" . PAGE_ID_PICTURE .
			"&amp;" . PAGE_ARG_PICTURE_ID . "=$picture_id" .
			"&amp;" . PAGE_ARG_SORT_BY . "=$sort_by" .
			($sort_asc ? "&amp;" . PAGE_ARG_SORT_ASC : "");
		
		$num_of_results = $num_of_marks;
		$lowest_limit = 5;
		$limit_increase = 5;
		$anchor = "#list_of_marks";
		$results_per_page_text = _("Marks per page:");
		
		require_once('core/templates/skel/pagination.php');
	}

endif; ?>

</div>
