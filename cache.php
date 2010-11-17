<?php
	// Obwohl system.php eingebunden wird sind wir nicht eingeloggt, da das Session-Cookie
	// aus Sicherheitsgründen auf /index.php beschränkt ist!
	require("system.php");
	set_time_limit(30);

	// Cachen von gespeicherten Übungen
	if(isset($_GET['data_id']) && $cache_everything) {
		$data = $database->query('SELECT data FROM data WHERE id = ' . intval($_GET['data_id']))->fetchColumn();
		list($url, $text) = split_data($data);
		if(!$url) {
			header("HTTP/1.1 404 Not found");
			die();
		}
		try {
			$_GET['cache_id'] = cache_file($url, false, true);
		}
		catch(Exception $e) {
			// Geht anscheinend nicht - dann leiten wir halt weiter.
			header('Location: ' . $image);
			die();
		}
		$_GET['filename'] = basename($url);
	}

	// Daten direkt aus dem Cache laden
	$cache_file = $cache_dir . basename($_GET['cache_id']);
	$file_name = $_GET['filename'];

	if(!file_exists($cache_file)) {
		header("HTTP/1.1 404 Not found");
		die();
	}

	header('Content-disposition: inline; filename="' . addcslashes($file_name, '"') . '"');
	header('Last-Modified: '.date('r', filemtime($cache_file)));
	if($_SERVER["HTTP_IF_MODIFIED_SINCE"]) {
		$time = strtotime(preg_replace('/;.*$/', '', $_SERVER['HTTP_IF_MODIFIED_SINCE']));
		if($time >= filemtime($cache_file)) {
			header('HTTP/1.1 304 Not modified');
			die();
		}
	}

	foreach(array(
		'pdf' => 'application/pdf',
		'png' => 'image/png',
		'jpg' => 'image/jpg',
	) as $extension => $ct) {
		if(preg_match('/\.'.$extension.'/i', $file_name)) {
			header('Content-type: '.$ct);
		}
	}
	readfile($cache_file);
