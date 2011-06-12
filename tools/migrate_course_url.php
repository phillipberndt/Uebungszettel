<?php
	chdir("..");
	require("system.php");
	header('Content-Type: text/plain');

	$course_urls = $database->query('SELECT id, course_url FROM feeds WHERE course_url IS NOT NULL');
	$database->query('CREATE TABLE feed_links (
		feed_id INTEGER,
		title VARCHAR(120),
		url TEXT,
		PRIMARY KEY (feed_id, title)
	);');
	$query = $database->prepare('INSERT INTO feed_links (feed_id, title, url) VALUES (?, "Homepage", ?)');
	foreach($course_urls as $urlset) {
		echo('Übernehme ' . $urlset['course_url'] . "\n");
		$query->execute(array($urlset['id'], $urlset['course_url']));
	}
	
	# Not supported by Sqlite!
	$database->query('ALTER TABLE feeds DROP course_url');
