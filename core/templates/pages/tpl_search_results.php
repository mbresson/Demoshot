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

require_once('core/utils/user.php');
use \Demoshot\User;

require_once('core/utils/picture.php');
use \Demoshot\Picture;

require_once('core/utils/mark.php');
use \Demoshot\Mark;

require_once('core/utils/tag.php');
use \Demoshot\Tag;


/*
 * MAIN
 */

/*
 * This array is used to store the warnings to issue to the user, if any.
 * E.g, if the visitor has input tags that don't exist, he must be warned.
 */
$messages = array();

/*
 * Retrieve all the search criteria.
 */

/*
 * When checking the different search criteria, some of them may lead to 0 results.
 * 
 * E.g: the visitor wants to search for a picture related to an exising tag
 * and a second tag which doesn't exist, with $join_tags == true (see below).
 * As a result, we can be sure the query will return 0 results because no picture can be
 * related to a non-existing tag.
 * 
 * In such a case, we set $no_query to true, there is no need to query the database.
 */
$no_query = false;

// are we searching for pictures (TABLE_CODE_PICTURE) or for users (TABLE_CODE_USER)?
$target_type = (int) $_GET[SEARCH_TARGET];

/*
 * Search for users/pictures whose name/title contains a peculiar pattern,
 * if the pattern is provided in the URL.
 */
$pattern_title = NULL;
if(isset($_GET[SEARCH_PATTERN_TITLE]) && $_GET[SEARCH_PATTERN_TITLE] !== "") {
	$pattern_title = $_GET[SEARCH_PATTERN_TITLE];
}

/*
 * Search for users/pictures whose description contains a peculiar pattern,
 * if the pattern is provided in the URL.
 */
$pattern_description = NULL;
if(isset($_GET[SEARCH_PATTERN_DESCRIPTION]) && $_GET[SEARCH_PATTERN_DESCRIPTION] !== "") {
	$pattern_description = $_GET[SEARCH_PATTERN_DESCRIPTION];
}

/*
 * By default, search for either users/pictures whose name/title contains a peculiar pattern
 * OR users/pictures whose description contains another peculiar pattern (it could be the same pattern).
 * 
 * If $join_patterns, search for users/pictures whose name/title contains a peculiar pattern
 * AND users/pictures whose description contains another peculiar pattern.
 */
$join_patterns = false;
if(isset($_GET[SEARCH_PATTERN_JOIN]) && $_GET[SEARCH_PATTERN_JOIN] == "1") {
	$join_patterns = true;
}

/*
 * If we are searching for pictures, we can choose to keep only the private ones or the public ones.
 */
$private_pictures = NULL;
if(isset($_GET[SEARCH_VISIBILITY])) {
	switch((int) $_GET[SEARCH_VISIBILITY]) {
		case SEARCH_VISIBILITY_PUBLIC:
			$private_pictures = false;
			break;
		
		case SEARCH_VISIBILITY_PRIVATE:
			$private_pictures = true;
			break;
	}
}

if($demoshot_var[PAGE_VAR_USERID] == -1) {
	$private_pictures = false;
}


/*
 * By default, search for pictures/users related to any of the tags.
 * If $join_tags, search for pictures/users related to all of them.
 */
$join_tags = false;
if(isset($_GET[SEARCH_TAGS_JOIN]) && $_GET[SEARCH_TAGS_JOIN] == "1") {
	$join_tags = true;
}


/*
 * Retrieve the type of criterion used to search for pictures/users.
 */
$crit_type = $target_type == TABLE_CODE_PICTURE ? RETRIEVE_ALL_PICTURES : RETRIEVE_ALL_USERS;
if(
	isset($_GET[SEARCH_CRIT_TYPE]) && $_GET[SEARCH_CRIT_TYPE] != '' &&
	isset($_GET[SEARCH_CRIT_VALUE]) && $_GET[SEARCH_CRIT_VALUE] != ''
) {
	$crit_type = (int) $_GET[SEARCH_CRIT_TYPE];
	
	if($target_type == TABLE_CODE_PICTURE) {
		if(!is_enum($crit_type, RETRIEVE_PICTURES_BY_TAG, RETRIEVE_ALL_PICTURES)) {
			$crit_type = RETRIEVE_ALL_PICTURES;
		}
	} else {
		if(!is_enum($crit_type, RETRIEVE_USERS_BY_FOLLOWED, RETRIEVE_ALL_USERS)) {
			$crit_type = RETRIEVE_ALL_USERS;
		}
	}
}

/*
 * Retrieve the research criterion.
 */
$crit_value = NULL;
if(
	($target_type == TABLE_CODE_PICTURE && $crit_type == RETRIEVE_PICTURES_BY_TAG) ||
	($target_type == TABLE_CODE_USER && $crit_type == RETRIEVE_USERS_BY_TAG)
) {
	$tmp_tags = Tag\split_tags($_GET[SEARCH_CRIT_VALUE]);
	$crit_value = array();
	
	/*
	 * Loop through the array of tags, to make sure they all exist.
	 * Add the existing ones to $tags.
	 */
	foreach($tmp_tags as $tag_title) {
		$tag_id = Tag\get_tag_id($demoshot, $tag_title);
		
		if($tag_id >= 0) {
			$crit_value[] = $tag_id;
		} else {
			$messages[] = sprintf(
				_("The tag %s doesn't exit!"),
				"<em>$tag_title</em>"
			);
			
			// clear the error message stored by get_tag_id
			$demoshot->clear_error();
			
			if($join_tags) {
				/*
				 * No picture can be related to a non-existing tag,
				 * no need to query the database.
				 */
				$no_query = true;
			}
		}
	}
	
	// if there were no existing tags
	if(count($crit_value) == 0) {
		$no_query = true;
	}
} else if(
	($target_type == TABLE_CODE_PICTURE && $crit_type != RETRIEVE_ALL_PICTURES) ||
	($target_type == TABLE_CODE_USER && $crit_type != RETRIEVE_ALL_USERS)
) {
	$crit_value = (int) $_GET[SEARCH_CRIT_VALUE];
}


/*
 * Get pagination and sorting informations.
 */

// the number of results per page
$limit = 12;

if(isset($_GET[PAGE_ARG_LIMIT]) && (int) $_GET[PAGE_ARG_LIMIT] > 0) {
	$limit = (int) $_GET[PAGE_ARG_LIMIT];
}

// display from picture/user no 0/0 to picture/user no 17/31 if no other value is provided
$starting_from = 0;
if(isset($_GET[PAGE_ARG_OFFSET]) && (int) $_GET[PAGE_ARG_OFFSET] > 0) {
	$starting_from = (int) $_GET[PAGE_ARG_OFFSET];
}

// sort pictures/users by date/username if no other sorting method is provided
$sort_by = $target_type == TABLE_CODE_PICTURE ? PICTURE_SORT_BY_DATE : USER_SORT_BY_USERNAME;
if(isset($_GET[PAGE_ARG_SORT_BY])) {
	
	if(
		$target_type == TABLE_CODE_PICTURE &&
		is_enum((int) $_GET[PAGE_ARG_SORT_BY], PICTURE_SORT_BY_TITLE, PICTURE_SORT_BY_DATE)
	) {
		
		$sort_by = (int) $_GET[PAGE_ARG_SORT_BY];
		
	} else if(
		$target_type == TABLE_CODE_USER &&
		is_enum((int) $_GET[PAGE_ARG_SORT_BY], USER_SORT_BY_DATE, USER_SORT_BY_RANDOM)
	) {
		
		$sort_by = (int) $_GET[PAGE_ARG_SORT_BY];
		
	}
}

// sort by descending order if no argument is provided through the URL
$sort_asc = isset($_GET[PAGE_ARG_SORT_ASC]);


/*
 * Query the database.
 */
$result_it = NULL;
$success = false;

if(!$no_query) {
	if($target_type == TABLE_CODE_PICTURE) {
		
		// determine the number of results when there is no limitation, used for pagination
		$num_of_results = Picture\get_number_of_pictures(
			$demoshot, $crit_value, $crit_type, $private_pictures,
			$pattern_title, $pattern_description,
			$join_tags, $join_patterns
		);
		
		$result_it = new Picture\PictureRetriever(
			$demoshot, $crit_value, $crit_type, $sort_by,
			$sort_asc, $private_pictures, $limit, $starting_from,
			$pattern_title, $pattern_description,
			$join_tags, $join_patterns
		);
		
	} else {
		
		// determine the number of results when there is no limitation, used for pagination
		$num_of_results = User\get_number_of_users(
			$demoshot, $crit_value, $crit_type,
			$pattern_title, $pattern_description,
			$join_tags, $join_patterns
		);
		
		// retrieve the results
		$result_it = new User\UserRetriever(
			$demoshot, $crit_value, $crit_type,
			$sort_by, $sort_asc, $limit, $starting_from,
			$pattern_title, $pattern_description,
			$join_tags, $join_patterns
		);
		
	}
	
	if($num_of_results > 0) {
		$success = true;
	}
}


/*
 * Save the search criteria in a URL to allow the visitor to edit his search.
 */
$num_join_patterns = $join_patterns ? "1" : "0";
$num_join_tags = $join_tags ? "1" : "0";

$export_crit_value = isset($_GET[SEARCH_CRIT_VALUE]) ? $_GET[SEARCH_CRIT_VALUE] : "";

$url_args = "&amp;" . SEARCH_TARGET . "=$target_type" .
	"&amp;" . SEARCH_PATTERN_TITLE . "=$pattern_title" .
	"&amp;" . SEARCH_PATTERN_DESCRIPTION . "=$pattern_description" .
	"&amp;" . SEARCH_PATTERN_JOIN . "=$num_join_patterns" .
	"&amp;" . SEARCH_CRIT_TYPE . "=$crit_type" .
	"&amp;" . SEARCH_CRIT_VALUE . "=$export_crit_value" .
	"&amp;" . SEARCH_TAGS_JOIN . "=$num_join_tags";

$base_search_url = "index.php?page_id=" . PAGE_ID_SEARCH_RESULTS . $url_args;
$edit_search_url = "index.php?page_id=" . PAGE_ID_SEARCH . $url_args;

if(is_bool($private_pictures) && $demoshot_var[PAGE_VAR_USERID] != -1) {
	$edit_search_url .= "&amp;" . SEARCH_VISIBILITY . "=";
	$base_search_url .= "&amp;" . SEARCH_VISIBILITY . "=";
	
	$edit_search_url .= $private_pictures ? SEARCH_VISIBILITY_PRIVATE : SEARCH_VISIBILITY_PUBLIC;
	$base_search_url .= $private_pictures ? SEARCH_VISIBILITY_PRIVATE : SEARCH_VISIBILITY_PUBLIC;
}


?>

<div class="container">

<?php

echo "<h1>";

if(!$success) {
	echo _("No results :(");
} else {
	if($num_of_results == 1) {
		echo _("1 result");
	} else {
		printf(
			_("%d results"),
			$num_of_results
		);
	}
}

echo "</h1>";

foreach($messages as $message) {
	echo "<h3 class='dm_wrong_input'>$message</h3>";
}


/*
 * Display a button allowing the user to edit his search criteria.
 * This option is restricted to the users who have used the search page to come here.
 */
if(
	($target_type == TABLE_CODE_PICTURE && ($crit_type == RETRIEVE_PICTURES_BY_TAG || $crit_type == RETRIEVE_ALL_PICTURES)) ||
	($target_type == TABLE_CODE_USER && ($crit_type == RETRIEVE_USERS_BY_TAG || $crit_type == RETRIEVE_ALL_USERS))
) {
	echo "<p><a href='$edit_search_url' class='btn btn-large'>" .
		"<i class='icon-arrow-left'></i> " .
		_("edit the search criteria") . "</a></p>";
}


if($success && $target_type == TABLE_CODE_PICTURE):

	// store the details of each picture in an array
	$pictures_array = array();
	
	foreach($result_it as $picture_id) {
		$pictures_array[] = Picture\get_picture($demoshot, $picture_id);
	}
	
	if($num_of_results > 1) {
		/*
		 * Display buttons to change the sorting method and order.
		 */
		
		echo "<div style='margin-bottom: 15px'>" . _("Sort by:");
		
		// the base url to keep the limit and offset arguments
		$sort_by_base_url = $base_search_url .
			"&amp;" . PAGE_ARG_LIMIT . "=$limit" .
			"&amp;" . PAGE_ARG_OFFSET . "=$starting_from" .
			"&amp;" . PAGE_ARG_SORT_BY . "=";
		
		$sort_by_title_url = $sort_by_base_url . PICTURE_SORT_BY_TITLE;
		$sort_by_title_text = _("title");
		
		$sort_by_av_mark_url = $sort_by_base_url . PICTURE_SORT_BY_AV_MARK;
		$sort_by_av_mark_text = _("average mark");
		
		$sort_by_num_of_marks_url = $sort_by_base_url . PICTURE_SORT_BY_NUM_OF_MARKS;
		$sort_by_num_of_marks_text = _("number of marks");
		
		$sort_by_relevance_url = $sort_by_base_url . PICTURE_SORT_BY_RELEVANCE;
		$sort_by_relevance_text = _("relevance");
		
		$sort_by_date_url = $sort_by_base_url . PICTURE_SORT_BY_DATE;
		$sort_by_date_text = _("date");
		
		if($crit_type == RETRIEVE_PICTURES_BY_MARK_AUTHOR) {
			/*
			 * Sorting pictures by date of the mark is only available
			 * if we are searching for pictures marked by a user.
			 */
			
			$sort_by_mark_date_url = $sort_by_base_url . PICTURE_SORT_BY_MARK_DATE;
			$sort_by_mark_date_text = _("date of the mark");
		}
		
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
		
		
		if(isset($sort_by_mark_date_text)) {
			/*
			 * Sort by date of mark.
			 */
			
			$output = $sort_by_head;
			if($sort_by == PICTURE_SORT_BY_MARK_DATE) {
				$output .= " btn-inverse' href='$sort_by_mark_date_url";
				
				if(!$sort_asc) {
					$output .= '&amp;' . PAGE_ARG_SORT_ASC;
				}
				
				$output .= "'><i class='icon-white ";
				
				if($sort_asc) {
					$output .= "icon-arrow-up'></i> ";
				} else {
					$output .= "icon-arrow-down'></i> ";
				}
				
				$output .= "$sort_by_mark_date_text</a>&nbsp;";
			} else {
				$output .= "' href='$sort_by_mark_date_url'>$sort_by_mark_date_text</a>&nbsp;";
			}
			
			echo $output;
		}
		
		
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

<!--				PICTURES			-->

<div>
	<div class="row" id="r_p_default" style="text-align: center">
		<h4>
			<?php echo _("Hover over one picture to see its details.") ?>
		</h4>
	</div>

<?php

	foreach($pictures_array as $picture) {
		$idname = 'r_p_' . $picture[GET_PICTURE_ID];
		
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
		
		
		echo "<h4 class='span4' style='text-align: center'><em>" . $picture[GET_PICTURE_TITLE] . "</em></h4>";
		
		
		$author_id = $picture[GET_PICTURE_AUTHOR];
		$author_name = User\get_user_name($demoshot, $author_id);
		$author_url = "index.php?page_id=" . PAGE_ID_PROFILE .
			"&amp;" . PAGE_ARG_USER_ID . "=$author_id";
		
		echo "<h4 class='span4' style='text-align: center'>" . _("by ") . "<a href='$author_url' title='";
		printf(_("Visit %s&#39;s profile"), $author_name);
		echo "'>" . $author_name . "</a></h4>";
		
		
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

<ul class="thumbnails row" id="list_of_results">
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
		$span .= "toggle_description('r_p_$picture_id')\" ";
		
		$span .= "title='" . _("Visit the page of this picture") . "'>";
		$span .= "<img src='$tn_path' alt='' />";
		
		// close the tag
		$span .= "</a>";
		
		$span .= "</li>\n\n";
		
		echo $span;
	}
?>
</ul>


<!--				USERS				-->


<?php elseif($success && $target_type == TABLE_CODE_USER):

	// store the details of each user in an array
	$users_array = array();
	
	foreach($result_it as $user_id) {
		$users_array[] = User\get_user($demoshot, $user_id);
	}
	
	if($num_of_results > 1) {
		/*
		 * Display buttons to change the sorting method and order.
		 */
		
		echo "<div style='margin-bottom: 15px'>" . _("Sort by:");
		
		// the base url to keep the limit and offset arguments
		$sort_by_base_url = $base_search_url .
			"&amp;" . PAGE_ARG_LIMIT . "=$limit" .
			"&amp;" . PAGE_ARG_OFFSET . "=$starting_from" .
			"&amp;" . PAGE_ARG_SORT_BY . "=";
		
		$sort_by_name_url = $sort_by_base_url . USER_SORT_BY_USERNAME;
		$sort_by_name_text = _("username");
		
		$sort_by_date_url = $sort_by_base_url . USER_SORT_BY_DATE;
		$sort_by_date_text = _("signup date");
		
		$sort_by_pictures_url = $sort_by_base_url . USER_SORT_BY_NUM_OF_PICTURES;
		$sort_by_pictures_text = _("number of pictures");
		
		$sort_by_marks_url = $sort_by_base_url . USER_SORT_BY_NUM_OF_MARKS;
		$sort_by_marks_text = _("number of marks");
		
		$sort_by_involvement_url = $sort_by_base_url . USER_SORT_BY_INVOLVEMENT;
		$sort_by_involvement_text = _("involvement");
		
		$sort_by_popularity_url = $sort_by_base_url . USER_SORT_BY_POPULARITY;
		$sort_by_popularity_text = _("popularity");
		
		$sort_by_random_url = $sort_by_base_url . USER_SORT_BY_RANDOM;
		$sort_by_random_text = _("random order");
		
		
		/*
		 * The head of the output,
		 * completed with "btn-inverse'" if it is the current sort method
		 * and an arrow icon if the sort order can be inversed.
		 */
		$sort_by_head = "&nbsp;&nbsp;<a class='btn btn-small";
		
		
		/*
		 * Sort by username.
		 */
		$output = $sort_by_head;
		if($sort_by == USER_SORT_BY_USERNAME) {
			$output .= " btn-inverse' href='$sort_by_name_url";
			
			if(!$sort_asc) { // inverse the sort order
				$output .= '&amp;' . PAGE_ARG_SORT_ASC;
			}
			
			$output .= "'><i class='icon-white ";
			
			if($sort_asc) {
				$output .= "icon-arrow-up'></i> ";
			} else {
				$output .= "icon-arrow-down'></i> ";
			}
			
			$output .= "$sort_by_name_text</a>&nbsp;";
		} else {
			$output .= "' href='$sort_by_name_url'>$sort_by_name_text</a>&nbsp;";
		}
		
		echo $output;
		
		
		/*
		 * Sort by date.
		 */
		$output = $sort_by_head;
		if($sort_by == USER_SORT_BY_DATE) {
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
		 * Sort by number of pictures.
		 */
		$output = $sort_by_head;
		if($sort_by == USER_SORT_BY_NUM_OF_PICTURES) {
			$output .= " btn-inverse' href='$sort_by_pictures_url";
			
			if(!$sort_asc) {
				$output .= '&amp;' . PAGE_ARG_SORT_ASC;
			}
			
			$output .= "'><i class='icon-white ";
			
			if($sort_asc) {
				$output .= "icon-arrow-up'></i> ";
			} else {
				$output .= "icon-arrow-down'></i> ";
			}
			
			$output .= "$sort_by_pictures_text</a>&nbsp;";
		} else {
			$output .= "' href='$sort_by_pictures_url'>$sort_by_pictures_text</a>&nbsp;";
		}
		
		
		echo $output;
		
		
		/*
		 * Sort by involvement.
		 */
		$output = $sort_by_head;
		if($sort_by == USER_SORT_BY_INVOLVEMENT) {
			$output .= " btn-inverse' href='$sort_by_involvement_url";
			
			if(!$sort_asc) {
				$output .= '&amp;' . PAGE_ARG_SORT_ASC;
			}
			
			$output .= "'><i class='icon-white ";
			
			if($sort_asc) {
				$output .= "icon-arrow-up'></i> ";
			} else {
				$output .= "icon-arrow-down'></i> ";
			}
			
			$output .= "$sort_by_involvement_text</a>&nbsp;";
		} else {
			$output .= "' href='$sort_by_involvement_url'>$sort_by_involvement_text</a>&nbsp;";
		}
		
		echo $output;
		
		
		/*
		 * Sort by popularity.
		 */
		$output = $sort_by_head;
		if($sort_by == USER_SORT_BY_POPULARITY) {
			$output .= " btn-inverse' href='$sort_by_popularity_url";
			
			if(!$sort_asc) {
				$output .= '&amp;' . PAGE_ARG_SORT_ASC;
			}
			
			$output .= "'><i class='icon-white ";
			
			if($sort_asc) {
				$output .= "icon-arrow-up'></i> ";
			} else {
				$output .= "icon-arrow-down'></i> ";
			}
			
			$output .= "$sort_by_popularity_text</a>&nbsp;";
		} else {
			$output .= "' href='$sort_by_popularity_url'>$sort_by_popularity_text</a>&nbsp;";
		}
		
		echo $output;
		
		
		/*
		 * Random order.
		 */
		$output = $sort_by_head;
		if($sort_by == USER_SORT_BY_RANDOM) {
			$output .= " btn-inverse' href='$sort_by_random_url'>" .
				"$sort_by_random_text</a>&nbsp;";
		} else {
			$output .= "' href='$sort_by_random_url'>$sort_by_random_text</a>&nbsp;";
		}
		
		echo $output;
		
		
		echo "</div>";
		
		
		/*
		 * If the users are sorted by involvement,
		 * explain what the sorting method consists of.
		 */
		if($sort_by == USER_SORT_BY_INVOLVEMENT) {
			echo "<p class='alert alert-info'>";
			echo _("Sorting users by involvement means that they are sorted by the sum of two numbers: the number of pictures they uploaded and the number of pictures they marked.");
			echo "</p>";
		}
		
		
		/*
		 * If the users are sorted by popularity,
		 * explain what the sorting method consists of.
		 */
		if($sort_by == USER_SORT_BY_POPULARITY) {
			echo "<p class='alert alert-info'>";
			echo _("Sorting users by popularity means that they are first sorted by their number of followers, then by the number of people they follow.");
			echo "</p>";
		}
		
		
		/*
		 * If the users come in a random order,
		 * warn the visitor that he may see the same user on several pages.
		 */
		if($sort_by == USER_SORT_BY_RANDOM) {
			echo "<p class='alert alert-info'>";
			echo _("Because the users come in random order, you may see the same user on several pages.");
			echo "</p>";
		}
	}
	
?>

<div>
	<div class="row" id="r_p_default" style="text-align: center">
		<h4>
			<?php echo _("Hover over one user to see its details.") ?>
		</h4>
	</div>
	
<?php

	foreach($users_array as $user) {
		$user_id = $user[GET_USER_ID];
		$username = $user[GET_USER_NAME];
		
		// GET_USER_DATE contains the date and the time, we skip the time
		list($signup_date, ) = preg_split("/[\s]+/", $user[GET_USER_DATE]);
		
		if($demoshot->get_lang() == USER_LANG_FR) {
			$new_date = reformat_date($signup_date, DATE_FORMAT_FR);
			
			if(is_string($new_date)) {
				$signup_date = $new_date;
			}
		}
		
		$idname = 'r_p_' . $user_id;
		
		echo "<div id='$idname' style='display: none' class='row'>";
		
		list($num_of_pictures, $num_of_marks, $num_of_followers, $num_of_followed) = User\get_user_statistics($demoshot, $user_id);
		
		echo "<h5 class='span3'>";
		
		$pictures_icon = "<i class='icon-picture'></i> ";
		
		$marks_icon = "<i class='icon-star'></i> ";
		if($num_of_marks == 0) {
			$marks_icon = "<i class='icon-star-empty'></i> ";
		}
		
		$followers_icon = "<i class='icon-user'></i> ";
		
		$followed_icon = "<i class='icon-eye-open'></i> ";
		if($num_of_followed == 0) {
			$followed_icon = "<i class='icon-eye-close'></i> ";
		}
		
		$pictures_title = sprintf(_("%d pictures uploaded"), $num_of_pictures);
		if($num_of_pictures < 2) {
			$pictures_title = sprintf(_("%d picture uploaded"), $num_of_pictures);
		}
		
		$marks_title = sprintf(_("%d pictures marked"), $num_of_marks);
		if($num_of_marks < 2) {
			$marks_title = sprintf(_("%d picture marked"), $num_of_marks);
		}
		
		$followers_title = sprintf(_("%d followers"), $num_of_followers);
		if($num_of_followers < 2) {
			$followers_title = sprintf(_("%d follower"), $num_of_followers);
		}
		
		$followed_title = sprintf(_("%d users followed"), $num_of_followed);
		if($num_of_followed < 2) {
			$followed_title = sprintf(_("%d user followed"), $num_of_followed);
		}
		
		echo "<span style='margin-right: 15px' title='$pictures_title'>$pictures_icon $num_of_pictures</span>";
		echo "<span style='margin-right: 15px' title='$marks_title'>$marks_icon $num_of_marks</span>";
		echo "<span style='margin-right: 15px' title='$followers_title'>$followers_icon $num_of_followers</span>";
		echo "<span title='$followed_title'>$followed_icon $num_of_followed</span>";
		echo "</h5>";
		
		echo "<h4 class='span6' style='text-align: center'><em>$username</em></a></h4>";
		
		echo '<h4 class="span3">';
		printf(
			_("signup date: %s"),
			$signup_date
		);
		echo '</h4>';
		
		echo "</div>";
	}

?>
	
</div>

<ul class="thumbnails row" id="list_of_results">
<?php

	echo "<ul class='row thumbnails'>";
	
	foreach($users_array as $user) {
		$avatar_path = $user[GET_USER_AVATAR];
		$username = $user[GET_USER_NAME];
		$user_id = $user[GET_USER_ID];
		
		$profile_url = "index.php?page_id=" . PAGE_ID_PROFILE;
		$profile_url .= "&amp;" . PAGE_ARG_USER_ID . "=$user_id";
		
		$span = "<li class='span2'><a class='thumbnail' href='$profile_url' ";
		
		// Javascript code to display the user details when the mouse is over the avatar
		$span .= 'onmouseover="';
		$span .= "toggle_description('r_p_$user_id')\" ";
		
		$span .= "title='" . sprintf(_("Visit %s&#39;s profile"), $username);
		$span .= "'><img src='$avatar_path' alt='$username' /></a></li>";
		
		echo $span;
	}
	
	echo "</ul>";

endif; ?>
</ul>


<?php if($success):

	if($num_of_results > 0) {
		
		/*
		 * Display:
		 * - buttons to change the number of results per page
		 * - pagination (links to other pages)
		 * 
		 * pagination.php takes care of this,
		 * we only have the following information:
		 */
	
		$base_url = $base_search_url .
			"&amp;" . PAGE_ARG_SORT_BY . "=$sort_by" .
			($sort_asc ? "&amp;" . PAGE_ARG_SORT_ASC : "");
		
		$lowest_limit = 12;
		$limit_increase = 6;
		$anchor = "#list_of_results";
		$results_per_page_text = _("Results per page:");
		
		require_once('core/templates/skel/pagination.php');
	}

endif; ?>



<script>
	var last_showed_description = document.getElementById("r_p_default");
	
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
