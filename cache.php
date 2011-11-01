<?php
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
			$_GET['cache_id'] = cache_file($url, false, true, 3600 * 24);
		}
		catch(Exception $e) {
			// Geht anscheinend nicht - dann leiten wir halt weiter.
			header('Location: ' . $image);
			die();
		}
	}

	// Daten direkt aus dem Cache laden
	$cache_id = $_GET['cache_id'];
	$cache_file = $cache_dir . basename($cache_id);

	$query = $database->prepare('SELECT * FROM cache WHERE id = ?');
	$query->execute(array($cache_id));
	$cache_object = $query->fetch(PDO::FETCH_OBJ);
	if(!$cache_object || !file_exists($cache_file)) {
		header("HTTP/1.1 404 Not found");
		die();
	}

	header('Content-disposition: inline; filename="' . addcslashes($cache_object->filename, '"') . '"');
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
		if(preg_match('/\.'.$extension.'/i', $cache_object->filename)) {
			header('Content-type: '.$ct);
		}
	}
	readfile($cache_file);
