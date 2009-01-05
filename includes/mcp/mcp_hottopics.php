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
class mcp_hottopics
{
	var $u_action;

	function main($id, $mode)
	{
		global $phpEx, $phpbb_root_path, $template, $db, $user;

		switch($mode)
		{
			// Overview
			case 'front':
				$this->page_title = 'MCP_HOTTOPICS';
				$this->tpl_name = 'mcp_hottopics';

				$sql = 'SELECT h.slot_id, h.topic_id, h.forum_id, h.last_update, t.topic_title FROM bp_hottopics h LEFT JOIN ' . TOPICS_TABLE . ' t ON h.topic_id = t.topic_id ORDER BY h.last_update ASC';
				$result = $db->sql_query($sql);
				while ($row = $db->sql_fetchrow($result))
				{
					$template->assign_block_vars('slot', array(
						'SLOT_ID' => $row['slot_id'],
						'TOPIC_ID' => $row['topic_id'],
						'FORUM_ID' => $row['forum_id'],
						'TOPIC_TITLE' => htmlspecialchars($row['topic_title']),
						'LAST_UPDATE' => $user->format_date($row['last_update']),
						'U_TOPIC' => append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . $row['forum_id'] . '&amp;t=' . $row['topic_id']),
					));
				}
				$db->sql_freeresult($result);

			break;

			// Slot 1-6
			default:
				$slot_id = (int)substr($mode, 4);

				if (isset($_POST['confirm']))
				{
					// Save
					$topic_id = request_var('hot_topicid', 0);
					$row = $this->get_data_from_topic($topic_id);
					$row['image_id'] = request_var('hot_imageid', '0');
					$row['updated'] = time();

					if (is_numeric($row['image_id']))
					{
						$row['image_id'] = (int)$row['image_id'];
					}
					else
					{
						// Example: http://blendpolis.de/download/file.php?id=43365
						$match = array();
						if (preg_match('/file.php\?id=(\d+)/', $row['image_id'], $match))
						{
							$row['image_id'] = (int)$match[1];
						}
						else
						{
							$row['image_id'] = 0;
						}
					}

					$sql = "UPDATE bp_hottopics SET topic_id=$topic_id, forum_id={$row[forum_id]}, image_id={$row[image_id]}, last_update={$row[updated]} WHERE slot_id=$slot_id";
					$db->sql_query($sql);

				}
				else if (isset($_POST['preview']))
				{
					// Prepare data for preview
					$topic_id = request_var('hot_topicid', 0);
					$row = $this->get_data_from_topic($topic_id);
					$row['image_id'] = request_var('hot_imageid', '0');

					if (is_numeric($row['image_id']))
					{
						$row['image_id'] = (int)$row['image_id'];
					}
					else
					{

						$match = array();
						if (preg_match('/file.php\?id=(\d+)/', $row['image_id'], $match))
						{
							$row['image_id'] = (int)$match[1];
						}
						else
						{
							$row['image_id'] = 0;
						}
					}

				}
				else
				{
					// Get data for current slot
					$sql = 'SELECT h.*, t.topic_title FROM bp_hottopics h LEFT JOIN ' . TOPICS_TABLE . ' t ON h.topic_id = t.topic_id WHERE h.slot_id = ' . $slot_id;
					$result = $db->sql_query($sql);
					$row = $db->sql_fetchrow($result);
				}

				$this->page_title = 'MCP_HOTTOPICS';
				$this->tpl_name = 'mcp_hottopics_slot';

				$template->assign_var('DEBUG', print_r($this->p_master, true));

				$template->assign_vars(array(
					'TOPIC_ID' => $row['topic_id'],
					'FORUM_ID' => $row['forum_id'],
					'TOPIC_TITLE' => htmlspecialchars($row['topic_title']),
					'IMAGE_ID' => $row['image_id'],
					'U_ACTION' => $this->u_action,
					'U_TOPIC' => append_sid("{$phpbb_root_path}viewtopic.$phpEx", 'f=' . $row['forum_id'] . '&amp;t=' . $row['topic_id']),
					'U_IMAGE' => append_sid("{$phpbb_root_path}download/file.$phpEx", 'id=' . $row['image_id']),
				));
			break;
		}
	}

	/**
	 * Get title and forum-id from topic
	 * @return array
	 */
	function get_data_from_topic($topic_id)
	{
		global $db;
		$sql = 'SELECT topic_title, forum_id FROM ' . TOPICS_TABLE . ' WHERE topic_id = ' . $topic_id;
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);
		$db->sql_freeresult($result);
		$row['topic_id'] = $topic_id;
		return $row;
	}

}
