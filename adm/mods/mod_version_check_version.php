<?php
/**
*
* @package acp
* @version $Id: mod_version_check_version.php 81 2009-07-16 08:16:34Z pat $
* @copyright (c) 2007 StarTrekGuide
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @package mod_version_check
*/

if (!defined('IN_PHPBB'))
{
	exit;
}

class mod_version_check_version
{
	function version()
	{
		return array(
			'author'	=> 'Handyman`',
			'title'		=> 'MOD Version Check',
			'tag'		=> 'mod_version_check',
			'version'	=> '1.0.3',
			'file'		=> array('startrekguide.com', 'updatecheck', 'mods101.xml'),
		);
	}
}

?>