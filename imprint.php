<?php

define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);

// Start session management
$user->session_begin();
$auth->acl($user->data);
$user->setup();

// No bots allowed in here
if ($user->data['is_bot'])
{
   redirect(append_sid("{$phpbb_root_path}index.$phpEx"));
}

page_header('Impressum');

$template->set_filenames(array(
    'body' => 'imprint.html',
));

make_jumpbox(append_sid("{$phpbb_root_path}viewforum.$phpEx"));
page_footer();
