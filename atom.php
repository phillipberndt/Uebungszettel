<?php
	require('system.php');

	$user = user_load('id', intval($_GET['u']));
	if(!$user) {
		header('HTTP/1.1 404 Not found');
		header('Content-Type: text/html; charset=utf-8');
		echo('<div id="error">Ungültige Benutzer-ID</div>');
		return;
	}
	$atom_token = substr(sha1($user->id . $user->salt . $user->name), 0, 4);

	if($user->atom_feed === false || !isset($_GET['t']) || $_GET['t'] != $atom_token) {
		header('HTTP/1.1 403 Access denied');
		header('Content-Type: text/html; charset=utf-8');
		echo('<div id="error">Entweder wurde dieser Feed deaktiviert oder der Link ist inkorrekt.</div>');
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
		<updated><?=date('c')?></updated>
		<link rel="self" href="http://<?=$_SERVER['SERVER_NAME']?>/atom.php?u=<?=$user->id?>" />
		<link rel="alternate" href="/" />
		<author><name>Übungszettelagregator</name></author>
		
		<?php

		$descs = array();
		foreach($database->query('SELECT id, short FROM feeds') as $feed) $descs[$feed['id']] = $feed['short'];

		$exercises = $database->query('
			SELECT
				id, data, feed_id, timestamp,
				(SELECT comment FROM user_data WHERE user_id = '.$user->id.' AND data_id = id) AS comment,
				(SELECT invisible FROM user_data WHERE user_id = '.$user->id.' AND data_id = id) AS invisible
			FROM
				data
			WHERE feed_id IN (SELECT feed_id FROM user_feeds WHERE user_id = '.$user->id.')
			AND (invisible IS NULL OR invisible != 1)');
		foreach($exercises as $exercise):

		?>
			
		<entry>
		<updated><?=date('c', $exercise['timestamp'])?></updated>
		<id><?=$base_url?>uebungszettel-<?=$user->id?>uebung-<?=$exercise['id']?></id>
		<title><?=htmlspecialchars($descs[$exercise['feed_id']])?> - <?=strip_tags(format_data($exercise['data']))?></title>
		<summary type="html">
		<?=htmlspecialchars(format_data($exercise['data']))?>
		</summary>
		<?php if(preg_match('#^(https?://\S+)(.*)#i', $exercise['data'], &$url)): ?>
			<link title="<?=htmlspecialchars(trim($url[2]))?>" href="<?=htmlspecialchars($url[1])?>" rel="alternate" />
		<?php else: ?>
			<content type="text"><?=htmlspecialchars($exercise['data'])?></content>
		<?php endif; ?>
		</entry>

		<?php endforeach; ?>
</feed>
<?php die(); ?>
