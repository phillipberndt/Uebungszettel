<?php
	$database->query('DELETE FROM user_autologin WHERE user_id = ' . user()->id);
	setcookie('autologin', '', time() - 3600, 
			(dirname($_SERVER['REQUEST_URI']) == '/' ? '/' : dirname($_SERVER['REQUEST_URI']) . '/'),
			null, false, true);
	session_destroy();
	gotop("index.php");
?>
