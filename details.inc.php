<?php
	// Daten laden
	$feed_id = intval($_GET['f']);
	$stmt = $database->prepare('SELECT * FROM feeds WHERE id = ?');
	$stmt->execute(array($feed_id));
	$feed = $stmt->fetch();
	$code = unserialize($feed['code']);

	if(!$feed) {
		header('Status: 404 Not found');
		?><div id="error">Ein Feed mit dieser ID existiert nicht.</div><?php
		return;
	}

	$is_owner = $feed['owner'] == user()->id;
	$can_edit = $is_owner || user()->level >= 1;

	// Bearbeiten per AJAX
	if($can_edit && isset($_POST['property'])) {
		ob_end_clean();
		$property = $_POST['property'];
		$value = trim($_POST['value']);
		if($value == "" || strlen($value) > 5000) die();

		if(user()->level >= 2 && $property == "code") {
			$code['code'] = $value;
			$stmt = $database->prepare('UPDATE feeds SET code = ? WHERE id = ?');
			$stmt->execute(array(serialize($code), $feed_id));

			echo(preg_replace('/&lt;\?php/', '', str_replace(array("&nbsp;", "<br />"), array(" ", "<br />\n"), highlight_string("<?php ".$value, true))));
		}

		if($property == 'desc') {
			$stmt = $database->prepare('UPDATE feeds SET `desc` = ? WHERE id = ?');
			$stmt->execute(array($value, $feed_id));
		}

		if($property == 'short') {
			$stmt = $database->prepare('UPDATE feeds SET short = ? WHERE id = ?');
			$stmt->execute(array($value, $feed_id));
		}

		if(array_search($property, array('url', 'search', 'exercise')) !== false) {
			if($property == 'url'    &! preg_match('#^http://#i', $value)) {
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
				(preg_match('/^(.).+\1([a-zA-Z]*)$/', $value, &$modifier) && strpos($modifier[2], 'e') !== false) ||
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
			$database->beginTransaction();
			$database->exec('DELETE FROM feeds WHERE id = '.$feed_id);
			$database->exec('DELETE FROM user_data WHERE data_id IN (SELECT id FROM data WHERE feed_id = '.$feed_id.')');
			$database->exec('DELETE FROM user_feeds WHERE feed_id = '.$feed_id);
			$database->exec('DELETE FROM data WHERE feed_id = '.$feed_id);
			$database->commit();
			gotop("index.php?q=feeds");
		}
	}
?>
<div id="content">
	<h2>Detailinformationen</h2>
	<?php if($can_edit): ?>
	<p class="info nomargin can_edit"><em>Hinweis:</em> Klicke auf eine Eigenschaft, um sie zu bearbeiten.</p>
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
		<dt>Genutzt von Benutzern</dt>
		<dd><?php
			echo $database->query('SELECT COUNT(*) FROM user_feeds WHERE feed_id = '.$feed_id)->fetchColumn();
		?></dd>
	</dl>
	<div class="bes-right">
		<?php
			if(isset($code['code'])) {
				echo('<p>Führt PHP-Code aus:</p><div id="edit-code" class="code editable">');
				echo(preg_replace('/&lt;\?php/', '', str_replace(array("&nbsp;", "<br />"),
					array(" ", "<br />\n"), highlight_string("<?php ".$code['code'], true))));
				echo('</div>');
			}
			else {
				echo('<p>Sucht in der URL</p><p id="edit-url" class="editable url"><a href="'.htmlspecialchars($code['url']).'">'.
					htmlspecialchars($code['url']).'</a></p><p>
					nach dem regulären Ausdruck</p><pre class="editable" id="edit-search">'.htmlspecialchars($code['search']).
					'</pre><p>und gibt als Übung zurück</p><pre class="editable" id="edit-exercise">'.htmlspecialchars($code['exercise']).'</pre>');
			}
		?>
	</div>
	</div>
	<?php if($can_edit): ?>
	<p class="small right">
		<?php if(user()->level >= 1): ?>
		<a href="index.php?q=details&f=<?=$feed_id?>&action=visibility">Sichtbarkeit umschalten</a> | 
		<?php endif; ?>
		<a href="index.php?q=details&f=<?=$feed_id?>&action=delete" class="confirm">Feed löschen</a>
	</p>
	<?php endif; ?>

	<h3>Aggregierte Aufgaben</h3>
	<p class="info nomargin"><em>Hinweis:</em> Diese Liste wird regelmäßig und <em>nicht</em> mit jeder Änderung oben umgehend aktualisiert.</p>
	<?php
		$feeds = $database->query('SELECT data FROM data WHERE feed_id = '.$feed_id.' ORDER BY id ASC');
		$feed = $feeds->fetch();
		if($feed) {
			echo('<ol><li>'.format_data($feed['data']).'</li>');
			foreach($feeds as $feed) {
				echo('<li>'.format_data($feed['data']).'</li>');
			}
			echo('</ol>');
		}
		else echo('<p>- Bisher keine -</p>');
	?>
</div>
