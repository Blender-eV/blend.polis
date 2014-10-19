<?php
/**
* @package: phpBB3 :: RSS feed 2.0 mod version check -> adm -> mods
* @version: $Id: rss_mod_version.php 90 2009-07-22 20:23:50Z pat $
* @copyright: leviatan21 < info@mssti.com > (Gabriel) http://www.mssti.com/phpbb3/
* @license: http://opensource.org/licenses/gpl-license.php GNU Public License
* @author: leviatan21 - http://www.phpbb.com/community/memberlist.php?mode=viewprofile&u=345763
*
**/

if (!defined('IN_PHPBB'))
{
	exit;
}

class rss_mod_version
{
	function version()
	{
		return array(
			'author'	=> 'leviatan21',
			'title'		=> '>MSSTI RSS Feed 2.0 with ACP',
			'tag'		=> 'rssfeed20',
			'version'	=> '1.0.9',
			'file'		=> array('mssti.com', 'phpbb3', 'mssti_phpbb3x_mods.xml'),
		);
	}
}

?>