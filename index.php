<?php


/*
 * MAIN OBJECTS
 */

require_once('core/Demoshot.php');

$demoshot = new Demoshot\Demoshot();
$demoshot_var = array();


/*
 * DEPENDENCIES
 */

require_once('core/utils/database.php');

require_once('core/utils/user.php');
use Demoshot\User;

require_once('core/utils/pages.php');
use Demoshot\Pages;

require_once('core/utils/errors.php');
use Demoshot\Errors;

require_once('core/utils/security.php');
use Demoshot\Security;

require_once('core/utils/tag.php');
use Demoshot\Tag;

require_once('core/utils/picture.php');
use Demoshot\Picture;

require_once('core/utils/mark.php');
use Demoshot\Mark;


/*
 * PHP CONFIGURATION
 */

// debug mode ON
assert_options(ASSERT_ACTIVE, 1);
assert_options(ASSERT_BAIL, 1);


/*
 * MAIN FLOW
 */

/*
 * Make sure Demoshot has been correctly initialized.
 */
if(!$demoshot->get_ok()) {
	$demoshot->dump_error();
	die;
}

$conf = $demoshot->get_conf();


/*
 * Select the right page, based on the URL argument named page_id.
 * Check it's not an illegal value.
 */
$page_id = PAGE_ID_HOME;

if(isset($_GET['page_id'])) {
	$page_id = (int) $_GET['page_id'];
	
	if($page_id < 0 || $page_id > PAGE_ID_404) {
		$page_id = PAGE_ID_404;
	}
}


/*
 * Login process (ignored if the user has tried to sign up or to delete his account):
 * 
 * Check if the user has filled in a login form to authenticate.
 * 
 * If he hasn't, check with the cookies that the user is not already logged in.
 * 
 * If he isn't, then he isn't logged in.
 */
$demoshot_var[PAGE_VAR_USERID] = -1; // by default, the user is logged out
$demoshot_var[PAGE_VAR_USERNAME] = _("Guest");


// has the user filled a login form?
$user = NULL; $passwd = NULL;

if(isset($_POST['ln_username'])) {
	$user = $_POST['ln_username'];
}

if(isset($_POST['ln_password'])) {
	$passwd = $_POST['ln_password'];
}

// if the user hasn't tried to sign up
if(is_null($user) || is_null($passwd)) {
	
	// no credentials? Try the cookies
	$user = User\login_with_cookie($demoshot);
	
	if($user < 0) {
		$demoshot->clear_error(); // the user is definitely not logged in
		
	} else {
		$demoshot_var[PAGE_VAR_USERID] = $user;
		
		$demoshot_var[PAGE_VAR_USERNAME] = User\get_user_name($demoshot, $user);
	}
} else {
	
	// try to login with the provided credentials
	$logged_in = User\login_with_credentials($demoshot, $user, $passwd);
	
	if($logged_in) {
		$demoshot_var[PAGE_VAR_USERID] = User\get_user_id($demoshot, $user);
		
		$demoshot_var[PAGE_VAR_USERNAME] = $user;
		
	} else {
		$errcode = $demoshot->get_errcode();
		if($errcode === ERROR_ON_QUERY) {
			$page_id = PAGE_ID_DATABASE_ERROR;
		}
		
		/*
		 * The login failed, redirect the user to login page,
		 * or maintenance page if the website is undergoing maintenance.
		 */
		if($conf['state']['maintenance'] == "1") {
			$page_id = PAGE_ID_MAINTENANCE;
		} else {
			$page_id = PAGE_ID_LOGIN;
		}

	}
}

/*
 * End of login process.
 */


// has the user filled an account deletion form?
if(isset($_POST['us_deletion_password'])) {
	$ok = User\check_password(
		$demoshot,
		$demoshot_var[PAGE_VAR_USERID],
		$_POST['us_deletion_password']
	);
	
	if($ok) {
		User\disconnect($demoshot, $user);
		User\delete_account($demoshot, $user);
		
		/*
		 * The user has deleted his account.
		 * Now he's logged out, we redirect him to the home page.
		 */
		
		$page_id = PAGE_ID_HOME;
		
		$demoshot_var[PAGE_VAR_USERID] = -1;
		$demoshot_var[PAGE_VAR_USERNAME] = _("Guest");
		
		$user = -1;
	}
}


// does the user want to log out?
if(isset($_GET['logout']) && $user >= 0) {
	User\disconnect($demoshot, $user);
	
	$demoshot_var[PAGE_VAR_USERID] = -1;
	$demoshot_var[PAGE_VAR_USERNAME] = _("Guest");
}


// is the user the administrator of the website?
if($demoshot_var[PAGE_VAR_USERID] != -1) {
	$type = User\get_user_type($demoshot, $demoshot_var[PAGE_VAR_USERID]);
	
	$demoshot_var[PAGE_VAR_ADMIN] = $type == TYPE_ADMIN;
} else {
	$demoshot_var[PAGE_VAR_ADMIN] = false;
}


/*
 * If the website is in maintenance and the visitor is not the webmaster,
 * redirect him to the maintenance page.
 */
if($conf['state']['maintenance'] == "1" && !$demoshot_var[PAGE_VAR_ADMIN]) {
	$page_id = PAGE_ID_MAINTENANCE;
}


/*
 * Check whether the user can access to the page.
 * If he can't redirect him to the 404 error page.
 * 
 * E.g, if the current page is the profile of a user, replace the page title with the username.
 * If an argument is missing (e.g. the ID of the user), it is considered as a 404 error.
 */
$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
switch($page_id) {
	
	
	case PAGE_ID_RESET_PASSWD:
	case PAGE_ID_SIGNUP:
		/* The signup or password reset page can be unavailable because:
		 * - The user is connected
		 */
		if($demoshot_var[PAGE_VAR_USERID] != -1) {
			$page_id = PAGE_ID_403;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
		}
		
		break;
	
	
	
	case PAGE_ID_USER_ALBUM:
	case PAGE_ID_PROFILE:
		/*
		 * The album or profile page can be unavailable because:
		 * - There is no PAGE_ARG_USER_ID
		 * - PAGE_ARG_USER_ID < 0
		 * - The referred user doesn't exist
		 */
		
		if(!isset($_GET[PAGE_ARG_USER_ID])) { // no argument
			$page_id = PAGE_ID_404;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
			break;
		}
		
		
		$user_id = (int) $_GET[PAGE_ARG_USER_ID];
		
		if($user_id < 0) { // invalid argument
			$page_id = PAGE_ID_404;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
			break;
		}
		
		
		$username = User\get_user_name($demoshot, (int) $_GET[PAGE_ARG_USER_ID]);
		
		if(is_null($username)) { // the user doesn't exist
			$errcode = $demoshot->get_errcode();
			
			if($errcode == ERROR_ON_QUERY) {
				$page_id = PAGE_ID_DATABASE_ERROR;
			} else { // ERROR_NOT_FOUND
				$page_id = PAGE_ID_404;
			}
			
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
			break;
		}
		
		if($page_id == PAGE_ID_USER_ALBUM) {
			$demoshot_var[PAGE_VAR_TITLE] = sprintf(
				_("%s's album"),
				$username
			);
		} else {
			$demoshot_var[PAGE_VAR_TITLE] = $username;
		}
		
		break;
	
	
	
	case PAGE_ID_MARK:
		/*
		 * The new mark page can be unavailable because:
		 * - There is no PAGE_ARG_PICTURE_ID
		 * - PAGE_ARG_PICTURE_ID < 0
		 * - The referred picture doesn't exist
		 * - The visitor is not logged in
		 * - The visitor is the owner of the picture
		 */
		
		if(!isset($_GET[PAGE_ARG_PICTURE_ID])) { // no argument
			$page_id = PAGE_ID_404;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
			break;
		}
		
		
		$picture_id = (int) $_GET[PAGE_ARG_PICTURE_ID];
		
		if($picture_id < 0) { // invalid argument
			$page_id = PAGE_ID_404;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
			break;
		}
		
		
		$picture = Picture\get_picture($demoshot, $picture_id);
		
		if(is_null($picture)) { // the picture doesn't exist
			$errcode = $demoshot->get_errcode();
			
			if($errcode == ERROR_ON_QUERY) {
				$page_id = PAGE_ID_DATABASE_ERROR;
			} else { // ERROR_NOT_FOUND
				$page_id = PAGE_ID_404;
			}
			
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
			
			break;
		}
		
		
		if($demoshot_var[PAGE_VAR_USERID] == -1) { // only a logged in user can mark a picture
			$page_id = PAGE_ID_403;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
			break;
		}
		
		
		if($demoshot_var[PAGE_VAR_USERID] == $picture[GET_PICTURE_AUTHOR]) { // the visitor is the owner of the picture
			$page_id = PAGE_ID_403;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
			break;
		}
		
		
		break;
	
	
	
	case PAGE_ID_NEW_PICTURE:
		/*
		 * The new picture page can be unavailable because:
		 * - The user is not logged in.
		 */
		
		if($demoshot_var[PAGE_VAR_USERID] == -1) {
			$page_id = PAGE_ID_403;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
		}
		
		break;
	
	
	
	case PAGE_ID_EDIT_PICTURE:
		/*
		 * The edit picture page can be unavailable because:
		 * - There is no PAGE_ARG_PICTURE_ID
		 * - PAGE_ARG_PICTURE_ID < 0
		 * - The referred picture doesn't exist
		 * - The visitor is not the owner of the picture
		 */
		
		if(!isset($_GET[PAGE_ARG_PICTURE_ID])) { // no argument
			$page_id = PAGE_ID_404;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
			break;
		}
		
		
		$picture_id = (int) $_GET[PAGE_ARG_PICTURE_ID];
		
		if($picture_id < 0) { // invalid argument
			$page_id = PAGE_ID_404;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
			break;
		}
		
		
		$picture = Picture\get_picture($demoshot, $picture_id);
		
		if(is_null($picture)) { // the picture doesn't exist
			$errcode = $demoshot->get_errcode();
			
			if($errcode == ERROR_ON_QUERY) {
				$page_id = PAGE_ID_DATABASE_ERROR;
			} else { // ERROR_NOT_FOUND
				$page_id = PAGE_ID_404;
			}
			
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
			
			break;
		}
		
		
		if($demoshot_var[PAGE_VAR_USERID] != $picture[GET_PICTURE_AUTHOR]) { // the visitor is not the owner
			$page_id = PAGE_ID_403;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
		}
		
		
		break;
	
	
	
	case PAGE_ID_PICTURE:
		/*
		 * The picture page can be unavailable because:
		 * - There is no PAGE_ARG_PICTURE_ID
		 * - PAGE_ARG_PICTURE_ID < 0
		 * - The referred picture doesn't exist
		 */
		
		if(!isset($_GET[PAGE_ARG_PICTURE_ID])) { // no argument
			$page_id = PAGE_ID_404;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
			break;
		}
		
		
		$picture_id = (int) $_GET[PAGE_ARG_PICTURE_ID];
		
		if($picture_id < 0) { // invalid argument
			$page_id = PAGE_ID_404;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
			break;
		}
		
		
		$picture_title = Picture\get_picture_title($demoshot, $picture_id);
		
		if(is_null($picture_title)) { // the picture doesn't exist
			$errcode = $demoshot->get_errcode();
			
			if($errcode == ERROR_ON_QUERY) {
				$page_id = PAGE_ID_DATABASE_ERROR;
			} else { // ERROR_NOT_FOUND
				$page_id = PAGE_ID_404;
			}
			
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
			
		} else { // all OK
			$demoshot_var[PAGE_VAR_TITLE] = $picture_title;
		}
	
		break;
	
	
	
	case PAGE_ID_TAG:
		/*
		 * The tag page can be unavailable because:
		 * - There is no PAGE_ARG_TAG_ID
		 * - PAGE_ARG_TAG_ID < 0
		 * - The referred tag doesn't exist
		 */
		
		if(!isset($_GET[PAGE_ARG_TAG_ID])) { // no argument
			$page_id = PAGE_ID_404;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
			break;
		}
		
		
		$tag_id = (int) $_GET[PAGE_ARG_TAG_ID];
		
		if($tag_id < 0) { // invalid argument
			$page_id = PAGE_ID_404;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
			break;
		}
		
		
		$tag_title = Tag\get_tag_title($demoshot, $tag_id);
		
		if(is_null($tag_title)) { // the referred tag doesn't exist
			$page_id = PAGE_ID_404;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
			break;
		}
		
		$demoshot_var[PAGE_VAR_TITLE] = $tag_title;
		
		break;
	
	
	
	case PAGE_ID_SEARCH_RESULTS:
		/*
		 * The result page can be unavailable because:
		 * - There is no SEARCH_TARGET
		 * - SEARCH_TARGET != TABLE_CODE_USER and != TABLE_CODE_PICTURE
		 * - These is a SEARCH_VISIBILITY but the user is not logged in
		 */
		
		if(!isset($_GET[SEARCH_TARGET])) {
			$page_id = PAGE_ID_404;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
		}
		
		$search_target = (int) $_GET[SEARCH_TARGET];
		
		if($search_target != TABLE_CODE_PICTURE && $search_target != TABLE_CODE_USER) {
			$page_id = PAGE_ID_404;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
		}
		
		if(isset($_GET[SEARCH_VISIBILITY]) && (int) $demoshot_var[PAGE_VAR_USERID] == -1) {
			$page_id = PAGE_ID_403;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
		}
		
		break;
	
	
	
	case PAGE_ID_USER_SETTINGS:
		/*
		 * The user settings page can be unavailable because:
		 * - There is no PAGE_ARG_USER_ID
		 * - PAGE_ARG_USER_ID < 0
		 * - The current user is not logged in
		 * - The current user is not the same as the one whose settings are targeted
		 */
		
		if(!isset($_GET[PAGE_ARG_USER_ID])) { // no argument
			$page_id = PAGE_ID_404;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
			break;
		}
		
		
		$user_id = (int) $_GET[PAGE_ARG_USER_ID];
		
		if($user_id < 0) { // invalid argument
			$page_id = PAGE_ID_404;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
			break;
		}
		
		
		$username = User\get_user_name($demoshot, $user_id);
		
		if(is_null($username)) { // the user doesn't exist
			$errcode = $demoshot->get_errcode();
			
			if($errcode == ERROR_ON_QUERY) {
				$page_id = PAGE_ID_DATABASE_ERROR;
			} else { // ERROR_NOT_FOUND
				$page_id = PAGE_ID_404;
			}
			
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
			break;
		}
		
		
		if($user_id != $demoshot_var[PAGE_VAR_USERID]) { // access forbidden
			$page_id = PAGE_ID_403;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
			break;
		}
			
		$demoshot_var[PAGE_VAR_TITLE] = $username;
		break;
	
	
	
	case PAGE_ID_MANAGE:
		/*
		 * Demoshot management page can be unavailable because:
		 * - The visitor is not the webmaster
		 */
		
		if(!$demoshot_var[PAGE_VAR_ADMIN]) {
			$page_id = PAGE_ID_403;
			$demoshot_var[PAGE_VAR_TITLE] = $page_titles[$page_id];
		}
		
		break;

}


/*
 * ASSEMBLING THE PAGE
 */

require_once('core/templates/skel/header.php');
require_once('core/templates/skel/navbar.php');

require_once('core/templates/pages/' . $page_filenames[$page_id] . '.php');

require_once('core/templates/skel/footer.php');

?>
