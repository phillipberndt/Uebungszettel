<?php
	if(!force_level(2)) return;

	if(isset($_POST['submit'])) {
		foreach($_POST['user'] as $id => $change) {
			$id = intval($id);
			if($id == 1) continue;

			$user = user_load('id', $id);
			if(!$user) continue;
			if($change['delete']) {
				$database->exec('DELETE FROM user_data WHERE user_id = '.$id);
				$database->exec('DELETE FROM user_feeds WHERE user_id = '.$id);
				$database->exec('DELETE FROM users WHERE id = '.$id);
				status_message("Benutzer " . htmlspecialchars($user->name) . " gelöscht");
				admin_log("Benutzer " . htmlspecialchars($user->name) . " gelöscht");
				continue;
			}
			if($change['pass']) {
				$user->pass = sha1($user->salt . $change['pass']);
				status_message("Passwort für Benutzer " . htmlspecialchars($user->name) . " geändert");
				admin_log("Passwort für Benutzer " . htmlspecialchars($user->name) . " geändert");
			}
			if(isset($change['level'])) {
				$user->level = intval($change['level']);
				status_message("Benutzerlevel für Benutzer " . htmlspecialchars($user->name) . " auf " . $user->level . " geändert");
				admin_log("Benutzerlevel für Benutzer " . htmlspecialchars($user->name) . " auf " . $user->level . " geändert");
			}
			user_save($user);
		}
		gotop("index.php?q=admin");
	}
?>
<script type="text/javascript"><!--
	$(document).ready(function() {
		var fields = {};
		$("#users input, #users select").change(function() {
			if(((this.type == "checkbox" && !this.checked) || this.value == "") && this.name in fields) {
				fields[this.name].remove();
				delete fields[this.name];
				return;
			}
			if(this.name in fields) {
				fields[this.name].val(this.value);
			}
			else {
				fields[this.name] = $("<input type='hidden'>").attr("name", this.name).val(this.value).appendTo($("#users_form"));
			}
		});
	});
// --> </script>
<div id="content">
	<h2>Administration</h2>
	<table id="users">
		<thead><tr><th>Benutzername</th><th>Email</th><th>Passwort</th><th>Berechtigungen</th><th>Aktion</th></tr></thead>
		<tbody>
		<?php
			$users = $database->query('SELECT id, name, level, settings FROM users WHERE id > 1 ORDER BY id ASC');
			foreach($users as $user):
				$settings = unserialize($user['settings']);
				?>
				<tr><td><?=htmlspecialchars($user['name'])?></td>
					<td><?=htmlspecialchars($settings['newsletter'])?></td>
					<td><input type="text" name="user[<?=$user['id']?>][pass]"></td>
					<td><select name="user[<?=$user['id']?>][level]">
						<?php foreach(user_levels() as $key => $desc): ?>
						<option value="<?=$key?>" <?php if($user['level'] == $key) echo('selected'); ?>><?=$desc?></option>
						<?php endforeach; ?>
						</select></td>
					<td><label><input type="checkbox" value="1" name="user[<?=$user['id']?>][delete]"> Löschen</label></td>
				</tr>
			<?php endforeach;
		?>
		</tbody>
	</table>
	<form method="post" id="users_form" action="index.php?q=admin">
		<input style="float: right; margin-top: 10px" type="submit" name="submit" value="Speichern">
	</form>
	<?php if($GLOBALS['admin_log_file']): ?>
	<h3>Admin Log-File</h3>
	<pre><?php
		echo(file_get_contents($GLOBALS['admin_log_file']));
	?></pre>
	<?php endif; ?>
</div>
