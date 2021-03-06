<?php
	$is_cron = true;
	require('system.php');
	$cron_debug = isset($cron_debug) ? (bool)$cron_debug : false;
	if(!$cron_debug) {
		error_reporting(0);
	}

	// Bei einem Aufruf via Kommandozeile Parameter korrekt verteilen
	if(isset($_SERVER['argc']) && $_SERVER['argc'] > 0) {
		if($_SERVER['argc'] > 1) {
			parse_str($_SERVER['argv'][1], $_GET);
		}
		$called_from_commandline = true;
	}

	// Das Cron-Script erzeugt für jeden Feed eine Unterabfrage, die den
	// Feed verarbeitet. Die Ausgabe wird dann in ein zentrales Array
	// eingepflegt und ganz am Ende werden die Mails versendet.

	header('Content-Type: text/plain; charset=UTF-8');
	$new_content = array();
	$content_ids = array();

	if(isset($_GET['f'])) {
		// Fehlermeldungen an dieser Stelle sind gewollt
		if($cron_debug) {
			error_reporting(E_ALL ^ E_NOTICE);
		}

		// Maximal eine Minute Zeit geben, dann abbrechen
		set_time_limit(60);

		// Dieser Code verarbeitet NUR ein Feed, nämlich das aus $_GET['f'].
		// Unten wird diese Seite rekursiv aufgerufen für alle Feeds

		// Sicherheitstoken muss stimmen, falls aus dem Web aufgerufen
		if(!isset($called_from_commandline) &&
		  ($_GET['s'] != sha1($secure_token . $_GET['t']) || $_GET['t'] > time() || $_GET['t'] < time() - 30)) {
			die(serialize(array(array(), array())));
		}

		// Ein bestimmtes Feed verarbeiten
		$feed = $database->query('SELECT id, code FROM feeds WHERE id = '.intval($_GET['f']))->fetch();
		$stmt = $database->prepare('INSERT INTO data (feed_id, data, timestamp) VALUES (?, ?, ?)');
		$feed_id = $feed['id'];
		$code = unserialize($feed['code']);

		if(isset($code['code'])) {
			try {
				$userFn = create_function('', $code['code']);
				$contents = $userFn();
			}
			catch(Exception $exception) {
				if($cron_debug) {
					echo $exception->getMessage();
				}
				die();
			}
		}
		else {
			try {
				$file = load_url($code['url']);
			}
			catch(Exception $exception) {
				if($cron_debug) {
					echo $exception->getMessage();
				}
				die();
			}
			$file = html_entity_decode($file, ENT_COMPAT, "utf-8");
			preg_match_all($code['search'], $file, $matches,  PREG_SET_ORDER);
			$contents = array();
			foreach($matches as $match) {
				$contents[] = preg_replace_callback('/\$([0-9]+)/', function($vmatch) use ($match) {
					return $match[$vmatch[1]];
				}, $code['exercise']);
			}
		}

		$known = array();
		$known_urls = array();
		$known_inactive = array();
		foreach($database->query('SELECT data, id, timestamp FROM data WHERE feed_id = '.$feed_id)->fetchAll() as $data) {
			list($url, $text) = split_data($data[0]);
			if($url) {
				$known_urls[$url] = $data[1];
			}
			$known[$data[0]] = $data[1];
			if($data[2] == null) {
				$known_inactive[$data[1]] = true;
			}
		}

		if(is_array($contents)) foreach($contents as $content) {
			list($url, $text) = split_data($content);

			// Ist die URL schon bekannt, der genaue Content aber nicht?
			if(!isset($known[$content]) && $url && isset($known_urls[$url])) {
				// Ja! D.h. die Feed-Definition wurde verändert. In dem Fall nur den Text
				// ändern, damit die gelesen/ungelesen Zustände und Notizen erhalten bleiben.
				$id = $known_urls[$url];
				$statement = $database->prepare('UPDATE data SET data = ? WHERE id = ?');
				$statement->execute(array($content, $id));
				$old_entry = array_search($id, $known);
				if($old_entry !== false) unset($known[$old_entry]);
				$known[$content] = $id;

				// Der else-Block unten wird - beabsichtigt - trotzdem ausgeführt!
			}

			// Sonst schauen, ob der Inhalt wörtlich gespeichert ist:
			if(!isset($known[$content])) {
				if($url) {
					// URL Datum cachen
					try {
						check_if_url_changed($url);
					}
					catch(Exception $ignore) {
						// Datei existiert nicht. In dem Fall Zettel auch nicht speichern.
						continue;
					}
				}
				$stmt->execute(array($feed_id, $content, time()));
				$new_content[$feed_id][] = $content;
				if($database->getAttribute(PDO::ATTR_DRIVER_NAME) == 'pgsql') {
					$content_ids[$content] = $database->lastInsertId('data_id_seq');
				}
				else {
					$content_ids[$content] = $database->lastInsertId();
				}
			}
			else
			{
				$id = $known[$content];

				// Falls URL geändert, ..
				if($url) {
					try {
						$url_check = check_if_url_changed($url);
					}
					catch(Exception $except) {
						// Datei existiert nicht mehr. In diesem Fall die Datei deaktivieren
						// (Was wir erledigen, indem wir sie nicht unset'en
						continue;
					}
					if($url_check) {
						// Überall wieder auf ungelesen setzen
						$database->query('UPDATE user_data SET invisible = 0, known = 0 WHERE
							data_id = '.$known[$content]);
						// Timestamp updaten
						$database->query('UPDATE data SET timestamp = '.time().' WHERE id = '.$id);
						// Und noch einmal per Email versenden
						if($text) {
							$up_content = $content . ' (Geändert)';
						} else {
							$up_content = $content . ' ' . basename($content) . ' (Geändert)';
						}
						$new_content[$feed_id][] = $up_content;
						$content_ids[$up_content] = $id;
					}
				}

				// Falls die Definition inaktiv war, wieder aktiv schalten
				if(isset($known_inactive[$id])) {
					$database->query('UPDATE data SET timestamp = '.time().' WHERE id = '.$id);
				}

				unset($known[$content]);
			}
		}

		if($known) {
			// Das entfernen von bekannten, nicht mehr gefundenen Zetteln hat sich als problematisch
			// herausgestellt, da häufiger Zettel versehentlich als neu eingestuft werden (weil gelöscht
			// und dann wieder eingestellt)
			// Daher anderes Prinzip: Wir setzen den timestamp auf null, sodass das Feld inaktiv wird.
			// Wird es später wieder gefunden, setzen wir ihn wieder auf time() 
			$database->exec('UPDATE data SET timestamp = NULL WHERE id IN ('. implode(",", $known) . ');');
		}

		die(serialize(array($new_content, $content_ids)));
	}

	// Im allgemeinen Fall Abbrüche ignorieren
	set_time_limit(180);
	ignore_user_abort();

	// Sicherheitstoken checken
	if(!isset($called_from_commandline) &&
	  (isset($cron_token) && !empty($cron_token) && $_GET['t'] != $cron_token)) {
		sleep(10);
		die();
	}

	// Der eigentliche Cronjob soll nicht mehrfach parallel ausgeführt werden
	if(function_exists('sem_get')) {
		$start_time = time();
		$id = ftok(__FILE__, 'c');
		$semaphore = sem_get($id, 1, 0666, 1);
		sem_acquire($semaphore);
		// Leider blockiert sem_acquire unabhängig vom time-limit. Daher müssen
		// wir die Zeit gegenchecken.
		if(time() - $start_time > 2) {
			// Da lief noch ein anderer Cron-Job. Wir wollen nicht unmittelbar
			// danach den nächsten starten..
			if($cron_debug) {
				echo "Multiple cron execution detected on " . date("c") . "\n";
			}
			die();
		}
	}

	// Bevor wir die neuen Daten laden, prüfen, ob der Cache zu groß wird
	if($allowed_cumulated_cache_size) {
		$maximal_cache_size = parse_human_readable_file_size($allowed_cumulated_cache_size);

		// Prüfen, wie groß das Cache-Verzeichnis ist
		$cache_size = 0;
		$deletion_query = $database->prepare('DELETE FROM cache WHERE id = ?');
		foreach(glob($cache_dir . '*') as $file) $cache_size += filesize($file);
		if($cache_size > $maximal_cache_size) {
			// Wir müssen etwas löschen
			$deletion_candidates = $database->query('SELECT id FROM cache WHERE created_timestamp + max_age < ' . time() . ' ORDER BY created_timestamp + max_age ASC');
			while($cache_size > $maximal_cache_size && $candidate = $deletion_candidates->fetchColumn()) {
				$cache_size -= filesize($cache_dir . $candidate);
				unlink($cache_dir . $candidate);
				$deletion_query->execute(array($candidate));
			}
		}
		if($cache_size > $maximal_cache_size) {
			echo "Warning: Maximum cache size exceeded by " . round(($cache_size - $maximal_cache_size) / 1024 / 1024, 2) . " Mb!\n";
		}
	}

	// Ausführung von der Kommandozeile: Unterabfragen via popen() durchführen
	if($called_from_commandline):

	// In diesem Fall können wir das Zeitlimit getrost erst einmal ganz abschalten,
	// denn eigene Prozesse kann man notfalls killen
	set_time_limit(0);

	// Alle Feeds in Unterprozessen verarbeiten
	foreach($database->query('SELECT id FROM feeds')->fetchAll() as $feed) {
		// Die Verarbeitung hier findet rein seriell statt
		$feed_id = $feed['id'];
		$query = 'f=' . $feed_id;

		$sub_query = popen("php " . __FILE__ . " " . $query, "r");
		$output = stream_get_contents($sub_query);
		pclose($sub_query);

		list($add_new_content, $add_content_ids) = @unserialize($output);
		if(!is_array($add_new_content) || !is_array($add_content_ids)) {
			if($cron_debug) {
				echo "Subrequest failed for feed " . $feed_id . ":\n  " . str_replace("\n", "\n  ", $output)
					. "\n";
			}
		}
		else {
			foreach($add_new_content as $key => $val) {
				$new_content[$key] = isset($new_content[$key]) ? array_merge($new_content[$key], $val) : $val;
			}
			$content_ids = array_merge_recursive($content_ids, $add_content_ids);

			// In die Tabelle eintragen, dass ein Update stattgefunden hat
			$database->query('UPDATE feeds SET update_timestamp = ' . time() . ' WHERE id = ' . $feed_id);
		}
	}

	else:
	// Ausführung via Webserver: Unterabfragen via curl durchführen

	// Alle Feeds in Unterabfragen verarbeiten
	$curl = curl_multi_init();
	foreach($database->query('SELECT id FROM feeds')->fetchAll() as $feed) {
		$sub = curl_init();
		$time = time();
		$sub_url = 'http://' . $_SERVER['SERVER_NAME'] . preg_replace('#\?.+$#', '', $_SERVER['REQUEST_URI']) . '?f=' .
			$feed['id'] . '&s=' . sha1($secure_token . $time) . "&t=" . $time;

		curl_setopt($sub, CURLOPT_URL, $sub_url);
		curl_setopt($sub, CURLOPT_TIMEOUT, 160);
		curl_setopt($sub, CURLOPT_RETURNTRANSFER, 1);
		curl_multi_add_handle($curl, $sub);

		// Die Abfrage schon einmal abschicken und eine Weile warten, um den Server nicht zu überlasten
		curl_multi_exec($curl, $running);
		sleep(10);
		set_time_limit(60);
	}
	$running = true;
	while($running) {
		curl_multi_exec($curl, $running);
		curl_multi_select($curl);
	}
	while($transfer = curl_multi_info_read($curl)) {
		$feed_id = preg_match('#f=([0-9]+)#', curl_getinfo($transfer['handle'], CURLINFO_EFFECTIVE_URL), $feed);
		if(!$feed_id) {
			if($cron_debug) {
				echo "Unrequested URL detected: " . curl_getinfo($transfer['handle'], CURLINFO_EFFECTIVE_URL) . "\n";
			}
			continue;
		}
		$feed_id = intval($feed[1]);

		if($transfer['result'] == CURLE_OK) {
			$output = curl_multi_getcontent($transfer['handle']);
			list($add_new_content, $add_content_ids) = @unserialize($output);
			if(!is_array($add_new_content) || !is_array($add_content_ids)) {
				if($cron_debug) {
					echo "Subrequest failed for feed " . $feed_id . ":\n  " . str_replace("\n", "\n  ", $output)
						. "\n";
				}
			}
			else {
				foreach($add_new_content as $key => $val) {
					$new_content[$key] = isset($new_content[$key]) ? array_merge($new_content[$key], $val) : $val;
				}
				$content_ids = array_merge_recursive($content_ids, $add_content_ids);

				// In die Tabelle eintragen, dass ein Update stattgefunden hat
				$database->query('UPDATE feeds SET update_timestamp = ' . time() . ' WHERE id = ' . $feed_id);
			}
		}
		else {
			if($cron_debug) {
				echo "Subrequest failed for feed " . $feed_id . ", HTTP-request failed.\n";
			}
		}
	}

	endif; // Unterscheidung zwischen Command Line / Webserver

	// Newsletter versenden
	if(!isset($called_from_commandline)) {
		// Das kann noch einmal lange dauern, also neues Time-Limit
		set_time_limit(600);
	}

	$user_mails = array();
	foreach($database->query('SELECT f.feed_id, fd.short, u.id FROM user_feeds f, users u, feeds fd WHERE u.id = f.user_id AND
		fd.id = f.feed_id AND u.flags & '.USER_FLAG_WANTSMAIL.' != 0') as $data) {

		$this_user = user_load('id', $data['id']);

		if(isset($new_content[$data['feed_id']]) && is_array($new_content[$data['feed_id']]) && $this_user->mail) {
			$user_mails[$this_user->mail]['name'] = $this_user->name;
			if(!isset($user_mails[$this_user->mail]['content'])) $user_mails[$this_user->mail]['content'] = array();
			foreach($new_content[$data['feed_id']] as $content) {
				$user_mails[$this_user->mail]['content'][] = array($data['short'], $content);

				if(!isset($content_ids[$content])) continue;
				$id = $content_ids[$content];
				try {
					$database->query('INSERT INTO user_data (user_id, data_id, known) VALUES ('.$data['id'].', '.
						$id.', 1);');
				}
				catch(Exception $db) {
					$database->query('UPDATE user_data SET known = 1 WHERE user_id = '.$data['id'].' AND
						data_id = '.$id);
				}
			}
		}
	}
	foreach($user_mails as $mail => $data) {
		$boundary = md5(time());
		$headers = "Content-Type: multipart/mixed; boundary=\"" . $boundary . "\"" . PHP_EOL . 
			"From: =?utf-8?Q?=C3=9Cbungen?= <noreply@" . $_SERVER['SERVER_NAME'] . ">" . PHP_EOL . 
			"Reply-To: ".$support_mail. PHP_EOL;
		$text = 'Hallo '.$data['name']."," . PHP_EOL . PHP_EOL . "für Dich stehen neue Übungszettel bereit:" . PHP_EOL;
		$attachments = PHP_EOL;
		$subjects = array();
		foreach($data['content'] as $content) {
			$short = $content[0]; $sheet = $content[1];
			$text .= ' · '.$short.': '.$sheet;
			list($sheet_url, $sheet_text) = split_data($sheet);
			if($sheet_url) {
				$subjects[] = $short . ' ' . $sheet_text;

				$text .= ' (Siehe Attachment)';
				$file_contents = cache_contents($sheet_url);
				// Hier raten wir den Namen etwas intelligenter, denn Email-Programme kommen
				// mit komischen Dateinamen nicht so gut klar wie Browser.
				if(preg_match('#cache.php.+filename=(.+)$#', $sheet_url, $match)) {
					$file_name = $match[1];
				}
				elseif(preg_match('#cache.php.+data_id=(.+)$#', $sheet_url, $match)) {
					$query = $database->prepare('SELECT filename FROM cache WHERE id = ?');
					$query->execute(array($match[1]));
					$cache_object = $query->fetch(PDO::FETCH_OBJ);
					$file_name = $cache_object->filename;
				}
				elseif(preg_match('#\.[a-z]{2,3}$#', $sheet_url) &&
				  !preg_match('#\.[a-z]{2,3}$#', $sheet_text)) {
					$file_name = basename($sheet_url);
				}
				else {
					$file_name = $sheet_text;
				}
				$mime_type = get_mime_type($file_contents, true);
				$attachments .= "--".$boundary . PHP_EOL .
					"Content-Type: ".$mime_type."; name*=UTF-8''" . rawurlencode($file_name) . PHP_EOL .
					"Content-Transfer-Encoding: base64" . PHP_EOL .
					"Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($file_name) . PHP_EOL . PHP_EOL .
					chunk_split(base64_encode($file_contents), 76, "\n") . PHP_EOL . PHP_EOL;
				unset($file_contents);
			}
			else {
				$subjects[] = $short;
			}
			$text .= PHP_EOL;
		}
		$text .= PHP_EOL . "Gruß," . PHP_EOL . "Dein Übungszettelservice" . PHP_EOL . PHP_EOL . "Ps. Wenn Du diese Email unbeabsichtigt bekommst, schreibe uns " .
			"eine Antwort. Wir bestellen diesen Dienst dann für Dich ab.";
		$message = "--" . $boundary . PHP_EOL . "Content-Type: text/plain;charset=UTF-8" . PHP_EOL .
			"Content-Transfer-Encoding: 8bit" . PHP_EOL . PHP_EOL . $text . $attachments . PHP_EOL . "--" . $boundary . "--";
		if(function_exists('mb_encode_mimeheader')) {
			$subject_encoded = mb_encode_mimeheader(implode(', ', $subjects), 'UTF-8', 'Q', "\n");
		}
		else {
			$subject_encoded = '=?UTF-8?B?' . base64_encode(implode(', ', $subjects)) . '?=';
		}
		mail($data['name'] . ' <'. $mail . '>', 'Neue =?utf-8?Q?=C3=9Cbungszettel?= (' . $subject_encoded . ')', $message, $headers);
	}
