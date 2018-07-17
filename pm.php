<?php

// You can send a message to the mods with this form
// Even if you are not logged in

require_once 'inc/functions.php';

check_login(false);

function mod_page($title, $template, $args, $subtitle = false) {
	global $config, $mod;
	
	echo Element('page.html', array(
		'config' => $config,
		'mod' => $mod,
		'hide_dashboard_link' => $template == 'mod/dashboard.html',
		'title' => $title,
		'subtitle' => $subtitle,
		'boardlist' => createBoardlist(false),
		'body' => Element($template,
				array_merge(
					array('config' => $config, 'mod' => $mod), 
					$args
				)
			)
		)
	);
}

function mod_user_new_with_fixed_type($type) {
	global $pdo, $config;

	
	//if (!hasPermission($config['mod']['createusers']))
	//	error($config['error']['noaccess']);
	
	if (isset($_POST['username'], $_POST['password'])) {
		if ($_POST['username'] == '')
			error(sprintf($config['error']['required'], 'username'));
		if ($_POST['password'] == '')
			error(sprintf($config['error']['required'], 'password'));
		
		if (true || isset($_POST['allboards'])) {
			$boards = array('*');
		} else {
			$_boards = listBoards();
			foreach ($_boards as &$board) {
				$board = $board['uri'];
			}
			
			$boards = array();
			foreach ($_POST as $name => $value) {
				if (preg_match('/^board_(' . $config['board_regex'] . ')$/u', $name, $matches) && in_array($matches[1], $_boards))
					$boards[] = $matches[1];
			}
		}
		
		if (!isset($config['mod']['groups'][$type]) || $type == DISABLED)
			error(sprintf($config['error']['invalidfield'], 'type'));
		
		list($version, $password) = crypt_password($_POST['password']);
		
		$query = prepare('INSERT INTO ``mods`` VALUES (NULL, :username, :password, :version, :type, :boards)');
		$query->bindValue(':username', $_POST['username']);
		$query->bindValue(':password', $password);
		$query->bindValue(':version', $version);
		$query->bindValue(':type', $type);
		$query->bindValue(':boards', implode(',', $boards));
		$query->execute() or error(db_error($query));
		
		$userID = $pdo->lastInsertId();
		return;
	}
	else {
		error();
	}
}

function mod_new_pm($username) {
	global $config, $mod;
	
	$query = prepare("SELECT `id` FROM ``mods`` WHERE `username` = :username");
	$query->bindValue(':username', $username);
	$query->execute() or error(db_error($query));
	if (!$id_of_user_to_pm = $query->fetchColumn()) {
		error($config['error']['404']);
	}

	if (isset($_POST['message'])) {
		if(!$mod) {
			// Check if user exists
			$query = prepare("SELECT `id` FROM ``mods`` WHERE `username` = :username");
			$query->bindValue(':username', $_POST['login_or_create_username']);
			$query->execute() or error(db_error($query));

			if (!$user_id = $query->fetchColumn()) {
				// User doesn't exist
				// No, we need to register a new user
				$_POST['username'] = $_POST['login_or_create_username'];
				$_POST['password'] = $_POST['login_or_create_password'];
				mod_user_new_with_fixed_type(5);
			}
			
			// Found user
			// We haven't logged in, so check if we can login with the credentials
			if($mod = login($_POST['login_or_create_username'], $_POST['login_or_create_password'])) {
				// Logged in succesfully
				setCookies();
			}
		}
		if (!$mod) {
			error("error in credentials check");
		}

		$_POST['message'] = escape_markup_modifiers($_POST['message']);
		markup($_POST['message']);
		
		$query = prepare("INSERT INTO ``pms`` VALUES (NULL, :me, :id, :message, :time, 1)");
		$query->bindValue(':id', $id_of_user_to_pm);
		$query->bindValue(':me', $mod['id']);
		$query->bindValue(':message', $_POST['message']);
		$query->bindValue(':time', time());
		$query->execute() or error(db_error($query));
		
		if ($config['cache']['enabled']) {
			cache::delete('pm_unread_' . $id_of_user_to_pm);
			cache::delete('pm_unreadcount_' . $id_of_user_to_pm);
		}
		
		modLog('Sent a PM to ' . utf8tohtml($username));
		
		mod_page('Viestisi on lähetetty', 'anonymous_pm_sent.html', array(
			'your_username' => $mod['username'],
			'token' => make_secure_link_token('new_PM/' . $username)
		));
	} else {
		mod_page(sprintf('%s %s', _('New PM for'), $username), 'anonymous_pm.html', array(
			'username' => $username,
			'id' => $id_of_user_to_pm,
			'token' => make_secure_link_token('new_PM/' . $username)
		));
	}
}

$username_to_send_pm_to = $config['community_manager_username'];
mod_new_pm($username_to_send_pm_to);

?>