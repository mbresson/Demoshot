<?php

/*
 * This is a template file.
 * It is used to compose each page sent to a client by the server.
 * The template pages can make use of the variables stored in the array
 * $demoshot_var to retrieve all the information they need.
 * (see PAGE_VAR_* constants in utils/pages.php)
 */

assert(isset($demoshot));
assert(isset($demoshot_var));


/*
 * DEPENDENCIES
 */

require_once('core/utils/database.php');

require_once('core/utils/pages.php');

require_once('core/utils/user.php');
use \Demoshot\User;


/*
 * MAIN
 */

$help_url = "index.php?page_id=" . PAGE_ID_HELP;

// a link used in the first question
$about_url = "index.php?page_id=" . PAGE_ID_ABOUT;
$about_title = _("About Demoshot");
$about_text = _("this page");
$about_ahref = "<a href='$about_url' title='$about_title'>$about_text</a>";

$content = array(
	_("What is Demoshot?") =>
		sprintf(
			_("Demoshot is a photo sharing website. To learn more about it, you may consult %s."),
			$about_ahref
		),
	
	_("Why should I sign up?") =>
		_("A signed in user can do much more than a simple visitor: he can upload pictures, mark and comment the pictures of other users and follow the people he likes. Also, only logged in users can access private pictures and read private comments."),
	
	_("What's the use of following other users?") =>
		_("If you follow an user, you will be notified by email when he uploads a new picture."),
	
	_("What is the privacy thing in my profile?") =>
		_("The privacy settings define your default privacy choice when uploading a picture or commenting an existing one. Still, you can bypass this setting when you upload a picture, or change it after the picture has been added to your album. The same goes for comments."),
	
	_("How can I delete my account?") =>
		_("Go to your profile page, then click to edit your profile. Select the account deletion tab and enter your password. Farewell!"),
	
	_("I can' remind my password!") =>
		_("Go to the login page, there is a link below the form to reset your password. All you need to do is enter your username to receive a new password by email.")
);


?>


<div class="container">

<h1><?php echo _("Frequently Asked Questions") ?></h1>

<?php

	/*
	 * If the contact form has been sent,
	 * send the corresponding message to the webmaster.
	 */
	
	$subject = NULL;
	if(isset($_POST['h_subject']) && $_POST['h_subject'] !== "") {
		$subject = $_POST['h_subject'];
	}
	
	$message = NULL;
	if(is_string($subject)) {
		if(isset($_POST['h_message']) && $_POST['h_message'] !== "") {
			$message = $_POST['h_message'];
		}
	}
	
	if(is_string($subject) && is_string($message)) {
		$ok = User\send_email_to_webmaster($demoshot, $demoshot_var[PAGE_VAR_USERID], $subject, $message);
		
		if(!$ok) {
			echo "<h3 class='dm_wrong_input'>";
			echo _("Your message couldn't be sent :(");
			echo "</h3>";
		} else {
			echo "<h3>";
			echo _("Thanks, the webmaster should receive your message soon");
			echo "</h3>";
		}
	}

?>

<div class="accordion" id="faq">
<?php

	$it = 0;

	foreach($content as $question => $answer):
	
		$it++; ?>
		
		<div class="accordion-group">
			<h4 class="accordion-heading">
				<a class='accordion-toggle' data-toggle='collapse' data-parent='#faq' href='#faq<?php echo $it ?>'>
					<?php echo $question ?>
				</a>
			</h4>
			
			<div id="faq<?php echo $it ?>" class="accordion-body collapse <?php if($it == 1) echo 'in'; else echo 'out' ?>">
				<h5 class="accordion-inner"><?php echo $answer ?></h5>
			</div>
		</div>
	
	<?php endforeach; ?>
	
	<div class="accordion-group">
		<h4 class="accordion-heading">
			<a class="accordion-toggle" data-toggle='collapse' data-parent="#faq" href="#faqform">
				<?php echo _("How do I contact the webmaster?") ?>
			</a>
		</h4>
		
		<div id="faqform" class="accordion-body collapse out">
			<form class="form-horizontal" method="post" action="<?php echo $help_url ?>">
			
				<div class="control-group">
					<label class="control-label" for="subject">
						<?php echo _("Subject") ?>
					</label>
					
					<div class="controls">
						<input type="text" class="input-xxlarge" id="subject" maxlength="100" name="h_subject" required placeholder="<?php echo _("Need help with...") ?>" />
					</div>
				</div>
				
				<div class="control-group">
					<label class="control-label" for="message">
						<?php echo _("Message") ?>
					</label>
					
					<div class="controls">
						<textarea class="input-xxlarge" id="message" name="h_message" maxlength="2000" rows="4" placeholder="<?php echo _("State your problem."); ?>" required></textarea>
					</div>
				</div>
				
				<div class="form-actions">
					<button type="submit" class="btn btn-inverse">
						<?php echo _("Send my message"); ?>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>


