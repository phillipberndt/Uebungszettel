<?php
	$database_conn = array('sqlite:'.getcwd().'/database.sqlite', null, null);
	#$database_conn = array('mysql:host=localhost;dbname=zettel', "root", "");
	$init_database = false;
	$cache_dir = getcwd().'/cache/';
	$admin_log_file = getcwd().'/cache/adminlog.log';
	$max_concurrent_toolkit_invokations = 5;
	$allowed_cache_file_size = 1024 * 1024 * 2;
	$support_mail = 'uebungen@lists.spline.inf.fu-berlin.de';
	$allowed_cache_types = array(
		'application/pdf',
		'image/png',
		'image/jpg',
		'image/jpeg',
		'image/gif',
		'application/postscript',
		'text/html',
		'text/plain',
		'application/xhtml+xml'
	);

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
		$database = new PDO($database_conn[0], $database_conn[1], $database_conn[2]);
		$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}
	catch(Exception $error) {
		?><h1>Datenbankfehler</h1>
		<p>Eine Datenbankverbindung konnte nicht hergestellt werden. Fehler:
			<em><?=htmlspecialchars($error->getMessage())?></em>
		</p>
		<p>Bitte kontaktiere den Support!</p>
		<?php
		die();
	}
	if($init_database) {
		// Tabellen anlegen
		$database->beginTransaction();
		if($database->getAttribute(PDO::ATTR_DRIVER_NAME) == 'sqlite') {
			$database->exec('CREATE TABLE users         (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(30),
				pass VARCHAR(40), salt VARCHAR(2) DEFAULT "", level INT DEFAULT 0, autologin VARCHAR(40), flags INTEGER DEFAULT 0, 
				settings LONGTEXT DEFAULT "a:0:{}");');
			$database->exec('CREATE INDEX uauto ON users (autologin);');
			$database->exec('CREATE TABLE feeds         (id INTEGER PRIMARY KEY AUTOINCREMENT, owner INT, desc VARCHAR(120), short VARCHAR(120),
				code LONGTEXT, public INT DEFAULT 0);');
			$database->exec('CREATE TABLE data          (id INTEGER PRIMARY KEY AUTOINCREMENT, feed_id INTEGER,
				data MEDIUMTEXT, timestamp INT(11))');
			$database->exec('CREATE INDEX fid ON data       (feed_id);');
			$database->exec('CREATE TABLE user_data         (data_id INTEGER, user_id INTEGER, comment MEDIUMTEXT DEFAULT "", invisible INTEGER DEFAULT 0, known INTEGER DEFAULT 0);');
			$database->exec('CREATE UNIQUE INDEX did_uid ON user_data (data_id, user_id);');
			$database->exec('CREATE TABLE user_feeds        (user_id INTEGER, feed_id INTEGER);');
			$database->exec('CREATE INDEX uid ON user_feeds (user_id);');
			$database->exec('CREATE TABLE suggestions       (id INTEGER PRIMARY KEY AUTOINCREMENT, text MEDIUMTEXT);');
			$database->exec('CREATE TABLE url_age_cache     (url MEDIUMTEXT, age INTEGER)');
		}
		else {
			$database->exec('CREATE TABLE users         (id INTEGER PRIMARY KEY AUTO_INCREMENT, name VARCHAR(30),
				pass VARCHAR(40), salt VARCHAR(2) DEFAULT "", level INT DEFAULT 0, autologin VARCHAR(40), flags INTEGER DEFAULT 0, settings LONGTEXT);');
			$database->exec('CREATE INDEX uauto ON users (autologin);');
			$database->exec('CREATE TABLE feeds         (id INTEGER PRIMARY KEY AUTO_INCREMENT, owner INTEGER, `desc` VARCHAR(120), short VARCHAR(120),
				code LONGTEXT, public INTEGER DEFAULT 0);');
			$database->exec('CREATE TABLE data          (id INTEGER PRIMARY KEY AUTO_INCREMENT, feed_id INTEGER,
				data MEDIUMTEXT)');
			$database->exec('CREATE INDEX fid ON data       (feed_id);');
			$database->exec('CREATE TABLE user_data         (data_id INTEGER, user_id INTEGER, comment MEDIUMTEXT DEFAULT "", invisible INTEGER DEFAULT 0, known INTEGER DEFAULT 0);');
			$database->exec('CREATE UNIQUE INDEX did_uid ON user_data (data_id, user_id);');
			$database->exec('CREATE TABLE user_feeds        (user_id INTEGER, feed_id INTEGER);');
			$database->exec('CREATE INDEX uid ON user_feeds (user_id);');
			$database->exec('CREATE TABLE suggestions       (id INTEGER PRIMARY KEY AUTO_INCREMENT, text MEDIUMTEXT);');
			$database->exec('CREATE TABLE url_age_cache     (url MEDIUMTEXT, age INTEGER)');
		}
		$database->exec('INSERT INTO users (id, name, pass, level) VALUES (1, "admin", "d033e22ae348aeb5660fc2140aec35850c4da997", 2);'); 
		$database->commit();
		$database = null;
		die("Datenbank angelegt. Der Standardbenutzer ist admin mit Passwort admin.");
	}
	// }}}
	// Hilfsfunktionen {{{
	function status_message($text) {
		// Statusmeldungen werden bei der Seitenausgabe in ein
		// Div geschrieben.
		$_SESSION['status_messages'][] = $text;
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
		return $_SESSION['logged_in'] == true;
	}
	function force_login() {
		if(!logged_in()) gotop("index.php?destination=" . urlencode($_SERVER['REQUEST_URI']));
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
				$user->autologin = null;
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
		foreach(unserialize($user->settings) as $key => $value) $user->$key = $value;
		return $user;
	}
	function user_save($user = null) {
		global $database;
		if(!$user) $user = user();
		$settings = array();
		foreach($user as $key => $val) {
			if(array_search($key, array('id', 'name', 'pass', 'salt', 'level', 'autologin', 'flags', 'settings')) === false) {
				$settings[$key] = $val;
			}
		}
		if(user()->id) {
			$stmt = $database->prepare('UPDATE users SET name = ?, pass = ?, salt = ?, level = ?, autologin = ?, flags = ?, 
				settings = ? WHERE id = ?');
			$stmt->execute(array($user->name, $user->pass, $user->salt, $user->level, $user->autologin, $user->flags, serialize($settings),
				$user->id));
		}
		else {
			$user = user();
			$stmt = $database->prepare('INSERT INTO users (name, pass, salt, level, autologin, flags, settings) VALUES (?, ?, ?, ?, ?, ?, ?)');
			$stmt->execute(array($user->name, $user->pass, $user->salt, $user->level, $user->autologin, $user->flags, serialize($settings)));
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
		if(!$GLOBALS['admin_log_file']) return;
		$log_file = fopen($GLOBALS['admin_log_file'], 'a');
		flock($log_file, LOCK_EX);
		fwrite($log_file, "[".date('d.m.Y H:i:s')." ".user()->name."] ".$text."\n");
		fclose($log_file);
	}/*}}}*/
	function get_mime_type($fileOrInline, $inline = false) {/*{{{*/
		$type = false;
		if(!$inline && !file_exists($fileOrInline)) return false;
		if(class_exists('finfo')) {
			$finfo = new finfo(FILEINFO_MIME, "/usr/share/misc/magic");
			$type = $inline ? $finfo->buffer($fileOrInline) : $finfo->file($fileOrInline);
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
	function check_if_url_changed($url) {/*{{{*/
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
		$current_age = curl_getinfo($curl, CURLINFO_FILETIME);
		if($current_age == -1 && preg_match('#^Last-Modified: (.+)#mi', $headers, &$matches)) {
			$current_age = strtotime($matches[1]);
		}

		if($current_age == -1) return true;
		if($db_age && $db_age == $current_age) return false;
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
		if(!$content) {
			throw new Exception("Die URL konnte nicht geladen werden");
		}
		curl_close($curl);

		if(strlen($content) > $GLOBALS['allowed_cache_file_size']) {
			throw new Exception("Datei zu groß. Dateien über 2Mb Größe können aus Sicherheitsgründen nicht heruntergeladen werden.");
		}
		$type = get_mime_type($content, true);
		if(preg_match('/^[^;]+/', $type, &$match)) $type = $match[0];
		if(array_search($type, $GLOBALS['allowed_cache_types']) === false) {
			throw new Exception("Dateityp unbekannt. Aus Sicherheitsgründen können nur bestimmte Dateitypen heruntergeladen werden.");
		}

		if(!$fix_encoding) return $content;
		return mb_convert_encoding($content, 'UTF-8',
				mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
	}/*}}}*/
	function is_mobile() {/*{{{*/
		if(preg_match('/SymbianOS|J2ME|Mobile|iPhone|iPad|iPod|android|opera mini|blackberry|windows ce/i', $_SERVER['HTTP_USER_AGENT'])) return true;
		return false;
	}/*}}}*/
	function format_data($data) {/*{{{*/
		if(preg_match('#^(https?://[^ ]+)( .+)?$#is', $data, &$match)) {
			$url = $match[1];
			// Mobile Device PDF: Das können wir als JPG ausliefern!
			if(preg_match('/\.(?:pdf|ps)$/i', $url) && is_mobile()) {
				$url = 'image.php?d='.htmlspecialchars(urlencode($url));
			}

			return '<a class="exercise" href="'.htmlspecialchars($url).'">'.htmlspecialchars($match[2] ? $match[2] : basename($match[1])).'</a>';
		}
		else {
			return htmlspecialchars($data);
		}
	}/*}}}*/
	function cache_file($url, $file_name = false, $return_id = false, $safeForDeletion = true) {/*{{{*/
		$cache_id = sha1($url);
		if(!$safeForDeletion) $cache_id = 'st_'.$cache_id;
		$cache_file = $GLOBALS['cache_dir'] . '/' . $cache_id;
		$directory = dirname($_SERVER['REQUEST_URI']); if(substr($directory, -1) != '/') $directory .= '/';
		$cache_url = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['SERVER_NAME'] . $directory . 
			'cache.php?cache_id=' . $cache_id . '&filename=' . ($file_name === false ? $cache_id : $file_name);

		$cache_exists = file_exists($cache_file);
		$content = load_url($url, false, $cache_exists ? filemtime($cache_file) : false);
		if($content && $content !== true) {
			// Nur den Cache neu schreiben, wenn sich tatsächlich etwas verändert hat
			// Dadurch verhindern wir, dass Zettel immer wieder als „Neu“ zählen, weil
			// ein Webserver schlampig konfiguriert ist.
			if(!file_exists($cache_file) || sha1_file($cache_file) != sha1($content)) {
				file_put_contents($cache_file, $content);
			}
		}

		if($return_id) return $cache_id;
		return $cache_url;
	}/*}}}*/
	function cache_contents($url) {/*{{{*/
		$cache_id = cache_file($url, false, true, true);
		return file_get_contents($GLOBALS['cache_dir'] . $cache_id);
	}/*}}}*/
	function in_shell_execution($begin) {/*{{{*/
		// Benutzt, falls möglich, die Semaphore-Implementation von
		// PHP, um die maximale Anzahl gleichzeitiger Shell-Invocations
		// zu regulieren
		if(!function_exists('sem_get')) return;

		global $_active_semaphore;
		if(!$_active_semaphore) {
			$id = ftok(__FILE__, 'u');
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
	// }}}
