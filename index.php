<?php
	require('system.php');

	$q = isset($_GET['q']) ? $_GET['q'] : 'main';
	ob_start();
	header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE HTML>
<html>
<head>
	<meta http-equiv="content-type" content="text/html; charset=utf-8">
	<?php // Diesen Check per PHP zu machen ist leider nötig, da einige Handys nicht wissen, dass sie Handys sind o_O
	      if(is_mobile()): 
	?>
	<style type="text/css">
		@import url('style.handheld.css');
	</style>
	<?php else: ?>
	<style type="text/css" media="screen">
		@import url('style.css');
	</style>
	<?php endif; ?>
	<script src="jquery-1.4.2.min.js" type="text/javascript" charset="utf-8"></script>
	<script src="uebungszettel.js" type="text/javascript" charset="utf-8"></script>
	<?php if(user()->id && user()->atom_feed !== false): ?>
	<link rel="alternate" type="application/atom+xml" title="Meine Übungszettel" href="atom.php?u=<?=user()->id?>">
	<?php endif; ?>
	<title>Übungszettel</title>
</head>
<body>
	<h1>Übungszettel</h1>
	<ul id="nav">
		<li><a href="index.php">Übersicht</a></li>
		<?php if(logged_in()): ?>
		<li><a href="index.php?q=feeds">Meine Kurse</a></li>
		<li><a href="index.php?q=acc">Mein Account</a></li>
		<?php if(user()->level >= 2): ?>
		<li><a href="index.php?q=admin">Administration</a></li>
		<?php endif; ?>
		<li><a href="index.php?q=logout">Logout</a></li>
		<?php endif; ?>
	</ul>

	<?php
		if(isset($_SESSION['status_messages']) && $_SESSION['status_messages']):
		?>
			<ul id="status">
			<?php foreach($_SESSION['status_messages'] as $message) echo('<li>'.$message.'</li>'); ?>
			</ul>
		<?php
		$_SESSION['status_messages'] = array();
		endif;

		if(file_exists(basename($q) . '.inc.php')) {
			require(basename($q) . '.inc.php');
		}
		else {
			header('HTTP/1.1 404 Not found');
			?>
			<div id="error">
				Die angegebene Seite wurde nicht gefunden.
			</div>
			<?php
		}
	?>
</body>
</html>
