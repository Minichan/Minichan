<?php

/* USER-AGENTS
================================================== */
function check_user_agent($type = null)
{
    $user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
    if ($type == 'bot') {
        // matches popular bots
                if (preg_match("/megaindex|linkanalyze|googlebot|adsbot|yandex|feedly|yahooseeker|yahoobot|msnbot|watchmouse|pingdom\.com|feedfetcher-google|ia_archiver|bingbot|mj12bot|ahrefsbot|semrushbot|spbot|check_http|quassel/", $user_agent)) {
                    return true;
                        // watchmouse|pingdom\.com are "uptime services"
                }
    } elseif ($type == 'browser') {
        // matches core browser types
                if (preg_match("/mozilla\/|opera\//", $user_agent)) {
                    return true;
                }
    } elseif ($type == 'mobile') {
        // matches popular mobile devices that have small screens and/or touch inputs
                // mobile devices have regional trends; some of these will have varying popularity in Europe, Asia, and America
                // detailed demographics are unknown, and South America, the Pacific Islands, and Africa trends might not be represented, here
                if (preg_match("/phone|iphone|itouch|ipod|symbian|android|htc_|htc-|palmos|blackberry|opera mini|iemobile|windows ce|nokia|fennec|hiptop|kindle|mot |mot-|webos\/|blazer\/|samsung|sonyericsson|^sie-|nintendo|playstation/", $user_agent)) {
                    // these are the most common
                        return true;
                } elseif (preg_match('/mobile|pda;|avantgo|eudoraweb|minimo|netfront|brew|teleca|lg;|lge |wap;| wap /', $user_agent)) {
                    // these are less common, and might not be worth checking
                        return true;
                }
    }

    return false;
}
