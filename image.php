<?php
	require("system.php");
	if(user()->id == 0) {
		status_message("Dieser Dienst steht nur eingeloggten Usern zur Verfügung");
		gotop("index.php");
	}
	if(system("which convert 2>&1 > /dev/null") != 0) {
		status_message("Dieser Dienst steht nur zur Verfügung, wenn ImageMagick auf dem Serversystem installiert ist");
		gotop("index.php");
	}

	set_time_limit(0);
	if(!isset($_GET['d'])) {
		header('HTTP/1.1 404 Not found');
		die("<h1>File not found</h1>");
	}
	$image = $_GET['d'];
	if(!preg_match('#^http://#i', $image)) {
		header('HTTP/1.1 404 Not found');
		die("<h1>File not found</h1>");
	}
	if(preg_match('#\.([^\.]+)$#', $image, $extension)) {
		$extension = $extension[1];
	}
	else {
		$extension = 'pdf';
	}

	$hash = sha1('img-'.$image);
	$cache_file = 'cache/'.$hash;

	header('Expires: '.gmdate("D, d M Y H:i:s", time() + 3600).' GMT');
	header("Last-Modified: ".gmdate('D, d M Y H:i:s', filemtime($file)).' GMT');

	$cache_exists = file_exists($cache_file);
	try {
		$data = load_url($image, false, $cache_exists ? filemtime($cache_file) : false);
	}
	catch(Exception $e) {
		status_message("Dieser Dienst funktioniert nur mit PDF-Dateien");
		gotop("index.php");
	}
	if($data && $data !== true) {
		// Cache neu bauen!
		file_put_contents($cache_file . '.' . $extension, $data);
		in_shell_execution(true);
		exec("convert ".escapeshellarg($cache_file . '.' . $extension)." -trim -append png:".escapeshellarg($cache_file));
		unlink($cache_file . '.' . $extension);
		in_shell_execution(false);
	}

	if(isset($_GET['p'])) {
		if($_SERVER["HTTP_IF_MODIFIED_SINCE"]) {
			header('HTTP/1.1 304 Not modified');
		}
		header('Content-type: image/png');
		readfile($cache_file);
	}
	else {
		die("<!DOCTYPE html><html><body><img src='image.php?p=1&amp;d=".urlencode($image)."' /></body></html>");
	}
