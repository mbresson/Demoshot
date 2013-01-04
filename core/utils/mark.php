<?php namespace Demoshot\Mark;


/*
 * DEPENDENCIES
 */

require_once('misc.php');

require_once('errors.php');
use \Demoshot\Errors;

require_once('database.php');
use \Demoshot\Database;


/*
 * CONSTANTS
 */

define('MARK_MAX_VALUE', 5);
define('MARK_MAX_COMMENT_LENGTH', 1000);

/*
 * The following constants help accessing the informations
 * returned by MarkRetriever.
 */
$enumi = 0;
define('GET_MARK_ID_AUTHOR_ID', $enumi++);
define('GET_MARK_ID_PICTURE_ID', $enumi++);

/*
 * The following constants help accessing the informations
 * returned by the function get_mark.
 */
$enumi = 0;
define('GET_MARK_VALUE', $enumi++);
define('GET_MARK_COMMENT', $enumi++);
define('GET_MARK_PRIVATE', $enumi++);
define('GET_MARK_DATE', $enumi++);

/*
 * These constants can be passed to MarkRetriever to have
 * the results sorted by a column.
 */
$enumi = 0;
define('MARK_SORT_BY_NONE', $enumi++);
define('MARK_SORT_BY_PICTURE_ID', $enumi++);
define('MARK_SORT_BY_AUTHOR_ID', $enumi++);
define('MARK_SORT_BY_VALUE', $enumi++);
define('MARK_SORT_BY_DATE', $enumi++);
define('MARK_SORT_BY_RANDOM', $enumi++);


/*
 * FUNCTIONS
 */

/*
 * @function compute_average_mark
 * 
 * @purpose
 * Compute the average mark of a picture.
 * 
 * @param int $picture
 * The ID of the picture.
 * 
 * @retval int
 * The average mark, or ERROR_* on failure.
 */
function compute_average_mark(\Demoshot\Demoshot $dm, $picture) {
	assert(is_unsigned($picture));
	
	$db = $dm->get_db();
	
	/*
	 * First, we need to get the number of marks.
	 */
	$query = "SELECT num_of_marks FROM " . TABLE_PICTURE .
		" WHERE ID = $picture";
	
	$result = $db->query($query);
	
	if(!$result) {
		$error = $db->errorInfo();
		
		$dm->set_error(
			ERROR_ON_QUERY,
			$error[2]
		);
		
		return ERROR_ON_QUERY;
	}
	
	$result = $result->fetch(\PDO::FETCH_NUM);
	
	if(!$result) {
		$dm->set_error(
			ERROR_NOT_FOUND,
			"$picture"
		);
		
		return ERROR_NOT_FOUND;
	}
	
	$num_of_marks = (int) $result[0];
	
	if($num_of_marks == 0) {
		return 0;
	}
	
	
	/*
	 * Then, we fetch all the marks.
	 */
	$query = "SELECT SUM(mark) FROM " . TABLE_MARK .
		" WHERE picture_id = $picture";
	
	$result = $db->query($query);
	
	if(!$result) {
		$error = $db->errorInfo();
		
		$dm->set_error(
			ERROR_ON_QUERY,
			$error[2]
		);
		
		return ERROR_ON_QUERY;
	}
	
	$result = $result->fetch(\PDO::FETCH_NUM);
	
	if(!$result) {
		$dm->set_error(
			ERROR_NOT_FOUND,
			"$picture"
		);
		
		return ERROR_NOT_FOUND;
	}
	
	$total_marks = $result[0];
	
	return (int)round($total_marks / $num_of_marks);
}

/*
 * @function create_mark
 * 
 * @purpose
 * Store a new mark in the database.
 * 
 * @param array(int, int) $id
 * $id[0] is the ID of the user.
 * $id[1] is the ID of the picture.
 * 
 * @param int $value
 * It must be >= 0 && <= MARK_MAX_VALUE.
 * 
 * @param string $comment
 * Not longer than `mark_max_comment_length` characters, it can be NULL.
 * 
 * @param bool $private
 * If true, the comment attached (if any) will only be visible to logged in users.
 * 
 * @retval bool
 * True on success, false on failure.
 */
function create_mark(\Demoshot\Demoshot $dm, $id, $value, $comment = NULL, $private = false) {
	assert(is_array($id) && count($id) == 2);
	assert(is_unsigned($id[0]));
	assert(is_unsigned($id[1]));
	assert(is_enum($value, 0, MARK_MAX_VALUE));
	assert(is_null($comment) || is_string($comment));
	assert(is_bool($private));
	
	/*
	 * Build the mark ID, based on the user ID and the picture ID.
	 */
	list($author_id, $picture_id) = $id;
	
	
	// check comment's length
	$conf = $dm->get_conf();
	$conf = $conf['database'];
	
	if(!isset($conf['max_comment_length'])) {
		$dm->set_error(
			ERROR_MISSING_INFORMATION,
			'Demoshot.ini["database"]["max_comment_length"]'
		);
		
		return false;
	}
	
	if(is_string($comment) && strlen($comment) > $conf['max_comment_length']) {
		$dm->set_error(
			ERROR_TEXT_OVERFLOW,
			$comment
		);
		
		return false;
	}
	
	
	$db = $dm->get_db();
	$private = (int) $private;
	
	$query = "INSERT INTO " . TABLE_MARK .
		" (picture_id, author_id, mark, " .
		"comment, private_comment, date) " .
		
		"VALUES ($picture_id, $author_id, $value, " .
		(is_null($comment) ? "NULL" : $db->quote(utf8_encode($comment))) . ", " .
		"$private, NOW())";
	
	$result = $db->exec($query);
	
	if($result === false || $result === 0) {
		$error = $db->errorInfo();
		
		$dm->set_error(
			ERROR_ON_QUERY,
			$error[2]
		);
		
		return false;
	}
	
	
	/*
	 * Update the picture row.
	 */
	$query = "UPDATE " . TABLE_PICTURE .
		" SET num_of_marks = num_of_marks + 1" .
		" WHERE ID = $picture_id";
	
	$db->exec($query);
	
	$average_mark = compute_average_mark($dm, $picture_id);
	
	if($average_mark < 0) {
		$dm->set_error(
			ERROR_OUT_OF_BOUNDS,
			"average_mark ($picture_id)"
		);
		
		return false;
	}
	
	$query = "UPDATE " . TABLE_PICTURE .
		" SET average_mark = $average_mark" .
		" WHERE ID = $picture_id";
	
	$db->exec($query);
	
	
	/*
	 * Update the num_of_marks of the user.
	 */
	$query = "UPDATE " . TABLE_USER .
		" SET num_of_marks = num_of_marks + 1" .
		" WHERE ID = $author_id";
	
	$db->exec($query);
	
	return true;
}

/*
 * @function edit_mark
 * 
 * @purpose
 * Change some of the information related to a mark.
 * E.g. the value and the comment can be modified.
 * 
 * @param array(int, int) $id
 * $id[0] is the ID of the user.
 * $id[1] is the ID of the picture.
 * 
 * @param int $value
 * The new value, or NULL to let the value unchanged.
 * 
 * @param string $comment
 * The new comment, or NULL to let it unchanged, or an empty string to remove it.
 * 
 * @param bool $private
 * True or false, or NULL to let it unchanged.
 * 
 * @retval bool
 * True on success, false on failure.
 */
function edit_mark(\Demoshot\Demoshot $dm, $id, $value = NULL, $comment = NULL, $private = NULL) {
	assert(is_array($id) && count($id) == 2);
	assert(is_unsigned($id[0]));
	assert(is_unsigned($id[1]));
	assert(is_enum($value, 0, 5));
	assert(is_string($comment) || is_null($comment));
	assert(is_bool($private) || is_null($private));
	
	$db = $dm->get_db();
	
	
	/*
	 * Prepare the query.
	 */
	$query = "UPDATE " . TABLE_MARK . " ";
	$no_set = true; // used to check if we must add a comma to the query
	
	
	// check the values to update
	if(is_enum($value, 0, 5)) {
		$query .= $no_set ? ' SET ' : ', ';
		
		$query .= "mark = $value";
		
		$no_set = false;
	}
	
	if(is_bool($private)) {
		$query .= $no_set ? ' SET ' : ', ';
		
		$private = (int) $private;
		
		$query .= "private_comment = $private";
		
		$no_set = false;
	}
	
	if(is_string($comment)) {
		// check the length of the comment
		$conf = $dm->get_conf();
		$conf = $conf['database'];
		
		if(!isset($conf['max_comment_length'])) {
			$dm->set_error(
				ERROR_MISSING_INFORMATION,
				'Demoshot.ini["database"]["max_comment_length"]'
			);
			
			return false;
		}
		
		if(strlen($comment) > (int) $conf['max_comment_length']) {
			$dm->set_error(
				ERROR_TEXT_OVERFLOW,
				$comment
			);
			
			return false;
		}
		
		$query .= $no_set ? ' SET ' : ', ';
		
		if($comment == '') {
			$query .= "comment = NULL";
		} else {
			$query .= "comment = " . $db->quote(utf8_encode($comment));
		}
		
		$no_set = false;
	}
	
	
	if($no_set) {
		return true; // there is nothing to update
	}
	
	
	// update the date
	$query .= ", date = NOW()";
	
	
	list($author_id, $picture_id) = $id;
	$query .= " WHERE author_id = $author_id AND picture_id = $picture_id";
	
	
	$result = $db->exec($query);
	
	if($result === false) {
		$error = $db->errorInfo();
		
		$dm->set_error(
			ERROR_ON_QUERY,
			$error[2]
		);
		
		return false;
	}
	
	/*
	 * Update the average mark of the picture.
	 */
	$average_mark = compute_average_mark($dm, $picture_id);
	
	if($average_mark < 0) {
		$dm->set_error(
			ERROR_OUT_OF_BOUNDS,
			"average_mark ($picture_id)"
		);
		
		return false;
	}
	
	$query = "UPDATE " . TABLE_PICTURE .
		" SET average_mark = $average_mark" .
		" WHERE ID = $picture_id";
	
	$db->exec($query);
	
	return true;
}

/*
 * @function get_mark
 * 
 * @purpose
 * Get the value and the comment of the mark.
 * 
 * @param array(int, int) $id
 * $id[0] is the ID of the user.
 * $id[1] is the ID of the picture.
 * 
 * @retval array(int, string, bool, string)
 * The value, the comment (NULL if no comment), the private-ness, the date.
 * Or NULL on failure.
 */
function get_mark(\Demoshot\Demoshot $dm, $id) {
	assert(is_array($id) && count($id) == 2);
	assert(is_unsigned($id[0]));
	assert(is_unsigned($id[1]));
	
	$db = $dm->get_db();
	
	/*
	 * Build the mark ID, based on the user ID and the picture ID.
	 */
	list($author_id, $picture_id) = $id;
	
	
	/*
	 * Query the database to get the informations.
	 */
	$query = "SELECT mark, comment, private_comment, date FROM " . TABLE_MARK .
		" WHERE picture_id = $picture_id AND author_id = $author_id";
	
	$result = $db->query($query);
	
	if(!$result) {
		$error = $db->errorInfo();
		
		$dm->set_error(
			ERROR_ON_QUERY,
			$error[2]
		);
		
		return NULL;
	}
	
	$result = $result->fetch(\PDO::FETCH_NUM);
	
	if(!$result) {
		$dm->set_error(
			ERROR_NOT_FOUND,
			"$picture_id"
		);
		
		return NULL;
	}
	
	return array(
		GET_MARK_VALUE => (int) $result[0],
		GET_MARK_COMMENT => (is_null($result[1]) ? NULL : utf8_decode($result[1])),
		GET_MARK_PRIVATE => (bool) $result[2],
		GET_MARK_DATE => $result[3]
	);
}

/*
 * @function delete_mark
 * 
 * @purpose
 * Remove the mark from the database.
 * 
 * @param array(int, int) $id
 * $id[0] is the ID of the user.
 * $id[1] is the ID of the picture.
 * 
 * @retval bool
 * False on failure, true on success.
 */
function delete_mark(\Demoshot\Demoshot $dm, $id) {
	assert(is_array($id) && count($id) == 2);
	assert(is_unsigned($id[0]));
	assert(is_unsigned($id[1]));
	
	/*
	 * Build the mark ID, based on the user ID and the picture ID.
	 */
	list($author_id, $picture_id) = $id;
	
	
	$db = $dm->get_db();
	
	$query = "DELETE FROM " . TABLE_MARK .
		" WHERE picture_id = $picture_id" .
		" AND author_id = $author_id";
	
	$result = $db->exec($query);
	
	if($result === false || $result === 0) {
		$error = $db->errorInfo();
		
		$dm->set_error(
			ERROR_ON_QUERY,
			$error[2]
		);
		
		return false;
	}
	
	
	/*
	 * Update the picture row.
	 */
	$query = "UPDATE " . TABLE_PICTURE .
		" SET num_of_marks = num_of_marks - 1" .
		" WHERE ID = $picture_id";
	
	$db->exec($query);
	
	$average_mark = compute_average_mark($dm, $picture_id);
	
	if($average_mark < 0) {
		$dm->set_error(
			ERROR_OUT_OF_BOUNDS,
			"average_mark ($picture_id)"
		);
		
		return false;
	}
	
	$query = "UPDATE " . TABLE_PICTURE .
		" SET average_mark = $average_mark" .
		" WHERE ID = $picture_id";
	
	$db->exec($query);
	
	/*
	 * Update the num_of_marks of the user.
	 */
	$query = "UPDATE " . TABLE_USER .
		" SET num_of_marks = num_of_marks - 1" .
		" WHERE ID = $author_id";
	
	$db->exec($query);
	
	return true;
}


/*
 * CLASSES
 */

/*
 * @class MarkRetriever
 * 
 * @purpose
 * This class can be used in a foreach loop to retrieve the ID of a series of marks.
 * These marks can be retrieved according to their picture or their user.
 */
class MarkRetriever implements \Iterator {
	
	
	/*
	 * MEMBERS
	 */

	/*
	 * @member int $position
	 * The index of the current mark.
	 */
	private $position = 0;
	
	/*
	 * @member array((int, int)) $array
	 * Each case contains the ID of a mark posted by a user or belonging to a picture.
	 * 
	 * E.g, if user 42 marked pictures 3, 7, 6, $array will contain:
	 * $array[0] => array(42, 3)
	 * $array[1] => array(42, 7)
	 * $array[2] => array(42, 6)
	 */
	private $array;
	
	
	/*
	 * SPECIAL METHODS
	 */
	
	/*
	 * @constructor
	 * 
	 * @param int $id
	 * Either the ID of a user to retrieve all the marks he posted,
	 * or the ID of a picture to retrieve all its marks.
	 * NULL to retrieve all existing marks.
	 * 
	 * @param int $table
	 * TABLE_CODE_USER || TABLE_CODE_PICTURE || TABLE_CODE_ALL
	 * Tell if we want to select the marks by user or by picture.
	 * TABLE_CODE_ALL if we set $id to NULL.
	 * 
	 * $param int $sort_by
	 * One of the constants enumerated as MARK_SORT_BY_*.
	 * 
	 * @param bool $sort_asc
	 * If true, sort the marks in ascending order.
	 * Else, sort them in descending order.
	 * 
	 * @param bool $private
	 * If true, search only for private marks.
	 * If false, search only for public marks.
	 * If NULL, search for all the marks.
	 * 
	 * @param string $like
	 * A pattern to look for in the comments, or NULL.
	 * 
	 * @param int $limit
	 * Limit the number of results. -1 for no limit.
	 * 
	 * @param int $start_from
	 * Start from the ?th result. The first result has index 0.
	 */
	public function __construct(

		// the long list of parameters...
		\Demoshot\Demoshot $dm,
		$id = NULL, $table = TABLE_CODE_ALL,
		$sort_by = MARK_SORT_BY_NONE, $sort_asc = true,
		$private = NULL, $like = NULL, $limit = -1, $start_from = -1

	) {
		assert(is_null($id) || is_unsigned($id));
		assert($table === TABLE_CODE_PICTURE || $table === TABLE_CODE_USER || $table === TABLE_CODE_ALL);
		assert(is_enum($sort_by, MARK_SORT_BY_NONE, MARK_SORT_BY_RANDOM));
		assert(is_bool($sort_asc));
		assert(is_null($private) || is_bool($private));
		assert(is_null($like) || is_string($like));
		assert(is_int($limit));
		assert(is_int($start_from) && $start_from >= -1);
		
		$db = $dm->get_db();
		
		$select_by_user = ($table == TABLE_CODE_USER);
		
		$this->position = 0;
		
		
		/*
		 * Retrieve the marks.
		 */
		$query = "SELECT picture_id, author_id FROM " . TABLE_MARK;
		$no_where = true;
		
		if(is_bool($private)) {
			$query .= $no_where ? " WHERE" : " AND";
			
			if($private) {
				$query .= " private_comment = 1";
			} else {
				$query .= " private_comment = 0";
			}
			
			$no_where = false;
		}
		
		
		/*
		 * Search for a pattern in the comment.
		 */
		if(is_string($like)) {
			$query .= $no_where ? " WHERE" : " AND";
			
			$query .= " (comment LIKE " . $db->quote(utf8_encode("%$like%")) . ")";
			
			$no_where = false;
		}
		
		if($table == TABLE_CODE_USER) {
			assert(!is_null($id));
			
			$query .= $no_where ? " WHERE" : " AND";
			
			$query .= " author_id = $id";
			
			$no_where = false;
		} else if($table == TABLE_CODE_PICTURE) {
			assert(!is_null($id));
			
			$query .= $no_where ? " WHERE" : " AND";
			
			$query .= " picture_id = $id";
			
			$no_where = false;
		}
		
		/*
		 * Sort the marks.
		 */
		switch($sort_by) {
			case MARK_SORT_BY_AUTHOR_ID: $sort_field = "author_id"; break;
			case MARK_SORT_BY_DATE: $sort_field = "date"; break;
			case MARK_SORT_BY_PICTURE_ID: $sort_field = "picture_id"; break;
			case MARK_SORT_BY_VALUE: $sort_field = "mark"; break;
			case MARK_SORT_BY_RANDOM: $sort_field = "RAND()"; break;
			default: break;
		}
		
		if(isset($sort_field)) {
			$query .= " ORDER BY " . $sort_field;
			
			$query .= $sort_asc ? " ASC" : " DESC";
		}
		
		/*
		 * This part is specific to MySQL
		 */
		if($start_from >= 0 && $limit <= 0) {
			$query .= " LIMIT $start_from, 0";
		} else if($start_from >= 0 && $limit > 0) {
			$query .= " LIMIT $start_from, $limit";
		} else if($start_from < 0 && $limit > 0) {
			$query .= " LIMIT $limit";
		}
		
		// query the database
		$result = $db->query($query);
		
		if(!$result) {
			$error = $db->errorInfo();
			
			$dm->set_error(
				ERROR_ON_QUERY,
				$error[2]
			);
			
			return;
		}
		
		$result = $result->fetchAll(\PDO::FETCH_NUM);
		
		if(!$result) {
			return;
		}
		
		foreach($result as $id) {
			$this->array[] = array((int) $id[1], (int) $id[0]);
		}
	}
	
	public function rewind() {
		$this->position = 0;
	}
	
	public function current() {
		return $this->array[$this->position];
	}
	
	public function key() {
		return $this->position;
	}
	
	public function next() {
		++$this->position;
	}
	
	public function valid() {
		return isset($this->array[$this->position]);
	}
	
	
	/*
	 * @function length
	 * 
	 * @retval int
	 * The number of items held by the iterator.
	 */
	public function length() {
		return count($this->array);
	}
}

?>
