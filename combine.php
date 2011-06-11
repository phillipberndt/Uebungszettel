<?php
	require('system.php');

	// Aufrufe in zu kurzen Abständen unterbinden (irgendein Bug in machen Browsern)
	if(isset($_SESSION['latest_combine_call']) && $_SESSION['latest_combine_call'] + 5 > time()) {
		gotop("index.php");
	}
	$_SESSION['latest_combine_call'] = time();

	$data_ids = array_map(intval, $_POST['d']);
	$combine = $database->query('SELECT data FROM data WHERE id IN (' . implode(', ', $data_ids) . ')');

	if(is_dir('pdftk')) {
		$execute = 'LD_LIBRARY_PATH=pdftk pdftk/pdftk ';
	}
	else {
		$execute = 'pdftk ';
	}
	$final_cache_id = '';
	foreach($combine as $data) {
		list($url, $title) = split_data($data['data']);
		if(!$url) continue;

		if(preg_match('/cache_id=([^&]+)/', $url, $match)) {
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

	// Je nach Aktion handeln
	$action = $_POST['action'];
	if($action == "Drucken" && $ssh_printing_enabled) {
		// Per SSH an den Drucker übergeben
		if(!user()->ssh) {
			status_message("Für diese Funktion muss ein <a href='index.php?q=acc'>SSH-Zugang konfiguriert</a> sein.");
			gotop("index.php");
		}
		else {
			$pdf_data = file_get_contents($cache_dir.$final_cache_id);
			in_shell_execution(true);
			$ssh_program = popen("ssh -o PasswordAuthentication=no -i " . escapeshellarg($ssh_printing_privkey_file) . " -a -k -q -x " .
				escapeshellarg(user()->ssh['account']) . '@' . escapeshellarg($ssh_printing_host) . " lp", "w");
			fwrite($ssh_program, $pdf_data);
			$status = pclose($ssh_program);
			in_shell_execution(false);

			if($status == 0) {
				status_message("Der Druckauftrag wurde erfolgreich weitergegeben");
				gotop("index.php");
			}
			else {
				if($status == 1) {
					status_message("Fehler beim Drucken. Hast Du den Druckernamen richtig eingetippt?");
				}
				elseif($status == 255) {
					status_message("Fehler beim Drucken. Hast Du die Anweisungen in der Email befolgt (Schlüssel eingetragen)?");
				}
				gotop("index.php");
			}
		}
	}
	else {
		// PDF herunterladen
		gotop('cache.php?cache_id='.$final_cache_id.'&filename=drucken.pdf');
	}
