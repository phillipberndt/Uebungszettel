<?php
	force_login();

	if(!empty($_POST) && user_load_authenticate(user()->name, $_POST['old_pass'])) {
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
		if(isset($_POST['change_pw']) && (user()->flags & USER_FLAG_IS_LDAP_ACCOUNT) == 0 || !$ldap_server) {
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
			foreach(array('mail', 'atom_feed') as $setting) {
				$value = isset($_POST[$setting]) ? trim($_POST[$setting]) : false;

				if($setting == 'mail') {
					if($value) $flags |= USER_FLAG_WANTSMAIL;
					else $flags &= ~USER_FLAG_WANTSMAIL;

					if((user()->flags & USER_FLAG_IS_LDAP_ACCOUNT) != 0 && $ldap_server) {
						// Mail-Adresse nicht überschreiben mit dem 1/0-Wert aus dem radio-Button
						continue;
					}
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

		if(isset($_POST['ldap_connect']) && $ldap_server) {
			if(ldap_authenticate(user()->name, $_POST['ldap_pass'])) {
				user()->flags |= USER_FLAG_IS_LDAP_ACCOUNT;
				user()->pass = '';
				user()->salt = '';
				user_save();

				$database->query('DELETE FROM user_autologin WHERE user_id = ' . user()->id);
				session_destroy();
				session_start();
				status_message("Dein Account ist jetzt mit dem LDAP-Server verknüpft.");
				gotop("index.php");
			}
			else {
				status_message("Dein LDAP-Kennwort war nicht korrekt, oder Du hast keinen LDAP-Account");
			}
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
			<?php if((user()->flags & USER_FLAG_IS_LDAP_ACCOUNT) == 0 || !$ldap_server): ?>
			<label><span>Neues Kennwort</span><input type="password" name="new_pass_1"></label>
			<label><span>Neues Kennwort</span><input type="password" name="new_pass_2"> (Bitte gib Dein neues Kennwort zur Bestätigung 2x ein)</label>
			<input type="submit" name="change_pw" value="Kennwort ändern">
			<?php else: ?>
			<p>
				Dein Kennwort kannst Du direkt auf dem <a href="<?=$ldap_web?>">LDAP-Server</a> ändern.
			</p>
			<?php endif; ?>
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
			<?php if((user()->flags & USER_FLAG_IS_LDAP_ACCOUNT) == 0 || !$ldap_server): ?>
			<label><span>Mail</span> <input type="text" name="mail" value="<?=htmlspecialchars(user()->mail)?>"></label>
			<p class="small indent">Ist hier eine Email-Adresse angegeben, werden neue Zettel per Email zugestellt</p>
			<?php else: ?>
			<label><span>Newsletter empfangen</span> <input type="radio" name="mail" value="1" <?=(user()->flags & USER_FLAG_WANTSMAIL) == 1 ? 'checked' : ''?>> Ja</label>
			<label><span>&nbsp;</span> <input type="radio" name="mail" value="0" <?=(user()->flags & USER_FLAG_WANTSMAIL) == 0 ? 'checked' : ''?>> Nein</label>
			<p class="small indent">Falls ausgewählt werden neue Zettel per Email an <em><?=htmlspecialchars(user()->mail)?></em> zugestellt</p>
			<?php endif; ?>
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
		<?php if($ldap_server && (user()->flags & USER_FLAG_IS_LDAP_ACCOUNT) == 0): ?>
		<fieldset>
			<legend>LDAP-Login</legend>
			<p>
				Du kannst Deinen Login mit dem <a href="<?=$ldap_web?>">LDAP-Server</a>
				verbinden. So werden Dein Benutzername, Kennwort und die
				Email-Adresse zentral verwaltet. Beachte: Diesen Schritt kann nur
				ein Administrator für Dich rückgängig machen!
			</p>
			<p>
				Um fortzufahren, bestätige bitte den Umzug mit dem Kennwort des
				LDAP-Benutzers <em><?=htmlspecialchars(user()->name)?></em>.
			</p>
			<label><span>Kennwort</span> <input type="password" name="ldap_pass" value=""></label>
			<input type="submit" name="ldap_connect" value="Fest mit LDAP-Account verknüpfen">
		<?php endif; ?>
	</form>
</div>
