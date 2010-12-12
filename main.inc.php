<?php if(!logged_in()): 
	require("login.inc.php");
else:
	$hide_invisible = !(isset($_GET['inv']) && $_GET['inv'] == 1);
	$only_feed = isset($_GET['f']) && $_GET['f'] ? intval($_GET['f']) : false;

	if($database->query('SELECT COUNT(*) FROM user_feeds WHERE user_id = '.user()->id)->fetchColumn() == 0) {
		gotop('index.php?q=feeds');
	}

	// Notiz ändern
	if(isset($_POST['note_id']) && isset($_POST['value'])) {
		ob_end_clean();
		try {
			$stmt = $database->prepare('INSERT INTO user_data (data_id, user_id, comment) VALUES (?, ?, ?)');
			$stmt->execute(array(intval($_POST['note_id']), user()->id, $_POST['value']));
		}
		catch(Exception $e) {
			$stmt = $database->prepare('UPDATE user_data SET comment = ? WHERE data_id = ? AND user_id = ?');
			$stmt->execute(array($_POST['value'], intval($_POST['note_id']), user()->id));
		}
		die();
	}

	// Sichtbarkeit ändern
	if($_GET['d']) {
		if($database->query('SELECT COUNT(*) FROM data WHERE id = '.intval($_GET['d']))->fetchColumn() == 0) die();
		$value = $database->query('SELECT invisible FROM user_data WHERE data_id = '.intval($_GET['d']).' AND user_id = '.user()->id)->fetchColumn();
		if($value === false) {
			$database->exec('INSERT INTO user_data (user_id, data_id, invisible) VALUES ('.user()->id.', '.intval($_GET['d']).', 1)');
		}
		else {
			$database->exec('UPDATE user_data SET invisible = '.($value == 1 ? 0 : 1).' WHERE user_id = '.user()->id.' AND data_id = '.intval($_GET['d']));
		}
		if(isset($_GET['ajax'])) {
			ob_end_clean();
			die();
		}
		gotop('index.php');
	}

	// Gelesen-Status ändern
	if($_GET['r']) {
		if($database->query('SELECT COUNT(*) FROM data WHERE id = '.intval($_GET['r']))->fetchColumn() == 0) die();
		$value = $database->query('SELECT known FROM user_data WHERE data_id = '.intval($_GET['r']).
			' AND user_id = '.user()->id)->fetchColumn();
		if($value === false) {
			$database->exec('INSERT INTO user_data (user_id, data_id, known) VALUES ('.user()->id.', '.
				intval($_GET['r']).', 1)');
		}
		else {
			$database->exec('UPDATE user_data SET known = 1 WHERE user_id = '.
				user()->id.' AND data_id = '.intval($_GET['r']));
		}
	}
?>
<div id="content">
	<h2>Übersicht</h2>
	<?php
		// Gibt es Kurse, deren Update im Verzug ist?
		$update_timestamp = $database->query('SELECT min(update_timestamp) FROM feeds WHERE id IN
			(SELECT feed_id FROM user_feeds WHERE user_id = ' . user()->id . ')')->fetchColumn();
		if($update_timestamp < time() - 3600):
		?>
		<p class="info nomargin"><strong>Achtung:</strong> Die Übungsaufgaben sind nicht aktuell. Ein Feed konnte seit <?php
				$time = time() - $update_timestamp;
				$days = floor($time / 86400);
				$time %= 86400;
				if($days) echo $days . ' Tage(n), ';
				echo gmdate('H:i:s', $time);
			?> Stunden nicht geladen werden!</p>
		<?php
		endif;
	?>
	<table id="kurse-uebersicht">
		<tr><th>Kurs</th><th>Zettel</th><th>Notiz</th><th>&nbsp;</th></tr>
		<?php
			$descs = array();
			foreach($database->query('SELECT id, short FROM feeds') as $feed) $descs[$feed['id']] = $feed['short'];

			// Diese ekelige Art mit den Subqueries statt right joins ist leider
			// für SQLite-Support notwendig (Stand: SQLite 3)
			$exercises = $database->query('
				SELECT
					id, data, feed_id,
					(SELECT comment FROM user_data WHERE user_id = '.user()->id.' AND data_id = id) AS comment,
					(SELECT invisible FROM user_data WHERE user_id = '.user()->id.' AND data_id = id) AS invisible,
					(SELECT known FROM user_data WHERE user_id = '.user()->id.' AND data_id = id) AS known
				FROM
					data
				WHERE feed_id IN (SELECT feed_id FROM user_feeds WHERE user_id = '.user()->id.')'.
				' AND timestamp IS NOT NULL '.
				($only_feed !== false ? ' AND feed_id = '.$only_feed : '').
				($hide_invisible ? ' GROUP BY id HAVING (invisible IS NULL OR invisible != 1)' : '').
				' ORDER BY id ASC');
			
			$outputted = false;
			foreach($exercises as $exercise) {
				$outputted = true;
				$formatted_data = format_data($exercise['data'], $exercise['id']);
				$classes = ($formatted_data != $data && !$exercise['known']) ? ' neu' : '';
				echo('<tr class="'.$classes.'" id="data-'.$exercise['id'].'"><td><a href="index.php?inv=' . ($hide_invisible ? 0 : 1 ) .
					'&amp;f=' . $exercise['feed_id'] . '">'.
					htmlspecialchars($descs[$exercise['feed_id']]).'</a></td><td>'.
					$formatted_data.'</td><td class="editable-note" id="edit-'.$exercise['id'].'">'.
						htmlspecialchars($exercise['comment']).'</td><td>');
				echo(	'<a class="erledigt" href="index.php?inv=' . ($hide_invisible ? 0 : 1 ) . '&amp;f=' . ($only_feed !== false ? $only_feed : '') .
						'&amp;d=' . $exercise['id'] . '">'.
					($exercise['invisible'] == 1 ? 'Unerledigt' : 'Erledigt').'</a></td></tr>');
			}
			if(!$outputted) {
				echo('<tr><td colspan="4">Derzeit sind keine Aufgaben zu bearbeiten.</td></tr>');
			}
		?>
	</table>
	<p class="right small">
		<a href="index.php?inv=<?=$hide_invisible ? 1 : 0?>&amp;f=<?=$only_feed ? $only_feed : ''?>"><?=$hide_invisible ? 'Erledigte Übungen anzeigen' :
			'Erledigte Übungen ausblenden'?></a>
		<?php if($only_feed): ?>
		| <a href="index.php?inv=<?=$hide_invisible ? 0 : 1?>">Alle Fächer anzeigen</a>
		<?php endif; ?>
	</p>

	<?php if(user()->level >= 1):
		if(isset($_GET['delsug'])) {
			$uid = $database->query('SELECT user_id FROM suggestions WHERE id = '.intval($_GET['delsug']))->fetchColumn();
			if($uid) {
				status_message('Deine vorgeschlagenen Kurse wurden von uns eingestellt! Du kannst sie nun abbonieren.', $uid);
			}
			$database->query('DELETE FROM suggestions WHERE id = '.intval($_GET['delsug']));
			gotop("index.php");
		}
		$suggestions = $database->query('SELECT * FROM suggestions')->fetchAll();
		if($suggestions):
		?>
		<h3>Vorschläge</h3>
		<p>Hier findest Du Vorschläge anderer Benutzer für neue Kurse</p>
		<ul>
			<?php
				foreach($suggestions as $suggestion) {
					echo('<li>'.$suggestion['text'].' (<a class="confirm" href="index.php?delsug='.$suggestion['id'].'">Erledigt</a>)</li>');
				}
			?>
		</ul>
		<?php endif;
	endif; ?>
</div>
<?php endif; ?>
