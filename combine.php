<?php
	require('system.php');

	$urls = $_POST['d'];
	$execute = 'pdftk ';
	$final_cache_id = '';
	foreach($urls as $url) {
		if(preg_match('/cache_id=([^&]+)/', $url, &$match)) {
			$cache_id = basename($match[1]);
		}
		else {
			try {
				$cache_id = cache_file($url, '', true);
			}
			catch(Exception $e) {
				status_message("Dieser Dienst funktioniert nur mit PDF-Dateien");
				gotop("index.php");
			}
		}
		if(substr(get_mime_type($cache_dir.$cache_id), 0, 15) != 'application/pdf') {
			status_message("Dieser Dienst funktioniert nur mit PDF-Dateien");
			gotop("index.php");
		}
		$execute .= escapeshellarg($cache_dir.$cache_id).' ';
		$final_cache_id .= $cache_id;
	}
	$final_cache_id = sha1($final_cache_id);
	$execute .= ' output '.escapeshellarg($cache_dir.$final_cache_id);
	if(!file_exists($cache_dir.$final_cache_id)) {
		in_shell_execution(true);
		exec($execute);
		in_shell_execution(false);
	}

	gotop('cache.php?cache_id='.$final_cache_id.'&filename=drucken.pdf');
