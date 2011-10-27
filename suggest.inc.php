<?php
	force_login();

	if($_POST['suggest']) {
		$stmt = $database->prepare('INSERT INTO suggestions (text, user_id) VALUES (?, ?)');
		$stmt->execute(array($_POST['suggest'], user()->id));
		activity_email("Vorschlag von " . user()->name . ":" . PHP_EOL . $_POST['suggest']);
		status_message('Danke für Deinen Vorschlag!');
		gotop('index.php?q=feeds');
	}
?>
<div id="content">
	<h2>Kurs vorschlagen</h2>
	<p>
		Hier kannst Du einen Kurs vorschlagen. Bitte beachte, dass es ein wenig dauern kann,
		bis jemand den Kurs für Dich einträgt!
	</p>
	<p>
		Trage in das Textfeld bitte alle notwendigen Informationen ein. Dazu gehören auch
		URLs und vorallem Logins! Derjenige, der den Kurs für Dich einträgt, sitzt vermutlich
		<em>nicht</em> in Deiner Vorlesung.
	</p>
	<form method="post" action="index.php?q=suggest">
		<textarea name="suggest"></textarea>
		<input type="submit" class="subright" value="Vorschlagen">
	</form>
</div>
