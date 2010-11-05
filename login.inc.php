<?php
	if(logged_in()) gotop("index.php");

	// Bei direktem Hit auf die Domain Weiterleiten auf diese Seite, weil nur hier
	// das Autologin-Cookie ankommt
	if(substr(basename($_SERVER['REQUEST_URI']), 0, 17) != 'index.php?q=login' && !$_POST) {
		gotop('index.php?q=login');
	}

	// Formzielbehandlung nur bei korrekter URL
	if($_GET['q'] == "login"):

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
				(dirname($_SERVER['REQUEST_URI']) == '/' ? '/' : dirname($_SERVER['REQUEST_URI']) . '/') . 'index.php?q=login',
				null, false, true);
			// Benutzer einloggen
			$user = user_load('id', $result->user_id);
			if(!$user) {
				gotop("index.php");
			}
			else {
				$_SESSION['logged_in'] = true;
				$_SESSION['login'] = $user;
				gotop('index.php');
			}
		}
	}

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

	$errName = $errPass = '';
	if(isset($_POST['action'])) {
		// Info-Text zur Anmeldung
		if($_POST['action'] == "Weiter") {
			$_SESSION['confirm'] = true;
			$_POST = $_SESSION['saved_post'];
			unset($_SESSION['saved_post']);
		}
		if(!isset($_SESSION['confirm']) && $_POST['action'] == 'Registrieren') {
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
				wird umgehend gelöscht. Beim Login wird ein Autologin-Cookie für Dich gespeichert, dass Dich
				beim nächsten Besuch automatisch wieder einloggt. Loggst Du Dich manuell aus, werden
				alle Deine Autologin-Cookies automatisch deaktiviert.
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

			if($errPass == $errName && $errName == "") {
				// Benutzer erstellen
				$salt = base_convert(rand(0, 36*36 - 1), 10, 36);
				$passSha = sha1($salt . $pass);
				$user = user();
				$user->name = $name;
				$user->pass = $passSha;
				$user->salt = $salt;
				user_save();
				status_message('Dein Benutzer wurde angelegt. Du kannst Dich jetzt mit Deinen Benutzerdaten anmelden.');
				gotop('index.php');
			}
		}

		if($_POST['action'] == 'Anmelden') {
			// Benutzer einloggen
			$user = user_load("name", $name);
			if($user) {
				// Passwortkontrolle
				if(sha1($user->salt . $pass) != $user->pass) {
					$errPass = 'Benutzername oder Kennwort sind falsch.';
				}
			}
			else {
				$errPass = 'Benutzername oder Kennwort sind falsch.';
			}
			if($errPass == '') {
				// Autologin-Cookie anlegen
				$autologin = sha1($user->salt . $secure_token . time() . $user->id . $_SERVER['REMOTE_ADDR']);
				$token = sha1($autologin . '-' . microtime() . '-' . rand());
				$database->query('INSERT INTO user_autologin (id, token, user_id) VALUES("' . $autologin . '",
					"' . $token . '", ' . $user->id . ');');
				setcookie('autologin', $autologin . '-' . $token, time() + 15552000, 
					(dirname($_SERVER['REQUEST_URI']) == '/' ? '/' : dirname($_SERVER['REQUEST_URI']) . '/') . 'index.php?q=login',
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
		<label><span>Benutzername</span><input type="text" name="name" value="<?=htmlspecialchars($_POST['name'])?>"></label>
		<?php if($errName) echo('<span class="error">'.$errName.'</span>'); ?>
		<label><span>Kennwort</span><input type="password" name="pass" value=""></label>
		<?php if($errPass) echo('<span class="error">'.$errPass.'</span>'); ?>
		<?php if(!$is_register) echo('<input type="submit" name="action" value="Anmelden">'); ?>
		<input type="submit" name="action" value="Registrieren">
	</div>
<?php if(!is_mobile()): ?>
	<p class="info">Feedback? Fragen? Kommentare? → Mail an <span class="tomail"><?=$support_mail_show?></span></p>
</form>
<footer>
	<p class="about"><img src="images/tux.png" />
		<a href="http://github.com/phillipberndt/Uebungszettel">Übungszettel</a> ist Angebot von <a href="http://www.spline.de">Spline</a>.<br>
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
