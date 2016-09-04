<?php

require 'includes/header.php';
force_id();
update_activity('watchlist');
$page_title = 'Your watchlist';

if (is_array($_POST['rejects'])) {
    foreach ($_POST['rejects'] as $reject_id) {
        $link->db_exec('DELETE FROM watchlists WHERE uid = %1 AND topic_id = %2', $_SESSION['UID'], $reject_id);
    }
    $_SESSION['notice'] = 'Selected topics unwatched.';
}

echo '<form name="watch_list" action="" method="post">';

$stmt = $link->db_exec('SELECT watchlists.topic_id, topics.headline, topics.replies, topics.visits, topics.time FROM watchlists INNER JOIN topics ON watchlists.topic_id = topics.id WHERE watchlists.uid = %1 ORDER BY last_post DESC', $_SESSION['UID']);

$topics = new table('selectable');
$topic_column = 'Topic';
$columns = array(
    $topic_column,
    'Replies',
    'Visits',
    'Age ▼',
);
$topics->add_table_class('selectable');
$topics->define_columns($columns, $topic_column);
$topics->add_td_class($topic_column, 'topic_headline');

while (list($topic_id, $topic_headline, $topic_replies, $topic_visits, $topic_time) = $link->fetch_row($stmt)) {
    $values = array(
        '<input type="checkbox" name="rejects[]" value="'.$topic_id.'" class="inline" /> <a href="'.DOMAIN.'topic/'.$topic_id.'">'.htmlspecialchars($topic_headline).'</a>',
        replies($topic_id, $topic_replies),
        format_number($topic_visits),
        '<span class="help" title="'.format_date($topic_time).'">'.calculate_age($topic_time).'</span>',
    );

    $topics->row($values);
}
$num_topics_fetched = $topics->num_rows_fetched;
echo $topics->output();

if ($num_topics_fetched !== 0) {
    echo '<div class="row"><input type="submit" value="Unwatch selected" onclick="return confirm(\'Really remove selected topic(s) from your watchlist?\');" class="inline" /></div>';
}
echo '</form>';

require 'includes/footer.php';
