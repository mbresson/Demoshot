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

require_once('core/utils/picture.php');
use \Demoshot\Picture;

require_once('core/utils/tag.php');
use \Demoshot\Tag;

require_once('core/utils/mark.php');
use \Demoshot\Mark;


/*
 * MAIN
 */

$user_id = $demoshot_var[PAGE_VAR_USERID]; // the ID of the visiter, if logged in
$target_id = (int) $_GET[PAGE_ARG_USER_ID]; // the ID of the user whose profile is visited


if(isset($_GET['follow']) && $target_id != $user_id && $user_id >= 0) {
	User\follow($demoshot, $user_id, $target_id);
}

if(isset($_GET['nofollow']) && $target_id != $user_id && $user_id >= 0) {
	User\stop_following($demoshot, $user_id, $target_id);
}

$target = User\get_user($demoshot, $target_id);
$target_followers = User\get_user_followers($demoshot, $target_id);
$target_followed = User\get_user_followed($demoshot, $target_id);

?>

<div class="container">

<?php

/*
 * If the visitor is the webmaster and he decided to ban the user,
 * delete the user's account.
 */
if(
	isset($_GET['ban']) && $demoshot_var[PAGE_VAR_ADMIN] &&
	$target_id != $user_id // the webmaster cannot ban himself
) {
	
	// notify the user by email that his account has been deleted
	
	$user_name = $target[GET_USER_NAME];
	
	$subject = _("Demoshot - You have been banned by the webmaster");
	
	$message = sprintf(
		_("Hello %s, we are sorry to tell you that the webmaster has deleted your account. You may have been banned because of inappropriate activity on the website."),
		$user_name
	);
	
	User\send_email($demoshot, $target_id, $subject, $message);
	
	User\delete_account($demoshot, $target_id);
	
	echo "<h1>";
	echo _("The user was successfully banned");
	echo "</h1>";
	
	$homepage_url = "index.php?page_id=" . PAGE_ID_HOME;
	
	echo "<div><a href='$homepage_url' title='' class='btn btn-large'><i class='icon-arrow-left'></i> ";
	echo _("Go back to the home page");
	echo "</a></div>";
	
	$user_banned = true;
}

if(!isset($user_banned)): ?>

<div class="row">
	
	<div class="span10">
		
		<h1>
		<?php
		
			/*
			 * Display the username and some buttons depending on the status of the visitor:
			 * - The visitor is a guest: only display a button to go to the user's album.
			 * - The visitor is logged in: display a button to go to the album and to (un)follow the user.
			 * - The user is the visitor: display buttons to manage his album and his account.
			 */
		
			echo $target[GET_USER_NAME];
			
			$album_url = "index.php?page_id=" . PAGE_ID_USER_ALBUM;
			$album_url .= "&amp;" . PAGE_ARG_USER_ID . "=$target_id";
			
			echo " &nbsp;<a class='btn' href='$album_url'>";
			echo "<i class='";
			if($user_id == $target_id) {
				echo "icon-pencil'></i> ";
				echo _("manage your album");
			} else {
				echo "icon-arrow-right'></i> ";
				echo _("visit his album");
			}
			
			echo "</a>";
			
			if($user_id >= 0 && $user_id != $target_id) {
				if(in_array($user_id, $target_followers)) {
					// the visitor is following the user
					$follow_url = "index.php?page_id=" . PAGE_ID_PROFILE;
					$follow_url .= "&amp;" . PAGE_ARG_USER_ID . "=$target_id";
					$follow_url .= "&amp;nofollow";
					
					echo " &nbsp;<a class='btn btn-danger' href='$follow_url'>";
					echo '<i class="icon-white icon-ban-circle"></i> ' . _("stop following");
					echo '</a>';
					
				} else {
					// display a 'follow' button for the visitor
					$follow_url = "index.php?page_id=" . PAGE_ID_PROFILE;
					$follow_url .= "&amp;" . PAGE_ARG_USER_ID . "=$target_id";
					$follow_url .= "&amp;follow";
					
					echo " &nbsp;<a class='btn btn-success' href='$follow_url'>";
					echo '<i class="icon-white icon-heart"></i> ' . _("follow");
					echo '</a>';
				}
			} else if($user_id == $target_id) {
				// display a button to edit the profile
				$settings_url = "index.php?page_id=" . PAGE_ID_USER_SETTINGS;
				$settings_url .= "&amp;" . PAGE_ARG_USER_ID . "=$target_id";
				
				echo " &nbsp;<a class='btn' href='$settings_url'>";
				echo '<i class="icon-wrench"></i> ' . _("edit your profile");
				echo '</a>';
			}
		?>
		</h1>
		
		<h4>
		<?php
			if(is_string($target[GET_USER_DESCRIPTION])) {
				echo "<blockquote>" . $target[GET_USER_DESCRIPTION] . "</blockquote>";
			} else {
				if($user_id == $target_id) {
					echo _("You haven't provided a description yet.");
				} else {
					echo _("This user hasn't provided a description yet.");
				}
			}
		
		?>
		</h4>
		
		<?php
			// if the user is an administrator, he can ban the user
			if($demoshot_var[PAGE_VAR_ADMIN] && $user_id != $target_id) {
				
				$ban_url = "index.php?page_id=" . PAGE_ID_PROFILE .
					"&amp;" . PAGE_ARG_USER_ID . "=$target_id&amp;ban";
				
				$ban_title = _("this action cannot be undone!");
				
				echo "<div><a class='btn btn-warning' href='$ban_url' title='$ban_title'><i class='icon-trash icon-white'></i> ";
				echo "<strong>" . _("ban this user") . "</strong>";
				echo "</a></div>";
			}
		?>
	</div>
	
	<div class="span2">
		<img class="thumbnail" src='<?php echo User\get_user_avatar($target_id, true) ?>' alt='' />
	</div>
	
</div>


<h3>
<?php echo _("Interests") ?>
</h3>

<p>
<?php
	$tag_it = new Tag\TagRetriever($demoshot, $target_id, TABLE_CODE_USER);

	$num_of_tags = $tag_it->length();
	
	foreach($tag_it as $it => $tag_id) {
		$tag_url = "index.php?page_id=" . PAGE_ID_TAG;
		$tag_url .= "&amp;" . PAGE_ARG_TAG_ID . "=$tag_id";
		
		$tag_title = Tag\get_tag_title($demoshot, $tag_id);
		
		if($it > 0) {
			echo ', ';
		}
		
		echo "<a href='$tag_url' title='$tag_title'><strong>$tag_title</strong></a>";
	}
	
	if($num_of_tags == 0) {
		echo _("No interest specified.");
	} else {
		echo ".";
	}
?>
</p>


<h3>
<?php echo _("Activity") ?>
</h3>

<div class="row">
	<div class="span6">
		<h4>
		<?php echo _("Latest published pictures") ?>
		</h4>
		
		<?php
		
			// if the user is logged in, he can see private pictures.
			$no_private_pictures = $demoshot_var[PAGE_VAR_USERID] === -1;
			
			// get the 5 latest pictures uploaded by this user
			$picture_it = new Picture\PictureRetriever(
				$demoshot, $target_id, RETRIEVE_PICTURES_BY_AUTHOR,
				PICTURE_SORT_BY_DATE, false, // false = descreasing order
				$no_private_pictures ? false : NULL, 6, -1,
				NULL, NULL
			);
			
			// display the latest pictures
			$not_empty = $picture_it->length() > 0;
			
			if($not_empty) {
				echo "<ul class='row thumbnails'>";
			} else {
				echo "<p>" . _("Nothing!") . "</p>";
			}
			
			$it = 0;
			
			foreach($picture_it as $picture_id) {
				$picture_path = Picture\get_picture_path($demoshot, $picture_id);
				$tn_path = Picture\get_thumbnail_path($demoshot, $picture_path);
				$picture_title = Picture\get_picture_title($demoshot, $picture_id);
				
				$picture_url = "index.php?page_id=" . PAGE_ID_PICTURE;
				$picture_url .= "&amp;" . PAGE_ARG_PICTURE_ID . "=$picture_id";
				
				echo "<li class='span1'><a class='thumbnail' href='$picture_url' title='" .
					"$picture_title'><img src='$tn_path' alt='$picture_title' /></a></li>";
				
				$it++;
				if($it >= 5) {
					break;
				}
			}
			
			if($not_empty)  {
				echo "</ul>";
			}
			
			if($picture_it->length() > 5) {
				$uploaded_pictures_url = "index.php?page_id=" . PAGE_ID_USER_ALBUM .
					"&amp;" . PAGE_ARG_USER_ID . "=$target_id";
				
				if($user_id == $target_id) {
					$uploaded_pictures_text = _("See all the pictures you uploaded.");
				} else {
					$uploaded_pictures_text = sprintf(_("See all the pictures uploaded by %s."), $target[GET_USER_NAME]);
				}
				
				?>
				<h5>
					<a href='<?php echo $uploaded_pictures_url ?>' title=''>
					<?php echo $uploaded_pictures_text ?>
					</a>
				</h5>
				<?php
			}
		?>
	</div>
	
	<div class="span6">
		<h4>
		<?php echo _("Latest marked pictures") ?>
		</h4>
		
		<?php
		
			// if the user is logged in, he can see private pictures.
			$no_private_comments = $demoshot_var[PAGE_VAR_USERID] === -1;
			
			/*
			 * Get the 6 latest pictures marked by the user.
			 * We will only display the 5 first.
			 * If there are more than 5 results, we will display a link
			 * to allow the user to see all of them.
			 */
			$picture_it = new Picture\PictureRetriever(
				$demoshot, $target_id, RETRIEVE_PICTURES_BY_MARK_AUTHOR,
				PICTURE_SORT_BY_MARK_DATE, false, // false = descreasing order
				$no_private_pictures ? false : NULL,
				6, -1, // get the 6 first pictures, only display the 5 first of them
				NULL, NULL
			);
			
			// display the latest marks
			$not_empty = $picture_it->length() > 0;
			
			if($not_empty) {
				echo "<ul class='row thumbnails'>";
			} else {
				echo "<p>" . _("Nothing!") . "</p>";
			}
			
			$it = 0;
			
			foreach($picture_it as $picture_id) {
				$picture_path = Picture\get_picture_path($demoshot, $picture_id);
				$tn_path = Picture\get_thumbnail_path($demoshot, $picture_path);
				$picture_title = Picture\get_picture_title($demoshot, $picture_id);
				
				$picture_url = "index.php?page_id=" . PAGE_ID_PICTURE;
				$picture_url .= "&amp;" . PAGE_ARG_PICTURE_ID . "=$picture_id";
				
				echo "<li class='span1'><a class='thumbnail' href='$picture_url' title='" .
					"$picture_title'><img src='$tn_path' alt='$picture_title' /></a></li>";
				
				$it++;
				if($it >= 5) {
					break;
				}
			}
			
			if($not_empty)  {
				echo "</ul>";
			}
			
			if($picture_it->length() > 5) {
				$marked_pictures_url = "index.php?page_id=" . PAGE_ID_SEARCH_RESULTS .
					"&amp;" . SEARCH_TARGET . "=" . TABLE_CODE_PICTURE .
					"&amp;" . SEARCH_CRIT_TYPE . "=" . RETRIEVE_PICTURES_BY_MARK_AUTHOR .
					"&amp;" . SEARCH_CRIT_VALUE . "=$target_id" .
					"&amp;" . PAGE_ARG_SORT_BY . "=" . PICTURE_SORT_BY_MARK_DATE;
				
				if($user_id == $target_id) {
					$marked_pictures_text = _("See all the pictures you marked.");
				} else {
					$marked_pictures_text = sprintf(_("See all the pictures marked by %s."), $target[GET_USER_NAME]);
				}
				
				?>
				<h5>
					<a href='<?php echo $marked_pictures_url ?>' title=''>
					<?php echo $marked_pictures_text ?>
					</a>
				</h5>
				<?php
			}
		?>
	</div>
</div>

<h3>
<?php echo _("Relationships") ?>
</h3>

<div class="row">
	<div class="span6">
		
		<h4><?php echo _("Following") ?></h4>
	
		<?php
			$followed_users = User\get_user_followed($demoshot, $target_id);
			
			if(count($followed_users) == 0) {
				echo "<p>" . _("Nobody!") . "</p>";
			} else {
				echo "<ul class='row thumbnails'>";
				
				$num_of_followed = count($followed_users);
				
				for($it = 0; $it < $num_of_followed && $it < 5; $it++) {
					$followed_id = $followed_users[$it];
					
					$followed_name = User\get_user_name($demoshot, $followed_id);
					$followed_avatar = User\get_user_avatar($followed_id, true);
					
					$followed_url = "index.php?page_id=" . PAGE_ID_PROFILE;
					$followed_url .= "&amp;" . PAGE_ARG_USER_ID . "=$followed_id";
					
					echo "<li class='span1'><a class='thumbnail' href='$followed_url' " .
						"title='$followed_name'><img src='$followed_avatar' alt='$followed_name' /></a></li>";
				}
				
				echo "</ul>";
				
				if($num_of_followed > 5) {
					$followed_users_url = "index.php?page_id=" . PAGE_ID_SEARCH_RESULTS .
						"&amp;" . SEARCH_TARGET . "=" . TABLE_CODE_USER .
						"&amp;" . SEARCH_CRIT_TYPE . "=" . RETRIEVE_USERS_BY_FOLLOWER .
						"&amp;" . SEARCH_CRIT_VALUE . "=$target_id";
					
					if($user_id == $target_id) {
						$followed_users_text = _("See all the people you follow.");
					} else {
						$followed_users_text = sprintf(_("See all the people followed by %s."), $target[GET_USER_NAME]);
					}
					
					?>
					<h5>
						<a href='<?php echo $followed_users_url ?>' title=''>
						<?php echo $followed_users_text ?>
						</a>
					</h5>
					<?php
				}
			}
		?>
	</div>
	
	<div class="span6">
		
		<h4><?php echo _("Followed by") ?></h4>
		
		<?php
			$followers = User\get_user_followers($demoshot, $target_id);
			
			if(count($followers) == 0) {
				echo "<p>" . _("Nobody!") . "</p>";
			} else {
				echo "<ul class='row thumbnails'>";
				
				$num_of_followers = count($followers);
				
				for($it = 0; $it < $num_of_followers && $it < 5; $it++) {
					$follower_id = $followers[$it];
					
					$follower_name = User\get_user_name($demoshot, $follower_id);
					$follower_avatar = User\get_user_avatar($follower_id, true);
					
					$follower_url = "index.php?page_id=" . PAGE_ID_PROFILE;
					$follower_url .= "&amp;" . PAGE_ARG_USER_ID . "=$follower_id";
					
					echo "<li class='span1'><a class='thumbnail' href='$follower_url' " .
						"title='$follower_name'><img src='$follower_avatar' alt='$follower_name' /></a></li>";
				}
				
				echo "</ul>";
				
				if($num_of_followers > 5) {
					$followers_url = "index.php?page_id=" . PAGE_ID_SEARCH_RESULTS .
						"&amp;" . SEARCH_TARGET . "=" . TABLE_CODE_USER .
						"&amp;" . SEARCH_CRIT_TYPE . "=" . RETRIEVE_USERS_BY_FOLLOWED .
						"&amp;" . SEARCH_CRIT_VALUE . "=$target_id";
					
					if($user_id == $target_id) {
						$followers_text = _("See all the people following you.");
					} else {
						$followers_text = sprintf(_("See all the people following %s."), $target[GET_USER_NAME]);
					}
					
					?>
					<h5>
						<a href='<?php echo $followers_url ?>' title=''>
						<?php echo $followers_text ?>
						</a>
					</h5>
					<?php
				}
			}
		?>
	</div>
</div>


<?php endif; // the if is near the account deletion code ?>

</div>
