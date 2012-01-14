<?php
	require("system.php");
	if(system("which convert 2>&1 > /dev/null") != 0) {
		status_message("Dieser Dienst steht nur zur Verfügung, wenn ImageMagick auf dem Serversystem installiert ist");
		gotop("index.php");
	}

	set_time_limit(0);
	if(!isset($_GET['data_id'])) {
		header('HTTP/1.1 404 Not found');
		die("<DOCTYPE HTML><h1>File not found</h1>");
	}

	$data = $database->query('SELECT data FROM data WHERE id = ' . intval($_GET['data_id']))->fetchColumn();
	list($image, $text) = split_data($data);

	if(!preg_match('#^https?://#i', $image)) {
		header('HTTP/1.1 404 Not found');
		die("<DOCTYPE HTML><h1>File not found</h1>");
	}
	if(preg_match('#\.([^\.]+)$#', $image, $extension)) {
		$extension = $extension[1];
	}
	else {
		$extension = 'pdf';
	}

	$hash = sha1('img-' . $image);
	$cache_file = $cache_dir . $hash;

	$cache_exists = file_exists($cache_file);
	try {
		$data = load_url($image, false, $cache_exists ? filemtime($cache_file) : false);
	}
	catch(Exception $e) {
		// Geht anscheinend nicht - dann leiten wir halt weiter auf das Original.
		header('Location: ' . $image);
		die();
	}
	if($data && $data !== true) {
		// Cache neu bauen!
		file_put_contents($cache_file . '.' . $extension, $data);
		in_shell_execution(true);
		exec("convert ".escapeshellarg($cache_file . '.' . $extension)." -trim -append png:".escapeshellarg($cache_file));
		unlink($cache_file . '.' . $extension);
		in_shell_execution(false);
	}

	// In der Datenbank ein Update ausführen
	// Das auch, wenn eigentlich nichts geändert wurde - weil wir nämlich durch
	// den Aufruf wissen, dass die Daten noch gebraucht werden.
	// Die erzeugten Bilder einen Tag im Cache vorhalten
	$query = $database->prepare('REPLACE INTO cache (id, created_timestamp, max_age, filename) VALUES (?, ?, ?, ?)');
	$query->execute(array($hash, time(), 3600 * 24, 'image.png'));

	header("Last-Modified: ".gmdate('r', filemtime($cache_file)).' GMT');
	header('Expires: '.gmdate('r', time() + 3600).' GMT');
	if($_SERVER["HTTP_IF_MODIFIED_SINCE"]) {
		$time = strtotime(preg_replace('/;.*$/', '', $_SERVER['HTTP_IF_MODIFIED_SINCE']));
		if($time >= filemtime($cache_file)) {
			header('HTTP/1.1 304 Not modified');
			die();
		}
	}

	if(isset($_GET['p'])) {
		header('Content-type: image/png');
		readfile($cache_file);
	}
	else {
		die("<!DOCTYPE html><html><body><img src='image.php?p=1&amp;data_id=".urlencode($_GET['data_id'])."' /></body></html>");
	}
