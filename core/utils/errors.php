<?php namespace Demoshot\Errors;


/*
 * CONSTANTS
 */

/*
 * @remark
 * The error codes >= 0 are related to a specific context (e.g. a bad password).
 * The codes < 0 are global and can occur in any context.
 * That's why they are defined here.
 */
$enumi = -1;

// the database refused to execute the query
define('ERROR_ON_QUERY', $enumi--);

/*
 * One field in the database can no longer hold a given value.
 * E.g. an UNSIGNED INT < 0 or an INT > 2147483647
 * Such an error should never occur under normal circumstances.
 */
define('ERROR_OUT_OF_BOUNDS', $enumi--);

/*
 * A text that must be inserted in the database is too long to fit.
 * E.g. a comment longer than 1000 will truncate.
 */
define('ERROR_TEXT_OVERFLOW', $enumi--);

/*
 * The value looked for in the database couldn't be found.
 */
define('ERROR_NOT_FOUND', $enumi--);

/*
 * If a function must return an unsigned int as a result,
 * it may return a negative value (ERROR_SIMPLE) instead of the boolean false.
 * 
 * This error code can also be used if a function cannot state the exact cause of failure.
 */
define('ERROR_SIMPLE', $enumi--);

/*
 * The file looked for doesn't exist.
 */
define('ERROR_NO_FILE', $enumi--);

/*
 * We don't have the permission to write in a folder.
 */
define('ERROR_NO_WRITE_PERMISSION', $enumi--);

/*
 * The function cannot handle one of the parameters it was given.
 */
define('ERROR_UNHANDLED_CASE', $enumi--);

/*
 * The function needs a piece of information which is not available.
 * E.g. a value in Demoshot.ini is not present.
 */
define('ERROR_MISSING_INFORMATION', $enumi--);

/*
 * The email couldn't be sent.
 */
define('ERROR_SENDING_EMAIL', $enumi--);

?>
