<?php

/*
 * This is a template file.
 * It is used to compose each page sent to a client by the server.
 * The template pages can make use of the variables stored in the array
 * $demoshot_var to retrieve all the information they need.
 */

assert(isset($demoshot));
assert(isset($demoshot_var));

/*
 * To allow the user to change the number of results per page
 * and to display the pagination, the following variables must be set:
 */

/*
 * The base url of the current page.
 * It may hold informations about sorting method and sorting order.
 */
assert(isset($base_url) && is_string($base_url));

/*
 * The total number of results.
 */
assert(isset($num_of_results) && is_unsigned($num_of_results));

/*
 * The number of results per page.
 */
assert(isset($limit) && is_unsigned($limit));

/*
 * The index of the first result displayed on the page.
 */
assert(isset($starting_from) && is_unsigned($starting_from));

/*
 * The minimum number of results per page.
 */
assert(isset($lowest_limit) && is_unsigned($lowest_limit));

/*
 * The number used to increase the number of results per page.
 * E.g: with $lowest_limit = 6 and $limit_increase = 6 => 6 results per page, 18, 24, 30, etc.
 */
assert(isset($limit_increase) && is_unsigned($limit_increase));

/*
 * An anchor to a block in the page.
 * It will be added at the end of every URL echo-ed in this file.
 */
if(!isset($anchor) || !is_string($anchor)) {
	$anchor = "#";
}

/*
 * The text displayed to introduce the buttons to change the number of results per page.
 * E.g: if we are on the picture page, it will be _("Marks per page:").
 */
if(!isset($results_per_page_text) || !is_string($results_per_page_text)) {
	$results_per_page_text = _("Results per page:");
}

?>



<div style='margin: 20px 0;'>
<?php

	/*
	 * Display buttons to change the number of visible results per page.
	 * The max number of visible results is determined by the number of marks:
	 * e.g. if there are 37 marks, the max number of visible results is 50.
	 */
	
	$limit_base_url = $base_url .
		"&amp;" . PAGE_ARG_LIMIT . "=";
	
	echo $results_per_page_text;
	
	$limit_value_array = array($lowest_limit);
	$limit_it = 0;
	
	/*
	 * Stop suggesting more results per page when the number of results per page
	 * is high enough to display all of them in one single page.
	 */
	while($limit_value_array[$limit_it++] < $num_of_results) {
		$limit_value_array[$limit_it] = $limit_value_array[$limit_it - 1] + $limit_increase;
	}
	
	$limit_head = "&nbsp;<a class='btn btn-small";
	
	// echo every button
	for($it = 0, $c = count($limit_value_array); $it < $c; $it++) {
		$tmp_value = $limit_value_array[$it];
		
		$output = $limit_head;
		
		$tmp_url = $limit_base_url . $tmp_value;
		
		if($limit == $tmp_value) { // if it is the limit in the current page
			$output .= " btn-inverse";
		}
		
		$output .= "' href='$tmp_url$anchor'>$tmp_value</a>&nbsp;";
		
		echo $output;
	}
?>
</div>

<?php if($num_of_results > $limit) : ?>
<div class='pagination'><ul>
<?php

	/*
	 * Display the pagination if some results are not displayed.
	 */

	// the base url to keep the limit, offset and sortby arguments
	$offset_base_url = $base_url .
		"&amp;" . PAGE_ARG_LIMIT . "=$limit" .
		"&amp;" . PAGE_ARG_OFFSET . "=";
	
	// if there a previous page?
	if($starting_from > 0) {
		$previous_page_url = $offset_base_url . ($starting_from - $limit);
		
		echo "<li><a href='$previous_page_url$anchor'>" . _("Previous page") . "</a></li>";
	} else {
		echo "<li class='disabled'><a href='$anchor'>" . _("Previous page") . "</a></li>";
	}
	
	// how many pages are needed to display all the pictures?
	$num_of_pages = ceil($num_of_results / $limit);
	
	for($page_it = 0; $page_it < $num_of_pages; $page_it++) {
		
		// the index of the first picture displayed on this page
		$lower_bound = $page_it * $limit;
		
		// the index of the first picture displayed on the next page
		$upper_bound = (1 + $page_it) * $limit - 1;
		
		if($starting_from > $upper_bound) {
			// this is a previous page
			$page_url = $offset_base_url . $lower_bound;
			echo "<li><a href='$page_url$anchor'>" . ($page_it + 1) . "</a></li>";
			
		} else if($starting_from < $lower_bound) {
			// this is a next page
			$page_url = $offset_base_url . $lower_bound;
			echo "<li><a href='$page_url$anchor'>" . ($page_it + 1) . "</a></li>";
			
		} else {
			// this is current page
			echo "<li class='disabled'><a href='$anchor'>" . ($page_it + 1) . "</a></li>";
			
		}
	}
	
	// if there a next page?
	if($num_of_results - $starting_from > $limit) {
		$next_page_url = $offset_base_url . ($starting_from + $limit);
		
		echo "<li><a href='$next_page_url$anchor'>" . _("Next page") . "</a></li>";
	} else {
		echo "<li class='disabled'><a href='$anchor'>" . _("Next page") . "</a></li>";
	}

?>
</ul></div>
<?php endif; ?>

