<?php

/*
 * This is a template file.
 * It is used to compose each page sent to a client by the server.
 * The template pages can make use of the variables stored in the array
 * $demoshot_var to retrieve all the information they need.
 */

assert(isset($demoshot));
assert(isset($demoshot_var));


/*
 * DEPENDENCIES
 */

require_once('core/utils/pages.php');
use \Demoshot\Pages;

require_once('core/utils/user.php');
use \Demoshot\User;


/*
 * MAIN FLOW
 */

$guest = $demoshot_var[PAGE_VAR_USERID] === -1; // see index.php
$base_url = "index.php";

$about_url = $base_url . '?page_id=' . PAGE_ID_ABOUT;
$search_url = $base_url . "?page_id=" . PAGE_ID_SEARCH;
$help_url = $base_url . "?page_id=" . PAGE_ID_HELP;
$admin_url = $base_url . "?page_id=" . PAGE_ID_MANAGE;


?>

<nav class="navbar navbar-inverse navbar-fixed-top">
	
	<div class="navbar-inner">
		
		<div class="container">
			
			<a class="brand" href='<?php echo $about_url; ?>'><img src='res/img/Demoshot-brand.png' alt='' /> Demoshot</a>
			
			<div class="nav-collapse collapse">
				<ul class="nav">
					<li class="divider-vertical"></li>
					
					<li><a href="<?php echo $base_url ?>"><i class="icon-home icon-white"></i> <?php echo _("Home") ?></a></li>
					
					<li><a href="<?php echo $search_url ?>"><i class="icon-search icon-white"></i> <?php echo _("Search") ?></a></li>
					
					<li><a href="<?php echo $help_url ?>"><i class="icon-question-sign icon-white"></i> <?php echo _("Help") ?></a></li>
					
					<?php if($demoshot_var[PAGE_VAR_ADMIN]): ?>
					<li><a href="<?php echo $admin_url ?>" style="font-weight: bold"><i class="icon-cog icon-white"></i> <?php echo _("Manage") ?></a></li>
					<?php endif; ?>
				</ul>
				
				
				
				<ul class="nav pull-right">
					<li class="dropdown">
						
						<a href="#" class="dropdown-toggle" data-toggle="dropdown">
						<?php echo _('Language'); ?> <b class="caret"></b>
						</a>
						
						<ul class="dropdown-menu">
						<?php
						
							$args = Pages\get_args_string();
							
							if($args === '') {
								$sep = '?';
							} else {
								$sep = '&amp;';
							}
							
							$fr_url = $base_url . $args . $sep . 'lang=' . USER_LANG_FR;
							$en_url = $base_url . $args . $sep . 'lang=' . USER_LANG_EN;
							
							$current_lang = $demoshot->get_lang();
							
							echo "<li><a href='$fr_url'>Français ";
							if($current_lang == USER_LANG_FR) {
								echo " <i class='icon-ok'></i>";
							}
							echo "</a></li>";
							
							echo "<li><a href='$en_url'>English";
							if($current_lang == USER_LANG_EN) {
								echo " <i class='icon-ok'></i>";
							}
							echo "</a></li>";
						
						?>
						</ul>
						
					</li>
					
					<?php if(!$guest): ?>
					<li class="dropdown">
						
						<a href="#" class="dropdown-toggle" data-toggle="dropdown">
							
							<?php echo $demoshot_var[PAGE_VAR_USERNAME] ?> <b class="caret"></b>
						</a>
						
						<ul class="dropdown-menu">
							
								<?php
								
									/*
									 * The dropdown menu contains the following items:
									 * 
									 * Profile
									 * Album
									 * Logout
									 */
								
									$user_id = $demoshot_var[PAGE_VAR_USERID];
									
									
									/*
									 * Profile
									 */
									$profile_url = $base_url . "?page_id=" . PAGE_ID_PROFILE;
									$profile_url .= "&amp;" . PAGE_ARG_USER_ID . "=$user_id";
									
									echo "<li><a href='$profile_url'><i class='icon-user'></i> " . _("Profile") . "</a></li>";
									
									
									/*
									 * Album
									 */
									$album_url = $base_url . "?page_id=" . PAGE_ID_USER_ALBUM;
									$album_url .= "&amp;" . PAGE_ARG_USER_ID . "=$user_id";
									
									echo "<li><a href='$album_url'><i class='icon-picture'></i> " . _("Album") . "</a></li>";
									
									
									/*
									 * Logout
									 */
									$logout_url = "index.php?logout";
									
									echo "<li><a href='$logout_url'><i class='icon-off'></i> " . _("Logout") . "</a></li>";
								
								?>
								
						</ul>
						
					</li>
					<?php else: ?>
						
						<p class="navbar-text pull-right"><?php
				
					$login_url = $base_url . "?page_id=" . PAGE_ID_LOGIN;
					$signup_url = $base_url . "?page_id=" . PAGE_ID_SIGNUP;
					
					echo $demoshot_var[PAGE_VAR_USERNAME] . " (<a href='$login_url'>" . _("log in") . "</a> ";
					echo _("or") . " <a href='$signup_url'>" . _("sign up") . "</a>)";
					
				?></p>
				
				<?php endif; ?>
				</ul>

            </div>
		</div>
	</div>
	
</nav>



