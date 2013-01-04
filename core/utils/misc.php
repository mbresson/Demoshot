<?php // these functions are so common they need no namespace


/*
 * CONSTANTS
 */
$enumi = 0;
define("DATE_FORMAT_EN", $enumi++);
define("DATE_FORMAT_FR", $enumi++);


/*
 * FUNCTIONS
 */

/*
 * @function reformat_date
 * 
 * @purpose
 * Reformat the date given in English format (year-month-day) to another format.
 * 
 * @param string $date
 * The date, correctly formatted.
 * E.g. to reformat an English date, $str may be 2012-11-24
 * 
 * @param int $format
 * The new format of the date.
 * DATE_FORMAT_FR || DATE_FORMAT_EN
 * 
 * @retval string
 * Or NULL on failure.
 */
function reformat_date($date, $format) {
	assert(is_string($date));
	assert(is_enum($format, DATE_FORMAT_EN, DATE_FORMAT_FR));
	
	if($format == DATE_FORMAT_FR) {
		$all = preg_split("*-*", $date);
		
		if(count($all) != 3) {
			return NULL;
		}
		
		$year = $all[0];
		$month = $all[1];
		$day = $all[2];
		
		return "$day/$month/$year";
	} else {
		return NULL;
	}
}

/*
 * @function is_enum
 * 
 * @purpose
 * Check the validity of a parameter which must be the value of an enumeration.
 * 
 * @param int $lower, $upper
 * The lower and upper bounds of the enumeration type.
 */
function is_enum($val, $lower, $upper) {
	return (is_int($val) && $val >= $lower && $val <= $upper);
}

/*
 * @function is_id
 * 
 * @purpose
 * Check the validity of a parameter which must be an unsigned int.
 */
function is_unsigned($val) {
	return (is_int($val) && $val >= 0);
}

/*
 * @function remove_dir
 * 
 * @purpose
 * Remove a directory and all the files and directories it may contain.
 * 
 * @retval bool
 * True on success, false on failure.
 */
function remove_dir($path) {
	$files = array_diff(scandir($path), array('.', '..'));
	
	foreach($files as $file) {
		if(is_dir("$path/$file")) {
			remove_dir("$path/$file");
		} else {
			unlink("$path/$file");
		}
	}
	
	return rmdir($path);
}

?>
