<div id="content">
	<h2>Wie funktioniert das Eintragen von Kursen?</h2>
	<p>Diese Anleitung gibt es in drei Versionen:</p>
	<ul>
		<li><a href="#expert">Für Experten</a> - ohne Schnickschnack einfach die technischen Details</li>
		<li><a href="#beginner">Für Anfänger</a> - als „Schritt für Schritt“-Anleitung anhand eines Beispiels</li>
		<li><a href="#php">Für das Team</a> - eine Erklärung, wie man PHP-Code in Definitionen verwendet</li>
	</ul>

	<h3 id="expert">Für Experten</h3>
	<p>Beim Eintragen von Kursen musst Du 5 Felder ausfüllen:</p>
	<dl class="exp">
		<dt>Beschreibung</dt>
		<dd>Gib hier eine aussagekräftige Beschreibung des Kurses ein, am Besten inklusive Semester und Dozent.</dd>
		<dt>Kürzel</dt>
		<dd>Dieses Kürzel wird in der Übersicht neben Übungen angezeigt, also sollte es dem Namen nach kurz sein (z.B. ALP, LinA, o.Ä.)</dd>
		<dt>URL</dt>
		<dd>Die URL zu der Seite, auf der die Übungsaufgaben verlinkt sind.</dd>
		<dt>Suchphrase</dt>
		<dd>Ein <a href="http://de3.php.net/manual/de/book.pcre.php">PCRE</a> inklusive Abgrenzungszeichen, der
			in der hinter der URL liegenden Seite nach Übungsaufgaben sucht.</dd>
		<dt>Übung</dt>
		<dd>Die Übung, die letztenendes dem Benutzer angezeigt wird. Gruppierungen aus der Suchphrase kannst Du mit
			<var>$1</var>…<var>$9</var> verwenden. Trägst Du hier eine URL ein, so wird die Datei verlinkt. Eine
			URL mit Freitext dahinter (durch ein Leerzeichen getrennt) wird zu der URL mit dem Freitext als Titel.
			Reiner Text bleibt reiner Text. Am Besten ist es, wenn Du hier PDF-Dateien verlinkst, denn diese
			werden von der Software erkannt und z.B. an Handys automatisch als Bilder ausgeliefert.</dd>
	</dl>

	<h3 id="beginner">„Schritt für Schritt“-Anleitung</h3>
	<p>Wenn Du diese Anleitung liest, hast Du hoffentlich bereits Erfahrung im Umgang mit regulären Ausdrücken. Das wird
		diese Anleitung voraussetzen. Hast Du die nicht, bist Du <a href="index.php?q=suggest">mit der Vorschlagenfunktion</a>
		vielleicht besser bedient.</p>
	<p>Das Eintragen eines Kurses wird hier am Beispiel der Vorlesung <em>Funktionentheorie I</em> von Klaus Ecker im
		Sommersemester 2010 erklärt. Du kannst nach demselben Schema vorgehen und es an Deine Vorlesung anpassen.</p>
	<ol class="big">
		<li>Zunächst einmal gilt es, die <a href="http://geometricanalysis.mi.fu-berlin.de/teaching/teaching-SS10.html">Veranstaltungshomepage</a> zu besuchen
			und nach den Übungszetteln zu suchen. In diesem Fall finden wir sie direkt auf dieser Seite:
				<div style="padding: 5px; font: 12px/18px verdana,arial,georgia,sans-serif; border: 1px solid #000; background: #E4D6B7">
					<span style="padding: 1px; background-color: #FAF4E7; border: 1px solid #009966"><strong>V-19024 Funktionentheorie 
						I (Ecker)</strong></span>
					<br><strong>Inhalt</strong>: <br>
					Komplexe Differenzierbarkeit, Cauchyscher Integralsatz, Satz von Liouville,
						Fundamentalsatz der Algebra, u.a., möglicherweise Weierstraßdarstellung.<br>
					<strong>Termine</strong>: <br>
					Vorlesung: <br>
					Do10:00 - 12:00 Uhr, Arnimallee 6, SR 031 <br>
					Fr 10:00 - 12:00 Uhr, Arnimallee 6, SR 007/008<br>
					Übung: Mi 14:00 - 16:00 Uhr,  Königin Luise Straße 24-25, HS 06<br>
					Übung: Mo 12:00 - 14:00 Uhr, Arnimallee 3, HH, SR 130<br>
					<strong>Klausur: am 15. Juli um 10:00 Uhr. </strong>  Wenn dieser Termin nicht wahrgenommen werden kann, wird 
						ein ärzliches Attest benötigt.<br>
					<a style="text-decoration: none; font-weight: bold; color: #930" 
					href="http://geometricanalysis.mi.fu-berlin.de/uebungen/funktionentheorie/SS10-FunktionentheorieI-notenliste.pdf">Klausurergebnisse</a>
					<br>
					<strong>Sprechstunde</strong> nach der Vorlesung<br>
					<strong>Literatur</strong>:<br>
					Literatur wird in der Vorlesung angegeben.<br>
					Übungen:<br>
					Blatt 1, Abgabe am 29.04.10 <a style="text-decoration: none; font-weight: bold; color: #930"
						href="http://geometricanalysis.mi.fu-berlin.de/uebungen/funktionentheorie/funkth1001.pdf">PDF-Format</a><br>
					Blatt 2, Abgabe am 06.05.10 <a style="text-decoration: none; font-weight: bold; color: #930"
						href="http://geometricanalysis.mi.fu-berlin.de/uebungen/funktionentheorie/funkth1002.pdf">PDF-Format</a><br>
					Blatt 3, Abgabe am 14.05.10 <a style="text-decoration: none; font-weight: bold; color: #930"
						href="http://geometricanalysis.mi.fu-berlin.de/uebungen/funktionentheorie/funkth1003.pdf">PDF-Format</a><br>
					Blatt 4, Abgabe am 21.05.10 <a style="text-decoration: none; font-weight: bold; color: #930"
						href="http://geometricanalysis.mi.fu-berlin.de/uebungen/funktionentheorie/funkth1004.pdf">PDF-Format</a><br>
					Blatt 5, Abgabe am 28.05.10 <a style="text-decoration: none; font-weight: bold; color: #930"
						href="http://geometricanalysis.mi.fu-berlin.de/uebungen/funktionentheorie/funkth1005.pdf">PDF-Format</a><br>
					Blatt 6, Abgabe am 18.06.10 <a style="text-decoration: none; font-weight: bold; color: #930"
						href="http://geometricanalysis.mi.fu-berlin.de/uebungen/funktionentheorie/funkth1006.pdf">PDF-Format</a><br>
					Blatt 7, Abgabe am 09.07.10 <a style="text-decoration: none; font-weight: bold; color: #930"
						href="http://geometricanalysis.mi.fu-berlin.de/uebungen/funktionentheorie/funkth1007.pdf">PDF-Format</a>
				</div>
		</li>
		<li>Im Quelltext der Seite sucht man nun nach den Links auf die Übungszettel. Vorallem ist es wichtig, ein Herausstellungsmerkmal
			der Übungszettel gegenüber den anderen Links zu finden. Bei uns sieht der Quelltext so aus:
			<div style="font-family:monospace">
				<span style="color: #a52a2a">&nbsp;1 </span>Übungen:<span style="color: #008b8b">&lt;</span><span 
					style="color: #a52a2a"><b>br</b></span><span style="color: #008b8b">&gt;</span><br>
				<span style="color: #a52a2a">&nbsp;2 </span><br>
				<span style="color: #a52a2a">&nbsp;3 </span>Blatt 1, Abgabe am 29.04.10 <span
					style="color: #008b8b">&lt;</span><span style="color: #a52a2a"><b>a</b></span><span
					style="color: #008b8b">&nbsp;</span><span style="color: #2e8b57"><b>href</b></span><span
					style="color: #008b8b">=</span><span 
					style="color: #ff00ff">&quot;../uebungen/funktionentheorie/funkth1001.pdf&quot;</span><span 
					style="color: #008b8b">&gt;</span><span style="color: #6a5acd"><u>PDF-Format</u></span><span
					style="color: #008b8b">&lt;/</span><span style="color: #a52a2a"><b>a</b></span><span
					style="color: #008b8b">&gt;</span><span style="color: #008b8b">&lt;</span><span
					style="color: #a52a2a"><b>br</b></span><span style="color: #008b8b">&gt;</span><br>
				<span style="color: #a52a2a">&nbsp;4 </span>Blatt 2, Abgabe am 06.05.10 <span style="color: #008b8b">&lt;</span><span 
					style="color: #a52a2a"><b>a</b></span><span style="color: #008b8b">&nbsp;</span><span
					style="color: #2e8b57"><b>href</b></span><span style="color: #008b8b">=</span><span
					style="color: #ff00ff">&quot;../uebungen/funktionentheorie/funkth1002.pdf&quot;</span><span
					style="color: #008b8b">&gt;</span><span style="color: #6a5acd"><u>PDF-Format</u></span><span
					style="color: #008b8b">&lt;/</span><span style="color: #a52a2a"><b>a</b></span><span
					style="color: #008b8b">&gt;</span><span style="color: #008b8b">&lt;</span><span
					style="color: #a52a2a"><b>br</b></span><span style="color: #008b8b">&gt;</span><br>
				<span style="color: #a52a2a">&nbsp;5 </span>Blatt 3, Abgabe am 14.05.10 <span style="color: #008b8b">&lt;</span><span
					style="color: #a52a2a"><b>a</b></span><span style="color: #008b8b">&nbsp;</span><span
					style="color: #2e8b57"><b>href</b></span><span style="color: #008b8b">=</span><span
					style="color: #ff00ff">&quot;../uebungen/funktionentheorie/funkth1003.pdf&quot;</span><span
					style="color: #008b8b">&gt;</span><span style="color: #6a5acd"><u>PDF-Format</u></span><span
					style="color: #008b8b">&lt;/</span><span style="color: #a52a2a"><b>a</b></span><span
					style="color: #008b8b">&gt;</span><span style="color: #008b8b">&lt;</span><span
					style="color: #a52a2a"><b>br</b></span><span style="color: #008b8b">&gt;</span><br>
				<span style="color: #a52a2a">&nbsp;6 </span>Blatt 4, Abgabe am 21.05.10 <span
					style="color: #008b8b">&lt;</span><span style="color: #a52a2a"><b>a</b></span><span
					style="color: #008b8b">&nbsp;</span><span style="color: #2e8b57"><b>href</b></span><span
					style="color: #008b8b">=</span><span
					style="color: #ff00ff">&quot;../uebungen/funktionentheorie/funkth1004.pdf&quot;</span><span
					style="color: #008b8b">&gt;</span><span style="color: #6a5acd"><u>PDF-Format</u></span><span
					style="color: #008b8b">&lt;/</span><span style="color: #a52a2a"><b>a</b></span><span
					style="color: #008b8b">&gt;</span><span style="color: #008b8b">&lt;</span><span
					style="color: #a52a2a"><b>br</b></span><span style="color: #008b8b">&gt;</span><br>
				<span style="color: #a52a2a">&nbsp;7 </span>Blatt 5, Abgabe am 28.05.10 <span style="color: #008b8b">&lt;</span><span
					style="color: #a52a2a"><b>a</b></span><span style="color: #008b8b">&nbsp;</span><span
					style="color: #2e8b57"><b>href</b></span><span style="color: #008b8b">=</span><span
					style="color: #ff00ff">&quot;../uebungen/funktionentheorie/funkth1005.pdf&quot;</span><span
					style="color: #008b8b">&gt;</span><span style="color: #6a5acd"><u>PDF-Format</u></span><span
					style="color: #008b8b">&lt;/</span><span style="color: #a52a2a"><b>a</b></span><span
					style="color: #008b8b">&gt;</span><span style="color: #008b8b">&lt;</span><span
					style="color: #a52a2a"><b>br</b></span><span style="color: #008b8b">&gt;</span><br>
				<span style="color: #a52a2a">&nbsp;8 </span>Blatt 6, Abgabe am 18.06.10 <span style="color: #008b8b">&lt;</span><span
					style="color: #a52a2a"><b>a</b></span><span style="color: #008b8b">&nbsp;</span><span
					style="color: #2e8b57"><b>href</b></span><span style="color: #008b8b">=</span><span
					style="color: #ff00ff">&quot;../uebungen/funktionentheorie/funkth1006.pdf&quot;</span><span
					style="color: #008b8b">&gt;</span><span style="color: #6a5acd"><u>PDF-Format</u></span><span
					style="color: #008b8b">&lt;/</span><span style="color: #a52a2a"><b>a</b></span><span
					style="color: #008b8b">&gt;</span><span style="color: #008b8b">&lt;</span><span
					style="color: #a52a2a"><b>br</b></span><span style="color: #008b8b">&gt;</span><br>
				<span style="color: #a52a2a">&nbsp;9 </span><br>
				<span style="color: #a52a2a">10 </span>Blatt 7, Abgabe am 09.07.10 <span style="color: #008b8b">&lt;</span><span
					style="color: #a52a2a"><b>a</b></span><span style="color: #008b8b">&nbsp;</span><span
					style="color: #2e8b57"><b>href</b></span><span style="color: #008b8b">=</span><span
					style="color: #ff00ff">&quot;../uebungen/funktionentheorie/funkth1007.pdf&quot;</span><span
					style="color: #008b8b">&gt;</span><span style="color: #6a5acd"><u>PDF-Format</u></span><span
					style="color: #008b8b">&lt;/</span><span style="color: #a52a2a"><b>a</b></span><span
					style="color: #008b8b">&gt;&lt;/</span><span style="color: #a52a2a"><b>p</b></span><span style="color: #008b8b">&gt;</span><br>
			</div>
			<p>Wir haben es hier damit einfach: Alle Übungszettel haben einen Dateinamen <var>funkth[0-9]+.pdf</var>. Am Anfang
				der Zeile steht zudem jeweils <em>Blatt</em>. Daraus lässt sich ein regulärer Ausdruck für Übungszettel
				ableiten:</p>
			<p style="text-align: center; font-family: monospace">#^(Blatt [0-9]+)[^&lt;]+&lt;a href="[^"]+(funkth[0-9]+\.pdf)#m</p>
			<p>Die Syntax entspricht dabei der von <a href="http://de3.php.net/manual/de/book.pcre.php">PCRE</a>. Am Anfang und Ende
				müssen Begrenzerzeichen stehen, ich habe hier <var>#</var> verwendet.</p>
			<p>Dieser Ausdruck kann natürlich beliebig kompliziert werden. Es gibt aber auch Fälle, in denen man an dieser Stelle feststellt,
				dass die Zettel nicht mit einem einzelnen Ausdruck erfassbar sind. In dem Fall hilft Dir <a href="index.php?q=suggest">die
					Vorschlagenfunktion</a> weiter, denn wir haben die Möglichkeit, PHP-Code zum finden der Zettel zu verwenden.</p>
			<p>In dem regulären Ausdruck habe ich an zwei Stellen Teile durch Klammern gruppiert: Den Dateinamen und die Beschreibung. Beide
				wollen wir später verwenden.</p>
		</li>
		<li>
			<p>Mit den gewonnenen Informationen füllen wir nun das Formular aus: Unter Beschreibung trage ich „Funktionentheorie I, Klaus Ecker, SS 10“ ein,
				damit andere den Kurs finden. Als Kürzel wähle ich „FT“. Die URL entspricht hier der Veranstaltungshomepage, denn dort haben wir die
				Links auf die Übungen gefunden. Das Suchmuster ist der reguläre Ausdruck von oben. In das Feld Übungszettel muss eingetragen werden,
				wie die Übungszettel später verlinkt werden sollen. Die Syntax ist dabei:</p>
			<p style="text-align: center; font-family: monospace">URL Freitext</p>
			<p>Die URL soll später die URL der Übungszettel sein, der Freitext wird zur Beschreibung des Links bzw. zum Dateinamen. Wir müssen darauf
				achten, einen absoluten Link zum Übungszettel anzugeben, dieser Dienst liegt schließlich nicht in derselben Domain wie die
				Übungszettel. Wir tragen also ein:</p>
			<p style="text-align: center; font-family: monospace">http://geometricanalysis.mi.fu-berlin.de/uebungen/funktionentheorie/$2 $1.pdf</p>
			<p><var>$1</var> wird dabei durch die erste Klammerngruppe des regulären Ausdrucks ersetzt, <var>$2</var> durch die zweite. Die URL
				haben wir so zusammengebaut, dass sie insgesamt so aussehen wird, wie sie ein Browser anzeigen würde, wenn wir auf der
				Veranstaltungsseite daraufgeklickt haben.</p>
		</li>
		<li>
			<p>Ein Klick auf „Vorschau“ öffnet nun ein Popup, in dem wir die Ausgabe unseres Suchmusters sehen:</p>
			<ul style='font-size: x-small'><li><a class="exercise" href="http://geometricanalysis.mi.fu-berlin.de/uebungen/funktionentheorie/funkth1001.pdf"> Blatt 1.pdf</a></li><li><a class="exercise" href="http://geometricanalysis.mi.fu-berlin.de/uebungen/funktionentheorie/funkth1002.pdf"> Blatt 2.pdf</a></li><li><a class="exercise" href="http://geometricanalysis.mi.fu-berlin.de/uebungen/funktionentheorie/funkth1003.pdf"> Blatt 3.pdf</a></li><li><a class="exercise" href="http://geometricanalysis.mi.fu-berlin.de/uebungen/funktionentheorie/funkth1004.pdf"> Blatt 4.pdf</a></li><li><a class="exercise" href="http://geometricanalysis.mi.fu-berlin.de/uebungen/funktionentheorie/funkth1005.pdf"> Blatt 5.pdf</a></li><li><a class="exercise" href="http://geometricanalysis.mi.fu-berlin.de/uebungen/funktionentheorie/funkth1006.pdf"> Blatt 6.pdf</a></li><li><a class="exercise" href="http://geometricanalysis.mi.fu-berlin.de/uebungen/funktionentheorie/funkth1007.pdf"> Blatt 7.pdf</a></li></ul>
			<p>Erscheint das Popup nicht, musst Du Deinen Popup-Blocker deaktivieren!</p>
		</li>
		<li>
			Gefällt Dir die Ausgabe (in diesem Fall sollte sie das) genügt ein Klick auf „Speichern“ zum Abspeichern der Definition.
		</li>
		<li>
			Bis jetzt kannst nur Du Deinen neu definierten Zettel sehen. Ein Administrator wird ihn bald für alle sichtbar
			schalten. (Es sei denn, Du schreibst in die Beschreibung, dass Du das nicht willst). Auf der Details-Seite kannst Du
			die Definition auch noch nachträglich ändern, indem Du auf die einzelnen Felder klickst.
		</li>
		<li>
			Bei der nächsten Ausführung des Cronjobs werden alle bisher verfügbaren Zettel aggregiert.
		</li>
	</ol>

	<h3 id="php">PHP-Code zur Definition verwenden</h3>
	<p>Diese Option steht nur Administratoren zur Verfügung. Bist Du keiner, aber der Meinung, dass Du PHP-Code ausführen musst,
		um ein Feed zu definieren, benutze  <a href="index.php?q=suggest">die Vorschlagenfunktion</a>.</p>
	<p>Über „Neue Kurse hinzufügen (PHP)“ kann ein Feed definiert werden, das PHP-Code verwendet. In die große Textarea muss
		dafür PHP-Code direkt eingetragen werden. Er sollte per <code>return</code> ein Array von Übungen (entsprechend dem
		„Übung“-Feld der normalen Eingabe) zurückgeben. Dabei stehen folgende Hilfsfunktionen zur Verfügung:</p>
	<dl class="exp">
		<dt>load_url</dt>
		<dd>Lädt eine übergebene URL und gibt ihren Inhalt kodiert in UTF-8 zurück</dd>
		<dt>cache_file</dt>
		<dd>Cached eine übergebene URL und gibt eine URL zurück, der auf diesem Server liegt. Praktisch
			für Seiten, die das herunterladen von Zetteln nur via einem Formular o.Ä. erlauben.
			Die Syntax lautet <code>load_url($url, $file_name, false, false);</code>
		</dd>
		<dt>cache_contents</dt>
		<dd>Wie cache_file, gibt aber den Inhalt des Caches zurück</dd>
	</dl>
	<p>Auch diese Art des Eintragens zeigt zunächst nur eine Vorschau an.</p>
</div>
