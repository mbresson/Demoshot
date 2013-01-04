<?php namespace Demoshot\Security;


/*
 * CONSTANTS
 */

define("FORBIDDEN_CHARACTERS", ";\"\\\$<>"); // a title cannot contain these characters


/*
 * FUNCTIONS
 */

/*
 * @function new_salt
 * 
 * @purpose
 * Generate a salt to be prepended to a value before hashing it.
 * 
 * @retval string
 * The new random salt.
 */
function new_salt() {
	$length = 64;
	$salt = "";
	
	$template = "1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
	$template_length = strlen($template);
	
	for($index = 0; $index < $length; $index++) {
		$random = rand(0, $template_length - 1);
		$salt .= $template[$random];
	}
	
	return $salt;
}

/*
 * @function hash_data
 * 
 * @purpose
 * Hash some data (typically a password) with the provided salt and SHA256 algorithm.
 * 
 * @retval string
 * The result of the hashing process.
 */
function hash_data($salt, $data) {
	assert(is_string($salt));
	assert(is_string($data));
	
	return hash('SHA256', $salt . $data);
}

?>
