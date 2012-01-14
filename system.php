<?php
	// Konfiguration laden
	if(!file_exists('config.php')) {
		die('<!DOCTYPE HTML><head><meta charset="utf-8"><title>Fehler</title></head>
			<body><h1>Konfiguration fehlt</h1><p>Bitte lege eine Konfigurationsdatei <em>config.php</em> an. Hierzu
			kannst Du die Vorlage aus <em>config.php.sample</em> verwenden.</p></body>');
	}
	require('config.php');

	// Flags für User, gespeichert in user()->flags
	define('USER_FLAG_WANTSMAIL', 1);

	// Stripslashes {{{
	function stripslashes_deep(&$value)
	{
		$value = is_array($value) ?
			array_map('stripslashes_deep', $value) :
			stripslashes($value);

		return $value;
	}
	if( (function_exists("get_magic_quotes_gpc") && get_magic_quotes_gpc())
	 || (ini_get('magic_quotes_sybase') && (strtolower(ini_get('magic_quotes_sybase')) != "off")))
	{
		stripslashes_deep($_GET);
		stripslashes_deep($_POST);
		stripslashes_deep($_COOKIE);
	}
	// }}}
	// Datenbankverbindung herstellen {{{
	try {
		$driver_options = array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8');
		$database = new PDO($database_conn[0], $database_conn[1], $database_conn[2], $driver_options);
		$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}
	catch(Exception $error) {
		?><!DOCTYPE HTML><head><title>Fehler</title><meta charset="utf-8"></head><body><h1>Datenbankfehler</h1>
		<p>Eine Datenbankverbindung konnte nicht hergestellt werden. Fehler:
			<em><?=htmlspecialchars($error->getMessage())?></em>
		</p>
		<p>Bitte kontaktiere den Support!</p>
		</body>
		<?php
		die();
	}
	if($init_database) {
		// Tabellen anlegen
		$database->beginTransaction();
		if($database->getAttribute(PDO::ATTR_DRIVER_NAME) == 'sqlite') {
			$database->exec('CREATE TABLE users         (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(50),
				pass VARCHAR(40), salt VARCHAR(2) DEFAULT "", level INT DEFAULT 0, flags INTEGER DEFAULT 0, 
				settings LONGTEXT DEFAULT "a:0:{}");'); 
			$database->exec('CREATE TABLE user_autologin (id VARCHAR(40), token VARCHAR(40), user_id INTEGER)');
			$database->exec('CREATE INDEX uauto ON user_autologin (id)');
			$database->exec('CREATE INDEX user ON user_autologin (user_id)');
			$database->exec('CREATE TABLE feeds         (id INTEGER PRIMARY KEY AUTOINCREMENT, owner INT, desc VARCHAR(120), short VARCHAR(120),
				code LONGTEXT, public INT DEFAULT 0, update_timestamp INT);');
			$database->query('CREATE TABLE feed_links (feed_id INTEGER, title VARCHAR(120), url TEXT, PRIMARY KEY (feed_id, title));');
			$database->exec('CREATE TABLE data          (id INTEGER PRIMARY KEY AUTOINCREMENT, feed_id INTEGER,
				data MEDIUMTEXT, timestamp INT(11))');
			$database->exec('CREATE INDEX fid ON data       (feed_id);');
			$database->exec('CREATE TABLE user_data         (data_id INTEGER, user_id INTEGER, comment MEDIUMTEXT DEFAULT "", invisible INTEGER DEFAULT 0, known INTEGER DEFAULT 0);');
			$database->exec('CREATE UNIQUE INDEX did_uid ON user_data (data_id, user_id);');
			$database->exec('CREATE TABLE user_feeds        (user_id INTEGER, feed_id INTEGER);');
			$database->exec('CREATE INDEX uid ON user_feeds (user_id);');
			$database->exec('CREATE UNIQUE INDEX uid_feed ON user_feeds (user_id, feed_id);');
			$database->exec('CREATE TABLE suggestions       (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, text MEDIUMTEXT);');
			$database->exec('CREATE TABLE url_age_cache     (url MEDIUMTEXT, age INTEGER)');
			$database->exec('CREATE TABLE cache             (id VARCHAR(40) PRIMARY KEY, created_timestamp INT, max_age INT, filename VARCHAR(255));');
		}
		else {
			$database->exec('CREATE TABLE users         (id INTEGER PRIMARY KEY AUTO_INCREMENT, name VARCHAR(50),
				pass VARCHAR(40), salt VARCHAR(2) DEFAULT "", level INT DEFAULT 0, flags INTEGER DEFAULT 0, settings LONGTEXT) DEFAULT CHARSET=utf8;');
			$database->exec('CREATE TABLE user_autologin (id VARCHAR(40), token VARCHAR(40), user_id INTEGER) DEFAULT CHARSET=utf8');
			$database->exec('CREATE INDEX uauto ON user_autologin (id)');
			$database->exec('CREATE INDEX user ON user_autologin (user_id)');
			$database->exec('CREATE TABLE feeds         (id INTEGER PRIMARY KEY AUTO_INCREMENT, owner INTEGER, `desc` VARCHAR(120), short VARCHAR(120),
				code LONGTEXT, public INTEGER DEFAULT 0, update_timestamp INT, course_url VARCHAR(255)
				DEFAULT CHARSET=utf8;');
			$database->query('CREATE TABLE feed_links (feed_id INTEGER, title VARCHAR(120), url TEXT, PRIMARY KEY (feed_id, title))
				DEFAULT CHARSET=utf8;');
			$database->exec('CREATE TABLE data          (id INTEGER PRIMARY KEY AUTO_INCREMENT, feed_id INTEGER,
				data MEDIUMTEXT, timestamp INT(11)) DEFAULT CHARSET=utf8');
			$database->exec('CREATE INDEX fid ON data       (feed_id);');
			$database->exec('CREATE TABLE user_data         (data_id INTEGER, user_id INTEGER, comment MEDIUMTEXT DEFAULT "", invisible INTEGER DEFAULT 0, known INTEGER DEFAULT 0) DEFAULT CHARSET=utf8;');
			$database->exec('CREATE UNIQUE INDEX did_uid ON user_data (data_id, user_id);');
			$database->exec('CREATE TABLE user_feeds        (user_id INTEGER, feed_id INTEGER) DEFAULT CHARSET=utf8;');
			$database->exec('CREATE INDEX uid ON user_feeds (user_id);');
			$database->exec('CREATE TABLE suggestions       (id INTEGER PRIMARY KEY AUTO_INCREMENT, user_id INTEGER, text MEDIUMTEXT) DEFAULT CHARSET=utf8;');
			$database->exec('CREATE TABLE url_age_cache     (url MEDIUMTEXT, age INTEGER) DEFAULT CHARSET=utf8');
			$database->exec('CREATE TABLE cache             (id VARCHAR(40) PRIMARY KEY, created_timestamp INT, max_age INT, filename VARCHAR(255));');
		}
		$salt = base_convert(rand(0, 36*36 - 1), 10, 36);
		$database->exec('INSERT INTO users (id, name, pass, level, salt) VALUES (1, "admin", "d033e22ae348aeb5660fc2140aec35850c4da997", 2, "' . $salt . '");'); 
		$database->commit();
		$database = null;
		die("Datenbank angelegt. Der Standardbenutzer ist admin mit Passwort admin.");
	}
	// }}}
	// Hilfsfunktionen {{{
	function status_message($text, $user = null) {
		// Statusmeldungen werden bei der Seitenausgabe in ein
		// Div geschrieben.
		if($user === null) {
			$_SESSION['status_messages'][] = $text;
		}
		else {
			$store_user = false;
			if(!is_object($user)) {
				$store_user = true;
				$user = user_load('id', $user);
				if(!$user) return;
			}
			if(!isset($user->saved_status_messages)) {
				$user->saved_status_messages = array();
			}
			$user->saved_status_messages[] = $text;
			if($store_user) {
				user_save($user);
			}
		}
	}
	function gotop($url) {
		if(isset($_REQUEST['destination']) && $_REQUEST['destination'] && !preg_match('#^[a-z]+:#i', $_REQUEST['destination'])) {
			$url = $_REQUEST['destination'];
		}
		header('Location: '.$url);
		die();
	}
	// Benutzerverwaltung {{{
	function user_levels() {
		return array(
			0 => 'Benutzer',
			1 => 'Moderator',
			2 => 'Administrator',
		);
	}
	session_start();
	function logged_in() {
		return $_SESSION['logged_in'] === true;
	}
	function force_login() {
		if(!logged_in()) gotop("index.php?q=login&destination=" . urlencode($_SERVER['REQUEST_URI']));
	}
	function force_level($level) {
		force_login();
		if(user()->level < $level) {
			header('HTTP/1.1 403 Access denied');
			echo('<div id="error">Deine Berechtigungen erlauben Dir nicht, diese Seite zu sehen</div>');
			return false;
		}
		return true;
	}
	function &user() {
		static $user;
		if(!$user) {
			if(logged_in()) {
				$user = &$_SESSION['login'];
			}
			else {
				$user = new stdclass();
				$user->id = 0;
				$user->settings = array();
				$user->flags = 0;
				$user->name = "Gast";
				$user->level = 0;
			}
		}
		return $user;
	}
	function user_load($key, $value) {
		global $database;
		$query = $database->prepare('SELECT * FROM users WHERE '.$key.' = ?');
		$query->execute(array($value));
		$user = $query->fetch(PDO::FETCH_OBJ);
		if(!$user) return false;
		$settings_array = unserialize($user->settings);
		if(is_array($settings_array)) {
			foreach($settings_array as $key => $value) {
				if(!isset($user->$key)) {
					$user->$key = $value;
				}
			}
		}
		return $user;
	}
	function user_save($user = null) {
		global $database;
		if(!$user) $user = user();
		$settings = array();
		foreach($user as $key => $val) {
			if(array_search($key, array('id', 'name', 'pass', 'salt', 'level', 'flags', 'settings')) === false) {
				$settings[$key] = $val;
			}
		}
		if(user()->id) {
			$stmt = $database->prepare('UPDATE users SET name = ?, pass = ?, salt = ?, level = ?, flags = ?, 
				settings = ? WHERE id = ?');
			$stmt->execute(array($user->name, $user->pass, $user->salt, $user->level, $user->flags, serialize($settings),
				$user->id));
		}
		else {
			$user = user();
			$stmt = $database->prepare('INSERT INTO users (name, pass, salt, level, flags, settings) VALUES (?, ?, ?, ?, ?, ?)');
			$stmt->execute(array($user->name, $user->pass, $user->salt, $user->level, $user->flags, serialize($settings)));
			$user->id = $database->lastInsertId();
		}
	}
	// }}}
	class tmpfile_manager {/*{{{*/
		private $file;
		public function __construct() {
			$this->file = tempnam("/tmp", "tmp");
		}
		public function __destruct() {
			unlink($this->file);
		}
		public function __toString() {
			return $this->file;
		}
	}/*}}}*/
	function admin_log($text) {/*{{{*/
		// Log von Administrator-Aufgaben
		// Vorallem für PHP-Ausführung wichtig
		$log_line = "[".date('d.m.Y H:i:s')." ".user()->name."] ".$text;
		activity_email("Administrator-Aktivität:" . PHP_EOL . $text);

		if(!$GLOBALS['admin_log_file']) return;
		$log_file = fopen($GLOBALS['admin_log_file'], 'a');
		flock($log_file, LOCK_EX);
		fwrite($log_file, $log_line."\n");
		fclose($log_file);
	}/*}}}*/
	function activity_email($text) {/*{{{*/
		if(!$GLOBALS['activity_mail']) return;
		$directory = dirname($_SERVER['REQUEST_URI']); if(substr($directory, -1) != '/') $directory .= '/';
		mail($GLOBALS['activity_mail'], '=?utf-8?Q?=C3=9Cbungszettel?= Moderation notwendig',
			"Moderator-Information für http://" . $_SERVER['SERVER_NAME'] . $directory . PHP_EOL . PHP_EOL .
			$text .
			PHP_EOL . PHP_EOL . "Gruß," . PHP_EOL . "Dein Übungszettelservice",
			"From: =?utf-8?Q?=C3=9Cbungen?= <noreply@" . $_SERVER['SERVER_NAME'] . ">" . PHP_EOL . 
			"Reply-To: " . $GLOBALS['support_mail'] . PHP_EOL .
			"Content-Type: text/plain; charset=utf-8");
	}/*}}}*/
	function remove_authentication_from_urls($data) { /*{{{*/
		// Funktion zum entfernen von Authentifizierungsinformationen aus URLs
		global $remove_authentication_for_viewing;
		if(isset($remove_authentication_for_viewing) && $remove_authentication_for_viewing) {
			return preg_replace('#(http|ftp)://([^:/]+?):[^/]*?[^\\\\]@(\S+)#i', '$1://$2@$3', $data);
		}
		else {
			return $data;
		}
	}/*}}}*/
	function get_mime_type($fileOrInline, $inline = false) {/*{{{*/
		$type = false;
		if(!$inline && !file_exists($fileOrInline)) return false;
		if(class_exists('finfo')) {
			$finfo = new finfo(FILEINFO_MIME, "/usr/share/misc/magic");
			$type = $inline ? $finfo->buffer($fileOrInline) : $finfo->file($fileOrInline);

			// Bugfix: PHP erkennt PDF-Dateien leider oft falsch
			if(substr($type, 0, 24) == 'application/octet-stream') {
				$file_header = $inline ? substr($fileOrInline, 0, 100) : file_get_contents($fileOrInline, false, NULL, 0, 100);
				if(substr($file_header, 0, 4) == "%PDF") $type = "application/pdf";
			}
		}
		else {
			in_shell_execution(true);
			if($inline && function_exists('proc_open')) {
				$fo = proc_open("file -b --mime-type -", array(0 => array("pipe", "r"), 1 => array("pipe", "w")), $pipes);
				fwrite($pipes[0], $fileOrInline);
				fclose($pipes[0]);
				$type = trim(stream_get_contents($pipes[1]));
				fclose($pipes[1]);
				proc_close($fo);
			}
			elseif(!$inline && function_exists('popen')) {
				$type = trim(stream_get_contents(popen("file -b --mime-type ".escapeshellarg($fileOrInline), "r")));
			}
			in_shell_execution(false);
		}
		return $type;
	}/*}}}*/
	function check_if_url_changed($url, $store_update = true) {/*{{{*/
		// Prüft, ob sich eine URL seit dem letzten Aufruf verändert
		// hat. Gibt true zurück, wenn ja oder wenn die URL noch nie
		// aufgerufen wurde.
		global $database;
		$query = $database->prepare('SELECT age FROM url_age_cache WHERE url = ?');
		$query->execute(array($url));
		$db_age = $query->fetchColumn();

		$cookie_file = &$GLOBALS['_system_cookie_file'];
		if(!$cookie_file) $cookie_file = new tmpfile_manager();
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_COOKIEJAR, $cookie_file);
		curl_setopt($curl, CURLOPT_COOKIEFILE, $cookie_file);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($curl, CURLOPT_TIMEOUT, 20);
		curl_setopt($curl, CURLOPT_HEADER, 1);
		curl_setopt($curl, CURLOPT_NOBODY, 1);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$headers = curl_exec($curl);
		if(curl_getinfo($curl, CURLINFO_HTTP_CODE) >= 400 || curl_errno($curl) != 0) {
			throw new Exception("Die URL konnte nicht geladen werden: " . curl_error($curl));
		}
		$current_age = curl_getinfo($curl, CURLINFO_FILETIME);
		if($current_age == -1 && preg_match('#^Last-Modified: (.+)#mi', $headers, $matches)) {
			$current_age = strtotime($matches[1]);
		}

		if($current_age == -1) return true;
		if($db_age && $db_age == $current_age) return false;
		if(!$store_update) return true;
		if($db_age) {
			$query = $database->prepare('UPDATE url_age_cache SET age = ? WHERE url = ?');
		}
		else {
			$query = $database->prepare('INSERT INTO url_age_cache (age, url) VALUES (?, ?)');
		}
		$query->execute(array($current_age, $url));
		return true;
	}/*}}}*/
	function load_url($url, $fix_encoding = true, $if_modified_since = false) {/*{{{*/
		$cookie_file = &$GLOBALS['_system_cookie_file'];
		if(!$cookie_file) $cookie_file = new tmpfile_manager();
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_COOKIEJAR, $cookie_file);
		curl_setopt($curl, CURLOPT_COOKIEFILE, $cookie_file);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($curl, CURLOPT_TIMEOUT, 20);
		if($if_modified_since !== false) {
			curl_setopt($curl, CURLOPT_TIMECONDITION, CURL_TIMECOND_IFMODSINCE);
			curl_setopt($curl, CURLOPT_TIMEVALUE, $if_modified_since);
		}
		$content = curl_exec($curl);
		if($if_modified_since !== false) {
			if(curl_getinfo($curl, CURLINFO_HTTP_CODE) == 304) return true; // Not modified
		}
		if(curl_getinfo($curl, CURLINFO_HTTP_CODE) >= 400 || curl_errno($curl) != 0 || !$content) {
			throw new Exception("Die URL konnte nicht geladen werden: " . curl_error($curl));
		}
		curl_close($curl);

		$maximal_cache_size = parse_human_readable_file_size($GLOBALS['allowed_cache_file_size']);
		if(strlen($content) > $maximal_cache_size) {
			throw new Exception("Datei zu groß. Dateien über " . round($maximal_cache_size / 1024 / 1024) .
				"Mb Größe können aus Sicherheitsgründen nicht heruntergeladen werden.");
		}
		$type = get_mime_type($content, true);
		if(preg_match('/^[^;]+/', $type, $match)) $type = $match[0];
		if(array_search($type, $GLOBALS['allowed_cache_types']) === false) {
			throw new Exception("Dateityp " . $type . " unbekannt. Aus Sicherheitsgründen können nur bestimmte Dateitypen heruntergeladen werden.");
		}

		if(!$fix_encoding) return $content;
		return mb_convert_encoding($content, 'UTF-8',
				mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
	}/*}}}*/
	function is_mobile() {/*{{{*/
		if(preg_match('/SymbianOS|J2ME|Mobile|iPhone|iPad|iPod|android|opera mini|blackberry|windows ce/i', $_SERVER['HTTP_USER_AGENT'])) return true;
		return false;
	}/*}}}*/
	function split_data($data) {/*{{{*/
		// Eine Übung aufteilen in URL und Text
		if(preg_match('#^(https?://[^ ]+)( .+)?$#is', $data, $match)) {
			return array($match[1], trim($match[2]));
		}
		else {
			return array('', $data);
		}
	}/*}}}*/
	function format_data($data, $id = null) {/*{{{*/
		list($url, $text) = split_data($data);
		if($url) {
			// Mobile Device PDF: Das können wir als JPG ausliefern!
			if($id && preg_match('/\.(?:pdf|ps)$/i', $url) && is_mobile()) {
				$url = 'image.php?data_id='.htmlspecialchars($id);
			}

			$url = remove_authentication_from_urls($url);
			return '<a class="exercise" href="'.htmlspecialchars($url).'">'.htmlspecialchars($text ? $text : basename($url)).'</a>';
		}
		else {
			return htmlspecialchars($text);
		}
	}/*}}}*/
	function cache_file($url, $file_name = false, $return_id = false, $cache_timeout = 15552000) {/*{{{*/
		global $database;
		$cache_id = sha1($url);
		$cache_file = $GLOBALS['cache_dir'] . '/' . $cache_id;
		$directory = dirname($_SERVER['REQUEST_URI']); if(substr($directory, -1) != '/') $directory .= '/';
		$cache_url = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['SERVER_NAME'] . $directory . 
			'cache.php?cache_id=' . $cache_id;

		$cache_exists = file_exists($cache_file);
		$content = load_url($url, false, $cache_exists ? filemtime($cache_file) : false);
		if($content && $content !== true) {
			// Nur den Cache neu schreiben, wenn sich tatsächlich etwas verändert hat
			// Dadurch verhindern wir, dass Zettel immer wieder als „Neu“ zählen, weil
			// ein Webserver schlampig konfiguriert ist.
			if(!file_exists($cache_file) || sha1_file($cache_file) != sha1($content)) {
				file_put_contents($cache_file, $content);
			}

			// In der Datenbank ein Update ausführen
			// Das auch, wenn eigentlich nichts geändert wurde - weil wir nämlich durch
			// den Aufruf wissen, dass die Daten noch gebraucht werden.
			$query = $database->prepare('REPLACE INTO cache (id, created_timestamp, max_age, filename) VALUES (?, ?, ?, ?)');
			$query->execute(array($cache_id, time(), $cache_timeout, $file_name ? $file_name : basename($url)));
		}

		if($return_id) return $cache_id;
		return $cache_url;
	}/*}}}*/
	function cache_contents($url) {/*{{{*/
		$cache_id = cache_file($url, false, true);
		return file_get_contents($GLOBALS['cache_dir'] . $cache_id);
	}/*}}}*/
	function cache_zip_file_contents($url, $identifier = null) { /*{{{*/
		global $database;
		if($identifier === null) $identifier = $url;
		$cache_id = cache_file($url, false, true, 3600 * 24 * 7);
		$retval = array();
		$directory = dirname($_SERVER['REQUEST_URI']); if(substr($directory, -1) != '/') $directory .= '/';
		$zip = new ZipArchive;
		if(!$zip->open($GLOBALS['cache_dir'] . '/' . $cache_id)) {
			throw new Execption("Failed to open ZIP archive from $url");
		}
		for($i=0; $i<$zip->numFiles; $i++) {
			$file_name = basename($zip->getNameIndex($i));
			if(isset($retval[$file_name])) {
				$n = 0;
				while(isset($retval[$n . '~' . $file_name])) $n++;
				$file_name = $n . '~' . $file_name;
			}
			$content = $zip->getFromIndex($i);
			$cache_id = sha1($identifier . '#' . $file_name);
			$cache_file = $GLOBALS['cache_dir'] . '/' . $cache_id;

			$cache_url = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['SERVER_NAME'] . $directory .
				'cache.php?cache_id=' . $cache_id . '&filename=' . $file_name;

			$cache_exists = file_exists($cache_file);
			if($content) {
				// Nur den Cache neu schreiben, wenn sich tatsächlich etwas verändert hat
				// Dadurch verhindern wir, dass Zettel immer wieder als „Neu“ zählen, weil
				// ein Webserver schlampig konfiguriert ist.
				if(!file_exists($cache_file) || sha1_file($cache_file) != sha1($content)) {
					file_put_contents($cache_file, $content);
				}

				// In der Datenbank ein Update ausführen
				// Das auch, wenn eigentlich nichts geändert wurde - weil wir nämlich durch
				// den Aufruf wissen, dass die Daten noch gebraucht werden.
				// Die hierrüber geladenen Daten ein halbes Jahr aufbewahren.
				$query = $database->prepare('REPLACE INTO cache (id, created_timestamp, max_age, filename) VALUES (?, ?, ?, ?)');
				$query->execute(array($cache_id, time(), 3600 * 24 * 30 * 6, $file_name));
			}

			$retval[$file_name] = $cache_url . ' ' . $file_name;
		}
		return $retval;
	} /*}}}*/
	function in_shell_execution($begin) {/*{{{*/
		// Benutzt, falls möglich, die Semaphore-Implementation von
		// PHP, um die maximale Anzahl gleichzeitiger Shell-Invocations
		// zu regulieren
		if(!function_exists('sem_get')) return;

		// Für den Cron-Job soll keine Beschränkung gelten, denn der muss ja
		// ausgeführt werden
		if(basename($_SERVER['PHP_SELF']) == "cron.php") return;

		// Ansonsten entsprechend der Konfiguration  aussperren
		global $_active_semaphore;
		if(!$_active_semaphore) {
			$id = ftok(__FILE__, 'v');
			$_active_semaphore = sem_get($id, $GLOBALS['max_concurrent_toolkit_invokations'], 0666, 1);
		}

		if($begin) {
			ignore_user_abort(true);
			set_time_limit(30);
			sem_acquire($_active_semaphore);
		}
		else {
			ignore_user_abort(false);
			sem_release($_active_semaphore);
		}
	}/*}}}*/
	function parse_human_readable_file_size($size) {/*{{{*/
		if(!preg_match('/^\s*([0-9]*\.[0-9]+|[0-9]+\.[0-9]*|[0-9]+)(M(?:ega)?|K(?:ilo)?|G(?:iga)?)(B(?:ytes)?)?\s*$/i', $size, $match)) {
			return intval($size);
		}
		$multiplier = 1;
		switch(strtoupper($match[2][0])) {
			case 'G': $multiplier *= 1024;
			case 'M': $multiplier *= 1024;
			case 'K': $multiplier *= 1024;
		}
		return $match[1] * $multiplier;
	}/*}}}*/
	// }}}

	// Angepasste Helfer-Funktionen für eine bestimmte Installation
	// Ist online sichtbar, vorallem praktisch, um Hilfsfunktionen für
	// Zettel zu definieren.
	if(file_exists('local.php')) {
		include('local.php');
	}
