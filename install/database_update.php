<?php
/**
*
* @package install
* @version $Id$
* @copyright (c) 2006 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

$updates_to_version = '3.0.5';

// Enter any version to update from to test updates. The version within the db will not be updated.
$debug_from_version = false;

// Return if we "just include it" to find out for which version the database update is responsible for
if (defined('IN_PHPBB') && defined('IN_INSTALL'))
{
	return;
}

/**
*/
define('IN_PHPBB', true);
define('IN_INSTALL', true);

$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './../';
$phpEx = substr(strrchr(__FILE__, '.'), 1);

// Report all errors, except notices
//error_reporting(E_ALL ^ E_NOTICE);
error_reporting(E_ALL);

@set_time_limit(0);

// Include essential scripts
include($phpbb_root_path . 'config.' . $phpEx);

if (!defined('PHPBB_INSTALLED') || empty($dbms) || empty($acm_type))
{
	die("Please read: <a href='../docs/INSTALL.html'>INSTALL.html</a> before attempting to update.");
}

// Load Extensions
if (!empty($load_extensions))
{
	$load_extensions = explode(',', $load_extensions);

	foreach ($load_extensions as $extension)
	{
		@dl(trim($extension));
	}
}

// Include files
require($phpbb_root_path . 'includes/acm/acm_' . $acm_type . '.' . $phpEx);
require($phpbb_root_path . 'includes/cache.' . $phpEx);
require($phpbb_root_path . 'includes/template.' . $phpEx);
require($phpbb_root_path . 'includes/session.' . $phpEx);
require($phpbb_root_path . 'includes/auth.' . $phpEx);

require($phpbb_root_path . 'includes/functions.' . $phpEx);

if (file_exists($phpbb_root_path . 'includes/functions_content.' . $phpEx))
{
	require($phpbb_root_path . 'includes/functions_content.' . $phpEx);
}

require($phpbb_root_path . 'includes/functions_admin.' . $phpEx);
require($phpbb_root_path . 'includes/constants.' . $phpEx);
require($phpbb_root_path . 'includes/db/' . $dbms . '.' . $phpEx);
require($phpbb_root_path . 'includes/utf/utf_tools.' . $phpEx);

// If we are on PHP >= 6.0.0 we do not need some code
if (version_compare(PHP_VERSION, '6.0.0-dev', '>='))
{
	/**
	* @ignore
	*/
	define('STRIP', false);
}
else
{
	@set_magic_quotes_runtime(0);
	define('STRIP', (get_magic_quotes_gpc()) ? true : false);
}

$user = new user();
$cache = new cache();
$db = new $sql_db();

// Add own hook handler, if present. :o
if (file_exists($phpbb_root_path . 'includes/hooks/index.' . $phpEx))
{
	require($phpbb_root_path . 'includes/hooks/index.' . $phpEx);
	$phpbb_hook = new phpbb_hook(array('exit_handler', 'phpbb_user_session_handler', 'append_sid', array('template', 'display')));

	foreach ($cache->obtain_hooks() as $hook)
	{
		@include($phpbb_root_path . 'includes/hooks/' . $hook . '.' . $phpEx);
	}
}
else
{
	$phpbb_hook = false;
}

// Connect to DB
$db->sql_connect($dbhost, $dbuser, $dbpasswd, $dbname, $dbport, false, false);

// We do not need this any longer, unset for safety purposes
unset($dbpasswd);

$user->ip = (!empty($_SERVER['REMOTE_ADDR'])) ? htmlspecialchars($_SERVER['REMOTE_ADDR']) : '';

$sql = "SELECT config_value
	FROM " . CONFIG_TABLE . "
	WHERE config_name = 'default_lang'";
$result = $db->sql_query($sql);
$row = $db->sql_fetchrow($result);
$db->sql_freeresult($result);

$language = basename(request_var('language', ''));

if (!$language)
{
	$language = $row['config_value'];
}

if (!file_exists($phpbb_root_path . 'language/' . $language))
{
	die('No language found!');
}

// And finally, load the relevant language files
include($phpbb_root_path . 'language/' . $language . '/common.' . $phpEx);
include($phpbb_root_path . 'language/' . $language . '/acp/common.' . $phpEx);
include($phpbb_root_path . 'language/' . $language . '/install.' . $phpEx);

// Set PHP error handler to ours
//set_error_handler('msg_handler');

// Define some variables for the database update
$inline_update = (request_var('type', 0)) ? true : false;

// To let set_config() calls succeed, we need to make the config array available globally
$config = array();

$sql = 'SELECT *
	FROM ' . CONFIG_TABLE;
$result = $db->sql_query($sql);

while ($row = $db->sql_fetchrow($result))
{
	$config[$row['config_name']] = $row['config_value'];
}
$db->sql_freeresult($result);

// We do not include DB Tools here, because we can not be sure the file is up-to-date ;)
// Instead, this file defines a clean db_tools version (we are also not able to provide a different file, else the database update will not work standalone)
$db_tools = new updater_db_tools($db, true);

$database_update_info = database_update_info();

$error_ary = array();
$errored = false;

header('Content-type: text/html; charset=UTF-8');

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" dir="<?php echo $lang['DIRECTION']; ?>" lang="<?php echo $lang['USER_LANG']; ?>" xml:lang="<?php echo $lang['USER_LANG']; ?>">
<head>

<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
<meta http-equiv="content-language" content="<?php echo $lang['USER_LANG']; ?>" />
<meta http-equiv="content-style-type" content="text/css" />
<meta http-equiv="imagetoolbar" content="no" />

<title><?php echo $lang['UPDATING_TO_LATEST_STABLE']; ?></title>

<link href="../adm/style/admin.css" rel="stylesheet" type="text/css" media="screen" />

</head>

<body>
<div id="wrap">
	<div id="page-header">&nbsp;</div>

	<div id="page-body">
		<div id="acp">
		<div class="panel">
			<span class="corners-top"><span></span></span>
				<div id="content">
					<div id="main" class="install-body">

	<h1><?php echo $lang['UPDATING_TO_LATEST_STABLE']; ?></h1>

	<br />

	<p><?php echo $lang['DATABASE_TYPE']; ?> :: <strong><?php echo $db->sql_layer; ?></strong><br />
<?php

if ($debug_from_version !== false)
{
	$config['version'] = $debug_from_version;
}

echo $lang['PREVIOUS_VERSION'] . ' :: <strong>' . $config['version'] . '</strong><br />';
echo $lang['UPDATED_VERSION'] . ' :: <strong>' . $updates_to_version . '</strong></p>';

$current_version = str_replace('rc', 'RC', strtolower($config['version']));
$latest_version = str_replace('rc', 'RC', strtolower($updates_to_version));
$orig_version = $config['version'];

// Fill DB version
if (empty($config['dbms_version']))
{
	set_config('dbms_version', $db->sql_server_info(true));
}

// MySQL update from MySQL 3.x/4.x to > 4.1.x required?
if ($db->sql_layer == 'mysql' || $db->sql_layer == 'mysql4' || $db->sql_layer == 'mysqli')
{
	// Verify by fetching column... if the column type matches the new type we update dbms_version...
	$sql = "SHOW COLUMNS FROM " . CONFIG_TABLE;
	$result = $db->sql_query($sql);

	$column_type = '';
	while ($row = $db->sql_fetchrow($result))
	{
		$field = strtolower($row['Field']);

		if ($field == 'config_value')
		{
			$column_type = strtolower($row['Type']);
			break;
		}
	}
	$db->sql_freeresult($result);

	// If column type is blob, but mysql version says we are on > 4.1.3, then the schema needs an update
	if (strpos($column_type, 'blob') !== false && version_compare($db->sql_server_info(true), '4.1.3', '>='))
	{
		echo '<br /><br />';
		echo '<h1>' . $lang['ERROR'] . '</h1><br />';

		echo '<p>' . sprintf($lang['MYSQL_SCHEMA_UPDATE_REQUIRED'], $config['dbms_version'], $db->sql_server_info(true)) . '</p>';
?>
					</div>
				</div>
			<span class="corners-bottom"><span></span></span>
		</div>
		</div>
	</div>

	<div id="page-footer">
		Powered by <a href="http://www.phpbb.com/">phpBB</a> &copy; 2000, 2002, 2005, 2007 phpBB Group
	</div>
</div>

</body>
</html>
<?php

		exit_handler();
		exit;
	}
}

// If the latest version and the current version are 'unequal', we will update the version_update_from, else we do not update anything.
if ($inline_update)
{
	if ($current_version !== $latest_version)
	{
		set_config('version_update_from', $orig_version);
	}
}
else
{
	// If not called from the update script, we will actually remove the traces
	$db->sql_query('DELETE FROM ' . CONFIG_TABLE . " WHERE config_name = 'version_update_from'");
}

// Schema updates
?>
	<br /><br />

	<h1><?php echo $lang['UPDATE_DATABASE_SCHEMA']; ?></h1>

	<br />
	<p><?php echo $lang['PROGRESS']; ?> :: <strong>

<?php

flush();

// We go through the schema changes from the lowest to the highest version
// We try to also include versions 'in-between'...
$no_updates = true;
$versions = array_keys($database_update_info);
for ($i = 0; $i < sizeof($versions); $i++)
{
	$version = $versions[$i];
	$schema_changes = $database_update_info[$version];

	$next_version = (isset($versions[$i + 1])) ? $versions[$i + 1] : $updates_to_version;

	// If the installed version to be updated to is < than the current version, and if the current version is >= as the version to be updated to next, we will skip the process
	if (version_compare($version, $current_version, '<') && version_compare($current_version, $next_version, '>='))
	{
		continue;
	}

	if (!sizeof($schema_changes))
	{
		continue;
	}

	$no_updates = false;

	$statements = $db_tools->perform_schema_changes($schema_changes);

	foreach ($statements as $sql)
	{
		_sql($sql, $errored, $error_ary);
	}
}

_write_result($no_updates, $errored, $error_ary);

// Data updates
$error_ary = array();
$errored = $no_updates = false;

?>

<br /><br />
<h1><?php echo $lang['UPDATING_DATA']; ?></h1>
<br />
<p><?php echo $lang['PROGRESS']; ?> :: <strong>

<?php

flush();

$no_updates = true;
$versions = array_keys($database_update_info);

// some code magic
for ($i = 0; $i < sizeof($versions); $i++)
{
	$version = $versions[$i];
	$next_version = (isset($versions[$i + 1])) ? $versions[$i + 1] : $updates_to_version;

	// If the installed version to be updated to is < than the current version, and if the current version is >= as the version to be updated to next, we will skip the process
	if (version_compare($version, $current_version, '<') && version_compare($current_version, $next_version, '>='))
	{
		continue;
	}

	change_database_data($no_updates, $version);
}

_write_result($no_updates, $errored, $error_ary);

$error_ary = array();
$errored = $no_updates = false;

?>

<br /><br />
<h1><?php echo $lang['UPDATE_VERSION_OPTIMIZE']; ?></h1>
<br />
<p><?php echo $lang['PROGRESS']; ?> :: <strong>

<?php

flush();

if ($debug_from_version === false)
{
	// update the version
	$sql = "UPDATE " . CONFIG_TABLE . "
		SET config_value = '$updates_to_version'
		WHERE config_name = 'version'";
	_sql($sql, $errored, $error_ary);
}

// Reset permissions
$sql = 'UPDATE ' . USERS_TABLE . "
	SET user_permissions = '',
		user_perm_from = 0";
_sql($sql, $errored, $error_ary);

// Update the dbms version if everything is ok...
set_config('dbms_version', $db->sql_server_info(true));

/* Optimize/vacuum analyze the tables where appropriate
// this should be done for each version in future along with
// the version number update
switch ($db->sql_layer)
{
	case 'mysql':
	case 'mysqli':
	case 'mysql4':
		$sql = 'OPTIMIZE TABLE ' . $table_prefix . 'auth_access, ' . $table_prefix . 'banlist, ' . $table_prefix . 'categories, ' . $table_prefix . 'config, ' . $table_prefix . 'disallow, ' . $table_prefix . 'forum_prune, ' . $table_prefix . 'forums, ' . $table_prefix . 'groups, ' . $table_prefix . 'posts, ' . $table_prefix . 'posts_text, ' . $table_prefix . 'privmsgs, ' . $table_prefix . 'privmsgs_text, ' . $table_prefix . 'ranks, ' . $table_prefix . 'search_results, ' . $table_prefix . 'search_wordlist, ' . $table_prefix . 'search_wordmatch, ' . $table_prefix . 'sessions_keys' . $table_prefix . 'smilies, ' . $table_prefix . 'themes, ' . $table_prefix . 'themes_name, ' . $table_prefix . 'topics, ' . $table_prefix . 'topics_watch, ' . $table_prefix . 'user_group, ' . $table_prefix . 'users, ' . $table_prefix . 'vote_desc, ' . $table_prefix . 'vote_results, ' . $table_prefix . 'vote_voters, ' . $table_prefix . 'words';
		_sql($sql, $errored, $error_ary);
	break;

	case 'postgresql':
		_sql("VACUUM ANALYZE", $errored, $error_ary);
	break;
}
*/

_write_result($no_updates, $errored, $error_ary);

?>

<br />
<h1><?php echo $lang['UPDATE_COMPLETED']; ?></h1>

<br />

<?php

if (!$inline_update)
{
?>

	<p style="color:red"><?php echo $lang['UPDATE_FILES_NOTICE']; ?></p>

	<p><?php echo $lang['COMPLETE_LOGIN_TO_BOARD']; ?></p>

<?php
}
else
{
?>

	<p><?php echo ((isset($lang['INLINE_UPDATE_SUCCESSFUL'])) ? $lang['INLINE_UPDATE_SUCCESSFUL'] : 'The database update was successful. Now you need to continue the update process.'); ?></p>

	<p><a href="<?php echo append_sid("{$phpbb_root_path}install/index.{$phpEx}", "mode=update&amp;sub=file_check&amp;lang=$language"); ?>" class="button1"><?php echo (isset($lang['CONTINUE_UPDATE_NOW'])) ? $lang['CONTINUE_UPDATE_NOW'] : 'Continue the update process now'; ?></a></p>

<?php
}

// Add database update to log
add_log('admin', 'LOG_UPDATE_DATABASE', $orig_version, $updates_to_version);

// Now we purge the session table as well as all cache files
$cache->purge();

?>

					</div>
				</div>
			<span class="corners-bottom"><span></span></span>
		</div>
		</div>
	</div>

	<div id="page-footer">
		Powered by phpBB &copy; 2000, 2002, 2005, 2007 <a href="http://www.phpbb.com/">phpBB Group</a>
	</div>
</div>

</body>
</html>

<?php

garbage_collection();

if (function_exists('exit_handler'))
{
	exit_handler();
}

/**
* Function for triggering an sql statement
*/
function _sql($sql, &$errored, &$error_ary, $echo_dot = true)
{
	global $db;

	if (defined('DEBUG_EXTRA'))
	{
		echo "<br />\n{$sql}\n<br />";
	}

	$db->sql_return_on_error(true);

	$result = $db->sql_query($sql);
	if ($db->sql_error_triggered)
	{
		$errored = true;
		$error_ary['sql'][] = $db->sql_error_sql;
		$error_ary['error_code'][] = $db->_sql_error();
	}

	$db->sql_return_on_error(false);

	if ($echo_dot)
	{
		echo ". \n";
		flush();
	}

	return $result;
}

function _write_result($no_updates, $errored, $error_ary)
{
	global $lang;

	if ($no_updates)
	{
		echo ' ' . $lang['NO_UPDATES_REQUIRED'] . '</strong></p>';
	}
	else
	{
		echo ' <span class="success">' . $lang['DONE'] . '</span></strong><br />' . $lang['RESULT'] . ' :: ';

		if ($errored)
		{
			echo ' <strong>' . $lang['SOME_QUERIES_FAILED'] . '</strong> <ul>';

			for ($i = 0; $i < sizeof($error_ary['sql']); $i++)
			{
				echo '<li>' . $lang['ERROR'] . ' :: <strong>' . htmlspecialchars($error_ary['error_code'][$i]['message']) . '</strong><br />';
				echo $lang['SQL'] . ' :: <strong>' . htmlspecialchars($error_ary['sql'][$i]) . '</strong><br /><br /></li>';
			}

			echo '</ul> <br /><br />' . $lang['SQL_FAILURE_EXPLAIN'] . '</p>';
		}
		else
		{
			echo '<strong>' . $lang['NO_ERRORS'] . '</strong></p>';
		}
	}
}

/****************************************************************************
* ADD YOUR DATABASE SCHEMA CHANGES HERE										*
*****************************************************************************/
function database_update_info()
{
	return array(
		// Changes from 3.0.0 to the next version
		'3.0.0'			=> array(
			// Add the following columns
			'add_columns'		=> array(
				FORUMS_TABLE			=> array(
					'display_subforum_list'		=> array('BOOL', 1),
				),
				SESSIONS_TABLE			=> array(
					'session_forum_id'		=> array('UINT', 0),
				),
			),
			'add_index'		=> array(
				SESSIONS_TABLE			=> array(
					'session_forum_id'		=> array('session_forum_id'),
				),
				GROUPS_TABLE			=> array(
					'group_legend_name'		=> array('group_legend', 'group_name'),
				),
			),
			'drop_keys'		=> array(
				GROUPS_TABLE			=> array('group_legend'),
			),
		),
		// No changes from 3.0.1-RC1 to 3.0.1
		'3.0.1-RC1'		=> array(),
		// No changes from 3.0.1 to 3.0.2-RC1
		'3.0.1'			=> array(),
		// Changes from 3.0.2-RC1 to 3.0.2-RC2
		'3.0.2-RC1'		=> array(
			'change_columns'	=> array(
				DRAFTS_TABLE			=> array(
					'draft_subject'		=> array('STEXT_UNI', ''),
				),
				FORUMS_TABLE	=> array(
					'forum_last_post_subject' => array('STEXT_UNI', ''),
				),
				POSTS_TABLE		=> array(
					'post_subject'			=> array('STEXT_UNI', '', 'true_sort'),
				),
				PRIVMSGS_TABLE	=> array(
					'message_subject'		=> array('STEXT_UNI', ''),
				),
				TOPICS_TABLE	=> array(
					'topic_title'				=> array('STEXT_UNI', '', 'true_sort'),
					'topic_last_post_subject'	=> array('STEXT_UNI', ''),
				),
			),
			'drop_keys'		=> array(
				SESSIONS_TABLE			=> array('session_forum_id'),
			),
			'add_index'		=> array(
				SESSIONS_TABLE			=> array(
					'session_fid'		=> array('session_forum_id'),
				),
			),
		),
		// No changes from 3.0.2-RC2 to 3.0.2
		'3.0.2-RC2'		=> array(),

		// Changes from 3.0.2 to 3.0.3-RC1
		'3.0.2'			=> array(
			// Add the following columns
			'add_columns'		=> array(
				STYLES_TEMPLATE_TABLE			=> array(
					'template_inherits_id'		=> array('UINT:4', 0),
					'template_inherit_path'		=> array('VCHAR', ''),
				),
				GROUPS_TABLE					=> array(
					'group_max_recipients'		=> array('UINT', 0),
				),
			),
		),

		// No changes from 3.0.3-RC1 to 3.0.3
		'3.0.3-RC1'		=> array(),

		// Changes from 3.0.3 to 3.0.4-RC1
		'3.0.3'			=> array(
			'add_columns'		=> array(
				PROFILE_FIELDS_TABLE			=> array(
					'field_show_profile'		=> array('BOOL', 0),
				),
			),
			'change_columns'	=> array(
				STYLES_TABLE				=> array(
					'style_id'				=> array('UINT', NULL, 'auto_increment'),
					'template_id'			=> array('UINT', 0),
					'theme_id'				=> array('UINT', 0),
					'imageset_id'			=> array('UINT', 0),
				),
				STYLES_IMAGESET_TABLE		=> array(
					'imageset_id'				=> array('UINT', NULL, 'auto_increment'),
				),
				STYLES_IMAGESET_DATA_TABLE	=> array(
					'image_id'				=> array('UINT', NULL, 'auto_increment'),
					'imageset_id'			=> array('UINT', 0),
				),
				STYLES_THEME_TABLE			=> array(
					'theme_id'				=> array('UINT', NULL, 'auto_increment'),
				),
				STYLES_TEMPLATE_TABLE		=> array(
					'template_id'			=> array('UINT', NULL, 'auto_increment'),
				),
				STYLES_TEMPLATE_DATA_TABLE	=> array(
					'template_id'			=> array('UINT', 0),
				),
				FORUMS_TABLE				=> array(
					'forum_style'			=> array('UINT', 0),
				),
				USERS_TABLE					=> array(
					'user_style'			=> array('UINT', 0),
				),
			),
		),

		// Changes from 3.0.4-RC1 to 3.0.4
		'3.0.4-RC1'		=> array(),

		// Changes from 3.0.4 to 3.0.5-RC1
		'3.0.4'			=> array(
			'change_columns'	=> array(
				FORUMS_TABLE				=> array(
					'forum_style'			=> array('UINT', 0),
				),
			),
		),

		// No changes from 3.0.5-RC1 to 3.0.5
		'3.0.5-RC1'		=> array(),
	);
}

/****************************************************************************
* ADD YOUR DATABASE DATA CHANGES HERE										*
* REMEMBER: You NEED to enter a schema array above and a data array here,	*
* even if both or one of them are empty.									*
*****************************************************************************/
function change_database_data(&$no_updates, $version)
{
	global $db, $errored, $error_ary, $config, $phpbb_root_path, $phpEx;

	switch ($version)
	{
		case '3.0.0':

			$sql = 'UPDATE ' . TOPICS_TABLE . "
				SET topic_last_view_time = topic_last_post_time
				WHERE topic_last_view_time = 0";
			_sql($sql, $errored, $error_ary);

			// Update smiley sizes
			$smileys = array('icon_e_surprised.gif', 'icon_eek.gif', 'icon_cool.gif', 'icon_lol.gif', 'icon_mad.gif', 'icon_razz.gif', 'icon_redface.gif', 'icon_cry.gif', 'icon_evil.gif', 'icon_twisted.gif', 'icon_rolleyes.gif', 'icon_exclaim.gif', 'icon_question.gif', 'icon_idea.gif', 'icon_arrow.gif', 'icon_neutral.gif', 'icon_mrgreen.gif', 'icon_e_ugeek.gif');

			foreach ($smileys as $smiley)
			{
				if (file_exists($phpbb_root_path . 'images/smilies/' . $smiley))
				{
					list($width, $height) = getimagesize($phpbb_root_path . 'images/smilies/' . $smiley);

					$sql = 'UPDATE ' . SMILIES_TABLE . '
						SET smiley_width = ' . $width . ', smiley_height = ' . $height . "
						WHERE smiley_url = '" . $db->sql_escape($smiley) . "'";

					_sql($sql, $errored, $error_ary);
				}
			}

			$no_updates = false;
		break;

		// No changes from 3.0.1-RC1 to 3.0.1
		case '3.0.1-RC1':
		break;

		// changes from 3.0.1 to 3.0.2-RC1
		case '3.0.1':

			set_config('referer_validation', '1');
			set_config('check_attachment_content', '1');
			set_config('mime_triggers', 'body|head|html|img|plaintext|a href|pre|script|table|title');

			$no_updates = false;
		break;

		// No changes from 3.0.2-RC1 to 3.0.2-RC2
		case '3.0.2-RC1':
		break;

		// No changes from 3.0.2-RC2 to 3.0.2
		case '3.0.2-RC2':
		break;

		// Changes from 3.0.2 to 3.0.3-RC1
		case '3.0.2':
			set_config('enable_queue_trigger', '0');
			set_config('queue_trigger_posts', '3');

			set_config('pm_max_recipients', '0');

			// Set maximum number of recipients for the registered users, bots, guests group
			$sql = 'UPDATE ' . GROUPS_TABLE . ' SET group_max_recipients = 5
				WHERE ' . $db->sql_in_set('group_name', array('GUESTS', 'REGISTERED', 'REGISTERED_COPPA', 'BOTS'));
			_sql($sql, $errored, $error_ary);

			// Not prefilling yet
			set_config('dbms_version', '');

			// Add new permission u_masspm_group and duplicate settings from u_masspm
			include_once($phpbb_root_path . 'includes/acp/auth.' . $phpEx);
			$auth_admin = new auth_admin();

			// Only add the new permission if it does not already exist
			if (empty($auth_admin->acl_options['id']['u_masspm_group']))
			{
				$auth_admin->acl_add_option(array('global' => array('u_masspm_group')));

				// Now the tricky part, filling the permission
				$old_id = $auth_admin->acl_options['id']['u_masspm'];
				$new_id = $auth_admin->acl_options['id']['u_masspm_group'];

				$tables = array(ACL_GROUPS_TABLE, ACL_ROLES_DATA_TABLE, ACL_USERS_TABLE);

				foreach ($tables as $table)
				{
					$sql = 'SELECT *
						FROM ' . $table . '
						WHERE auth_option_id = ' . $old_id;
					$result = _sql($sql, $errored, $error_ary);

					$sql_ary = array();
					while ($row = $db->sql_fetchrow($result))
					{
						$row['auth_option_id'] = $new_id;
						$sql_ary[] = $row;
					}
					$db->sql_freeresult($result);

					if (sizeof($sql_ary))
					{
						$db->sql_multi_insert($table, $sql_ary);
					}
				}

				// Remove any old permission entries
				$auth_admin->acl_clear_prefetch();
			}

			/**
			* Do not resync post counts here. An admin may do this later from the ACP
			$start = 0;
			$step = ($config['num_posts']) ? (max((int) ($config['num_posts'] / 5), 20000)) : 20000;

			$sql = 'UPDATE ' . USERS_TABLE . ' SET user_posts = 0';
			_sql($sql, $errored, $error_ary);

			do
			{
				$sql = 'SELECT COUNT(post_id) AS num_posts, poster_id
					FROM ' . POSTS_TABLE . '
					WHERE post_id BETWEEN ' . ($start + 1) . ' AND ' . ($start + $step) . '
						AND post_postcount = 1 AND post_approved = 1
					GROUP BY poster_id';
				$result = _sql($sql, $errored, $error_ary);

				if ($row = $db->sql_fetchrow($result))
				{
					do
					{
						$sql = 'UPDATE ' . USERS_TABLE . " SET user_posts = user_posts + {$row['num_posts']} WHERE user_id = {$row['poster_id']}";
						_sql($sql, $errored, $error_ary);
					}
					while ($row = $db->sql_fetchrow($result));

					$start += $step;
				}
				else
				{
					$start = 0;
				}
				$db->sql_freeresult($result);
			}
			while ($start);
			*/

			$sql = 'UPDATE ' . MODULES_TABLE . '
				SET module_auth = \'acl_a_email && cfg_email_enable\'
				WHERE module_class = \'acp\'
					AND module_basename = \'email\'';
			_sql($sql, $errored, $error_ary);

			$no_updates = false;
		break;

		// Changes from 3.0.3-RC1 to 3.0.3
		case '3.0.3-RC1':
			$sql = 'UPDATE ' . LOG_TABLE . "
				SET log_operation = 'LOG_DELETE_TOPIC'
				WHERE log_operation = 'LOG_TOPIC_DELETED'";
			_sql($sql, $errored, $error_ary);

			$no_updates = false;
		break;

		// Changes from 3.0.3 to 3.0.4-RC1
		case '3.0.3':
			// Update the Custom Profile Fields based on previous settings to the new format
			$sql = 'SELECT field_id, field_required, field_show_on_reg, field_hide
					FROM ' . PROFILE_FIELDS_TABLE;
			$result = _sql($sql, $errored, $error_ary);

			while ($row = $db->sql_fetchrow($result))
			{
				$sql_ary = array(
					'field_required'	=> 0,
					'field_show_on_reg'	=> 0,
					'field_hide'		=> 0,
					'field_show_profile'=> 0,
				);

				if ($row['field_required'])
				{
					$sql_ary['field_required'] = $sql_ary['field_show_on_reg'] = $sql_ary['field_show_profile'] = 1;
				}
				else if ($row['field_show_on_reg'])
				{
					$sql_ary['field_show_on_reg'] = $sql_ary['field_show_profile'] = 1;
				}
				else if ($row['field_hide'])
				{
					// Only administrators and moderators can see this CPF, if the view is enabled, they can see it, otherwise just admins in the acp_users module
					$sql_ary['field_hide'] = 1;
				}
				else
				{
					// equivelant to "none", which is the "Display in user control panel" option
					$sql_ary['field_show_profile'] = 1;
				}

				_sql('UPDATE ' . PROFILE_FIELDS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_ary) . ' WHERE field_id = ' . $row['field_id'], $errored, $error_ary);
			}
			$no_updates = false;

		break;

		// Changes from 3.0.4-RC1 to 3.0.4
		case '3.0.4-RC1':
		break;

		// Changes from 3.0.4 to 3.0.5-RC1
		case '3.0.4':

			// Captcha config variables
			set_config('captcha_gd_wave', 0);
			set_config('captcha_gd_3d_noise', 1);
			set_config('captcha_gd_fonts', 1);

			set_config('confirm_refresh', 1);

			// Maximum number of keywords
			set_config('max_num_search_keywords', 10);

			// Remove static config var and put it back as dynamic variable
			$sql = 'UPDATE ' . CONFIG_TABLE . "
				SET is_dynamic = 1
				WHERE config_name = 'search_indexing_state'";
			_sql($sql, $errored, $error_ary);

			// Hash old MD5 passwords
			$sql = 'SELECT user_id, user_password
					FROM ' . USERS_TABLE . '
					WHERE user_pass_convert = 1';
			$result = _sql($sql, $errored, $error_ary);

			while ($row = $db->sql_fetchrow($result))
			{
				if (strlen($row['user_password']) == 32)
				{
					$sql_ary = array(
						'user_password'	=> phpbb_hash($row['user_password']),
					);

					_sql('UPDATE ' . USERS_TABLE . ' SET ' . $db->sql_build_array('UPDATE', $sql_ary) . ' WHERE user_id = ' . $row['user_id'], $errored, $error_ary);
				}
			}
			$db->sql_freeresult($result);

			// Adjust bot entry
			$sql = 'UPDATE ' . BOTS_TABLE . "
				SET bot_agent = 'ichiro/'
				WHERE bot_agent = 'ichiro/2'";
			_sql($sql, $errored, $error_ary);

			// Before we are able to add a unique key to auth_option, we need to remove duplicate entries

			// We get duplicate entries first
			$sql = 'SELECT auth_option
				FROM ' . ACL_OPTIONS_TABLE . '
				GROUP BY auth_option
				HAVING COUNT(*) >= 2';
			$result = $db->sql_query($sql);

			$auth_options = array();
			while ($row = $db->sql_fetchrow($result))
			{
				$auth_options[] = $row['auth_option'];
			}
			$db->sql_freeresult($result);

			// Remove specific auth options
			if (!empty($auth_options))
			{
				foreach ($auth_options as $option)
				{
					// Select auth_option_ids... the largest id will be preserved
					$sql = 'SELECT auth_option_id
						FROM ' . ACL_OPTIONS_TABLE . "
						WHERE auth_option = '" . $db->sql_escape($option) . "'
						ORDER BY auth_option_id DESC";
					// sql_query_limit not possible here, due to bug in postgresql layer
					$result = $db->sql_query($sql);

					// Skip first row, this is our original auth option we want to preserve
					$row = $db->sql_fetchrow($result);

					while ($row = $db->sql_fetchrow($result))
					{
						// Ok, remove this auth option...
						_sql('DELETE FROM ' . ACL_OPTIONS_TABLE . ' WHERE auth_option_id = ' . $row['auth_option_id'], $errored, $error_ary);
						_sql('DELETE FROM ' . ACL_ROLES_DATA_TABLE . ' WHERE auth_option_id = ' . $row['auth_option_id'], $errored, $error_ary);
						_sql('DELETE FROM ' . ACL_GROUPS_TABLE . ' WHERE auth_option_id = ' . $row['auth_option_id'], $errored, $error_ary);
						_sql('DELETE FROM ' . ACL_USERS_TABLE . ' WHERE auth_option_id = ' . $row['auth_option_id'], $errored, $error_ary);
					}
					$db->sql_freeresult($result);
				}
			}

			// Now make auth_option UNIQUE, by dropping the old index and adding a UNIQUE one.
			$changes = array(
				'drop_keys'			=> array(
					ACL_OPTIONS_TABLE		=> array('auth_option'),
				),
				'add_unique_index'	=> array(
					ACL_OPTIONS_TABLE		=> array(
						'auth_option'		=> array('auth_option'),
					),
				),
			);

			global $db_tools;

			$statements = $db_tools->perform_schema_changes($changes);

			foreach ($statements as $sql)
			{
				_sql($sql, $errored, $error_ary);
			}

			$no_updates = false;

		break;

		// No changes from 3.0.5-RC1 to 3.0.5
		case '3.0.5-RC1':
		break;
	}
}


/**
* Database Tools for handling cross-db actions such as altering columns, etc.
* Currently not supported is returning SQL for creating tables.
*
* @package dbal
*/
class updater_db_tools
{
	/**
	* Current sql layer
	*/
	var $sql_layer = '';

	/**
	* @var object DB object
	*/
	var $db = NULL;

	/**
	* The Column types for every database we support
	* @var array
	*/
	var $dbms_type_map = array(
		'mysql_41'	=> array(
			'INT:'		=> 'int(%d)',
			'BINT'		=> 'bigint(20)',
			'UINT'		=> 'mediumint(8) UNSIGNED',
			'UINT:'		=> 'int(%d) UNSIGNED',
			'TINT:'		=> 'tinyint(%d)',
			'USINT'		=> 'smallint(4) UNSIGNED',
			'BOOL'		=> 'tinyint(1) UNSIGNED',
			'VCHAR'		=> 'varchar(255)',
			'VCHAR:'	=> 'varchar(%d)',
			'CHAR:'		=> 'char(%d)',
			'XSTEXT'	=> 'text',
			'XSTEXT_UNI'=> 'varchar(100)',
			'STEXT'		=> 'text',
			'STEXT_UNI'	=> 'varchar(255)',
			'TEXT'		=> 'text',
			'TEXT_UNI'	=> 'text',
			'MTEXT'		=> 'mediumtext',
			'MTEXT_UNI'	=> 'mediumtext',
			'TIMESTAMP'	=> 'int(11) UNSIGNED',
			'DECIMAL'	=> 'decimal(5,2)',
			'DECIMAL:'	=> 'decimal(%d,2)',
			'PDECIMAL'	=> 'decimal(6,3)',
			'PDECIMAL:'	=> 'decimal(%d,3)',
			'VCHAR_UNI'	=> 'varchar(255)',
			'VCHAR_UNI:'=> 'varchar(%d)',
			'VCHAR_CI'	=> 'varchar(255)',
			'VARBINARY'	=> 'varbinary(255)',
		),

		'mysql_40'	=> array(
			'INT:'		=> 'int(%d)',
			'BINT'		=> 'bigint(20)',
			'UINT'		=> 'mediumint(8) UNSIGNED',
			'UINT:'		=> 'int(%d) UNSIGNED',
			'TINT:'		=> 'tinyint(%d)',
			'USINT'		=> 'smallint(4) UNSIGNED',
			'BOOL'		=> 'tinyint(1) UNSIGNED',
			'VCHAR'		=> 'varbinary(255)',
			'VCHAR:'	=> 'varbinary(%d)',
			'CHAR:'		=> 'binary(%d)',
			'XSTEXT'	=> 'blob',
			'XSTEXT_UNI'=> 'blob',
			'STEXT'		=> 'blob',
			'STEXT_UNI'	=> 'blob',
			'TEXT'		=> 'blob',
			'TEXT_UNI'	=> 'blob',
			'MTEXT'		=> 'mediumblob',
			'MTEXT_UNI'	=> 'mediumblob',
			'TIMESTAMP'	=> 'int(11) UNSIGNED',
			'DECIMAL'	=> 'decimal(5,2)',
			'DECIMAL:'	=> 'decimal(%d,2)',
			'PDECIMAL'	=> 'decimal(6,3)',
			'PDECIMAL:'	=> 'decimal(%d,3)',
			'VCHAR_UNI'	=> 'blob',
			'VCHAR_UNI:'=> array('varbinary(%d)', 'limit' => array('mult', 3, 255, 'blob')),
			'VCHAR_CI'	=> 'blob',
			'VARBINARY'	=> 'varbinary(255)',
		),

		'firebird'	=> array(
			'INT:'		=> 'INTEGER',
			'BINT'		=> 'DOUBLE PRECISION',
			'UINT'		=> 'INTEGER',
			'UINT:'		=> 'INTEGER',
			'TINT:'		=> 'INTEGER',
			'USINT'		=> 'INTEGER',
			'BOOL'		=> 'INTEGER',
			'VCHAR'		=> 'VARCHAR(255) CHARACTER SET NONE',
			'VCHAR:'	=> 'VARCHAR(%d) CHARACTER SET NONE',
			'CHAR:'		=> 'CHAR(%d) CHARACTER SET NONE',
			'XSTEXT'	=> 'BLOB SUB_TYPE TEXT CHARACTER SET NONE',
			'STEXT'		=> 'BLOB SUB_TYPE TEXT CHARACTER SET NONE',
			'TEXT'		=> 'BLOB SUB_TYPE TEXT CHARACTER SET NONE',
			'MTEXT'		=> 'BLOB SUB_TYPE TEXT CHARACTER SET NONE',
			'XSTEXT_UNI'=> 'VARCHAR(100) CHARACTER SET UTF8',
			'STEXT_UNI'	=> 'VARCHAR(255) CHARACTER SET UTF8',
			'TEXT_UNI'	=> 'BLOB SUB_TYPE TEXT CHARACTER SET UTF8',
			'MTEXT_UNI'	=> 'BLOB SUB_TYPE TEXT CHARACTER SET UTF8',
			'TIMESTAMP'	=> 'INTEGER',
			'DECIMAL'	=> 'DOUBLE PRECISION',
			'DECIMAL:'	=> 'DOUBLE PRECISION',
			'PDECIMAL'	=> 'DOUBLE PRECISION',
			'PDECIMAL:'	=> 'DOUBLE PRECISION',
			'VCHAR_UNI'	=> 'VARCHAR(255) CHARACTER SET UTF8',
			'VCHAR_UNI:'=> 'VARCHAR(%d) CHARACTER SET UTF8',
			'VCHAR_CI'	=> 'VARCHAR(255) CHARACTER SET UTF8',
			'VARBINARY'	=> 'CHAR(255) CHARACTER SET NONE',
		),

		'mssql'		=> array(
			'INT:'		=> '[int]',
			'BINT'		=> '[float]',
			'UINT'		=> '[int]',
			'UINT:'		=> '[int]',
			'TINT:'		=> '[int]',
			'USINT'		=> '[int]',
			'BOOL'		=> '[int]',
			'VCHAR'		=> '[varchar] (255)',
			'VCHAR:'	=> '[varchar] (%d)',
			'CHAR:'		=> '[char] (%d)',
			'XSTEXT'	=> '[varchar] (1000)',
			'STEXT'		=> '[varchar] (3000)',
			'TEXT'		=> '[varchar] (8000)',
			'MTEXT'		=> '[text]',
			'XSTEXT_UNI'=> '[varchar] (100)',
			'STEXT_UNI'	=> '[varchar] (255)',
			'TEXT_UNI'	=> '[varchar] (4000)',
			'MTEXT_UNI'	=> '[text]',
			'TIMESTAMP'	=> '[int]',
			'DECIMAL'	=> '[float]',
			'DECIMAL:'	=> '[float]',
			'PDECIMAL'	=> '[float]',
			'PDECIMAL:'	=> '[float]',
			'VCHAR_UNI'	=> '[varchar] (255)',
			'VCHAR_UNI:'=> '[varchar] (%d)',
			'VCHAR_CI'	=> '[varchar] (255)',
			'VARBINARY'	=> '[varchar] (255)',
		),

		'oracle'	=> array(
			'INT:'		=> 'number(%d)',
			'BINT'		=> 'number(20)',
			'UINT'		=> 'number(8)',
			'UINT:'		=> 'number(%d)',
			'TINT:'		=> 'number(%d)',
			'USINT'		=> 'number(4)',
			'BOOL'		=> 'number(1)',
			'VCHAR'		=> 'varchar2(255)',
			'VCHAR:'	=> 'varchar2(%d)',
			'CHAR:'		=> 'char(%d)',
			'XSTEXT'	=> 'varchar2(1000)',
			'STEXT'		=> 'varchar2(3000)',
			'TEXT'		=> 'clob',
			'MTEXT'		=> 'clob',
			'XSTEXT_UNI'=> 'varchar2(300)',
			'STEXT_UNI'	=> 'varchar2(765)',
			'TEXT_UNI'	=> 'clob',
			'MTEXT_UNI'	=> 'clob',
			'TIMESTAMP'	=> 'number(11)',
			'DECIMAL'	=> 'number(5, 2)',
			'DECIMAL:'	=> 'number(%d, 2)',
			'PDECIMAL'	=> 'number(6, 3)',
			'PDECIMAL:'	=> 'number(%d, 3)',
			'VCHAR_UNI'	=> 'varchar2(765)',
			'VCHAR_UNI:'=> array('varchar2(%d)', 'limit' => array('mult', 3, 765, 'clob')),
			'VCHAR_CI'	=> 'varchar2(255)',
			'VARBINARY'	=> 'raw(255)',
		),

		'sqlite'	=> array(
			'INT:'		=> 'int(%d)',
			'BINT'		=> 'bigint(20)',
			'UINT'		=> 'INTEGER UNSIGNED', //'mediumint(8) UNSIGNED',
			'UINT:'		=> 'INTEGER UNSIGNED', // 'int(%d) UNSIGNED',
			'TINT:'		=> 'tinyint(%d)',
			'USINT'		=> 'INTEGER UNSIGNED', //'mediumint(4) UNSIGNED',
			'BOOL'		=> 'INTEGER UNSIGNED', //'tinyint(1) UNSIGNED',
			'VCHAR'		=> 'varchar(255)',
			'VCHAR:'	=> 'varchar(%d)',
			'CHAR:'		=> 'char(%d)',
			'XSTEXT'	=> 'text(65535)',
			'STEXT'		=> 'text(65535)',
			'TEXT'		=> 'text(65535)',
			'MTEXT'		=> 'mediumtext(16777215)',
			'XSTEXT_UNI'=> 'text(65535)',
			'STEXT_UNI'	=> 'text(65535)',
			'TEXT_UNI'	=> 'text(65535)',
			'MTEXT_UNI'	=> 'mediumtext(16777215)',
			'TIMESTAMP'	=> 'INTEGER UNSIGNED', //'int(11) UNSIGNED',
			'DECIMAL'	=> 'decimal(5,2)',
			'DECIMAL:'	=> 'decimal(%d,2)',
			'PDECIMAL'	=> 'decimal(6,3)',
			'PDECIMAL:'	=> 'decimal(%d,3)',
			'VCHAR_UNI'	=> 'varchar(255)',
			'VCHAR_UNI:'=> 'varchar(%d)',
			'VCHAR_CI'	=> 'varchar(255)',
			'VARBINARY'	=> 'blob',
		),

		'postgres'	=> array(
			'INT:'		=> 'INT4',
			'BINT'		=> 'INT8',
			'UINT'		=> 'INT4', // unsigned
			'UINT:'		=> 'INT4', // unsigned
			'USINT'		=> 'INT2', // unsigned
			'BOOL'		=> 'INT2', // unsigned
			'TINT:'		=> 'INT2',
			'VCHAR'		=> 'varchar(255)',
			'VCHAR:'	=> 'varchar(%d)',
			'CHAR:'		=> 'char(%d)',
			'XSTEXT'	=> 'varchar(1000)',
			'STEXT'		=> 'varchar(3000)',
			'TEXT'		=> 'varchar(8000)',
			'MTEXT'		=> 'TEXT',
			'XSTEXT_UNI'=> 'varchar(100)',
			'STEXT_UNI'	=> 'varchar(255)',
			'TEXT_UNI'	=> 'varchar(4000)',
			'MTEXT_UNI'	=> 'TEXT',
			'TIMESTAMP'	=> 'INT4', // unsigned
			'DECIMAL'	=> 'decimal(5,2)',
			'DECIMAL:'	=> 'decimal(%d,2)',
			'PDECIMAL'	=> 'decimal(6,3)',
			'PDECIMAL:'	=> 'decimal(%d,3)',
			'VCHAR_UNI'	=> 'varchar(255)',
			'VCHAR_UNI:'=> 'varchar(%d)',
			'VCHAR_CI'	=> 'varchar_ci',
			'VARBINARY'	=> 'bytea',
		),
	);

	/**
	* A list of types being unsigned for better reference in some db's
	* @var array
	*/
	var $unsigned_types = array('UINT', 'UINT:', 'USINT', 'BOOL', 'TIMESTAMP');

	/**
	* A list of supported DBMS. We change this class to support more DBMS, the DBMS itself only need to follow some rules.
	* @var array
	*/
	var $supported_dbms = array('firebird', 'mssql', 'mysql_40', 'mysql_41', 'oracle', 'postgres', 'sqlite');

	/**
	* This is set to true if user only wants to return the 'to-be-executed' SQL statement(s) (as an array).
	* This mode has no effect on some methods (inserting of data for example). This is expressed within the methods command.
	*/
	var $return_statements = false;

	/**
	* Constructor. Set DB Object and set {@link $return_statements return_statements}.
	*
	* @param phpbb_dbal	$db					DBAL object
	* @param bool		$return_statements	True if only statements should be returned and no SQL being executed
	*/
	function updater_db_tools(&$db, $return_statements = false)
	{
		$this->db = $db;
		$this->return_statements = $return_statements;

		// Determine mapping database type
		switch ($this->db->sql_layer)
		{
			case 'mysql':
				$this->sql_layer = 'mysql_40';
			break;

			case 'mysql4':
				if (version_compare($this->db->sql_server_info(true), '4.1.3', '>='))
				{
					$this->sql_layer = 'mysql_41';
				}
				else
				{
					$this->sql_layer = 'mysql_40';
				}
			break;

			case 'mysqli':
				$this->sql_layer = 'mysql_41';
			break;

			case 'mssql':
			case 'mssql_odbc':
				$this->sql_layer = 'mssql';
			break;

			default:
				$this->sql_layer = $this->db->sql_layer;
			break;
		}
	}

	/**
	* Handle passed database update array.
	* Expected structure...
	* Key being one of the following
	*	change_columns: Column changes (only type, not name)
	*	add_columns: Add columns to a table
	*	drop_keys: Dropping keys
	*	drop_columns: Removing/Dropping columns
	*	add_primary_keys: adding primary keys
	*	add_unique_index: adding an unique index
	*	add_index: adding an index
	*
	* The values are in this format:
	*		{TABLE NAME}		=> array(
	*			{COLUMN NAME}		=> array({COLUMN TYPE}, {DEFAULT VALUE}, {OPTIONAL VARIABLES}),
	*			{KEY/INDEX NAME}	=> array({COLUMN NAMES}),
	*		)
	*
	* For more information have a look at /develop/create_schema_files.php (only available through SVN)
	*/
	function perform_schema_changes($schema_changes)
	{
		if (empty($schema_changes))
		{
			return;
		}

		$statements = array();

		// Change columns?
		if (!empty($schema_changes['change_columns']))
		{
			foreach ($schema_changes['change_columns'] as $table => $columns)
			{
				foreach ($columns as $column_name => $column_data)
				{
					// If the column exists we change it, else we add it ;)
					if ($this->sql_column_exists($table, $column_name))
					{
						$result = $this->sql_column_change($table, $column_name, $column_data);
					}
					else
					{
						$result = $this->sql_column_add($table, $column_name, $column_data);
					}

					if ($this->return_statements)
					{
						$statements = array_merge($statements, $result);
					}
				}
			}
		}

		// Add columns?
		if (!empty($schema_changes['add_columns']))
		{
			foreach ($schema_changes['add_columns'] as $table => $columns)
			{
				foreach ($columns as $column_name => $column_data)
				{
					// Only add the column if it does not exist yet, else change it (to be consistent)
					if ($this->sql_column_exists($table, $column_name))
					{
						$result = $this->sql_column_change($table, $column_name, $column_data);
					}
					else
					{
						$result = $this->sql_column_add($table, $column_name, $column_data);
					}

					if ($this->return_statements)
					{
						$statements = array_merge($statements, $result);
					}
				}
			}
		}

		// Remove keys?
		if (!empty($schema_changes['drop_keys']))
		{
			foreach ($schema_changes['drop_keys'] as $table => $indexes)
			{
				foreach ($indexes as $index_name)
				{
					$result = $this->sql_index_drop($table, $index_name);

					if ($this->return_statements)
					{
						$statements = array_merge($statements, $result);
					}
				}
			}
		}

		// Drop columns?
		if (!empty($schema_changes['drop_columns']))
		{
			foreach ($schema_changes['drop_columns'] as $table => $columns)
			{
				foreach ($columns as $column)
				{
					// Only remove the column if it exists...
					if ($this->sql_column_exists($table, $column))
					{
						$result = $this->sql_column_remove($table, $column);

						if ($this->return_statements)
						{
							$statements = array_merge($statements, $result);
						}
					}
				}
			}
		}

		// Add primary keys?
		if (!empty($schema_changes['add_primary_keys']))
		{
			foreach ($schema_changes['add_primary_keys'] as $table => $columns)
			{
				$result = $this->sql_create_primary_key($table, $columns);

				if ($this->return_statements)
				{
					$statements = array_merge($statements, $result);
				}
			}
		}

		// Add unqiue indexes?
		if (!empty($schema_changes['add_unique_index']))
		{
			foreach ($schema_changes['add_unique_index'] as $table => $index_array)
			{
				foreach ($index_array as $index_name => $column)
				{
					$result = $this->sql_create_unique_index($table, $index_name, $column);

					if ($this->return_statements)
					{
						$statements = array_merge($statements, $result);
					}
				}
			}
		}

		// Add indexes?
		if (!empty($schema_changes['add_index']))
		{
			foreach ($schema_changes['add_index'] as $table => $index_array)
			{
				foreach ($index_array as $index_name => $column)
				{
					$result = $this->sql_create_index($table, $index_name, $column);

					if ($this->return_statements)
					{
						$statements = array_merge($statements, $result);
					}
				}
			}
		}

		if ($this->return_statements)
		{
			return $statements;
		}
	}

	/**
	* Check if a specified column exist
	*
	* @param string	$table			Table to check the column at
	* @param string	$column_name	The column to check
	*
	* @return bool True if column exists, else false
	*/
	function sql_column_exists($table, $column_name)
	{
		switch ($this->sql_layer)
		{
			case 'mysql_40':
			case 'mysql_41':

				$sql = "SHOW COLUMNS FROM $table";
				$result = $this->db->sql_query($sql);

				while ($row = $this->db->sql_fetchrow($result))
				{
					// lower case just in case
					if (strtolower($row['Field']) == $column_name)
					{
						$this->db->sql_freeresult($result);
						return true;
					}
				}
				$this->db->sql_freeresult($result);
				return false;
			break;

			// PostgreSQL has a way of doing this in a much simpler way but would
			// not allow us to support all versions of PostgreSQL
			case 'postgres':
				$sql = "SELECT a.attname
					FROM pg_class c, pg_attribute a
					WHERE c.relname = '{$table}'
						AND a.attnum > 0
						AND a.attrelid = c.oid";
				$result = $this->db->sql_query($sql);
				while ($row = $this->db->sql_fetchrow($result))
				{
					// lower case just in case
					if (strtolower($row['attname']) == $column_name)
					{
						$this->db->sql_freeresult($result);
						return true;
					}
				}
				$this->db->sql_freeresult($result);

				return false;
			break;

			// same deal with PostgreSQL, we must perform more complex operations than
			// we technically could
			case 'mssql':
				$sql = "SELECT c.name
					FROM syscolumns c
					LEFT JOIN sysobjects o ON c.id = o.id
					WHERE o.name = '{$table}'";
				$result = $this->db->sql_query($sql);
				while ($row = $this->db->sql_fetchrow($result))
				{
					// lower case just in case
					if (strtolower($row['name']) == $column_name)
					{
						$this->db->sql_freeresult($result);
						return true;
					}
				}
				$this->db->sql_freeresult($result);
				return false;
			break;

			case 'oracle':
				$sql = "SELECT column_name
					FROM user_tab_columns
					WHERE table_name = '{$table}'";
				$result = $this->db->sql_query($sql);
				while ($row = $this->db->sql_fetchrow($result))
				{
					// lower case just in case
					if (strtolower($row['column_name']) == $column_name)
					{
						$this->db->sql_freeresult($result);
						return true;
					}
				}
				$this->db->sql_freeresult($result);
				return false;
			break;

			case 'firebird':
				$sql = "SELECT RDB\$FIELD_NAME as FNAME
					FROM RDB\$RELATION_FIELDS
					WHERE RDB\$RELATION_NAME = '{$table}'";
				$result = $this->db->sql_query($sql);
				while ($row = $this->db->sql_fetchrow($result))
				{
					// lower case just in case
					if (strtolower($row['fname']) == $column_name)
					{
						$this->db->sql_freeresult($result);
						return true;
					}
				}
				$this->db->sql_freeresult($result);
				return false;
			break;

			// ugh, SQLite
			case 'sqlite':
				$sql = "SELECT sql
					FROM sqlite_master
					WHERE type = 'table'
						AND name = '{$table}'";
				$result = $this->db->sql_query($sql);

				if (!$result)
				{
					return false;
				}

				$row = $this->db->sql_fetchrow($result);
				$this->db->sql_freeresult($result);

				preg_match('#\((.*)\)#s', $row['sql'], $matches);

				$cols = trim($matches[1]);
				$col_array = preg_split('/,(?![\s\w]+\))/m', $cols);

				foreach ($col_array as $declaration)
				{
					$entities = preg_split('#\s+#', trim($declaration));
					if ($entities[0] == 'PRIMARY')
					{
						continue;
					}

					if (strtolower($entities[0]) == $column_name)
					{
						return true;
					}
				}
				return false;
			break;
		}
	}

	/**
	* Private method for performing sql statements (either execute them or return them)
	* @access private
	*/
	function _sql_run_sql($statements)
	{
		if ($this->return_statements)
		{
			return $statements;
		}

		// We could add error handling here...
		foreach ($statements as $sql)
		{
			if ($sql === 'begin')
			{
				$this->db->sql_transaction('begin');
			}
			else if ($sql === 'commit')
			{
				$this->db->sql_transaction('commit');
			}
			else
			{
				$this->db->sql_query($sql);
			}
		}

		return true;
	}

	/**
	* Function to prepare some column information for better usage
	* @access private
	*/
	function sql_prepare_column_data($table_name, $column_name, $column_data)
	{
		// Get type
		if (strpos($column_data[0], ':') !== false)
		{
			list($orig_column_type, $column_length) = explode(':', $column_data[0]);
			if (!is_array($this->dbms_type_map[$this->sql_layer][$orig_column_type . ':']))
			{
				$column_type = sprintf($this->dbms_type_map[$this->sql_layer][$orig_column_type . ':'], $column_length);
			}
			else
			{
				if (isset($this->dbms_type_map[$this->sql_layer][$orig_column_type . ':']['rule']))
				{
					switch ($this->dbms_type_map[$this->sql_layer][$orig_column_type . ':']['rule'][0])
					{
						case 'div':
							$column_length /= $this->dbms_type_map[$this->sql_layer][$orig_column_type . ':']['rule'][1];
							$column_length = ceil($column_length);
							$column_type = sprintf($this->dbms_type_map[$this->sql_layer][$orig_column_type . ':'][0], $column_length);
						break;
					}
				}

				if (isset($this->dbms_type_map[$this->sql_layer][$orig_column_type . ':']['limit']))
				{
					switch ($this->dbms_type_map[$this->sql_layer][$orig_column_type . ':']['limit'][0])
					{
						case 'mult':
							$column_length *= $this->dbms_type_map[$this->sql_layer][$orig_column_type . ':']['limit'][1];
							if ($column_length > $this->dbms_type_map[$this->sql_layer][$orig_column_type . ':']['limit'][2])
							{
								$column_type = $this->dbms_type_map[$this->sql_layer][$orig_column_type . ':']['limit'][3];
							}
							else
							{
								$column_type = sprintf($this->dbms_type_map[$this->sql_layer][$orig_column_type . ':'][0], $column_length);
							}
						break;
					}
				}
			}
			$orig_column_type .= ':';
		}
		else
		{
			$orig_column_type = $column_data[0];
			$column_type = $this->dbms_type_map[$this->sql_layer][$column_data[0]];
		}

		// Adjust default value if db-dependant specified
		if (is_array($column_data[1]))
		{
			$column_data[1] = (isset($column_data[1][$this->sql_layer])) ? $column_data[1][$this->sql_layer] : $column_data[1]['default'];
		}

		$sql = '';

		$return_array = array();

		switch ($this->sql_layer)
		{
			case 'firebird':
				$sql .= " {$column_type} ";

				if (!is_null($column_data[1]))
				{
					$sql .= 'DEFAULT ' . ((is_numeric($column_data[1])) ? $column_data[1] : "'{$column_data[1]}'") . ' ';
				}

				$sql .= 'NOT NULL';

				// This is a UNICODE column and thus should be given it's fair share
				if (preg_match('/^X?STEXT_UNI|VCHAR_(CI|UNI:?)/', $column_data[0]))
				{
					$sql .= ' COLLATE UNICODE';
				}

				$return_array['auto_increment'] = false;
				if (isset($column_data[2]) && $column_data[2] == 'auto_increment')
				{
					$return_array['auto_increment'] = true;
				}

			break;

			case 'mssql':
				$sql .= " {$column_type} ";
				$sql_default = " {$column_type} ";

				// For adding columns we need the default definition
				if (!is_null($column_data[1]))
				{
					// For hexadecimal values do not use single quotes
					if (strpos($column_data[1], '0x') === 0)
					{
						$sql_default .= 'DEFAULT (' . $column_data[1] . ') ';
					}
					else
					{
						$sql_default .= 'DEFAULT (' . ((is_numeric($column_data[1])) ? $column_data[1] : "'{$column_data[1]}'") . ') ';
					}
				}

				if (isset($column_data[2]) && $column_data[2] == 'auto_increment')
				{
//					$sql .= 'IDENTITY (1, 1) ';
					$sql_default .= 'IDENTITY (1, 1) ';
				}

				$return_array['textimage'] = $column_type === '[text]';

				$sql .= 'NOT NULL';
				$sql_default .= 'NOT NULL';

				$return_array['column_type_sql_default'] = $sql_default;

			break;

			case 'mysql_40':
			case 'mysql_41':
				$sql .= " {$column_type} ";

				// For hexadecimal values do not use single quotes
				if (!is_null($column_data[1]) && substr($column_type, -4) !== 'text' && substr($column_type, -4) !== 'blob')
				{
					$sql .= (strpos($column_data[1], '0x') === 0) ? "DEFAULT {$column_data[1]} " : "DEFAULT '{$column_data[1]}' ";
				}
				$sql .= 'NOT NULL';

				if (isset($column_data[2]))
				{
					if ($column_data[2] == 'auto_increment')
					{
						$sql .= ' auto_increment';
					}
					else if ($this->sql_layer === 'mysql_41' && $column_data[2] == 'true_sort')
					{
						$sql .= ' COLLATE utf8_unicode_ci';
					}
				}

			break;

			case 'oracle':
				$sql .= " {$column_type} ";
				$sql .= (!is_null($column_data[1])) ? "DEFAULT '{$column_data[1]}' " : '';

				// In Oracle empty strings ('') are treated as NULL.
				// Therefore in oracle we allow NULL's for all DEFAULT '' entries
				// Oracle does not like setting NOT NULL on a column that is already NOT NULL (this happens only on number fields)
				if (!preg_match('/number/i', $column_type))
				{
					$sql .= ($column_data[1] === '') ? '' : 'NOT NULL';
				}

				$return_array['auto_increment'] = false;
				if (isset($column_data[2]) && $column_data[2] == 'auto_increment')
				{
					$return_array['auto_increment'] = true;
				}

			break;

			case 'postgres':
				$return_array['column_type'] = $column_type;

				$sql .= " {$column_type} ";

				$return_array['auto_increment'] = false;
				if (isset($column_data[2]) && $column_data[2] == 'auto_increment')
				{
					$default_val = "nextval('{$table_name}_seq')";
					$return_array['auto_increment'] = true;
				}
				else if (!is_null($column_data[1]))
				{
					$default_val = "'" . $column_data[1] . "'";
					$return_array['null'] = 'NOT NULL';
					$sql .= 'NOT NULL ';
				}

				$return_array['default'] = $default_val;

				$sql .= "DEFAULT {$default_val}";

				// Unsigned? Then add a CHECK contraint
				if (in_array($orig_column_type, $this->unsigned_types))
				{
					$return_array['constraint'] = "CHECK ({$column_name} >= 0)";
					$sql .= " CHECK ({$column_name} >= 0)";
				}

			break;

			case 'sqlite':
				$return_array['primary_key_set'] = false;
				if (isset($column_data[2]) && $column_data[2] == 'auto_increment')
				{
					$sql .= ' INTEGER PRIMARY KEY';
					$return_array['primary_key_set'] = true;
				}
				else
				{
					$sql .= ' ' . $column_type;
				}

				$sql .= ' NOT NULL ';
				$sql .= (!is_null($column_data[1])) ? "DEFAULT '{$column_data[1]}'" : '';

			break;
		}

		$return_array['column_type_sql'] = $sql;

		return $return_array;
	}

	/**
	* Add new column
	*/
	function sql_column_add($table_name, $column_name, $column_data)
	{
		$column_data = $this->sql_prepare_column_data($table_name, $column_name, $column_data);
		$statements = array();

		switch ($this->sql_layer)
		{
			case 'firebird':
				$statements[] = 'ALTER TABLE ' . $table_name . ' ADD "' . strtoupper($column_name) . '" ' . $column_data['column_type_sql'];
			break;

			case 'mssql':
				$statements[] = 'ALTER TABLE [' . $table_name . '] ADD [' . $column_name . '] ' . $column_data['column_type_sql_default'];
			break;

			case 'mysql_40':
			case 'mysql_41':
				$statements[] = 'ALTER TABLE `' . $table_name . '` ADD COLUMN `' . $column_name . '` ' . $column_data['column_type_sql'];
			break;

			case 'oracle':
				$statements[] = 'ALTER TABLE ' . $table_name . ' ADD ' . $column_name . ' ' . $column_data['column_type_sql'];
			break;

			case 'postgres':
				$statements[] = 'ALTER TABLE ' . $table_name . ' ADD COLUMN "' . $column_name . '" ' . $column_data['column_type_sql'];
			break;

			case 'sqlite':
				if (version_compare(sqlite_libversion(), '3.0') == -1)
				{
					$sql = "SELECT sql
						FROM sqlite_master
						WHERE type = 'table'
							AND name = '{$table_name}'
						ORDER BY type DESC, name;";
					$result = $this->db->sql_query($sql);

					if (!$result)
					{
						break;
					}

					$row = $this->db->sql_fetchrow($result);
					$this->db->sql_freeresult($result);

					$statements[] = 'begin';

					// Create a backup table and populate it, destroy the existing one
					$statements[] = preg_replace('#CREATE\s+TABLE\s+"?' . $table_name . '"?#i', 'CREATE TEMPORARY TABLE ' . $table_name . '_temp', $row['sql']);
					$statements[] = 'INSERT INTO ' . $table_name . '_temp SELECT * FROM ' . $table_name;
					$statements[] = 'DROP TABLE ' . $table_name;

					preg_match('#\((.*)\)#s', $row['sql'], $matches);

					$new_table_cols = trim($matches[1]);
					$old_table_cols = preg_split('/,(?![\s\w]+\))/m', $new_table_cols);
					$column_list = array();

					foreach ($old_table_cols as $declaration)
					{
						$entities = preg_split('#\s+#', trim($declaration));
						if ($entities[0] == 'PRIMARY')
						{
							continue;
						}
						$column_list[] = $entities[0];
					}

					$columns = implode(',', $column_list);

					$new_table_cols = $column_name . ' ' . $column_data['column_type_sql'] . ',' . $new_table_cols;

					// create a new table and fill it up. destroy the temp one
					$statements[] = 'CREATE TABLE ' . $table_name . ' (' . $new_table_cols . ');';
					$statements[] = 'INSERT INTO ' . $table_name . ' (' . $columns . ') SELECT ' . $columns . ' FROM ' . $table_name . '_temp;';
					$statements[] = 'DROP TABLE ' . $table_name . '_temp';

					$statements[] = 'commit';
				}
				else
				{
					$statements[] = 'ALTER TABLE ' . $table_name . ' ADD ' . $column_name . ' [' . $column_data['column_type_sql'] . ']';
				}
			break;
		}

		return $this->_sql_run_sql($statements);
	}

	/**
	* Drop column
	*/
	function sql_column_remove($table_name, $column_name)
	{
		$statements = array();

		switch ($this->sql_layer)
		{
			case 'firebird':
				$statements[] = 'ALTER TABLE ' . $table_name . ' DROP "' . strtoupper($column_name) . '"';
			break;

			case 'mssql':
				$statements[] = 'ALTER TABLE [' . $table_name . '] DROP COLUMN [' . $column_name . ']';
			break;

			case 'mysql_40':
			case 'mysql_41':
				$statements[] = 'ALTER TABLE `' . $table_name . '` DROP COLUMN `' . $column_name . '`';
			break;

			case 'oracle':
				$statements[] = 'ALTER TABLE ' . $table_name . ' DROP ' . $column_name;
			break;

			case 'postgres':
				$statements[] = 'ALTER TABLE ' . $table_name . ' DROP COLUMN "' . $column_name . '"';
			break;

			case 'sqlite':
				if (version_compare(sqlite_libversion(), '3.0') == -1)
				{
					$sql = "SELECT sql
						FROM sqlite_master
						WHERE type = 'table'
							AND name = '{$table_name}'
						ORDER BY type DESC, name;";
					$result = $this->db->sql_query($sql);

					if (!$result)
					{
						break;
					}

					$row = $this->db->sql_fetchrow($result);
					$this->db->sql_freeresult($result);

					$statements[] = 'begin';

					// Create a backup table and populate it, destroy the existing one
					$statements[] = preg_replace('#CREATE\s+TABLE\s+"?' . $table_name . '"?#i', 'CREATE TEMPORARY TABLE ' . $table_name . '_temp', $row['sql']);
					$statements[] = 'INSERT INTO ' . $table_name . '_temp SELECT * FROM ' . $table_name;
					$statements[] = 'DROP TABLE ' . $table_name;

					preg_match('#\((.*)\)#s', $row['sql'], $matches);

					$new_table_cols = trim($matches[1]);
					$old_table_cols = preg_split('/,(?![\s\w]+\))/m', $new_table_cols);
					$column_list = array();

					foreach ($old_table_cols as $declaration)
					{
						$entities = preg_split('#\s+#', trim($declaration));
						if ($entities[0] == 'PRIMARY' || $entities[0] === $column_name)
						{
							continue;
						}
						$column_list[] = $entities[0];
					}

					$columns = implode(',', $column_list);

					$new_table_cols = $new_table_cols = preg_replace('/' . $column_name . '[^,]+(?:,|$)/m', '', $new_table_cols);

					// create a new table and fill it up. destroy the temp one
					$statements[] = 'CREATE TABLE ' . $table_name . ' (' . $new_table_cols . ');';
					$statements[] = 'INSERT INTO ' . $table_name . ' (' . $columns . ') SELECT ' . $columns . ' FROM ' . $table_name . '_temp;';
					$statements[] = 'DROP TABLE ' . $table_name . '_temp';

					$statements[] = 'commit';
				}
				else
				{
					$statements[] = 'ALTER TABLE ' . $table_name . ' DROP COLUMN ' . $column_name;
				}
			break;
		}

		return $this->_sql_run_sql($statements);
	}

	/**
	* Drop Index
	*/
	function sql_index_drop($table_name, $index_name)
	{
		$statements = array();

		switch ($this->sql_layer)
		{
			case 'mssql':
				$statements[] = 'DROP INDEX ' . $table_name . '.' . $index_name;
			break;

			case 'mysql_40':
			case 'mysql_41':
				$statements[] = 'DROP INDEX ' . $index_name . ' ON ' . $table_name;
			break;

			case 'firebird':
			case 'oracle':
			case 'postgres':
			case 'sqlite':
				$statements[] = 'DROP INDEX ' . $table_name . '_' . $index_name;
			break;
		}

		return $this->_sql_run_sql($statements);
	}

	/**
	* Add primary key
	*/
	function sql_create_primary_key($table_name, $column)
	{
		$statements = array();

		switch ($this->sql_layer)
		{
			case 'firebird':
			case 'postgres':
			case 'mysql_40':
			case 'mysql_41':
				$statements[] = 'ALTER TABLE ' . $table_name . ' ADD PRIMARY KEY (' . implode(', ', $column) . ')';
			break;

			case 'mssql':
				$sql = "ALTER TABLE [{$table_name}] WITH NOCHECK ADD ";
				$sql .= "CONSTRAINT [PK_{$table_name}] PRIMARY KEY  CLUSTERED (";
				$sql .= '[' . implode("],\n\t\t[", $column) . ']';
				$sql .= ') ON [PRIMARY]';

				$statements[] = $sql;
			break;

			case 'oracle':
				$statements[] = 'ALTER TABLE ' . $table_name . 'add CONSTRAINT pk_' . $table_name . ' PRIMARY KEY (' . implode(', ', $column) . ')';
			break;

			case 'sqlite':
				$sql = "SELECT sql
					FROM sqlite_master
					WHERE type = 'table'
						AND name = '{$table_name}'
					ORDER BY type DESC, name;";
				$result = $this->db->sql_query($sql);

				if (!$result)
				{
					break;
				}

				$row = $this->db->sql_fetchrow($result);
				$this->db->sql_freeresult($result);

				$statements[] = 'begin';

				// Create a backup table and populate it, destroy the existing one
				$statements[] = preg_replace('#CREATE\s+TABLE\s+"?' . $table_name . '"?#i', 'CREATE TEMPORARY TABLE ' . $table_name . '_temp', $row['sql']);
				$statements[] = 'INSERT INTO ' . $table_name . '_temp SELECT * FROM ' . $table_name;
				$statements[] = 'DROP TABLE ' . $table_name;

				preg_match('#\((.*)\)#s', $row['sql'], $matches);

				$new_table_cols = trim($matches[1]);
				$old_table_cols = preg_split('/,(?![\s\w]+\))/m', $new_table_cols);
				$column_list = array();

				foreach ($old_table_cols as $declaration)
				{
					$entities = preg_split('#\s+#', trim($declaration));
					if ($entities[0] == 'PRIMARY')
					{
						continue;
					}
					$column_list[] = $entities[0];
				}

				$columns = implode(',', $column_list);

				// create a new table and fill it up. destroy the temp one
				$statements[] = 'CREATE TABLE ' . $table_name . ' (' . $new_table_cols . ', PRIMARY KEY (' . implode(', ', $column) . '));';
				$statements[] = 'INSERT INTO ' . $table_name . ' (' . $columns . ') SELECT ' . $columns . ' FROM ' . $table_name . '_temp;';
				$statements[] = 'DROP TABLE ' . $table_name . '_temp';

				$statements[] = 'commit';
			break;
		}

		return $this->_sql_run_sql($statements);
	}

	/**
	* Add unique index
	*/
	function sql_create_unique_index($table_name, $index_name, $column)
	{
		$statements = array();

		switch ($this->sql_layer)
		{
			case 'firebird':
			case 'postgres':
			case 'oracle':
			case 'sqlite':
				$statements[] = 'CREATE UNIQUE INDEX ' . $table_name . '_' . $index_name . ' ON ' . $table_name . '(' . implode(', ', $column) . ')';
			break;

			case 'mysql_40':
			case 'mysql_41':
				$statements[] = 'CREATE UNIQUE INDEX ' . $index_name . ' ON ' . $table_name . '(' . implode(', ', $column) . ')';
			break;

			case 'mssql':
				$statements[] = 'CREATE UNIQUE INDEX ' . $index_name . ' ON ' . $table_name . '(' . implode(', ', $column) . ') ON [PRIMARY]';
			break;
		}

		return $this->_sql_run_sql($statements);
	}

	/**
	* Add index
	*/
	function sql_create_index($table_name, $index_name, $column)
	{
		$statements = array();

		switch ($this->sql_layer)
		{
			case 'firebird':
			case 'postgres':
			case 'oracle':
			case 'sqlite':
				$statements[] = 'CREATE INDEX ' . $table_name . '_' . $index_name . ' ON ' . $table_name . '(' . implode(', ', $column) . ')';
			break;

			case 'mysql_40':
			case 'mysql_41':
				$statements[] = 'CREATE INDEX ' . $index_name . ' ON ' . $table_name . '(' . implode(', ', $column) . ')';
			break;

			case 'mssql':
				$statements[] = 'CREATE INDEX ' . $index_name . ' ON ' . $table_name . '(' . implode(', ', $column) . ') ON [PRIMARY]';
			break;
		}

		return $this->_sql_run_sql($statements);
	}

	/**
	* Change column type (not name!)
	*/
	function sql_column_change($table_name, $column_name, $column_data)
	{
		$column_data = $this->sql_prepare_column_data($table_name, $column_name, $column_data);
		$statements = array();

		switch ($this->sql_layer)
		{
			case 'firebird':
				// Change type...
				$statements[] = 'ALTER TABLE ' . $table_name . ' ALTER COLUMN "' . strtoupper($column_name) . '" TYPE ' . ' ' . $column_data['column_type_sql'];
			break;

			case 'mssql':
				$statements[] = 'ALTER TABLE [' . $table_name . '] ALTER COLUMN [' . $column_name . '] ' . $column_data['column_type_sql'];
			break;

			case 'mysql_40':
			case 'mysql_41':
				$statements[] = 'ALTER TABLE `' . $table_name . '` CHANGE `' . $column_name . '` `' . $column_name . '` ' . $column_data['column_type_sql'];
			break;

			case 'oracle':
				$statements[] = 'ALTER TABLE ' . $table_name . ' MODIFY ' . $column_name . ' ' . $column_data['column_type_sql'];
			break;

			case 'postgres':
				$sql = 'ALTER TABLE ' . $table_name . ' ';

				$sql_array = array();
				$sql_array[] = 'ALTER COLUMN ' . $column_name . ' TYPE ' . $column_data['column_type'];

				if (isset($column_data['null']))
				{
					if ($column_data['null'] == 'NOT NULL')
					{
						$sql_array[] = 'ALTER COLUMN ' . $column_name . ' SET NOT NULL';
					}
					else if ($column_data['null'] == 'NULL')
					{
						$sql_array[] = 'ALTER COLUMN ' . $column_name . ' DROP NOT NULL';
					}
				}

				if (isset($column_data['default']))
				{
					$sql_array[] = 'ALTER COLUMN ' . $column_name . ' SET DEFAULT ' . $column_data['default'];
				}

				// we don't want to double up on constraints if we change different number data types
				if (isset($column_data['constraint']))
				{
					$constraint_sql = "SELECT consrc as constraint_data
								FROM pg_constraint, pg_class bc
								WHERE conrelid = bc.oid
									AND bc.relname = '{$table_name}'
									AND NOT EXISTS (
										SELECT *
											FROM pg_constraint as c, pg_inherits as i
											WHERE i.inhrelid = pg_constraint.conrelid
												AND c.conname = pg_constraint.conname
												AND c.consrc = pg_constraint.consrc
												AND c.conrelid = i.inhparent
									)";

					$constraint_exists = false;

					$result = $this->db->sql_query($constraint_sql);
					while ($row = $this->db->sql_fetchrow($result))
					{
						if (trim($row['constraint_data']) == trim($column_data['constraint']))
						{
							$constraint_exists = true;
							break;
						}
					}
					$this->db->sql_freeresult($result);

					if (!$constraint_exists)
					{
						$sql_array[] = 'ADD ' . $column_data['constraint'];
					}
				}

				$sql .= implode(', ', $sql_array);

				$statements[] = $sql;
			break;

			case 'sqlite':

				$sql = "SELECT sql
					FROM sqlite_master
					WHERE type = 'table'
						AND name = '{$table_name}'
					ORDER BY type DESC, name;";
				$result = $this->db->sql_query($sql);

				if (!$result)
				{
					break;
				}

				$row = $this->db->sql_fetchrow($result);
				$this->db->sql_freeresult($result);

				$statements[] = 'begin';

				// Create a temp table and populate it, destroy the existing one
				$statements[] = preg_replace('#CREATE\s+TABLE\s+"?' . $table_name . '"?#i', 'CREATE TEMPORARY TABLE ' . $table_name . '_temp', $row['sql']);
				$statements[] = 'INSERT INTO ' . $table_name . '_temp SELECT * FROM ' . $table_name;
				$statements[] = 'DROP TABLE ' . $table_name;

				preg_match('#\((.*)\)#s', $row['sql'], $matches);

				$new_table_cols = trim($matches[1]);
				$old_table_cols = preg_split('/,(?![\s\w]+\))/m', $new_table_cols);
				$column_list = array();

				foreach ($old_table_cols as $key => $declaration)
				{
					$entities = preg_split('#\s+#', trim($declaration));
					$column_list[] = $entities[0];
					if ($entities[0] == $column_name)
					{
						$old_table_cols[$key] = $column_name . ' ' . $column_data['column_type_sql'];
					}
				}

				$columns = implode(',', $column_list);

				// create a new table and fill it up. destroy the temp one
				$statements[] = 'CREATE TABLE ' . $table_name . ' (' . implode(',', $old_table_cols) . ');';
				$statements[] = 'INSERT INTO ' . $table_name . ' (' . $columns . ') SELECT ' . $columns . ' FROM ' . $table_name . '_temp;';
				$statements[] = 'DROP TABLE ' . $table_name . '_temp';

				$statements[] = 'commit';

			break;
		}

		return $this->_sql_run_sql($statements);
	}
}

?>