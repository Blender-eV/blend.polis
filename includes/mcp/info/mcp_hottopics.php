<?php
/**
*
* @author pat pat@blendpolis.de
* @package mcp
* @version $Id: $
* @copyright (c) pat
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
* blend.polis MCP module to manage hot topics display
*/

/**
* @package module_install
*/
class mcp_hottopics_info
{
	function module()
	{
		$result = array(
			'filename'	=> 'mcp_hottopics',
			'title'		=> 'MCP_HOTTOPICS',
			'version'	=> '1.0.0',
			'modes'		=> array(
				'front'	=> array('title' => 'MCP_HOTTOPICS_FRONT', 'auth' => '', 'cat' => array('MCP_HOTTOPICS')),
			),
		);

		for ($i = 1; $i < 7; $i++) {
			$result['modes']['slot' . $i] = array('title' => 'Slot ' . $i, 'auth' => '', 'cat' => array('MCP_HOTTOPICS'));
		}

		return $result;
	}

	function install()
	{
	}

	function uninstall()
	{
	}

}
