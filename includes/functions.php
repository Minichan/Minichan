<?php
require_once 'formatter.php';

$errors = array();
$erred = false;

// ---------------
// User functions.
// ---------------

$tinybbs = array();

$actions = array(
    'advertise' => 'Inquiring about advertising.',
    'statistics' => 'Looking at board statistics.',
    'hot_topics' => 'Looking at the hottest topics.',
    'shuffle' => 'Doing a topic shuffle.',
    'bulletins' => 'Reading latest bulletins.',
    'bulletins_old' => 'Reading latest bulletins.',
    'bulletins_new' => 'Posting a new bulletin.',
    'events' => 'Checking out events.',
    'events_new' => 'Posting a new event.',
    'folks' => 'Looking at what other people are doing.',
    'ignore_list' => 'Editing their ignore list.',
    'notepad' => 'Reading or writing in their <a href="'.DOMAIN.'notepad">notepad</a>.',
    'topics' => 'Looking at older topics.',
    'dashboard' => 'Modifying their dashboard',
    'latest_replies' => 'Looking at latest replies.',
    'latest_bumps' => 'Checking out latest bumps.',
    'latest_topics' => 'Checking out latest topics.',
    'search' => 'Searching for a topic.',
    'stuff' => 'Looking at stuff.',
    'history' => 'Looking at post history.',
    'failed_postings' => 'Looking at post failures.',
    'watchlist' => 'Checking out their watchlist.',
    'restore_id' => 'Logging in.',
    'new_topic' => 'Creating a new topic.',
    'nonexistent_topic' => 'Trying to look at a non-existent topic.',
    'topic' => 'Reading in topic: <strong><a href="'.DOMAIN.'topic/{action_id}">{headline}</a></strong>',
    'replying' => 'Replying to topic: <strong><a href="'.DOMAIN.'topic/{action_id}">{headline}</a></strong>',
    'topic_trivia' => 'Reading <a href="'.DOMAIN.'trivia_for_topic/{action_id}">trivia for topic</a>: <strong><a href="'.DOMAIN.'topic/{action_id}">{headline}</a></strong>',
    'trash_can' => 'Going through the trash.',
    'status_check' => 'Doing a status check.',
    'banned' => 'Being banned.',
    'pm_compose' => 'Composing a private message.',
    'image_macro' => 'Creating an image macro.',
);

function sanitize($type, $val)
{
    switch ($type) {
        case 'uid':
        case 'profile':
            if (!allowed('open_profile')) {
                return;
            }
            break;
        case 'ip':
            if (!allowed('open_ip')) {
                return;
            }
            break;
    }

    return $val;
}

$permission_list = array(
    'delete', 'delete_image', 'ban_uid', 'ban_ip', 'lock_topic', 'stick_topic', 'edit_post', 'post_html',
    'open_profile', 'open_ip', 'open_modlog', 'mod_hyperlink', 'mod_pm',  'watch',
    'manage_search', 'manage_bulletins', 'manage_events', 'manage_defcon',
    'manage_reports', 'manage_reporting', 'undelete', 'exterminate', 'manage_cms', 'nuke_uids', 'nuke_posts',
    'minecraft', 'set_image', 'set_time',
);

function get_all_permissions()
{
    global $permission_list;

    return $permission_list;
}

function allowed($permission, $uid = null)
{
    global $permissions, $link, $administrators;
    if (!$link) {
        return false;
    }

    if (!$uid) {
        $uid = $_SESSION['UID'];
    }

    if (in_array($uid, $administrators) && $permission != 'minecraft') {
        return true;
    }

    if (!is_array($permissions)) {
        $permissions = array();
    }

    if (!is_array($permissions[$uid])) {
        $permissions[$uid] = array();
        $link->db_exec('SELECT permission FROM permissions WHERE uid = %1', $uid);
        while ($row = $link->fetch_row()) {
            $permissions[$uid][] = $row[0];
        }
    }

    return in_array(strtolower($permission), $permissions[$uid]);
}

function delete_citation($id)
{
    global $link;
    if (!is_array($id)) {
        $id = array($id);
    }

    $link->db_exec('SELECT uid, reply FROM citations WHERE reply IN('.implode(',', $id).')');
    $citations_sent = array();
    while ($row = $link->fetch_row()) {
        if (in_array($row[1], $citations_sent)) {
            continue;
        }
        remove_notification('citation', $row[0], $row[1]);
        $citations_sent[] = $row[1];
    }

    $link->db_exec('DELETE FROM citations WHERE reply IN('.implode(',', $id).')');
}

function add_notification($event, $target, $identifier, $data, $parent_id = null)
{
    global $link;

    //_send_notification('put', array('event'=>$event, 'target'=>$target, 'identifier'=>$identifier, 'data'=>json_encode($data), 'parent_id'=>$parent_id));

    sendMessageToChrome($target, $event, $data);

    $link->db_exec('SELECT token FROM android_tokens WHERE uid = %1', $target);
    if ($link->num_rows() > 0) {
        $result = $link->fetch_assoc();
        if (!is_array($data)) {
            $data = array('data' => $data);
        }
        sendMessageToPhone($result['token'], $event.'_'.$identifier, array('event' => $event, 'parent_id' => $parent_id, 'identifier' => $identifier) + $data);

        $link->show_errors = false;
        $link->db_exec('INSERT INTO stored_notifications (event, target, identifier, parent_id) VALUES (%1, %2, %3, %4)', $event, $target, $identifier, $parent_id);
        $link->show_errors = true;
    }
}

function remove_notification($event, $target, $identifier, $parent_id = null)
{
    global $link, $administrator;
    //_send_notification('del', array('event'=>$event, 'target'=>$target, 'identifier'=>$identifier, 'parent_id'=>$parent_id));

    $link->db_exec('SELECT token FROM android_tokens WHERE uid = %1', $target);
    if ($link->num_rows() > 0) {
        $result = $link->fetch_assoc();
        $link->db_exec('DELETE FROM stored_notifications WHERE (event = %1 AND target = %2) AND (identifier = %3 OR parent_id = %4)', $event, $target, $identifier, $parent_id);
        if ($link->affected_rows() > 0) {
            sendMessageToPhone($result['token'], $event.'_'.$identifier, array('remove' => 'yes', 'event' => $event, 'identifier' => $identifier, 'parent_id' => $parent_id));
        }
    }
}

function topic_notification($headline, $author, $snippet, $link)
{
    _send_notification('topic', array('headline' => $headline, 'author' => $author, 'snippet' => $snippet, 'link' => $link));
}

function _send_notification($method, $data, $tryagain = true)
{
    global $administrator, $link;

    return true;

    //if(!$administrator) return true;

    $header[] = 'secret: '.NODE_SECRET;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, NODE_SERVER.'/'.$method.'?'.http_build_query($data, '', '&'));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode != 200 && $tryagain) {
        return _send_notification($method, $data, false); // Try one more time before generating an error
    } elseif ($httpCode != 200) {
        $link->insert('private_messages', array(
            'source' => 'node',
            'destination' => NODE_ERROR_RECIPIENT,
            'time' => time(),
            'expiration' => 0,
            'read' => 0,
            'can_reply' => 0,
            'contents' => '_send_notification ('.$method.') encountered an error. The HTTP response code was '.((int) $httpCode).' and the body of the response was: "'.((string) $response).'". The curl error ('.curl_errno($ch).') was: '.curl_error($ch)."\r\n\r\nThe data we tried to send: ".print_r($data, true),
        ));

        $ret = false;
    } else {
        $ret = true;
    }

    curl_close($ch);

    return $ret;
}

function print_statistics($uid, $public = true)
{
    global $link;
    $statistics = array();

    $uid = $link->escape($uid);

    $query = array();
    $query['num_topics_deleted'] = 'SELECT count(*) FROM topics WHERE deleted = 1;';
    $query['num_replies_deleted'] = 'SELECT count(*) FROM replies WHERE deleted = 1;';
    $query['num_topics_all_anon'] = "SELECT count(*) FROM topics WHERE namefag = '' AND tripfag = '';";
    $query['num_replies_all_anon'] = "SELECT count(*) FROM replies WHERE namefag = '' AND tripfag = '';";
    $query['num_topics'] = 'SELECT count(*) FROM topics WHERE deleted = 0;';
    $query['num_replies'] = 'SELECT count(*) FROM replies WHERE deleted = 0;';
    $query['num_bans'] = 'SELECT count(*) FROM uid_bans;';
    $query['your_topics_deleted'] = "SELECT count(*) FROM topics WHERE deleted = 1 AND author = '$uid';";
    $query['your_replies_deleted'] = "SELECT count(*) FROM replies WHERE deleted = 1 AND author = '$uid';";
    $query['your_topics_all_anon'] = "SELECT count(*) FROM topics WHERE author = '$uid' AND namefag = '' AND tripfag = '';";
    $query['your_replies_all_anon'] = "SELECT count(*) FROM replies WHERE author = '$uid' AND namefag = '' AND tripfag = '';";
    $query['your_topics'] = "SELECT count(*) FROM topics WHERE deleted = 0 AND author = '$uid';";
    $query['your_replies'] = "SELECT count(*) FROM replies WHERE deleted = 0 AND  author = '$uid';";
    $query['num_ip_bans'] = 'SELECT count(*) FROM ip_bans;';
    $query['topics_per_user'] = 'SELECT AVG(posts) FROM (SELECT COUNT(id) AS posts FROM topics WHERE deleted = 0 GROUP BY author) AS inline';
    $query['replies_per_user'] = 'SELECT AVG(posts) FROM (SELECT COUNT(id) AS posts FROM replies WHERE deleted = 0 GROUP BY author) AS inline';
    $query['first_seen'] = "SELECT first_seen FROM users WHERE uid = '$uid'";
    $query['posters_per_topic'] = 'SELECT AVG(PosterCount) FROM (SELECT MAX(poster_number) AS PosterCount FROM replies GROUP BY parent_id) as t';
    $query['your_posters_per_topic'] = "SELECT AVG(PosterCount) FROM (SELECT MAX(r.poster_number) AS PosterCount FROM replies AS r, topics as t WHERE r.parent_id = t.id AND t.author = '$uid' AND t.deleted = 0 AND r.deleted = 0 GROUP BY parent_id) as t";

    $query['replies_to_your_topics'] = "SELECT AVG(replies) FROM topics WHERE author = '$uid' AND deleted = 0";
    $query['replies_to_your_topics_all'] = "SELECT AVG(replies) FROM topics WHERE author = '$uid'";

    foreach ($query as $k => $q) {
        if(!($data = apcu_fetch($q))) {
            $result = $link->db_exec($q);
            $row = $link->fetch_row($result);
            $data = $row[0];
            apcu_store($q, $data, 600);
        }

        $statistics[$k] = $data;
    }

    extract($statistics);

    $days_since_first_seen = floor(($_SERVER['REQUEST_TIME'] - $first_seen) / 86400);
    if ($days_since_first_seen == 0) {
        $days_since_first_seen = 1;
    }

    $num_topics_all = $num_topics + $num_topics_deleted;
    $num_replies_all = $num_replies + $num_replies_deleted;

    $your_topics_all = $your_topics + $your_topics_deleted;
    $your_replies_all = $your_replies + $your_replies_deleted;

    $posts_per_user = $topics_per_user + $replies_per_user;
    $replies_per_topic_all = round($num_replies_all / $num_topics_all, 2);
    $replies_per_topic = round($num_replies / $num_topics, 2);
    $your_posts_all = $your_topics_all + $your_replies_all;
    $your_posts_all_deleted = $your_topics_deleted + $your_replies_deleted;
    $your_posts_all_anon = $your_topics_all_anon + $your_replies_all_anon;
    $your_posts = $your_topics + $your_replies;
    $total_posts = $num_topics + $num_replies;
    $total_posts_all = $num_topics_all + $num_replies_all;
    $total_posts_deleted = $num_topics_deleted + $num_replies_deleted;
    $total_posts_anon = $num_topics_all_anon + $num_replies_all_anon;
    $days_since_start = floor(($_SERVER['REQUEST_TIME'] - SITE_FOUNDED) / 86400);
    if ($days_since_start == 0) {
        $days_since_start = 1;
    }
    $posts_per_day_all = round($total_posts_all / $days_since_start, 2);
    $topics_per_day_all = round($num_topics_all / $days_since_start, 2);
    $replies_per_day_all = round($num_replies_all / $days_since_start, 2);
    $posts_per_day = round($total_posts / $days_since_start, 2);
    $topics_per_day = round($num_topics / $days_since_start, 2);
    $replies_per_day = round($num_replies / $days_since_start, 2);

    $your_posts_day = round($your_posts / $days_since_first_seen, 2);
    $your_replies_day = round($your_replies / $days_since_first_seen, 2);
    $your_topics_day = round($your_topics / $days_since_first_seen, 2);

    $your_posts_day_all = round($your_posts_all / $days_since_first_seen, 2);
    $your_replies_day_all = round($your_replies_all / $days_since_first_seen, 2);
    $your_topics_day_all = round($your_topics_all / $days_since_first_seen, 2);

    if ($your_topics > 0) {
        $your_ratio = '1:'.round($your_replies / $your_topics, 2);
        $your_ratio_all = '1:'.round($your_replies_all / $your_topics_all, 2);
    } else {
        $your_ratio = '-';
        $your_ratio_all = '-';
    }

    if ($num_topics > 0) {
        $ratio = '1:'.round($num_replies / $num_topics, 2);
        $ratio_all = '1:'.round($num_replies_all / $num_topics_all, 2);
    } else {
        $ratio = '-';
        $ratio_all = '-';
    }
    ?>
	<?php if ($public) {
    ?>
	<table>
		<tr>
			<th></th>
			<th class="minimal">Amount</th>
			<th>Comment</th>
		</tr>
		<tr class="odd">
			<th class="minimal">Total existing posts</th>
			<td class="minimal"><?php echo format_number($total_posts) ?></td>
			<td><span class="unimportant"><?php echo format_number($total_posts_anon) ?> anonymous, <?php echo format_number($total_posts_deleted) ?> deleted.</span></td>
		</tr>
		<tr>
			<th class="minimal">Existing topics</th>
			<td class="minimal"><?php echo format_number($num_topics) ?></td>
			<td><span class="unimportant"><?php echo format_number($num_topics_all_anon) ?> anonymous, <?php echo format_number($num_topics_deleted) ?> deleted.</span></td>
		</tr>
		<tr class="odd">
			<th class="minimal">Existing replies</th>
			<td class="minimal"><?php echo format_number($num_replies) ?></td>
			<td><span class="unimportant"><?php echo format_number($num_replies_all_anon) ?> anonymous, <?php echo format_number($num_replies_deleted) ?> deleted.</span></td>
		</tr>
		<tr>
			<th class="minimal">Ratio topics/replies</th>
			<td class="minimal"><?php echo $ratio ?></td>
			<td><span class="unimportant"><?php echo $ratio_all ?> including deleted posts.</span></td>
		</tr>
		<tr class="odd">
			<th class="minimal">Posts/day</th>
			<td class="minimal">~<?php echo format_number($posts_per_day, 2) ?></td>
			<td><span class="unimportant">~<?php echo format_number($posts_per_day_all, 2) ?> including deleted posts.</span></td>
		</tr>
		<tr>
			<th class="minimal">Topics/day</th>
			<td class="minimal">~<?php echo format_number($topics_per_day, 2) ?></td>
			<td><span class="unimportant">~<?php echo format_number($topics_per_day_all, 2) ?> including deleted topics.</span></td>
		</tr>
		<tr class="odd">
			<th class="minimal">Replies/day</th>
			<td class="minimal">~<?php echo format_number($replies_per_day, 2) ?></td>
			<td><span class="unimportant">~<?php echo format_number($replies_per_day_all, 2) ?> including deleted replies.</span></td>
		</tr>
		<tr>
			<th class="minimal">Posts/user</th>
			<td class="minimal">~<?php echo format_number($posts_per_user, 2) ?></td>
			<td><span class="unimportant">-</span></td>
		</tr>
		<tr class="odd">
			<th class="minimal">Replies/user</th>
			<td class="minimal">~<?php echo format_number($replies_per_user, 2) ?></td>
			<td><span class="unimportant">-</span></td>
		</tr>
		<tr>
			<th class="minimal">Topics/user</th>
			<td class="minimal">~<?php echo format_number($topics_per_user, 2) ?></td>
			<td><span class="unimportant">-</span></td>
		</tr>
		<tr class="odd">
			<th class="minimal">Users/topic</th>
			<td class="minimal">~<?php echo format_number($posters_per_topic, 2) ?></td>
			<td><span class="unimportant">-</span></td>
		</tr>
		<tr>
			<th class="minimal">Temporarily banned IDs</th>
			<td class="minimal"><?php echo format_number($num_bans) ?></td>
			<td>-</td>
		</tr>
		<tr class="odd">
			<th class="minimal">Banned IP addresses</th>
			<td class="minimal"><?php echo format_number($num_ip_bans) ?></td>
			<td>-</td>
		</tr>
		<tr>
			<th class="minimal">Days since launch</th>
			<td class="minimal"><?php echo number_format($days_since_start) ?></td>
			<td>We went live on <?php echo date('Y-m-d', SITE_FOUNDED).', '.calculate_age(SITE_FOUNDED) ?> ago.</td>
		</tr>
	</table>
	<?php
}
    ?>
	<table>
		<tr>
			<th></th>
			<th class="minimal">Amount</th>
			<th>Comment</th>
		</tr>
		<?php if ($public) {
    ?>
		<tr>
			<th class="minimal">Days since your first visit</th>
			<td class="minimal"><?php echo number_format($days_since_first_seen) ?></td>
			<td>We first saw you on <?php echo date('Y-m-d', $first_seen).', '.calculate_age($first_seen) ?> ago.</td>
		</tr>
		<tr class="odd">
			<th class="minimal">Total posts by you</th>
			<td class="minimal"><?php echo format_number($your_posts) ?></td>
			<td><span class="unimportant"><?php echo format_number($your_posts_all_anon) ?> anonymous, <?php echo format_number($your_posts_all_deleted) ?> deleted.</span></td>
		</tr>
		<tr>
			<th class="minimal">Total topics by you</th>
			<td class="minimal"><?php echo format_number($your_topics) ?></td>
			<td><span class="unimportant"><?php echo format_number($your_topics_all_anon) ?> anonymous, <?php echo format_number($your_topics_deleted) ?> deleted.</span></td>
		</tr>
		<tr class="odd">
			<th class="minimal">Total replies by you</th>
			<td class="minimal"><?php echo format_number($your_replies) ?></td>
			<td><span class="unimportant"><?php echo format_number($your_replies_all_anon) ?> anonymous, <?php echo format_number($your_replies_deleted) ?> deleted.</span></td>
		</tr>
		<?php
}
    ?>
		<tr>
			<th class="minimal">Ratio topics/replies by you</th>
			<td class="minimal"><?php echo $your_ratio ?></td>
			<td><span class="unimportant"><?php echo $your_ratio_all ?> including deleted posts.</span></td>
		</tr>
		<tr class="odd">
			<th class="minimal">Average replies to your topics</th>
			<td class="minimal"><?php echo format_number($replies_to_your_topics, 2) ?></td>
			<td><span class="unimportant"><?php echo format_number($replies_to_your_topics_all, 2) ?> including deleted posts.</span></td>
		</tr>
		<tr>
			<th class="minimal">Average users/topic by you</th>
			<td class="minimal"><?php echo format_number($your_posters_per_topic, 2) ?></td>
			<td><span class="unimportant">-</span></td>
		</tr>
		<tr class="odd">
			<th class="minimal">Average posts/day by you</th>
			<td class="minimal"><?php echo format_number($your_posts_day, 2) ?></td>
			<td><span class="unimportant">~<?php echo format_number($your_posts_day_all, 2) ?> including deleted posts.</span></td>
		</tr>
		<tr>
			<th class="minimal">Average topics/day by you</th>
			<td class="minimal"><?php echo format_number($your_topics_day, 2) ?></td>
			<td><span class="unimportant">~<?php echo format_number($your_topics_day_all, 2) ?> including deleted posts.</span></td>
		</tr>
		<tr class="odd">
			<th class="minimal">Average replies/day by you</th>
			<td class="minimal"><?php echo format_number($your_replies_day, 2) ?></td>
			<td><span class="unimportant">~<?php echo format_number($your_replies_day_all, 2) ?> including deleted posts.</span></td>
		</tr>
	</table>
	<?php

}

function sendMessageToChrome($uid, $type, $data)
{
    global $link;

    if (!defined('CHROME_TOKEN')) {
        return;
    }
    // The push events don't support data yet
    $subscriptions = array();

    $link->db_exec('SELECT subscription_id FROM chrome_tokens WHERE uid = %1', $uid);
    while (($row = $link->fetch_assoc()) != null) {
        $subscriptions[] = $row['subscription_id'];
    }

    if (count($subscriptions) == 0) {
        return;
    }

    $headers = array(
        'Authorization: key='.CHROME_TOKEN,
        'Content-Type: application/json',
    );

    $post = array(
        'registration_ids' => $subscriptions,
    );

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://android.googleapis.com/gcm/send');
    if ($headers) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

function sendMessageToPhone($deviceRegistrationId, $msgType, $extraParams)
{
    global $link;

    if (!defined('GOOGLE_TOKEN')) {
        return;
    }

    $headers = array('Authorization: GoogleLogin auth='.GOOGLE_TOKEN);
    $data = array(
        'registration_id' => $deviceRegistrationId,
        'collapse_key' => $msgType,
    );

    if (is_array($extraParams)) {
        foreach ($extraParams as $k => $v) {
            $data['data.'.$k] = $v;
        }
    }

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, 'https://android.apis.google.com/c2dm/send');
    if ($headers) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    $response = curl_exec($ch);

    curl_close($ch);

    if (stripos($response, 'InvalidRegistration') !== false || stripos($response, 'NotRegistered')) {
        $link->db_exec('DELETE FROM android_tokens WHERE token = %1', $deviceRegistrationId);
    }

    return $response;
}

function check_gold($uid)
{
    //	return false;
    global $link;
    static $gold_accounts;
    if (!is_array($gold_accounts)) {
        $gold_accounts = array();
    }
    if (in_array($uid, $gold_accounts)) {
        return true;
    }
    $link->db_exec('SELECT expires FROM gold_accounts WHERE UID = %1', $uid);
    if ($link->num_rows() > 0) {
        $gold_accounts[] = $uid;

        return true;
    }

    return false;
}

function check_proxy($extra = false)
{
    //	return false;
    global $hostaddr;

    if ($extra === false && isset($_SESSION['proxy'])) {
        return $_SESSION['proxy'];
    }

    if (!$hostaddr) {
        $hostaddr = gethostbyaddr($_SERVER['REMOTE_ADDR']);
    }

    /*$ch = curl_init();
    curl_setopt($ch,CURLOPT_URL, "http://www.stopforumspam.com/api?ip=".urlencode($_SERVER['REMOTE_ADDR'])."&f=serial");
    curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 5);
    $stopforumspam = unserialize(curl_exec($ch));
    curl_close($ch);*/

    if (!$hostaddr
    || $hostaddr == '.'
    //|| !$_SERVER['HTTP_ACCEPT_ENCODING'] // Maybe disable in case of false negatives?
    || $_SERVER['HTTP_X_FORWARDED_FOR']
    || $_SERVER['HTTP_X_FORWARDED']
    || $_SERVER['HTTP_FORWARDED_FOR']
    || $_SERVER['HTTP_VIA']
    || in_array($_SERVER['REMOTE_PORT'], array(8080, 80, 6588, 8000, 3128, 553, 554))
    //|| !$_SERVER['HTTP_CONNECTION']
    || (stripos($hostaddr, 'tor-exit') !== false)
    || (stripos($hostaddr, 'torserversnet') !== false)
    || (stripos($hostaddr, 'anonymizer') !== false)
    || (stripos($hostaddr, 'mycingular.net') !== false)
    || (stripos($hostaddr, 'ipredate.net') !== false)
    || (stripos($hostaddr, 'ipredator.se') !== false)
    || (stripos($hostaddr, 'proxy') !== false)
    || (stripos($hostaddr, '.info') !== false)
    || (stripos($hostaddr, 'ioflood.com') !== false)
    || (stripos($hostaddr, 'linode.com') !== false)
    || (stripos($hostaddr, 'rackcentre') !== false)
    //|| ($stopforumspam["ip"]["appears"])
    || IsTorExitPoint()
    || (strpos($hostaddr, '.') !== false && substr_count($hostaddr, '.') < 2)
    ) {
        $_SESSION['proxy'] = true;

        return true;
    }

    $_SESSION['proxy'] = false;

    return false;
}

function img_url_data($url)
{
    global $disable_errors;
    $parts = parse_url($url);
    $path = (isset($parts['path'])) ? $parts['path'] : '';
    switch ($parts['host']) {
        case 'img.imgur.com':
        case 'i.imgur.com':
        case 'imgur.com':
            preg_match('%/*([A-Za-z0-9]+)\.([a-zA-Z]+)%i', $path, $regs);
            $name = trim($regs[0], '/');
            $name = $regs[1];
            $ext = $regs[2];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); //not necessary unless the file redirects (like the PHP example we're using here)
            $data = curl_exec($ch);
            curl_close($ch);

            $contentLength = -1;
            if (preg_match('/Content-Length: (\d+)/', $data, $matches)) {
                $contentLength = (int) $matches[1];
            }

            if ($ext == 'gif' && $contentLength > 0 && $contentLength < 800 * 1024) {
                $thumb = $name.'.'.$ext;
            } else {
                $thumb = $name.'m.'.$ext;
            }

            $thumburl = 'https://'.$parts['host'].'/'.urlencode($thumb);

            $disable_errors = true;
            $thumbsize = getimagesize($thumburl);

            $disable_errors = false;

            if (!$thumbsize) {
                return;
            } // Invalid URL

            $thumbsize = normalize_thumb_size($thumbsize);

            return array('thumb_width' => $thumbsize[0], 'thumb_height' => $thumbsize[1], 'imgurl' => $url, 'thumburl' => $thumburl);
        break;
        default:
            return;
    }

    return 1;
}

function normalize_thumb_size($thumbsize)
{
    $width = $thumbsize[0];
    $height = $thumbsize[1];

    $biggest = max($width, $height);

    if ($biggest > MAX_IMAGE_DIMENSIONS) {
        $scale = MAX_IMAGE_DIMENSIONS / $biggest;
    } else {
        $scale = 1;
    }

    return array($width * $scale, $height * $scale);
}

function detect_spam($haystack)
{
    global $spam_phrases, $arrChars;
    $haystack = detectFormatter($haystack)->formatAsText($haystack, false, false);
    $haystack = preg_replace('/\s/', '', $haystack);
    foreach ($arrChars as $to => $chars) {
        $haystack = str_replace($chars, $to, $haystack);
    }

    foreach ($spam_phrases as $phrase) {
        if (detect_phrase($phrase, $haystack)) {
            return true;
        }
    }

    return false;
}

function detect_phrase($needle, $haystack)
{
    //$needle = preg_replace('/((?<!\\\\)[a-z])/m', '\1{1,2}.{0,2}', $needle);
    $needle = str_replace('/', '\\/', $needle);

    if (preg_match('/'.$needle.'/si', $haystack)) {
        return true;
    } else {
        return false;
    }
}

function modname($uid)
{
    global $administrators, $moderators, $janitors;
    static $join;
    if (!$join) {
        $join = array_merge($administrators, $moderators, $janitors);
        $join = array_flip($join);
    }

    if (!strpos($uid, '.')) {
        return $uid;
    }

    if (array_key_exists($uid, $join)) {
        return $join[$uid];
    } else {
        return 'a user';
    }
}

function getcache($key, &$exists = '')
{
    if (function_exists('apc_fetch')) {
        return apc_fetch($key, $exists);
    } else {
        $exists = false;

        return;
    }
}

function delcache($key)
{
    if (function_exists('apc_delete')) {
        return apc_delete($key);
    } else {
        return false;
    }
}

function setcache($key, $value, $expire)
{
    if (function_exists('apc_store')) {
        apc_store($key, $value, $expire);

        return true;
    } else {
        return false;
    }
}

function recaptcha_inline()
{
    ?>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_PUBLIC_KEY;
    ?>"></div>
<noscript>
  <div style="width: 302px; height: 352px;">
    <div style="width: 302px; height: 352px; position: relative;">
      <div style="width: 302px; height: 352px; position: absolute;">
        <iframe src="https://www.google.com/recaptcha/api/fallback?k=<?php echo RECAPTCHA_PUBLIC_KEY;
    ?>"
                frameborder="0" scrolling="no"
                style="width: 302px; height:352px; border-style: none;">
        </iframe>
      </div>
      <div style="width: 250px; height: 80px; position: absolute; border-style: none;
                  bottom: 21px; left: 25px; margin: 0px; padding: 0px; right: 25px;">
        <textarea id="g-recaptcha-response" name="g-recaptcha-response"
                  class="g-recaptcha-response"
                  style="width: 250px; height: 80px; border: 1px solid #c1c1c1;
                         margin: 0px; padding: 0px; resize: none;" value=""></textarea>
      </div>
    </div>
  </div>
</noscript><?php
    // require_once("includes/recaptchalib.php");
    // echo recaptcha_get_html(RECAPTCHA_PUBLIC_KEY);
}

function recaptcha_valid()
{
    $param = urlencode($_POST['g-recaptcha-response']);
    $remoteip = urlencode($_SERVER['REMOTE_ADDR']);
    $secret = RECAPTCHA_PRIVATE_KEY;

    $ch = curl_init("https://www.google.com/recaptcha/api/siteverify?secret=$secret&response=$param&remoteip=$remoteip");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // This is bad but if anyone cares enough to mitm i think we have bigger problems
    $output = curl_exec($ch);
    curl_close($ch);

    if (!$output) {
        return false;
    }
    $json = json_decode($output);
    if (!$json || !$json->success === true) {
        return false;
    }

    return true;

    // require_once("includes/recaptchalib.php");
    // if ($_POST["recaptcha_response_field"]) {
        // $resp = recaptcha_check_answer (RECAPTCHA_PRIVATE_KEY,
                                        // $_SERVER["REMOTE_ADDR"],
                                        // $_POST["recaptcha_challenge_field"],
                                        // $_POST["recaptcha_response_field"]);

        // if ($resp->is_valid) {
            // return true;
        // } else {
            // return false;
        // }
    // }else{
        // return false;
    // }
}

function recaptcha($extra = '')
{
    require_once 'includes/recaptchalib.php';
    if ($_POST['recaptcha_response_field']) {
        $resp = recaptcha_check_answer(RECAPTCHA_PRIVATE_KEY,
                                        $_SERVER['REMOTE_ADDR'],
                                        $_POST['recaptcha_challenge_field'],
                                        $_POST['recaptcha_response_field']);

        if ($resp->is_valid) {
            unset($_POST['recaptcha_challenge_field']);
            unset($_POST['recaptcha_response_field']);

            return true;
        } else {
            # set the error code so that we can display it
                $error = $resp->error;
        }
    }
    ob_end_clean();
    echo "<html><head></head><body><form action='' method='post'>";
    echo (!$extra) ? RECAPTCHA_NOTICE : $extra;
    echo recaptcha_get_html(RECAPTCHA_PUBLIC_KEY, $error);
    echo "<input type='submit' value='Enter it!' />";
    foreach ($_POST as $k => $v) { // Let the user resume as intended
        if ($k == 'recaptcha_challenge_field' || $k == 'recaptcha_response_field') {
            continue;
        }
        echo "<input type='hidden' name='".htmlentities($k)."' value='".htmlentities($v)."' />";
    }
    $topics = '';

    echo '</form></body></html>';
    die();
}

function nameAndTripcode($name)
{
    global $vanity_trips;

    if (is_array($name)) {
        $name = implode(' ', $name);
    }
    if (mb_ereg('(.*)###(.*)', $name, $regs)) {
        if (isset($vanity_trips[$regs[2]])) {
            return array($regs[1], ' !'.$vanity_trips[$regs[2]]);
        }
    }

    if (mb_ereg('(#|!)(.*)', $name, $regs)) {
        $cap = $regs[2];
        $cap_full = '#'.$regs[2];

        if (function_exists('mb_convert_encoding')) {
            $recoded_cap = mb_convert_encoding($cap, 'SJIS', 'UTF-8');
            if ($recoded_cap != '') {
                $cap = $recoded_cap;
            }
        }

        if (strpos($name, '#') === false) {
            $cap_delimiter = '!';
        } elseif (strpos($name, '!') === false) {
            $cap_delimiter = '#';
        } else {
            $cap_delimiter = (strpos($name, '#') < strpos($name, '!')) ? '#' : '!';
        }

        if (substr($cap, 0, 1) == '#') {
            $cap = substr($cap, 1);
            $is_secure_trip = true;
        } else {
            $is_secure_trip = false;
        }
        $tripcode = '';
        if ($cap != '') {
            $cap = strtr($cap, '&', '&');
            $cap = strtr($cap, ',', ', ');
            $salt = substr($cap.'H.', 1, 2);
            $salt = preg_replace("([^\.-z])", '.', $salt);
            $salt = strtr($salt, ':;<=>?@[\\]^_`', 'ABCDEFGabcdef');
            $tripcode = substr(crypt($cap, $salt), -10);
        }

        if ($is_secure_trip) {
            if ($cap != '') {
                $tripcode = '!';
            }
            $salt = TRIPSEED.'B5]';
            $password = str_replace('&', '&', $cap);
            $password = str_replace(',', ',', $cap);
            $hash = md5($salt.$cap, true);
            $sub = substr(base64_encode(substr($hash, 0, 8)), 0, 10);
            $tripcode = '!'.$sub;

            //	(TRIPSEED) ? $seed = TRIPSEED : $seed = '';
            //	$tripcode = "!" . substr(md5($cap . $seed), 2, 10);
        }

        if ($tripcode == '') {
            return array(preg_replace('(('.$cap_delimiter.')(.*))', '', $name), ' !faggot');
        }

        return array(preg_replace('(('.$cap_delimiter.')(.*))', '', $name), ' !'.$tripcode);
    }

    return array($name, '');
}

function create_id()
{
    global $link, $DEFCON, $proxy, $async;
    if (DEFCON < 5 || $proxy) {
        return false;
    } // DEFCON 4.

    /*if(ENABLE_RECAPTCHA_ON_BOT){
        $link->db_exec("SELECT 1 FROM users WHERE ip_address = %1 AND last_seen > (UNIX_TIMESTAMP()-3600)", $_SERVER['REMOTE_ADDR']);
        $uids_recent = $link->num_rows();
        if($uids_recent > RECAPTHCA_MAX_UIDS_PER_HOUR) {
            recaptcha("Please enable cookies for this site. Enter the following captcha to continue. If you have cookies enabled and keep getting this message, try removing the ones set for <b>minichan.org</b> and <b>.minichan.org</b>. We apologize for the inconvience.");
        }
    }*/

/*	$fh = fopen("includes/creates.log", "a");
    fwrite($fh, time() . " " . $_SERVER['REMOTE_ADDR'] . " " . $_SERVER['REQUEST_URI'] . " " . serialize($_COOKIE) . " " . serialize($_SESSION) . "\r\n");
    fclose($fh);*/

    $user_id = uniqid('', true);
    $password = generate_password();

    $stmt = $link->db_exec('INSERT INTO users (uid, password, ip_address, first_seen) VALUES (%1, %2, %3, UNIX_TIMESTAMP())', $user_id, $password, $_SERVER['REMOTE_ADDR']);

    $_SESSION['first_seen'] = $_SERVER['REQUEST_TIME'];

    setcookie('UID', $user_id, $_SERVER['REQUEST_TIME'] + 315569260, '/', COOKIE_DOMAIN);
    $_COOKIE['UID'] = $user_id;
    setcookie('password', $password, $_SERVER['REQUEST_TIME'] + 315569260, '/', COOKIE_DOMAIN);
    $_COOKIE['password'] = $password;
    $_SESSION['UID'] = $user_id;

    $async->checkUID($user_id, $_SERVER['REMOTE_ADDR']);
}

function generate_password()
{
    $characters = str_split('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789');
    $password = '';

    for ($i = 0; $i < 32; ++$i) {
        $password .= $characters[array_rand($characters)];
    }

    return $password;
}

function activate_id()
{
    global $link;

    $stmt = $link->db_exec('SELECT password, first_seen FROM users WHERE uid = %1', $_COOKIE['UID']);
    list($db_password, $first_seen) = $link->fetch_row($stmt);

    if (!empty($db_password) && $_COOKIE['password'] === $db_password) {
        // The password is correct!
        $_SESSION['UID'] = $_COOKIE['UID'];
        // Our ID wasn't just created.
        $_SESSION['ID_activated'] = true;
        // For post.php
        $_SESSION['first_seen'] = $first_seen;

        return true;
    }
    // If the password was wrong, create a new ID.
    create_id();
}

function force_id($proxy_value = null, $redirect = true, $allow = false)
{
    global $link, $hostaddr;

    if ($_SESSION['ID_activated'] && $_SESSION['UID']) {
        return true;
    }

    if ($proxy_value != null) {
        $proxy = $proxy_value;
    } else {
        $proxy = check_proxy(true);
    }

    if (!$hostaddr) {
        $hostaddr = gethostbyaddr($_SERVER['REMOTE_ADDR']);
    }

    if (!isset($_SESSION['ID_activated']) || empty($_COOKIE['UID']) || empty($_COOKIE['password'])) {
        if (DEFCON < 5 && $redirect) { // DEFCON 4.
            $_SESSION['notice'] = DEFCON_4_MESSAGE;
            header('Location: '.DOMAIN);
            die();
        }

        if ($proxy) {
            add_error('UIDs cannot be created through proxies.', true);
            $nouid = true;
        }
    }

    if (check_user_agent('bot') || preg_match('/^msnbot|search\.msn\.com$|amazonaws\.com$|linode\.com$/', $hostaddr)) {
        $msg = 'You have been identified as a bot, so no internal UID will be assigned to you. If you are a real person messing with your useragent, you should change it back to something normal.';
        if (!$allow) {
            add_error($msg, true);
        } elseif (!$_SESSION['welcomed']) {
            $_SESSION['notice'] = $msg;
        }
        $nouid = true;
    }

    if ((empty($_COOKIE['UID']) || empty($_COOKIE['password'])) && !$proxy) {
        if (!$nouid) {
            create_id();
            activate_id();
        }
        /*
        echo '<html><head><script>function oload(){document.getElementById("myform").submit();}</head><body><form action="" method="post" id="myform">';
        foreach($_POST as $k=>$v){
            echo "<input type='hidden' name='$k' value='$v' />";
        }
        echo '<input type="submit" value="Click me!"></body></html>';

        die();
        */
    }

    if (!$allow && !$_SESSION['UID']) {
        add_error('An UID could not be created. Try clearing your cookies? :-/', true);
    }
}

function update_activity($action_name, $action_id = '')
{
    global $link;

    if (!isset($_SESSION['UID']) || !$_SESSION['UID']) {
        return false;
    }
    $values['time'] = 'UNIX_TIMESTAMP()';
    $values['uid'] = $_SESSION['UID'];
    $values['action_name'] = $action_name;
    $values['action_id'] = $action_id;
    $link->insertorupdate('activity', $values);
}

function id_exists($id)
{
    global $link;

    $uid_exists = $link->db_exec('SELECT 1 FROM users WHERE uid = %1', $_GET['id']);

    if ($link->num_rows($uid_exists) < 1) {
        return false;
    }

    return true;
}

function remove_id_ban($id)
{
    global $link;

    $remove_ban = $link->db_exec('DELETE FROM uid_bans WHERE uid = %1', $id);
}

function remove_ip_ban($ip)
{
    global $link;

    $remove_ban = $link->db_exec('DELETE FROM ip_bans WHERE ip_address = %1', $ip);
}

function fetch_ignore_list()
{ // For ostrich mode.
    global $link, $user_settings;

    if (!$user_settings['ostrich_mode']) {
        return array(
        'phrases' => array(),
        'names' => array(),
    );
    }

    $fetch_ignore_list = $link->db_exec('SELECT ignored_phrases, ignored_names FROM ignore_lists WHERE uid = %1', $_COOKIE['UID']);
    list($ignored_phrases, $ignored_names) = $link->fetch_row($fetch_ignore_list);

    // To make this work with Windows input, we need to strip out the return carriage.
    return array(
        'phrases' => explode("\n", str_replace("\r", '', $ignored_phrases)),
        'names' => explode("\n", str_replace("\r", '', $ignored_names)),
    );
}

function show_trash($uid, $silence = false)
{ // For profile and trash.
    global $link, $administrator;

    $fetch_trash = $link->db_exec(<<<EOF
	(
		SELECT "" as headline, replies.body, replies.id, replies.parent_id, mod_actions.time, mod_actions.mod_UID
		FROM replies
		LEFT JOIN mod_actions
			ON mod_actions.target = replies.id
			AND mod_actions.action = "delete_reply"
		WHERE replies.author = %1
		AND replies.deleted = 1
	) UNION ALL (
		SELECT topics.headline, topics.body, topics.id, "" as parent_id, mod_actions.time, mod_actions.mod_UID
		FROM topics
		LEFT JOIN mod_actions
			ON mod_actions.target = topics.id
			AND mod_actions.action = "delete_topic"
		WHERE topics.author = %1
		AND topics.deleted = 1
	)
	ORDER BY time desc
EOF
, $uid);

    $table = new table();
    $columns = array(
        'Headline',
        'Content',
        'Time since deletion ▲',
        'Deleted by',
    );

    if (!allowed('open_profile')) {
        unset($columns[3]);
    }
    $table->define_columns($columns, 'Headline');

    while (list($trash_headline, $trash_body, $id, $parent_id, $trash_time, $mod_uid) = $link->fetch_row($fetch_trash)) {
        if (!$trash_headline) {
            $trash_headline = '<span class="unimportant">(Reply.)</span>';
            $reply = true;
        } else {
            $trash_headline = htmlspecialchars($trash_headline);
            $reply = false;
        }

        if ($reply) {
            $action = 'delete_reply';
            $trash_headline = '<a href="'.DOMAIN.'topic/'.$parent_id.'#reply_'.$id.'">'.$trash_headline.'</a>';
        } else {
            $action = 'delete_topic';
            $trash_headline = '<a href="'.DOMAIN.'topic/'.$id.'">'.$trash_headline.'</a>';
        }

        $values = array(
            $trash_headline,
            snippet(nl2br(htmlspecialchars($trash_body)), 100),
            '<span class="help" title="'.format_date($trash_time).'">'.calculate_age($trash_time).'</span>',
            '<a href="'.DOMAIN.'profile/'.$mod_uid.'">'.modname($mod_uid).'</a>',
        );

        if (!allowed('open_profile')) {
            unset($values[3]);
        }

        if ($trash_time < 1) { // Not logged? :-/
            $values[2] = '<span class="unimportant">(Unknown)</span>';
        }

        $table->row($values);
    }

    if ($table->num_rows_fetched === 0) {
        return false;
    }

    return $table->output();
}

// -----------------
// Output functions.
// -----------------

// Prettify dynamic mark-up
function indent($num_tabs = 1)
{
    return "\n".str_repeat("\t", $num_tabs);
}

// Print a <table>. 100 rows takes ~0.0035 seconds on my computer.
class table
{
    public $num_rows_fetched = 0;
    private $output = '';
    private $primary_key;
    private $columns = array();
    private $table_tag;
    private $td_classes = array();
    private $marker_printed = false;
    private $last_seen = false;
    private $order_time = false;
    private $odd = false;

    public function __construct($classes='') {
        $this->table_tag = '<table class="'.$classes.'">';
    }

    public function define_columns($all_columns, $primary_column)
    {
        $this->columns = $all_columns;

        $this->output .= $this->table_tag
                      .indent().'<thead>'.indent(2).'<tr>';

        foreach ($all_columns as $key => $column) {
            $this->output .=   indent(3).' <th class="';
            if ($column != $primary_column) {
                $this->output .= 'minimal ';
            } else {
                $this->primary_key = $key;
            }
            $this->output .= string_to_stylesheet_class($column).'">'.$column.'</th>';
        }
        $this->output .=  indent(2).'</tr>'.indent().'</thead>'.indent().'<tbody>';
    }

    public function add_table_class($class)
    {
        $this->table_classes = $class;
    }

    public function add_td_class($column_name, $class)
    {
        $this->td_classes[$column_name] = $class;
    }

    public function last_seen_marker($last_seen, $order_time)
    {
        $this->last_seen = $last_seen;
        $this->order_time = $order_time;
    }

    public function row($values, $ignored = false)
    {
        // Print <tr>.
        $this->output .=  indent(2).'<tr';
        $classes = array();

        if ($this->odd) {
            $classes[] = 'odd';
        }

        if ($ignored) {
            $classes[] = 'ignored';
        }

        $this->output .=  ' class="'.implode(' ', $classes).'"';
        $this->odd = !$this->odd;

        // Print the last seen marker.
        if ($this->last_seen && !$this->marker_printed && $this->order_time <= $this->last_seen && !$ignored) {
            $this->marker_printed = true;
            if ($this->num_rows_fetched != 0) {
                $this->output .=  ' id="last_seen_marker"';
            }
        }
        $this->output .=  '>';

        // Print each <td>.
        foreach ($values as $key => $value) {
            $classes = array();
            $this->output .=  indent(3).'<td';

            // If this isn't the primary column (as set in define_columns()), its length should be minimal.
            if ($key !== $this->primary_key) {
                $classes[] = 'minimal';
            }
            //add class to this td
            //$classes[] = string_to_stylesheet_class($this->columns[$key]);

            // Check if a class has been added via add_td_class.
            if (isset($this->td_classes[ $this->columns[$key] ])) {
                $classes[] = $this->td_classes[$this->columns[$key]];
            }

            // Print any classes added by the above two conditionals.
            if (!empty($classes)) {
                $this->output .=  ' class="'.implode(' ', $classes).'"';
            }
            $this->output .=  '>'.$value.'</td>';
        }
        $this->output .=  indent(2).'</tr>';
        ++$this->num_rows_fetched;
    }

    public function output($items = 'items', $silence = false)
    {
        $this->output .=  indent().'</tbody>'."\n".'</table>'."\n";

        if ($this->num_rows_fetched > 0) {
            return $this->output;
        } elseif (!$silence) {
            return '<p>(No '.$items.' to show.)</p>';
        }
        // Silence.
        return '';
    }
}

function add_error($message, $critical = false)
{
    global $errors, $erred;

    $errors[] = $message;
    $erred = true;

    if ($critical) {
        print_errors(true);
    }
}

function print_errors($critical = false)
{
    global $errors, $_start_time, $link;

    $number_errors = count($errors);
    if ($number_errors > 0) {
        echo '<h3 id="error">';
        if ($number_errors > 1) {
            echo $number_errors.' errors';
        } else {
            echo 'Error';
        }
        echo '</h3><ul class="body standalone">';

        foreach ($errors as $error_message) {
            echo '<li>'.$error_message.'</li>';
        }
        echo '</ul>';

        if ($critical) {
            if (!isset($page_title)) {
                $page_title = 'Fatal error';
            }
            require 'footer.php';
            exit();
        }
    }
}

function page_navigation($section_name, $current_page, $num_items_fetched)
{
    $output = '';
    if ($current_page != 1) {
        $output .= indent().'<li><a href="'.DOMAIN.$section_name.'">Latest</a></li>';
    }
    if ($current_page != 1 && $current_page != 2) {
        $newer = $current_page - 1;
        $output .= indent().'<li><a href="'.DOMAIN.$section_name.'/'.$newer.'">Newer</a></li>';
    }
    if ($num_items_fetched >= ITEMS_PER_PAGE) {
        $older = $current_page + 1;
        $output .= indent().'<li><a href="'.DOMAIN.$section_name.'/'.$older.'">Older</a></li>';
    }
    if (!empty($output)) {
        echo "\n".'<ul class="menu">'.$output."\n".'<li><span class="reply_id unimportant"><a href="#top">[Top]</a></span></li></ul>'."\n";
    }
}

function edited_message($original_time, $edit_time, $edit_mod)
{
    if ($edit_time) {
        echo '<p class="unimportant">(Edited '.calculate_age($original_time, $edit_time).' later';
        if ($edit_mod) {
            echo ' by a moderator';
        }
        echo '.)</p>';
    }
}

function dummy_form()
{
    if (!isset($_SESSION['token'])) {
        $_SESSION['token'] = md5(SALT.mt_rand());
    }
    echo "\n".'<form id="dummy_form" class="noscreen" action="" method="post">'.indent().'<div class="noscreen"> <input type="hidden" name="CSRF_token" value="'.$_SESSION['token'].'" /> </div> <div> <input type="hidden" name="some_var" value="" /> </div>'."\n".'</form>'."\n";
}

// To redirect to index, use redirect($notice, ''). To redirect back to referrer,
// use redirect($notice). To redirect to /topic/1,  use redirect($notice, 'topic/1')
function redirect($notice = '', $location = null)
{
    if (!empty($notice)) {
        $_SESSION['notice'] = $notice;
    }

    if (!is_null($location) || empty($_SERVER['HTTP_REFERER'])) {
        $location = $location;
    } else {
        $location = $_SERVER['HTTP_REFERER'];
    }
    if (substr($location, 0, strlen(DOMAIN)) == DOMAIN) {
        $location = substr($location, strlen(DOMAIN));
    }
    header('Location: '.DOMAIN.$location);
    exit;
}

// -------------------
// Checking functions.
// -------------------

function check_length($text, $name, $min_length, $max_length)
{
    $text_length = strlen($text);

    if ($min_length > 0 && empty($text)) {
        add_error('The '.$name.' can not be blank.');
    } elseif ($text_length > $max_length) {
        add_error('The '.$name.' was '.number_format($text_length - $max_length).' characters over the limit ('.number_format($max_length).').');
    } elseif ($text_length < $min_length) {
        add_error('The '.$name.' was too short.');
    }
}

function IsTorExitPoint()
{
    if (gethostbyname(ReverseIPOctets($_SERVER['REMOTE_ADDR']).'.'.$_SERVER['SERVER_PORT'].'.'.ReverseIPOctets($_SERVER['SERVER_ADDR']).'.ip-port.exitlist.torproject.org') == '127.0.0.2') {
        return true;
    } else {
        return false;
    }
}
function ReverseIPOctets($inputip)
{
    $ipoc = explode('.', $inputip);

    return $ipoc[3].'.'.$ipoc[2].'.'.$ipoc[1].'.'.$ipoc[0];
}

function csrf_token()
{ // Prevent cross-site redirection forgeries, create token.
    if (!isset($_SESSION['token'])) {
        $_SESSION['token'] = md5(SALT.mt_rand());
    }
    echo '<div class="noscreen"> <input type="hidden" name="CSRF_token" id="CSRF_token" value="'.$_SESSION['token'].'" /> </div>'."\n";
}

function check_token()
{ // Prevent cross-site redirection forgeries, token check.
    if ($_REQUEST['CSRF_token'] !== $_SESSION['token']) {
        //add_error('CSRF token error, please try again. Please report the following information: "' . $_POST['CSRF_token'] . " !== " . $_SESSION['token'] . '"');
        add_error('Uh oh, some tubes got twisted! Please try again.');

        return false;
    }

    return true;
}

function parse($text)
{
    return detectFormatter($text)->formatAsHtml($text);
}

function snippet($text, $snippet_length = 80, $include_cites = false, $encode = true)
{
    if (!$include_cites) {
        $text = preg_replace('/(@|>)(.*)/m', '', $text);
    }

    $text = detectFormatter($text)->formatAsText($text, $include_cites, $encode);

    if (!$include_cites) {
        $text = str_replace(array("\r", "\n"), ' ', $text);
    } // Strip line breaks.

    if ($snippet_length == 80 && ctype_digit($_COOKIE['snippet_length'])) {
        $snippet_length = $_COOKIE['snippet_length'];
    }
    if (mb_strlen($text) > $snippet_length) {
        $text = mb_substr($text, 0, $snippet_length).($encode ? '&hellip;' : '...');
    }

    if (!$include_cites && !trim($text)) {
        $text = '~';
    }

    return $text;
}

function super_trim($text)
{
    static $nonprinting_characters;
    // Strip return carriage and non-printing characters.
    if (!$nonprinting_characters) {
        $nonprinting_characters = array(
            "\r",
            '­', //soft hyphen ( U+00AD)
            '﻿', // zero width no-break space ( U+FEFF)
            '​', // zero width space (U+200B)
            '‍', // zero width joiner (U+200D)
            '‌', // zero width non-joiner (U+200C)
        );
    }
    $text = str_replace($nonprinting_characters, '', $text);
     // Trim and kill excessive newlines (maximum of 3).

    return preg_replace('/(\r?\n[ \t]*){3,}/', "\n\n\n", trim($text));
}

function sanitize_for_textarea($text)
{
    detectFormatter($text);

    return htmlspecialchars($text);
    $text = str_ireplace('/textarea', '/textarea', $text);
    $text = str_replace('<!--', '<!--', $text);

    return $text;
}

$units = array(
                'second' => 60,
                'minute' => 60,
                'hour' => 24,
                'day' => 7,
                'week' => 4.25, // We don't like the Gregorian calendar.
                'month' => 12,
                );

function calculate_age($timestamp, $comparison = '')
{
    global $units;
    if (empty($comparison)) {
        $comparison = $_SERVER['REQUEST_TIME'];
    }
    $age_current_unit = abs($comparison - $timestamp);
    foreach ($units as $unit => $max_current_unit) {
        $age_next_unit = $age_current_unit / $max_current_unit;
        if ($age_next_unit < 1) { // Are there enough of the current unit to make one of the next unit?
            $age_current_unit = floor($age_current_unit);
            $formatted_age = $age_current_unit.' '.$unit;

            return $formatted_age.($age_current_unit == 1 ? '' : 's');
        }
        $age_current_unit = $age_next_unit;
    }

    $age_current_unit = round($age_current_unit, 1);
    $formatted_age = $age_current_unit.' year';

    return $formatted_age.(floor($age_current_unit) == 1 ? '' : 's');
}

function format_date($timestamp)
{
    return date('Y-m-d H:i:s \U\T\C — l \t\h\e jS \o\f F Y, g:i A', $timestamp);
}

function format_number($number, $decimals = 0)
{
    if ($number == 0) {
        return '-';
    }

    return number_format($number, $decimals);
}

function number_to_letter($number)
{
    $alphabet = range('A', 'Y');
    if ($number < 24) {
        return $alphabet[$number];
    }
    $number = $number - 23;

    return 'Z-'.$number;
}

function letter_to_number($letter)
{
    $letter = strtoupper($letter);
    $letter = str_replace(array('-', '_', ' '), '', $letter);
    $alphabet = range('A', 'Y');
    if (strlen($letter) == 1) {
        if (in_array($letter, $alphabet)) {
            return array_search($letter, $alphabet);
        } else {
            return -1;
        }
    } elseif (substr($letter, 0, 1) == 'Z') {
        $num = substr($letter, 1);
        if (!is_numeric($num)) {
            return -1;
        }

        return 23 + $num;
    }

    return -1;
}

function string_to_stylesheet_class($string)
{
    // added Aug 2010 by Reid
    // takes a string and makes it useful for CSS classes or IDs
    $output = $string;
    // limit to 60 chars
    $output = substr($string, 0, 60);
    // lowercase
    $output = strtolower($output);
    // remove other chars
    $output = preg_replace('/[^a-z0-9_]/', '', $output);
    // trim
    $output = trim($output);
    // underscores
    $output = str_replace(' ', '_', $output);
    if (preg_match('/^[0-9]/', $output)) {
        // classes cannot start with numbers; replace with underscore
        $output = '_'.$output;
    }

    return $output;
}

function replies($topic_id, $topic_replies)
{
    global $visited_topics;

    $output = '';
    if (!isset($visited_topics[$topic_id])) {
        $output = '<strong>';
    }
    $output .= format_number($topic_replies);

    if (!isset($visited_topics[$topic_id])) {
        $output .= '</strong>';
    } elseif ($visited_topics[$topic_id] < $topic_replies) {
        $output .= ' (<a href="'.DOMAIN.'topic/'.$topic_id.'#new">';
        $new_replies = $topic_replies - $visited_topics[$topic_id];
        if ($new_replies != $topic_replies) {
            $output .= '<strong>'.$new_replies.'</strong>'.(MOBILE_MODE ? '' : ' ');
        } else {
            if (!MOBILE_MODE) {
                $output .= 'all-';
            }
        }
        if (!MOBILE_MODE) {
            $output .= 'new</a>)';
        } else {
            $output .= 'n</a>)';
        }
    }

    return $output;
}

function thumbnailNative($source, $dest_name, $type, $width, $height)
{
    $copy_original = false;

    $source_filesize = filesize($source);

    if ($width > MAX_IMAGE_DIMENSIONS || $height > MAX_IMAGE_DIMENSIONS) {
        $percent = MAX_IMAGE_DIMENSIONS / (($width > $height) ? $width : $height);

        $new_width = round($width * $percent);
        $new_height = round($height * $percent);

        if ($type == 'gif') {
            shell_exec('gifsicle --no-warnings --colors 256 --resize '.$new_width.'x'.$new_height." \"$source\" > \"thumbs/$dest_name\"");
        } else {
            shell_exec('convert -quiet -resize '.$new_width.'x'.$new_height." \"$source\" \"thumbs/$dest_name\" 2>&1");
        }

        if (!file_exists("thumbs/$dest_name") || $dest_filesize = filesize("thumbs/$dest_name") == 0) {
            return thumbnail($source, $dest_name, $type, true);
        }
    } else {
        $new_width = $width;
        $new_height = $height;

        $copy_original = true;
    }

    if ($copy_original || $source_filesize < $dest_filesize) {
        copy($source, "thumbs/$dest_name");
    }

    return array($new_width, $new_height);
}

function thumbnail($source, $dest_name, $type, $force_internal = false)
{
    $type = strtolower($type);

    switch ($type) {
        case 'gif':
            $image = imagecreatefromgif($source);
        break;

        case 'jpg':
            $image = imagecreatefromjpeg($source);
        break;

        case 'png':
            $image = imagecreatefrompng($source);
        break;
    }

    $width = imagesx($image);
    $height = imagesy($image);

    if (!$force_internal && (($type == 'gif' && USE_GIFSICLE) || ($type != 'gif' && USE_IMAGICK))) {
        imagedestroy($image);

        return thumbnailNative($source, $dest_name, $type, $width, $height);
    }

    if ($width > MAX_IMAGE_DIMENSIONS || $height > MAX_IMAGE_DIMENSIONS) {
        $percent = MAX_IMAGE_DIMENSIONS / (($width > $height) ? $width : $height);

        $new_width = round($width * $percent);
        $new_height = round($height * $percent);

        $thumbnail = imagecreatetruecolor($new_width, $new_height);
        imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    } else {
        $thumbnail = $image;
    }

    switch ($type) {
        case 'jpg':
            // Thumb nail image quality, should be moved to config.php
            imagejpeg($thumbnail, 'thumbs/'.$dest_name, 80);
        break;

        case 'gif':
            imagegif($thumbnail, 'thumbs/'.$dest_name);
        break;

        case 'png':
            imagepng($thumbnail, 'thumbs/'.$dest_name);
    }

    imagedestroy($thumbnail);
    if (gettype($image) == 'resource') {
        imagedestroy($image);
    }

    return array($new_width, $new_height);
}

function deleteImage($mode = 'reply', $postId, $parent_id = 0)
{
    global $link;
    $grab_img = $link->db_exec('SELECT file_name, img_external FROM images WHERE md5 IN (SELECT md5 FROM images WHERE '.$mode.'_id = %1)', $postId);
    $img_usages = $link->num_rows($grab_img);
    list($img_filename, $img_external) = $link->fetch_row();
    if ($img_external) {
        $link->db_exec('DELETE FROM images WHERE '.$mode.'_id = %1', $postId);
    } elseif ($img_filename) { // We got an image!
        if ($img_usages == 1) { // Only this reply uses the image.
            if (file_exists('img/'.$img_filename)) { // Make sure it's /actually/ there. So we won't mess up a delete.
                unlink('img/'.$img_filename);
            }
            if (file_exists('thumbs/'.$img_filename)) {
                unlink('thumbs/'.$img_filename);
            }
        }
        $link->db_exec('DELETE FROM images WHERE '.$mode.'_id = %1', $postId);
    }
}

function log_mod($action, $target)
{
    global $link;
    $data['action'] = $action;
    $data['target'] = $target;
    $data['mod_UID'] = $_SESSION['UID'];
    $data['mod_ip'] = $_SERVER['REMOTE_ADDR'];
    $data['time'] = 'UNIX_TIMESTAMP()';
    $link->insert('mod_actions', $data);

    if ($action != 'open_profile' && $action != 'open_ip') {
        log_irc('Mod action '.$action.' by '.modname($_SESSION['UID']), true);
    }
}

function create_link($url)
{
    global $link;
    $link->insert('internal_shorturls', array('url' => $url));

    return DOMAIN.'!'.base_convert($link->insert_id(), 10, 36);
}

function log_irc($message, $staff = false)
{
    if (!ENABLE_IRC_PING) {
        return;
    }
    global $disable_errors;
    $disable_errors = true;
    $fp = @fsockopen(IRC_PING_DOMAIN, IRC_PING_PORT, $errno, $errstr, 1);
    $disable_errors = false;
    if (!is_resource($fp)) {
        return;
    }

    $out = 'GET /chat?secret='.IRC_PING_SECRET.'&msg='.urlencode($message).($staff ? '&staff=1' : '')." HTTP/1.0\r\n";
    $out .= 'Host: '.IRC_PING_DOMAIN."\r\n";
    $out .= "Content-Type: text/plain\r\n";
    $out .= "Content-Length: 0\r\n";
    $out .= "Connection: Close\r\n\r\n";
    fwrite($fp, $out);
    fclose($fp);
}

function send($sql)
{
    global $link;

    return $link->db_exec($sql);
}

function isMatt($ip)
{
    return false;
    $hostname = gethostbyaddr($ip);
    $test = '.cn';
    $chinaCIDRS = array('182.240.0.0/13', '222.219.0.0/16', '222.220.0.0/15', '1.0.1.0/24', '1.0.2.0/23', '1.0.8.0/21', '1.0.32.0/19', '1.1.0.0/24', '1.1.2.0/23', '1.1.4.0/22', '1.1.8.0/21', '1.1.16.0/20', '1.1.32.0/19', '1.2.0.0/23', '1.2.2.0/24', '1.2.4.0/22', '1.2.8.0/21', '1.2.16.0/20', '1.2.32.0/19', '1.2.64.0/18', '1.3.0.0/16', '1.4.1.0/24', '1.4.2.0/23', '1.4.4.0/22', '1.4.8.0/21', '1.4.16.0/20', '1.4.32.0/19', '1.4.64.0/18', '1.8.0.0/16', '1.10.0.0/21', '1.10.8.0/23', '1.10.11.0/24', '1.10.12.0/22', '1.10.16.0/20', '1.10.32.0/19', '1.10.64.0/18', '1.12.0.0/14', '1.24.0.0/13', '1.45.0.0/16', '1.48.0.0/14', '1.56.0.0/13', '1.68.0.0/14', '1.80.0.0/12', '1.116.0.0/14', '1.180.0.0/14', '1.184.0.0/15', '1.188.0.0/14', '1.192.0.0/13', '1.202.0.0/15', '1.204.0.0/14', '5.10.85.8/30', '14.0.0.0/21', '14.0.12.0/22', '14.1.0.0/22', '14.16.0.0/12', '14.102.128.0/22', '14.102.156.0/22', '14.103.0.0/16', '14.104.0.0/13', '14.112.0.0/12', '14.130.0.0/15', '14.134.0.0/15', '14.144.0.0/12', '14.192.60.0/22', '14.192.76.0/22', '14.196.0.0/15', '14.204.0.0/15', '14.208.0.0/12', '27.8.0.0/13', '27.16.0.0/12', '27.34.232.0/21', '27.36.0.0/14', '27.40.0.0/13', '27.50.40.0/21', '27.50.128.0/17', '27.54.72.0/21', '27.54.152.0/21', '27.54.192.0/18', '27.98.208.0/20', '27.98.224.0/19', '27.99.128.0/17', '27.103.0.0/16', '27.106.128.0/18', '27.106.204.0/22', '27.109.32.0/19', '27.112.0.0/18', '27.112.80.0/20', '27.113.128.0/18', '27.115.0.0/17', '27.116.44.0/22', '27.121.72.0/21', '27.121.120.0/21', '27.128.0.0/15', '27.131.220.0/22', '27.144.0.0/16', '27.148.0.0/14', '27.152.0.0/13', '27.184.0.0/13', '27.192.0.0/11', '27.224.0.0/14', '36.0.0.0/22', '36.0.8.0/21', '36.0.16.0/20', '36.0.32.0/19', '36.0.64.0/18', '36.0.128.0/17', '36.1.0.0/16', '36.4.0.0/14', '36.16.0.0/12', '36.32.0.0/14', '36.36.0.0/16', '36.37.0.0/19', '36.37.36.0/23', '36.37.39.0/24', '36.37.40.0/21', '36.37.48.0/20', '36.40.0.0/13', '36.48.0.0/15', '36.51.0.0/16', '36.56.0.0/13', '36.96.0.0/11', '36.128.0.0/10', '36.192.0.0/11', '36.248.0.0/14', '36.254.0.0/16', '39.0.0.0/24', '39.0.2.0/23', '39.0.4.0/22', '39.0.8.0/21', '39.0.16.0/20', '39.0.32.0/19', '39.0.64.0/18', '39.0.128.0/17', '39.64.0.0/11', '39.128.0.0/10', '42.0.0.0/22', '42.0.8.0/21', '42.0.16.0/21', '42.0.24.0/22', '42.0.32.0/19', '42.0.128.0/17', '42.1.0.0/19', '42.1.32.0/20', '42.1.48.0/21', '42.1.56.0/22', '42.1.128.0/17', '42.4.0.0/14', '42.48.0.0/13', '42.56.0.0/14', '42.62.0.0/17', '42.62.128.0/19', '42.62.160.0/20', '42.62.180.0/22', '42.62.184.0/21', '42.63.0.0/16', '42.80.0.0/15', '42.83.64.0/20', '42.83.80.0/22', '42.83.88.0/21', '42.83.96.0/19', '42.83.128.0/17', '42.84.0.0/14', '42.88.0.0/13', '42.96.64.0/19', '42.96.96.0/21', '42.96.108.0/22', '42.96.112.0/20', '42.96.128.0/17', '42.97.0.0/16', '42.99.0.0/18', '42.99.64.0/19', '42.99.96.0/20', '42.99.112.0/22', '42.99.120.0/21', '42.100.0.0/14', '42.120.0.0/15', '42.122.0.0/16', '42.123.0.0/19', '42.123.36.0/22', '42.123.40.0/21', '42.123.48.0/20', '42.123.64.0/18', '42.123.128.0/17', '42.128.0.0/12', '42.156.0.0/19', '42.156.36.0/22', '42.156.40.0/21', '42.156.48.0/20', '42.156.64.0/18', '42.156.128.0/17', '42.157.0.0/16', '42.158.0.0/15', '42.160.0.0/12', '42.176.0.0/13', '42.184.0.0/15', '42.186.0.0/16', '42.187.0.0/18', '42.187.64.0/19', '42.187.96.0/20', '42.187.112.0/21', '42.187.120.0/22', '42.187.128.0/17', '42.192.0.0/13', '42.201.0.0/17', '42.202.0.0/15', '42.204.0.0/14', '42.208.0.0/12', '42.224.0.0/12', '42.240.0.0/16', '42.242.0.0/15', '42.244.0.0/14', '42.248.0.0/13', '43.236.0.0/16', '43.237.0.0/18', '43.237.64.0/19', '43.237.96.0/21', '43.237.132.0/22', '43.237.136.0/21', '43.237.144.0/20', '43.237.160.0/19', '43.237.192.0/18', '43.238.0.0/17', '43.238.132.0/22', '43.238.136.0/21', '43.238.144.0/20', '43.238.160.0/22', '43.238.168.0/21', '43.238.172.0/22', '43.238.176.0/20', '43.238.192.0/18', '43.239.0.0/19', '43.239.32.0/20', '43.239.48.0/22', '43.240.48.0/22', '43.240.56.0/21', '43.240.68.0/22', '43.240.72.0/21', '43.240.84.0/22', '43.240.124.0/22', '43.240.128.0/21', '43.240.136.0/22', '43.240.204.0/22', '43.240.208.0/20', '43.241.48.0/22', '43.241.76.0/20', '43.241.112.0/22', '43.241.168.0/21', '43.241.176.0/21', '43.241.184.0/22', '43.241.196.0/22', '43.241.208.0/20', '43.241.224.0/20', '43.241.240.0/22', '43.241.248.0/21', '43.242.8.0/21', '43.242.16.0/21', '43.242.24.0/21', '43.242.32.0/22', '43.242.44.0/22', '43.242.48.0/20', '43.242.64.0/19', '43.242.96.0/22', '43.242.144.0/20', '43.242.160.0/21', '43.242.168.0/22', '43.242.180.0/22', '43.242.188.0/22', '43.242.192.0/21', '43.242.204.0/22', '43.242.216.0/21', '43.242.252.0/22', '43.243.4.0/22', '43.243.8.0/21', '43.243.16.0/22', '43.243.24.0/22', '43.243.88.0/22', '43.246.0.0/18', '43.246.64.0/19', '43.246.96.0/22', '43.247.4.0/22', '43.247.8.0/22', '43.247.44.0/22', '43.247.48.0/22', '43.247.68.0/22', '43.247.76.0/22', '43.247.84.0/22', '43.247.88.0/21', '43.247.96.0/21', '43.247.108.0/22', '43.247.112.0/22', '43.247.148.0/22', '43.247.152.0/22', '43.247.176.0/20', '43.247.196.0/22', '43.247.200.0/21', '43.247.208.0/20', '43.247.224.0/19', '43.252.40.0/22', '43.252.48.0/22', '43.252.56.0/22', '43.252.224.0/22', '43.254.0.0/21', '43.254.8.0/22', '43.254.24.0/22', '43.254.36.0/22', '43.254.44.0/22', '43.254.52.0/22', '43.254.64.0/22', '43.254.72.0/22', '43.254.84.0/22', '43.254.88.0/21', '43.254.100.0/22', '43.254.104.0/22', '43.254.112.0/21', '43.254.128.0/22', '43.254.136.0/21', '43.254.144.0/20', '43.254.168.0/21', '43.254.180.0/22', '43.254.184.0/21', '43.254.192.0/21', '43.254.200.0/22', '43.254.208.0/22', '43.254.220.0/22', '43.254.224.0/20', '43.254.240.0/22', '43.254.248.0/21', '43.255.0.0/21', '43.255.8.0/22', '43.255.16.0/22', '43.255.48.0/22', '43.255.60.0/22', '43.255.64.0/20', '43.255.84.0/22', '43.255.96.0/22', '43.255.108.0/22', '43.255.144.0/22', '43.255.168.0/22', '43.255.176.0/22', '43.255.184.0/22', '43.255.192.0/22', '43.255.200.0/21', '43.255.208.0/21', '43.255.224.0/21', '43.255.232.0/22', '43.255.244.0/22', '45.64.112.0/23', '49.4.0.0/14', '49.51.0.0/16', '49.52.0.0/14', '49.64.0.0/11', '49.112.0.0/13', '49.120.0.0/14', '49.128.0.0/24', '49.128.2.0/23', '49.140.0.0/15', '49.152.0.0/14', '49.208.0.0/14', '49.220.0.0/14', '49.232.0.0/14', '49.239.0.0/18', '49.239.192.0/18', '49.246.224.0/19', '54.222.0.0/15', '58.14.0.0/15', '58.16.0.0/13', '58.24.0.0/15', '58.30.0.0/15', '58.32.0.0/11', '58.65.232.0/21', '58.66.0.0/15', '58.68.128.0/17', '58.82.0.0/17', '58.83.0.0/16', '58.87.64.0/18', '58.99.128.0/17', '58.100.0.0/15', '58.116.0.0/14', '58.128.0.0/13', '58.144.0.0/16', '58.154.0.0/15', '58.192.0.0/11', '58.240.0.0/12', '59.32.0.0/11', '59.64.0.0/12', '59.80.0.0/14', '59.107.0.0/16', '59.108.0.0/14', '59.151.0.0/17', '59.155.0.0/16', '59.172.0.0/14', '59.191.0.0/17', '59.191.240.0/20', '59.192.0.0/10', '60.0.0.0/11', '60.55.0.0/16', '60.63.0.0/16', '60.160.0.0/11', '60.194.0.0/15', '60.200.0.0/13', '60.208.0.0/12', '60.232.0.0/15', '60.235.0.0/16', '60.245.128.0/17', '60.247.0.0/16', '60.252.0.0/16', '60.253.128.0/17', '60.255.0.0/16', '61.4.80.0/20', '61.4.176.0/20', '61.8.160.0/20', '61.28.0.0/17', '61.29.128.0/17', '61.45.128.0/18', '61.45.224.0/20', '61.47.128.0/18', '61.48.0.0/13', '61.87.192.0/18', '61.128.0.0/10', '61.232.0.0/14', '61.236.0.0/15', '61.240.0.0/14', '91.234.36.0/24', '101.0.0.0/22', '101.1.0.0/22', '101.2.172.0/22', '101.4.0.0/14', '101.16.0.0/12', '101.32.0.0/12', '101.48.0.0/15', '101.50.56.0/22', '101.52.0.0/16', '101.53.100.0/22', '101.54.0.0/16', '101.55.224.0/21', '101.64.0.0/13', '101.72.0.0/14', '101.76.0.0/15', '101.78.0.0/22', '101.78.32.0/19', '101.80.0.0/12', '101.96.0.0/21', '101.96.8.0/22', '101.96.16.0/20', '101.96.128.0/17', '101.99.96.0/19', '101.101.64.0/19', '101.101.100.0/24', '101.101.102.0/23', '101.101.104.0/21', '101.101.112.0/20', '101.102.64.0/19', '101.102.100.0/23', '101.102.102.0/24', '101.102.104.0/21', '101.102.112.0/20', '101.104.0.0/14', '101.110.64.0/19', '101.110.96.0/20', '101.110.116.0/22', '101.110.120.0/21', '101.120.0.0/14', '101.124.0.0/15', '101.126.0.0/16', '101.128.0.0/22', '101.128.8.0/21', '101.128.16.0/20', '101.128.32.0/19', '101.129.0.0/16', '101.130.0.0/15', '101.132.0.0/14', '101.144.0.0/12', '101.192.0.0/13', '101.200.0.0/15', '101.203.128.0/19', '101.203.160.0/21', '101.203.172.0/22', '101.203.176.0/20', '101.204.0.0/14', '101.224.0.0/13', '101.232.0.0/15', '101.234.64.0/21', '101.234.76.0/22', '101.234.80.0/20', '101.234.96.0/19', '101.236.0.0/14', '101.240.0.0/13', '101.248.0.0/15', '101.251.0.0/22', '101.251.8.0/21', '101.251.16.0/20', '101.251.32.0/19', '101.251.64.0/18', '101.251.128.0/17', '101.252.0.0/15', '101.254.0.0/16', '103.1.8.0/22', '103.1.20.0/22', '103.1.24.0/22', '103.1.72.0/22', '103.1.88.0/22', '103.1.168.0/22', '103.2.108.0/22', '103.2.156.0/22', '103.2.164.0/22', '103.2.200.0/21', '103.2.208.0/21', '103.3.84.0/22', '103.3.88.0/21', '103.3.96.0/19', '103.3.128.0/20', '103.3.148.0/22', '103.3.152.0/21', '103.4.56.0/22', '103.4.168.0/22', '103.4.184.0/22', '103.5.36.0/22', '103.5.52.0/22', '103.5.56.0/22', '103.5.252.0/22', '103.6.76.0/22', '103.6.220.0/22', '103.7.4.0/22', '103.7.28.0/22', '103.7.212.0/22', '103.7.216.0/21', '103.8.4.0/22', '103.8.8.0/22', '103.8.32.0/22', '103.8.52.0/22', '103.8.108.0/22', '103.8.156.0/22', '103.8.200.0/21', '103.8.220.0/22', '103.9.152.0/22', '103.9.248.0/21', '103.10.0.0/22', '103.10.16.0/22', '103.10.84.0/22', '103.10.111.0/24', '103.10.140.0/22', '103.11.180.0/22', '103.12.32.0/22', '103.12.68.0/22', '103.12.136.0/22', '103.12.184.0/22', '103.12.232.0/22', '103.13.124.0/22', '103.13.144.0/22', '103.13.196.0/22', '103.13.244.0/22', '103.14.84.0/22', '103.14.112.0/22', '103.14.132.0/22', '103.14.136.0/22', '103.14.156.0/22', '103.14.240.0/22', '103.15.4.0/22', '103.15.8.0/22', '103.15.16.0/22', '103.15.96.0/22', '103.15.200.0/22', '103.16.52.0/22', '103.16.80.0/21', '103.16.88.0/22', '103.16.108.0/22', '103.16.124.0/22', '103.17.40.0/22', '103.17.120.0/22', '103.17.160.0/22', '103.17.204.0/22', '103.17.228.0/22', '103.18.192.0/22', '103.18.208.0/21', '103.18.224.0/22', '103.19.12.0/22', '103.19.40.0/21', '103.19.64.0/21', '103.19.72.0/22', '103.19.232.0/22', '103.20.12.0/22', '103.20.32.0/22', '103.20.112.0/22', '103.20.128.0/22', '103.20.160.0/22', '103.20.248.0/22', '103.21.112.0/21', '103.21.136.0/21', '103.21.176.0/22', '103.21.208.0/22', '103.21.240.0/22', '103.22.0.0/18', '103.22.64.0/19', '103.22.100.0/22', '103.22.104.0/21', '103.22.112.0/20', '103.22.188.0/22', '103.22.228.0/22', '103.22.252.0/22', '103.23.8.0/22', '103.23.56.0/22', '103.23.160.0/21', '103.23.176.0/22', '103.23.228.0/22', '103.24.116.0/22', '103.24.128.0/22', '103.24.144.0/22', '103.24.176.0/22', '103.24.184.0/22', '103.24.220.0/22', '103.24.228.0/22', '103.24.248.0/21', '103.25.8.0/23', '103.25.20.0/22', '103.25.24.0/21', '103.25.32.0/21', '103.25.40.0/22', '103.25.48.0/22', '103.25.64.0/21', '103.25.148.0/22', '103.25.156.0/22', '103.25.216.0/22', '103.26.0.0/22', '103.26.64.0/22', '103.26.156.0/22', '103.26.160.0/22', '103.26.228.0/22', '103.26.240.0/22', '103.27.4.0/22', '103.27.12.0/22', '103.27.24.0/22', '103.27.56.0/22', '103.27.96.0/22', '103.27.176.0/22', '103.27.208.0/22', '103.27.240.0/22', '103.28.4.0/22', '103.28.8.0/22', '103.28.204.0/22', '103.29.16.0/22', '103.29.128.0/21', '103.29.136.0/22', '103.30.20.0/22', '103.30.96.0/22', '103.30.148.0/22', '103.30.200.0/22', '103.30.216.0/22', '103.30.228.0/22', '103.30.232.0/21', '103.31.0.0/22', '103.31.48.0/20', '103.31.64.0/21', '103.31.72.0/22', '103.31.148.0/22', '103.31.160.0/22', '103.31.168.0/22', '103.31.200.0/22', '103.32.0.0/16', '103.33.0.0/18', '103.33.64.0/19', '103.33.96.0/21', '103.33.100.0/22', '103.33.132.0/22', '103.33.136.0/21', '103.33.144.0/20', '103.33.160.0/19', '103.33.192.0/19', '103.33.224.0/21', '103.33.232.0/21', '103.33.240.0/20', '103.34.0.0/16', '103.35.0.0/19', '103.35.32.0/20', '103.35.48.0/22', '103.36.20.0/22', '103.36.28.0/22', '103.36.36.0/22', '103.36.56.0/21', '103.36.64.0/22', '103.36.72.0/22', '103.36.96.0/22', '103.36.132.0/22', '103.36.136.0/22', '103.36.208.0/20', '103.36.224.0/20', '103.36.240.0/21', '103.37.44.0/22', '103.37.52.0/22', '103.37.56.0/22', '103.37.72.0/22', '103.37.100.0/22', '103.37.104.0/22', '103.37.124.0/22', '103.37.136.0/21', '103.37.144.0/20', '103.37.160.0/21', '103.37.172.0/22', '103.37.176.0/22', '103.37.208.0/20', '103.37.248.0/21', '103.38.0.0/22', '103.38.32.0/22', '103.38.40.0/21', '103.38.56.0/22', '103.38.76.0/22', '103.38.84.0/22', '103.38.92.0/22', '103.38.96.0/22', '103.38.116.0/22', '103.38.132.0/22', '103.38.140.0/22', '103.224.40.0/21', '103.224.60.0/22', '103.224.80.0/22', '103.224.220.0/22', '103.224.224.0/21', '103.224.232.0/22', '103.225.84.0/22', '103.226.16.0/22', '103.226.40.0/22', '103.226.56.0/21', '103.226.80.0/22', '103.226.116.0/22', '103.226.132.0/22', '103.226.156.0/22', '103.226.180.0/22', '103.226.196.0/22', '103.227.48.0/22', '103.227.72.0/21', '103.227.80.0/22', '103.227.100.0/22', '103.227.120.0/22', '103.227.132.0/22', '103.227.136.0/22', '103.227.196.0/22', '103.227.204.0/22', '103.227.212.0/22', '103.227.228.0/22', '103.228.12.0/22', '103.228.28.0/22', '103.228.68.0/22', '103.228.88.0/22', '103.228.128.0/22', '103.228.160.0/22', '103.228.176.0/22', '103.228.204.0/22', '103.228.208.0/22', '103.228.228.0/22', '103.228.232.0/22', '103.229.20.0/22', '103.229.136.0/22', '103.229.148.0/22', '103.229.172.0/22', '103.229.212.0/22', '103.229.216.0/21', '103.229.228.0/22', '103.229.236.0/22', '103.229.240.0/22', '103.230.0.0/22', '103.230.28.0/22', '103.230.40.0/21', '103.230.96.0/22', '103.230.196.0/22', '103.230.200.0/21', '103.230.212.0/22', '103.230.236.0/22', '103.231.16.0/21', '103.231.64.0/21', '103.231.144.0/22', '103.231.180.0/22', '103.231.184.0/22', '103.231.244.0/22', '103.232.4.0/22', '103.232.144.0/22', '103.232.212.0/22', '103.233.4.0/22', '103.233.44.0/22', '103.233.52.0/22', '103.233.104.0/22', '103.233.128.0/22', '103.233.136.0/22', '103.233.228.0/22', '103.234.0.0/22', '103.234.20.0/22', '103.234.56.0/22', '103.234.124.0/22', '103.234.128.0/22', '103.234.172.0/22', '103.234.180.0/22', '103.235.16.0/22', '103.235.48.0/22', '103.235.56.0/21', '103.235.80.0/21', '103.235.128.0/20', '103.235.144.0/21', '103.235.184.0/22', '103.235.192.0/22', '103.235.200.0/22', '103.235.220.0/22', '103.235.224.0/19', '103.236.0.0/18', '103.236.64.0/19', '103.236.96.0/22', '103.237.0.0/20', '103.237.24.0/21', '103.237.68.0/22', '103.237.88.0/22', '103.237.152.0/22', '103.237.176.0/20', '103.237.192.0/18', '103.238.0.0/21', '103.238.16.0/20', '103.238.32.0/20', '103.238.48.0/21', '103.238.56.0/22', '103.238.88.0/21', '103.238.96.0/22', '103.238.132.0/22', '103.238.140.0/22', '103.238.144.0/22', '103.238.160.0/19', '103.238.196.0/22', '103.238.204.0/22', '103.238.252.0/22', '103.239.0.0/22', '103.239.40.0/21', '103.239.68.0/22', '103.239.96.0/22', '103.239.152.0/21', '103.239.176.0/21', '103.239.184.0/22', '103.239.192.0/21', '103.239.204.0/22', '103.239.208.0/22', '103.239.224.0/22', '103.239.244.0/22', '103.240.16.0/22', '103.240.36.0/22', '103.240.72.0/22', '103.240.84.0/22', '103.240.124.0/22', '103.240.156.0/22', '103.240.172.0/22', '103.240.244.0/22', '103.241.12.0/22', '103.241.72.0/22', '103.241.92.0/22', '103.241.96.0/22', '103.241.160.0/22', '103.241.184.0/21', '103.241.220.0/22', '103.242.8.0/22', '103.242.64.0/22', '103.242.128.0/21', '103.242.160.0/22', '103.242.168.0/21', '103.242.176.0/22', '103.242.200.0/22', '103.242.212.0/22', '103.242.220.0/22', '103.242.240.0/22', '103.243.24.0/22', '103.243.136.0/22', '103.243.252.0/22', '103.244.16.0/22', '103.244.56.0/21', '103.244.64.0/20', '103.244.80.0/21', '103.244.88.0/22', '103.244.148.0/22', '103.244.164.0/22', '103.244.232.0/22', '103.244.252.0/22', '103.245.23.0/24', '103.245.52.0/22', '103.245.60.0/22', '103.245.80.0/22', '103.245.124.0/22', '103.245.128.0/22', '103.246.8.0/21', '103.246.120.0/21', '103.246.132.0/22', '103.246.152.0/21', '103.247.168.0/21', '103.247.176.0/22', '103.247.200.0/22', '103.247.212.0/22', '103.248.0.0/23', '103.248.64.0/22', '103.248.100.0/22', '103.248.124.0/22', '103.248.152.0/22', '103.248.168.0/22', '103.248.192.0/22', '103.248.212.0/22', '103.248.224.0/21', '103.249.12.0/22', '103.249.52.0/22', '103.249.128.0/22', '103.249.136.0/22', '103.249.144.0/22', '103.249.164.0/22', '103.249.168.0/21', '103.249.176.0/22', '103.249.188.0/22', '103.249.192.0/22', '103.249.244.0/22', '103.249.252.0/22', '103.250.32.0/22', '103.250.104.0/22', '103.250.124.0/22', '103.250.180.0/22', '103.250.192.0/22', '103.250.216.0/22', '103.250.224.0/22', '103.250.236.0/22', '103.250.248.0/21', '103.251.32.0/22', '103.251.84.0/22', '103.251.96.0/22', '103.251.124.0/22', '103.251.128.0/22', '103.251.160.0/22', '103.251.204.0/22', '103.251.236.0/22', '103.251.240.0/22', '103.252.28.0/22', '103.252.36.0/22', '103.252.64.0/22', '103.252.104.0/22', '103.252.172.0/22', '103.252.204.0/22', '103.252.208.0/22', '103.252.232.0/22', '103.252.248.0/22', '103.253.4.0/22', '103.253.60.0/22', '103.253.204.0/22', '103.253.220.0/22', '103.253.224.0/22', '103.253.232.0/22', '103.254.8.0/22', '103.254.20.0/22', '103.254.64.0/20', '103.254.112.0/22', '103.254.148.0/22', '103.254.176.0/22', '103.254.188.0/22', '103.254.196.0/24', '103.254.220.0/22', '103.255.68.0/22', '103.255.88.0/21', '103.255.136.0/21', '103.255.184.0/22', '103.255.200.0/22', '103.255.208.0/21', '103.255.228.0/22', '106.0.0.0/24', '106.0.2.0/23', '106.0.4.0/22', '106.0.8.0/21', '106.0.16.0/20', '106.0.64.0/18', '106.2.0.0/15', '106.4.0.0/14', '106.8.0.0/15', '106.11.0.0/16', '106.12.0.0/14', '106.16.0.0/12', '106.32.0.0/12', '106.48.0.0/15', '106.50.0.0/16', '106.52.0.0/14', '106.56.0.0/13', '106.74.0.0/15', '106.80.0.0/12', '106.108.0.0/14', '106.112.0.0/12', '106.224.0.0/12', '110.6.0.0/15', '110.16.0.0/14', '110.40.0.0/14', '110.44.144.0/20', '110.48.0.0/16', '110.51.0.0/16', '110.52.0.0/15', '110.56.0.0/13', '110.64.0.0/15', '110.72.0.0/15', '110.75.0.0/16', '110.76.0.0/18', '110.76.156.0/22', '110.76.184.0/22', '110.76.192.0/18', '110.77.0.0/17', '110.80.0.0/13', '110.88.0.0/14', '110.93.32.0/19', '110.94.0.0/15', '110.96.0.0/11', '110.152.0.0/14', '110.156.0.0/15', '110.165.32.0/19', '110.166.0.0/15', '110.172.192.0/18', '110.173.0.0/19', '110.173.32.0/20', '110.173.64.0/18', '110.173.192.0/19', '110.176.0.0/12', '110.192.0.0/11', '110.228.0.0/14', '110.232.32.0/19', '110.236.0.0/15', '110.240.0.0/12', '111.0.0.0/10', '111.66.0.0/16', '111.67.192.0/20', '111.68.64.0/19', '111.72.0.0/13', '111.85.0.0/16', '111.91.192.0/19', '111.112.0.0/14', '111.116.0.0/15', '111.118.200.0/21', '111.119.64.0/18', '111.119.128.0/19', '111.120.0.0/14', '111.124.0.0/16', '111.126.0.0/15', '111.128.0.0/11', '111.160.0.0/13', '111.170.0.0/16', '111.172.0.0/14', '111.176.0.0/13', '111.186.0.0/15', '111.192.0.0/12', '111.208.0.0/13', '111.221.128.0/17', '111.222.0.0/16', '111.223.240.0/22', '111.223.248.0/22', '111.224.0.0/13', '111.235.96.0/19', '111.235.156.0/22', '111.235.160.0/19', '112.0.0.0/10', '112.64.0.0/14', '112.73.0.0/16', '112.74.0.0/15', '112.80.0.0/12', '112.96.0.0/13', '112.109.128.0/17', '112.111.0.0/16', '112.112.0.0/14', '112.116.0.0/15', '112.122.0.0/15', '112.124.0.0/14', '112.128.0.0/14', '112.132.0.0/16', '112.137.48.0/21', '112.192.0.0/14', '112.224.0.0/11', '113.0.0.0/13', '113.8.0.0/15', '113.11.192.0/19', '113.12.0.0/14', '113.16.0.0/15', '113.18.0.0/16', '113.24.0.0/14', '113.31.0.0/16', '113.44.0.0/14', '113.48.0.0/14', '113.52.160.0/19', '113.54.0.0/15', '113.56.0.0/15', '113.58.0.0/16', '113.59.0.0/17', '113.59.224.0/22', '113.62.0.0/15', '113.64.0.0/10', '113.128.0.0/15', '113.130.96.0/20', '113.130.112.0/21', '113.132.0.0/14', '113.136.0.0/13', '113.194.0.0/15', '113.197.100.0/22', '113.200.0.0/15', '113.202.0.0/16', '113.204.0.0/14', '113.208.96.0/19', '113.208.128.0/17', '113.209.0.0/16', '113.212.0.0/18', '113.212.100.0/22', '113.212.184.0/21', '113.213.0.0/17', '113.214.0.0/15', '113.218.0.0/15', '113.220.0.0/14', '113.224.0.0/12', '113.240.0.0/13', '113.248.0.0/14', '114.28.0.0/16', '114.54.0.0/15', '114.60.0.0/14', '114.64.0.0/14', '114.68.0.0/16', '114.79.64.0/18', '114.80.0.0/12', '114.96.0.0/13', '114.104.0.0/14', '114.110.0.0/20', '114.110.64.0/18', '114.111.0.0/19', '114.111.160.0/19', '114.112.0.0/13', '114.132.0.0/16', '114.135.0.0/16', '114.138.0.0/15', '114.141.64.0/21', '114.141.128.0/18', '114.196.0.0/15', '114.198.248.0/21', '114.208.0.0/12', '114.224.0.0/11', '115.24.0.0/14', '115.28.0.0/15', '115.32.0.0/14', '115.44.0.0/14', '115.48.0.0/12', '115.69.64.0/20', '115.84.0.0/18', '115.84.192.0/19', '115.85.192.0/18', '115.100.0.0/14', '115.104.0.0/14', '115.120.0.0/14', '115.124.16.0/20', '115.148.0.0/14', '115.152.0.0/13', '115.166.64.0/19', '115.168.0.0/13', '115.180.0.0/14', '115.190.0.0/15', '115.192.0.0/11', '115.224.0.0/12', '116.0.8.0/21', '116.0.24.0/21', '116.1.0.0/16', '116.2.0.0/15', '116.4.0.0/14', '116.8.0.0/14', '116.13.0.0/16', '116.16.0.0/12', '116.50.0.0/20', '116.52.0.0/14', '116.56.0.0/15', '116.58.128.0/20', '116.58.208.0/20', '116.60.0.0/14', '116.66.0.0/17', '116.69.0.0/16', '116.70.0.0/17', '116.76.0.0/14', '116.85.0.0/16', '116.89.144.0/20', '116.90.80.0/20', '116.90.184.0/21', '116.95.0.0/16', '116.112.0.0/14', '116.116.0.0/15', '116.128.0.0/10', '116.192.0.0/16', '116.193.16.0/20', '116.193.32.0/19', '116.193.176.0/21', '116.194.0.0/15', '116.196.0.0/16', '116.198.0.0/16', '116.199.0.0/17', '116.199.128.0/19', '116.204.0.0/15', '116.207.0.0/16', '116.208.0.0/14', '116.212.160.0/20', '116.213.64.0/18', '116.213.128.0/17', '116.214.32.0/19', '116.214.64.0/20', '116.214.128.0/17', '116.215.0.0/16', '116.216.0.0/14', '116.224.0.0/12', '116.242.0.0/15', '116.244.0.0/14', '116.248.0.0/15', '116.251.64.0/18', '116.252.0.0/15', '116.254.128.0/17', '116.255.128.0/17', '117.8.0.0/13', '117.21.0.0/16', '117.22.0.0/15', '117.24.0.0/13', '117.32.0.0/13', '117.40.0.0/14', '117.44.0.0/15', '117.48.0.0/14', '117.53.48.0/20', '117.53.176.0/20', '117.57.0.0/16', '117.58.0.0/17', '117.59.0.0/16', '117.60.0.0/14', '117.64.0.0/13', '117.72.0.0/15', '117.74.64.0/19', '117.74.128.0/17', '117.75.0.0/16', '117.76.0.0/14', '117.80.0.0/12', '117.100.0.0/15', '117.103.16.0/20', '117.103.40.0/21', '117.103.72.0/21', '117.103.128.0/20', '117.104.168.0/21', '117.106.0.0/15', '117.112.0.0/13', '117.120.64.0/18', '117.120.128.0/17', '117.121.0.0/17', '117.121.128.0/18', '117.121.192.0/21', '117.122.128.0/17', '117.124.0.0/14', '117.128.0.0/10', '118.24.0.0/15', '118.26.0.0/16', '118.28.0.0/14', '118.64.0.0/15', '118.66.0.0/16', '118.67.112.0/20', '118.72.0.0/13', '118.80.0.0/15', '118.84.0.0/15', '118.88.32.0/19', '118.88.64.0/18', '118.88.128.0/17', '118.89.0.0/16', '118.91.240.0/20', '118.102.16.0/20', '118.102.32.0/21', '118.112.0.0/13', '118.120.0.0/14', '118.124.0.0/15', '118.126.0.0/16', '118.127.128.0/19', '118.132.0.0/14', '118.144.0.0/14', '118.178.0.0/16', '118.180.0.0/14', '118.184.0.0/16', '118.186.0.0/15', '118.188.0.0/16', '118.190.0.0/15', '118.192.0.0/13', '118.202.0.0/15', '118.204.0.0/14', '118.212.0.0/15', '118.224.0.0/14', '118.228.0.0/15', '118.230.0.0/16', '118.239.0.0/16', '118.242.0.0/16', '118.244.0.0/14', '118.248.0.0/13', '119.0.0.0/15', '119.2.0.0/19', '119.2.128.0/17', '119.3.0.0/16', '119.4.0.0/14', '119.8.0.0/16', '119.10.0.0/17', '119.15.136.0/21', '119.16.0.0/16', '119.18.192.0/20', '119.18.208.0/21', '119.18.224.0/19', '119.19.0.0/16', '119.20.0.0/14', '119.27.64.0/18', '119.27.128.0/17', '119.28.0.0/15', '119.30.48.0/20', '119.31.192.0/19', '119.32.0.0/13', '119.40.0.0/18', '119.40.64.0/20', '119.40.128.0/17', '119.41.0.0/16', '119.42.0.0/19', '119.42.128.0/20', '119.42.136.0/21', '119.42.224.0/19', '119.44.0.0/15', '119.48.0.0/13', '119.57.0.0/16', '119.58.0.0/16', '119.59.128.0/17', '119.60.0.0/15', '119.62.0.0/16', '119.63.32.0/19', '119.75.208.0/20', '119.78.0.0/15', '119.80.0.0/16', '119.82.208.0/20', '119.84.0.0/14', '119.88.0.0/14', '119.96.0.0/13', '119.108.0.0/15', '119.112.0.0/12', '119.128.0.0/12', '119.144.0.0/14', '119.148.160.0/19', '119.151.192.0/18', '119.160.200.0/21', '119.161.128.0/17', '119.162.0.0/15', '119.164.0.0/14', '119.176.0.0/12', '119.232.0.0/15', '119.235.128.0/18', '119.248.0.0/14', '119.252.96.0/21', '119.252.240.0/20', '119.253.0.0/16', '119.254.0.0/15', '120.0.0.0/12', '120.24.0.0/14', '120.30.0.0/15', '120.32.0.0/12', '120.48.0.0/15', '120.52.0.0/14', '120.64.0.0/13', '120.72.32.0/19', '120.72.128.0/17', '120.76.0.0/14', '120.80.0.0/13', '120.88.8.0/21', '120.90.0.0/15', '120.92.0.0/16', '120.94.0.0/15', '120.128.0.0/13', '120.136.128.0/18', '120.137.0.0/17', '120.143.128.0/19', '120.192.0.0/10', '121.0.8.0/21', '121.0.16.0/20', '121.4.0.0/15', '121.8.0.0/13', '121.16.0.0/12', '121.32.0.0/13', '121.40.0.0/14', '121.46.0.0/18', '121.46.128.0/17', '121.47.0.0/16', '121.48.0.0/15', '121.50.8.0/21', '121.51.0.0/16', '121.52.160.0/19', '121.52.208.0/20', '121.52.224.0/19', '121.54.176.0/21', '121.55.0.0/18', '121.56.0.0/15', '121.58.0.0/17', '121.58.136.0/21', '121.58.144.0/20', '121.58.160.0/21', '121.59.0.0/16', '121.60.0.0/14', '121.68.0.0/14', '121.76.0.0/15', '121.79.128.0/18', '121.89.0.0/16', '121.100.128.0/17', '121.101.0.0/18', '121.101.208.0/20', '121.192.0.0/13', '121.200.192.0/21', '121.201.0.0/16', '121.204.0.0/14', '121.224.0.0/12', '121.248.0.0/14', '121.255.0.0/16', '122.0.64.0/18', '122.0.128.0/17', '122.4.0.0/14', '122.8.0.0/15', '122.10.0.0/16', '122.11.0.0/17', '122.12.0.0/15', '122.14.0.0/16', '122.48.0.0/16', '122.49.0.0/18', '122.51.0.0/16', '122.64.0.0/11', '122.96.0.0/15', '122.102.0.0/20', '122.102.64.0/19', '122.112.0.0/14', '122.119.0.0/16', '122.128.120.0/21', '122.136.0.0/13', '122.144.128.0/17', '122.152.192.0/18', '122.156.0.0/14', '122.188.0.0/14', '122.192.0.0/14', '122.198.0.0/16', '122.200.64.0/18', '122.201.48.0/20', '122.204.0.0/14', '122.224.0.0/12', '122.240.0.0/13', '122.248.24.0/21', '122.248.48.0/20', '122.255.64.0/21', '123.0.128.0/18', '123.4.0.0/14', '123.8.0.0/13', '123.49.128.0/17', '123.50.160.0/19', '123.52.0.0/14', '123.56.0.0/14', '123.60.0.0/15', '123.62.0.0/16', '123.64.0.0/11', '123.96.0.0/15', '123.98.0.0/17', '123.99.128.0/17', '123.100.0.0/19', '123.101.0.0/16', '123.103.0.0/17', '123.108.128.0/20', '123.108.208.0/20', '123.112.0.0/12', '123.128.0.0/13', '123.136.80.0/20', '123.137.0.0/16', '123.138.0.0/15', '123.144.0.0/12', '123.160.0.0/12', '123.176.60.0/22', '123.176.80.0/20', '123.177.0.0/16', '123.178.0.0/15', '123.180.0.0/14', '123.184.0.0/13', '123.196.0.0/15', '123.199.128.0/17', '123.206.0.0/15', '123.232.0.0/14', '123.242.0.0/17', '123.244.0.0/14', '123.249.0.0/16', '123.253.0.0/16', '124.6.64.0/18', '124.14.0.0/15', '124.16.0.0/15', '124.20.0.0/14', '124.28.192.0/18', '124.29.0.0/17', '124.31.0.0/16', '124.40.112.0/20', '124.40.128.0/18', '124.40.192.0/19', '124.42.0.0/16', '124.47.0.0/18', '124.64.0.0/15', '124.66.0.0/17', '124.67.0.0/16', '124.68.0.0/14', '124.72.0.0/13', '124.88.0.0/13', '124.108.8.0/21', '124.108.40.0/21', '124.109.96.0/21', '124.112.0.0/13', '124.126.0.0/15', '124.128.0.0/13', '124.147.128.0/17', '124.151.0.0/16', '124.152.0.0/16', '124.156.0.0/16', '124.160.0.0/13', '124.172.0.0/14', '124.192.0.0/15', '124.196.0.0/16', '124.200.0.0/13', '124.220.0.0/14', '124.224.0.0/12', '124.240.0.0/17', '124.240.128.0/18', '124.242.0.0/16', '124.243.192.0/18', '124.248.0.0/17', '124.249.0.0/16', '124.250.0.0/15', '124.254.0.0/18', '125.31.192.0/18', '125.32.0.0/12', '125.58.128.0/17', '125.61.128.0/17', '125.62.0.0/18', '125.64.0.0/11', '125.96.0.0/15', '125.98.0.0/16', '125.104.0.0/13', '125.112.0.0/12', '125.169.0.0/16', '125.171.0.0/16', '125.208.0.0/18', '125.210.0.0/15', '125.213.0.0/17', '125.214.96.0/19', '125.215.0.0/18', '125.216.0.0/13', '125.254.128.0/17', '134.196.0.0/16', '139.9.0.0/16', '139.129.0.0/16', '139.148.0.0/16', '139.155.0.0/16', '139.159.0.0/16', '139.170.0.0/16', '139.176.0.0/16', '139.183.0.0/16', '139.186.0.0/16', '139.189.0.0/16', '139.196.0.0/14', '139.200.0.0/13', '139.208.0.0/13', '139.217.0.0/16', '139.219.0.0/16', '139.220.0.0/15', '139.224.0.0/16', '139.226.0.0/15', '140.75.0.0/16', '140.143.0.0/16', '140.205.0.0/16', '140.206.0.0/15', '140.210.0.0/16', '140.224.0.0/16', '140.237.0.0/16', '140.240.0.0/16', '140.243.0.0/16', '140.246.0.0/16', '140.249.0.0/16', '140.250.0.0/16', '140.255.0.0/16', '144.0.0.0/16', '144.7.0.0/16', '144.12.0.0/16', '144.52.0.0/16', '144.123.0.0/16', '144.255.0.0/16', '147.243.224.0/19', '150.0.0.0/16', '150.115.0.0/16', '150.121.0.0/16', '150.122.0.0/16', '150.129.152.0/22', '150.129.192.0/22', '150.129.216.0/22', '150.129.252.0/22', '150.138.0.0/15', '150.223.0.0/16', '150.242.0.0/21', '150.242.8.0/22', '150.242.28.0/22', '150.242.44.0/22', '150.242.48.0/21', '150.242.56.0/22', '150.242.76.0/22', '150.242.80.0/22', '150.242.92.0/22', '150.242.96.0/22', '150.242.112.0/21', '150.242.120.0/22', '150.242.152.0/21', '150.242.160.0/21', '150.242.168.0/22', '150.242.184.0/21', '150.242.192.0/22', '150.242.212.0/22', '150.242.224.0/22', '150.242.232.0/21', '150.242.240.0/21', '150.242.248.0/22', '150.255.0.0/16', '152.104.128.0/17', '153.0.0.0/16', '153.3.0.0/16', '153.34.0.0/15', '153.36.0.0/15', '153.99.0.0/16', '153.101.0.0/16', '153.118.0.0/15', '157.0.0.0/16', '157.18.0.0/16', '157.61.0.0/16', '157.122.0.0/16', '157.148.0.0/16', '157.156.0.0/16', '157.255.0.0/16', '159.226.0.0/16', '161.207.0.0/16', '162.105.0.0/16', '163.0.0.0/16', '163.47.4.0/22', '163.53.0.0/20', '163.53.36.0/22', '163.53.40.0/21', '163.53.48.0/20', '163.53.64.0/22', '163.53.88.0/21', '163.53.96.0/19', '163.53.128.0/21', '163.53.136.0/22', '163.53.160.0/20', '163.53.188.0/22', '163.53.220.0/22', '163.53.240.0/22', '163.125.0.0/16', '163.142.0.0/16', '163.177.0.0/16', '163.179.0.0/16', '163.204.0.0/16', '166.111.0.0/16', '167.139.0.0/16', '167.189.0.0/16', '168.160.0.0/16', '171.8.0.0/13', '171.34.0.0/15', '171.36.0.0/14', '171.40.0.0/13', '171.80.0.0/12', '171.104.0.0/13', '171.112.0.0/12', '171.208.0.0/12', '175.0.0.0/12', '175.16.0.0/13', '175.24.0.0/14', '175.30.0.0/15', '175.42.0.0/15', '175.44.0.0/16', '175.46.0.0/15', '175.48.0.0/12', '175.64.0.0/11', '175.102.0.0/16', '175.106.128.0/17', '175.146.0.0/15', '175.148.0.0/14', '175.152.0.0/14', '175.160.0.0/12', '175.178.0.0/16', '175.184.128.0/18', '175.185.0.0/16', '175.186.0.0/15', '175.188.0.0/14', '180.76.0.0/14', '180.84.0.0/15', '180.86.0.0/16', '180.88.0.0/14', '180.94.56.0/21', '180.94.96.0/20', '180.95.128.0/17', '180.96.0.0/11', '180.129.128.0/17', '180.130.0.0/16', '180.136.0.0/13', '180.148.16.0/21', '180.148.152.0/21', '180.148.216.0/21', '180.148.224.0/19', '180.149.128.0/19', '180.150.160.0/19', '180.152.0.0/13', '180.160.0.0/12', '180.178.192.0/18', '180.184.0.0/14', '180.188.0.0/17', '180.189.148.0/22', '180.200.252.0/22', '180.201.0.0/16', '180.202.0.0/15', '180.208.0.0/15', '180.210.224.0/19', '180.212.0.0/15', '180.222.224.0/19', '180.223.0.0/16', '180.233.0.0/18', '180.233.64.0/19', '180.235.64.0/19', '182.16.192.0/19', '182.18.0.0/17', '182.23.184.0/21', '182.23.200.0/21', '182.32.0.0/12', '182.48.96.0/19', '182.49.0.0/16', '182.50.0.0/20', '182.50.112.0/20', '182.51.0.0/16', '182.54.0.0/17', '182.61.0.0/16', '182.80.0.0/13', '182.88.0.0/14', '182.92.0.0/16', '182.96.0.0/11', '182.128.0.0/12', '182.144.0.0/13', '182.157.0.0/16', '182.160.64.0/19', '182.174.0.0/15', '182.200.0.0/13', '182.236.128.0/17', '182.238.0.0/16', '182.239.0.0/19', '182.240.0.0/13', '182.254.0.0/16', '183.0.0.0/10', '183.64.0.0/13', '183.78.180.0/22', '183.81.180.0/22', '183.84.0.0/15', '183.91.128.0/22', '183.91.136.0/21', '183.91.144.0/20', '183.92.0.0/14', '183.128.0.0/11', '183.160.0.0/13', '183.168.0.0/15', '183.170.0.0/16', '183.172.0.0/14', '183.182.0.0/19', '183.184.0.0/13', '183.192.0.0/10', '192.124.154.0/24', '192.188.170.0/24', '202.0.100.0/23', '202.0.122.0/23', '202.0.176.0/22', '202.3.128.0/23', '202.4.128.0/19', '202.4.252.0/22', '202.6.6.0/23', '202.6.66.0/23', '202.6.72.0/23', '202.6.87.0/24', '202.6.88.0/23', '202.6.92.0/23', '202.6.103.0/24', '202.6.108.0/24', '202.6.110.0/23', '202.6.114.0/24', '202.6.176.0/20', '202.8.0.0/24', '202.8.2.0/23', '202.8.4.0/23', '202.8.12.0/24', '202.8.24.0/24', '202.8.77.0/24', '202.8.128.0/19', '202.8.192.0/20', '202.9.32.0/24', '202.9.34.0/23', '202.9.48.0/23', '202.9.51.0/24', '202.9.52.0/23', '202.9.54.0/24', '202.9.57.0/24', '202.9.58.0/23', '202.10.64.0/20', '202.12.1.0/24', '202.12.2.0/24', '202.12.17.0/24', '202.12.18.0/23', '202.12.72.0/24', '202.12.84.0/23', '202.12.96.0/24', '202.12.98.0/23', '202.12.106.0/24', '202.12.111.0/24', '202.12.116.0/24', '202.14.64.0/23', '202.14.69.0/24', '202.14.73.0/24', '202.14.74.0/23', '202.14.76.0/24', '202.14.78.0/23', '202.14.88.0/24', '202.14.97.0/24', '202.14.104.0/23', '202.14.108.0/23', '202.14.111.0/24', '202.14.114.0/23', '202.14.118.0/23', '202.14.124.0/23', '202.14.127.0/24', '202.14.129.0/24', '202.14.135.0/24', '202.14.136.0/24', '202.14.149.0/24', '202.14.151.0/24', '202.14.157.0/24', '202.14.158.0/23', '202.14.169.0/24', '202.14.170.0/23', '202.14.176.0/24', '202.14.184.0/23', '202.14.208.0/23', '202.14.213.0/24', '202.14.219.0/24', '202.14.220.0/24', '202.14.222.0/23', '202.14.225.0/24', '202.14.226.0/23', '202.14.231.0/24', '202.14.235.0/24', '202.14.236.0/22', '202.14.246.0/24', '202.14.251.0/24', '202.20.66.0/24', '202.20.79.0/24', '202.20.87.0/24', '202.20.88.0/23', '202.20.90.0/24', '202.20.94.0/23', '202.20.114.0/24', '202.20.117.0/24', '202.20.120.0/24', '202.20.125.0/24', '202.20.127.0/24', '202.21.131.0/24', '202.21.132.0/24', '202.21.141.0/24', '202.21.142.0/24', '202.21.147.0/24', '202.21.148.0/24', '202.21.150.0/23', '202.21.152.0/23', '202.21.154.0/24', '202.21.156.0/24', '202.22.248.0/21', '202.27.136.0/23', '202.38.0.0/22', '202.38.8.0/21', '202.38.48.0/20', '202.38.64.0/18', '202.38.128.0/21', '202.38.130.0/23', '202.38.136.0/23', '202.38.138.0/24', '202.38.140.0/22', '202.38.146.0/23', '202.38.149.0/24', '202.38.150.0/23', '202.38.152.0/22', '202.38.156.0/24', '202.38.158.0/23', '202.38.160.0/23', '202.38.164.0/22', '202.38.168.0/22', '202.38.176.0/23', '202.38.184.0/21', '202.38.192.0/18', '202.40.4.0/23', '202.40.7.0/24', '202.40.15.0/24', '202.40.135.0/24', '202.40.136.0/24', '202.40.140.0/24', '202.40.143.0/24', '202.40.144.0/23', '202.40.150.0/24', '202.40.155.0/24', '202.40.156.0/24', '202.40.158.0/23', '202.40.162.0/24', '202.41.8.0/23', '202.41.11.0/24', '202.41.12.0/23', '202.41.128.0/24', '202.41.130.0/23', '202.41.152.0/21', '202.41.192.0/24', '202.41.240.0/20', '202.43.76.0/22', '202.43.144.0/20', '202.44.16.0/20', '202.44.67.0/24', '202.44.74.0/24', '202.44.129.0/24', '202.44.132.0/23', '202.44.146.0/23', '202.45.0.0/23', '202.45.2.0/24', '202.45.15.0/24', '202.45.16.0/20', '202.46.16.0/23', '202.46.18.0/24', '202.46.20.0/23', '202.46.32.0/19', '202.46.128.0/24', '202.46.224.0/20', '202.47.82.0/23', '202.47.126.0/24', '202.47.128.0/24', '202.47.130.0/23', '202.57.240.0/20', '202.58.0.0/24', '202.59.0.0/24', '202.59.212.0/22', '202.59.232.0/23', '202.59.236.0/24', '202.60.48.0/21', '202.60.96.0/21', '202.60.112.0/20', '202.60.132.0/22', '202.60.136.0/21', '202.60.144.0/20', '202.62.112.0/22', '202.62.248.0/22', '202.62.252.0/24', '202.62.255.0/24', '202.63.81.0/24', '202.63.82.0/23', '202.63.84.0/22', '202.63.88.0/21', '202.63.160.0/19', '202.63.248.0/22', '202.65.0.0/21', '202.65.8.0/23', '202.67.0.0/22', '202.69.4.0/22', '202.69.16.0/20', '202.70.0.0/19', '202.70.96.0/20', '202.70.192.0/20', '202.72.40.0/21', '202.72.80.0/20', '202.73.128.0/22', '202.74.8.0/21', '202.74.80.0/20', '202.74.254.0/23', '202.75.208.0/20', '202.75.252.0/22', '202.76.252.0/22', '202.77.80.0/21', '202.77.92.0/22', '202.78.8.0/21', '202.79.224.0/21', '202.79.248.0/22', '202.80.192.0/20', '202.81.0.0/22', '202.83.252.0/22', '202.84.0.0/20', '202.84.16.0/23', '202.84.24.0/21', '202.85.208.0/20', '202.86.249.0/24', '202.86.252.0/22', '202.87.80.0/20', '202.89.8.0/21', '202.90.0.0/22', '202.90.112.0/20', '202.90.196.0/24', '202.90.224.0/20', '202.91.0.0/22', '202.91.96.0/20', '202.91.128.0/22', '202.91.176.0/20', '202.91.224.0/19', '202.92.0.0/22', '202.92.8.0/21', '202.92.48.0/20', '202.92.252.0/22', '202.93.0.0/22', '202.93.252.0/22', '202.94.92.0/22', '202.95.0.0/19', '202.95.240.0/21', '202.95.252.0/22', '202.96.0.0/12', '202.112.0.0/13', '202.120.0.0/15', '202.122.0.0/21', '202.122.32.0/21', '202.122.64.0/19', '202.122.112.0/20', '202.122.128.0/24', '202.122.132.0/24', '202.123.96.0/20', '202.124.16.0/21', '202.124.24.0/22', '202.125.112.0/20', '202.125.176.0/20', '202.127.0.0/21', '202.127.12.0/22', '202.127.16.0/20', '202.127.40.0/21', '202.127.48.0/20', '202.127.112.0/20', '202.127.128.0/19', '202.127.160.0/21', '202.127.192.0/20', '202.127.208.0/23', '202.127.212.0/22', '202.127.216.0/21', '202.127.224.0/19', '202.130.0.0/19', '202.130.224.0/19', '202.131.16.0/21', '202.131.48.0/20', '202.131.208.0/20', '202.133.32.0/20', '202.134.58.0/24', '202.134.128.0/20', '202.136.48.0/20', '202.136.208.0/20', '202.136.224.0/20', '202.137.231.0/24', '202.141.160.0/19', '202.142.16.0/20', '202.143.4.0/22', '202.143.16.0/20', '202.143.32.0/20', '202.143.56.0/21', '202.146.160.0/20', '202.146.188.0/22', '202.146.196.0/22', '202.146.200.0/21', '202.147.144.0/20', '202.148.32.0/20', '202.148.64.0/18', '202.149.32.0/19', '202.149.160.0/19', '202.149.224.0/19', '202.150.16.0/20', '202.150.32.0/20', '202.150.56.0/22', '202.150.192.0/20', '202.150.224.0/19', '202.151.0.0/22', '202.151.128.0/19', '202.152.176.0/20', '202.153.0.0/22', '202.153.48.0/20', '202.157.192.0/19', '202.158.160.0/19', '202.160.176.0/20', '202.162.67.0/24', '202.162.75.0/24', '202.164.0.0/20', '202.164.96.0/19', '202.165.96.0/20', '202.165.176.0/20', '202.165.208.0/20', '202.165.239.0/24', '202.165.240.0/23', '202.165.243.0/24', '202.165.245.0/24', '202.165.251.0/24', '202.165.252.0/22', '202.166.224.0/19', '202.168.160.0/19', '202.170.128.0/19', '202.170.216.0/21', '202.170.224.0/19', '202.171.216.0/21', '202.171.235.0/24', '202.172.0.0/22', '202.173.0.0/22', '202.173.8.0/21', '202.173.224.0/19', '202.174.64.0/20', '202.176.224.0/19', '202.179.240.0/20', '202.180.128.0/19', '202.180.208.0/21', '202.181.112.0/20', '202.182.32.0/20', '202.182.192.0/19', '202.189.0.0/18', '202.189.80.0/20', '202.189.184.0/21', '202.191.0.0/24', '202.191.68.0/22', '202.191.72.0/21', '202.191.80.0/20', '202.192.0.0/12', '203.0.4.0/22', '203.0.10.0/23', '203.0.18.0/24', '203.0.24.0/24', '203.0.42.0/23', '203.0.45.0/24', '203.0.46.0/23', '203.0.81.0/24', '203.0.82.0/23', '203.0.90.0/23', '203.0.96.0/23', '203.0.104.0/21', '203.0.114.0/23', '203.0.122.0/24', '203.0.128.0/24', '203.0.130.0/23', '203.0.132.0/22', '203.0.137.0/24', '203.0.142.0/24', '203.0.144.0/24', '203.0.146.0/24', '203.0.148.0/24', '203.0.150.0/23', '203.0.152.0/24', '203.0.177.0/24', '203.0.224.0/24', '203.1.4.0/22', '203.1.18.0/24', '203.1.26.0/23', '203.1.65.0/24', '203.1.66.0/23', '203.1.70.0/23', '203.1.76.0/23', '203.1.90.0/24', '203.1.97.0/24', '203.1.98.0/23', '203.1.100.0/22', '203.1.108.0/24', '203.1.253.0/24', '203.1.254.0/24', '203.2.64.0/21', '203.2.73.0/24', '203.2.112.0/21', '203.2.126.0/23', '203.2.140.0/24', '203.2.150.0/24', '203.2.152.0/22', '203.2.156.0/23', '203.2.160.0/21', '203.2.180.0/23', '203.2.196.0/23', '203.2.209.0/24', '203.2.214.0/23', '203.2.226.0/23', '203.2.229.0/24', '203.2.236.0/23', '203.3.68.0/24', '203.3.72.0/23', '203.3.75.0/24', '203.3.80.0/21', '203.3.96.0/22', '203.3.105.0/24', '203.3.112.0/21', '203.3.120.0/24', '203.3.123.0/24', '203.3.135.0/24', '203.3.139.0/24', '203.3.143.0/24', '203.4.132.0/23', '203.4.134.0/24', '203.4.151.0/24', '203.4.152.0/22', '203.4.174.0/23', '203.4.180.0/24', '203.4.186.0/24', '203.4.205.0/24', '203.4.208.0/22', '203.4.227.0/24', '203.4.230.0/23', '203.5.4.0/23', '203.5.7.0/24', '203.5.8.0/23', '203.5.11.0/24', '203.5.21.0/24', '203.5.22.0/24', '203.5.44.0/24', '203.5.46.0/23', '203.5.52.0/22', '203.5.56.0/23', '203.5.60.0/23', '203.5.114.0/23', '203.5.118.0/24', '203.5.120.0/24', '203.5.172.0/24', '203.5.180.0/23', '203.5.182.0/24', '203.5.185.0/24', '203.5.186.0/24', '203.5.188.0/23', '203.5.190.0/24', '203.5.195.0/24', '203.5.214.0/23', '203.5.218.0/23', '203.6.131.0/24', '203.6.136.0/24', '203.6.138.0/23', '203.6.142.0/24', '203.6.150.0/23', '203.6.157.0/24', '203.6.159.0/24', '203.6.224.0/20', '203.6.248.0/23', '203.7.129.0/24', '203.7.138.0/23', '203.7.147.0/24', '203.7.150.0/23', '203.7.158.0/24', '203.7.192.0/23', '203.7.200.0/24', '203.8.0.0/24', '203.8.8.0/24', '203.8.23.0/24', '203.8.24.0/21', '203.8.70.0/24', '203.8.82.0/24', '203.8.86.0/23', '203.8.91.0/24', '203.8.110.0/23', '203.8.115.0/24', '203.8.166.0/23', '203.8.169.0/24', '203.8.173.0/24', '203.8.184.0/24', '203.8.186.0/23', '203.8.190.0/23', '203.8.192.0/24', '203.8.197.0/24', '203.8.198.0/23', '203.8.203.0/24', '203.8.209.0/24', '203.8.210.0/23', '203.8.212.0/22', '203.8.217.0/24', '203.8.220.0/24', '203.9.32.0/24', '203.9.36.0/23', '203.9.57.0/24', '203.9.63.0/24', '203.9.65.0/24', '203.9.70.0/23', '203.9.72.0/24', '203.9.75.0/24', '203.9.76.0/23', '203.9.96.0/22', '203.9.100.0/23', '203.9.108.0/24', '203.9.158.0/24', '203.10.34.0/24', '203.10.56.0/24', '203.10.74.0/23', '203.10.84.0/22', '203.10.88.0/24', '203.10.95.0/24', '203.10.125.0/24', '203.11.70.0/24', '203.11.76.0/22', '203.11.82.0/24', '203.11.84.0/22', '203.11.100.0/22', '203.11.109.0/24', '203.11.117.0/24', '203.11.122.0/24', '203.11.126.0/24', '203.11.136.0/22', '203.11.141.0/24', '203.11.142.0/23', '203.11.180.0/22', '203.11.208.0/22', '203.12.16.0/24', '203.12.19.0/24', '203.12.24.0/24', '203.12.57.0/24', '203.12.65.0/24', '203.12.66.0/24', '203.12.70.0/23', '203.12.87.0/24', '203.12.88.0/21', '203.12.100.0/23', '203.12.103.0/24', '203.12.114.0/24', '203.12.118.0/24', '203.12.130.0/24', '203.12.137.0/24', '203.12.196.0/22', '203.12.200.0/21', '203.12.211.0/24', '203.12.219.0/24', '203.12.226.0/24', '203.12.240.0/22', '203.13.18.0/24', '203.13.24.0/24', '203.13.44.0/23', '203.13.80.0/21', '203.13.88.0/23', '203.13.92.0/22', '203.13.173.0/24', '203.13.224.0/23', '203.13.227.0/24', '203.13.233.0/24', '203.14.24.0/22', '203.14.33.0/24', '203.14.56.0/24', '203.14.61.0/24', '203.14.62.0/24', '203.14.104.0/24', '203.14.114.0/23', '203.14.118.0/24', '203.14.162.0/24', '203.14.184.0/21', '203.14.192.0/24', '203.14.194.0/23', '203.14.214.0/24', '203.14.231.0/24', '203.14.246.0/24', '203.15.0.0/20', '203.15.20.0/23', '203.15.22.0/24', '203.15.87.0/24', '203.15.88.0/23', '203.15.105.0/24', '203.15.112.0/21', '203.15.130.0/23', '203.15.149.0/24', '203.15.151.0/24', '203.15.156.0/22', '203.15.174.0/24', '203.15.227.0/24', '203.15.232.0/21', '203.15.240.0/23', '203.15.246.0/24', '203.16.10.0/24', '203.16.12.0/23', '203.16.16.0/21', '203.16.27.0/24', '203.16.38.0/24', '203.16.49.0/24', '203.16.50.0/23', '203.16.58.0/24', '203.16.133.0/24', '203.16.161.0/24', '203.16.162.0/24', '203.16.186.0/23', '203.16.228.0/24', '203.16.238.0/24', '203.16.240.0/24', '203.16.245.0/24', '203.17.2.0/24', '203.17.18.0/24', '203.17.28.0/24', '203.17.39.0/24', '203.17.56.0/24', '203.17.74.0/23', '203.17.88.0/23', '203.17.136.0/24', '203.17.164.0/24', '203.17.187.0/24', '203.17.190.0/23', '203.17.231.0/24', '203.17.233.0/24', '203.17.248.0/24', '203.17.255.0/24', '203.18.2.0/23', '203.18.4.0/24', '203.18.7.0/24', '203.18.31.0/24', '203.18.37.0/24', '203.18.48.0/23', '203.18.50.0/24', '203.18.52.0/24', '203.18.72.0/22', '203.18.80.0/23', '203.18.87.0/24', '203.18.100.0/23', '203.18.105.0/24', '203.18.107.0/24', '203.18.110.0/24', '203.18.129.0/24', '203.18.131.0/24', '203.18.132.0/23', '203.18.144.0/24', '203.18.153.0/24', '203.18.199.0/24', '203.18.208.0/24', '203.18.211.0/24', '203.18.215.0/24', '203.19.18.0/24', '203.19.24.0/24', '203.19.30.0/24', '203.19.32.0/21', '203.19.41.0/24', '203.19.44.0/23', '203.19.46.0/24', '203.19.58.0/24', '203.19.60.0/23', '203.19.64.0/24', '203.19.68.0/24', '203.19.72.0/24', '203.19.101.0/24', '203.19.111.0/24', '203.19.131.0/24', '203.19.133.0/24', '203.19.144.0/24', '203.19.149.0/24', '203.19.156.0/24', '203.19.176.0/24', '203.19.178.0/23', '203.19.208.0/24', '203.19.228.0/22', '203.19.233.0/24', '203.19.242.0/24', '203.19.248.0/23', '203.19.255.0/24', '203.20.17.0/24', '203.20.40.0/23', '203.20.48.0/24', '203.20.61.0/24', '203.20.65.0/24', '203.20.84.0/23', '203.20.89.0/24', '203.20.106.0/23', '203.20.115.0/24', '203.20.117.0/24', '203.20.118.0/23', '203.20.122.0/24', '203.20.126.0/23', '203.20.135.0/24', '203.20.136.0/21', '203.20.150.0/24', '203.20.230.0/24', '203.20.232.0/24', '203.20.236.0/24', '203.21.0.0/23', '203.21.2.0/24', '203.21.8.0/24', '203.21.10.0/24', '203.21.18.0/24', '203.21.33.0/24', '203.21.34.0/24', '203.21.41.0/24', '203.21.44.0/24', '203.21.68.0/24', '203.21.82.0/24', '203.21.96.0/22', '203.21.124.0/24', '203.21.136.0/23', '203.21.145.0/24', '203.21.206.0/24', '203.22.24.0/24', '203.22.28.0/23', '203.22.31.0/24', '203.22.68.0/24', '203.22.76.0/24', '203.22.78.0/24', '203.22.84.0/24', '203.22.87.0/24', '203.22.92.0/22', '203.22.99.0/24', '203.22.106.0/24', '203.22.122.0/23', '203.22.131.0/24', '203.22.163.0/24', '203.22.166.0/24', '203.22.170.0/24', '203.22.176.0/21', '203.22.194.0/24', '203.22.242.0/23', '203.22.245.0/24', '203.22.246.0/24', '203.22.252.0/23', '203.23.0.0/24', '203.23.47.0/24', '203.23.61.0/24', '203.23.62.0/23', '203.23.73.0/24', '203.23.85.0/24', '203.23.92.0/22', '203.23.98.0/24', '203.23.107.0/24', '203.23.112.0/24', '203.23.130.0/24', '203.23.140.0/23', '203.23.172.0/24', '203.23.182.0/24', '203.23.186.0/23', '203.23.192.0/24', '203.23.197.0/24', '203.23.198.0/24', '203.23.204.0/22', '203.23.224.0/24', '203.23.226.0/23', '203.23.228.0/22', '203.23.249.0/24', '203.23.251.0/24', '203.24.13.0/24', '203.24.18.0/24', '203.24.27.0/24', '203.24.43.0/24', '203.24.56.0/24', '203.24.58.0/24', '203.24.67.0/24', '203.24.74.0/24', '203.24.79.0/24', '203.24.80.0/23', '203.24.84.0/23', '203.24.86.0/24', '203.24.90.0/24', '203.24.111.0/24', '203.24.112.0/24', '203.24.116.0/24', '203.24.122.0/23', '203.24.145.0/24', '203.24.152.0/23', '203.24.157.0/24', '203.24.161.0/24', '203.24.167.0/24', '203.24.186.0/23', '203.24.199.0/24', '203.24.202.0/24', '203.24.212.0/23', '203.24.217.0/24', '203.24.219.0/24', '203.24.244.0/24', '203.25.19.0/24', '203.25.20.0/23', '203.25.46.0/24', '203.25.48.0/21', '203.25.64.0/23', '203.25.91.0/24', '203.25.99.0/24', '203.25.100.0/24', '203.25.106.0/24', '203.25.131.0/24', '203.25.135.0/24', '203.25.138.0/24', '203.25.147.0/24', '203.25.153.0/24', '203.25.154.0/23', '203.25.164.0/24', '203.25.166.0/24', '203.25.174.0/23', '203.25.180.0/24', '203.25.182.0/24', '203.25.191.0/24', '203.25.199.0/24', '203.25.200.0/24', '203.25.202.0/23', '203.25.208.0/20', '203.25.229.0/24', '203.25.235.0/24', '203.25.236.0/24', '203.25.242.0/24', '203.26.12.0/24', '203.26.34.0/24', '203.26.49.0/24', '203.26.50.0/24', '203.26.55.0/24', '203.26.56.0/23', '203.26.60.0/24', '203.26.65.0/24', '203.26.68.0/24', '203.26.76.0/24', '203.26.80.0/24', '203.26.84.0/24', '203.26.97.0/24', '203.26.102.0/23', '203.26.115.0/24', '203.26.116.0/24', '203.26.129.0/24', '203.26.143.0/24', '203.26.144.0/24', '203.26.148.0/23', '203.26.154.0/24', '203.26.158.0/23', '203.26.170.0/24', '203.26.173.0/24', '203.26.176.0/24', '203.26.185.0/24', '203.26.202.0/23', '203.26.210.0/24', '203.26.214.0/24', '203.26.222.0/24', '203.26.224.0/24', '203.26.228.0/24', '203.26.232.0/24', '203.27.0.0/24', '203.27.10.0/24', '203.27.15.0/24', '203.27.16.0/24', '203.27.20.0/24', '203.27.22.0/23', '203.27.40.0/24', '203.27.45.0/24', '203.27.53.0/24', '203.27.65.0/24', '203.27.66.0/24', '203.27.81.0/24', '203.27.88.0/24', '203.27.102.0/24', '203.27.109.0/24', '203.27.117.0/24', '203.27.121.0/24', '203.27.122.0/23', '203.27.125.0/24', '203.27.200.0/24', '203.27.202.0/24', '203.27.233.0/24', '203.27.241.0/24', '203.27.250.0/24', '203.28.10.0/24', '203.28.12.0/24', '203.28.33.0/24', '203.28.34.0/23', '203.28.43.0/24', '203.28.44.0/24', '203.28.54.0/24', '203.28.56.0/24', '203.28.73.0/24', '203.28.74.0/24', '203.28.76.0/24', '203.28.86.0/24', '203.28.88.0/24', '203.28.112.0/24', '203.28.131.0/24', '203.28.136.0/24', '203.28.140.0/24', '203.28.145.0/24', '203.28.165.0/24', '203.28.169.0/24', '203.28.170.0/24', '203.28.178.0/23', '203.28.185.0/24', '203.28.187.0/24', '203.28.196.0/24', '203.28.226.0/23', '203.28.239.0/24', '203.29.2.0/24', '203.29.8.0/23', '203.29.13.0/24', '203.29.14.0/24', '203.29.28.0/24', '203.29.46.0/24', '203.29.57.0/24', '203.29.61.0/24', '203.29.63.0/24', '203.29.69.0/24', '203.29.73.0/24', '203.29.81.0/24', '203.29.90.0/24', '203.29.95.0/24', '203.29.100.0/24', '203.29.103.0/24', '203.29.112.0/24', '203.29.120.0/22', '203.29.182.0/23', '203.29.187.0/24', '203.29.189.0/24', '203.29.190.0/24', '203.29.205.0/24', '203.29.210.0/24', '203.29.217.0/24', '203.29.227.0/24', '203.29.231.0/24', '203.29.233.0/24', '203.29.234.0/24', '203.29.248.0/24', '203.29.254.0/23', '203.30.16.0/23', '203.30.25.0/24', '203.30.27.0/24', '203.30.29.0/24', '203.30.66.0/24', '203.30.81.0/24', '203.30.87.0/24', '203.30.111.0/24', '203.30.121.0/24', '203.30.123.0/24', '203.30.152.0/24', '203.30.156.0/24', '203.30.162.0/24', '203.30.173.0/24', '203.30.175.0/24', '203.30.187.0/24', '203.30.194.0/24', '203.30.217.0/24', '203.30.220.0/24', '203.30.222.0/24', '203.30.232.0/23', '203.30.235.0/24', '203.30.240.0/23', '203.30.246.0/24', '203.30.250.0/23', '203.31.45.0/24', '203.31.46.0/24', '203.31.49.0/24', '203.31.51.0/24', '203.31.54.0/23', '203.31.69.0/24', '203.31.72.0/24', '203.31.80.0/24', '203.31.85.0/24', '203.31.97.0/24', '203.31.105.0/24', '203.31.106.0/24', '203.31.108.0/23', '203.31.124.0/24', '203.31.162.0/24', '203.31.174.0/24', '203.31.177.0/24', '203.31.181.0/24', '203.31.187.0/24', '203.31.189.0/24', '203.31.204.0/24', '203.31.220.0/24', '203.31.222.0/23', '203.31.225.0/24', '203.31.229.0/24', '203.31.248.0/23', '203.31.253.0/24', '203.32.20.0/24', '203.32.48.0/23', '203.32.56.0/24', '203.32.60.0/24', '203.32.62.0/24', '203.32.68.0/23', '203.32.76.0/24', '203.32.81.0/24', '203.32.84.0/23', '203.32.95.0/24', '203.32.102.0/24', '203.32.105.0/24', '203.32.130.0/24', '203.32.133.0/24', '203.32.140.0/24', '203.32.152.0/24', '203.32.186.0/23', '203.32.192.0/24', '203.32.196.0/24', '203.32.203.0/24', '203.32.204.0/23', '203.32.212.0/24', '203.33.4.0/24', '203.33.7.0/24', '203.33.8.0/21', '203.33.21.0/24', '203.33.26.0/24', '203.33.32.0/24', '203.33.63.0/24', '203.33.64.0/24', '203.33.67.0/24', '203.33.68.0/24', '203.33.73.0/24', '203.33.79.0/24', '203.33.100.0/24', '203.33.122.0/24', '203.33.129.0/24', '203.33.131.0/24', '203.33.145.0/24', '203.33.156.0/24', '203.33.158.0/23', '203.33.174.0/24', '203.33.185.0/24', '203.33.200.0/24', '203.33.202.0/23', '203.33.204.0/24', '203.33.206.0/23', '203.33.214.0/23', '203.33.224.0/23', '203.33.226.0/24', '203.33.233.0/24', '203.33.243.0/24', '203.33.250.0/24', '203.34.4.0/24', '203.34.21.0/24', '203.34.27.0/24', '203.34.39.0/24', '203.34.48.0/23', '203.34.54.0/24', '203.34.56.0/23', '203.34.67.0/24', '203.34.69.0/24', '203.34.76.0/24', '203.34.92.0/24', '203.34.106.0/24', '203.34.113.0/24', '203.34.147.0/24', '203.34.150.0/24', '203.34.152.0/23', '203.34.161.0/24', '203.34.162.0/24', '203.34.187.0/24', '203.34.192.0/21', '203.34.204.0/22', '203.34.232.0/24', '203.34.240.0/24', '203.34.242.0/24', '203.34.245.0/24', '203.34.251.0/24', '203.55.2.0/23', '203.55.4.0/24', '203.55.10.0/24', '203.55.13.0/24', '203.55.22.0/24', '203.55.30.0/24', '203.55.93.0/24', '203.55.101.0/24', '203.55.109.0/24', '203.55.110.0/24', '203.55.116.0/23', '203.55.119.0/24', '203.55.128.0/23', '203.55.146.0/23', '203.55.192.0/24', '203.55.196.0/24', '203.55.218.0/23', '203.55.221.0/24', '203.55.224.0/24', '203.56.1.0/24', '203.56.4.0/24', '203.56.12.0/24', '203.56.24.0/24', '203.56.38.0/24', '203.56.40.0/24', '203.56.46.0/24', '203.56.48.0/21', '203.56.68.0/23', '203.56.82.0/23', '203.56.84.0/23', '203.56.95.0/24', '203.56.110.0/24', '203.56.121.0/24', '203.56.161.0/24', '203.56.169.0/24', '203.56.172.0/23', '203.56.175.0/24', '203.56.183.0/24', '203.56.185.0/24', '203.56.187.0/24', '203.56.192.0/24', '203.56.198.0/24', '203.56.201.0/24', '203.56.208.0/23', '203.56.210.0/24', '203.56.214.0/24', '203.56.216.0/24', '203.56.227.0/24', '203.56.228.0/24', '203.56.232.0/24', '203.56.240.0/24', '203.56.252.0/24', '203.56.254.0/24', '203.57.5.0/24', '203.57.6.0/24', '203.57.12.0/23', '203.57.28.0/24', '203.57.39.0/24', '203.57.46.0/24', '203.57.58.0/24', '203.57.61.0/24', '203.57.66.0/24', '203.57.69.0/24', '203.57.70.0/23', '203.57.73.0/24', '203.57.90.0/24', '203.57.101.0/24', '203.57.109.0/24', '203.57.123.0/24', '203.57.157.0/24', '203.57.200.0/24', '203.57.202.0/24', '203.57.206.0/24', '203.57.222.0/24', '203.57.224.0/20', '203.57.246.0/23', '203.57.249.0/24', '203.57.253.0/24', '203.57.254.0/23', '203.62.2.0/24', '203.62.131.0/24', '203.62.139.0/24', '203.62.161.0/24', '203.62.197.0/24', '203.62.228.0/22', '203.62.234.0/24', '203.62.246.0/24', '203.76.160.0/22', '203.76.168.0/22', '203.77.180.0/22', '203.78.48.0/20', '203.79.0.0/20', '203.79.32.0/20', '203.80.4.0/23', '203.80.32.0/20', '203.80.57.0/24', '203.80.132.0/22', '203.80.136.0/21', '203.80.144.0/20', '203.81.0.0/21', '203.81.16.0/20', '203.82.0.0/23', '203.82.16.0/21', '203.83.0.0/22', '203.83.56.0/21', '203.83.224.0/20', '203.86.0.0/17', '203.86.254.0/23', '203.88.32.0/19', '203.88.192.0/19', '203.89.0.0/22', '203.89.8.0/21', '203.89.136.0/22', '203.90.0.0/22', '203.90.8.0/22', '203.90.128.0/18', '203.90.192.0/19', '203.91.32.0/19', '203.91.96.0/20', '203.91.120.0/21', '203.92.0.0/22', '203.92.160.0/19', '203.93.0.0/16', '203.94.0.0/19', '203.95.0.0/21', '203.95.96.0/19', '203.95.128.0/18', '203.95.224.0/19', '203.99.8.0/21', '203.99.16.0/20', '203.99.80.0/20', '203.100.32.0/20', '203.100.48.0/21', '203.100.63.0/24', '203.100.80.0/20', '203.100.96.0/19', '203.100.192.0/20', '203.104.32.0/20', '203.105.96.0/19', '203.105.128.0/19', '203.107.0.0/17', '203.110.160.0/19', '203.110.208.0/20', '203.110.232.0/23', '203.110.234.0/24', '203.114.244.0/22', '203.118.192.0/19', '203.118.241.0/24', '203.118.248.0/22', '203.119.24.0/21', '203.119.32.0/22', '203.119.80.0/22', '203.119.85.0/24', '203.119.113.0/24', '203.119.114.0/23', '203.119.116.0/22', '203.119.120.0/21', '203.119.128.0/17', '203.128.32.0/19', '203.128.96.0/19', '203.128.224.0/21', '203.129.8.0/21', '203.130.32.0/19', '203.132.32.0/19', '203.134.240.0/21', '203.135.96.0/19', '203.135.160.0/20', '203.142.224.0/19', '203.144.96.0/19', '203.145.0.0/19', '203.148.0.0/18', '203.148.64.0/20', '203.148.80.0/22', '203.148.86.0/23', '203.149.92.0/22', '203.152.64.0/19', '203.152.128.0/19', '203.153.0.0/22', '203.156.192.0/18', '203.158.16.0/21', '203.160.104.0/21', '203.160.129.0/24', '203.160.192.0/19', '203.161.0.0/22', '203.161.180.0/24', '203.161.192.0/19', '203.166.160.0/19', '203.168.0.0/19', '203.170.58.0/23', '203.171.0.0/22', '203.171.224.0/20', '203.174.4.0/24', '203.174.7.0/24', '203.174.96.0/19', '203.175.128.0/19', '203.175.192.0/18', '203.176.0.0/18', '203.176.64.0/19', '203.176.168.0/21', '203.184.80.0/20', '203.187.160.0/19', '203.189.0.0/23', '203.189.6.0/23', '203.189.112.0/22', '203.189.192.0/19', '203.190.96.0/20', '203.190.249.0/24', '203.191.0.0/23', '203.191.16.0/20', '203.191.64.0/18', '203.191.144.0/20', '203.192.0.0/19', '203.193.224.0/19', '203.194.120.0/21', '203.195.64.0/19', '203.195.112.0/21', '203.195.128.0/17', '203.196.0.0/20', '203.202.236.0/22', '203.205.64.0/19', '203.205.128.0/17', '203.207.64.0/18', '203.207.128.0/17', '203.208.0.0/20', '203.208.16.0/22', '203.208.32.0/19', '203.209.224.0/19', '203.212.0.0/20', '203.212.80.0/20', '203.215.232.0/21', '203.222.192.0/20', '203.223.0.0/20', '203.223.16.0/21', '210.2.0.0/19', '210.5.0.0/19', '210.5.56.0/21', '210.5.128.0/19', '210.12.0.0/15', '210.14.64.0/19', '210.14.112.0/20', '210.14.128.0/17', '210.15.0.0/17', '210.15.128.0/18', '210.16.128.0/18', '210.21.0.0/16', '210.22.0.0/16', '210.23.32.0/19', '210.25.0.0/16', '210.26.0.0/15', '210.28.0.0/14', '210.32.0.0/12', '210.48.136.0/21', '210.51.0.0/16', '210.52.0.0/15', '210.56.192.0/19', '210.72.0.0/14', '210.76.0.0/15', '210.78.0.0/16', '210.79.64.0/18', '210.79.224.0/19', '210.82.0.0/15', '210.87.128.0/18', '210.185.192.0/18', '210.192.96.0/19', '211.64.0.0/13', '211.80.0.0/12', '211.96.0.0/13', '211.136.0.0/13', '211.144.0.0/12', '211.160.0.0/13', '218.0.0.0/11', '218.56.0.0/13', '218.64.0.0/11', '218.96.0.0/14', '218.100.88.0/21', '218.100.96.0/19', '218.100.128.0/17', '218.104.0.0/14', '218.108.0.0/15', '218.185.192.0/19', '218.185.240.0/21', '218.192.0.0/12', '218.240.0.0/13', '218.249.0.0/16', '219.72.0.0/16', '219.82.0.0/16', '219.83.128.0/17', '219.128.0.0/11', '219.216.0.0/13', '219.224.0.0/12', '219.242.0.0/15', '219.244.0.0/14', '220.101.192.0/18', '220.112.0.0/14', '220.152.128.0/17', '220.154.0.0/15', '220.160.0.0/11', '220.192.0.0/12', '220.231.0.0/18', '220.231.128.0/17', '220.232.64.0/18', '220.234.0.0/16', '220.242.0.0/15', '220.247.136.0/21', '220.248.0.0/14', '220.252.0.0/16', '221.0.0.0/13', '221.8.0.0/14', '221.12.0.0/17', '221.12.128.0/18', '221.13.0.0/16', '221.14.0.0/15', '221.122.0.0/15', '221.128.128.0/17', '221.129.0.0/16', '221.130.0.0/15', '221.133.224.0/19', '221.136.0.0/15', '221.172.0.0/14', '221.176.0.0/13', '221.192.0.0/14', '221.196.0.0/15', '221.198.0.0/16', '221.199.0.0/17', '221.199.128.0/18', '221.199.192.0/20', '221.199.224.0/19', '221.200.0.0/13', '221.208.0.0/12', '221.224.0.0/12', '222.16.0.0/12', '222.32.0.0/11', '222.64.0.0/11', '222.125.0.0/16', '222.126.128.0/17', '222.128.0.0/12', '222.160.0.0/14', '222.168.0.0/13', '222.176.0.0/12', '222.192.0.0/11', '222.240.0.0/13', '222.248.0.0/15', '223.0.0.0/12', '223.20.0.0/15', '223.27.184.0/22', '223.64.0.0/11', '223.96.0.0/12', '223.112.0.0/14', '223.116.0.0/15', '223.120.0.0/13', '223.128.0.0/15', '223.144.0.0/12', '223.160.0.0/14', '223.166.0.0/15', '223.192.0.0/15', '223.198.0.0/15', '223.201.0.0/16', '223.202.0.0/15', '223.208.0.0/13', '223.220.0.0/15', '223.223.176.0/20', '223.223.192.0/20', '223.240.0.0/13', '223.248.0.0/14', '223.252.128.0/17', '223.254.0.0/16', '223.255.0.0/17', '223.255.236.0/22', '223.255.252.0/23');
    foreach ($chinaCIDRS as $cidr) {
        if (ipCIDRCheck($_SERVER['REMOTE_ADDR'], $cidr)) {
            return true;
        }
    }
    if (strpos($_SERVER['REMOTE_ADDR'], '65.49.') === 0) {
        return true;
    }
    if ($hostname && $hostname != '.' && substr_compare($hostname, $test, -strlen($test), strlen($test)) === 0) {
        return true;
    }

    return false;
}

function isHindu($ip)
{
    if ($ip == '100.33.0.74') {
        return true;
    }

    return false;
}

function getFlagForIP($ip)
{
    if (isMatt($ip)) {
        return 'china';
    }

    return;
}

function getRandomYoutube()
{
    $youtubes = array(
        'rv9zEcispCU', 'hAWEaXvezoY', 'wmSvzxcD9yw', '1VaHIflPemM', 'UxTVYvHbwTs', 'fln0QbxiCPk', '9ivVw9v-QZI', 'QYeIVWQxcZQ',
    );

    shuffle($youtubes);

    $html = "<br /><br /><div style='position: relative'>";
    if (!MOBILE_MODE) {
        $html .= "<div style='position: absolute; left: 0px; top: 0px; width: 100%; height: 100%; z-index: 9999'></div>";
    }
    $html .= '<iframe width="560" height="315" tabindex="-1" src="//www.youtube-nocookie.com/embed/'.$youtubes[0].'?version=3&playlist='.implode(',', array_slice($youtubes, 1)).'&autoplay=1&loop=1&controls=0&disablekb=1&cc_lang_pref=en&cc_load_policy=1&playsinline=1" frameborder="0" allowfullscreen></iframe>';
    $html .= '</div>';

    return $html;
}

function canSeeStealthBannedPost($uid, $ip)
{
    global $stealth_banned;
    if ($stealth_banned) {
        return true;
    }
    if ($uid == $_SESSION['UID']) {
        return true;
    }
    if ($ip == $_SERVER['REMOTE_ADDR']) {
        return true;
    }
    $ip1 = explode('.', $ip);
    $ip2 = explode('.', $_SERVER['REMOTE_ADDR']);
    if ($ip1[0] == $ip2[0] && $ip1[1] == $ip2[1] && $ip1[2] == $ip2[2]) {
        return true;
    }

    return false;
}

function matchIgnoredName($ignoredNames, $namefag, $tripfag)
{
    if (!$namefag && !$tripfag) {
        return false;
    }

    foreach ($ignoredNames as $ignoredName) {
        if (preg_match('/^([^!]*)(?: ?!(.*?))?$/', $ignoredName, $bits)) {
            $ignoredNamefag = trim($bits[1]);
            $ignoredTripfag = trim($bits[2]);
            if (!$ignoredNamefag && !$ignoredTripfag) {
                continue;
            }
            $lTripfag = substr(trim($tripfag), 1);
            $lNamefag = trim($namefag);

            $needMatches = 0;
            if ($ignoredNamefag) {
                ++$needMatches;
            }
            if ($ignoredTripfag) {
                ++$needMatches;
            }

            if ($ignoredNamefag && $lNamefag && strcasecmp($ignoredNamefag, $lNamefag) == 0) {
                --$needMatches;
            }
            if ($ignoredTripfag && $lTripfag && strcasecmp($ignoredTripfag, $lTripfag) == 0) {
                --$needMatches;
            }

            if ($needMatches == 0) {
                return true;
            }
        }
    }

    return false;
}

function dashboardDefaults()
{
    return array(
        'memorable_name' => '',
        'memorable_password' => '',
        'email' => '',
        'topics_mode' => 0,
        'spoiler_mode' => 0,
        'ostrich_mode' => 0,
        'disable_images' => 0,
        'snippet_length' => 80,
        'image_viewer' => 1,
        'rounded_corners' => 0,
        'style' => DEFAULT_STYLESHEET,
    );
}

function template($name)
{
    if (file_exists("includes/templates/override/$name.php")) {
        return "includes/templates/override/$name.php";
    }

    return "includes/templates/$name.php";
}

?>
