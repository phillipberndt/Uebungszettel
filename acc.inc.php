<?php
	force_login();

	if(!empty($_POST) && sha1(user()->salt . $_POST['old_pass']) == user()->pass) {
		// Account löschen
		if(isset($_POST['del_account'])) {
			if(!isset($_POST['del_confirm'])) {
				status_message("Bitte wähle die Checkbox zur Bestätigung aus!");
				gotop("index.php?q=acc");
			}
			else {
				$database->exec('DELETE FROM user_data WHERE user_id = '.user()->id);
				$database->exec('DELETE FROM user_feeds WHERE user_id = '.user()->id);
				$database->exec('DELETE FROM users WHERE id = '.user()->id);
				$database->exec('DELETE FROM user_autologin WHERE user_id = '.user()->id);
				session_destroy();
				session_start();
				setcookie('autologin', '', time() - 3600, 
						(dirname($_SERVER['REQUEST_URI']) == '/' ? '/' : dirname($_SERVER['REQUEST_URI']) . '/') . 'index.php?q=login',
						null, false, true);
				status_message("Dein Account wurde gelöscht.");
				gotop("index.php");
			}
		}

		// Passwort ändern
		if(isset($_POST['change_pw'])) {
			if(!trim($_POST['new_pass_1'])) {
				status_message("Bitte gib ein neues Kennwort ein, das nicht nur aus Whitespace besteht!");
				gotop("index.php?q=acc");
			}
			if($_POST['new_pass_1'] != $_POST['new_pass_2']) {
				status_message("Die neuen Kennwörter stimmen nicht überein.");
				gotop("index.php?q=acc");
			}
			else {
				user()->pass = sha1(user()->salt . $_POST['new_pass_1']);
				user_save();
				session_destroy();
				session_start();
				status_message("Dein Kennwort wurde geändert.");
				gotop("index.php");
			}
		}

		// Einstellungen ändern
		if(isset($_POST['settings'])) {
			$flags = &user()->flags;
			foreach(array('newsletter', 'atom_feed') as $setting) {
				$value = isset($_POST[$setting]) ? trim($_POST[$setting]) : false;

				if($setting == 'newsletter') {
					if($value) $flags |= USER_FLAG_WANTSMAIL;
					else $flags &= ~USER_FLAG_WANTSMAIL;
				}

				user()->$setting = $value;
			}
			user_save();
			status_message("Deine Einstellungen wurden gespeichert.");
			gotop("index.php?q=acc");
		}
	}
	elseif(!empty($_POST)) {
		status_message("Dein altes Kennwort war nicht korrekt.");
		gotop("index.php?q=acc");
	}
?>
<div id="content">
	<h2>Mein Account</h2>
	<form method="post" action="index.php?q=acc">
		<fieldset>
			<legend>Kennwort bestätigen</legend>
			<p>Für alle Änderungen hier musst Du zunächst Dein altes Kennwort bestätigen.</p>
			<label><span>Altes Kennwort</span><input type="password" name="old_pass"></label>
		</fieldset>
		<fieldset>
			<legend>Kennwort ändern</legend>
			<label><span>Neues Kennwort</span><input type="password" name="new_pass_1"></label>
			<label><span>Neues Kennwort</span><input type="password" name="new_pass_2"> (Bitte gib Dein neues Kennwort zur Bestätigung 2x ein)</label>
			<input type="submit" name="change_pw" value="Kennwort ändern">
		</fieldset>
		<fieldset>
			<legend>Account löschen</legend>
			<p>Hiermit löscht Du deinen Account sowie alle Deine Einstellungen. Die von Dir eingetragenen
				Kurse bleiben bestehen!</p>
			<label><span>Account löschen</span><input type="checkbox" name="del_confirm" value="1"></label>	
			<input type="submit" name="del_account" value="Account löschen">
		</fieldset>
		<fieldset>
			<legend>Einstellungen</legend>
			<label><span>Newsletter</span> <input type="text" name="newsletter" value="<?=htmlspecialchars(user()->newsletter)?>"></label>
			<p class="small indent">Ist hier eine Email-Adresse angegeben, werden neue Zettel per Email zugestellt</p>
			<label><span>Atom-Feed</span> <input type="checkbox" value="1" name="atom_feed" <?php
				if(user()->atom_feed !== false) echo('checked');
			?>></label>
			<p class="small indent">Hier kannst Du einstellen, ob Dein Feed aktiviert ist.
				Dein Feed ist unter <a href="atom.php?u=<?=user()->id?>">atom.php?u=<?=user()->id?></a> verfügbar.</p>
			<input type="submit" name="settings" value="Einstellungen ändern">
		</fieldset>
	</form>
</div>
