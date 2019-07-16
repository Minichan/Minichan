<?php
require 'includes/header.php';

if (!allowed('open_ip')) {
    add_error(MESSAGE_PAGE_ACCESS_DENIED, true);
}

// Validate IP address.
if (!filter_var($_GET['ip'], FILTER_VALIDATE_IP)) {
    add_error('That is not a valid IP address.', true);
}

$ip_address = $_GET['ip'];
$hostname = gethostbyaddr($ip_address);
if ($hostname === $ip_address) {
    $hostname = false;
}

$page_title = 'Information on IP address '.$ip_address;

// If a ban request has been submitted.
if (!empty($_POST['ban_length']) && allowed('ban_ip')) {
    // CSRF checking.
    check_token();
    if ($_POST['ban_length'] == 'indefinite' | $_POST['ban_length'] == 'infinite') {
        $new_ban_expiry = 0;
    } elseif (strtotime($_POST['ban_length']) > $_SERVER['REQUEST_TIME']) {
        $new_ban_expiry = strtotime($_POST['ban_length']);
    } else {
        add_error('Invalid ban length.');
    }

    if (strlen($_POST['ban_reason']) > 255) {
        add_error('Ban reason too long.');
    }

    $banreason = $_POST['ban_reason'];

    $link->db_exec('SELECT uid FROM users WHERE ip_address = %1', $ip_address);
    while (list($uid) = $link->fetch_row()) {
        if ($uid == $_SESSION['UID']) {
            add_error('Errr... What are you trying to do?', true);
        }

        if (in_array($uid, $administrators)) {
            add_error(MESSAGE_PAGE_ACCESS_DENIED, true);
        }

        if (allowed('ban_ip', $uid) && !$administrator) {
            add_error(MESSAGE_PAGE_ACCESS_DENIED, true);
        }
    }

    if (!$erred) {
        $sqldata = array();
        $sqldata['ip_address'] = $ip_address;
        $sqldata['expiry'] = $new_ban_expiry;
        $sqldata['reason'] = $banreason;
        $sqldata['filed'] = 'UNIX_TIMESTAMP()';
        $sqldata['who'] = $_SESSION['UID'];
        $sqldata['stealth'] = $_POST['stealth_ban'];
        $ban_ip = $link->insertorupdate('ip_bans', $sqldata);
        log_mod('ban_ip', $ip_address);
        $_SESSION['notice'] = 'IP address banned.';

        if (isset($_POST['banuids'])) {
            $stmt = $link->db_exec('SELECT uid FROM users WHERE ip_address = %1', $ip_address);

            while (list($uid) = $link->fetch_row($stmt)) {
                $sqldata = array();
                $sqldata['uid'] = $uid;
                $sqldata['expiry'] = $new_ban_expiry;
                $sqldata['filed'] = 'UNIX_TIMESTAMP()';
                $sqldata['who'] = $_SESSION['UID'];
                $sqldata['reason'] = $banreason;
                $sqldata['stealth'] = $_POST['stealth_ban'];
                $ban_ip = $link->insertorupdate('uid_bans', $sqldata);
                log_mod('ban_uid', $uid);
            }
            $_SESSION['notice'] = 'IP address & UIDs banned.';
        }
    }
} else {
    log_mod('open_ip', $ip_address);
}

// Check for ban.
$check_ban = $link->db_exec('SELECT filed, expiry, reason, stealth FROM ip_bans WHERE ip_address = %1', $ip_address);
list($ban_filed, $ban_expiry, $banreason, $is_stealth_banned) = $link->fetch_row($check_ban);

$banned = false;
if (!empty($ban_filed)) {
    if ($ban_expiry == 0 || $ban_expiry > $_SERVER['REQUEST_TIME']) {
        $banned = true;
    } else {
        // The ban has already expired.

        remove_ip_ban($ip_address);
    }
}

// Get statistics.
$query = array();
$query[] = "SELECT count(*) FROM topics WHERE author_ip = '".$link->escape($ip_address)."';";
$query[] = "SELECT count(*) FROM replies WHERE author_ip = '".$link->escape($ip_address)."';";
$query[] = "SELECT count(*) FROM users WHERE ip_address = '".$link->escape($ip_address)."';";

foreach ($query as $q) {
    $result = $link->db_exec($q);
    while ($row = $link->fetch_row($result)) {
        $queries[] = $row[0];
    }
}

$ip_num_topics = $queries[0];
$ip_num_replies = $queries[1];
$ip_num_ids = $queries[2];

print_errors();

echo '<p>This IP address (';
if ($hostname) {
    echo '<strong>'.$hostname.'</strong>';
} else {
    echo 'no valid host name';
}
echo ') is associated with <strong>'.$ip_num_ids.'</strong> ID'.($ip_num_ids == 1 ? '' : 's').' and has been used to post <strong>'.$ip_num_topics.'</strong> existing topic'.($ip_num_topics == 1 ? '' : 's').' and <strong>'.$ip_num_replies.'</strong> existing repl'.($ip_num_replies == 1 ? 'y' : 'ies').'.</p>';
if ($banned) {
    echo '<p>This IP is currently <strong>banned</strong>. The ban was filed <span class="help" title="'.format_date($ban_filed).'">'.calculate_age($ban_filed).' ago</span> and will ';
    if ($ban_expiry == 0) {
        echo 'last indefinitely';
    } else {
        echo 'expire in '.calculate_age($ban_expiry);
    }
    echo '.</p>';
}

if (allowed('ban_ip')) {
    ?>
<form action="" method="post">
	<?php csrf_token() ?>
	<div class="row">
		<label for="ban_length" class="inline">Ban length&nbsp;&nbsp;</label>
		<input type="text" name="ban_length" id="ban_length" value="<?php if (!$banned) {
    echo '1 week';
}
    ?>" class="inline" />
		<input type="checkbox" name="stealth_ban" id="stealth_ban" class="inline" value="1" <?php echo $is_stealth_banned ? 'checked="checked"' : '' ?> />
		<label for="stealth_ban">Stealth ban</label>
<br />
		<label for="ban_length" class="inline">Ban reason</label>
		<input type="text" maxlength="255" name="ban_reason" id="ban_reason" value="<?php if ($banreason) {
    echo htmlspecialchars($banreason);
}
    ?>" class="inline" />
		<input type="submit" value="<?php echo ($banned) ? 'Update ban length' : 'Ban' ?>" class="inline" /> <input type="submit" name="banuids" value="Ban & Ban all UIDs" class="inline" />
		<span class="unimportant">(A ban length of "indefinite" will never expire.)</span>
	</div>
</form>
<?php
} ?>
<ul class="menu">
	<?php if ($banned && allowed('ban_ip')) {
    echo '<li><a href="'.DOMAIN.'unban_IP/'.$ip_address.'">Unban IP</a></li>';
} ?>
	<?php if ($banned && allowed('ban_ip')) {
    echo '<li><a href="'.DOMAIN.'unban_IP_UIDS/'.$ip_address.'">Unban IP & UIDs</a></li>';
} ?>
	<?php if (allowed('nuke_uids')) {
    ?><li><a href="<?php echo DOMAIN;
    ?>delete_IP_IDs/<?php echo $ip_address ?>">Delete all IDs</a></li><?php
} ?>
	<?php if (allowed('nuke_posts')) {
    ?><li><a href="<?php echo DOMAIN;
    ?>nuke_IP/<?php echo $ip_address ?>">Delete all posts</a></li><?php
} ?>
	<li><a target="_blank" href="https://whois.domaintools.com/<?php echo $ip_address ?>">Whois</a></li>
	<li><a target="_blank" href="https://db-ip.com/<?php echo $ip_address ?>">Geofag (1)</a></li>
	<li><a target="_blank" href="https://www.geoiptool.com/en/?ip=<?php echo $ip_address ?>">Geofag (2)</a></li>
	<li><a href="<?php echo DOMAIN; ?>stalk/ip/<?php echo $ip_address ?>">Stalk</a></li>
</ul>
<?php
if ($ip_num_ids > 0) {
    echo '<h4 class="section">IDs</h4>';

    $stmt = $link->db_exec('SELECT uid, first_seen FROM users WHERE ip_address = %1 ORDER BY first_seen DESC LIMIT 5000', $ip_address);

    $id_table = new table();
    $columns = array(
        'ID',
        'First seen ▼',
    );
    $id_table->define_columns($columns, 'ID');

    while (list($id, $id_first_seen) = $link->fetch_row($stmt)) {
        $values = array(
            '<a href="'.DOMAIN.'profile/'.$id.'">'.$id.'</a>',
            '<span class="help" title="'.format_date($id_first_seen).'">'.calculate_age($id_first_seen).'</span>',
        );

        $id_table->row($values);
    }
    echo $id_table->output();
}

if ($ip_num_topics > 0) {
    echo '<h4 class="section">Topics</h4>';

    $stmt = $link->db_exec('SELECT id, time, replies, visits, headline, author_ip, namefag, tripfag, author FROM topics WHERE deleted = 0 AND author_ip = %1 ORDER BY id DESC', $ip_address);

    $topics = new table();
    $columns = array(
        'Headline',
        'Name',
        'IP address',
        'Replies',
        'Visits',
        'Age ▼',
    );
    $topics->define_columns($columns, 'Headline');
    $topics->add_td_class('Headline', 'topic_headline');

    while (list($topic_id, $topic_time, $topic_replies, $topic_visits, $topic_headline, $topic_ip_address, $namefag, $tripfag, $author) = $link->fetch_row($stmt)) {
        $name = 'Anonymous';
        if ($namefag) {
            $name = "<b>$namefag</b>";
            if ($tripfag) {
                $name .= ' ';
            }
        }
        if ($tripfag) {
            $name .= $tripfag;
        }

        $values = array(
            '<a href="'.DOMAIN.'topic/'.$topic_id.'">'.htmlspecialchars($topic_headline).'</a>',
            '<a href="'.DOMAIN.'profile/'.$author.'">'.$name.'</a>',
            '<a href="'.DOMAIN.'IP_address/'.$topic_ip_address.'">'.$topic_ip_address.'</a>',
            replies($topic_id, $topic_replies),
            format_number($topic_visits),
            '<span class="help" title="'.format_date($topic_time).'">'.calculate_age($topic_time).'</span>',
        );

        $topics->row($values);
    }
    echo $topics->output();
}

if ($ip_num_replies > 0) {
    echo '<h4 class="section">Replies</h4>';

    $stmt = $link->db_exec('SELECT replies.id, replies.parent_id, replies.time, replies.body, replies.author_ip, topics.headline, topics.time, replies.namefag, replies.tripfag, replies.author FROM replies INNER JOIN topics ON replies.parent_id = topics.id WHERE replies.author_ip = %1 AND replies.deleted = 0 AND topics.deleted = 0 ORDER BY id DESC', $ip_address);

    $replies = new table();
    $columns = array(
        'Reply snippet',
        'Topic',
        'Name',
        'IP address',
        'Age ▼',
    );
    $replies->define_columns($columns, 'Topic');
    $replies->add_td_class('Topic', 'topic_headline');
    $replies->add_td_class('Reply snippet', 'reply_body_snippet');

    while (list($reply_id, $parent_id, $reply_time, $reply_body, $reply_ip_address, $topic_headline, $topic_time, $namefag, $tripfag, $author) = $link->fetch_row($stmt)) {
        $name = 'Anonymous';
        if ($namefag) {
            $name = "<b>$namefag</b>";
            if ($tripfag) {
                $name .= ' ';
            }
        }
        if ($tripfag) {
            $name .= $tripfag;
        }
        $values = array(
//			'<div class="profile_fix"><a href="'.DOMAIN.'topic/' . $parent_id . '#reply_' . $reply_id . '">' . snippet($reply_body) . '</a></div>',
            '<a href="'.DOMAIN.'topic/'.$parent_id.'#reply_'.$reply_id.'">'.snippet($reply_body).'</a>',
            '<a href="'.DOMAIN.'topic/'.$parent_id.'">'.htmlspecialchars($topic_headline).'</a> <span class="help unimportant" title="'.format_date($topic_time).'">('.calculate_age($topic_time).' old)</span>',
            '<a href="'.DOMAIN.'profile/'.$author.'">'.$name.'</a>',
            '<a href="'.DOMAIN.'IP_address/'.$reply_ip_address.'">'.$reply_ip_address.'</a>',
            '<span class="help" title="'.format_date($reply_time).'">'.calculate_age($reply_time).'</span>',
        );
        $replies->row($values);
    }
    echo $replies->output();
}

require 'includes/footer.php';
?>
