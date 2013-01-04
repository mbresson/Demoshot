<?php namespace Demoshot\User;


/*
 * DEPENDENCIES
 */

require_once('misc.php');

require_once('errors.php');
use \Demoshot\Errors;

require_once('database.php');
use \Demoshot\Database;

require_once('security.php');
use \Demoshot\Security;

require_once('picture.php');
use \Demoshot\Picture;

require_once('tag.php');
use \Demoshot\Tag;

require_once('mark.php');
use \Demoshot\Mark;


/*
 * CONSTANTS
 */

define("LABEL_COOKIE_ID", "demoshot_id");
define("LABEL_COOKIE_LANG", "demoshot_lang");
define("COOKIE_LIFETIME", 24*3600);
define("USER_ROOT_DIRECTORY", "data/");
define("USER_PATH_TO_DEFAULT_AVATAR", 'res/img/default_avatar.png');

define("USER_LANG_EN", "en_US.UTF-8");
define("USER_LANG_FR", "fr_FR.UTF-8");
define("USER_DEFAULT_LANG", USER_LANG_EN);

$enumi = 0;
define('TYPE_USER', $enumi++);
define('TYPE_ADMIN', $enumi++);

$enumi = 1; // error codes should begin at 1 to be not mistaken for NO_ERROR in Demoshot.php
define('FOLLOW_ERROR_SAME_USER', $enumi++); // the user is trying to follow himself

/*
 * @remark
 * These constants are used as error codes by the signup function.
 */
$enumi = 1;
define('SIGNUP_WRONG_MKDIR', $enumi++); // the user's directory couldn't be created
define('SIGNUP_USERNAME_EXISTS', $enumi++); // the username already exists
define('SIGNUP_EMAIL_EXISTS', $enumi++); // the email already exists

/*
 * @remark
 * These constants are used as error codes by login functions.
 */
$enumi = 1;
define('LOGIN_WRONG_USERNAME', $enumi++); // there is no user with this name in the database
define('LOGIN_WRONG_PASSWD', $enumi++); // the password is not correct
define('LOGIN_WRONG_NO_COOKIE', $enumi++); // there is no session cookie
define('LOGIN_WRONG_SESSION_ID', $enumi++); // there is no user with this session id in the database

/*
 * The following constants can be passed to UserRetriever to limit the results.
 */
$enumi = 0;
define("RETRIEVE_USERS_BY_FOLLOWED", $enumi++);
define("RETRIEVE_USERS_BY_FOLLOWER", $enumi++);
define("RETRIEVE_USERS_BY_PICTURE", $enumi++);
define("RETRIEVE_USERS_BY_TAG", $enumi++);
define("RETRIEVE_ALL_USERS", $enumi++);

/*
 * The following constants help accessing to the informations
 * returned by function get_user.
 */
$enumi = 0;
define('GET_USER_ID', $enumi++);
define('GET_USER_NAME', $enumi++);
define('GET_USER_EMAIL', $enumi++);
define('GET_USER_AVATAR', $enumi++);
define('GET_USER_DATE', $enumi++);
define('GET_USER_PRIVATE_PICTURES', $enumi++);
define('GET_USER_PRIVATE_COMMENTS', $enumi++);
define('GET_USER_DESCRIPTION', $enumi++);
define('GET_USER_TYPE', $enumi++);

/*
 * The following constants help accessing to the informations
 * returned by function get_user_statistics.
 */
$enumi = 0;
define("GET_USER_STATS_PICTURES", $enumi++);
define("GET_USER_STATS_MARKS", $enumi++);
define("GET_USER_STATS_FOLLOWERS", $enumi++);
define("GET_USER_STATS_FOLLOWED", $enumi++);

/*
 * The width and height of an avatar image.
 */
define('AVATAR_WIDTH', 100);
define('AVATAR_HEIGHT', 100);

/*
 * Used in create_avatar function.
 */
$enumi = 0;
define('AVATAR_METHOD_CENTER', $enumi++); // crop the picture to keep a 100x100 area at its center
define('AVATAR_METHOD_RESIZE', $enumi++); // resize the picture to a 100x100 image

/*
 * These constants can be passed to UserRetriever to have
 * the results sorted by a column.
 */
$enumi = 0;
define('USER_SORT_BY_NONE', $enumi++);
define('USER_SORT_BY_ID', $enumi++);
define('USER_SORT_BY_DATE', $enumi++);
define('USER_SORT_BY_USERNAME', $enumi++);
define('USER_SORT_BY_NUM_OF_MARKS', $enumi++);
define('USER_SORT_BY_NUM_OF_PICTURES', $enumi++);
define('USER_SORT_BY_NUM_OF_FOLLOWERS', $enumi++);
define('USER_SORT_BY_NUM_OF_FOLLOWED', $enumi++);
define('USER_SORT_BY_INVOLVEMENT', $enumi++);
define('USER_SORT_BY_POPULARITY', $enumi++);
define('USER_SORT_BY_RANDOM', $enumi++); // random order


/*
 * FUNCTIONS
 */

/*
 * @function check_password
 * 
 * @param int $id
 * The ID of a user we want to check.
 * 
 * @param string $passwd
 * The password of this user.
 * 
 * @retval bool
 * True if it is the right password.
 */
function check_password(\Demoshot\Demoshot $dm, $id, $passwd) {
	assert(is_unsigned($id));
	assert(is_string($passwd));
	
	$db = $dm->get_db();
	
	// get password salt in the database
	$query = "SELECT passwd_salt FROM " . TABLE_USER . " WHERE ID = $id";
	
	$result = $db->query($query);
	
	if(!$result) {
		$error = $db->errorInfo();
		
		$dm->set_error(
			ERROR_ON_QUERY,
			$error[2]
		);
		
		return false;
	}
	
	$result = $result->fetch(\PDO::FETCH_NUM);
	
	if(!$result) {
		$dm->set_error(
			ERROR_NOT_FOUND,
			$name
		);
		
		return false;
	}
	
	// compare $passwd with the password stored in the database
	$passwd = Security\hash_data($result[0], $passwd);
	
	$query = "SELECT ID FROM " . TABLE_USER . " WHERE passwd = '$passwd' AND ID = $id";
	
	$result = $db->query($query);
	
	if(!$result) {
		$error = $db->errorInfo();
		
		$dm->set_error(
			ERROR_ON_QUERY,
			$error[2]
		);
		
		return false;
	}
	
	$result = $result->fetch(\PDO::FETCH_NUM);
	
	if(!$result) {
		$dm->set_error(
			ERROR_SIMPLE,
			$id
		);
		
		return false;
	}
	
	if((int) $result[0] != $id) {
		$dm->set_error(
			ERROR_SIMPLE,
			$id
		);
		
		return false;
	}
	
	return true;
}

/*
 * @function create_avatar
 * 
 * @purpose
 * Create an avatar of fixed dimensions with the original picture located at $filepath.
 * Save the avatar as a PNG file in the user directory.
 * 
 * @param string $filepath
 * The path to the original file.
 * 
 * @param int $method
 * The method to use to create an avatar of AVATAR_WIDTH x AVATAR_HEIGHT pixels.
 * AVATAR_METHOD_CENTER || AVATAR_METHOD_RESIZE
 * 
 * @retval bool
 * True on success, false on failure.
 */
function create_avatar(\Demoshot\Demoshot $dm, $id, $filepath, $method) {
	assert(is_unsigned($id));
	assert(is_string($filepath));
	assert(is_enum($method, AVATAR_METHOD_CENTER, AVATAR_METHOD_RESIZE));
	
	if(!file_exists($filepath)) {
		$dm->set_error(
			ERROR_NO_FILE,
			$filepath
		);
		
		return false;
	}
	
	$userdir = get_user_dir($dm, $id);
	
	if(!is_dir($userdir)) {
		$dm->set_error(
			ERROR_NOT_FOUND, // if there is no directory then there is no user
			$id
		);
		
		return false;
	}
	
	$avatar = imagecreatetruecolor(AVATAR_WIDTH, AVATAR_HEIGHT);
	
	$type = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
	
	$source = NULL;
	if($type === "png") {
		$source = imagecreatefrompng($filepath);
	} else if($type === "jpg" || $type === "jpeg") {
		$source = imagecreatefromjpeg($filepath);
	} else {
		$dm->set_error(
			ERROR_UNHANDLED_CASE, // we can't handle this type of image
			$type
		);
		
		return false;
	}
	
	list($org_width, $org_height) = getimagesize($filepath);
	
	$ok = false;
	
	
	/*
	 * Conserve transparency in case the original image is a PNG.
	 */
	imagesavealpha($avatar, true);
	$transparent = imagecolorallocatealpha($avatar, 0, 0, 0, 127);
	imagefill($avatar, 0, 0, $transparent);
	
	
	/*
	 * Generate the avatar.
	 */
	if($method == AVATAR_METHOD_CENTER) {
		/*
		 * If the original image is smaller than the avatar,
		 * we will copy it to the center of the avatar without enlarging it.
		 */
		
		if($org_width < AVATAR_WIDTH) {
			$src_w = $org_width;
			$dst_x = AVATAR_WIDTH / 2 - $src_w / 2;
			$src_x = 0;
			
		} else {
			$src_w = AVATAR_WIDTH;
			$dst_x = 0;
			$src_x = $org_width / 2 - AVATAR_WIDTH / 2;
		}
		
		if($org_height < AVATAR_HEIGHT) {
			$src_h = $org_height;
			$dst_y = AVATAR_HEIGHT / 2 - $src_h / 2;
			$src_y = 0;
			
		} else {
			$src_h = AVATAR_HEIGHT;
			$dst_y = 0;
			$src_y = $org_height / 2 - AVATAR_HEIGHT / 2;
		}
		
		$ok = imagecopy(
			$avatar, $source,
			$dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h
		);
		
	} else { // AVATAR_METHOD_RESIZE
		
		$ok = imagecopyresampled(
			$avatar, $source, 0, 0, 0, 0,
			AVATAR_WIDTH, AVATAR_HEIGHT, $org_width, $org_height
		);
	}
	
	if(!$ok) {
		$dm->set_error(
			ERROR_SIMPLE,
			""
		);
		
		return false;
	}
	
	$avatar_path = get_user_avatar($id);
	
	if(file_exists($avatar_path)) {
		unlink($avatar_path);
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
	
	// save the avatar
	if(!imagepng($avatar, $avatar_path, $compression_level)) {
		$dm->set_error(
			ERROR_NO_WRITE_PERMISSION,
			$avatar_path
		);
		
		return false;
	}
	
	return true;
}

/*
 * @function delete_account
 * 
 * @purpose
 * Delete user account with the following steps:
 * - Remove all the connections to other users and tags.
 * - Delete all the pictures posted by the user.
 * - Delete all the marks posted by the user.
 * - Empty the user's directory then remove it.
 * 
 * @retval bool
 * True on success, false on failure.
 */
function delete_account(\Demoshot\Demoshot $dm, $id) {
	assert(is_unsigned($id));
	
	$db = $dm->get_db();
	
	/*
	 * Remove all connections between this user and any other user.
	 */
	stop_being_followed($dm, $id);
	stop_following($dm, $id);
	
	
	/*
	 * Remove the connection to any tag.
	 */
	Tag\unlink_tag($dm, $id, TABLE_CODE_USER);
	
	
	/*
	 * Remove the marks posted by the user.
	 */
	$mark_retr = new Mark\MarkRetriever($dm, $id, TABLE_CODE_USER);
	
	foreach($mark_retr as $mark) {
		Mark\delete_mark($dm, $mark);
	}
	
	
	/*
	 * Get all the pictures posted by the user and delete them.
	 */
	$pic_retr = new Picture\PictureRetriever($dm, $id, RETRIEVE_PICTURES_BY_AUTHOR);
	
	foreach($pic_retr as $picture) {
		Picture\delete_picture($dm, $picture);
	}
	
	if(!remove_dir(get_user_dir($dm, $id))) {
		$dm->set_error(
			ERROR_NO_WRITE_PERMISSION,
			get_user_dir($dm, $id)
		);
		
		return false;
	}
	
	$query = "DELETE FROM " . TABLE_USER . " WHERE ID = $id";
	
	$db->exec($query);
	
	return true;
}

/*
 * @function disconnect
 * 
 * @purpose
 * - Remove session cookies.
 * - Empty field tmp_session_id in database.
 * 
 * @param int $user
 * The ID of the user to disconnect.
 * 
 * @retval bool
 * True on success, false on failure.
 */
function disconnect(\Demoshot\Demoshot $dm, $user) {
	assert(is_unsigned($user));
	
	// remove session cookie
	setcookie(LABEL_COOKIE_ID, "", time() - 42);
	
	
	// remove the session id from the database
	$db = $dm->get_db();
	
	$query = "UPDATE " . TABLE_USER . " SET tmp_session_id = '' WHERE ID = $user";
	
	if($db->exec($query) === false) {
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
 * @function edit_account
 * 
 * @purpose
 * Change some of the information related to the user.
 * E.g. his email and his description can be modified.
 * 
 * @param string $passwd
 * The new password, or NULL to let the password unchanged.
 * 
 * @param string $email
 * The new email address, or NULL to let it unchanged.
 * 
 * @param bool $private_pictures
 * True or false, or NULL to let it unchanged.
 * If true, any new picture uploaded by the user will be private by default.
 * 
 * @param bool $private_comments
 * True or false, or NULL to let it unchanged.
 * If true, any new comment posted by the user
 * will be private by default.
 * 
 * @param string $description
 * The new description, or NULL to let it unchanged, or an empty string to remove it.
 * 
 * @param int $type
 * The new user type, or NULL to let it unchanged.
 * TYPE_USER || TYPE_ADMIN
 * 
 * @retval bool
 * True on success, false on failure.
 */
function edit_account (

	// the long list of parameters...
	\Demoshot\Demoshot $dm, $id,
	$passwd = NULL, $email = NULL,
	$private_pictures = NULL, $private_comments = NULL,
	$description = NULL, $type = NULL
	
) {
	// preconditions
	assert(is_unsigned($id));
	assert(is_null($passwd) || (is_string($passwd) && $passwd !== ''));
	assert(is_null($email) || (is_string($email) && $email !== ''));
	assert(is_bool($private_pictures) || is_null($private_pictures));
	assert(is_bool($private_comments) || is_null($private_comments));
	assert(is_null($description) || (is_string($description)));
	assert(is_null($type) || is_enum($type, TYPE_USER, TYPE_ADMIN));
	
	$db = $dm->get_db();
	
	
	/*
	 * Prepare the query.
	 */
	$query = "UPDATE " . TABLE_USER . " ";
	$no_set = true; // used to check if we must add a comma to the query
	
	// check the values to update
	if(is_string($passwd)) {
		$passwd_salt = Security\new_salt();
		$passwd = Security\hash_data($passwd_salt, $passwd);
		
		$query .= "SET passwd = '$passwd', passwd_salt = '$passwd_salt'";
		
		$no_set = false;
	}
	
	if(is_string($email)) {
		$email = $db->quote(utf8_encode($email));
		
		$query .= $no_set ? ' SET ' : ', ';
		
		$query .= "email = $email";
		
		$no_set = false;
	}
	
	if(is_bool($private_pictures)) {
		$query .= $no_set ? ' SET ' : ', ';
		
		$private_pictures = (int) $private_pictures;
		
		$query .= "private_pictures = $private_pictures";
		
		$no_set = false;
	}
	
	if(is_bool($private_comments)) {
		$query .= $no_set ? ' SET ' : ', ';
		
		$private_comments = (int) $private_comments;
		
		$query .= "private_comments = $private_comments";
		
		$no_set = false;
	}
	
	if(is_string($description)) {
		// check the length of the description
		$conf = $dm->get_conf();
		$conf = $conf['database'];
		
		if(!isset($conf['max_description_length'])) {
			$dm->set_error(
				ERROR_MISSING_INFORMATION,
				'Demoshot.ini["database"]["max_description_length"]'
			);
			
			return false;
		}
		
		if(strlen($description) > (int) $conf['max_description_length']) {
			$dm->set_error(
				ERROR_TEXT_OVERFLOW,
				$description
			);
			
			return false;
		}
		
		$query .= $no_set ? ' SET ' : ', ';
		
		if($description == '') {
			$query .= "description = NULL";
		} else {
			$query .= "description = " . $db->quote(utf8_encode($description));
		}
		
		$no_set = false;
	}
	
	if(is_enum($type, TYPE_USER, TYPE_ADMIN)) {
		$query .= $no_set ? ' SET ' : ', ';
		
		$query .= "type = $type";
		
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
 * @function follow
 * 
 * @purpose
 * Make an user follow another user.
 * 
 * @param int $follower
 * The ID of the follower.
 * 
 * @param int followed
 * The ID of the followed user.
 * 
 * @retval bool
 * True on success, false on failure.
 */
function follow(\Demoshot\Demoshot $dm, $follower, $followed) {
	assert(is_unsigned($follower));
	assert(is_unsigned($followed));
	
	
	/*
	 * Make sure that the user is not trying to follow himself.
	 */
	if($follower == $followed) {
		$dm->set_error(
			FOLLOW_ERROR_SAME_USER,
			"$follower"
		);
		
		return false;
	}
	
	
	/*
	 * Insert the new row
	 */
	$db = $dm->get_db();
	
	$query = "INSERT INTO " . TABLE_FOLLOWER .
		" (follower_id, followed_id)" .
		" VALUES ($follower, $followed)";
	
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
	 * Update the num_of_followers of the followed user
	 * and the num_of_followed of the follower.
	 */
	$query = "UPDATE " . TABLE_USER .
		" SET num_of_followers = num_of_followers + 1" .
		" WHERE ID = $followed";
	
	$db->exec($query);
	
	$query = "UPDATE " . TABLE_USER .
		" SET num_of_followed = num_of_followed + 1" .
		" WHERE ID = $follower";
	
	$db->exec($query);
	
	return true;
}

/*
 * @function get_number_of_users
 * 
 * @purpose
 * Get the number of users satisfying the provided criteria.
 * This function takes the same parameters as UserRetriever,
 * except for $limit, $start_from, $sort_by and $sort_asc.
 * For explanations on the use of these parameters, see UserRetriever.
 * 
 * This function is used for pagination in the search results page,
 * when we need to know how many results we would get if we didn't limit
 * the number of results per page.
 * 
 * For pictures, there is the function get_number_of_pictures.
 * 
 * @retval int
 * The number of results, or ERROR_ on failure.
 */
function get_number_of_users(

	\Demoshot\Demoshot $dm,
	$id = NULL, $retrieve = RETRIEVE_ALL_USERS,
	$like = NULL, $description_like = NULL,
	$join_tags = false, $join_likes = false

) {
	assert(is_null($id) || is_unsigned($id) || is_array($id));
	assert(is_enum($retrieve, RETRIEVE_USERS_BY_FOLLOWED, RETRIEVE_ALL_USERS));
	assert(is_null($like) || is_string($like));
	assert(is_null($description_like) || is_string($description_like));
	assert(is_bool($join_tags));
	assert(is_bool($join_likes));
	
	$db = $dm->get_db();
	
	/*
	 * Retrieve the number of users.
	 */
	$query = "SELECT COUNT(DISTINCT(ID)) FROM " . TABLE_USER;
	$no_where = true;
	
	
	if($retrieve != RETRIEVE_ALL_USERS) {
		switch($retrieve) {
			case RETRIEVE_USERS_BY_FOLLOWED:
				/*
				 * We will retrieve all the users following the user
				 * whose ID = $id.
				 */
				
				assert(is_unsigned($retrieve));
				
				$query .= " INNER JOIN " . TABLE_FOLLOWER .
					" ON " . TABLE_FOLLOWER . ".follower_id = " . TABLE_USER . ".ID" .
					" WHERE " . TABLE_FOLLOWER . ".followed_id = $id";
				
				$no_where = false;
				break;
			
			case RETRIEVE_USERS_BY_FOLLOWER:
				/*
				 * We will retrieve all the users followed by the user
				 * whose ID = $id.
				 */
				
				assert(is_unsigned($retrieve));
				
				$query .= " INNER JOIN " . TABLE_FOLLOWER .
					" ON " . TABLE_FOLLOWER . ".followed_id = " . TABLE_USER . ".ID" .
					" WHERE " . TABLE_FOLLOWER . ".follower_id = $id";
				
				$no_where = false;
				break;
			
			case RETRIEVE_USERS_BY_PICTURE:
				/*
				 * We will retrieve all the users who have marked the picture
				 * whose ID = $id.
				 */
				
				assert(is_unsigned($retrieve));
				
				$query .= " INNER JOIN " . TABLE_MARK .
					" ON " . TABLE_MARK . ".author_id = " . TABLE_USER . ".ID" .
					" WHERE " . TABLE_MARK . ".picture_id = $id";
					
				$no_where = false;
				break;
			
			case RETRIEVE_USERS_BY_TAG:
				/*
				 * We will retrieve all the users who are related to the tag(s)
				 * whose ID(s) are (listed in) $id.
				 */
				
				assert(is_unsigned($id) || is_array($id));
				
				if(is_array($id)) {
					
					if($join_tags) {
						/*
						 * Select the users interested in all the tags.
						 * To do so, we need to do as many joins on the user_tag table
						 * as there are different tags in the array $id.
						 * 
						 * E.g: we want to retrieve the users interested in tags 1, 3 and 42.
						 * The query will look like this:
						 * 
						 * SELECT ID from user
						 *      INNER JOIN user_tag tag1 ON tag1.user_id = user.id
						 *      INNER JOIN user_tag tag2 ON tag2.user_id = user.id
						 *      INNER JOIN user_tag tag3 ON tag3.user_id = user.id
						 *      WHERE tag1.tag_id = 1 AND tag2.tag_id = 3 AND tag3.tag_id = 42;
						 */
						
						// first, we add the INNER JOIN bits
						for($it = 0, $c = count($id); $it < $c; $it++) {
							assert(is_unsigned($id[$it]));
							
							$query .= " INNER JOIN " . TABLE_USER_TAG . " tag$it" .
								" ON tag$it.user_id = " . TABLE_USER . ".ID";
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
						
						$query .= " INNER JOIN " . TABLE_USER_TAG .
							" ON " . TABLE_USER_TAG . ".user_id = " . TABLE_USER . ".ID AND " .
							TABLE_USER_TAG . ".tag_id IN($list)";
					}
					
				} else {
					/*
					 * Only one tag is required.
					 */
					
					$query .= " INNER JOIN " . TABLE_USER_TAG .
						" ON " . TABLE_USER_TAG . ".user_id = " . TABLE_USER . ".ID" .
						" AND " . TABLE_USER_TAG . ".tag_id = $id";
				}
				
				break;
		}
	}
	
	
	/*
	 * Search for a pattern in the username and/or in the description.
	 */
	if(is_string($like)) {
		$query .= $no_where ? " WHERE" : " AND";
		
		$query .= " (username LIKE " . $db->quote(utf8_encode("%$like%"));
		
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
 * @function get_user
 * 
 * @purpose
 * Get the information related to a user.
 * 
 * @param int $id
 * The ID of the user.
 * 
 * @retval array(int, string, string, string, bool, bool, string, int)
 * The ID, the name, the email (can be NULL), the path to the avatar,
 * the default private-ness of his pictures,
 * the default private-ness of his comments,
 * the description and the type of the user.
 * Or NULL on failure.
 */
function get_user(\Demoshot\Demoshot $dm, $id) {
	assert(is_unsigned($id));
	
	$db = $dm->get_db();
	
	$query = "SELECT username, email," .
		" private_pictures, private_comments, description, type, date" .
		" FROM " . TABLE_USER .
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
		GET_USER_ID => $id,
		GET_USER_NAME => htmlspecialchars(utf8_decode($result[0]), ENT_QUOTES | ENT_HTML5),
		GET_USER_EMAIL => htmlspecialchars(utf8_decode($result[1]), ENT_QUOTES | ENT_HTML5),
		GET_USER_AVATAR => get_user_avatar($id, true),
		GET_USER_PRIVATE_PICTURES => (bool) $result[2],
		GET_USER_PRIVATE_COMMENTS => (bool) $result[3],
		GET_USER_DESCRIPTION => ($result[4] === NULL ? NULL : htmlspecialchars(utf8_decode($result[4]), ENT_QUOTES | ENT_HTML5)),
		GET_USER_TYPE => (int) $result[5],
		GET_USER_DATE => $result[6]
	);
}

/*
 * @function get_user_avatar
 * 
 * @purpose
 * Get the path to the user avatar image.
 * 
 * @param bool $redirect
 * If false, the function doesn't check whether the avatar actually exists.
 * If true, if the avatar doesn't exist, the function return the path to the default avatar.
 */
function get_user_avatar($user, $redirect = false) {
	assert(is_unsigned($user));
	
	$path = USER_ROOT_DIRECTORY . $user . '/avatar.png';
	
	if($redirect && !file_exists($path)) {
		$path = USER_PATH_TO_DEFAULT_AVATAR;
	}
	
	return $path;
}

/*
 * @function get_user_dir
 * 
 * @purpose
 * Get the path to the user directory.
 * 
 * @param int $user
 * The ID of the user.
 */
function get_user_dir(\Demoshot\Demoshot $dm, $id) {
	assert(is_unsigned($id));
	
	return (USER_ROOT_DIRECTORY . $id . '/');
}

/*
 * @function get_user_email
 * 
 * @retval string
 * The email address of the user.
 * NULL on failure.
 */
function get_user_email(\Demoshot\Demoshot $dm, $id) {
	assert(is_unsigned($id));
	
	$db = $dm->get_db();
	
	$query = "SELECT email FROM " . TABLE_USER .
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
			$id
		);
		
		return NULL;
	}
	
	return $result[0];
}

/*
 * @function get_user_followed
 * 
 * @retval array(int...)
 * An array containing the ID-s of all the users followed by $id.
 * NULL on failure, or an empty array if there are no followed users.
 */
function get_user_followed(\Demoshot\Demoshot $dm, $id) {
	assert(is_unsigned($id));
	
	$db = $dm->get_db();
	
	$query = "SELECT followed_id FROM " . TABLE_FOLLOWER .
		" WHERE follower_id = $id";
	
	$result = $db->query($query);
	
	if(!$result) {
		$error = $db->errorInfo();
		
		$dm->set_error(
			ERROR_ON_QUERY,
			$error[2]
		);
		
		return NULL;
	}
	
	$result = $result->fetchAll(\PDO::FETCH_NUM);
	
	if(!$result) {
		return array();
	}
	
	$followed = array();
	foreach($result as $followed_id) {
		$followed[] = (int) $followed_id[0];
	}
	
	return $followed;
}

/*
 * @function get_user_followers
 * 
 * @retval array(int...)
 * An array containing the ID-s of all the users following $id.
 * NULL on failure, or an empty array if there are no followed users.
 */
function get_user_followers(\Demoshot\Demoshot $dm, $id) {
	assert(is_unsigned($id));
	
	$db = $dm->get_db();
	
	$query = "SELECT follower_id FROM " . TABLE_FOLLOWER .
		" WHERE followed_id = $id";
	
	$result = $db->query($query);
	
	if(!$result) {
		$error = $db->errorInfo();
		
		$dm->set_error(
			ERROR_ON_QUERY,
			$error[2]
		);
		
		return NULL;
	}
	
	$result = $result->fetchAll(\PDO::FETCH_NUM);
	
	if(!$result) {
		return array();
	}
	
	$follower = array();
	foreach($result as $follower_id) {
		$follower[] = (int) $follower_id[0];
	}
	
	return $follower;
}

/*
 * @function get_user_id
 * 
 * @purpose
 * Get the user id stored in the database.
 * 
 * @retval int
 * The id, or ERROR_NOT_FOUND if the user doesn't exit.
 */
function get_user_id(\Demoshot\Demoshot $dm, $name) {
	assert(is_string($name));
	
	$db = $dm->get_db();
	
	$query = "SELECT ID from " . TABLE_USER . " WHERE username = " . $db->quote(utf8_encode($name));
	
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
			$name
		);
		
		return ERROR_NOT_FOUND;
	}
	
	return (int) $result[0];
}

/*
 * @function get_user_lang
 * 
 * @retval string
 * The current language, stored in the cookie LABEL_COOKIE_LANG or passed through the URL.
 * If there is no such cookie, return the default language.
 */
function get_user_lang() {
	if(isset($_GET['lang'])) {
		
		$lang = $_GET['lang'];
		
		if($lang === USER_LANG_EN || $lang === USER_LANG_FR) {
			// a parameter has been passed to change the language
			set_user_lang($lang);
			
			return $lang;
		}
	}
	
	if(!isset($_COOKIE[LABEL_COOKIE_LANG])) {
		set_user_lang(USER_DEFAULT_LANG); // create the cookie that doesn't exist
		
		return USER_DEFAULT_LANG;
	} else {
		// we always reset the cookie so that it's never deleted
		set_user_lang($_COOKIE[LABEL_COOKIE_LANG]);
		
		return $_COOKIE[LABEL_COOKIE_LANG];
	}
}

/*
 * @function get_user_name
 * 
 * @purpose
 * Get the username stored in the database with the provided ID.
 * 
 * @param int $id
 * The ID of the user.
 * 
 * @retval string
 * The username, or NULL if the user was not found.
 */
function get_user_name(\Demoshot\Demoshot $dm, $id) {
	assert(is_unsigned($id));
	
	$db = $dm->get_db();
	
	$query = "SELECT username FROM " . TABLE_USER . " WHERE ID = $id";
	
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
	
	return htmlspecialchars(utf8_decode($result[0]), ENT_QUOTES | ENT_HTML5);
}

/*
 * @function get_user_type
 * 
 * @retval int
 * TYPE_USER || TYPE_ADMIN
 * Or ERROR_* if the user was not found.
 */
function get_user_type(\Demoshot \Demoshot $dm, $id) {
	assert(is_unsigned($id));
	
	$db = $dm->get_db();
	
	$query = "SELECT type FROM " . TABLE_USER . " WHERE ID = $id";
	
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
			"$id"
		);
		
		return ERROR_NOT_FOUND;
	}
	
	return (int) $result[0];
}

/*
 * @function get_user_statistics
 * 
 * @purpose
 * Get the number of pictures published by a user
 * and the number of marks posted by this user.
 * 
 * @retval array(int, int)
 * GET_USER_STATS_PICTURES => the number of pictures
 * GET_USER_STATS_MARKS => the number of marks
 * GET_USER_STATS_FOLLOWERS => the number of followers
 * GET_USER_STATS_FOLLOWED => the number of followed
 * Or NULL on failure.
 */
function get_user_statistics(\Demoshot\Demoshot $dm, $id) {
	assert(is_unsigned($id));
	
	$db = $dm->get_db();
	
	// get the number of pictures
	$query = "SELECT num_of_pictures, num_of_marks, " .
		"num_of_followers, num_of_followed FROM " . TABLE_USER .
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
		$num_of_pictures = 0;
		$num_of_marks = 0;
		$num_of_followers = 0;
		$num_of_followed = 0;
	} else {
		$num_of_pictures = (int) $result[0];
		$num_of_marks = (int) $result[1];
		$num_of_followers = (int) $result[2];
		$num_of_followed = (int) $result[3];
	}
	
	
	return array(
		GET_USER_STATS_PICTURES => $num_of_pictures,
		GET_USER_STATS_MARKS => $num_of_marks,
		GET_USER_STATS_FOLLOWERS => $num_of_followers,
		GET_USER_STATS_FOLLOWED => $num_of_followed
	);
}

/*
 * @function login_with_cookie
 * 
 * @purpose
 * Connect with the cookie named LABEL_COOKIE_ID.
 * Retrieve the username.
 * 
 * @retval id
 * The ID of the user, or ERROR_ on failure.
 */
function login_with_cookie(\Demoshot\Demoshot $dm) {
	if(!isset($_COOKIE[LABEL_COOKIE_ID])) {
		$dm->set_error(
			LOGIN_WRONG_NO_COOKIE,
			LABEL_COOKIE_ID // return the name of the cookie which is needed
		);
		
		return ERROR_SIMPLE;
	}
	
	$db = $dm->get_db();
	$session = $_COOKIE[LABEL_COOKIE_ID];
	
	// retrieve the username
	$query = "SELECT ID FROM " . TABLE_USER . " WHERE tmp_session_id = '$session'";
	
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
			LOGIN_WRONG_SESSION_ID,
			$session
		);
		
		return ERROR_SIMPLE;
	}
	
	return (int) $result[0];
}

/*
 * @function login_with_credentials
 * 
 * @purpose
 * Connect with the given credentials.
 *
 * @side effects
 * If connection was successful:
 * - Generate a session ID.
 * - Store it in a cookie named LABEL_COOKIE_ID.
 * - Store it in the database, in user.tmp_session_id.
 * If connection failed:
 * - Call $dm->set_error to store information about the error encountered.
 * 
 * @retval bool
 * Equals true on success, false on failure.
 */
function login_with_credentials(\Demoshot\Demoshot $dm, $name, $passwd) {
	assert(is_string($name));
	assert(is_string($passwd));
	
	$db = $dm->get_db();
	
	// get password salt in the database
	$query = "SELECT passwd_salt FROM " . TABLE_USER . " WHERE username = " . $db->quote(utf8_encode($name));
	
	$result = $db->query($query);
	
	if(!$result) {
		$error = $db->errorInfo();
		
		$dm->set_error(
			ERROR_ON_QUERY,
			$error[2]
		);
		
		return false;
	}
	
	$result = $result->fetch(\PDO::FETCH_NUM);
	
	if(!$result) {
		$dm->set_error(
			LOGIN_WRONG_USERNAME,
			$name
		);
		
		return false;
	}
	
	// compare $passwd with the password stored in the database
	$passwd = Security\hash_data($result[0], $passwd);
	
	$query = "SELECT username FROM " . TABLE_USER .
		" WHERE passwd = '$passwd' AND username = " . $db->quote(utf8_encode($name));
	
	$result = $db->query($query);
	
	if(!$result) {
		$error = $db->errorInfo();
		
		$dm->set_error(
			ERROR_ON_QUERY,
			$error[2]
		);
		
		return false;
	}
	
	$result = $result->fetch(\PDO::FETCH_NUM);
	
	if(!$result) {
		$dm->set_error(
			LOGIN_WRONG_PASSWD
		);
		
		return false;
	}
	
	// create session id and store it in $_COOKIE and in the database
	$session = new_session_id($dm);
	
	setcookie(LABEL_COOKIE_ID, $session, time() + COOKIE_LIFETIME, NULL, NULL, false, true);
	
	$query = "UPDATE " . TABLE_USER .
		" SET tmp_session_id = '$session' WHERE username = " . $db->quote(utf8_encode($name));
	
	$db->exec($query);
	
	return true;
}

/*
 * @function new_session_id
 * 
 * @purpose
 * Return an md5-hashed session ID, based on the user agent and the IP address.
 * 
 * @retval string
 * The new session id.
 */
function new_session_id() {
	return md5(uniqid() . $_SERVER['HTTP_USER_AGENT'] . $_SERVER['REMOTE_ADDR']);
}

/*
 * @function notify_for_new_picture
 * 
 * @purpose
 * Send an email to all the users following $id to notify them that he has uploaded a new picture.
 * 
 * @param int $picture
 * The ID of the newly created picture.
 * 
 * @retval int
 * ERROR_SIMPLE on failure, or the number of notified users.
 */
function notify_for_new_picture(\Demoshot\Demoshot $dm, $id, $picture) {
	assert(is_unsigned($id));
	assert(is_unsigned($picture));
	
	// get the name of the user who has published the new picture
	$author = get_user($dm, $id);
	if(is_null($author)) {
		return ERROR_SIMPLE;
	}
	
	$author_username = $author[GET_USER_NAME];
	
	// get the title of the picture
	$picture_details = Picture\get_picture($dm, $picture);
	
	if(is_null($picture_details)) {
		return ERROR_SIMPLE;
	}
	
	$picture_title = $picture_details[GET_PICTURE_TITLE];
	
	$followers = get_user_followers($dm, $id);
	
	// get the email address of the sender (Demoshot's webmaster)
	$conf = $dm->get_conf();
	$conf = $conf['manager'];
	
	if(isset($conf['email_local_part']) && isset($conf['email_domain_part'])) {
		$sender_email = $conf['email_local_part'] . '@' . $conf['email_domain_part'];
	}
	
	$notified_users = 0;
	
	$subject = sprintf(
		_("Demoshot - %s has published a new picture"),
		$author_username
	);
	
	foreach($followers as $follower_id) {
		$follower = get_user($dm, $follower_id);
		$follower_email = $follower[GET_USER_EMAIL];
		
		$message = sprintf(
			_("Hello %s, %s has published a new picture: %s."),
			$follower[GET_USER_NAME],
			$author_username,
			$picture_title
		);
		
		if(mail($follower_email, $subject, $message, is_string($sender_email) ? "From: $sender_email" : "")) {
			$notified_users++;
		}
	}
	
	return $notified_users;
}

/*
 * @function reset_password
 * 
 * @purpose
 * If the user has forgotten his password,
 * this function will create a new one and send it to him by email.
 * 
 * @retval bool
 * True on success, false on failure.
 */
function reset_password(\Demoshot\Demoshot $dm, $username) {
	assert(is_string($username) && $username !== '');
	
	$db = $dm->get_db();
	$username = $db->quote(utf8_encode($username));
	
	// get the user's ID based on his username
	$query = "SELECT ID FROM " . TABLE_USER . " WHERE username = $username";
	
	$result = $db->query($query);
	
	if(!$result) {
		$error = $db->errorInfo();
		
		$dm->set_error(
			ERROR_ON_QUERY,
			$error[2]
		);
		
		return false;
	}
	
	$result = $result->fetch(\PDO::FETCH_NUM);
	
	if(!$result) {
		$dm->set_error(
			ERROR_NOT_FOUND,
			$username
		);
		
		return false;
	}
	
	$id = (int) $result[0];
	
	// create a new password
	$new_passwd = md5(uniqid());
	
	
	// prepare the message
	$subject = _("Demoshot - Your password has been reset");
	
	$message = sprintf(
		_("Hello %s, your new password is %s. We recommend that you change it."),
		$username, $new_passwd
	);
	
	
	// send the email
	if(!send_email($dm, $id, $subject, $message)) {
		return false;
	}
	
	// change the password
	$ok = edit_account($dm, $id, $new_passwd);
	if(!$ok) {
		return false;
	}
	
	return true;
}

/*
 * @function send_email
 * 
 * @purpose
 * Send an email to a user, from the webmaster.
 * 
 * @param int $id
 * The ID of the recipient.
 * 
 * @retval bool
 * True on success, false on failure.
 */
function send_email(\Demoshot\Demoshot $dm, $id, $subject, $message) {
	assert(is_unsigned($id));
	assert(is_string($subject));
	assert(is_string($message));
	
	// get the email address of the user
	$address = get_user_email($dm, $id);
	if(is_null($address)) {
		return false;
	}
	
	// get the email address of the sender
	$conf = $dm->get_conf();
	$conf = $conf['manager'];
	
	$sender = NULL;
	if(isset($conf['email_local_part']) && isset($conf['email_domain_part'])) {
		$sender = $conf['email_local_part'] . '@' . $conf['email_domain_part'];
	}
	
	// send the message
	if(!mail($address, $subject, $message, is_string($sender) ? "From: $sender" : "")) {
		return false;
	}
	
	return true;
}

/*
 * @function send_email_to_webmaster
 * 
 * @purpose
 * Send an email to the webmaster, from an existing user or a logged out visitor.
 * 
 * @param int $from
 * The ID of the sender, or -1 if he is not logged in.
 * 
 * @retval bool
 * True on success, false on failure.
 */
function send_email_to_webmaster(\Demoshot\Demoshot $dm, $from, $subject, $message) {
	assert(is_int($from));
	assert(is_string($subject));
	assert(is_string($message));
	
	// get the email address of the webmaster
	$conf = $dm->get_conf();
	$conf = $conf['manager'];
	
	$address = NULL;
	if(isset($conf['email_local_part']) && isset($conf['email_domain_part'])) {
		$address = $conf['email_local_part'] . '@' . $conf['email_domain_part'];
	}
	
	if(is_null($address)) {
		return false;
	}
	
	// get the email address of the sender
	$sender = NULL;
	if($from >= 0) {
		$sender = get_user_email($dm, $from);
	}
	
	$subject = "Demoshot - $subject";
	
	// send the message
	if(!mail($address, $subject, $message, is_string($sender) ? "From: $sender" : "")) {
		return false;
	}
	
	return true;
}

/*
 * @function set_user_lang
 * 
 * @purpose
 * Set the cookie named LABEL_COOKIE_LANG to the provided language.
 * 
 * @param string $lang
 * One of the USER_LANG_* constants.
 */
function set_user_lang($lang) {
	assert(is_string($lang));
	
	setcookie(LABEL_COOKIE_LANG, $lang, time() + COOKIE_LIFETIME, NULL, NULL, false, true);
}

/*
 * @function signup
 * 
 * @purpose
 * - Create a new user in the database with the given credentials.
 * - Create a new directory named after the user's ID.
 * 
 * @param int $type
 * TYPE_USER || TYPE_ADMIN
 * 
 * @param bool $private_pictures
 * If true, any new picture uploaded by the user
 * will be private by default.
 * 
 * @param bool $private_comments
 * If true, any new comment posted by the user
 * will be private by default.
 * 
 * @param string $description
 * It must be shorter than 256 chars.
 * 
 * @retval bool
 * False on failure, true on success.
 */
function signup(

	// the long list of parameters...
	\Demoshot\Demoshot $dm,
	$name, $passwd,
	$type, $email,
	$private_pictures = false,
	$private_comments = false,
	$description = NULL
	
) {
	// preconditions
	assert(is_string($name));
	assert(is_string($passwd));
	assert(is_enum($type, TYPE_USER, TYPE_ADMIN));
	assert(is_string($email));
	assert(is_bool($private_pictures));
	assert(is_bool($private_comments));
	assert(is_null($description) || is_string($description));
	
	$db = $dm->get_db();
	
	
	// prepare credentials
	$name = $db->quote(utf8_encode($name));
	$email = $db->quote(utf8_encode($email));
	
	
	// make sure the username is available
	$query = "SELECT ID FROM " . TABLE_USER . " WHERE username = $name";
	
	$result = $db->query($query);
	
	if(!$result) {
		$error = $db->errorInfo();
		
		$dm->set_error(
			ERROR_ON_QUERY,
			$error[2]
		);
		
		return false;
	}
	
	$result = $result->fetch(\PDO::FETCH_NUM);
	
	if($result != NULL) {
		$dm->set_error(
			SIGNUP_USERNAME_EXISTS,
			utf8_decode($name)
		);
		
		return false;
	}
	
	
	// make sure the email is available
	$query = "SELECT ID FROM " . TABLE_USER . " WHERE email = $email";
	
	$result = $db->query($query);
	
	if(!$result) {
		$error = $db->errorInfo();
		
		$dm->set_error(
			ERROR_ON_QUERY,
			$error[2]
		);
		
		return false;
	}
	
	$result = $result->fetch(\PDO::FETCH_NUM);
	
	if($result != NULL) {
		$dm->set_error(
			SIGNUP_EMAIL_EXISTS,
			utf8_decode($email)
		);
		
		return false;
	}
	
	
	// prepare the password (salt it and hash it)
	$passwd_salt = Security\new_salt();
	$passwd = Security\hash_data($passwd_salt, $passwd);
	
	$private_pictures = (int) $private_pictures;
	$private_comments = (int) $private_comments;
	
	if(is_string($description)) {
		// check the length of the description
		$conf = $dm->get_conf();
		$conf = $conf['database'];
		
		if(!isset($conf['max_description_length'])) {
			$dm->set_error(
				ERROR_MISSING_INFORMATION,
				'Demoshot.ini["database"]["max_description_length"]'
			);
			
			return false;
		}
		
		if(strlen($description) > $conf['max_description_length']) {
			$dm->set_error(
				ERROR_TEXT_OVERFLOW,
				$description
			);
			
			return false;
		}
		
		$description = $db->quote(utf8_encode($description));
	}
	
	// prepare query
	$query = 'INSERT INTO ' . TABLE_USER .
		'(ID, username, passwd, passwd_salt, ' .
		'email, private_pictures, private_comments, ' .
		'description, type, tmp_session_id, date, ' .
		'num_of_pictures, num_of_marks, num_of_followers, num_of_followed ' .
		') VALUES ' .
		
		"(NULL, $name, '$passwd', '$passwd_salt', " .
		"$email, $private_pictures, $private_comments, " .
		(is_null($description) ? "NULL" : "$description") . ", " .
		"$type, NULL, NOW(), 0, 0, 0, 0)";
	
	$result = $db->exec($query);
	
	if($result === false || $result === 0) {
		$error = $db->errorInfo();
		
		$dm->set_error(
			ERROR_ON_QUERY,
			$error[2]
		);
		
		return false;
	}
	
	// create a directory for the new user
	$id = (int) $db->lastInsertId();
	$path = get_user_dir($dm, $id);
	
	if(!mkdir($path)) {
		$dm->set_error(
			SIGNUP_WRONG_MKDIR,
			$path
		);
		
		// remove the newly created account from the database
		$query = "DELETE FROM " . TABLE_USER . " WHERE ID = $id";
		$db->exec($query);
		
		return false;
	}
	
	return true;
}

/*
 * @function stop_being_followed
 * 
 * @purpose
 * The user won't be followed by any other user anymore.
 * 
 * @param int $id
 * The ID of the user.
 * 
 * @retval int
 * The number of followers removed,
 * or ERROR_* on failure.
 */
function stop_being_followed(\Demoshot\Demoshot $dm, $id) {
	assert(is_unsigned($id));
	
	
	$db = $dm->get_db();
	
	
	$followers_array = get_user_followers($dm, $id);
	foreach($followers_array as $follower) {
		/*
		 * Update the num_of_followed of each follower.
		 */
		
		$query = "UPDATE " . TABLE_USER .
			" SET num_of_followed = num_of_followed - 1" .
			" WHERE ID = $follower";
		
		$db->exec($query);
	}
	
	/*
	 * Update the num_of_followers of the user.
	 */
	
	$query = "UPDATE " . TABLE_USER .
		" SET num_of_followers = 0" .
		" WHERE ID = $id";
	
	$db->exec($query);
	
	$query = "DELETE FROM " . TABLE_FOLLOWER .
		" WHERE followed_id = $id";
	
	$result = $db->exec($query);
	
	if($result === false) {
		$error = $db->errorInfo();
		
		$dm->set_error(
			ERROR_ON_QUERY,
			$error[2]
		);
		
		return ERROR_ON_QUERY;
	}
	
	return $result;
}

/*
 * @function stop_following
 * 
 * @purpose
 * Make an user stop following another user.
 * 
 * @param int $follower
 * The ID of the follower.
 * 
 * @param int followed
 * The ID of the followed user.
 * If NULL, then the follower will stop following any other user.
 * 
 * @retval int
 * The number of removed connections,
 * or ERROR_* on failure.
 */
function stop_following(\Demoshot\Demoshot $dm, $follower, $followed = NULL) {
	assert(is_unsigned($follower));
	assert(is_unsigned($followed) || is_null($followed));
	
	
	$db = $dm->get_db();	
	
	if(is_null($followed)) {
		
		/*
		 * The user will stop following any other user.
		 */
		
		$followed_array = get_user_followed($dm, $follower);
		foreach($followed_array as $followed) {
			
			/*
			 * Update the num_of_followers of each followed user.
			 */
			
			$query = "UPDATE " . TABLE_USER .
				" SET num_of_followers = num_of_followers - 1" .
				" WHERE ID = $followed";
			
			$db->exec($query);
			
		}
		
		$query = "DELETE FROM " . TABLE_FOLLOWER .
			" WHERE follower_id = $follower";
		
		$result = $db->exec($query);
		
		if($result === false) {
			$error = $db->errorInfo();
			
			$dm->set_error(
				ERROR_ON_QUERY,
				$error[2]
			);
			
			return ERROR_ON_QUERY;
		}
		
		/*
		 * Update the num_of_followed of the follower.
		 */
		$query = "UPDATE " . TABLE_USER .
			" SET num_of_followed = 0" .
			" WHERE ID = $follower";
		
		$db->exec($query);
		
		return count($followed_array);
		
	} else {
		
		$query = "DELETE FROM " . TABLE_FOLLOWER .
			" WHERE follower_id = $follower AND" .
			" followed_id = $followed";
		
		$result = $db->exec($query);
		
		if($result === false) {
			$error = $db->errorInfo();
			
			$dm->set_error(
				ERROR_ON_QUERY,
				$error[2]
			);
			
			return ERROR_ON_QUERY;
		}
		
		/*
		 * Update the num_of_followers of the followed user
		 * and the num_of_followed of the follower.
		 */
		$query = "UPDATE " . TABLE_USER .
			" SET num_of_followers = num_of_followers - 1" .
			" WHERE ID = $followed";
		
		$db->exec($query);
		
		$query = "UPDATE " . TABLE_USER .
			" SET num_of_followed = num_of_followed - 1" .
			" WHERE ID = $follower";
		
		$db->exec($query);
		
		
		return 1;
	}
}


/*
 * CLASSES
 */


/*
 * @class UserRetriever
 * 
 * @purpose
 * This class can be used in a foreach loop to retrieve the ID of a series of users.
 * These users can be retrieved according to the tag they follow,
 * the user they are following or the user who is following them,
 * the picture they marked.
 */
class UserRetriever implements \Iterator {
	
	
	/*
	 * MEMBERS
	 */

	/*
	 * @member int $position
	 * The index of the current user.
	 */
	private $position = 0;
	
	/*
	 * @member array(int) $array
	 * Each case contains the ID of a user.
	 */
	private $array;
	
	
	/*
	 * SPECIAL METHODS
	 */
	
	/*
	 * @constructor
	 * 
	 * @param int $id
	 * Either the ID of a user to retrieve all the users he follows,
	 * or the ID of a user to retrieve all the users who are following him,
	 * or the ID of a tag to retrieve all the users interested in this tag,
	 * or an array of ID of tags to retrieve all the users interested in them,
	 * or the ID of a picture to retrieve all the users who marked it,
	 * NULL to retrieve all existing users.
	 * 
	 * @param int $retrieve
	 * RETRIEVE_USERS_BY_FOLLOWED || RETRIEVE_USERS_BY_FOLLOWER ||
	 * RETRIEVE_USERS_BY_PICTURE || RETRIEVE_USERS_BY_TAG
	 * RETRIEVE_ALL_USERS if we set $id to NULL.
	 * 
	 * @param int $sort_by
	 * One of the constants enumerated as USER_SORT_BY_*.
	 * 
	 * @param bool $sort_asc
	 * If true, sort the users in ascending order.
	 * Else, sort them in descending order.
	 * 
	 * @param string $like
	 * A pattern to look for in the usernames, or NULL.
	 * 
	 * @param int $limit
	 * Limit the number of results. -1 for no limit.
	 * 
	 * @param int $start_from
	 * Start from the ?th result. The first result has index 0.
	 * 
	 * @param string $description_like
	 * A pattern to look for in the descriptions of the users, or NULL.
	 * 
	 * @param bool $join_tags
	 * When an array of tag ID-s are provided through $id,
	 * if $join_tags, look for pictures related to all of them,
	 * else, look for pictures related to any of them.
	 * 
	 * @param bool $join_likes
	 * When we are looking for a pattern in the username and a pattern in the description,
	 * if $join_likes, look for users whose name contains $like AND whose description contains $description_like,
	 * else, look for pictures whose name contains $like OR whose description contains $description_like.
	 */
	public function __construct(
	
		// the long list of parameters...
		\Demoshot\Demoshot $dm,
		$id = NULL, $retrieve = RETRIEVE_ALL_USERS,
		$sort_by = USER_SORT_BY_NONE, $sort_asc = true,
		$limit = -1, $start_from = -1,
		$like = NULL, $description_like = NULL,
		$join_tags = false, $join_likes = false
	
	) {
		assert(is_null($id) || is_unsigned($id) || is_array($id));
		assert(is_enum($retrieve, RETRIEVE_USERS_BY_FOLLOWED, RETRIEVE_ALL_USERS));
		assert(is_enum($sort_by, USER_SORT_BY_NONE, USER_SORT_BY_RANDOM));
		assert(is_bool($sort_asc));
		assert(is_int($limit));
		assert(is_int($start_from) && $start_from >= -1);
		assert(is_null($like) || is_string($like));
		assert(is_null($description_like) || is_string($description_like));
		assert(is_bool($join_tags));
		assert(is_bool($join_likes));
		
		$db = $dm->get_db();
		
		$this->position = 0;
		
		/*
		 * Retrieve the users.
		 */
		$query = "SELECT DISTINCT(ID) FROM " . TABLE_USER;
		$no_where = true;
		
		
		if($retrieve != RETRIEVE_ALL_USERS) {
			switch($retrieve) {
				case RETRIEVE_USERS_BY_FOLLOWED:
					/*
					 * We will retrieve all the users following the user
					 * whose ID = $id.
					 */
					
					assert(is_unsigned($retrieve));
					
					$query .= " INNER JOIN " . TABLE_FOLLOWER .
						" ON " . TABLE_FOLLOWER . ".follower_id = " . TABLE_USER . ".ID" .
						" WHERE " . TABLE_FOLLOWER . ".followed_id = $id";
					
					$no_where = false;
					break;
				
				case RETRIEVE_USERS_BY_FOLLOWER:
					/*
					 * We will retrieve all the users followed by the user
					 * whose ID = $id.
					 */
					
					assert(is_unsigned($retrieve));
					
					$query .= " INNER JOIN " . TABLE_FOLLOWER .
						" ON " . TABLE_FOLLOWER . ".followed_id = " . TABLE_USER . ".ID" .
						" WHERE " . TABLE_FOLLOWER . ".follower_id = $id";
					
					$no_where = false;
					break;
				
				case RETRIEVE_USERS_BY_PICTURE:
					/*
					 * We will retrieve all the users who have marked the picture
					 * whose ID = $id.
					 */
					
					assert(is_unsigned($retrieve));
					
					$query .= " INNER JOIN " . TABLE_MARK .
						" ON " . TABLE_MARK . ".author_id = " . TABLE_USER . ".ID" .
						" WHERE " . TABLE_MARK . ".picture_id = $id";
						
					$no_where = false;
					break;
				
				case RETRIEVE_USERS_BY_TAG:
					/*
					 * We will retrieve all the users who are related to the tag(s)
					 * whose ID(s) are (listed in) $id.
					 */
					
					assert(is_unsigned($id) || is_array($id));
					
					if(is_array($id)) {
						
						if($join_tags) {
							/*
							 * Select the users interested in all the tags.
							 * To do so, we need to do as many joins on the user_tag table
							 * as there are different tags in the array $id.
							 * 
							 * E.g: we want to retrieve the users interested in tags 1, 3 and 42.
							 * The query will look like this:
							 * 
							 * SELECT ID from user
							 *      INNER JOIN user_tag tag1 ON tag1.user_id = user.id
							 *      INNER JOIN user_tag tag2 ON tag2.user_id = user.id
							 *      INNER JOIN user_tag tag3 ON tag3.user_id = user.id
							 *      WHERE tag1.tag_id = 1 AND tag2.tag_id = 3 AND tag3.tag_id = 42;
							 */
							
							// first, we add the INNER JOIN bits
							for($it = 0, $c = count($id); $it < $c; $it++) {
								assert(is_unsigned($id[$it]));
								
								$query .= " INNER JOIN " . TABLE_USER_TAG . " tag$it" .
									" ON tag$it.user_id = " . TABLE_USER . ".ID";
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
							
							$query .= " INNER JOIN " . TABLE_USER_TAG .
								" ON " . TABLE_USER_TAG . ".user_id = " . TABLE_USER . ".ID AND " .
								TABLE_USER_TAG . ".tag_id IN($list)";
						}
						
					} else {
						/*
						 * Only one tag is required.
						 */
						
						$query .= " INNER JOIN " . TABLE_USER_TAG .
							" ON " . TABLE_USER_TAG . ".user_id = " . TABLE_USER . ".ID" .
							" AND " . TABLE_USER_TAG . ".tag_id = $id";
					}
					
					break;
			}
		}
		
		
		/*
		 * Search for a pattern in the username and/or in the description.
		 */
		if(is_string($like)) {
			$query .= $no_where ? " WHERE" : " AND";
			
			$query .= " (username LIKE " . $db->quote(utf8_encode("%$like%"));
			
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
		 * Sort the results.
		 */
		switch($sort_by) {
			case USER_SORT_BY_ID: $sort_field = "ID"; break;
			case USER_SORT_BY_RANDOM: $sort_field = "RAND()"; break;
			case USER_SORT_BY_USERNAME: $sort_field = "username"; break;
			case USER_SORT_BY_DATE: $sort_field = TABLE_USER . ".date"; break;
			case USER_SORT_BY_NUM_OF_FOLLOWED: $sort_field = "num_of_followed"; break;
			case USER_SORT_BY_NUM_OF_FOLLOWERS: $sort_field = "num_of_followers"; break;
			case USER_SORT_BY_NUM_OF_MARKS: $sort_field = "num_of_marks"; break;
			case USER_SORT_BY_NUM_OF_PICTURES: $sort_field = "num_of_pictures"; break;
			
			case USER_SORT_BY_POPULARITY:
				$sort_field = "num_of_followers";
				$sort_field .= $sort_asc ? " ASC" : " DESC";
				$sort_field .= ", num_of_followed"; break;
			
			case USER_SORT_BY_INVOLVEMENT: $sort_field = "num_of_marks + num_of_pictures"; break;
			
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
