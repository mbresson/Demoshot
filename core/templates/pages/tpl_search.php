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

require_once('core/utils/misc.php');

require_once('core/utils/database.php');

require_once('core/utils/pages.php');


/*
 * MAIN
 */

/*
 * If the input fields have already been completed
 * (e.g. the user has returned to change his research criteria),
 * respawn them to make the user's life easier.
 */
$respawn_picture_search = false;
$respawn_user_search = false;

if(isset($_GET[SEARCH_TARGET])) {
	// we must find out what the user was searching for (either pictures or users)
	switch((int) $_GET[SEARCH_TARGET]) {
		case TABLE_CODE_PICTURE:
			$respawn_picture_search = true;
			break;
		
		case TABLE_CODE_USER:	
			$respawn_user_search = true;
			break;
	}
}

?>

<div class="container">

<h1><?php echo _("What are you looking for?") ?></h1>

<ul id="menu" class="nav nav-tabs">
	<li <?php if(!$respawn_user_search) echo 'class="active"' ?>>
		<a href="#1" data-toggle="tab"><?php echo _("Search for pictures") ?></a>
	</li>
	
	<li <?php if($respawn_user_search) echo 'class="active"' ?>>
		<a href="#2" data-toggle="tab"><?php echo _("Search for users") ?></a>
	</li>
</ul>

<div class="tab-content">
	<div class="tab-pane <?php if(!$respawn_user_search) echo 'active' ?>" id="1">
	
		<form class="form-horizontal" method="get" action="index.php">
			
			<!--
				The forms of this page use the GET method.
				As a result, when it is sent, information concerning the ID of the action page and the search target is lost.
				To prevent it, the id of the action page and the search target must be sent through the form.
			-->
			<input type="hidden" name="page_id" value="<?php echo PAGE_ID_SEARCH_RESULTS ?>" />
			<input type="hidden" name="<?php echo SEARCH_TARGET ?>" value="<?php echo TABLE_CODE_PICTURE ?>" />
			<input type="hidden" name="<?php echo SEARCH_CRIT_TYPE ?>" value="<?php echo RETRIEVE_PICTURES_BY_TAG ?>" />
			
			<fieldset>
				<legend><?php echo _("Related to") ?></legend>
				
				<div class="control-group">
					<label class="control-label" for="tags">
						<?php echo _("Tags") ?>
					</label>
					
					<?php
						/*
						 * Retrieve the value of this input field if provided through the URL.
						 */
						
						$value = "";
						if($respawn_picture_search && isset($_GET[SEARCH_CRIT_VALUE])) {
							$value = $_GET[SEARCH_CRIT_VALUE];
						}
						
						$placeholder = _("programming, South Korea, holidays");
					?>
					
					<div class="controls">
						<input type="text" class="input-xlarge" id="tags" name="<?php echo SEARCH_CRIT_VALUE ?>" value="<?php echo $value ?>" placeholder="<?php echo $placeholder ?>" />
					</div>
				</div>
				
				<div class="control-group">
					<label class="control-label" for="join_tags">
						<?php echo _("At least") ?>
					</label>
					
					<?php
						$join_tags = false;
						
						if($respawn_picture_search && isset($_GET[SEARCH_TAGS_JOIN])) {
							$join_tags = $_GET[SEARCH_TAGS_JOIN] == "1";
						}
					?>
					
					<div class="controls">
						<label class="radio inline">
							<input id="join_tags" type="radio" name="<?php echo SEARCH_TAGS_JOIN ?>" value="0" <?php if(!$join_tags) echo 'checked' ?> />
							 <?php echo _("one of them") ?>
						</label>
						
						<label class="radio inline">
							<input type="radio" name="<?php echo SEARCH_TAGS_JOIN ?>" value="1" <?php if($join_tags) echo 'checked' ?> />
							 <?php echo _("all of them") ?>
						</label>
					</div>
				</div>
			</fieldset>
			
			<fieldset>
				<legend><?php echo _("Pattern match") ?></legend>
				
				<div class="control-group">
					<label class="control-label" for="pattern">
						<?php echo _("Title must contain"); ?>
					</label>
					
					<?php
						$value = "";
						if($respawn_picture_search && isset($_GET[SEARCH_PATTERN_TITLE])) {
							$value = $_GET[SEARCH_PATTERN_TITLE];
						}
					?>
					
					<div class="controls">
						<input type="text" class="input-xlarge" id="pattern" name="<?php echo SEARCH_PATTERN_TITLE ?>" value="<?php echo $value ?>" />
					</div>
				</div>
				
				<div class="control-group">
					<label class="control-label" for="pattern">
						<?php echo _("Description must contain"); ?>
					</label>
					
					<?php
						$value = "";
						if($respawn_picture_search && isset($_GET[SEARCH_PATTERN_DESCRIPTION])) {
							$value = $_GET[SEARCH_PATTERN_DESCRIPTION];
						}
					?>
					
					<div class="controls">
						<input type="text" class="input-xlarge" id="pattern" name="<?php echo SEARCH_PATTERN_DESCRIPTION ?>" value="<?php echo $value ?>" />
					</div>
				</div>
				
				<div class="control-group">
					<label class="control-label" for="join_patterns">
						<?php echo _("At least") ?>
					</label>
					
					<?php
						$join_patterns = false;
						
						if($respawn_picture_search && isset($_GET[SEARCH_PATTERN_JOIN])) {
							$join_patterns = $_GET[SEARCH_PATTERN_JOIN] == "1";
						}
					?>
					
					<div class="controls">
						<label class="radio inline">
							<input id="join_patterns" type="radio" name="<?php echo SEARCH_PATTERN_JOIN ?>" value="0" <?php if(!$join_patterns) echo 'checked' ?> />
							 <?php echo _("one of them") ?>
						</label>
						
						<label class="radio inline">
							<input type="radio" name="<?php echo SEARCH_PATTERN_JOIN ?>" value="1" <?php if($join_patterns) echo 'checked' ?> />
							 <?php echo _("both") ?>
						</label>
					</div>
				</div>
			</fieldset>
			
			<?php if($demoshot_var[PAGE_VAR_USERID] != -1):
			
			/*
			 * Only logged in users can have access to these options.
			 */
			
			?>
			
			<fieldset>
				<legend><?php echo _("Other critera") ?></legend>
				
				<div class="control-group">
					<label class="control-label" for="visibility">
						<?php echo _("Visibility") ?>
					</label>
					
					<?php
						$visibility = SEARCH_VISIBILITY_ALL;
						
						if($respawn_picture_search && isset($_GET[SEARCH_VISIBILITY])) {
							$visibility = (int) $_GET[SEARCH_VISIBILITY];
							
							if(!is_enum($visibility, SEARCH_VISIBILITY_PRIVATE, SEARCH_VISIBILITY_ALL)) {
								$visibility = SEARCH_VISIBILITY_ALL;
							}
						}
						
					?>
					
					<div class="controls">
						<label class="radio inline">
							<input id="visibility" type="radio" name="<?php echo SEARCH_VISIBILITY ?>" value="<?php echo SEARCH_VISIBILITY_PRIVATE ?>" <?php if($visibility == SEARCH_VISIBILITY_PRIVATE) echo 'checked' ?> />
							 <?php echo _("private") ?>
						</label>
						
						<label class="radio inline">
							<input type="radio" name="<?php echo SEARCH_VISIBILITY ?>" value="<?php echo SEARCH_VISIBILITY_PUBLIC ?>" <?php if($visibility == SEARCH_VISIBILITY_PUBLIC) echo 'checked' ?> />
							 <?php echo _("public") ?>
						</label>
						
						<label class="radio inline">
							<input type="radio" name="<?php echo SEARCH_VISIBILITY ?>" value="<?php echo SEARCH_VISIBILITY_ALL ?>" <?php if($visibility == SEARCH_VISIBILITY_ALL) echo 'checked' ?> />
							 <?php echo _("whatever") ?>
						</label>
					</div>
				</div>
			</fieldset>
			
			<?php else: ?>
			
			<h4 class='alert alert-warning'>
			<?php echo _("Because you are not logged in, you cannot have access to some options.") ?>
			</h4>
			
			<?php endif; ?>
			
			<div class="form-actions">
				<button type="submit" class="btn btn-primary">
					<?php echo _("Search"); ?>
				</button>
			</div>
		</form>
	
	</div>
	
	
	
	<div class="tab-pane <?php if($respawn_user_search) echo 'active' ?>" id="2">
	
		
		<form class="form-horizontal" method="get" action="index.php">
			
			<!--
				The forms of this page use the GET method.
				As a result, when it is sent, information concerning the ID of the action page and the search target is lost.
				To prevent it, the id of the action page and the search target must be sent through the form.
			-->
			<input type="hidden" name="page_id" value="<?php echo PAGE_ID_SEARCH_RESULTS ?>" />
			<input type="hidden" name="<?php echo SEARCH_TARGET ?>" value="<?php echo TABLE_CODE_USER ?>" />
			<input type="hidden" name="<?php echo SEARCH_CRIT_TYPE ?>" value="<?php echo RETRIEVE_USERS_BY_TAG ?>" />
			
			<fieldset>
				<legend><?php echo _("Interested in") ?></legend>
				
				<div class="control-group">
					<label class="control-label" for="tags">
						<?php echo _("Tags") ?>
					</label>
					
					<?php
						/*
						 * Retrieve the value of this input field if provided through the URL.
						 */
						
						$value = "";
						if($respawn_user_search && isset($_GET[SEARCH_CRIT_VALUE])) {
							$value = $_GET[SEARCH_CRIT_VALUE];
						}
						
						$placeholder = _("programming, South Korea, holidays");
					?>
					
					<div class="controls">
						<input type="text" class="input-xlarge" id="tags" name="<?php echo SEARCH_CRIT_VALUE ?>" value="<?php echo $value ?>" placeholder="<?php echo $placeholder ?>" />
					</div>
				</div>
				
				<div class="control-group">
					<label class="control-label" for="join_tags">
						<?php echo _("At least") ?>
					</label>
					
					<?php
						$join_tags = false;
						
						if($respawn_user_search && isset($_GET[SEARCH_TAGS_JOIN])) {
							$join_tags = $_GET[SEARCH_TAGS_JOIN] == "1";
						}
					?>
					
					<div class="controls">
						<label class="radio inline">
							<input id="join_tags" type="radio" name="<?php echo SEARCH_TAGS_JOIN ?>" value="0" <?php if(!$join_tags) echo 'checked' ?> />
							 <?php echo _("one of them") ?>
						</label>
						
						<label class="radio inline">
							<input type="radio" name="<?php echo SEARCH_TAGS_JOIN ?>" value="1" <?php if($join_tags) echo 'checked' ?> />
							 <?php echo _("all of them") ?>
						</label>
					</div>
				</div>
			</fieldset>
			
			<fieldset>
				<legend><?php echo _("Pattern match") ?></legend>
				
				<div class="control-group">
					<label class="control-label" for="pattern">
						<?php echo _("Username must contain"); ?>
					</label>
					
					<?php
						$value = "";
						if($respawn_user_search && isset($_GET[SEARCH_PATTERN_TITLE])) {
							$value = $_GET[SEARCH_PATTERN_TITLE];
						}
					?>
					
					<div class="controls">
						<input type="text" class="input-xlarge" id="pattern" name="<?php echo SEARCH_PATTERN_TITLE ?>" value="<?php echo $value ?>" />
					</div>
				</div>
				
				<div class="control-group">
					<label class="control-label" for="pattern">
						<?php echo _("Description must contain"); ?>
					</label>
					
					<?php
						$value = "";
						if($respawn_user_search && isset($_GET[SEARCH_PATTERN_DESCRIPTION])) {
							$value = $_GET[SEARCH_PATTERN_DESCRIPTION];
						}
					?>
					
					<div class="controls">
						<input type="text" class="input-xlarge" id="pattern" name="<?php echo SEARCH_PATTERN_DESCRIPTION ?>" value="<?php echo $value ?>" />
					</div>
				</div>
				
				<div class="control-group">
					<label class="control-label" for="join_patterns">
						<?php echo _("At least") ?>
					</label>
					
					<?php
						$join_patterns = false;
						
						if($respawn_user_search && isset($_GET[SEARCH_PATTERN_JOIN])) {
							$join_patterns = $_GET[SEARCH_PATTERN_JOIN] == "1";
						}
					?>
					
					<div class="controls">
						<label class="radio inline">
							<input id="join_patterns" type="radio" name="<?php echo SEARCH_PATTERN_JOIN ?>" value="0" <?php if(!$join_patterns) echo 'checked' ?> />
							 <?php echo _("one of them") ?>
						</label>
						
						<label class="radio inline">
							<input type="radio" name="<?php echo SEARCH_PATTERN_JOIN ?>" value="1" <?php if($join_patterns) echo 'checked' ?> />
							 <?php echo _("both") ?>
						</label>
					</div>
				</div>
			</fieldset>
			
			<div class="form-actions">
				<button type="submit" class="btn btn-primary">
					<?php echo _("Search"); ?>
				</button>
			</div>
		</form>
	
	</div>

</div>
