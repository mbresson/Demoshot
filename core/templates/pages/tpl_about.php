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

require_once('core/Demoshot.php');

?>

<div class="container">

<div class="row">
	<div class="span10"><h1><?php echo _("About") ?></h1></div>
	
	<div class="span2"><img src='res/img/Demoshot.png' alt='' /></div>
</div>

<h3><?php echo _("In a nutshell") ?></h3>

<p>
<?php
	$code_url = "https://github.com/mbresson/Demoshot";

	$code_ahref = "<a href='$code_url' title='" . _("Demoshot on Github") . "'>" . _("on Github") . "</a>";

	printf(
		_("Demoshot is a photo sharing website created by %s. It is released under the terms of the <a href='res/others/LICENSE' title='Read the terms of the license'>%s</a> license. The source code is available %s."),
		DEMOSHOT_AUTHOR, DEMOSHOT_LICENSE, $code_ahref
	);
?>
</p>

<h3><?php echo _("The big picture") ?></h3>

<p>
<?php
	echo _("Today (December 27, 2012) I'm a second-year student at the Institute of Technology of Champs-sur-Marne (France) in the department of “Communication Networks and Services”. Demoshot is the outcome of a work asked by my PHP teacher. Its development may be discontinued hereafter, due to a lack of time. Nonetheless I hope you have a great time using it.");
?>
</p>

<h3><?php echo _("Credits") ?></h3>

<p>
<?php
	echo _("I used <strong><a href='http://twitter.github.com/bootstrap/' title='Visit the website of Bootstrap'>Twitter Bootstrap</a></strong> to create the UI of Demoshot. I would like to thank <strong>Mark Otto</strong> and <strong>fat-kun</strong> for their work and for the nice documentation they wrote for Bootstrap. The documentation helped me a lot to get familiar with the tool.");
?>
</p>

<p>
<?php
	echo _("I'd also like to thank all the people who worked on the <strong><a href='https://code.google.com/p/html5shiv/' title='See the page of HTML5 Shiv'>HTML5 Shiv</a></strong> script. Even though my computer doesn't run Windows, I'm pleased to know that Internet Explorer<10 users are able to browse Demoshot without the quirks of a non-HTML5-capable browser.");
?>
</p>

<p>
<?php
	echo _("Last but not least, I want to thank the developers of the tools I use in my everyday life: <strong><a href='http://www.linuxfoundation.org/' title='Visit the website of the Linux Foundation'>GNU/Linux</a></strong>, <strong><a href='http://www.geany.org/' title='Visit the website of Geany'>Geany</a></strong>, <strong><a href='http://www.poedit.net/' title='Visit the website of Poedit'>Poedit</a></strong>, the list never ends...");
?>
</p>

</div>
