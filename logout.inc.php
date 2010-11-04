<?php
	session_destroy();
	if(isset($_COOKIE['autologin'])) {
		setcookie('autologin', '', time() - 3600, 
			(dirname($_SERVER['REQUEST_URI']) == '/' ? '/' : dirname($_SERVER['REQUEST_URI']) . '/') . 'index.php?q=login',
			null,
			false,
			true);
	}
	gotop("index.php");
?>
