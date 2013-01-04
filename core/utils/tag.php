<?php namespace Demoshot\Tag;


/*
 * DEPENDENCIES
 */

require_once('misc.php');

require_once('errors.php');
use \Demoshot\Errors;

require_once('user.php');
use \Demoshot\User;

require_once('picture.php');
use \Demoshot\Picture;


/*
 * The following constants help accessing to the informations
 * returned by function get_tag_statistics.
 */
$enumi = 0;
define("GET_TAG_STATS_PICTURES", $enumi++);
define("GET_TAG_STATS_USERS", $enumi++);

/*
 * These constants can be passed to UserRetriever to have
 * the results sorted by a column.
 */
$enumi = 0;
define('TAG_SORT_BY_NONE', $enumi++);
define('TAG_SORT_BY_ID', $enumi++);
define('TAG_SORT_BY_TITLE', $enumi++);
define('TAG_SORT_BY_RANDOM', $enumi++); // random order


/*
 * FUNCTIONS
 */

/*
 * @function create_tag
 * 
 * @purpose
 * Create a new tag in the database's so-called 'tag' table.
 * 
 * @retval int
 * The ID of the newly created tag, or ERROR_SIMPLE on failure.
 */
function create_tag(\Demoshot\Demoshot $dm, $title) {
	assert(is_string($title));
	
	$db = $dm->get_db();
	
	// it's ok, let's create the tag
	$query = 'INSERT INTO ' . TABLE_TAG .
		' (ID, title) VALUES ' .
		"(NULL, " . $db->quote(utf8_encode($title)) . ")";
	
	$result = $db->exec($query);
	
	if($result === false || $result === 0) {
		$error = $db->errorInfo();
		
		$dm->set_error(
			ERROR_ON_QUERY,
			$error[2]
		);
		
		return ERROR_SIMPLE;
	}
	
	return (int) $db->lastInsertId();
}

/*
 * @function delete_tag
 * 
 * @purpose
 * Remove the given tag from the database:
 * - Affect tag table.
 * - Affect picture_tag and user_tag tables if necessary.
 * 
 * @param int $tag
 * The ID of the tag.
 * 
 * @retval bool
 * Return false on failure.
 */
function delete_tag(\Demoshot\Demoshot $dm, $tag) {
	assert(is_unsigned($tag));
	
	$db = $dm->get_db();
	
	// delete all occurrences of this ID in picture_tag and user_tag tables
	$db->exec("DELETE FROM " . TABLE_PICTURE_TAG . " WHERE tag_id = $tag");
	$db->exec("DELETE FROM " . TABLE_USER_TAG . " WHERE tag_id = $tag");
	
	// last but not least, remove the tag from tag table
	$result = $db->exec("DELETE FROM " . TABLE_TAG . " WHERE ID = $tag");
	
	if($result === false || $result === 0) {
		$error = $db->errorInfo();
		
		$dm->set_error(
			ERROR_ON_QUERY,
			$error[2]
		);
		
		return false;
	}
	
	return true;
}

/*
 * @function enumerate_tags
 * 
 * @purpose
 * Return a string containing all the tags of a user/picture separated by commas.
 * This function does the opposite of function split_tags.
 * 
 * @param int $target
 * The ID of the user/picture.
 * 
 * @param int $table
 * TABLE_CODE_USER || TABLE_CODE_PICTURE
 * 
 * @param bool $join
 * If true, all the tags will be joined.
 * If false, they will be returned as an array of strings instead.
 * 
 * @retval mixed
 * The final string or an array of strings if $join == true.
 */
function enumerate_tags(\Demoshot\Demoshot $dm, $target, $table, $join = true) {
	assert(is_unsigned($target));
	assert($table === TABLE_CODE_USER || $table === TABLE_CODE_PICTURE);
	assert(is_bool($join));
	
	$tag_it = new TagRetriever($dm, $target, $table, TAG_SORT_BY_TITLE);
	
	$titles = array();
	foreach($tag_it as $tag_id) {
		$titles[] = strtolower(get_tag_title($dm, $tag_id));
	}
	
	if(!$join) {
		return $titles;
	}
	
	return join(', ', $titles);
}

/*
 * @function get_tag_id
 * 
 * @purpose
 * Get the ID of the tag registered in the database.
 * 
 * @retval int
 * The ID of the tag, or ERROR_NOT_FOUND if the tag couldn't be found.
 */
function get_tag_id(\Demoshot\Demoshot $dm, $title) {
	assert(is_string($title));
	
	$db = $dm->get_db();
	
	$query = "SELECT ID FROM " . TABLE_TAG . " WHERE title = " . $db->quote(utf8_encode($title));
	
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
			$title
		);
		
		return ERROR_NOT_FOUND;
	}
	
	return (int) $result[0];
}

/*
 * @function get_tag_statistics
 * 
 * @purpose
 * Get the number of users following a tag
 * and the number of pictures described with this tag.
 * 
 * @retval array(int, int)
 * GET_TAG_STATS_PICTURES => the number of pictures
 * GET_TAG_STATS_USERS => the number of users
 * Or NULL on failure.
 */
function get_tag_statistics(\Demoshot\Demoshot $dm, $id) {
	assert(is_unsigned($id));
	
	$db = $dm->get_db();
	
	
	// get the number of pictures
	$query = "SELECT COUNT(picture_id) FROM " . TABLE_PICTURE_TAG;
	$query .= " WHERE tag_id = $id";
	
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
		$num_of_pictures = 0;
	} else {
		$num_of_pictures = (int) $result[0];
	}
	
	
	// get the number of users
	$query = "SELECT COUNT(user_id) FROM " . TABLE_USER_TAG;
	$query .= " WHERE tag_id = $id";
	
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
		$num_of_users = 0;
	} else {
		$num_of_users = (int) $result[0];
	}
	
	return array(
		GET_TAG_STATS_PICTURES => $num_of_pictures,
		GET_TAG_STATS_USERS => $num_of_users
	);
}

/*
 * @function get_tag_title
 * 
 * @retval string
 * The title of the tag, or NULL on failure.
 */
function get_tag_title(\Demoshot\Demoshot $dm, $id) {
	assert(is_unsigned($id));
	
	$db = $dm->get_db();
	
	$query = "SELECT title FROM " . TABLE_TAG . " WHERE ID = $id";
	
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
			$id
		);
		
		return NULL;
	}
	
	return htmlspecialchars(utf8_decode($result[0]), ENT_QUOTES | ENT_HTML5);
}

/*
 * @function link_tag
 * 
 * @purpose
 * Connect a user or a picture to a tag.
 * 
 * @param int $tag
 * The ID of the tag.
 * 
 * @param int $target
 * The ID of the target.
 * 
 * @param int $table
 * TABLE_CODE_PICTURE || TABLE_CODE_USER
 * 
 * @retval bool
 * True on success, false on failure.
 */
function link_tag(\Demoshot\Demoshot $dm, $tag, $target, $table) {
	assert(is_unsigned($tag));
	assert(is_unsigned($target));
	assert(is_enum($table, TABLE_CODE_PICTURE, TABLE_CODE_USER));
	
	$db = $dm->get_db();
	
	$table_label = ($table == TABLE_CODE_PICTURE ? TABLE_PICTURE_TAG : TABLE_USER_TAG);
	$target_label = ($table == TABLE_CODE_PICTURE ? "picture_id" : "user_id");
	
	$query = "INSERT INTO $table_label " .
		"(tag_id, $target_label) VALUES " .
		"($tag, $target)";
	
	$result = $db->exec($query);
	
	if($result === false || $result === 0) {
		$error = $db->errorInfo();
		
		$dm->set_error(
			ERROR_ON_QUERY,
			$error[2]
		);
		
		return false;
	}
	
	return true;
}

/*
 * @function split_tags
 * 
 * @purpose
 * Split a string into an array of tags,
 * take care of eliminating twins or empty tags.
 * This function does the opposite of function enumerate_tags.
 * 
 * @retval array(string, ...)
 * An array of strings.
 */
function split_tags($str) {
	assert(is_string($str));
	
	/*
	 * The needle must filter tag words separated by commas,
	 * with any number of spaces before or after them.
	 */
	
	$needle = "/[\s]{0,}[,]+[\s]{0,}/";
	
	$tmp_tags = preg_split($needle, $str);
	
	/*
	 * Remove all possible errors and remove the twins if any,
	 * e.g. "dance, , pizza" will result in having an empty tag between "dance" and "pizza",
	 * "dance, pizza, dance" will result in having two "dance" tags.
	 */
	$tags = array();
	
	foreach($tmp_tags as $tag_str) {
		if($tag_str == '') {
			continue;
		}
		
		if(in_array($tag_str, $tags)) {
			continue;
		}
		
		$tags[] = $tag_str;
	}
	
	return $tags;
}

/*
 * @function unlink_tag
 * 
 * @purpose
 * Disconnect a user or a picture from a tag.
 * Delete the tag if it is no longer used by any picture and user.
 * 
 * @param int $target
 * The ID of the target.
 * 
 * @param int $table
 * TABLE_CODE_PICTURE || TABLE_CODE_USER
 * 
 * @param int $tag
 * The ID of the tag.
 * If NULL, all connections to the user/picture will be undone.
 * E.g: a picture has been deleted, all its tags must be disconnected.
 * 
 * @side effect
 * If the tag ends up being connected to 0 picture and 0 user, it will be removed.
 * 
 * @retval int
 * The number of removed connections,
 * or ERROR_* if it failed.
 */
function unlink_tag(\Demoshot\Demoshot $dm, $target, $table, $tag = NULL) {
	assert(is_unsigned($target));
	assert(is_enum($table, TABLE_CODE_PICTURE, TABLE_CODE_USER));
	assert(is_null($tag) || is_unsigned($tag));
	
	$db = $dm->get_db();
	
	$table_label = ($table == TABLE_CODE_PICTURE ? TABLE_PICTURE_TAG : TABLE_USER_TAG);
	$target_label = ($table == TABLE_CODE_PICTURE ? "picture_id" : "user_id");
	
	$return_val = 0;
	
	if(is_null($tag)) { // all tags connected to the target will be disconnected
	
		/*
		 * Before removing anything, we need to get the list of all tags connected to the target
		 * so that we can remove them if they are no longer linked to anything.
		 */
		$query = "SELECT tag_id FROM $table_label WHERE $target_label = $target";
		
		$result = $db->query($query);
		
		if(!$result) {
			$error = $db->errorInfo();
			
			$dm->set_error(
				ERROR_ON_QUERY,
				$error[2]
			);
			
			return ERROR_ON_QUERY;
		}
		
		$result = $result->fetchAll(\PDO::FETCH_NUM);
		
		// if the user/picture is linked to zero tags, simply return 0
		if(!$result) {
			return 0;
		}
		
		/*
		 * We need to remember the tags after we have unlinked them,
		 * in order to check if they are still used or need to be deleted.
		 */
		$tags = array();
		foreach($result as $tag) {
			$tags[] = (int) $tag[0];
		}
		
		
		$query = "DELETE FROM $table_label WHERE $target_label = $target";
		
		if($db->exec($query) === false) {
			$error = $db->errorInfo();
			
			$dm->set_error(
				ERROR_ON_QUERY,
				$error[2]
			);
			
			return ERROR_ON_QUERY;
		}
		
		foreach($tags as $tag) {
			// delete the tag if it is no longer used
			list($num_of_pictures, $num_of_users) = get_tag_statistics($dm, $tag);
			if($num_of_pictures == 0 && $num_of_users == 0) {
				delete_tag($dm, $tag);
			}
		}
		
		
		$return_val = count($tags);
		
	} else {
		// disconnect the tag
		$query = "DELETE FROM $table_label WHERE " .
			"$target_label = $target AND tag_id = $tag";
		
		if($db->exec($query) === false) {
			$error = $db->errorInfo();
			
			$dm->set_error(
				ERROR_ON_QUERY,
				$error[2]
			);
			
			return ERROR_ON_QUERY;
		}
				
		// delete the tag if it is no longer used
		list($num_of_pictures, $num_of_users) = get_tag_statistics($dm, $tag);
		if($num_of_pictures == 0 && $num_of_users == 0) {
			delete_tag($dm, $tag);
		}
		
		$return_val = 1;
		
	}
	
	return $return_val;
}


/*
 * CLASSES
 */

/*
 * @class TagRetriever
 * 
 * @purpose
 * This class can be used in a foreach loop to retrieve the ID of a series of tags.
 * These marks can be retrieved according to a picture or a user.
 */
class TagRetriever implements \Iterator {
	
	
	/*
	 * MEMBERS
	 */

	/*
	 * @member int $position
	 * The index of the current tag.
	 */
	private $position = 0;
	
	/*
	 * @member array(int) $array
	 * Each case contains the ID of a tag.
	 */
	private $array;
	
	
	/*
	 * SPECIAL METHODS
	 */
	
	/*
	 * @constructor
	 * 
	 * @param int $id
	 * Either the ID of a user or the ID of a picture to retrieve all its tags.
	 * NULL to retrieve all existing tags.
	 * 
	 * @param int $table
	 * TABLE_CODE_USER || TABLE_CODE_PICTURE || TABLE_CODE_ALL
	 * Tell if we want to select the tags by user or by picture.
	 * TABLE_CODE_ALL if we set $id to NULL.
	 * 
	 * $param int $sort_by
	 * One of the constants enumerated as TAG_SORT_BY_*.
	 * 
	 * @param bool $sort_asc
	 * If true, sort the tags in ascending order.
	 * Else, sort them in descending order.
	 * 
	 * @param string $like
	 * A pattern to look for in the titles, or NULL.
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
		$sort_by = TAG_SORT_BY_NONE, $sort_asc = true,
		$like = NULL, $limit = -1, $start_from = -1

	) {
		assert(is_null($id) || is_unsigned($id));
		assert($table === TABLE_CODE_PICTURE || $table === TABLE_CODE_USER || $table === TABLE_CODE_ALL);
		assert(is_enum($sort_by, MARK_SORT_BY_NONE, MARK_SORT_BY_DATE));
		assert(is_bool($sort_asc));
		assert(is_null($like) || is_string($like));
		assert(is_int($limit));
		assert(is_int($start_from) && ($start_from > $limit || $start_from == -1));
		
		$db = $dm->get_db();
		
		$this->position = 0;
		
		
		/*
		 * Retrieve the tags
		 */
		$query = "SELECT ID FROM " . TABLE_TAG;
		
		
		/*
		 * Search for a pattern in the title.
		 */	
		if(is_string($like)) {
			$query .= " WHERE (title LIKE '%$like%')";
		}
		
		
		if($table === TABLE_CODE_USER) {
			assert(!is_null($id));
			
			$query .= " INNER JOIN " . TABLE_USER_TAG .
				" ON " . TABLE_USER_TAG . ".tag_id = " . TABLE_TAG . ".ID" .
				" AND " . TABLE_USER_TAG . ".user_id = $id";
			
		} else if($table === TABLE_CODE_PICTURE) {
			assert(!is_null($id));
			
			$query .= " INNER JOIN " . TABLE_PICTURE_TAG .
				" ON " . TABLE_PICTURE_TAG . ".tag_id = " . TABLE_TAG . ".ID" .
				" AND " . TABLE_PICTURE_TAG . ".picture_id = $id";
			
		}
		
		
		/*
		 * Sort the tags.
		 */
		switch($sort_by) {
			case TAG_SORT_BY_ID: $sort_field = "ID"; break;
			case TAG_SORT_BY_TITLE: $sort_field = "title"; break;
			case TAG_SORT_BY_RANDOM: $sort_field = "RAND()"; break;
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
			$this->array[] = (int) $id[0];
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
