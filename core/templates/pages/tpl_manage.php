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

require_once('core/utils/pages.php');

require_once('core/utils/user.php');
use \Demoshot\User;


/*
 * MAIN
 */

?>

<div class="container">

<?php
	if(isset($_GET['maintenance'])) {
		$conf = $demoshot->get_conf();
		
		// if the maintenance mode is already on, turn it off
		if($conf['state']['maintenance'] == "1") {
			$demoshot->set_maintenance(false);
			
			echo "<h1>" . _("The maintenance mode was switched off") . "</h1>";
		} else {
			$demoshot->set_maintenance(true);
			
			echo "<h1>" . _("The maintenance mode was switched on") . "</h1>";
		}
	}
	
	if(isset($_POST['cf_password']) && $_POST['cf_password'] != '') {
		$password = $_POST['cf_password'];
		$new_max_size = NULL;
		$new_thumbnail_compression_level = NULL;
		
		if(isset($_POST['cf_max_size']) && $_POST['cf_max_size'] != '') {
			$new_max_size = (int) $_POST['cf_max_size'];
		}
		
		if(isset($_POST['cf_thumbnail_compression_level']) && $_POST['cf_thumbnail_compression_level'] != '') {
			$new_thumbnail_compression_level = (int) $_POST['cf_thumbnail_compression_level'];
		}
		
		// check the password
		$user_id = $demoshot_var[PAGE_VAR_USERID];
		
		if(User\check_password($demoshot, $user_id, $password)) {
			$demoshot->change_image_conf($new_max_size, $new_thumbnail_compression_level);
			
			echo "<h1>";
			echo _("The configuration was successfully updated");
			echo "</h1>";
		} else {
			echo "<h1 class='dm_wrong_input'>";
			echo _("Wrong password");
			echo "</h1>";
		}
		
	}
?>

<ul class="nav nav-tabs">
	<li class="active">
		<a href="#1" data-toggle="tab"><?php echo _("Configuration") ?></a>
	</li>
	
	<li>
		<a href="#2" data-toggle="tab"><?php echo _("Maintenance") ?></a>
	</li>
</ul>

<div class="tab-content">
	<div class="tab-pane active" id="1">
		<p class='alert alert-info'>
		<?php
			$conf = $demoshot->get_conf();
		
			$max_size = (int) $conf['image']['max_size'];
			$thumbnail_compression_level = (int) $conf['image']['thumbnail_compression_level'];
		
			$action_url = "index.php?page_id=" . PAGE_ID_MANAGE;
			
			echo _("Keep in mind that if you do change the configuration, the new settings won't be applied to the old pictures and their thumbnails.");
		?>
		</p>
		
		<form method="post" action="<?php echo $action_url ?>" class="form-horizontal">
			<div class="control-group">
				<label class="control-label" for="max_size">
					<?php echo _("Max size of a picture"); ?>
				</label>
				
				<div class="controls">
					<input type="number" min="1" max="1000" id="max_size" value="<?php echo $max_size ?>" name="cf_max_size" class="input-mini" />
					
					<p class="help-block">
					<?php
						echo _("Unit of measurement: MB (megabyte).");
					?>
					</p>
				</div>
			</div>
			
			<div class="control-group">
				<label class="control-label" for="thumbnail_compression_level">
					<?php echo _("PNG compression level"); ?>
				</label>
				
				<div class="controls">
					<input type="number" min="0" max="9" id="thumbnail_compression_level" value="<?php echo $thumbnail_compression_level ?>" name="cf_thumbnail_compression_level" class="input-mini" />
					
					<p class="help-block">
					<?php
						echo _("The compression level of the thumbnails (the lower, the heavier and the higher, the slower). From 0 to 9.");
					?>
					</p>
				</div>
			</div>
			
			<div class="control-group">
				<label class="control-label" for="password">
					<?php echo _("Your password"); ?>
				</label>
				
				<div class="controls">
					<input type="password" class="input-large" id="password" name="cf_password" required />
					
					<p class="help-block"><strong>
					<?php
						echo _("Required for any change pertaining to the configuration.");
					?>
					</strong></p>
				</div>
			</div>
			
			<div class="form-actions">
				<button type="submit" class="btn btn-primary">
					<?php echo _("Update the configuration"); ?>
				</button>
			</div>
		</form>
	</div>
	
	<div class="tab-pane" id="2">
		
		<?php
			$conf = $demoshot->get_conf();
		
			$maintenance = $conf['state']['maintenance'] == "1";
			
		?>
		
		<h4>
		<?php
			if($maintenance) {
				echo _("Maintenance mode is on.");
			} else {
				echo _("Maintenance mode is off.");
			}
		?>
		</h4>
		
		<p class='alert alert-info'>
		<?php
			echo _("Maintenance mode allows you to prevent any visitor from accessing the website when you need to make changes.");
		?>
		</p>
		
		<div>
		<?php
			$maintenance_url = "index.php?page_id=" . PAGE_ID_MANAGE . "&amp;maintenance";
			
			$maintenance_title = $maintenance ? _("turn it off") : _("turn it on");
			
			$btn_class = $maintenance ? "btn-success" : "btn-danger";
			$icon_class = $maintenance ? "icon-off icon-white" : "icon-lock icon-white";
		
			echo "<a href='$maintenance_url' title='' class='btn btn-large $btn_class'>" .
				"<i class='$icon_class'></i> $maintenance_title</a>";
		?>
		</div>
	</div>
</div>

</div>
