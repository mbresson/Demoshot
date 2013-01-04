<?php namespace Demoshot\Database;


/*
 * CONSTANTS
 */

/*
 * These constants provide the name of every table in the database.
 * They can be modified to adapt the whole program to a database with different table names.
 */
define('TABLE_FOLLOWER', 'follower');
define('TABLE_MARK', 'mark');
define('TABLE_PICTURE', 'picture');
define('TABLE_PICTURE_TAG', 'picture_tag');
define('TABLE_TAG', 'tag');
define('TABLE_USER', 'user');
define('TABLE_USER_TAG', 'user_tag');

$enumi = 0;
define('TABLE_CODE_FOLLOWER', $enumi++);
define('TABLE_CODE_MARK', $enumi++);
define('TABLE_CODE_PICTURE', $enumi++);
define('TABLE_CODE_PICTURE_TAG', $enumi++);
define('TABLE_CODE_TAG', $enumi++);
define('TABLE_CODE_USER', $enumi++);
define('TABLE_CODE_USER_TAG', $enumi++);
/*
 * This is a special code.
 * When passed to iterators constructors (such as PictureRetriever),
 * it means that we want them to retrieve every existing item.
 */
define('TABLE_CODE_ALL', $enumi++);

?>
