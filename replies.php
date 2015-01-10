<?php
require('includes/header.php');

// Check if we're on a specific page.
if (!ctype_digit($_GET['p']) || $_GET['p'] < 2) {
	$current_page = 1;
	$page_title   = 'Latest replies';
	update_activity('latest_replies');
} else {
	$current_page = $_GET['p'];
	$page_title   = 'Replies, page #' . number_format($current_page);
	
	update_activity('replies', $current_page);
}
// Print out the appropriate replies.
$items_per_page           = ITEMS_PER_PAGE;
$start_listing_replies_at = $items_per_page * ($current_page - 1);

$stmt = $link->db_exec('SELECT replies.id, replies.parent_id, replies.time, replies.body, topics.headline, topics.time FROM replies INNER JOIN topics ON replies.parent_id = topics.id WHERE replies.deleted = 0 AND topics.deleted = 0 AND topics.secret_id IS NULL ORDER BY id DESC LIMIT %1, %2', $start_listing_replies_at, $items_per_page);
$replies = new table();
$columns = array(
	'Snippet',
	'Topic',
	'Age ▼'
);
$replies->define_columns($columns, 'Topic');
$replies->add_td_class('Topic', 'topic_headline');
$replies->add_td_class('Snippet', 'snippet');

while (list($reply_id, $parent_id, $reply_time, $reply_body, $topic_headline, $topic_time) = $link->fetch_row($stmt)) {
	$url = DOMAIN.'topic/' . $parent_id . '#reply_' . $reply_id;
	$values = array(
		'<a href="' . $url . '">' . snippet($reply_body) . '</a>',
		'<a href="'.DOMAIN.'topic/' . $parent_id . '">' . htmlspecialchars($topic_headline) . '</a> <span class="help unimportant" title="' . format_date($topic_time) . '">(' . calculate_age($topic_time) . ' old)</span>',
		'<span class="help" title="' . format_date($reply_time) . '">' . calculate_age($reply_time) . '</span>'
	);
	
	$replies->row($values, array('onClick' => 'document.location = "' . $url . '"'));
}
$num_replies_fetched = $replies->num_rows_fetched;
echo $replies->output();

// Navigate backward or forward.
page_navigation('replies', $current_page, $num_replies_fetched);
require('includes/footer.php');
?>
