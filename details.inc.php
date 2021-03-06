<?php
	force_login();

	// Helferfunktionen anzeigen
	if(file_exists('local.php') && isset($_GET['show']) && $_GET['show'] == 'helper') {
		?>
		<div id="content">
			<h2>Helferfunktionen</h2>
			<p>Diese Funktionen stehen beim Schreiben von eigenem PHP-Code zusätzlich zur Verfügung:</p>
		<?php
		echo(preg_replace('/&lt;\?php/', '', str_replace("<br />", "<br>\n", highlight_file('local.php', true))));
		echo('</div>');
		return;
	}

	// Daten laden
	$feed_id = intval($_GET['f']);
	$stmt = $database->prepare('SELECT * FROM feeds WHERE id = ?');
	$stmt->execute(array($feed_id));
	$feed = $stmt->fetch();
	$code = unserialize($feed['code']);

	if(!$feed) {
		header('HTTP/1.1 404 Not found');
		?><div id="error">Ein Feed mit dieser ID existiert nicht.</div><?php
		return;
	}

	$is_owner = $feed['owner'] == user()->id;
	$can_edit = ($is_owner || user()->level >= 1) && (!isset($code['code']) || user()->level >= 2);

	// Kurse bestellen und abbestellen
	if(isset($_GET['abbo']) && user()->id) {
		if($_GET['abbo'] == 0) {
			$database->query('DELETE FROM user_feeds WHERE user_id = '.user()->id.' AND feed_id = '.$feed_id);
		}
		else {
			try {
				$database->query('INSERT INTO user_feeds (user_id, feed_id) VALUES ('.user()->id.', '.$feed_id.')');
			}
			catch(Exception $e) {
				// Ignorieren: Den Kurs hatte der User schon bestellt
			}
		}
		gotop("index.php?q=details&f=".$feed_id);
	}

	// Zusätzliche URLs
	if($_GET['get'] == 'urls') {
		ob_end_clean();
		header('Content-Type: application/json');
		$urls = array();
		foreach($database->query('SELECT title, url FROM feed_links WHERE feed_id = ' . intval($feed_id)) as $urlset) {
			$urls[$urlset['title']] = $urlset['url'];
		}
		die(json_encode($urls));
	}

	// Bearbeiten per AJAX
	if($can_edit && isset($_POST['additional_urls'])) {
		ob_end_clean();
		$title = trim($_POST['title']);
		if(!$title) die('0');
		$url = trim($_POST['url']);
		if(!$url) {
			$database->prepare('DELETE FROM feed_links WHERE feed_id = ? AND title = ?')->execute(array($feed_id, $title));
			die('1');
		}
		if($url && !preg_match('#^http://#i', $url)) {
			die('0');
		}
		$query = $database->prepare('SELECT COUNT(*) FROM feed_links WHERE feed_id = ? AND title = ?');
		$query->execute(array($feed_id, $title));
		if($query->fetchColumn() == 1) {
			$database->prepare('UPDATE feed_links SET title = ?, url = ? WHERE feed_id = ?')->execute(array($title, $url, $feed_id));
		}
		else {
			$database->prepare('INSERT INTO feed_links (feed_id, title, url) VALUES (?, ?, ?)')->execute(array($feed_id, $title, $url));
		}
		die('1');
	}

	if($can_edit && isset($_POST['property'])) {
		ob_end_clean();
		$property = $_POST['property'];

		$value = trim($_POST['value']);
		if($value == "" || strlen($value) > 5000) die();

		if(user()->level >= 2 && $property == "code") {
			$code['code'] = $value;
			$stmt = $database->prepare('UPDATE feeds SET code = ? WHERE id = ?');
			$stmt->execute(array(serialize($code), $feed_id));

			echo(preg_replace('/&lt;\?php/', '', str_replace(array("&nbsp;", "<br />"), array(" ", "<br>\n"), highlight_string("<?php ".$value, true))));
		}

		if($property == 'desc') {
			if($database->getAttribute(PDO::ATTR_DRIVER_NAME) == 'pgsql') {
				$stmt = $database->prepare('UPDATE feeds SET "desc" = ? WHERE id = ?');
			}
			else {
				$stmt = $database->prepare('UPDATE feeds SET `desc` = ? WHERE id = ?');
			}
			$stmt->execute(array($value, $feed_id));
		}

		if($property == 'short') {
			$stmt = $database->prepare('UPDATE feeds SET short = ? WHERE id = ?');
			$stmt->execute(array($value, $feed_id));
		}

		if($property == 'course_url') {
			if(!preg_match('#^http://#i', $value)) {
				die(htmlspecialchars($feed['course_url']));
			}

			$query = $database->prepare("SELECT COUNT(*) FROM feed_links WHERE feed_id = ? AND title = 'Homepage'");
			$query->execute(array($feed_id));
			if($query->fetchColumn() == 1) {
				$database->prepare("UPDATE feed_links SET url = ? WHERE feed_id = ? AND title = 'Homepage'")->execute(array($value, $feed_id));
			}
			else {
				$database->prepare("INSERT INTO feed_links (feed_id, title, url) VALUES (?, 'Homepage', ?)")->execute(array($feed_id, $value));
			}
		}

		if(array_search($property, array('url', 'search', 'exercise')) !== false) {
			if($property == 'url'    &! preg_match('#^https?://#i', $value)) {
				die(htmlspecialchars($code[$property]));
			}
			$oldvalue = $code[$property];
			set_error_handler(function($errno, $errstr) use ($oldvalue) {
				echo('<script><!--
				alert("Der eingegebene reguläre Ausdruck ist ungültig.");
				// --></script>');
				die(htmlspecialchars($oldvalue));
			});
			if($property == 'search' && (
				(preg_match('/^(.).+\1([a-zA-Z]*)$/', $value, $modifier) && strpos($modifier[2], 'e') !== false) ||
				(preg_match($value, '') || preg_last_error() != PREG_NO_ERROR)
			)) {
				echo('<script><!--
				alert("Der eingegebene reguläre Ausdruck ist ungültig.");
				// --></script>');
				die(htmlspecialchars($code[$property]));
			}
			restore_error_handler();

			$code[$property] = $value;
			$stmt = $database->prepare('UPDATE feeds SET code = ? WHERE id = ?');
			$stmt->execute(array(serialize($code), $feed_id));
		}

		die();
	}
	if($can_edit) {
		if($_GET['action'] == "visibility" && user()->level >= 1) {
			$database->exec('UPDATE feeds SET public = '.($feed['public'] ? 0 : 1).' WHERE id = '.$feed_id);
			gotop("index.php?q=details&f=".$feed_id);
		}

		if($_GET['action'] == "delete") {
			$database->exec('DELETE FROM feeds WHERE id = '.$feed_id);
			$database->exec('DELETE FROM user_data WHERE data_id IN (SELECT id FROM data WHERE feed_id = '.$feed_id.')');
			$database->exec('DELETE FROM user_feeds WHERE feed_id = '.$feed_id);
			$database->exec('DELETE FROM feed_links WHERE feed_id = '.$feed_id);
			$database->exec('DELETE FROM data WHERE feed_id = '.$feed_id);
			gotop("index.php?q=feeds");
		}
	}
?>
<div id="content">
	<h2>Detailinformationen zum Kurs <em><?=htmlspecialchars($feed['desc'])?></em></h2>
	<?php if($can_edit): ?>
	<p class="info nomargin can_edit"><em>Hinweis:</em> Klicke auf eine Eigenschaft, um sie zu bearbeiten. <noscript>Dafür benötigst Du
		Javascript!</noscript></p>
	<?php endif; ?>
	<div class="bes">
	<dl class="bes-left">
		<dt>Kurs-Bezeichnung</dt>
		<dd class="editable" id="edit-desc"><?=htmlspecialchars($feed['desc'])?></dd>
		<dt>Kürzel</dt>
		<dd class="editable" id="edit-short"><?=htmlspecialchars($feed['short'])?></dd>
		<dt>Eingestellt von</dt>
		<dd><?php
			$user = $database->query('SELECT name FROM users WHERE id = '.intval($feed['owner']))->fetchColumn();
			if(!$user) $user = '<em>Unbekannt</em>';
			echo $user;
		?></dd>
		<dt>Öffentlich sichtbar</dt>
		<dd><?=$feed['public'] ? 'Ja' : 'Nein'?></dd>
		<dt>Kurs-Homepage</dt>
		<dd class="editable url short" id="edit-course_url"><?
			$course_url = $database->query("SELECT url FROM feed_links WHERE feed_id = " .
				intval($feed['id']) . " AND title = 'Homepage'")->fetchColumn();

			if($course_url) {
				$link_title = parse_url($course_url, PHP_URL_HOST);
				echo '<a href="' . htmlspecialchars($course_url) . '">' . htmlspecialchars($link_title) . '</a>';
			}
			else {
				echo 'Keine';
			}
		?></dd>
		<dt>Genutzt von Benutzern</dt>
		<dd><?php
			echo $database->query('SELECT COUNT(*) FROM user_feeds WHERE feed_id = '.$feed_id)->fetchColumn();
		?></dd>
	</dl>
	<div class="bes-right">
		<p class="fun">Technische Funktionsweise</p>
		<?php
			if(isset($code['code'])) {
				if(!$can_edit) {
					$code['code'] = remove_authentication_from_urls($code['code']);
				}
				$helper = '';
				if(file_exists('local.php')) {
					$helper = '<span class="right small">(<a href="index.php?q=details&amp;show=helper">Helferfunktionen</a>)</span>';
				}
				echo('<p>Führt PHP-Code aus:' . $helper . '</p><div id="edit-code" class="code editable">');
				echo(preg_replace('/&lt;\?php/', '', str_replace(array("&nbsp;", "<br />"),
					array(" ", "<br>\n"), highlight_string("<?php ".$code['code'], true))));
				echo('</div>');
			}
			else {
				if(!$can_edit) {
					$code['url'] = remove_authentication_from_urls($code['url']);
					$code['exercise'] = remove_authentication_from_urls($code['exercise']);
				}
				echo('<p>Sucht in der URL</p><p id="edit-url" class="editable url"><a href="'.htmlspecialchars($code['url']).'">'.
					htmlspecialchars($code['url']).'</a></p><p>
					nach dem regulären Ausdruck</p><pre class="editable" id="edit-search">'.htmlspecialchars($code['search']).
					'</pre><p>und gibt als Übung zurück</p><pre class="editable" id="edit-exercise">'.htmlspecialchars($code['exercise']).'</pre>');
			}
		?>
	</div>
	</div>
	<?php if($can_edit): ?>
	<p class="small buttons">
		<?php if(user()->level >= 1): ?>
		<a href="index.php?q=details&f=<?=$feed_id?>&action=visibility">Sichtbarkeit umschalten</a> | 
		<?php endif; ?>
		<a href="index.php?q=details&f=<?=$feed_id?>&action=delete" class="confirm">Feed löschen</a>
	</p>
	<?php endif; ?>
	<?php if(user()->id): ?>
	<p class="abbo small">
	<?php
		$user_uses_feed = $database->query('SELECT COUNT(*) FROM user_feeds WHERE feed_id = '.$feed_id.' AND user_id = '.user()->id)->fetchColumn();
		if($user_uses_feed) {
			echo('Du abbonierst diesen Kurs. <a href="index.php?q=details&amp;f='.$feed_id.'&amp;abbo=0">Klicke hier, um ihn abzubestellen.</a>');
		}
		else {
			echo('Du abbonierst diesen Kurs nicht. <a href="index.php?q=details&amp;f='.$feed_id.'&amp;abbo=1">Klicke hier, um ihn zu bestellen.</a>');
		}
	?>
	</p>
	<?php endif; ?>

	<h3>Aggregierte Aufgaben</h3>
	<?php if($feed['update_timestamp'] && $feed['update_timestamp'] < time() - 3600): ?>
	<p class="info nomargin"><strong>Achtung:</strong> Die Übungsaufgaben zu diesem Kurs konnten seit <?php
			$time = time() - $feed['update_timestamp'];
			$days = floor($time / 86400);
			$time %= 86400;
			if($days) echo $days . ' Tage(n), ';
			echo gmdate('H:i:s', $time);
		?> Stunden nicht geladen werden!</p>
	<?php endif; ?>
	<?php if($can_edit && !$feed['update_timestamp']): ?>
	<p class="info nomargin"><em>Hinweis:</em> Es kann ein wenig dauern, bis diese Liste die letzten Änderungen berücksichtigt.</p>
	<?php endif;
		$feeds = $database->query('SELECT data, id FROM data WHERE feed_id = '.$feed_id.' AND timestamp IS NOT NULL ORDER BY id ASC');
		$feed = $feeds->fetch();
		if($feed) {
			echo('<ol><li>'.format_data($feed['data'], $feed['id']).'</li>');
			foreach($feeds as $feed) {
				echo('<li>'.format_data($feed['data'], $feed['id']).'</li>');
			}
			echo('</ol>');
		}
		else echo('<p>- Bisher keine -</p>');
	?>
</div>
