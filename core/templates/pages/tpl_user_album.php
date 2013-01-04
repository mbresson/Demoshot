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
use \Demoshot\Pages;

require_once('core/utils/picture.php');
use \Demoshot\Picture;

require_once('core/utils/user.php');
use \Demoshot\User;

/*
 * MAIN
 */

$user_id = $demoshot_var[PAGE_VAR_USERID];
$target_id = (int) $_GET[PAGE_ARG_USER_ID];

// check if the visitor can see private pictures
$no_private_pictures = $demoshot_var[PAGE_VAR_USERID] === -1;

// get the number of pictures published by the user
list($num_of_pictures, ) = User\get_user_statistics($demoshot, $target_id);

/*
 * Get pagination and sorting informations.
 */

// compute the best number of pictures per page
$limit = (int) ceil($num_of_pictures / 6) * 6;

// not higher than 18 by default, or else there will be too much scroll
if($limit > 18) {
	$limit = 18;
}

if(isset($_GET[PAGE_ARG_LIMIT]) && (int) $_GET[PAGE_ARG_LIMIT] > 0) {
	$limit = (int) $_GET[PAGE_ARG_LIMIT];
}

// display from the picture no 0 to the picture no 17 if no other value is provided
$starting_from = 0;
if(isset($_GET[PAGE_ARG_OFFSET]) && (int) $_GET[PAGE_ARG_OFFSET] > 0) {
	$starting_from = (int) $_GET[PAGE_ARG_OFFSET];
	
	if($starting_from >= $num_of_pictures) {
		$starting_from = $num_of_pictures - $limit;
		
		if($starting_from < 0) {
			$starting_from = 0;
		}
	}
}

// sort by date if no other sorting method is provided
$sort_by = PICTURE_SORT_BY_DATE;
if(
	isset($_GET[PAGE_ARG_SORT_BY]) &&
	is_enum((int) $_GET[PAGE_ARG_SORT_BY], PICTURE_SORT_BY_TITLE, PICTURE_SORT_BY_DATE)
) {
	$sort_by = (int) $_GET[PAGE_ARG_SORT_BY];
}

// sort by descending order if no argument is provided through the URL
$sort_asc = isset($_GET[PAGE_ARG_SORT_ASC]);


// get the user whose album we want to see
$target = User\get_user($demoshot, $target_id);


// get the pictures uploaded by this user
$picture_it = new Picture\PictureRetriever(
	$demoshot, $target_id, RETRIEVE_PICTURES_BY_AUTHOR,
	$sort_by, $sort_asc, // false = descreasing order
	$no_private_pictures ? false : NULL, $limit, $starting_from
);

// store their information in an array
$pictures_array = array();

foreach($picture_it as $picture_id) {
	$pictures_array[] = Picture\get_picture($demoshot, $picture_id);
}

?>

<div class="container">

<h1>
<?php
	if($user_id == $target_id) {
		echo _("This is your album");
	} else {
		printf(
			_("This is %s's album"),
			$target[GET_USER_NAME]
		);
	}
?>
</h1>

<?php
	if($user_id == $target_id) {
		$new_picture_url = "index.php?page_id=" . PAGE_ID_NEW_PICTURE;
		
		echo "<h3><a href='$new_picture_url' class='btn btn-inverse'>";
		echo "<i class='icon-white icon-plus-sign'></i> ";
		echo _("Add a picture");
		echo "</a></h3>";
	}
?>

<?php
	if($no_private_pictures) {
		echo "<p class='alert'>";
		printf(
			_("Because you're not signed in, you won't be able to see the pictures kept private by %s."),
			$target[GET_USER_NAME]
		);
		echo "</p>";
	}

	if($num_of_pictures > 1) {
		/*
		 * Display buttons to change the sorting method and order.
		 */
		
		echo "<div style='margin-bottom: 15px'>" . _("Sort method:");
		
		// the base url to keep the limit and offset arguments
		$sort_by_base_url = "index.php?page_id=" . PAGE_ID_USER_ALBUM .
			"&amp;" . PAGE_ARG_USER_ID . "=$target_id" .
			"&amp;" . PAGE_ARG_LIMIT . "=$limit" .
			"&amp;" . PAGE_ARG_OFFSET . "=$starting_from" .
			"&amp;" . PAGE_ARG_SORT_BY . "=";
		
		$sort_by_title_url = $sort_by_base_url . PICTURE_SORT_BY_TITLE;
		$sort_by_title_text = _("sort by title");
		
		$sort_by_av_mark_url = $sort_by_base_url . PICTURE_SORT_BY_AV_MARK;
		$sort_by_av_mark_text = _("sort by average mark");
		
		$sort_by_num_of_marks_url = $sort_by_base_url . PICTURE_SORT_BY_NUM_OF_MARKS;
		$sort_by_num_of_marks_text = _("sort by number of marks");
		
		$sort_by_relevance_url = $sort_by_base_url . PICTURE_SORT_BY_RELEVANCE;
		$sort_by_relevance_text = _("sort by relevance");
		
		$sort_by_date_url = $sort_by_base_url . PICTURE_SORT_BY_DATE;
		$sort_by_date_text = _("sort by date");
		
		$sort_by_random_url = $sort_by_base_url . PICTURE_SORT_BY_RANDOM;
		$sort_by_random_text = _("random order");
		
		
		/*
		 * The head of the output,
		 * completed with "btn-inverse'" if it is the current sort method
		 * and an arrow icon if the sort order can be inversed.
		 */
		$sort_by_head = "&nbsp;&nbsp;<a class='btn btn-small";
		
		
		/*
		 * Sort by title.
		 */
		$output = $sort_by_head;
		if($sort_by == PICTURE_SORT_BY_TITLE) {
			$output .= " btn-inverse' href='$sort_by_title_url";
			
			if(!$sort_asc) { // inverse the sort order
				$output .= '&amp;' . PAGE_ARG_SORT_ASC;
			}
			
			$output .= "'><i class='icon-white ";
			
			if($sort_asc) {
				$output .= "icon-arrow-up'></i> ";
			} else {
				$output .= "icon-arrow-down'></i> ";
			}
			
			$output .= "$sort_by_title_text</a>&nbsp;";
		} else {
			$output .= "' href='$sort_by_title_url'>$sort_by_title_text</a>&nbsp;";
		}
		
		echo $output;
		
		
		/*
		 * Sort by average mark.
		 */
		$output = $sort_by_head;
		if($sort_by == PICTURE_SORT_BY_AV_MARK) {
			$output .= " btn-inverse' href='$sort_by_av_mark_url";
			
			if(!$sort_asc) {
				$output .= '&amp;' . PAGE_ARG_SORT_ASC;
			}
			
			$output .= "'><i class='icon-white ";
			
			if($sort_asc) {
				$output .= "icon-arrow-up'></i> ";
			} else {
				$output .= "icon-arrow-down'></i> ";
			}
			
			$output .= "$sort_by_av_mark_text</a>&nbsp;";
		} else {
			$output .= "' href='$sort_by_av_mark_url'>$sort_by_av_mark_text</a>&nbsp;";
		}
		
		echo $output;
		
		
		/*
		 * Sort by number of marks.
		 */
		$output = $sort_by_head;
		if($sort_by == PICTURE_SORT_BY_NUM_OF_MARKS) {
			$output .= " btn-inverse' href='$sort_by_num_of_marks_url";
			
			if(!$sort_asc) {
				$output .= '&amp;' . PAGE_ARG_SORT_ASC;
			}
			
			$output .= "'><i class='icon-white ";
			
			if($sort_asc) {
				$output .= "icon-arrow-up'></i> ";
			} else {
				$output .= "icon-arrow-down'></i> ";
			}
			
			$output .= "$sort_by_num_of_marks_text</a>&nbsp;";
		} else {
			$output .= "' href='$sort_by_num_of_marks_url'>$sort_by_num_of_marks_text</a>&nbsp;";
		}
		
		echo $output;
		
		
		/*
		 * Sort by relevance.
		 */
		$output = $sort_by_head;
		if($sort_by == PICTURE_SORT_BY_RELEVANCE) {
			$output .= " btn-inverse' href='$sort_by_relevance_url";
			
			if(!$sort_asc) {
				$output .= '&amp;' . PAGE_ARG_SORT_ASC;
			}
			
			$output .= "'><i class='icon-white ";
			
			if($sort_asc) {
				$output .= "icon-arrow-up'></i> ";
			} else {
				$output .= "icon-arrow-down'></i> ";
			}
			
			$output .= "$sort_by_relevance_text</a>&nbsp;";
		} else {
			$output .= "' href='$sort_by_relevance_url'>$sort_by_relevance_text</a>&nbsp;";
		}
		
		echo $output;
		
		
		/*
		 * Sort by date.
		 */
		$output = $sort_by_head;
		if($sort_by == PICTURE_SORT_BY_DATE) {
			$output .= " btn-inverse' href='$sort_by_date_url";
			
			if(!$sort_asc) {
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
		if($sort_by == PICTURE_SORT_BY_RANDOM) {
			$output .= " btn-inverse' href='$sort_by_random_url'>" .
				"$sort_by_random_text</a>&nbsp;";
		} else {
			$output .= "' href='$sort_by_random_url'>$sort_by_random_text</a>&nbsp;";
		}
		
		echo $output;
		
		echo "</div>";
		
		/*
		 * If the pictures come in a random order,
		 * warn the user that he may see the same picture on several pages.
		 */
		if($sort_by == PICTURE_SORT_BY_RANDOM) {
			echo "<p class='alert alert-info'>";
			echo _("Because the pictures come in random order, you may see the same picture on several pages.");
			echo "</p>";
		}
		
		if($sort_by == PICTURE_SORT_BY_RELEVANCE) {
			echo "<p class='alert alert-info'>";
			echo _("Sorting pictures by relevance means that they are first sorted by their average mark, then by their number of marks.");
			echo "</p>";
		}
	}
?>

<?php if($num_of_pictures > 0): ?>
<div>
	<div class="row" id="u_a_default" style="text-align: center">
		<h4>
			<?php echo _("Hover over one picture to see its details.") ?>
		</h4>
	</div>
	
	<?php

	foreach($pictures_array as $picture) {
		$idname = 'u_a_' . $picture[GET_PICTURE_ID];
		
		echo "<div id='$idname' style='display: none' class='row'>";
		
		// display the average mark with stars
		$picture_av_mark = $picture[GET_PICTURE_AVERAGE_MARK];
		
		$path_to_empty_star = 'res/img/empty_star_album.png';
		$path_to_full_star = 'res/img/full_star_album.png';
		
		echo '<h4 class="span2">';
		
		$empty_stars = 5 - $picture_av_mark;
		
		while($picture_av_mark-- > 0) {
			echo "<img src='$path_to_full_star' alt='' />";
		}
		
		while($empty_stars-- > 0) {
			echo "<img src='$path_to_empty_star' alt='' />";
		}
		
		echo '</h4>';
		
		echo "<h4 class='span8' style='text-align: center'><em>" . $picture[GET_PICTURE_TITLE] . "</em></h4>";
		
		
		// GET_PICTURE_DATE contains the date and the time, we skip the time
		list($picture_date, ) = preg_split("/[\s]+/", $picture[GET_PICTURE_DATE]);
		
		if($demoshot->get_lang() == USER_LANG_FR) {
			$new_date = reformat_date($picture_date, DATE_FORMAT_FR);
			
			if(is_string($new_date)) {
				$picture_date = $new_date;
			}
		}
		
		echo "<h4 class='span2' style='text-align: right'>$picture_date</h4>";
		
		echo "</div>";
	}

?>
	
</div>


<ul class="thumbnails row" id="list_of_pictures">
<?php
	foreach($pictures_array as $picture) {
		$picture_id = $picture[GET_PICTURE_ID];
		
		$path = Picture\get_picture_path($demoshot, $picture_id);
		$tn_path = Picture\get_thumbnail_path($demoshot, $path);
		
		
		$picture_title = $picture[GET_PICTURE_TITLE];
		
		$picture_url = "index.php?page_id=" . PAGE_ID_PICTURE;
		$picture_url .= "&amp;" . PAGE_ARG_PICTURE_ID . "=$picture_id";
		
		$span = "<li class='span2'><a href='$picture_url' class='thumbnail' ";
		
		// Javascript code to display the picture details when the mouse is over the picture
		$span .= 'onmouseover="';
		$span .= "toggle_description('u_a_$picture_id')\" ";
		
		$span .= "title='" . _("Visit the page of this picture") . "'>";
		$span .= "<img src='$tn_path' alt='' />";
		
		// close the tag
		$span .= "</a>";
		
		$span .= "</li>\n\n";
		
		echo $span;
	}
?>
</ul>
<?php endif; ?>

<?php

	if($picture_it->length() == 0) {
		echo "<h3>";
		echo _("This album is empty!");
		echo "</h3>";
	} else {
	
		/*
		 * Display:
		 * - buttons to change the number of results per page
		 * - pagination (links to other pages)
		 * 
		 * pagination.php takes care of this,
		 * we only have the following information:
		 */
	
		$base_url = "index.php?page_id=" . PAGE_ID_USER_ALBUM .
			"&amp;" . PAGE_ARG_USER_ID . "=$target_id" .
			"&amp;" . PAGE_ARG_SORT_BY . "=$sort_by" .
			($sort_asc ? "&amp;" . PAGE_ARG_SORT_ASC : "");
		
		$num_of_results = $num_of_pictures;
		$lowest_limit = 6;
		$limit_increase = 6;
		$anchor = "#list_of_pictures";
		$results_per_page_text = _("Pictures per page:");
		
		require_once('core/templates/skel/pagination.php');
		
	}
?>

<h3>
	<?php
		$profile_url = "index.php?page_id=" . PAGE_ID_PROFILE;
		$profile_url .= "&amp;" . PAGE_ARG_USER_ID . "=$target_id";
		
		if($user_id == $target_id) {
			$profile_text = _("go back to your profile page");
		} else {
			$profile_text =_("go back to his profile");
		}
	?>
	
	<a class="btn" href='<?php echo $profile_url ?>'>
		<i class="icon-arrow-left"></i> <?php echo $profile_text ?>
	</a>
</h3>

</div>


<script>
	var last_showed_description = document.getElementById("u_a_default");
	
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
