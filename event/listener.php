<?php
/** 
*
* @package Hide_BBcode
* @copyright (c) 2016 ntvy95 based on Marco van Oort's work
* @license http://opensource.org/licenses/gpl-license.php GNU Public License v2 
*
*/

namespace ntvy95\hide\event;

/**
* @ignore
*/
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
	protected $user;
	protected $template;
	protected $db;
	protected $current_row;
	protected $post_list;
	protected $iterator;
	protected $end;
	protected $decoded;
	protected $is_quoted;

	public function __construct(\phpbb\user $user, \phpbb\template\template $template, \phpbb\db\driver\driver_interface $db)
	{
		$this->user = $user;
		$this->template = $template;
		$this->db = $db;
	}

	static public function getSubscribedEvents()
	{
		return array(
			'core.user_setup'	=> 'load_language_on_setup',
			'core.viewtopic_modify_post_data'	=> 'viewtopic_modify_post_data',
			'core.topic_review_modify_post_list' => 'topic_review_modify_post_list',
			'core.topic_review_modify_row' => 'iterate',
			'core.modify_format_display_text_after' => 'modify_format_display_text_after',
			'core.modify_text_for_display_after' => 'modify_text_for_display_after',
			'core.decode_message_after' => 'decode_message_after',
			'core.search_modify_rowset' => 'search_modify_rowset',
			'core.modify_posting_parameters' => 'modify_posting_parameters',
			'core.viewtopic_modify_post_row' => 'iterate',
			'core.search_modify_tpl_ary' => 'iterate',
		);
	}
	
		/**
	* Load common files during user setup
	*
	* @param object $event The event object
	* @return null
	* @access public
	*/
	public function load_language_on_setup($event)
	{
		$lang_set_ext = $event['lang_set_ext'];
		$lang_set_ext[] = array(
			'ext_name' => 'ntvy95/hide',
			'lang_set' => 'hide_bbcode',
		);
		$event['lang_set_ext'] = $lang_set_ext;
	}
	
	public function viewtopic_modify_post_data($event) {
		$this->post_list = array();
		$post_list = $event['post_list'];
		$rowset = $event['rowset'];
		for ($i = 0, $end = sizeof($post_list); $i < $end; ++$i)
		{
			if (!isset($rowset[$post_list[$i]]))
			{
				continue;
			}
			$row = $rowset[$post_list[$i]];
			$poster_id = $row['user_id'];
			$this->post_list[$i] = $poster_id;
		}
		$this->iterator = 0;
		$this->end = $end;
		$this->decoded = false;
	}
	
	public function search_modify_rowset($event) {
		$this->post_list = array();
		if($event['show_results'] == 'posts') {
			$rowset = $event['rowset'];
			$i = 0;
			foreach ($rowset as &$row)
			{
				if($row['display_text_only']) {
					$this->replace_hide_bbcode_wrapper($row['poster_id'], $row['bbcode_uid'], $row['post_text'], true);
				}
				else {
					$this->post_list[$i] = $row['poster_id'];
					$i = $i + 1;
				}
			}
			$this->iterator = 0;
			$this->end = $i;
			$this->decoded = true;
			$event['rowset'] = $rowset;
		}
	}
	
	public function topic_review_modify_post_list($event) {
		$this->post_list = array();
		$post_list = $event['post_list'];
		$rowset = $event['rowset'];
		for ($i = 0, $end = sizeof($post_list); $i < $end; ++$i)
		{
			if (!isset($rowset[$post_list[$i]]))
			{
				continue;
			}
			$row = $rowset[$post_list[$i]];
			$poster_id = $row['user_id'];
			$this->post_list[$i] = $poster_id;
		}
		$this->iterator = 0;
		$this->end = $end;
		$this->decoded = false;
	}
	
	public function modify_text_for_display_after($event) {
		if(isset($this->iterator)) {
			$this->current_row['user_id'] = $this->post_list[$this->iterator];
		}
		$text = $event['text'];
		if(isset($this->current_row['user_id'])) {
			$this->replace_hide_bbcode_wrapper($this->current_row['user_id'], $event['uid'], $text, $this->decoded);
		}
		else {
			$this->replace_hide_bbcode_wrapper(null, $event['uid'], $text, $this->decoded);
		}
		$event['text'] = $text;
	}
	
	public function decode_message_after($event) {
		if(isset($this->iterator)) {
			$this->current_row['user_id'] = $this->post_list[$this->iterator];
		}
		$text = $event['message_text'];
		if(isset($this->current_row['user_id'])) {
			if($this->user->data['user_id'] != $this->current_row['user_id']) {
				$this->replace_hide_bbcode_wrapper($this->current_row['user_id'], $event['bbcode_uid'], $text, true);
			}
		}
		else {
			$this->replace_hide_bbcode_wrapper(null, $event['bbcode_uid'], $text, true);
		}
		$event['message_text'] = $text;
	}
	
	public function modify_posting_parameters($event) {
		$sql = 'SELECT poster_id
			FROM ' . POSTS_TABLE . '
			WHERE post_id = ' . $event['post_id'];
		
		/*$sql_array = array(
		    'SELECT'    =>  'poster_id',
		    'FROM'      =>  POSTS_TABLE,
		    'WHERE'     =>  'post_id = ' . $event['post_id'],
		);

		$sql = $db->sql_build_query('SELECT', $sql_array);*/
		$result = $this->db->sql_query($sql);
		$this->current_row['user_id'] = (int) $this->db->sql_fetchfield('poster_id');
		$this->db->sql_freeresult($result);
	}

	public function iterate() {
		if(isset($this->iterator)) {
			$this->iterator = $this->iterator + 1;
			if($this->iterator >= $this->end) {
				unset($this->iterator);
			}
		}
	}
	
	public function modify_format_display_text_after($event) {
		$text = $event['text'];
		$this->replace_hide_bbcode_wrapper($this->user->data['user_id'], null, $text, false);
		$event['text'] = $text;
	}
	
	public function replace_hide_bbcode_wrapper($user_id, $bbcode_uid, &$message, $decoded) {
		$this->current_row['user_id'] = $user_id;
		$this->current_row['regex']['open_tag'] = "@\[hide(|\=(|[0-9,]+)(|\|([0-9,]+)))(|:". $bbcode_uid .")\]@is";
		$this->current_row['regex']['close_tag'] = "@\[/hide(|:". $bbcode_uid .")\]@is";
		if(preg_match_all($this->current_row['regex']['open_tag'], $message, $open_matches, PREG_OFFSET_CAPTURE)
		&& preg_match_all($this->current_row['regex']['close_tag'], $message, $close_matches, PREG_OFFSET_CAPTURE))
		{
			$result = $this->find_pairs($open_matches, $close_matches);
			$matches = $result[0];
			$tree = $result[1];
			//var_dump("-----------TREE------------\n");
			//var_dump($tree);
			//var_dump($matches);
			//var_dump($open_matches);
			if(!$decoded) {
				$this->template->set_style(array('styles', 'ext/ntvy95/hide/styles'));
				$bbcode = new \bbcode();
				$bbcode->template_filename = $this->template->get_source_file_for_handle('hide_bbcode.html');
				$unhide_open = $bbcode->bbcode_tpl('unhide_open');
				$unhide_close = $bbcode->bbcode_tpl('unhide_close');
				$hide = $bbcode->bbcode_tpl('hide');
			}
			else {
				$unhide_open = "[hide]";
				$unhide_close = "[/hide]";
				$hide = "[hide][/hide]";
			}
			$message = $this->replace_hide_bbcode($message, $tree, $matches, $open_matches, $close_matches, $unhide_open, $unhide_close, $hide);
		}
	}
	
	public function find_pairs($open_matches, $close_matches) {
		$matches = array();
		$tree_inf = array();
		if(count($open_matches[0]) > count($close_matches[0])) {
			$current = count($open_matches[0]) - count($close_matches[0]);
		}
		else {
			$current = 0;
		}
		$prev[$current] = $current - 1; $next[$current] = $current + 1;
		$max_current = 0;
		$tree_inf[$current] = $current;
		for($i = 0; $i < count($close_matches[0]); $i++) {
			while($current != -1) {
				if($next[$current] < count($open_matches[0])
				&& $open_matches[0][$next[$current]][1] < $close_matches[0][$i][1]) {
					if(isset($tree_inf[$current])
					  && $tree_inf[$current] == $current) {
						$tree_inf[$current] = array();
					}
					$tree_inf[$current][$next[$current]] = $next[$current];
					$prev[$next[$current]] = $current;
					$current = $next[$current];
					if(!isset($next[$current])) {
						$next[$current] = $current + 1;
					}
				}
				else {
					if($current > $max_current) {
						$max_current = $current;
					}
					$matches[$current] = $i;
					$next[$prev[$current]] = $next[$current];
					$current = $prev[$current];
					break;
				}
			}
			if($current == -1
			&& $max_current + 1 < count($open_matches[0])) {
				$current = $max_current + 1;
				$next[$current] = $current + 1;
				$tree_inf[$current] = $current;
				$prev[$current] = -1;
			}
		}
		while(count($tree_inf) > 0) {
			$traverse = key($tree_inf);
			$tree[$traverse] = $this->buildTree($traverse, $tree_inf);
		}
		return array($matches, $tree);
	}
	
	public function buildTree($parent, &$tree_inf) {
		if(isset($tree_inf[$parent])) {
			$children = $tree_inf[$parent];
			unset($tree_inf[$parent]);
			if(is_array($children)) {
				foreach($children as &$child) {
					$child = $this->buildTree($child, $tree_inf);
				}
			}
		}
		else {
			$children = $parent;
		}
		return $children;
	}
	
	public function replace_hide_bbcode($subject, $tree, $matches, $open_matches, $close_matches, $unhide_open, $unhide_close, $hide){
		$len['unhide_open'] = strlen($unhide_open);
		$len['unhide_close'] = strlen($unhide_close);
		$len['hide'] = strlen($hide);
		
		$start_dist = 0;
		$previous_depth = 0;
		$current_close = -1;
		//var_dump("-----------STACK------------\n");
		foreach($tree as $parent => $children) {
			$stack = array();
			array_push($stack, array($parent, $children, 0));
			while(count($stack) > 0) {
				//var_dump("=======\n");
				$current = array_pop($stack);
				//var_dump($current);
				$i = $current[0];
				$j = $matches[$current[0]];
				$len['close_tag'] = strlen($close_matches[0][$j][0]);
				$len['open_tag'] = strlen($open_matches[0][$i][0]);
				$start = $open_matches[0][$i][1];
				$length = $close_matches[0][$j][1] + $len['close_tag'] - $start;
				$more_dist = 0;
				if($current_close != -1
				&& $j > $current_close) {
					$start_dist = $start_dist + ($len['unhide_close'] - $len['close_tag']) * ($previous_depth - $current[2] + 1);
				}
				if($this->user->data['user_id'] == $this->current_row['user_id']
				|| in_array($this->user->data['user_id'], explode(',', $open_matches[2][$i][0]))
				|| in_array($this->user->data['group_id'], explode(',', $open_matches[4][$i][0]))) {
					$replace_string = $unhide_open
					. substr($subject,
							$start_dist + $start + $len['open_tag'],
							$length - $len['close_tag'] - $len['open_tag'])
					. $unhide_close;
					$more_dist = $len['unhide_open'];
					if(is_array($current[1])) {
						$current[1] = array_reverse($current[1], true);
						foreach($current[1] as $child => $grandchildren) {
							array_push($stack, array($child, $grandchildren, $current[2] + 1));
						}
					}
					$previous_depth = $current[2];
					$current_close = $j;
				}
				else {
					$replace_string = $hide;
					$more_dist = $len['hide'] - $length + $len['open_tag'];
				}
				//var_dump(substr(
				//	$subject, $start_dist + $start, $length
				//));
				//var_dump($replace_string);
				$subject = substr_replace($subject, $replace_string, $start_dist + $start, $length);
				$start_dist = $start_dist + $more_dist - $len['open_tag'];
			}
		}
		/*$close_threshold = null;
		$current_parent_close = null;
		$parent_close_count = 0;
		ksort($matches);
		foreach($matches as $i => $j) {
			if($close_threshold === null || $j > $close_threshold) {
				$len['close_tag'] = strlen($close_matches[0][$j][0]);
				$len['open_tag'] = strlen($open_matches[0][$i][0]);
				
				$start = $open_matches[0][$i][1];
				$length = $close_matches[0][$j][1] + $len['close_tag'] - $start;
				$more_dist = 0;
				
				if($current_parent_close !== null
				&& $j > $current_parent_close)
				{
					$start_dist = $start_dist + ($len['unhide_close'] - $len['close_tag']);
				}
				if($this->user->data['user_id'] == $this->current_row['user_id']
				|| in_array($this->user->data['user_id'], explode(',', $open_matches[2][$i][0]))
				|| in_array($this->user->data['group_id'], explode(',', $open_matches[4][$i][0]))) {
					$replace_string = $unhide_open
					. substr($subject,
							$start_dist + $start + $len['open_tag'],
							$length - $len['close_tag'] - $len['open_tag'])
					. $unhide_close;
					$more_dist = $len['unhide_open'];
					$current_parent_close = $j;
				}
				else {
					$replace_string = $hide;
					$more_dist = $len['hide'] - $length + $len['open_tag'];
					$close_threshold = $j;
					$current_parent_close = null;
					$parent_close_count = 0;
				}
				var_dump(substr(
					$subject, $start_dist + $start, $length
				));
				var_dump($replace_string);
				$subject = substr_replace($subject, $replace_string, $start_dist + $start, $length);
				$start_dist = $start_dist + $more_dist - $len['open_tag'];
			}
		}*/
		return $subject;
	}
}
