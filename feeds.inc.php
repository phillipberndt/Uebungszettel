<?php
	force_login();

	if(!empty($_POST)) {
		// PHP Code; nur für Admins {{{
		if(user()->level >= 2 && isset($_POST['code']) && $_POST['code'] && trim($_POST['desc'])) {
			// Vorschau-Modus
			if(isset($_POST['preview'])) {
				ob_end_clean();
				$userFn = create_function('', $_POST['code']);
				admin_log("Testet Code:\n| " . str_replace("\n", "\n| ", $_POST['code']));
				foreach($userFn() as $item) {
					$sheet_out = format_data($item);
					list($url, $text) = split_data($item);
					if($url) {
						// Prüfen, ob die URL existiert
						try {
							check_if_url_changed($url, false);
						}
						catch(Exception $ignore) {
							$sheet_out .= ' (Die Datei wurde nicht gefunden)';
						}
					}
					echo('<li>'.$sheet_out.'</li>');
				}
				die();
			}

			// Einen Testdurchlauf machen
			$userFn = create_function('', $_POST['code']);
			$retval = $userFn();

			if(empty($retVal) || is_array($retVal)) {
				admin_log("Speichert Code:\n| " . str_replace("\n", "\n| ", $_POST['code']));
				$database->beginTransaction();
				$short = trim($_POST['short']);
				if(empty($short)) $short = $_POST['desc'];
				$stmt = $database->prepare("INSERT INTO feeds (owner, `desc`, code, public, short) VALUES (?, ?, ?, 1, ?);");
				$stmt->execute(array(user()->id, trim($_POST['desc']), serialize(array('code' => $_POST['code'])), $short));
				$stmt = $database->prepare("INSERT INTO user_feeds (user_id, feed_id) VALUES (?, ?);");
				$stmt->execute(array(user()->id, $database->lastInsertId()));
				$database->commit();
				gotop('index.php?q=feeds');
			}
		} // }}}
		// Neue Standardanfrage {{{
		if($_POST['url'] && $_POST['search'] && $_POST['exercise'] && trim($_POST['desc'])) {
			// Einen Testdurchlauf machen
			$old_reporthing = ini_set('display_errors', 0);
			$error = '';
			if(!preg_match('#^https?://#i', $_POST['url'])) {
				$error = 'URL ungültig.';
			}
			else if(
				preg_match('/^(.).+\1([a-zA-Z]*)$/', $_POST['search'], &$modifier) && strpos($modifier[2], 'e') === false
			) {
				set_error_handler(function($errno, $errstr) use ($error) {
					$error .= $errstr;
				});
				try {
					$contents = load_url($_POST['url']);
				}
				catch(Exception $e) {
					$error .= $e->getMessage();
				}
				$contents = html_entity_decode($contents, ENT_COMPAT, "utf-8");
				preg_match_all($_POST['search'], $contents, &$matches,  PREG_SET_ORDER);
				restore_error_handler();
				if(preg_last_error() != PREG_NO_ERROR) {
					$error = "Regulärer Ausdruck ungültig.";
				}
				else {
					$output = '';
					foreach($matches as $match) {
						$sheet = preg_replace_callback('/\$([0-9]+)/', function($vmatch) use ($match) {
								return $match[$vmatch[1]];
							}, $_POST['exercise']);
						list($sheet_url, $sheet_text) = split_data($sheet);
						$sheet_out = format_data($sheet);
						if($sheet_url) {
							// Prüfen, ob die URL existiert
							try {
								check_if_url_changed($sheet_url, false);
							}
							catch(Exception $ignore) {
								$sheet_out .= ' (Die Datei wurde nicht gefunden)';
							}
						}
						$output .= '<li>'.$sheet_out.'</li>';
					}
				}
			}
			else {
				$error .= 'Ungültiger Regulärer Ausdruck.';
			}
			ini_set('display_errors', $old_reporthing);

			// Vorschau-Modus
			if(isset($_POST['preview'])) {
				ob_end_clean();
				if($error) die("Fehler: ".$error);
				$err = error_get_last();
				if($err != null && $err['type'] & 3 != 0) die("Fehler: " . $err['message']);
				die($output);
			}

			// Kein Fehler? Dann eintragen.
			if(!$error && (($err = error_get_last()) == null || $err['type'] != 1)) {
				$database->beginTransaction();
				$short = trim($_POST['short']);
				if(empty($short)) $short = $_POST['desc'];
				$stmt = $database->prepare("INSERT INTO feeds (owner, `desc`, code, short) VALUES (?, ?, ?, ?);");
				$stmt->execute(array(user()->id, trim($_POST['desc']), serialize(array(
					'search' => $_POST['search'],
					'exercise' => $_POST['exercise'],
					'url' => $_POST['url'],
				)),
				$short));
				$stmt = $database->prepare("INSERT INTO user_feeds (user_id, feed_id) VALUES (?, ?);");
				$stmt->execute(array(user()->id, $database->lastInsertId()));
				$database->commit();
				status_message("Der Feed wurde erfolgreich erstellt.");
				gotop('index.php?q=feeds');
			}
		}
		// }}}
		// Kursauswahl {{{
		if(isset($_POST['submit'])) {
			$lectures = array();
			foreach($database->query('SELECT feed_id FROM user_feeds WHERE user_id = '.user()->id) as $lecture) $lectures[$lecture['feed_id']] = 1;
			$was_empty = empty($lectures);

			// Kurse aus POST, die es bisher nicht gibt, erstellen
			foreach(array_keys($_POST['lecture']) as $lecture) {
				if(!isset($lectures[$lecture])) {
					$database->exec('INSERT INTO user_feeds (user_id, feed_id) VALUES ('.user()->id.','.intval($lecture).');');
				} else unset($lectures[$lecture]);
			}

			// Kurse aus der Datenbank, die es nicht mehr gibt, killen
			if($lectures) {
				$database->exec('DELETE FROM user_feeds WHERE user_id = '.user()->id.' AND feed_id IN ('.implode(',', array_keys($lectures)).')');
			}

			status_message("Deine Kurse wurden erfolgreich gespeichert.");

			// Zur Startseite, falls das der erste Aufruf war.
			if($was_empty) gotop("index.php");
		}
		// }}}
		gotop('index.php?q=feeds');
	}
?>
<div id="content">
	<h2>Kurse auswählen</h2>
	<p>Bitte wähle die Kurse aus, die Du abbonieren möchtest.</p>
	<form class="feeds" method="post" action="index.php?q=feeds">
	<table id="kurse">
		<tr><th>&nbsp;</th><th>Kurs</th><th>Zettel</th><th>&nbsp;</th></tr>
		<?php
			$has_course = false;
			$query = 'SELECT id, `desc`, public, update_timestamp, 
			(SELECT COUNT(*) FROM user_feeds WHERE feed_id = feeds.id AND user_id = '.user()->id.') AS checked,
			(SELECT COUNT(*) FROM data WHERE feed_id = feeds.id) AS count
			FROM feeds
			WHERE owner = '.user()->id.' OR public = 1 '.(user()->level >= 1 ? ' OR 1 ' : '').'
			ORDER BY `desc` ASC';
			foreach($database->query($query) as $course) {
					$has_course = true;
					$classes = ($course['public'] ? '' : 'private') . ' ' .
						($course['update_timestamp'] && $course['update_timestamp'] < time() - 3600 ? 'outdated' : '');
					echo('<tr class="course ' . $classes . '"><td><input type="checkbox" name="lecture['.$course['id'].']" value="1" '.
						($course['checked'] ? 'checked' : '').'></td><td>'.
						htmlspecialchars($course['desc']).
						'</td><td>'.$course['count'].'</td><td><a href="index.php?q=details&amp;f='.$course['id'].'">Details</a></td></tr>');
			}
			if(!$has_course) {
				echo('<tr><td colspan="4">Bisher sind keine Kurse definiert.</td></tr>');
			}
		?>
	</table>
	<input type="submit" name="submit" value="Speichern">
	</form>

	<p class="info">
		Dein Kurs ist noch nicht dabei? Wenn Du Erfahrung mit regulären Ausdrücken hast, füge ihn selbst
		unten hinzu. Oder <a href="index.php?q=suggest">gib uns Bescheid</a>, dann machen wir das für Dich!
	</p>

	<div class="collapse">
	<h2>Neue Kurse hinzufügen</h2>
	<p>Falls Du Erfahrungen mit regulären Ausdrücken hast, wirf einen Blick auf <a href="index.php?q=feeds_explain">die Anleitung zum
		Eintragen von Kursen</a>. Sollte Dir die Anleitung nicht weiterhelfen, kannst Du uns <a href="index.php?q=suggest">Kurse vorschlagen</a>,
		die wir dann für Dich eintragen werden.</p>
	<form class="feeds newcourse" method="post" action="index.php?q=feeds">
	<label><span>Beschreibung</span><input type="text" name="desc" value=""></label>
	<label><span>Kürzel</span><input type="text" name="short" value=""></label>
	<label><span>URL</span><input type="text" name="url" value=""></label>
	<label><span>Suchphrase</span><input type="text" name="search" value="/regex/i"></label>
	<label><span>Übung</span><input type="text" name="exercise" value=""></label>
	<input type="submit" value="Speichern">
	</form>

	</div>

	<?php if(user()->level >= 2): ?>
	<div class="collapse">
	<h2>Neue Kurse hinzufügen (PHP)</h2>
	<form class="feeds newcourse" method="post" action="index.php?q=feeds">
	<label><span>Beschreibung</span><input type="text" name="desc" value=""></label>
	<label><span>Kürzel</span><input type="text" name="short" value=""></label>
	<textarea name="code"></textarea>
	<input type="submit" value="Speichern">
	</form>
	</div>
	<?php endif; ?>
</div>
