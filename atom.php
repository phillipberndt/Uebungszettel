<?php
	require('system.php');

	$user = user_load('id', intval($_GET['u']));
	if(!$user) {
		header('HTTP/1.1 404 Not found');
		header('Content-Type: text/html; charset=utf-8');
		echo('<div id="error">Ungültige Benutzer-ID</div>');
		return;
	}

	if($user->atom_feed === false) {
		header('HTTP/1.1 403 Access denied');
		header('Content-Type: text/html; charset=utf-8');
		echo('<div id="error">Dieser Feed wurde deaktiviert.</div>');
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
