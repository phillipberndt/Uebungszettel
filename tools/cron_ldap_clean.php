<?php
	/*
		Cleanup script: Remove accounts which no longer exist in LDAP

		This script should be placed outside the htdocs directory and run as a
		cron-job. It checks for each LDAP user if a LDAP account still exists
		and deletes an account if it does not.
	*/
	chdir('htdocs'); // change this to fit your directory structure
	require('system.php');
	restore_exception_handler();

	$not_found = array();

	$users = $database->query('SELECT id, name FROM users WHERE id > 1 AND (flags & ' . USER_FLAG_IS_LDAP_ACCOUNT . ') != 0 ORDER BY id ASC');
	foreach($users as $user) {
		printf("%20s: ", $user['name']);
		$exists = ldap_check_if_name_exists($user['name']);
		if($exists) {
			echo " found in ldap\n";
			continue;
		}
		echo " not found\n";

		$not_found[] = $user['id'];
	}

	if($not_found) {
		$ids = implode(', ', $not_found);
		echo "Deleting users:\n";
		foreach(array(
			'BEGIN TRANSACTION',
			'DELETE FROM user_data WHERE user_id IN ('.$ids.')',
			'DELETE FROM user_feeds WHERE user_id IN ('.$ids.')',
			'DELETE FROM users WHERE id IN ('.$ids.')',
			'COMMIT') as $query) {
			echo $query . "\n";

			if($database->exec($query) === FALSE) die("\nSQL statement failed to execute.");
		}
	}
