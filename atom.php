<?php
	require('system.php');

	$user = intval($_GET['u']);
	$user = $database->query('SELECT * FROM users WHERE id = '.$user)->fetch(PDO::FETCH_OBJ);
	if(!$user) {
		header('Status: 404 Not found');
		echo('<div id="error">Ungültige Benutzer-ID</div>');
		return;
	}

	ob_end_clean();
	header('Content-type: application/atom+xml; charset=utf-8');
	echo('<?xml version="1.0"?>');

	$base_url = htmlspecialchars('http://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'].'/');
?>
<feed xmlns="http://www.w3.org/2005/Atom" xml:lang="de">
		<id><?=$base_url?>uebungszettel-<?=$user->id?></id>
		<title>Aktuelle Übungszettel für <?=htmlspecialchars($user->name)?></title>
		
		<?php

		$descs = array();
		foreach($database->query('SELECT id, short FROM feeds') as $feed) $descs[$feed['id']] = $feed['short'];

		$exercises = $database->query('
			SELECT
				id, data, feed_id,
				(SELECT comment FROM user_data WHERE user_id = '.$user->id.' AND data_id = id) AS comment,
				(SELECT invisible FROM user_data WHERE user_id = '.$user->id.' AND data_id = id) AS invisible
			FROM
				data
			WHERE feed_id IN (SELECT feed_id FROM user_feeds WHERE user_id = '.$user->id.')
			AND (invisible IS NULL OR invisible != 1)');
		foreach($exercises as $exercise):

		?>
			
		<entry>
		<id><?=$base_url?>uebungszettel-<?=$user->id?>uebung-<?=$exercise['id']?></id>
		<title><?=htmlspecialchars($descs[$exercise['feed_id']])?> - <?=strip_tags(format_data($exercise['data']))?></title>
		<summary type="html">
		<?=htmlspecialchars(format_data($exercise['data']))?>
		</summary>
		</entry>

		<?php endforeach; ?>
</feed>
<?php die(); ?>
