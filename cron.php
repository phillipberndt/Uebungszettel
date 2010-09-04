<?php
	require('system.php');
	set_time_limit(180);
	error_reporting(0);
	ignore_user_abort();

	// Zettel updaten
	$database->beginTransaction();
	$stmt = $database->prepare('INSERT INTO data (feed_id, data) VALUES (?, ?)');
	$new_content = array();
	foreach($database->query('SELECT id, code FROM feeds') as $feed) {
		$id = $feed['id'];
		$code = unserialize($feed['code']);

		if(isset($code['code'])) {
			try {
				$userFn = create_function('', $code['code']);
				$contents = $userFn();
			}
			catch(Exception $ignore) {}
		}
		else {
			try {
				$file = load_url($code['url']);
			}
			catch(Exception $ignore) {}
			$file = html_entity_decode($file, ENT_COMPAT, "utf-8");
			preg_match_all($code['search'], $file, &$matches,  PREG_SET_ORDER);
			$contents = array();
			foreach($matches as $match) {
				$contents[] = preg_replace_callback('/\$([0-9]+)/', function($vmatch) use ($match) {
					return $match[$vmatch[1]];
					}, $code['exercise']);
			}
		}

		$known = array();
		foreach($database->query('SELECT data, id FROM data WHERE feed_id = '.$id)->fetchAll() as $data) $known[$data[0]] = $data[1];

		if(is_array($contents)) foreach($contents as $content) {
			if(!isset($known[$content])) {
				$stmt->execute(array($id, $content));
				$new_content[$id][] = $content;
				if(preg_match('#^(http://[^ ]+)( .+)?#i', $content, &$match)) {
					// URL Datum cachen
					check_if_url_changed($match[1]);
				}
			}
			else
			{
				// Falls URL geändert, überall wieder auf ungelesen setzen
				if(preg_match('#^(http://[^ ]+)( .+)?#i', $content, &$match)) {
					if(check_if_url_changed($match[1])) {
						$database->query('UPDATE user_data SET invisible = 0, known = 0 WHERE
							data_id = '.$known[$content]);
					}
				}
				unset($known[$content]);
			}
		}

		if($known) {
			$database->exec('DELETE FROM user_data WHERE data_id IN ('. implode(",", $known) . ');');
			$database->exec('DELETE FROM data WHERE id IN ('. implode(",", $known) . ');');
		}
	}
	$database->commit();

	// Newsletter versenden
	$user_mails = array();
	foreach($database->query('SELECT f.feed_id, fd.short, u.name, u.settings FROM user_feeds f, users u, feeds fd WHERE u.id = f.user_id AND
		fd.id = f.feed_id AND u.flags & '.USER_FLAG_WANTSMAIL.' != 0') as $data) {
		$settings = unserialize($data['settings']);
		if(is_array($new_content[$data['feed_id']]) && $settings['newsletter']) {
			$user_mails[$settings['newsletter']]['name'] = $data['name'];
			$user_mails[$settings['newsletter']]['short'] = $data['short'];
			if(!isset($user_mails[$settings->newsletter]['content'])) $user_mails[$settings['newsletter']]['content'] = array();
			$user_mails[$settings['newsletter']]['content'] = array_merge($user_mails[$settings['newsletter']]['content'], $new_content[$data['feed_id']]);
		}
	}
	foreach($user_mails as $mail => $data) {
		$boundary = md5(time());
		$headers = "Content-Type: multipart/mixed; boundary=\"" . $boundary . "\"\r\n".
			"From: =?utf-8?Q?=C3=9Cbungen?= <noreply@" . $_SERVER['SERVER_NAME'] . ">\r\n".
			"Reply-To: ".$support_mail."\r\n";
		$text = 'Hallo '.$data['name'].",\r\n\r\nfür Dich stehen neue Übungszettel bereit:\r\n";
		$attachments = "\r\n";
		foreach($data['content'] as $uebung) {
			$text .= ' · '.$data['short'].': '.$uebung;
			if(preg_match('#^(http://[^ ]+)( .+)?#i', $uebung, &$match)) {
				$text .= ' (Siehe Attachment)';
				$file_contents = cache_contents($match[1]);
				$file_name = trim($match[2]) ? trim($match[2]) : basename($match[1]);
				$mime_type = get_mime_type($file_contents, true);
				$attachments .= "--".$boundary."\r\n".
					"Content-Type: ".$mime_type."; name=\"" . addslashes($file_name) . "\"\r\n" .
					"Content-Transfer-Encoding: base64\r\n" .
					"Content-Disposition: attachment\r\n\r\n" . chunk_split(base64_encode($file_contents)) . "\r\n\r\n";
				unset($file_contents);
			}
			$text .= "\r\n";
		}
		$text .= "\r\nGruß,\r\nDein Übungszettelservice\r\n\r\nPs. Wenn Du diese Email unbeabsichtigt bekommst, antworte auf sie zum abbestellen dieses Dienstes.";
		$message = "--" . $boundary . "\nContent-Type: text/plain;charset=UTF-8\n\n" . $text . $attachments;
		mail($data['name'] . ' <'. $mail . '>', 'Neue =?utf-8?Q?=C3=9Cbungszettel?=', $message, $headers);
	}
