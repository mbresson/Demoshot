<?php namespace Demoshot\Pages;


/*
 * DEPENDENCIES
 */

require_once('misc.php');

require_once('errors.php');
use \Demoshot\Errors;


/*
 * CONSTANTS
 */

/*
 * These constants are used as indexes in the array $demoshot_var,
 * which must be set before the page is assembled.
 */
$enumi = 0;
define("PAGE_VAR_ADMIN", $enumi++); // true if the user is the administrator
define("PAGE_VAR_TITLE", $enumi++);
define("PAGE_VAR_USERID", $enumi++); // -1 if the user is not logged in
define("PAGE_VAR_USERNAME", $enumi++);


/*
 * These constants define the page which will be sent to the client.
 * Most of these constants are associated with a php file in core/template.
 * PAGE_ID_404 must remain the last one.
 */
$enumi = 0;
define("PAGE_ID_HOME", $enumi++);
define("PAGE_ID_SEARCH", $enumi++); // the search form
define("PAGE_ID_SEARCH_RESULTS", $enumi++); // the search results
define("PAGE_ID_ABOUT", $enumi++); // about Demoshot
define("PAGE_ID_HELP", $enumi++); // Frequently Asked Questions
define("PAGE_ID_SIGNUP", $enumi++);
define("PAGE_ID_LOGIN", $enumi++);
define("PAGE_ID_PROFILE", $enumi++);
define("PAGE_ID_PICTURE", $enumi++);
define("PAGE_ID_NEW_PICTURE", $enumi++);
define("PAGE_ID_EDIT_PICTURE", $enumi++);
define("PAGE_ID_MARK", $enumi++);
define("PAGE_ID_TAG", $enumi++);
define("PAGE_ID_USER_SETTINGS", $enumi++);
define("PAGE_ID_USER_ALBUM", $enumi++);
define("PAGE_ID_RESET_PASSWD", $enumi++);
define("PAGE_ID_DATABASE_ERROR", $enumi++);
define("PAGE_ID_MAINTENANCE", $enumi++); // the website is undergoing maintenance
define("PAGE_ID_MANAGE", $enumi++); // administration page, only the webmaster can access it
define("PAGE_ID_403", $enumi++); // access forbidden
define("PAGE_ID_404", $enumi++); // the page doesn't exist


/*
 * The filenames of each page.
 * They all start with tpl_ to be not mistaken for a core/utils/ php file.
 */
$page_filenames = array (
	PAGE_ID_HOME => "tpl_home",
	PAGE_ID_SEARCH => "tpl_search",
	PAGE_ID_SEARCH_RESULTS => "tpl_search_results",
	PAGE_ID_ABOUT => "tpl_about",
	PAGE_ID_HELP => "tpl_help",
	PAGE_ID_SIGNUP => "tpl_signup",
	PAGE_ID_LOGIN => "tpl_login",
	PAGE_ID_PROFILE => "tpl_profile",
	PAGE_ID_PICTURE => "tpl_picture",
	PAGE_ID_NEW_PICTURE => "tpl_new_picture",
	PAGE_ID_EDIT_PICTURE => "tpl_edit_picture",
	PAGE_ID_MARK => "tpl_mark",
	PAGE_ID_TAG => "tpl_tag",
	PAGE_ID_USER_SETTINGS => "tpl_user_settings",
	PAGE_ID_USER_ALBUM => "tpl_user_album",
	PAGE_ID_RESET_PASSWD => "tpl_reset_passwd",
	PAGE_ID_DATABASE_ERROR => "tpl_db_error",
	PAGE_ID_MAINTENANCE => "tpl_maintenance",
	PAGE_ID_MANAGE => "tpl_manage",
	PAGE_ID_403 => "tpl_403",
	PAGE_ID_404 => "tpl_404",
);

/*
 * We're associating each page type to its title.
 */
$page_titles = array (
	PAGE_ID_HOME => _("Home"),
	PAGE_ID_SEARCH => _("Search"),
	PAGE_ID_SEARCH_RESULTS => _("The search results"),
	PAGE_ID_ABOUT => _("About Demoshot"),
	PAGE_ID_HELP => _("Help"),
	PAGE_ID_SIGNUP => _("Signup"),
	PAGE_ID_LOGIN => _("Login"),
	PAGE_ID_PROFILE => _("Profile"),
	PAGE_ID_PICTURE => _("Picture"),
	PAGE_ID_NEW_PICTURE => _("Add a picture"),
	PAGE_ID_EDIT_PICTURE => _("Edit the picture"),
	PAGE_ID_MARK => _("Mark"),
	PAGE_ID_TAG => _("Tag"),
	PAGE_ID_USER_SETTINGS => _("Settings"),
	PAGE_ID_USER_ALBUM => _("Album"),
	PAGE_ID_RESET_PASSWD => _("Password forgotten"),
	PAGE_ID_DATABASE_ERROR => _("Database error"),
	PAGE_ID_MAINTENANCE => _("Maintenance"),
	PAGE_ID_MANAGE => _("Manage"),
	PAGE_ID_403 => _("403 error"),
	PAGE_ID_404 => _("404 error"),
);


/*
 * These constants define the different arguments
 * that can be passed to a page in the URL
 */
define("PAGE_ARG_USER_ID", "uid");
define("PAGE_ARG_PICTURE_ID", "pid");
define("PAGE_ARG_TAG_ID", "tid");
define("PAGE_ARG_OFFSET", "offset"); // used for pagination
define("PAGE_ARG_LIMIT", "limit"); // used to limit the number of results in a page
define("PAGE_ARG_SORT_BY", "sort_by");
define("PAGE_ARG_SORT_ASC", "sort_asc"); // if this argument is passed, sort the results in ascending order


/*
 * These constants define the different arguments
 * that can be passed to the search result pages in the URL.
 */
define("SEARCH_TARGET", "sid"); // TABLE_CODE_USER || TABLE_CODE_PICTURE to search for users or pictures
define("SEARCH_PATTERN_TITLE", "title"); // the pattern titles/usernames must match
define("SEARCH_PATTERN_DESCRIPTION", "description"); // the pattern descriptions must match

/*
 * When searching for users: RETRIEVE_USERS_BY_TAG || RETRIEVE_USERS_BY_FOLLOWER || RETRIEVE_USERS_BY_FOLLOWED.
 * If not set, defaults to RETRIEVE_ALL_USERS.
 * 
 * When searching for pictures: RETRIEVE_PICTURES_BY_TAG || RETRIEVE_PICTURES_BY_AUTHOR || RETRIEVE_PICTURES_BY_MARK_AUTHOR.
 * If not set, defaults to RETRIEVE_ALL_PICTURES.
 */
define("SEARCH_CRIT_TYPE", "cid");
define("SEARCH_CRIT_VALUE", "c"); // can be tags to look for, or the ID of a follower / followed user

/*
 * If SEARCH_PATTERN_JOIN, both SEARCH_PATTERN_TITLE and SEARCH_PATTERN_DESCRIPTION must match.
 * See $join_likes in PictureRetriever, UserRetriever (utils/picture.php, utils/user.php).
 */
define("SEARCH_PATTERN_JOIN", "pj");

/*
 * If SEARCH_TAGS_JOIN, search for pictures related to ALL of these tags.
 * See $join_tags in PictureRetriever, UserRetriever.
 */
define("SEARCH_TAGS_JOIN", "tj");

/*
 * SEARCH_STATUS_PRIVATE | PUBLIC | ALL
 * This option is only available to logged in users.
 */
define("SEARCH_VISIBILITY", "v");

$enumi = 0;
define("SEARCH_VISIBILITY_PRIVATE", $enumi++); // only look for private pictures
define("SEARCH_VISIBILITY_PUBLIC", $enumi++); // only look for public pictures
define("SEARCH_VISIBILITY_ALL", $enumi++); // look for both private pictures and public pictures


/*
 * FUNCTIONS
 */

/*
 * @function get_args_string
 * 
 * @param string $ignore
 * The name of an argument to ignore.
 * Lang, logout, login, follow, nofollow are automatically ignored.
 * 
 * @param string $append
 * An argument to append to the end of the URL.
 * 
 * @retval string
 * The concatenation of all arguments passed through the URL.
 */
function get_args_string($ignore = '', $append = '')  {
	assert(is_string($ignore));
	assert(is_string($append));
	
	if(count($_GET) == 0) {
		if($append !== '') {
			return "?$append";
		} else {
			return '';
		}
	}
	
	$ignored = array('lang', 'logout', 'login', 'follow', 'nofollow', 'maintenance', $ignore);
	
	$url = '';
	$argument = false;
	
	foreach($_GET as $argname => $argval) {
		if(!in_array($argname, $ignored)) {
			if($url == '') {
				$url .= '?' . $argname . '=' . $argval;
				$argument = true;
			} else {
				$url .= '&amp;' . $argname . '=' . $argval;
			}
		}
	}
	
	if($argument && $append !== '') {
		$url .= "&amp;$append";
	} else if($append !== '') {
		$url .= "?$append";
	}
	
	return $url;
}


?>
