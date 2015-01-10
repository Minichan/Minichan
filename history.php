<?php
require('includes/header.php');
update_activity('history');
force_id();

if($_GET['citations']) {
	$page_title = "Replies to your replies";
}elseif (!ctype_digit($_GET['p']) || $_GET['p'] < 2) {
	$current_page = 1;
	$page_title   = 'Your posting history';
} else {
	$current_page = $_GET['p'];
	$page_title   = 'Your post history, page #' . number_format($current_page);
}

$items_per_page   = ITEMS_PER_PAGE;
$start_listing_at = $items_per_page * ($current_page - 1);

if($new_citations) {
		// Delete notifications of replies-to-replies that no longer exist.
	$link->db_exec('DELETE FROM citations WHERE uid = %1 AND (NOT EXISTS (SELECT 1 FROM replies WHERE citations.reply = replies.id AND replies.deleted = 0) OR NOT EXISTS (SELECT 1 FROM topics WHERE citations.topic = topics.id AND topics.deleted = 0))', $_SESSION['UID']);

	// List replies to user's replies.
	$stmt = $link->db_exec('SELECT DISTINCT citations.reply, replies.parent_id, replies.time, replies.body, topics.headline, topics.time FROM citations INNER JOIN replies ON citations.reply = replies.id INNER JOIN topics ON replies.parent_id = topics.id WHERE citations.uid = %1 ORDER BY citations.reply DESC', $_SESSION['UID']);

	$citations = new table();
	$columns = array(
		'Reply to your reply',
		'Topic',
		'Age ▼'
	);
	$citations->define_columns($columns, 'Topic');
	$citations->add_td_class('Topic', 'topic_headline');
	$citations->add_td_class('Reply to your reply', 'reply_body_snippet');

	while (list($reply_id, $parent_id, $reply_time, $reply_body, $topic_headline, $topic_time) = $link->fetch_row($stmt)) {
        $url = DOMAIN.'topic/' . $parent_id . '#reply_' . $reply_id;
		$values = array(
			'<a href="'. $url . '">' . snippet($reply_body) . '</a>',
			'<a href="'.DOMAIN.'topic/' . $parent_id . '">' . htmlspecialchars($topic_headline) . '</a> <span class="help unimportant" title="' . format_date($topic_time) . '">(' . calculate_age($topic_time) . ' old)</span>',
			'<span class="help" title="' . format_date($reply_time) . '">' . calculate_age($reply_time) . '</span>'
		);
		
		$citations->row($values, array('onClick' => 'document.location="' . $url . '"'));
	}
	if($citations->num_rows_fetched > 0) {
		echo $citations->output();
	} else {
		echo '<p>Nothing to see here...</p>';
	}
}

if(!$_GET['citations']) {
	// List topics.
	$stmt = $link->db_exec('SELECT id, time, replies, visits, headline FROM topics WHERE author = %1 AND deleted = 0 ORDER BY id DESC LIMIT %2, %3', $_SESSION['UID'], $start_listing_at, $items_per_page);
	
	$topics  = new table();
	$columns = array(
		'Headline',
		'Replies',
		'Visits',
		'Age ▼'
	);
	$topics->define_columns($columns, 'Headline');
	$topics->add_td_class('Headline', 'topic_headline');
	
	while (list($topic_id, $topic_time, $topic_replies, $topic_visits, $topic_headline) = $link->fetch_row($stmt)) {
        $url = DOMAIN.'topic/' . $topic_id;
		$values = array(
			'<a href="'. $url . '">' . htmlspecialchars($topic_headline) . '</a>',
			replies($topic_id, $topic_replies),
			format_number($topic_visits),
			'<span class="help" title="' . format_date($topic_time) . '">' . calculate_age($topic_time) . '</span>'
		);
		
		$topics->row($values, array('onClick' => 'document.location="' . $url . '"'));
	}
	$num_topics_fetched = $topics->num_rows_fetched;
	echo $topics->output('topics');
	
	// List replies.
	$stmt = $link->db_exec('SELECT replies.id, replies.parent_id, replies.time, replies.body, topics.headline, topics.time, topics.replies FROM replies INNER JOIN topics ON replies.parent_id = topics.id WHERE replies.author = %1 AND replies.deleted = 0 AND topics.deleted = 0 ORDER BY id DESC LIMIT %2, %3', $_SESSION['UID'], $start_listing_at, $items_per_page);
	
	$replies = new table();
	$columns = array(
		'Reply snippet',
		'Topic',
		'Replies',
		'Age ▼'
	);
	$replies->define_columns($columns, 'Topic');
	$replies->add_td_class('Topic', 'topic_headline');
	$replies->add_td_class('Reply snippet', 'reply_body_snippet');
	
	while (list($reply_id, $parent_id, $reply_time, $reply_body, $topic_headline, $topic_time, $parent_replies) = $link->fetch_row($stmt)) {
        $url = DOMAIN.'topic/' . $parent_id . '#reply_' . $reply_id;
		$values = array(
			'<a href="'. $url . '">' . snippet($reply_body) . '</a>',
			'<a href="'.DOMAIN.'topic/' . $parent_id . '">' . htmlspecialchars($topic_headline) . '</a> <span class="help unimportant" title="' . format_date($topic_time) . '">(' . calculate_age($topic_time) . ' old)</span>',
			replies($parent_id, $parent_replies),
			'<span class="help" title="' . format_date($reply_time) . '">' . calculate_age($reply_time) . '</span>'
		);
		
		$replies->row($values, array('onClick' => 'document.location="' . $url . '"'));
	}
	$num_replies_fetched = $replies->num_rows_fetched;
	echo $replies->output('replies');
	page_navigation('history', $current_page, $num_replies_fetched);
}elseif(!$new_citations) {
	echo "<p>Nothing to see here...</p>";
}
require('includes/footer.php');
?>