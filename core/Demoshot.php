<?php namespace Demoshot;


/*
 * DEPENDENCIES
 */

require_once('utils/misc.php');

require_once('utils/user.php');
use \Demoshot\User;

/*
 * CONSTANTS
 */

define('DEMOSHOT_AUTHOR', 'Matthieu Bresson');
define('DEMOSHOT_RELEASE_MAJ', 1);
define('DEMOSHOT_RELEASE_MIN', 0);
define('DEMOSHOT_LICENSE', 'MIT');

/*
 * @beware
 * Other php scripts may set other error codes than the ones defined here.
 */
$enumi = 0;
define('NO_ERROR', $enumi++);
define('ERROR_NO_INI_FILE', $enumi++);
define('ERROR_INI_MISSING_SECTION', $enumi++);
define('ERROR_CONNECTING_DATABASE', $enumi++);


/*
 * CLASSES
 */

/*
 * @class Demoshot
 * 
 * @purpose
 * This class stores the main information about Demoshot.
 * An object of this type must be created at the very beginning of the main script.
 */
class Demoshot {
	
	
	/*
	 * MEMBERS
	 */
	
	/*
	 * @member array $conf
	 * Contains the configuration stored in Demoshot.ini.
	 */
	private $conf = NULL;
	
	/*
	 * @member DBO $db
	 * A pointer to a DBO object connected to the database.
	 */
	private $db = NULL;
	
	/*
	 * @member int $errcode
	 * An int containing the code of the last error if any.
	 */
	private $errcode = NO_ERROR;
	
	/*
	 * @member mixed $errmsg
	 * It may contain additionnal information about the error.
	 */
	private $errmsg = "";
	
	/*
	 * @member string $lang
	 * The current locale.
	 */
	private $lang = USER_DEFAULT_LANG;
	
	/*
	 * @member bool $ok
	 * Equals false if an error occurred.
	 */
	private $ok = true;
	
	/*
	 * @member bool $updated_conf
	 * If the configuration of Demoshot has been modified,
	 * set $write_conf to true so that the Demoshot object
	 * updates the INI file at the end of its lifetime.
	 */
	private $updated_conf = false;
	
	
	/*
	 * GETTERS
	 */
	
	/*
	 * @getter get_conf $this->conf
	 */
	public function get_conf() {
		return $this->conf;
	}
	
	/*
	 * @getter get_db $this->db
	 */
	public function get_db() {
		return $this->db;
	}
	
	/*
	 * @getter get_errcode $this->errcode
	 */
	public function get_errcode() {
		return $this->errcode;
	}
	
	/*
	 * @getter get_errmsg $this->errmsg
	 */
	public function get_errmsg() {
		return $this->errmsg;
	}
	
	/*
	 * @getter get_lang $this->lang
	 */
	public function get_lang() {
		return $this->lang;
	}
	
	/*
	 * @getter get_ok $this->ok
	 */
	public function get_ok() {
		return $this->ok;
	}
	
	
	/*
	 * PUBLIC METHODS
	 */
	
	/*
	 * @function change_image_conf
	 * 
	 * @purpose
	 * Update the configuration of Demoshot with new values provided.
	 * 
	 * @param int $new_max_size
	 * The new maximum of a picture, in MB (megabytes),
	 * or NULL to leave the field unchanged.
	 * 
	 * @param int $new_thumbnail_compression_level
	 * The new compression level of PNG thumbnails. Minimum: 0, maximum: 9.
	 * or NULL to leave the field unchanged.
	 */
	public function change_image_conf($new_max_size = NULL, $new_thumbnail_compression_level = NULL) {
		assert(is_null($new_max_size) || is_unsigned($new_max_size));
		assert(is_null($new_thumbnail_compression_level) || is_enum($new_thumbnail_compression_level, 0, 9));
		
		if(is_unsigned($new_max_size)) {
			$this->conf['image']['max_size'] = $new_max_size;
		}
		
		if(is_int($new_thumbnail_compression_level)) {
			$this->conf['image']['thumbnail_compression_level'] = $new_thumbnail_compression_level;
		}
		
		$this->updated_conf = true;
	}
	
	/*
	 * @function change_manager_conf
	 * 
	 * @purpose
	 * Update the configuration of Demoshot with the new values provided.
	 * 
	 * @param string $new_name
	 * The manager's new username,
	 * or NULL to leave the field unchanged.
	 * 
	 * @param string $new_email
	 * The manager's new email address,
	 * or NULL to leave the field unchanged.
	 */
	public function change_manager_conf($new_name = NULL, $new_email = NULL) {
		assert(is_null($new_name) || is_string($new_name));
		assert(is_null($new_email) || is_string($new_email));
		
		if(is_string($new_name) && $new_name != "") {
			$this->conf['manager']['name'] = $new_name;
		}
		
		if(is_string($new_email) && $new_email != "") {
			$split_email = preg_split("*@*", $new_email);
			
			if(count($split_email) == 2) {
				list($email_local_part, $email_domain_part) = $split_email;
				
				$this->conf['manager']['email_local_part'] = $email_local_part;
				$this->conf['manager']['email_domain_part'] = $email_domain_part;
			}
		}
		
		$this->updated_conf = true;
	}
	
	/*
	 * @function clear_error
	 * 
	 * @purpose
	 * Clear all error fields.
	 */
	public function clear_error() {
		$this->ok = true;
		$this->errcode = NO_ERROR;
		$this->errmsg = "";
	}
	
	/*
	 * @function dump_error
	 * 
	 * @purpose
	 * Echoes error informations to help debugging.
	 */
	public function dump_error() {
		echo "Demoshot::ok(" . gettype($this->ok) . ") => " . ($this->ok ? "true<br />" : "false<br />");
		echo "Demoshot::errcode(" . gettype($this->errcode) . ") => $this->errcode<br />";
		echo "Demoshot::errmsg(" . gettype($this->errmsg) . ") => $this->errmsg<br />";
	}
	
	/*
	 * @function set_error
	 * 
	 * @param int $code
	 * The error code to store.
	 * 
	 * @param mixed $msg
	 * The error message (string) to store. Can be NULL.
	 */
	public function set_error($code, $msg = "") {
		assert(is_int($code));
		assert(is_int($msg) || is_string($msg) || is_null($msg));
		
		$this->errcode = $code;
		$this->errmsg = (string) $msg;
		
		$this->ok = false;
	}
	
	/*
	 * @function set_maintenance
	 * 
	 * @purpose
	 * Change the maintenance value in the configuration of Demoshot.
	 * Next time Demoshot is accessed, access will be refused to any user
	 * except for the administrator.
	 * 
	 * @param bool $on
	 * If false, leave maintenance mode.
	 */
	public function set_maintenance($on = true) {
		assert(is_bool($on));
		
		if($on) {
			$this->conf['state']['maintenance'] = "1";
		} else {
			$this->conf['state']['maintenance'] = "0";
		}
		
		$this->updated_conf = true;
	}
	
	
	/*
	 * PRIVATE METHODS
	 */
	
	/*
	 * @function write_conf
	 * 
	 * @purpose
	 * Write Demoshot's configuration file (Demoshot.ini) at the specified path.
	 * It will overwrite the existing file if present.
	 */
	private function write_conf() {
		
		/*
		 * First, retrieve all the configuration values.
		 * Second, store the content of the INI file in a string.
		 * Last, write the string to the file.
		 */
		
		
		/*
		 * Retrieve all configuration values.
		 */
		$conf = $this->conf;
		
		// state section
		$maintenance = $conf['state']['maintenance'];
		
		// manager section
		$name = $conf['manager']['name'];
		$email_local_part = $conf['manager']['email_local_part'];
		$email_domain_part = $conf['manager']['email_domain_part'];
		
		// database section
		$host = $conf['database']['host'];
		$db_name = $conf['database']['name'];
		$user = $conf['database']['user'];
		$passwd = $conf['database']['passwd'];
		$max_comment_length = $conf['database']['max_comment_length'];
		$max_description_length = $conf['database']['max_description_length'];
		
		// image section
		$max_size = $conf['image']['max_size'];
		$thumbnail_compression_level = $conf['image']['thumbnail_compression_level'];
		
		
		/*
		 * Store the content of the INI file in a string.
		 */
		$ini =
			"\n; Demoshot.ini" .
			"\n; contains the minimal configuration needed to run Demoshot" .
			
			"\n\n\n[state]" .
			"\n\nmaintenance                   =          $maintenance" .
			
			"\n\n\n[manager]" .
			"\n\nname                          =          \"$name\"" .
			"\nemail_local_part              =          \"$email_local_part\"" .
			"\nemail_domain_part             =          \"$email_domain_part\"" .
			
			"\n\n\n[database]" .
			
			"\n\nhost                          =          \"$host\"" .
			"\nname                          =	         \"$db_name\"" .
			"\nuser                          =          \"$user\"" .
			"\npasswd                        =          \"$passwd\"" .
			"\n\n; this limit depends on the database" .
			"\nmax_comment_length            =          $max_comment_length ; characters" .
			"\nmax_description_length        =          $max_description_length" .
			
			"\n\n\n[image]" .
			
			"\n\nmax_size                      =          $max_size ; MB" .
			"\nthumbnail_compression_level   =          $thumbnail_compression_level ; from 0 to 9";
		
		
		/*
		 * Write the string to the file.
		 */
		if(basename(__FILE__) == "Demoshot.php") {
			$path = dirname(__FILE__) . "/Demoshot.ini";
		} else {
			$path = dirname(__FILE__) . '/core/Demoshot';
		}
		
		$file = fopen($path, "w");
		
		if(!$file) {
			return false;
		} else {
			if(!fwrite($file, $ini)) {
				fclose($file);
				return false;
			}
		}
		
		fclose($file);
		
		return true;
	}
	
	
	/*
	 * SPECIAL METHODS
	 */
	
	/*
	 * @constructor
	 * 
	 * @purpose
	 * - Start gettext domain.
	 * - Load main configuration file.
	 * - Connect to the database.
	 */
	public function __construct() {
		
		// load configuration file
		$this->conf = parse_ini_file('core/Demoshot.ini', true);
		if(!$this->conf) {
			$this->set_error(
				ERROR_NO_INI_FILE,
				"Configuration file core/Demoshot.ini couldn't be read!"
			);

			return;
		}
		
		// check if configuration file is valid
		$sections = array('state', 'manager', 'database', 'image');
		foreach($sections as $section) {
			if(!isset($this->conf[$section])) {
				$this->set_error(
					ERROR_INI_MISSING_SECTION,
					"Missing section '$section' in configuration file core/Demoshot.ini!"
				);
				
				return;
			}
		}
		
		
		// bind text domain for internationalization
		$this->lang = User\get_user_lang();
		
		setlocale(LC_ALL, $this->lang);
		bindtextdomain("Demoshot", "core/lang");
		textdomain("Demoshot");
		
		// connect to the database
		try {
			$this->db = new \PDO(
				"mysql:host=" . $this->conf['database']['host'] .
				";dbname=" . $this->conf['database']['name'],
				$this->conf['database']['user'],
				$this->conf['database']['passwd']
			);
		} catch(\PDOException $exc) {
			$this->set_error(
				ERROR_CONNECTING_DATABASE,
				$exc->getMessage()
			);

			return;
		}
	}
	
	
	/*
	 * @destructor
	 * 
	 * @purpose
	 * If the configuration of Demoshot has changed,
	 * update the Demoshot.ini file.
	 */
	public function __destruct() {
		if($this->updated_conf) {
			$this->write_conf();
		}
	}
}

?>
