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

require_once('core/utils/database.php');

require_once('core/utils/misc.php');

require_once('core/utils/picture.php');
use \Demoshot\Picture;

require_once('core/utils/user.php');
use \Demoshot\User;


/*
 * MAIN
 */

$username = $demoshot_var[PAGE_VAR_USERNAME];

// if the user is logged in, he can see private pictures
$no_private_pictures = $demoshot_var[PAGE_VAR_USERID] === -1;

// get the best rated pictures
$picture_it = new Picture\PictureRetriever(
	$demoshot, NULL, RETRIEVE_ALL_PICTURES,
	PICTURE_SORT_BY_AV_MARK, false, // false = descreasing order
	$no_private_pictures ? false : NULL, 12 // get only the 12 first pictures
);

// store their information in an array
$pictures_array = array();

foreach($picture_it as $picture_id) {
	$pictures_array[] = Picture\get_picture($demoshot, $picture_id);
}

?>

<div class="container">

<h1><?php printf(_("Welcome %s!"), $username); ?></h1>

<?php if($no_private_pictures):

	/*
	 * If the visitor is not logged in,
	 * display a message to tell him a few words about what is Demoshot.
	 */
	
	$about_url = "index.php?page_id=" . PAGE_ID_ABOUT;
	$about_tip = _("Visit the presentation page");
	
	echo "<h4><a href='$about_url' title='$about_tip'>";
	echo _("What is this website?");
	echo "</a></h4>";

endif; ?>

	<div class="row">
		
		<div class="span8">
			<ul class="row thumbnails">

				<?php
				
					$it = 0;
				
					foreach($pictures_array as $picture) {
						if($it > 0 && $it % 4 == 0) {
							echo "</ul><ul class='row thumbnails'>";
						}
						
						$it++;
						
						
						$path = Picture\get_picture_path($demoshot, $picture[GET_PICTURE_ID]);
						$tn_path = Picture\get_thumbnail_path($demoshot, $path);
						
						$picture_id = $picture[GET_PICTURE_ID];
						
						$picture_url = "index.php?page_id=" . PAGE_ID_PICTURE;
						$picture_url .= "&amp;" . PAGE_ARG_PICTURE_ID . "=$picture_id";
						
						$span = "<li class='span2'><a href='$picture_url' class='thumbnail' ";
						
						// Javascript code to display the picture details when the mouse is over the picture
						$span .= 'onmouseover="';
						$span .= "toggle_description('b_r_pic$picture_id')\" ";
						
						$span .= "title='" . _("Visit the page of this picture") . "'>";
						$span .= "<img src='$tn_path' ";
						
						// Javascript code to hide the picture details when the mouse moves out
						$span .= 'onmouseout="';
						$span .= "toggle_description('b_r_pic$picture_id')\" ";
						
						// close the tag
						$span .= "alt='' /></a></li>\n\n";
						
						echo $span;
					}
				
				?>

			</ul>
		</div>
		
		<div class="span4">
			<?php
			
				echo "<div id='b_r_default'>";
				echo "<h1>" . _("Best rated pictures ever.") . "</h1>";
				echo "<h3>" . _("Hover over one picture to see its description.") . "</h3>";
				echo "</div>";
			
				foreach($pictures_array as $picture) {
					$picture_author = User\get_user_name($demoshot, $picture[GET_PICTURE_AUTHOR]);
					$idname = 'b_r_pic' . $picture[GET_PICTURE_ID];
					
					// GET_PICTURE_DATE contains the date and the time, we only want the date
					list($picture_date, ) = preg_split("/[\s]+/", $picture[GET_PICTURE_DATE]);
					
					echo "<div id='$idname' style='display: none'>";
					
					echo "<h1><em>" . $picture[GET_PICTURE_TITLE] . "</em></h1>";
					
					$author_url = "index.php?page_id=" . PAGE_ID_PROFILE;
					$author_url .= "&amp;" . PAGE_ARG_USER_ID . "=" . $picture[GET_PICTURE_AUTHOR];
					
					echo "<h2><a href='$author_url' title='";
					printf(_("Visit %s&#39;s profile"), $picture_author);
					echo "'>$picture_author</a></h2>";
					
					if($demoshot->get_lang() == USER_LANG_FR) {
						$new_date = reformat_date($picture_date, DATE_FORMAT_FR);
						
						if(is_string($new_date)) {
							$picture_date = $new_date;
						}
					}
					
					echo "<h3>$picture_date</h3>";
					
					// display the average mark with stars
					$picture_av_mark = $picture[GET_PICTURE_AVERAGE_MARK];
					
					$path_to_empty_star = 'res/img/empty_star.png';
					$path_to_full_star = 'res/img/full_star.png';
					
					echo '<p>';
					
					$empty_stars = 5 - $picture_av_mark;
					
					while($picture_av_mark-- > 0) {
						echo "<img src='$path_to_full_star' alt='' />";
					}
					
					while($empty_stars-- > 0) {
						echo "<img src='$path_to_empty_star' alt='' />";
					}
					
					echo '</p>';
					
					echo "</div>";
				}
			
			?>
		</div>
		
	</div>
	
	<h4>
	<?php
		$all_pictures_url = "index.php?page_id=" . PAGE_ID_SEARCH_RESULTS .
			"&amp;" . SEARCH_TARGET . "=" . TABLE_CODE_PICTURE .
			"&amp;" . PAGE_ARG_LIMIT . "=24";
		
		$all_pictures_text = _("See all existing pictures");
		
		$all_users_url = "index.php?page_id=" . PAGE_ID_SEARCH_RESULTS .
			"&amp;" . SEARCH_TARGET . "=" . TABLE_CODE_USER .
			"&amp;" . PAGE_ARG_LIMIT . "=24";
		
		$all_users_text = _("See all signed up users");
	?>
	<a href='<?php echo $all_pictures_url ?>' class='btn btn-inverse' style='margin-right: 50px'><i class="icon-white icon-arrow-down"></i> <?php echo $all_pictures_text ?></a>
	
	<a href='<?php echo $all_users_url ?>' class='btn btn-inverse'><i class="icon-white icon-arrow-down"></i> <?php echo $all_users_text ?></a>
	</h4>

</div>


<script>
	var last_showed_description = document.getElementById("b_r_default");
	
	/*
	 * This function displays the description of the hovered image
	 * and hide the previously displayed description.
	 */
	
	function toggle_description(id) {
		last_showed_description.style.display = "none";
		
		var description = document.getElementById(id);
		
		description.style.display = "block";
		
		last_showed_description = description;
	}
</script>
