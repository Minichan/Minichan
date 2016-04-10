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
		<link rel="icon" type="image/gif" href="<?php echo STATIC_DOMAIN; ?>favicon.gif" />
		<link rel="stylesheet" type="text/css" media="screen" href="<?php echo STATIC_DOMAIN.'style/layout.css' ?>?21" />
		<link rel="stylesheet" type="text/css" media="screen" href="<?php echo ($custom_stylesheet) ? htmlspecialchars($custom_stylesheet) : (STATIC_DOMAIN.'style/'.$stylesheet.'.css?3') ?>" />
		<link rel="stylesheet" type="text/css" media="screen" href="<?php echo STATIC_DOMAIN.'javascript/highlight-styles/vs.css' ?>" />
		<?php if (MOBILE_MODE) {
    ?>
			<link rel="stylesheet" type="text/css" media="screen" href="<?php echo STATIC_DOMAIN.'style/mobile.css' ?>" />
			<?php 
} ?>
		<?php /* <link rel="stylesheet" type="text/css" href="<?php echo STATIC_DOMAIN; ?>style/april.css?13"> */ ?>
		<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
		<script type="text/javascript">var IMGUR_CLIENT_ID = "<?php echo IMGUR_CLIENT_ID; ?>";</script>
		<script type="text/javascript" src="<?php echo STATIC_DOMAIN; ?>javascript/main.js?x15"></script>
		<script type="text/javascript" src="<?php echo STATIC_DOMAIN; ?>javascript/extras.js?x9"></script>
		<script type="text/javascript" src="<?php echo STATIC_DOMAIN; ?>javascript/highlight.pack.js"></script>
		<script>hljs.initHighlightingOnLoad();</script>
        <?php if ($administrator && false) {
    ?><script type="text/javascript" src="<?php echo STATIC_DOMAIN;
    ?>javascript/scrollBar.js"></script><?php 
} ?>
        <?php if (MOBILE_MODE && false) {
    ?><script type="text/javascript" src="<?php echo STATIC_DOMAIN;
    ?>javascript/textarea.js"></script><?php 
} ?>

		<?php if (false && $administrator) {
    ?><script type="text/javascript" src="<?php echo STATIC_DOMAIN;
    ?>javascript/inlineEdit.js"></script><?php 
} ?>
		<?php if (false) {
    ?><script type="text/javascript" src="<?php echo STATIC_DOMAIN;
    ?>javascript/snowflake.js?5"></script><?php 
} ?>
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

                            if (false) {
                                ?>
<script type="text/javascript" src="<?php echo DOMAIN;
                                ?>javascript/fool.js"></script>
<script>
$(document).ready(function() {
	$.fool({
		fallingScrollbar: Math.random()>0.9,   //  Want the scrollbar to fall over?
		rick: Math.random()>0.9,               //  The synonymous Rick Astley video, hidden off-screen
		hiddenVideos: Math.random()>0.9,       //  Show some wonderfully annoying videos
		vanishingElements: false,  //  Hide random elements as they interact
		questionTime: Math.random()>0.9,       //  Sing Spongebob with your browser!
		upsideDown: Math.random()>0.9,         //  Flip the page upside down!
		h4xx0r: Math.random()>0.9,             //  Make the page 100% editable
		wonky: Math.random()>0.9,              //  Make the page a little bit crooked
		flash: Math.random()>0.9,              //  Makes the site flash on and off
		crashAndBurn: false,       //  Runs an endless loop. This will kill your browser!
		shutter: false,            //  Forces a shutter on the screen
		unclickable: false,        //  Makes the page unclickable
	});
});
</script>
		<?php

                            }

            echo $additional_head;
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
// DO NOT REMOVE THE FOOTER OR DONATION LINK YOU FUCKING PSYCHO TROLLS.
// The footer starts here.
// $donationlink = '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=7P4QPNBZJ4R7N&lc=GB&item_name=TinyBBS&item_number=TinyBBS%20Donation&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted" alt="Donate" target="_new">donate</a>';
// echo sprintf('<hr style="height:1px;border-width:0;color:gray;background-color:gray" /><span class="unimportant">Powered by <a href="http://tinybbs.org" target="_new">TinyBBS</a> open source software. %s This page took %.5f seconds to be generated.</span>', $donationlink, ($e0+$e1)-($s0+$s1));
if (!MOBILE_MODE) {
    // echo sprintf('<span class="unimportant">All trademarks and copyrights on this site are owned by their respective parties. Images and comments are the responsibility of the posters. %s This page took %.5f seconds to be generated.', $donationlink, ($e0+$e1)-($s0+$s1));

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
    <?php if ($administrator && false) { // http://minichan.org/facebook/spread.php ?>
    <div style='position: absolute; top: 0; right: 0;'>
    <a href='javascript:confirm("Spread the word to your facebook friends? (This will post a link on their walls)")'><img src="//minichan.org/img/spread.png" /></a>
    </div>
    <?php 
} ?>

<!--		
<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" codebase="http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,29,0" width="1" height="1">
  <param name="movie" value="http://breadfish.de/img/breadfish.swf" id="toonURL1"><param name="quality" value="high"><embed src="http://breadfish.de/img/breadfish.swf" quality="high" pluginspage="http://www.macromedia.com/go/getflashplayer" type="application/x-shockwave-flash" width="1" height="1"  id="toonURL2"></embed></object>
-->
<!-- <div style="position: fixed; width:1px; height:1px; left:100px;top:200px;background-color:blue;z-index:1000"></div> -->
<div id="fb-root"></div>
<?php /*
<div style='width:1px;height:1px;background-color:black;position:fixed;left:300px;top:50px;cursor:default'></div>
<script>
$(document).ready(function() {
    var w = $(window).width();
    var h = $(window).height();
    
    function getRandomInt (min, max) {
        return Math.floor(Math.random() * (max - min + 1)) + min;
    }
    
    setInterval(function() {
        var elem = $("<div style='width:1px;height:1px;background-color:black;position:fixed;cursor:default'></div>");
        elem.css('left', getRandomInt(0, w) + 'px');
        elem.css('top', getRandomInt(0, h) + 'px');
        $('body').append(elem);
    }, 5000);
});
</script>
*/ ?>
</body>
</html>
