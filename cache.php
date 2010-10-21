<?php
	require("system.php");
	set_time_limit(0);
	$cache_file = $cache_dir . basename($_GET['cache_id']);
	$file_name = $_GET['filename'];

	if(!file_exists($cache_file)) {
		header("HTTP/1.1 404 Not found");
		die();
	}

	header('Content-disposition: attachment; filename="' . addcslashes($file_name, '"') . '"');
	header('Last-Modified: '.date('r', filemtime($cache_file)));
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
