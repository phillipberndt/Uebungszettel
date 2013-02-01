<?php
	if(logged_in()) gotop("index.php");

	// Per Autologin einloggen
	if(isset($_COOKIE['autologin']) && strpos($_COOKIE['autologin'], '-') !== false) {
		list($autologin, $token) = explode('-', $_COOKIE['autologin'], 2);
		$query = $database->prepare('SELECT token, user_id FROM user_autologin WHERE id = ?');
		$query->execute(array($autologin));
		$result = $query->fetch(PDO::FETCH_OBJ);

		if($result) {
			if($result->token != $token) {
				// Hier liegt ein Sicherheitsproblem vor!
				status_message("Dein Login-Cookie wurde zwischenzeitlich von einem anderen Standort aus verwendet." .
					" Du wurdest aus Sicherheitsgründen ausgeloggt.");
				$query = $database->prepare('DELETE FROM user_autologin WHERE id = ?');
				$query->execute(array($autologin));
				gotop("index.php?q=login");
			}
			// Neues Sicherheitstoken erzeugen
			$token = sha1($token . '-' . microtime() . '-' . rand());
			$query = $database->prepare('UPDATE user_autologin SET token = ? WHERE id = ?');
			$query->execute(array($token, $autologin));
			setcookie('autologin', $autologin . '-' . $token, time() + 15552000, 
				(dirname($_SERVER['REQUEST_URI']) == '/' ? '/' : dirname($_SERVER['REQUEST_URI']) . '/'),
				null, false, true);
			// Benutzer einloggen
			$user = user_load('id', $result->user_id);
			if(!$user) {
				setcookie('autologin', null, time() - 1,
					(dirname($_SERVER['REQUEST_URI']) == '/' ? '/' : dirname($_SERVER['REQUEST_URI']) . '/'),
					null, false, true);
				gotop("index.php");
			}
			else {
				$_SESSION['logged_in'] = true;
				$_SESSION['login'] = $user;
				gotop('index.php');
			}
		}
	}

	// Formzielbehandlung nur bei korrekter URL
	if($_GET['q'] == "login"):

	// Namens-Check für Anmeldung
	if(isset($_GET['name_check'])) {
		ob_end_clean();
		$query = $database->prepare('SELECT COUNT(*) FROM users WHERE name = ?');
		$query->execute(array($_GET['name_check']));
		if($query->fetchColumn() != 0) {
			die("Dieser Benutzername ist bereits vergeben.");
		}
		die();
	}

	// Registierungsinfo nochmals anzeigen, falls Token gesetzt
	if(isset($_REQUEST['token'])) {
		$is_register = true;
	}

	$errName = $errPass = '';
	if(isset($_REQUEST['action'])) { // Request statt Post ist Absicht!
		if($_POST['action'] != 'Anmelden') {
			// D.h. wir sind bei der Registrierung

			// Registrierung auf IP-Bereiche einschränken
			if(isset($restrict_registration) && $restrict_registration) {
				$register_ok = false;
				$client_ip = ip2bin($_SERVER['REMOTE_ADDR']);
				foreach($restrict_registration as $ip_range) {
					list($ip, $sub) = explode('/', $ip_range);
					$mask_ip = ip2bin($ip);

					if(strncmp($client_ip, $mask_ip, $sub) == 0) {
						$register_ok = true;
						break;
					}
				}

				if(!$register_ok && isset($_POST['token'])) {
					// Registrierungslink validieren
					list($time, $hash) = explode('-', $_POST['token'], 2);
					if($time > time() - 3600 * 24 * 2 && substr(md5($time . "register" . $secure_token), 0, 5) == $hash) {
						$register_ok = true;
					}
				}

				if(!$register_ok && isset($_POST['register_mail']) && $restrict_registration_mail_allow) {
					// Registrierungsmail zusenden
					$valid = false;
					foreach($restrict_registration_mail_allow as $regex) $valid |= preg_match($regex, $_POST['register_mail']);
					if(!$valid) {
						status_message("Diese Email-Adresse ist leider nicht erlaubt");
						gotop("index.php?q=login&action=Registrieren");
					}
					else {
						$headers = "Content-Type: text/plain;charset=UTF-8" . PHP_EOL .
							"Content-Transfer-Encoding: 8bit" . PHP_EOL .
							"From: =?utf-8?Q?=C3=9Cbungen?= <noreply@" . $_SERVER['SERVER_NAME'] . ">" . PHP_EOL .
							"Reply-To: ".$support_mail;
						$directory = dirname($_SERVER['REQUEST_URI']); if(substr($directory, -1) != '/') $directory .= '/';
						$time = time();
						$token = $time . "-" . substr(md5($time . "register" . $secure_token), 0, 5);
						$link = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['SERVER_NAME'] . $directory . 'index.php?q=login&token=' . $token;

						$message = "Hallo," . PHP_EOL . PHP_EOL .
							"Du erhälst Diese Mail, weil Du einen Registrierungslink für den Übungszettelaggregator" . PHP_EOL .
							"angefordert hast. Bitte klicke auf folgenden Link und verwende dann den „Registrieren“-Button," . PHP_EOL .
							"um Dir dann einen Benutzer anzulegen:" . PHP_EOL . PHP_EOL .
							" " . $link . PHP_EOL . PHP_EOL .
							"Dieser Link wird zwei Tage lang gültig sein. Solltest Du diesen Link nicht angefordert haben," . PHP_EOL .
							"kannst Du diese Mail einfach ignorieren." . PHP_EOL . PHP_EOL . "Gruß," . PHP_EOL . "Dein Übungszettelservice";

						mail($_POST['register_mail'], '=?utf-8?Q?=C3=9Cbungszetteldienst?= Registrierung', $message, $headers);

						status_message("Die Registrierungsmail wurde versandt und sollte bald bei Dir eintreffen.");
						gotop("index.php");
					}
				}

				if(!$register_ok && !$restrict_registration_mail_allow):
				?><div id="content">
				<h2>Von hier aus kannst Du Dich leider nicht registrieren..</h2>
				<p>Die Registrierung bei diesem Dienst ist nur eingeschränkt auf Rechner aus bestimmten Netzwerken möglich.
					Das ist notwendig, damit wir Dir auch Übungen von Veranstaltungen ausliefern zu dürfen, deren Homepages
					vor externen Zugriffen gesichert sind.</p>
				<p>Bitte benutze zum Registrieren einen PC aus einem der folgenden Netzwerke:</p>
				<ul>
					<?php foreach(array_keys($restrict_registration) as $key): ?>
					<li><?=$key?></li>
					<?php endforeach; ?>
				</ul>
				<p>Du kannst auch ein VPN oder einen SSH-Tunnel verwenden, um Dich von zu Hause aus anzumelden.
					Wie das geht, steht auf der Webseite Deines Instituts!</p>
				<p>Nachdem Du Dich registriert hast, kannst Du von überall aus auf Deinen Account zugreifen.</p>
				</div>
				<?php
				return;
				endif;

				if(!$register_ok && $restrict_registration_mail_allow):
				?><div id="content">
				<h2>Bitte bestätige Deine Institutsangehörigkeit</h2>
				<p>Du meldest Dich von außerhalb Deines Universitätsnetzwerkes an. Um Dir auch Übungen von Veranstaltungen
					ausliefern zu dürfen, deren Homepages vor externen Zugriffen gesichert sind, müssen wir überprüfen, ob
					Du Institutsangehöriger bist.</p>
				<p>Bitte gib Deine Email-Adresse an einer der folgenden Universitäten an:</p>
				<ul>
					<?php foreach(array_keys($restrict_registration) as $key): ?>
					<li><?=$key?></li>
					<?php endforeach; ?>
				</ul>
				<p>Wir senden Dir einen speziellen Link zu, über den Du Dir dann einen Benutzernamen anlegen kannst. Dein Account wird nicht
					mit der Email-Adresse verknüpft und die Adresse wird von uns auch nicht gespeichert.</p>

				<h3>Login-Link anfordern</h3>
				<form method="post">
					<p>
						<label><span>Email</span> <input type="text" name="register_mail" /></label>
					</p>
					<input type="submit" name="action" value="Anmeldelink zusenden" />
				</form>
				</div>
				<?php
				return;
				endif;
			}
		}

		// Info-Text zur Anmeldung
		if($_POST['action'] == "Weiter") {
			$_SESSION['confirm'] = true;
			$_POST = $_SESSION['saved_post'];
			unset($_SESSION['saved_post']);
		}
		if(!isset($_SESSION['confirm']) && $_POST['action'] == 'Registrieren') {
			// Willkommens-Text anzeigen
			$_SESSION['saved_post'] = $_POST;
			?><div id="content">
			<h2>Übungszettel</h2>
			<p>
				Mit der Registrierung ein paar Worte zum Programm, dem Datenschutz und den Daten, die hier
				gespeichert werden:
			</p>
			<p>
				Diese Seite erlaubt es Dir, Übungszettel automatisch von anderen Webseiten
				aggregieren zu lassen. Dass ein Zettel hier nicht auftaucht, ist keine Entschuldigung
				dafür ihn nicht bearbeitet zu haben. Mitdenken und aufpassen kann Dir keine Software
				abnehmen.
			</p>
			<p>
				Die Seite speichert von Dir Deinen Loginnamen, einen (gesalteten SHA1-)Hash Deines Passwortes sowie
				welche Kurse Du abboniert hast, welche Übungszettel Du bereits bearbeitet und welche
				Notizen Du hinterlegt hast. Du kannst Deinen Account jederzeit kündigen und all das
				wird umgehend gelöscht. Beim Login wird ein Autologin-Cookie für Dich gespeichert, der Dich
				beim nächsten Besuch automatisch wieder einloggt. Loggst Du Dich manuell aus, werden
				alle Deine Autologin-Cookies automatisch deaktiviert.
			<?php if($ldap_server): ?>
				Falls Du bei der Registrierung einen Benutzernamen samt
				Kennwort aus dem <a href="http://<?=htmlspecialchars($ldap_server)?>">LDAP</a>
				angibst, speichern wir Dein Passwort nicht, sondern speichern nur die Verknüpfung Deines Accounts
				mit dem LDAP-Verzeichnis. Diese Verknüpfung kannst Du auch noch nachträglich erstellen, falls Du
				Dir später einen Account im LDAP anlegst.
			<?php endif; ?>
			</p>
			<p>
				Du hast auf dieser Seite die Möglichkeit, neue Kurse zu speichern. Diese Kurse werden
				<em>nicht</em> automatisch gelöscht, wenn Du Dich abmeldest. Du kannst sie aber jederzeit
				manuell löschen. Alle von Dir angelegten Kurse sind erst einmal nur für Dich privat verfügbar,
				bis sie von einem Administrator für alle anderen freigeschaltet werden. Das geschieht prinzipiell
				immer und Du hast keine technische Möglichkeit, das zu verhindern. (Eine Kennzeichnung
				als <em>privat</em> im Titel wird eine Freischaltung faktisch verhindern, verhindert aber
				nicht, dass Administratoren den Kurs sehen können). Die Inhalte aggregierter URLs werden
				für die mobile Ansicht als PNG, sowie für die Funktion „PDFs kombinieren“ als PDF auf diesem
				Server zwischengespeichert.
			</p>
			<p>
				Die Software dieser Seite steht unter der <a href="http://www.gnu.org/licenses/gpl.html">GNU
				General Public License</a> frei zur Verfügung. Diese Seite nutzt Icons von
				<a href="http://www.famfamfam.com/lab/icons/silk/">Famfamfam</a>.
			</p>
			<p>
				Solltest Du mit alledem einverstanden sein, so klicke auf „Weiter“.
			</p>
			<form method="post">
				<input type="submit" name="action" value="Weiter">
				<?php if(isset($_POST['token'])): ?><input type="hidden" name="token" value="<?=htmlspecialchars($_POST['token'])?>"><?php endif; ?>
			</form>
			</div><?php
			return;
		}

		$name = strtolower(trim($_POST['name']));
		$pass = $_POST['pass'];
		$is_register = false;

		if($_POST['action'] == 'Registrieren') {
			$is_register = true;

			// Anmelden
			if(empty($name)) {
				$errName = 'Bitte gib einen Benutzernamen ein.';
			}
			else {
				// Checken, ob der Name schon vergeben ist
				$name = str_replace(array('<', "\n", '>'), '', $name);
				$stmt = $database->prepare('SELECT count(*) FROM users WHERE name = ?');
				$stmt->execute(array($name));
				if($stmt->fetchColumn() > 0) {
					$errName = 'Dieser Benutzername ist bereits vergeben.';		
				}
			}
			if(empty($pass)) {
				$errPass = 'Bitte gib ein Passwort ein.';
			}

			// LDAP Server gegenchecken
			$is_ldap_account = false;
			if($ldap_server && !$errPass && !$errName && ldap_check_if_name_exists($name)) {
				if(!ldap_authenticate($name, $pass)) {
					$errName = 'Dieser Benutzername ist bereits im LDAP vergeben';
					$errPass = 'Das Passwort stimmt nicht mit dem im LDAP überein';
				}
				else {
					$is_ldap_account = true;
				}
			}

			if($errPass == $errName && $errName == "") {
				// Benutzer erstellen
				$user = user();
				$user->name = $name;
				if($is_ldap_account) {
					$user->flags = USER_FLAG_IS_LDAP_ACCOUNT;
				}
				else {
					$salt = base_convert(rand(0, 36*36 - 1), 10, 36);
					$passSha = sha1($salt . $pass);
					$user->pass = $passSha;
					$user->salt = $salt;
				}
				user_save();
				status_message('Dein Benutzer wurde angelegt. Willkommen beim Übungszetteldienst!');

				// Kein Autologin beim ersten Anmelden

				$_SESSION['logged_in'] = true;
				$_SESSION['login'] = $user;
				gotop('index.php');
			}
		}

		if($_POST['action'] == 'Anmelden') {
			// Benutzer einloggen
			$user = user_load_authenticate($name, $pass);
			if(!$user) {
				$errPass = 'Benutzername oder Kennwort sind falsch.';
			}
			else {
				// Autologin-Cookie anlegen
				$autologin = sha1($user->salt . $secure_token . time() . $user->id . $_SERVER['REMOTE_ADDR']);
				$token = sha1($autologin . '-' . microtime() . '-' . rand());
				$database->query("INSERT INTO user_autologin (id, token, user_id) VALUES('" . $autologin . "',
					'" . $token . "', " . $user->id . ');');
				setcookie('autologin', $autologin . '-' . $token, time() + 15552000, 
					(dirname($_SERVER['REQUEST_URI']) == '/' ? '/' : dirname($_SERVER['REQUEST_URI']) . '/'),
					null, false, true);

				// User ist nun eingeloggt. Zur Übersicht.
				$_SESSION['logged_in'] = true;
				$_SESSION['login'] = $user;
				gotop('index.php');
			}
		}
	}

	endif; // Formzielbehandlung

	$support_mail_show = str_replace(array('@'), array(' auf '), $support_mail);

?>
<form action="index.php?q=login" method="post" id="login" accept-charset="utf-8">
	<div>
		<input type="hidden" name="destination" value="<?=htmlspecialchars($_REQUEST['destination'])?>">
		<?php if($is_register) echo("<span class='info'>Um Dich zu registrieren wähle bitte einen Benutzernamen und ein Passwort aus.</span>"); ?>
		<label><span>Benutzername</span><input type="text" name="name" maxlength="50" value="<?=htmlspecialchars($_POST['name'])?>"></label>
		<?php if($errName) echo('<span class="error">'.$errName.'</span>'); ?>
		<label><span>Kennwort</span><input type="password" name="pass" value=""></label>
		<?php if($errPass) echo('<span class="error">'.$errPass.'</span>'); ?>
		<?php if(!$is_register) echo('<input type="submit" name="action" value="Anmelden">'); ?>
		<?php if(isset($_REQUEST['token'])): ?><input type="hidden" name="token" value="<?=htmlspecialchars($_REQUEST['token'])?>"><?php endif; ?>
		<input type="submit" name="action" value="Registrieren">
	</div>
<?php if(!is_mobile()): ?>
	<p class="info">Feedback? Fragen? Kommentare? → Mail an <span class="tomail"><?=$support_mail_show?></span></p>
</form>
<footer>
	<p class="about"><img src="images/tux.png" />
		<a href="http://github.com/phillipberndt/Uebungszettel">Übungszettel</a> ist ein Angebot von <a href="http://www.spline.de">Spline</a>.<br>
		Geschrieben von <a href="http://www.pberndt.com">Phillip Berndt</a>.<br>
		<?php
		echo($database->query('SELECT COUNT(*) FROM users')->fetchColumn() . ' Benutzer haben zusammen ' .
			($database->query('SELECT SUM( (SELECT COUNT(*) FROM data WHERE data.feed_id = user_feeds.feed_id) ) FROM user_feeds')->fetchColumn() + 0) .
			' Zettel erhalten.');
		?>
	</p>
</footer>
<?php else: ?>
</form>
<?php endif; ?>
