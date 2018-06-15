<?php
    if (MOD_GZIP) {
        if (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) {
            ob_start('ob_gzhandler');
        } else {
            ob_start();
        }
    }
    global $link;
    if ($link) {
        // Get or set style.
        $stmt = $link->db_exec('SELECT style, custom_style, rounded_corners FROM user_settings WHERE uid = %1', $_SESSION['UID']);
        list($stylesheet, $custom_stylesheet, $rounded_corners) = $link->fetch_row($stmt);
        if ($custom_stylesheet) {
            $custom_stylesheet = preg_replace('%^http://%s', 'https://', $custom_stylesheet);
        }
        if ($stylesheet != 'Custom' || $_GET['nocss'] == 1) {
            unset($custom_stylesheet);
            if (!$_SESSION['ID_activated'] || !$stylesheet || $_GET['nocss'] == 1 || !file_exists(SITE_ROOT.'/style/'.$stylesheet.'.css')) {
                $stylesheet = DEFAULT_STYLESHEET;
            }
        }
        $_SESSION['user_style'] = $stylesheet;
    } else {
        $stylesheet = DEFAULT_STYLESHEET;
    }
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" id="top">
	<head>
		<meta http-equiv="Content-type" content="text/html;charset=UTF-8" />
		<meta name="robots" content="noarchive" />
		<?php if (MOBILE_MODE) {
    ?><meta name="viewport" content="width=device-width; initial-scale=1.0; maximum-scale=1.0; user-scalable=0;" /><?php
} ?>
		<meta name="description" content="Minichan; annoyingly elitist." />
		<meta name="keywords" content="minichan, bbs, board, anonymous, free, debate, discuss, argue, drama, loldrama, youarenowbrowsingmanually" />
		<title><?php echo strip_tags($page_title).' — '.SITE_TITLE ?></title>
		<link rel="icon" type="image/gif" href="<?php echo STATIC_DOMAIN; ?>favicon.gif?<?php echo $assetFiles->hash ?>" />
		<link rel="stylesheet" type="text/css" media="screen" href="<?php echo STATIC_DOMAIN.'bin/main.css?' . $assetFiles->hash ?>" />
    <?php if(MOBILE_MODE) { ?>
    <link rel="stylesheet" type="text/css" media="screen" href="<?php echo STATIC_DOMAIN.'bin/mobile.css?' . $assetFiles->hash ?>" />
    <?php } ?>
		<link rel="stylesheet" type="text/css" media="screen" href="<?php echo ($custom_stylesheet) ? htmlspecialchars($custom_stylesheet) : (STATIC_DOMAIN.'style/'.$stylesheet.'.css?'.$assetFiles->hash) ?>" />
		<link rel="stylesheet" type="text/css" media="screen" href="<?php echo STATIC_DOMAIN.'javascript/highlight-styles/vs.css?'.$assetFiles->hash ?>" />
		<script type="text/javascript">var IMGUR_CLIENT_ID = "<?php echo IMGUR_CLIENT_ID; ?>";</script>
    <script type="text/javascript" src="<?php echo STATIC_DOMAIN; ?>javascript/highlight.pack.js"></script>
		<script type="text/javascript" src="<?php echo STATIC_DOMAIN.'bin/main.js?' . $assetFiles->hash ?>"></script>
    <?php if(MOBILE_MODE) { ?>
    <script type="text/javascript" src="<?php echo STATIC_DOMAIN.'bin/mobile.js?' . $assetFiles->hash ?>"></script>
    <?php } ?>
		<?php
            if (BREAK_OUT_FRAME) {
                echo "\t";
                echo '<script type="text/javascript">';
                echo "\n\t";
                echo <<<EOF
if (top.location != location) {
	top.location.href = document.location.href;
	}
EOF;
                echo "\n\t";
                echo "</script>\n";
            }

            echo $additional_head;
			
			if(defined('GOOGLE_ANALYTICS_ID')) {
        ?>
		<script type="text/javascript">
			var _gaq = _gaq || [];
			_gaq.push(['_setAccount', '<?php echo GOOGLE_ANALYTICS_ID; ?>']);
			_gaq.push(['_setDomainName', '<?php echo GOOGLE_ANALYTICS_DOMAIN; ?>']);
			_gaq.push(['_setCustomVar', 1, 'ID', '<?php echo $_SESSION['UID']; ?>', 2]);
			_gaq.push(['_trackPageview'<?php if ($analytics_track_url) {
    echo ", '".str_replace("'", '"', $analytics_track_url)."'";
}?>]);
			(function() {
				var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
				ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/<?php echo ($administrator && false) ? 'u/ga_debug.js' : 'ga.js'?>';
				var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
			})();
		</script>
</head>
	<?php
			}
        echo '<body';
        if (!empty($onload_javascript)) {
            echo ' onload="'.$onload_javascript.'"';
        }
        echo ' class="';
        if ($rounded_corners) {
            echo 'rounded ';
        }
        echo 'page-'.preg_replace('%^/|\.php$%i', '', $_SERVER['SCRIPT_NAME']);
        echo ' '.(MOBILE_MODE ? 'mobile' : 'desktop').'-mode';
        echo '"';
        echo '>';
        if ($administrator && false) {
            ?>
<script type="text/javascript">
	var toolbarConfig = {title:"Minichan",link:"http://minichan.org/topic/6296",slogan:"Get all the latest drama you've come to love, direct to your browser",hide:"hideMCToolbar_",slide:true};
	document.write("<script type=\"text/javascript\" src=\"/javascript/chrome_toolbar.js?"+Math.random()+"\"></s"+"cript>");
</script>

		<?php

        }
        if (!empty($_SESSION['notice'])) {
            echo '<div id="notice" onclick="this.parentNode.removeChild(this);"><strong>Notice</strong>: '.$_SESSION['notice'].'</div>';
            unset($_SESSION['notice']);
        }
        $site_slogan = array(
        'Implying implications since 2010.',
        'Banned in Iran.',
        'We know sad cunts.',
        'Annoyingly elitist.',
        'Spoilers punishable by death.',
        );
    ?>
	<h1 class="top_text" id="logo">
		<?php
            if ($administrator || allowed('manage_defcon')) {
                if (DEFCON < 5) {
                    $additional = ' - <a href="'.DOMAIN.'defcon" title="Manage defcon status.">DEFCON</a> '.DEFCON;
                } else {
                    $additional = '';
                }
            } else {
                $additional = '';
            }
            echo '<a rel="index" href="'.DOMAIN.'" class="help_cursor" title="'.$site_slogan[rand(0, count($site_slogan) - 1)].'">'.SITE_TITLE.$additional."</a>\n"
         ?>
	</h1>
<div id="main_menu_wrapper">
	<ul id="main_menu" class="menu">
		<?php
            if (MOBILE_MODE) {
                $newTopic = 'New topic';
            } else {
                $newTopic = 'New topic';
            }
            $main_menu = array(
            'Hot' => 'hot_topics',
            'Topics' => 'topics',
            'Bumps' => 'bumps',
            'Replies' => 'replies',
            $newTopic => 'new_topic',
            'History' => 'history',
            'Watchlist' => 'watchlist',
            'Bulletins' => 'bulletins',
            'Events' => 'events',
            'Folks' => 'folks',
            'Search' => 'search',
            'Shuffle' => 'shuffle',
            'Stuff' => 'stuff',
            'Log in' => 'login',
            );

            if (isset($topics_mode)) {
                if ($topics_mode) {
                    unset($main_menu['Topics']);
                } else {
                    unset($main_menu['Bumps']);
                }
            }

            if (!$show_mod_alert) {
                unset($main_menu['Log in']);
            }

            if (MOBILE_MODE) {
                unset($main_menu['Shuffle']);
                unset($main_menu['Folks']);
                unset($main_menu['Hot']);
                unset($main_menu['Watchlist']);
                unset($main_menu['History']);
                unset($main_menu['Search']);
                unset($main_menu['Events']);
                unset($main_menu['Replies']);
                unset($main_menu['Bulletins']);
            }
            // Items in last_action_check need to be checked for updates.
            $last_action_check = array();

            if ($_COOKIE['topics_mode'] == 1) {
                $last_action_check['Topics'] = 'last_topic';
                $last_action_check['Bumps'] = 'last_bump';
                $last_action_check['Bulletins'] = 'last_bulletin';
            } else {
                $last_action_check['Topics'] = 'last_topic';
                $last_action_check['Bumps'] = 'last_bump';
                $last_action_check['Bulletins'] = 'last_bulletin';

                // Remove the "Bumps" link if bumps mode is default. Uncommenting it will break the updating of ! for new topics, bumps and bulletins.
                //	array_splice($main_menu, 2, 2);
            }
            foreach ($main_menu as $linked_text => $path) {
                // Output the link if we're not already on that page.
                if ($path != trim($_SERVER['REQUEST_URI'], '/\\')) {
                    echo indent().'<li class="'.$path.'"><a href="'.DOMAIN.$path.'">'.$linked_text;

                    // If we need to check for new stuff...
                    if (isset($last_action_check[ $linked_text ])) {
                        $last_action_name = $last_action_check[ $linked_text ];
                        // If there's new stuff, print an exclamation mark.
                        if (isset($_COOKIE[$last_action_name]) && $_COOKIE[ $last_action_name ] < $last_actions[ $last_action_name ]) {
                            echo '<i>!</i>';
                        }
                    }
                    echo '</a>';
                    if ($path == 'history' && $new_citations) {
                        echo '<em><a href="'.DOMAIN.'citations" class="help" title="'.$new_citations.' new repl'.($new_citations > 1 ? 'ies' : 'y').' to your replies"><b>!</b></a></em>';
                    } elseif ($path == 'watchlist' && $new_watchlists) {
                        echo '<em><a href="'.DOMAIN.'watchlist" class="help" title="'.$new_watchlists.' new repl'.($new_watchlists > 1 ? 'ies' : 'y').' to watched topics"><b>!</b></a></em>';
                    }

                    echo '</li>';
                }
            }
        ?>
	</ul>
</div>
<div id="body_wrapper">
	<h2 id="body_title">
		<?php
            echo $page_title
        ?>
	</h2>
	<?php
        echo $buffered_content;
        $end = microtime();
        list($s0, $s1) = explode(' ', $_start_time);
        list($e0, $e1) = explode(' ', $end);
    ?></div><div style="display:block;clear: both">

<?php
// echo sprintf('<hr style="height:1px;border-width:0;color:gray;background-color:gray" /><span class="unimportant">Powered by <a href="http://tinybbs.org" target="_new">TinyBBS</a> open source software. %s This page took %.5f seconds to be generated.</span>', $donationlink, ($e0+$e1)-($s0+$s1));
if (!MOBILE_MODE) {
    // Generation time-n-RAM usage.
    if ($administrator) {
        function humanize_bytes($size)
        {
            $unit = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');

            return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2).$unit[$i];
        }
        echo sprintf('<span class="unimportant">This page took %.5f seconds to be generated.', ($e0 + $e1) - ($s0 + $s1));
        echo ' Memory usage: '.humanize_bytes(memory_get_peak_usage()).' / '.ini_get('memory_limit').'.</span>';
    }
// End of footer.
}
?>
		<noscript><br /><span class="unimportant">Note: Your browser's JavaScript is disabled; some site features may not fully function.</span></noscript>
	</div>
</body>
</html>
