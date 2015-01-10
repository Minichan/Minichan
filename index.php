<?php
require('includes/header.php');
//add_notification('notify', '4d4839bb5803c1.29024241', 'bug_1', 'Herro there!');
//remove_notification('notify', '4d4839bb5803c1.29024241', 'bug_1');

//if($administrator) {
/*	$additional_head = "<script src=\"" . DOMAIN . "javascript/flip.js\"></script>";*/
//}

// Should we sort by topic creation date or last bump?
// if($_COOKIE['topics_mode'] == 1 && ! $_GET['bumps']) {
//	$topics_mode = true;
// }

if($_GET['bumps']) {
	$topics_mode = false;
}else if($_GET['topics']){
	$topics_mode = true;
}else{
	$link->db_exec("SELECT topics_mode FROM user_settings WHERE uid = %1", $_SESSION["UID"]);
	list($topics_mode) = $link->fetch_row();
	$topics_mode = (bool)$topics_mode;
}
if(! empty($_GET['error'])) {
	redirect($_GET['error']);
	add_error('error');
}

// Are we on the first page?
if ($_GET['p'] < 2 || ! ctype_digit($_GET['p'])) {
	$current_page = 1;
	
	if($topics_mode) {
		update_activity('latest_topics');
		$page_title = 'Latest topics';
		$last_seen = $_COOKIE['last_topic'];
	} else {
		update_activity('latest_bumps');
		$page_title = 'Latest bumps';
		$last_seen = $_COOKIE['last_bump'];
	}
// The page number is greater than one.
} else {
	$current_page = $_GET['p'];
	if($topics_mode) {
		update_activity('latest_topics', $current_page);
		$page_title = 'Topics, page #' . number_format($current_page);
		$last_seen = $_COOKIE['last_topic'];
	} else {
		update_activity('latest_bumps', $current_page);
		$page_title = 'Bumps, page #' . number_format($current_page);
		$last_seen = $_COOKIE['last_bump'];
	}
}

// Update the last_bump and last_topic cookies. These control both the last seen marker and the exclamation mark in main menu.
if($_COOKIE['last_bump'] <= $last_actions['last_bump']) {
	setcookie('last_bump', $_SERVER['REQUEST_TIME'], $_SERVER['REQUEST_TIME'] + 315569260, '/');
}
if($_COOKIE['last_topic'] <= $last_actions['last_topic']) {
	setcookie('last_topic', $_SERVER['REQUEST_TIME'], $_SERVER['REQUEST_TIME'] + 315569260, '/');
}

// If ostrich mode is enabled, fetch a list of blacklisted phrases.
$ignored_phrases = fetch_ignore_list();

// Fetch the topics appropriate to this page.
$items_per_page = ITEMS_PER_PAGE;
$start_listing_at = $items_per_page * ($current_page - 1);
// Print the topics we just fetched in a table.
$table = new table();
$order_name = ($topics_mode) ? 'Age' : ((MOBILE_MODE) ? 'Bump' : 'Last bump');
$columns = array
(
	'Headline',
	'Snippet',
	'Replies',
	'Visits',
	$order_name . ' ▼'
);
			
if($_COOKIE['spoiler_mode'] != 1) {
	// If spoiler mode is disabled, remove the snippet column.	
	array_splice($columns, 1, 1);
}

if(MOBILE_MODE){
	array_splice($columns, 2, 1);
}

$table->define_columns($columns, 'Headline');
$table->add_td_class('Headline', 'topic_headline');
$table->add_td_class('Snippet', 'snippet');

if($topics_mode) {
	$stickies = $link->db_exec('SELECT id, time, replies, visits, headline, body, last_post, locked, poll, secret_id FROM topics WHERE sticky = 1 AND deleted = 0 ORDER BY id DESC');
}else{
	$stickies = $link->db_exec('SELECT id, time, replies, visits, headline, body, last_post, locked, poll, secret_id FROM topics WHERE sticky = 1 AND deleted = 0 ORDER BY last_post DESC');
}

$stickyRows = $link->num_rows($stickies);

//http://trac.tinybbs.org/newticket
//$table->last_seen_marker($last_seen, 0);
//$values = array(
//	'<a class="visited" href="http://forum.fuckingstoned.org">BUNKER BOARD</a> <small class="topic_info">[LINK]</small>',
//	snippet('Bunker board...'),
//	'-',
//	'-',
//	'<span>Never</span>'
//);



while(list($sticky_id, $sticky_time, $sticky_replies, $sticky_visits, $sticky_headline, $sticky_body, $sticky_last_post, $sticky_locked, $sticky_poll, $secret_id) = $link->fetch_row($stickies)){
	if($secret_id && !allowed('minecraft')) {
		$table->num_rows_fetched++;
		continue;
	}
	
	if($topics_mode) {
		$order_time = $sticky_time;
	} else {
		$order_time = $sticky_last_post;
	}
	
	if((time()-$sticky_last_post)>NECRO_BUMP_TIME) $sticky_locked = true;
	
	//$sticky_locked = !$sticky_locked;
	
	// Process the values for this row of our table. 
	if($sticky_locked) {
		$lockTxt = "[LOCKED] ";
	}else{
		$lockTxt = "";
	}
			
	if($sticky_poll){
		$pollTxt = " (Poll)";
	}else{
		$pollTxt = "";
	}

	$visited = ((!$visited_topics[$sticky_id] && isset($visited_topics[$sticky_id])) || (($sticky_replies - $visited_topics[$sticky_id] != $sticky_replies)) ? ' class="visited"' : '');
	$url = DOMAIN . 'topic/' . $sticky_id;
	$values = array (
						'<a' . $visited . ' href="'. $url . '">' . htmlspecialchars($sticky_headline, ENT_COMPAT | ENT_HTML401, "") . '</a>' . $pollTxt . ' <small class="topic_info">'.$lockTxt.'[STICKY]</small>',
						snippet($sticky_body),
						replies($sticky_id, $sticky_replies),
						format_number($sticky_visits),
						'<span class="help" title="' . format_date($order_time) . '">' . calculate_age($order_time) . '</span>'
					);
	if($_COOKIE['spoiler_mode'] != 1) {	
		array_splice($values, 1, 1);
	}
	if(MOBILE_MODE){
		array_splice($values, 2, 1);
	}
	$table->last_seen_marker(false, false);
	$table->row($values, array('onClick' => 'document.location="' . $url . '"'));
}

$newPerPage = ($items_per_page - $stickyRows);

if($topics_mode) {
	$stmt = $link->db_exec('SELECT id, time, replies, visits, headline, body, last_post, locked, poll, secret_id FROM topics WHERE sticky = 0 AND deleted = 0 ORDER BY id DESC LIMIT %1, %2', $start_listing_at, $newPerPage);
} else {
	$stmt = $link->db_exec('SELECT id, time, replies, visits, headline, body, last_post, locked, poll, secret_id FROM topics WHERE sticky = 0 AND deleted = 0 ORDER BY last_post DESC LIMIT %1, %2', $start_listing_at, $newPerPage);
}

while(list($topic_id, $topic_time, $topic_replies, $topic_visits, $topic_headline, $topic_body, $topic_last_post, $topic_locked, $topic_poll, $secret_id) = $link->fetch_row($stmt)) {
	if($secret_id && !allowed('minecraft')) {
		$table->num_rows_fetched++;
		continue;
	}
	
	// Should we even bother?
	if($_COOKIE['ostrich_mode'] == 1) {
		foreach($ignored_phrases as $ignored_phrase)
		{
			if(stripos($topic_headline, $ignored_phrase) !== false || stripos($topic_body, $ignored_phrase) !== false) {
				// We've encountered an ignored phrase, so skip the rest of this while() iteration.
				$table->num_rows_fetched++;
				continue 2;
			}
		}
	}
	
	// Decide what to use for the last seen marker and the age/last bump column.
	if($topics_mode) {
		$order_time = $topic_time;
	} else {
		$order_time = $topic_last_post;
	}
	
	if((time()-$topic_last_post)>NECRO_BUMP_TIME) $topic_locked = true;
	
	//$topic_locked = !$topic_locked;
	
	if($topic_locked) {
		$lockTxt = "<small class='topic_info'>[LOCKED]</a>";
	}else{
		$lockTxt = "";
	}
		
	if($topic_poll) {
		$pollTxt = " (Poll)";
	}else{
		$pollTxt = "";
	}
	
	// Process the values for this row of our table. 
	
	//if(MOBILE_MODE){
	// chr(8203) = zero width whitespace
		//$topic_headline = str_split($topic_headline, 25);
		//$topic_headline = implode(chr(8203), $topic_headline);
		//$topic_headline = preg_replace('/(\w{13})(?![^a-zA-Z])/', '$1'.chr(8203), $topic_headline);
	//}

    $url = DOMAIN.'topic/' . $topic_id;
	$visited = ((!$visited_topics[$topic_id] && isset($visited_topics[$topic_id])) || (($topic_replies - $visited_topics[$topic_id] != $topic_replies)) ? ' class="visited"' : '');

	$values = array (
						'<a'.$visited.' href="'. $url . '">' . htmlspecialchars($topic_headline, ENT_COMPAT | ENT_HTML401, "") . '</a>' . $pollTxt . $lockTxt,
						snippet($topic_body),
						replies($topic_id, $topic_replies),
						format_number($topic_visits),
						'<span class="help" title="' . format_date($order_time) . '">' . calculate_age($order_time) . '</span>'
					);
	if($_COOKIE['spoiler_mode'] != 1) {	
		array_splice($values, 1, 1);
	}
	if(MOBILE_MODE){
		array_splice($values, 2, 1);
	}
	$table->last_seen_marker($last_seen, $order_time);
	$table->row($values, array('onClick' => 'document.location="' . $url . '"'));
}
$link->free_result($stmt);
$num_rows_fetched = $table->num_rows_fetched;
echo $table->output('topics');

// Navigate backward or forward.
$navigation_path = 'bumps';
if($_GET['topics']) {
	$navigation_path = 'topics';
}

page_navigation($navigation_path, $current_page, $num_rows_fetched);
/*
?>
<script src="https://secbrowsing.appspot.com/static/plugin.js"></script> 
<script src="https://secbrowsing.appspot.com/get?format=js"></script> 
<script> 
  secBrowsing.Plugin.setPluginVersions(window.secBrowsingData);
  var results = secBrowsing.Plugin.checkPlugins();
  if(results.old.length > 0) {
	  //$("table tbody").prepend('<tr><td>You have ' + results.old.length + ' vulnerable browser plugin(s). Click <a href="http://secbrowsing.appspot.com/">here</a> for more information.<small class="topic_info">[IMPORTANT]</small></td><td class="minimal">-</td><td class="minimal">-</td><td class="minimal">-</td></tr>');
	  
	  var post = {};
	  
	  $.each(results.old, function(index, value){
		  post[index] = {
			  name: value.plugin.name,
			  desc: value.plugin.description,
			  version: value.version
		  };
	  });
	  
	  $.post("<?php echo DOMAIN; ?>plugins.php", {"plugins": post});
  }
</script>
<?php
*/
require('includes/footer.php');
?>
