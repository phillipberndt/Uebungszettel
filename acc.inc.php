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
						(dirname($_SERVER['REQUEST_URI']) == '/' ? '/' : dirname($_SERVER['REQUEST_URI']) . '/'),
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
				$database->query('DELETE FROM user_autologin WHERE user_id = ' . user()->id);
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

		// SSH-Schlüssel erzeugen
		if(isset($_POST['ssh']) && $ssh_printing_enabled) {
			if($_POST['security_code'] && user()->ssh && $_POST['security_code'] == user()->ssh['code'] && $_POST['fb_account'] == user()->ssh['account']) {
				// Sicherheitscode bestätigt.
				user()->ssh = array('account' => $_POST['fb_account'], 'validated' => true);
				user_save();
				status_message("Dein Fachbereichsaccount wurde gespeichert.");
				gotop("index.php?q=acc");
			}
			elseif($_POST['security_code'] && user()->ssh) {
				status_message("Der eingegebene Sicherheitscode ist falsch");
			}

			// Sicherheitscode versenden
			if(!preg_match('#^[0-9A-Za-z_-]+$#', $_POST['fb_account'])) {
				status_message("Ungültige Eingabe!");
				gotop("index.php?q=acc");
			}
			$security_code = md5($secure_token . $_POST['fb_account'] . microtime());
			mail($_POST['fb_account'] . $ssh_printing_email_suffix, 'Validierung Deines Accounts',
				"Hallo " . user()->name . "," . PHP_EOL . PHP_EOL .
				"Du bekommst diese Mail, weil Du beim Übungszetteldienst Deinen" . PHP_EOL .
				"Fachbereichsaccount registriert hast. Du musst jetzt den Sicherheitscode in den" . PHP_EOL .
				"Einstellungen eingeben und eine Datei in Deinem Fachbereichsaccount ändern." . PHP_EOL .
				"Dann steht Dir die Drucken-Funktion zur Verfügung." . PHP_EOL . PHP_EOL .
				"Der Sicherheitscode lautet:" . PHP_EOL . PHP_EOL . "     " . $security_code . PHP_EOL . PHP_EOL .
				"In Deinem Fachbereichsaccount öffne bitte die Datei ~/.ssh/authorized_keys in" . PHP_EOL .
				"einem Editor. Eventuell musst Du diese Datei auch erst anlegen. Füge unten ans" . PHP_EOL .
				"Ende die folgende Zeile ein:" . PHP_EOL . PHP_EOL .
				file_get_contents($ssh_printing_pubkey_file) . PHP_EOL . PHP_EOL .
				"Den dort angegebenen Drucker musst Du gegebenenfalls auf Deinen Lieblingsdrucker am" . PHP_EOL .
				"Fachbereich ändern." . PHP_EOL . PHP_EOL . "Noch einmal als Info: Mit dieser Änderung erhalten wir die" . PHP_EOL .
				"Möglichkeit, uns auf Deinem Account einzuloggen. Dabei können wir aber nur den" . PHP_EOL .
				"am Anfang der Zeile angegebenen Befehl ausführen, in diesem Fall ein" . PHP_EOL .
				"Druckbefehl. Möchtest Du das nicht länger, reicht es, diese Zeile wieder zu" . PHP_EOL .
				"entfernen." . PHP_EOL .
				"" . PHP_EOL . "Gruß," . PHP_EOL . "Dein Übungszettelservice" . PHP_EOL . PHP_EOL .
				"Ps. Wenn Du diese Email unbeabsichtigt bekommst, schreibe uns " .
				"eine Antwort. Wir bestellen diesen Dienst dann für Dich ab.",
				"Content-type: text/plain; charset=UTF-8" . PHP_EOL .
				"From: =?utf-8?Q?=C3=9Cbungen?= <noreply@" . $_SERVER['SERVER_NAME'] . ">" . PHP_EOL .
				"Reply-To: ".$support_mail . PHP_EOL);
			user()->ssh = array('account' => $_POST['fb_account'], 'code' => $security_code);
			user_save();
			status_message("Wir haben Dir Deinen neuen Sicherheitscode zugeschickt!");
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
				<?php
					$atom_token = substr(sha1(user()->id . user()->salt . user()->name), 0, 4);
				?>
				Dein Feed ist unter <a href="atom.php?u=<?=user()->id?>&amp;t=<?=$atom_token?>">atom.php?u=<?=user()->id?>&amp;t=<?=$atom_token?></a>
					verfügbar.</p>
			<input type="submit" name="settings" value="Einstellungen ändern">
		</fieldset>
		<?php if($ssh_printing_enabled): ?>
		<fieldset>
			<legend>Drucken</legend>
			<p class="small indent">Wenn Du willst, kannst Du mithilfe der „PDFs kombinieren“ Funktion der Übersicht Deine Zettel direkt ausdrucken.
				Dazu musst Du aber Deinen Fachbereichsaccount so konfigurieren, dass dieser Dienst darüber drucken kann.
			</p>
			<label><span>FB-Account</span> <input type="text" name="fb_account" value="<?=htmlspecialchars(user()->ssh ? user()->ssh['account'] : user()->name)?>"></label>
			<?php if(user()->ssh && user()->ssh['validated']): ?>
				<p>Dein Account wurde bestätigt. Du kannst die Drucken-Funktion jetzt verwenden!</p>
			<?php else: ?>
				<label><span>Sicherheitscode</span> <input type="text" name="security_code" value=""></label>
				<p class="small indent">Den Sicherheitscode bekommst Du bei Änderungen hier per Email an Deinen Fachbereichsaccount geschickt. Du
					musst ihn eingeben, bevor die Drucken-Funktion aktiviert wird.</p>
			<?php endif; ?>
			<input type="submit" name="ssh" value="Fachbereichsaccount speichern">
		</fieldset>
		<?php endif; ?>
	</form>
</div>
