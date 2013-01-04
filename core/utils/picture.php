<?php namespace Demoshot\Picture;


/*
 * DEPENDENCIES
 */

require_once('misc.php');

require_once('errors.php');
use \Demoshot\Errors;

require_once('database.php');
use \Demoshot\Database;

require_once('user.php');
use \Demoshot\User;

require_once('tag.php');
use \Demoshot\Tag;

require_once('mark.php');
use \Demoshot\Mark;


/*
 * CONSTANTS
 */

define('PICTURE_THUMBNAIL_POSTFIX', 'tn'); // image.png => image.png.tn
define('PICTURE_THUMBNAIL_HEIGHT', 180);
define('PICTURE_THUMBNAIL_WIDTH', 260);

$enumi = 0;
define('PICTURE_TYPE_PNG', $enumi++);
define('PICTURE_TYPE_JPG', $enumi++);

/*
 * The following constants help accessing to the informations
 * returned by function get_picture.
 */
$enumi = 0;
define('GET_PICTURE_ID', $enumi++);
define('GET_PICTURE_TITLE', $enumi++);
define('GET_PICTURE_DESCRIPTION', $enumi++);
define('GET_PICTURE_AUTHOR', $enumi++);
define('GET_PICTURE_AVERAGE_MARK', $enumi++);
define('GET_PICTURE_NUM_OF_MARKS', $enumi++);
define('GET_PICTURE_PRIVATE', $enumi++);
define('GET_PICTURE_DATE', $enumi++);
define('GET_PICTURE_LICENSE', $enumi++);

/*
 * These constants can be passed to PictureRetriever to have
 * the results sorted by a column.
 */
$enumi = 0;
define('PICTURE_SORT_BY_NONE', $enumi++);
define('PICTURE_SORT_BY_ID', $enumi++);
define('PICTURE_SORT_BY_AUTHOR_ID', $enumi++);
define('PICTURE_SORT_BY_TITLE', $enumi++);
define('PICTURE_SORT_BY_AV_MARK', $enumi++);
define('PICTURE_SORT_BY_NUM_OF_MARKS', $enumi++);
define('PICTURE_SORT_BY_RELEVANCE', $enumi++); // = sort by average mark, then by num_of_marks
define('PICTURE_SORT_BY_RANDOM', $enumi++); // random order
define('PICTURE_SORT_BY_MARK_DATE', $enumi++); // sort by the date of mark, when searching by mark author
define('PICTURE_SORT_BY_DATE', $enumi++);


/*
 * The following constants can be passed to PictureRetriever to limit the results.
 */
$enumi = 0;
define("RETRIEVE_PICTURES_BY_TAG", $enumi++);
define("RETRIEVE_PICTURES_BY_AUTHOR", $enumi++);
define("RETRIEVE_PICTURES_BY_MARK_AUTHOR", $enumi++);
define("RETRIEVE_ALL_PICTURES", $enumi++);


/*
 * FUNCTIONS
 */

/*
 * @function create_picture
 * 
 * @purpose
 * Store information about a new picture in the database.
 * 
 * @param string $title
 * The title of the picture.
 * 
 * @param int $author
 * The ID of the author.
 * 
 * @param bool $private
 * If true, the picture can only be viewed by logged in users.
 * 
 * @param int $type
 * PICTURE_TYPE_PNG || PICTURE_TYPE_JPG
 * 
 * @retval int
 * An error code < 0 on failure, or the ID of the newly created picture.
 */
function create_picture(

	// the long list of parameters...
	\Demoshot\Demoshot $dm,
	$title, $author,
	$private,
	$type,
	$description = NULL, $license = NULL
	
) {
	assert(is_string($title));
	assert(is_unsigned($author));
	assert(is_bool($private));
	assert(is_enum($type, PICTURE_TYPE_PNG, PICTURE_TYPE_JPG));
	assert(is_null($description) || is_string($description));
	assert(is_null($license) || is_string($license));
	
	$db = $dm->get_db();
	
	/*
	 * Get the max length for a description or license text.
	 */
	$conf = $dm->get_conf();
	$conf = $conf['database'];
	
	if(!isset($conf['max_description_length'])) {
		$dm->set_error(
				ERROR_MISSING_INFORMATION,
				'Demoshot.ini["database"]["max_description_length"]'
		);
		
		return ERROR_MISSING_INFORMATION;
	}
	$max_description_length = (int) $conf['max_description_length'];
	
	
	/*
	 * Check the values.
	 */
	if(is_string($description)) {
		// check the length of the description
		if(strlen($description) > $max_description_length) {
			$dm->set_error(
				ERROR_TEXT_OVERFLOW,
				$description
			);
			
			return ERROR_TEXT_OVERFLOW;
		}
		
		$description = $db->quote(utf8_encode($description));
	}
	
	if(is_string($license)) {
		// check the length of the license
		if(strlen($license) > $max_description_length) {
			$dm->set_error(
				ERROR_TEXT_OVERFLOW,
				$license
			);
			
			return ERROR_TEXT_OVERFLOW;
		}
		
		$license = $db->quote(utf8_encode($license));
	}
	
	$private = (int) $private;
	
	$query = 'INSERT INTO ' . TABLE_PICTURE .
		'(id, title, ' .
		'description, ' .
		'author_id, average_mark, num_of_marks, ' .
		'private, date, ' .
		'license, type) VALUES ' .
		
		"(NULL, " . $db->quote(utf8_encode($title)) . ', ' .
		(is_null($description) ? "NULL" : "$description") . ', ' .
		"$author, 0, 0, " .
		"$private, NOW(), " .
		(is_null($license) ? "NULL" : "$license") . ", $type)";
	
	$result = $db->exec($query);
	
	if($result === false || $result === 0) {
		$error = $db->errorInfo();
		
		$dm->set_error(
			ERROR_ON_QUERY,
			$error[2]
		);
		
		return ERROR_ON_QUERY;
	}
	
	$new_picture_id = (int) $db->lastInsertId();
	
	/*
	 * Update the num_of_pictures of the user.
	 */
	$query = "UPDATE " . TABLE_USER .
		" SET num_of_pictures = num_of_pictures + 1" .
		" WHERE ID = $author";
	
	$db->exec($query);
	
	return $new_picture_id;
}

/*
 * @function create_thumbnail
 * 
 * @purpose
 * Create a thumbnail of the picture and save it in the same directory.
 * 
 * @param int $picture
 * The ID of the picture.
 * 
 * @param bool $keep_ratio
 * If true, reduce the width or the height of the thumbnail
 * to preserve the aspect ratio of the original picture.
 * 
 * @retval bool
 * True on success, false on failure.
 */
function create_thumbnail(\Demoshot\Demoshot $dm, $picture, $keep_ratio = true) {
	assert(is_unsigned($picture));
	
	$db = $dm->get_db();
	
	// get the type of the picture (PNG, JPG)
	$type = get_picture_type($dm, $picture);
	
	if($type < 0) {
		return false;
	}
	
	// get the path to the picture
	$path = get_picture_path($dm, $picture);
	
	if(!is_file($path)) {
		$dm->set_error(
			ERROR_NO_FILE,
			$path
		);
		
		return false;
	}
	
	// build the path to the thumbnail to create
	$tn_path = get_thumbnail_path($dm, $path);
	
	if(is_null($tn_path)) {
		return false;
	}
	
	// if the thumbnail already exists, delete it
	if(file_exists($tn_path)) {
		unlink($tn_path);
	}
	
	// retrieve the width of the thumbnail, compute its height
	$tn_width = PICTURE_THUMBNAIL_WIDTH;
	
	list($width, $height) = getimagesize($path);
	
	$tn_height = PICTURE_THUMBNAIL_HEIGHT;
	
	// create the thumbnail bitmap
	$tn = imagecreatetruecolor($tn_width, $tn_height);
	
	$source = NULL;
	
	switch($type) {
		case PICTURE_TYPE_JPG:
			$source = imagecreatefromjpeg($path);
			break;
		
		case PICTURE_TYPE_PNG:
			$source = imagecreatefrompng($path);
			break;
	}
	
	
	/*
	 * Conserve transparency in case the original image is a PNG.
	 */
	imagesavealpha($tn, true);
	$transparent = imagecolorallocatealpha($tn, 0, 0, 0, 127);
	imagefill($tn, 0, 0, $transparent);
	
	/*
	 * Choose the destination coordinates.
	 */
	$dst_x = 0; $dst_y = 0;
	$dst_w = $tn_width; $dst_h = $tn_height;
	
	if($keep_ratio) {
		/*
		 * If the image's height is greater than its width,
		 * we preserve the ratio by reducing its width.
		 * 
		 * If the image's width is greater than its height,
		 * we preserve the ratio by reducing its height.
		 */
		if($height > $width) {
			$ratio = $dst_h / $height;
			
			$dst_w = $ratio * $width;
			$dst_x = ($tn_width - $dst_w) / 2;
			
		} else if($width > $height) {
			$ratio = $dst_w / $width;
			
			$dst_h = $ratio * $height;
			$dst_y = ($tn_height - $dst_h) / 2;
		}
	}
	
	
	if(!imagecopyresampled(
		$tn, $source, $dst_x, $dst_y, 0, 0,
		$dst_w, $dst_h, $width, $height)
	) {
		$dm->set_error(
			ERROR_SIMPLE,
			"imagecopyresampled($tn, $source, 0, 0, 0, 0, $tn_width, $tn_height, $width, $height)"
		);
		
		return false;
	}
	
	
	$conf = $dm->get_conf();
	$conf = $conf['image'];
	
	if(!isset($conf['thumbnail_compression_level'])) {
		$dm->set_error(
			ERROR_MISSING_INFORMATION,
			'Demoshot.ini["image"]["thumbnail_compression_level"]'
		);
		
		return false;
	}
	
	$compression_level = (int) $conf['thumbnail_compression_level'];
	
	// the compression level must be comprised between 0 and 9
	if(!is_enum($compression_level, 0, 9)) {
		$compression_level = 5;
	}
	
	
	// save the thumbnail
	if(!imagepng($tn, $tn_path, $compression_level)) {
		$dm->set_error(
			ERROR_SIMPLE,
			"imagepng($tn, $tn_path, $compression_level)"
		);
		
		return false;
	}
	
	return true;
}

/*
 * @function delete_picture
 * 
 * @purpose
 * Remove the picture file and its thumbnail.
 * Remove the picture from the database.
 * Remove all occurrences of the picture's ID in picture_tag and mark tables.
 * 
 * @param int $picture
 * The ID of the picture.
 * 
 * @retval bool
 * True on success, false on failure.
 */
function delete_picture(\Demoshot\Demoshot $dm, $picture) {
	assert(is_unsigned($picture));
	
	$db = $dm->get_db();
	
	
	/*
	 * First, we unlink the picture to all its tags.
	 */
	if(Tag\unlink_tag($dm, $picture, TABLE_CODE_PICTURE) < 0) {
		return false;
	}
	
	
	/*
	 * Second, we remove the picture file and its thumbnail.
	 */
	$path = get_picture_path($dm, $picture);
	
	if(is_null($path)) {
		return false;
	} else {
		unlink($path);
		unlink(get_thumbnail_path($dm, $path));
	}
	
	
	/*
	 * Third, we remove all its marks.
	 */
	$mark_retr = new Mark\MarkRetriever($dm, $picture, TABLE_CODE_PICTURE);
	
	foreach($mark_retr as $mark) {
		Mark\delete_mark($dm, $mark);
	}
	
	
	/*
	 * Fourth, we update the num_of_pictures of the user.
	 */
	$author = get_picture_author($dm, $picture);
	
	$query = "UPDATE " . TABLE_USER .
		" SET num_of_pictures = num_of_pictures - 1" .
		" WHERE ID = $author";
	
	$db->exec($query);
	
	
	/*
	 * Last but not least, we remove the picture from the database.
	 */
	$query = "DELETE FROM " . TABLE_PICTURE .
		" WHERE ID = $picture";
	
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
 * @function edit_picture
 * 
 * @purpose
 * Change some of the information related to the picture.
 * E.g. the description and the title can be modified.
 * 
 * @param string $title
 * The new title, or NULL to let it unchanged.
 * 
 * @param string $description
 * The new description, or NULL to let it unchanged.
 * 
 * @param bool $private
 * True or false, or NULL to let it unchanged.
 * 
 * @param string $license
 * The new license, or NULL to let it unchanged.
 * 
 * @retval bool
 * True on success, false on failure.
 */
function edit_picture(

	// the long list of parameters...
	\Demoshot\Demoshot $dm, $id,
	$title = NULL, $description = NULL,
	$private = NULL, $license = NULL

) {
	assert(is_unsigned($id));
	assert(is_null($title) || is_string($title));
	assert(is_null($description) || is_string($description));
	assert(is_null($private) || is_bool($private));
	assert(is_null($license) || is_string($license));
	
	$db = $dm->get_db();
	
	
	/*
	 * Get the max length for a description or license text.
	 */
	$conf = $dm->get_conf();
	$conf = $conf['database'];
	
	if(!isset($conf['max_description_length'])) {
		$dm->set_error(
				ERROR_MISSING_INFORMATION,
				'Demoshot.ini["database"]["max_description_length"]'
		);
		
		return false;
	}
	$max_description_length = (int) $conf['max_description_length'];
	
	
	/*
	 * Prepare the query.
	 */
	$query = "UPDATE " . TABLE_PICTURE . " ";
	$no_set = true; // used to check if we must add a comma to the query
	
	
	/*
	 * Check the values to update.
	 */
	if(is_string($title)) {
		$title = $db->quote(utf8_encode($title));
		
		$query .= "SET title = $title";
		
		$no_set = false;
	}
	
	if(is_string($description)) {
		if(strlen($description) > $max_description_length) {
			$dm->set_error(
				ERROR_TEXT_OVERFLOW,
				$description
			);
			
			return false;
		}
		
		$description = $db->quote(utf8_encode($description));
		
		$query .= $no_set ? ' SET ' : ', ';
		
		$query .= "description = $description";
		
		$no_set = false;
	}
	
	if(is_string($license)) {
		if(strlen($license) > $max_description_length) {
			$dm->set_error(
				ERROR_TEXT_OVERFLOW,
				$license
			);
			
			return false;
		}
		
		$license = $db->quote(utf8_encode($license));
		
		$query .= $no_set ? ' SET ' : ', ';
		
		$query .= "license = $license";
		
		$no_set = false;
	}
	
	if(is_bool($private)) {
		$private = (int) $private;
		
		$query .= $no_set ? ' SET ' : ', ';
		
		$query .= "private = $private";
		
		$no_set = false;
	}
	
	$query .= " WHERE ID = $id";
	
	
	/*
	 * Last but not least, execute the query.
	 */
	if($no_set) {
		// strange enough, there is nothing to update
		return true;
	}
	
	$result = $db->exec($query);
	
	if($result === false) {
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
 * @function get_picture
 * 
 * @purpose
 * Get the information related to a picture.
 * 
 * @param int $id
 * The ID of the picture.
 * 
 * @retval array(string, string, int, int, int, bool, string, string)
 * The title, the description (can be NULL), the ID of the author, the average mark,
 * the number of marks, the private-ness, the date and the license (can be NULL) of the picture.
 * Or NULL on failure.
 */
function get_picture(\Demoshot\Demoshot $dm, $id) {
	assert(is_unsigned($id));
	
	$db = $dm->get_db();
	
	$query = "SELECT title, description, author_id," .
		" average_mark, num_of_marks, private, date, license" .
		" FROM " . TABLE_PICTURE .
		" WHERE ID = $id";
	
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
			"$id"
		);
		
		return NULL;
	}
	
	return array(
		GET_PICTURE_ID => $id,
		GET_PICTURE_TITLE => htmlspecialchars(utf8_decode($result[0]), ENT_QUOTES | ENT_HTML5),
		GET_PICTURE_DESCRIPTION => ($result[1] === NULL ? NULL : htmlspecialchars(utf8_decode($result[1]), ENT_QUOTES | ENT_HTML5)),
		GET_PICTURE_AUTHOR => (int) $result[2],
		GET_PICTURE_AVERAGE_MARK => (int) $result[3],
		GET_PICTURE_NUM_OF_MARKS => (int) $result[4],
		GET_PICTURE_PRIVATE => (bool) $result[5],
		GET_PICTURE_DATE => $result[6],
		GET_PICTURE_LICENSE => ($result[7] === NULL ? NULL : htmlspecialchars(utf8_decode($result[7]), ENT_QUOTES | ENT_HTML5))
	);
}

/*
 * @function get_picture_author
 * 
 * @retval int
 * The ID of the author of the picture,
 * or ERROR_* on failure.
 */
function get_picture_author(\Demoshot\Demoshot $dm, $id) {
	assert(is_unsigned($id));
	
	$db = $dm->get_db();
	
	$query = "SELECT author_id FROM " . TABLE_PICTURE .
		" WHERE ID = $id";
	
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
			$id
		);
		
		return ERROR_NOT_FOUND;
	}
	
	return (int) $result[0];
}

/*
 * @function get_picture_id
 * 
 * @purpose
 * Get the ID of the picture registered in the database.
 * 
 * @retval int
 * The ID of the picture, or ERROR_NOT_FOUND if the picture couldn't be found.
 */
function get_picture_id(\Demoshot\Demoshot $dm, $title) {
	assert(is_string($title));
	
	$db = $dm->get_db();
	
	$query = "SELECT ID FROM " . TABLE_PICTURE . " WHERE title = " . $db->quote(utf8_encode($title));
	
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
 * @function get_max_size
 * 
 * @retval int
 * The maximum size of an image (bytes).
 */
function get_max_size(\Demoshot\Demoshot $dm) {
	$conf = $dm->get_conf();
	$conf = $conf['image'];
	
	$post_max_size = 1000000 * (int) ini_get('post_max_size');
	$upload_max_filesize = 1000000 * (int) ini_get('upload_max_filesize');
	$minimum_size = min($post_max_size, $upload_max_filesize);
	
	if(!isset($conf['max_size'])) {
		return $minimum_size;
	}
	
	$max_size = 1000000 * (int) $conf['max_size'];
	
	/*
	 * If the maximum size of an image is greater than
	 * the maximum upload size, then the maximum size is lowered.
	 */
	
	if($max_size > $minimum_size) {
		return $minimum_size;
	} else {
		return $max_size;
	}
}

/*
 * @function get_number_of_pictures
 * 
 * @purpose
 * Get the number of pictures satisfying the provided criteria.
 * This function takes the same parameters as PictureRetriever,
 * except for $limit, $start_from, $sort_by and $sort_asc.
 * For explanations on the use of these parameters, see PictureRetriever.
 * 
 * This function is used for pagination in the search results page,
 * when we need to know how many results we would get if we didn't limit
 * the number of results per page.
 * 
 * For users, there is the function get_number_of_users.
 * 
 * @retval int
 * The number of results, or ERROR_ on failure.
 */
function get_number_of_pictures(

	\Demoshot\Demoshot $dm,
	$id = NULL, $retrieve = RETRIEVE_ALL_PICTURES,
	$private = NULL, $like = NULL, $description_like = NULL,
	$join_tags = false, $join_likes = false

) {
	assert(is_null($id) || is_unsigned($id) || is_array($id));
	assert(is_enum($retrieve, RETRIEVE_PICTURES_BY_TAG, RETRIEVE_ALL_PICTURES));
	assert(is_null($private) || is_bool($private));
	assert(is_null($like) || is_string($like));
	assert(is_null($description_like) || is_string($description_like));
	assert(is_bool($join_tags));
	assert(is_bool($join_likes));
	
	$db = $dm->get_db();
	
	/*
	 * Retrieve the number of pictures.
	 */
	$query = "SELECT COUNT(DISTINCT(ID)) FROM " . TABLE_PICTURE;
	$no_where = true;
	
	
	switch($retrieve) {
		case RETRIEVE_PICTURES_BY_AUTHOR:
			assert(is_unsigned($id));
			
			$query .= $no_where ? " WHERE" : " AND";
			
			$query .= " author_id = $id";
			
			$no_where = false;
			break;
		
		case RETRIEVE_PICTURES_BY_TAG:
			assert(is_unsigned($id) || is_array($id));
			
			if(is_array($id)) {
				
				if($join_tags) {
					/*
					 * Select the pictures related to all the tags.
					 * To do so, we need to do as many joins on the picture_tag table
					 * as there are different tags in the array $id.
					 * 
					 * E.g: we want to retrieve the pictures related to tags 1, 3 and 42.
					 * The query will look like this:
					 * 
					 * SELECT ID from picture
					 *      INNER JOIN picture_tag tag1 ON tag1.picture_id = picture.id
					 *      INNER JOIN picture_tag tag2 ON tag2.picture_id = picture.id
					 *      INNER JOIN picture_tag tag3 ON tag3.picture_id = picture.id
					 *      WHERE tag1.tag_id = 1 AND tag2.tag_id = 3 AND tag3.tag_id = 42;
					 */
					
					// first, we add the INNER JOIN bits
					for($it = 0, $c = count($id); $it < $c; $it++) {
						assert(is_unsigned($id[$it]));
						
						$query .= " INNER JOIN " . TABLE_PICTURE_TAG . " tag$it" .
							" ON tag$it.picture_id = " . TABLE_PICTURE . ".ID";
					}
					
					$query .= " WHERE ";
					
					// second, we add the WHERE...AND clause
					for($it = 0, $c = count($id); $it < $c; $it++) {
						if($it > 0) {
							$query .= " AND ";
						}
						
						$query .= "tag$it.tag_id = $id[$it]";
					}
					
					$no_where = false;
					
				} else {
					/*
					 * Select the pictures related to one of the tags.
					 */
					
					$list = join(', ', $id);
					
					$query .= " INNER JOIN " . TABLE_PICTURE_TAG .
						" ON " . TABLE_PICTURE_TAG . ".picture_id = " . TABLE_PICTURE . ".ID AND " .
						TABLE_PICTURE_TAG . ".tag_id IN($list)";
				}
				
			} else {
				/*
				 * Only one tag is required.
				 */
				
				$query .= " INNER JOIN " . TABLE_PICTURE_TAG .
					" ON " . TABLE_PICTURE_TAG . ".picture_id = " . TABLE_PICTURE . ".ID" .
					" AND " . TABLE_PICTURE_TAG . ".tag_id = $id";
			}
			break;
		
		case RETRIEVE_PICTURES_BY_MARK_AUTHOR:
			/*
			 * Select the pictures marked by a user.
			 */
			
			$query .= " INNER JOIN " . TABLE_MARK .
				" ON " . TABLE_MARK . ".picture_id = " . TABLE_PICTURE . ".ID" .
				" AND " . TABLE_MARK . ".author_id = $id";
			break;
	}
	
	
	if(is_bool($private)) {
		$query .= $no_where ? " WHERE" : " AND";
		
		if($private) {
			$query .= " private = 1";
		} else {
			$query .= " private = 0";
		}
		
		$no_where = false;
	}
	
	
	/*
	 * Search for a pattern in the title and/or in the description.
	 */		
	if(is_string($like)) {
		$query .= $no_where ? " WHERE" : " AND";
		
		$query .= " (title LIKE " . $db->quote(utf8_encode("%$like%"));
		
		if(is_string($description_like)) {
			if($join_likes) {
				$query .= " AND ";
			} else {
				$query .= " OR ";
			}
			
			$query .= "description LIKE " . $db->quote(utf8_encode("%$description_like%"));
		}
		
		$query .= ")";
		
		$no_where = false;
	} else if(is_string($description_like)) {
		$query .= $no_where ? " WHERE" : " AND";
		
		$query .= " (description LIKE " . $db->quote(utf8_encode("%$description_like%")) . ")";
		
		$no_where = false;
	}
	
	
	// query the database
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
		return 0;
	} else {
		return (int) $result[0];
	}
}

/*
 * @function get_picture_path
 * 
 * @purpose
 * Return the path to the picture.
 * 
 * @param int $picture
 * The ID of the picture.
 * 
 * @retval string
 * The path. Or NULL on failure.
 */
function get_picture_path(\Demoshot\Demoshot $dm, $picture) {
	assert(is_unsigned($picture));
	
	$db = $dm->get_db();
	
	// we need to get the type of the picture (PNG or JPG)
	$type = get_picture_type($dm, $picture);
	
	if($type < 0) {
		return NULL;
	}
	
	$extension = ($type == PICTURE_TYPE_PNG ? ".png" : ".jpg");
	
	// we need to get the ID of the author of the picture
	$query = "SELECT author_id FROM " . TABLE_PICTURE . " WHERE ID = $picture";
	
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
			$name
		);
		
		return NULL;
	}
	
	$author = (int) $result[0];
	
	return User\get_user_dir($dm, $author) . $picture . $extension;
}

/*
 * @function get_picture_title
 * 
 * @retval string
 * The title of the picture, or NULL on failure.
 */
function get_picture_title(\Demoshot\Demoshot $dm, $id) {
	assert(is_unsigned($id));
	
	$db = $dm->get_db();
	
	$query = "SELECT title FROM " . TABLE_PICTURE . " WHERE ID = $id";
	
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
 * @function get_picture_type
 * 
 * @retval int
 * PICTURE_TYPE_PNG || PICTURE_TYPE_JPG
 */
function get_picture_type(\Demoshot\Demoshot $dm, $id) {
	assert(is_unsigned($id));
	
	$db = $dm->get_db();
	
	$query = "SELECT type FROM " . TABLE_PICTURE . " WHERE ID = $id";
	
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
			$id
		);
		
		return ERROR_NOT_FOUND;
	}
	
	$type = (int) $result[0];
	
	if($type < PICTURE_TYPE_PNG || $type > PICTURE_TYPE_JPG) {
		$dm->set_error(
			ERROR_UNHANDLED_CASE,
			"type code is $type"
		);
		
		return ERROR_UNHANDLED_CASE;
	}
	
	return $type;
}

/*
 * @function get_thumbnail_path
 * 
 * @purpose
 * Return the absolute path to the thumbnail.
 * 
 * @param string $path
 * The path to the picture which the thumbnail belongs to.
 * 
 * @retval string
 * The path. Or NULL on failure.
 */
function get_thumbnail_path(\Demoshot\Demoshot $dm, $path) {
	assert(is_string($path));
	
	/*
	 * E.g: we have the path data/4/42.jpg,
	 * the thumbnail is located at data/4/42.tn.png.
	 * 
	 * So we remove the .jpg part (or .png if it is a PNG)
	 * and add '.tn.png'.
	 * 
	 * (.+) matches any number of any kind of character before the last dot.
	 * '$1' selects the first part of the match, before .jpg or .png.
	 */
	
	return preg_replace('#^(.+)\.(png|jpg)$#', '$1.' . PICTURE_THUMBNAIL_POSTFIX . '.png', $path);
}

/*
 * CLASSES
 */


/*
 * @class PictureRetriever
 * 
 * @purpose
 * This class can be used in a foreach loop to retrieve the ID of a series of pictures.
 * These pictures can be retrieved according to their tags or their owner.
 */
class PictureRetriever implements \Iterator {
	
	
	/*
	 * MEMBERS
	 */

	/*
	 * @member int $position
	 * The index of the current picture.
	 */
	private $position = 0;
	
	/*
	 * @member array(int) $array
	 * Each case contains the ID of a picture posted by a user or related to a tag.
	 */
	private $array;
	
	
	/*
	 * SPECIAL METHODS
	 */
	
	/*
	 * @constructor
	 * 
	 * @param mixed $id
	 * Either the ID of a user to retrieve all the pictures he posted,
	 * or an array of ID of users to retrieve all the pictures they posted,
	 * or the ID of a tag to retrieve all the related pictures,
	 * or an array of ID of tags to retrieve all the related pictures.
	 * NULL to retrieve all existing pictures.
	 * 
	 * @param int $retrieve
	 * RETRIEVE_PICTURES_BY_TAG || RETRIEVE_PICTURES_BY_AUTHOR ||
	 * RETRIEVE_PICTURES_BY_MARK_AUTHOR
	 * RETRIEVE_ALL_PICTURES if we set $id to NULL.
	 * 
	 * @param int $sort_by
	 * One of the constants enumerated as PICTURE_SORT_BY_*.
	 * 
	 * @param bool $sort_asc
	 * If true, sort the pictures in ascending order.
	 * Else, sort them in descending order.
	 * 
	 * @param bool $private
	 * If true, search only for private pictures.
	 * If false, search only for public pictures.
	 * If NULL, search for all the pictures.
	 * 
	 * @param string $like
	 * A pattern to look for in the titles, or NULL.
	 * 
	 * @param int $limit
	 * Limit the number of results. -1 for no limit.
	 * 
	 * @param int $start_from
	 * Start from the ?th result. The first result has index 0.
	 * 
	 * @param string $description_like
	 * A pattern to look to in the descriptions, or NULL.
	 * 
	 * @param bool $join_tags
	 * When an array of tag ID-s are provided through $id,
	 * if $join_tags, look for pictures related to all of them,
	 * else, look for pictures related to any of them.
	 * 
	 * @param bool $join_likes
	 * When we are looking for a pattern in the title and a pattern in the description,
	 * if $join_likes, look for pictures whose title contains $like AND whose description contains $description_like,
	 * else, look for pictures whose title contains $like OR whose description contains $description_like.
	 */
	public function __construct(
	
		// the long list of parameters...
		\Demoshot\Demoshot $dm,
		$id = NULL, $retrieve = RETRIEVE_ALL_PICTURES,
		$sort_by = PICTURE_SORT_BY_NONE, $sort_asc = true,
		$private = NULL, $limit = -1, $start_from = -1,
		$like = NULL, $description_like = NULL,
		$join_tags = false, $join_likes = false
	
	) {
		assert(is_null($id) || is_unsigned($id) || is_array($id));
		assert(is_enum($retrieve, RETRIEVE_PICTURES_BY_TAG, RETRIEVE_ALL_PICTURES));
		assert(is_enum($sort_by, PICTURE_SORT_BY_NONE, PICTURE_SORT_BY_DATE));
		assert(is_bool($sort_asc));
		assert(is_null($private) || is_bool($private));
		assert(is_int($limit));
		assert(is_int($start_from) && $start_from >= -1);
		assert(is_null($like) || is_string($like));
		assert(is_null($description_like) || is_string($description_like));
		assert(is_bool($join_tags));
		assert(is_bool($join_likes));
		
		$db = $dm->get_db();
		
		$this->position = 0;
		
		
		/*
		 * Retrieve the pictures.
		 */
		$query = "SELECT DISTINCT(ID) FROM " . TABLE_PICTURE;
		$no_where = true;
		
		
		switch($retrieve) {
			case RETRIEVE_PICTURES_BY_AUTHOR:
				assert(is_unsigned($id));
				
				$query .= $no_where ? " WHERE" : " AND";
				
				$query .= " author_id = $id";
				
				$no_where = false;
				break;
			
			case RETRIEVE_PICTURES_BY_TAG:
				assert(is_unsigned($id) || is_array($id));
				
				if(is_array($id)) {
					
					if($join_tags) {
						/*
						 * Select the pictures related to all the tags.
						 * To do so, we need to do as many joins on the picture_tag table
						 * as there are different tags in the array $id.
						 * 
						 * E.g: we want to retrieve the pictures related to tags 1, 3 and 42.
						 * The query will look like this:
						 * 
						 * SELECT ID from picture
						 *      INNER JOIN picture_tag tag1 ON tag1.picture_id = picture.id
						 *      INNER JOIN picture_tag tag2 ON tag2.picture_id = picture.id
						 *      INNER JOIN picture_tag tag3 ON tag3.picture_id = picture.id
						 *      WHERE tag1.tag_id = 1 AND tag2.tag_id = 3 AND tag3.tag_id = 42;
						 */
						
						// first, we add the INNER JOIN bits
						for($it = 0, $c = count($id); $it < $c; $it++) {
							assert(is_unsigned($id[$it]));
							
							$query .= " INNER JOIN " . TABLE_PICTURE_TAG . " tag$it" .
								" ON tag$it.picture_id = " . TABLE_PICTURE . ".ID";
						}
						
						$query .= " WHERE ";
						
						// second, we add the WHERE...AND clause
						for($it = 0, $c = count($id); $it < $c; $it++) {
							if($it > 0) {
								$query .= " AND ";
							}
							
							$query .= "tag$it.tag_id = $id[$it]";
						}
						
						$no_where = false;
						
					} else {
						/*
						 * Select the pictures related to one of the tags.
						 */
						
						$list = join(', ', $id);
						
						$query .= " INNER JOIN " . TABLE_PICTURE_TAG .
							" ON " . TABLE_PICTURE_TAG . ".picture_id = " . TABLE_PICTURE . ".ID AND " .
							TABLE_PICTURE_TAG . ".tag_id IN($list)";
					}
					
				} else {
					/*
					 * Only one tag is required.
					 */
					
					$query .= " INNER JOIN " . TABLE_PICTURE_TAG .
						" ON " . TABLE_PICTURE_TAG . ".picture_id = " . TABLE_PICTURE . ".ID" .
						" AND " . TABLE_PICTURE_TAG . ".tag_id = $id";
				}
				break;
			
			case RETRIEVE_PICTURES_BY_MARK_AUTHOR:
				/*
				 * Select the pictures marked by a user.
				 */
				
				$query .= " INNER JOIN " . TABLE_MARK .
					" ON " . TABLE_MARK . ".picture_id = " . TABLE_PICTURE . ".ID" .
					" AND " . TABLE_MARK . ".author_id = $id";
				break;
		}
		
		
		if(is_bool($private)) {
			$query .= $no_where ? " WHERE" : " AND";
			
			if($private) {
				$query .= " private = 1";
			} else {
				$query .= " private = 0";
			}
			
			$no_where = false;
		}
		
		
		/*
		 * Search for a pattern in the title and/or in the description.
		 */		
		if(is_string($like)) {
			$query .= $no_where ? " WHERE" : " AND";
			
			$query .= " (title LIKE " . $db->quote(utf8_encode("%$like%"));
			
			if(is_string($description_like)) {
				if($join_likes) {
					$query .= " AND ";
				} else {
					$query .= " OR ";
				}
				
				$query .= "description LIKE " . $db->quote(utf8_encode("%$description_like%"));
			}
			
			$query .= ")";
			
			$no_where = false;
		} else if(is_string($description_like)) {
			$query .= $no_where ? " WHERE" : " AND";
			
			$query .= " (description LIKE " . $db->quote(utf8_encode("%$description_like%")) . ")";
			
			$no_where = false;
		}
		
		
		/*
		 * Sort the pictures.
		 */
		switch($sort_by) {
			case PICTURE_SORT_BY_ID: $sort_field = "ID"; break;
			case PICTURE_SORT_BY_TITLE: $sort_field = "title"; break;
			case PICTURE_SORT_BY_AUTHOR_ID: $sort_field = TABLE_PICTURE . ".author_id"; break;
			case PICTURE_SORT_BY_AV_MARK: $sort_field = "average_mark"; break;
			case PICTURE_SORT_BY_NUM_OF_MARKS: $sort_field = "num_of_marks"; break;
			
			case PICTURE_SORT_BY_RELEVANCE:
				$sort_field = "average_mark";
				$sort_field .= $sort_asc ? " ASC" : " DESC";
				$sort_field .= ", num_of_marks"; break;
			
			case PICTURE_SORT_BY_RANDOM: $sort_field = "RAND()"; break;
			
			case PICTURE_SORT_BY_MARK_DATE:
				if($retrieve == RETRIEVE_PICTURES_BY_MARK_AUTHOR) {
					$sort_field = TABLE_MARK . ".date";
				}
				break;
			
			case PICTURE_SORT_BY_DATE: $sort_field = TABLE_PICTURE . ".date"; break;
			default: break;
		}
		
		if(isset($sort_field)) {
			$query .= " ORDER BY $sort_field";
			
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
