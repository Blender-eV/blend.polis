<?php
/**
 * blend.polis IRC Client
 * java-Browserplugin benötigt
 */
define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup();

page_header('IRC Chat');

$template->set_filenames(array(
    'body' => 'chat_body.html',
));

$template->assign_vars(array(
    'USERNAME' => str_replace(' ', '_', $user->data['username']),
	 'ALTERNATENICK' => 'Gast_' . rand(1000, 99999),
));

#make_jumpbox(append_sid("{$phpbb_root_path}viewforum.$phpEx"));
page_footer();
