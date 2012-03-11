<?php
	require('system.php');

	$q = isset($_GET['q']) ? $_GET['q'] : 'main';
	ob_start();
	header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE HTML>
<html>
<head>
	<meta charset="utf-8">
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
	<script src="jquery-1.4.3.min.js" type="text/javascript" charset="utf-8"></script>
	<script src="uebungszettel.js" type="text/javascript" charset="utf-8"></script>
	<?php if(user()->id && user()->atom_feed !== false):
		$atom_token = substr(sha1(user()->id . user()->salt . user()->name), 0, 4);
	?>
	<link rel="alternate" type="application/atom+xml" title="Meine Übungszettel" href="atom.php?u=<?=user()->id?>&amp;t=<?=$atom_token?>">
	<?php endif; ?>
	<title>Übungszettel</title>
</head>
<body>
	<header>
		<h1>Übungszettel</h1>
	</header>
	<nav>
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
	</nav>

	<section>
	<?php
		if(logged_in() && isset(user()->saved_status_messages)) {
			if(is_array($_SESSION['status_messages'])) {
				$_SESSION['status_messages'] = array_merge($_SESSION['status_messages'], user()->saved_status_messages);
			}
			else {
				$_SESSION['status_messages'] = user()->saved_status_messages;
			}
			unset(user()->saved_status_messages);
			user_save();
		}

		$_SESSION['status_messages'][] = 'Wir testen im Moment ein neues Datenbankbackup. Deswegen kommt es zu Beeinträchtigungen!';

		if(isset($_SESSION['status_messages']) && $_SESSION['status_messages']):
		?>
			<ul id="status">
			<?php foreach($_SESSION['status_messages'] as $message) echo('<li>'.$message.'</li>'); ?>
			</ul>
		<?php
		$displayed_status_messages = count($_SESSION['status_messages']);
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

		if(isset($displayed_status_messages)) {
			$_SESSION['status_messages'] = array_slice($_SESSION['status_messages'], $displayed_status_messages);
		}
	?>
	</section>
</body>
</html>
