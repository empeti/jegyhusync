<?php
add_action('upgrader_post_install','magyar_dashboard');
add_action('wp_dashboard_setup','magyar_dashboard');

function magyar_dashboard()
{  
  $widget_options = get_option('dashboard_widget_options');
  
  //Ha az alap fejlesztői blog van, akkor átírjuk
  if (empty($widget_options['dashboard_primary']) || $widget_options['dashboard_primary']['link'] == 'http://wordpress.org/development/')
  {
    $widget_options['dashboard_primary'] = array(
    	'link' => 'http://napsugar.net/',
    	'url' => 'http://feeds.feedburner.com/idezetek/',
    	'title' => 'A napi lélekmelegítőd',
    	'items' => 1,
    	'show_summary' => 1,
    	'show_author' => 1,
    	'show_date' => 1
    );
  }
  
  //Ha az alap wordpress planet van, akkor is átírjuk
  if (empty($widget_options['dashboard_secondary']) || $widget_options['dashboard_secondary']['link'] == 'http://planet.wordpress.org/')
  {
    $widget_options['dashboard_secondary'] = array(
    	'link' => 'http://word-press.hu/',
    	'url' => 'http://word-press.hu/feed/',
    	'title' => 'Magyar WordPress Hírek',
    	'items' => 4,
    	'show_summary' => 1,
    	'show_author' => 1,
    	'show_date' => 1
    );
  }
  
  update_option('dashboard_widget_options', $widget_options);
}

//$wp_default_secret_key = 'írj ide valami nagyon bonyolultat';

/**
 * mno.hu wp_embed handler
 *
 * Utoljára frissítve: 2014-12-19 02:40:00 +0100
 *
 * @author DjZoNe
 * @since 4.1
 */
function wp_embed_handler_mnohu( $matches, $attr, $url, $rawattr )
{
    global $content_width;
    $matches['video_id'] = $matches['2'];
    
    $ratio = 0.625;
    $width = floor($content_width);
    $height = floor($content_width * $ratio);
    
    $embed = sprintf("<iframe style='width:%dpx; height:%dpx' src='http://video.mno.hu/embed/%s' id='videoplayer_%s'></iframe>",$width,$height,$matches['video_id'],$matches['video_id']);

    return apply_filters( 'embed_mnohu', $embed, $matches, $attr, $url, $rawattr );
}

/**
 * tv2.hu wp_embed handler
 *
 * Utoljára frissítve: 2014-12-19 02:40:00 +0100
 *
 * @author DjZoNe
 * @since 4.1
 */
function wp_embed_handler_tv2hu( $matches, $attr, $url, $rawattr )
{
    global $content_width;
    $video_id = $matches['video_id'] = $matches['4'];
    $slug = $matches['5'];
    
    $ratio = 0.5625;
    $width = floor($content_width);
    $height = floor($content_width * $ratio);
    
    $embed = sprintf('<iframe width="%d" height="%d" src="http://tv2.hu/neoplayer/tv2/embed/%d_%s.html/%d" frameborder="0" scrolling="no"></iframe>',$width,$height,$video_id,$slug,$width);

    return apply_filters( 'embed_tv2hu', $embed, $matches, $attr, $url, $rawattr );
}

/**
 * vivatv.hu wp_embed handler
 *
 * Utoljára frissítve: 2014-12-19 02:40:00 +0100
 *
 * @author DjZoNe
 * @since 4.1
 */
function wp_embed_handler_vivatvhu( $matches, $attr, $url, $rawattr )
{
    global $content_width;
    $video_id = $matches['video_id'] = $matches['2'];
    $slug = $matches['3'];
    
    $ratio = 0.625;
    $width = floor($content_width);
    $height = floor($content_width * $ratio);
    
    /***
     * Minden kész már csak az embed video ID kell...
     */

    $video_hash = _get_mtvi_mgid($url);

    if(!empty($video_hash))
    {
	$embed = sprintf('<iframe width="%d" height="%d" src="http://media.mtvnservices.com/embed/%s" frameborder="0"></iframe>',$width,$height,$video_hash);
    }
    
    /***
     * Ha valamiért mégsincs az eredeti URL-t adjuk vissza
     */
    if(empty($embed)) $embed = $url;

    return apply_filters( 'embed_vivatvhu', $embed, $matches, $attr, $url, $rawattr );
}

/**
 * mtv.co.hu wp_embed handler
 *
 * Utoljára frissítve: 2014-12-19 02:40:00 +0100
 *
 * @author DjZoNe
 * @since 4.1
 */
function wp_embed_handler_mtvcohu( $matches, $attr, $url, $rawattr )
{
    global $content_width;
    $video_id = $matches['video_id'] = $matches['2'];
    $slug = $matches['3'];
    
    $ratio = 0.625;
    $width = floor($content_width);
    $height = floor($content_width * $ratio);
    
    /***
     * Minden kész már csak az embed video ID kell...
     */

    $video_hash = _get_mtvi_mgid($url);

    if(!empty($video_hash))
    {
	$embed = sprintf('<iframe width="%d" height="%d" src="http://media.mtvnservices.com/embed/%s" frameborder="0"></iframe>',$width,$height,$video_hash);
    }
    
    /***
     * Ha valamiért mégsincs az eredeti URL-t adjuk vissza
     */
    if(empty($embed)) $embed = $url;

    return apply_filters( 'embed_mtvcohu', $embed, $matches, $attr, $url, $rawattr );
}

/***
 * This discover method is borrowed from
 * WordPress 4.1 class-oembed.php discover() function
 */
function _get_mtvi_mgid($url)
{
    $request = wp_safe_remote_get( $url);
    
    if ( $html = wp_remote_retrieve_body( $request ) ) 
    {
	$linktypes = apply_filters( 'opengraph_videotypes', array(
	    'og:video' => 'video',
	) );

	// Strip <body>
	$html = substr( $html, 0, stripos( $html, '</head>' ) );

        // Do a quick check
        $tagfound = false;
        $opengraph_video = false;
        $video_hash = false;
        foreach ( $linktypes as $linktype => $format ) {
	    if ( stripos($html, $linktype) ) {
    	        $tagfound = true;
        	break;
    	    }
	}

	if ( $tagfound && preg_match_all( '/<meta([^<>]+)>/i', $html, $links ) ) {
    	    foreach ( $links[1] as $link ) {
        	$atts = shortcode_parse_atts( $link );
        	if ( !empty($atts['property']) && !empty($linktypes[$atts['property']]) && !empty($atts['content']) ) {
            	    $opengraph_video = $atts['content'];
        	    // Stop here if it's og:video (that's all we need)
        	    if ( 'og:video' == $linktypes[$atts['type']] ) break;
    		}
    	    }
	}

	if($opengraph_video) 
	{
	    if(preg_match('#http://media\.mtvnservices\.com/fb/([0-9a-zA-Z\:\-\_\/\.]+)\.swf#i',$opengraph_video,$matches))
	    {
		$video_hash = $matches['1'];
	    }
	}
    }
    
    return $video_hash;
}

/***
 * Provider URL felülírás, mert indavideonál máshogy nem megy
 *
 * $provider = apply_filters( 'oembed_fetch_url', $provider, $url, $args );
 */
function magyar_oembed_fetch_url($provider,$url,$args)
{
    if(strstr($url,'indavideo.hu'))
    {
	$provider = str_replace('/video/','/oembed/',$url).'&amp;format=json';
    }
    
    return $provider;
}
add_filter('oembed_fetch_url','magyar_oembed_fetch_url',10,3);

/***
 * Ez a funkció tartalmazza azokat a magyar nyelvű oldalakat, ahonnét magyar nyelvű tartalmakat lehet beágyazni
 *
 * Ezek után pedig jöjjön a szégyenfal, akiknek az oldalain nincs ilyen úri huncutság 2014 végén:
 *
 * - hirado.hu - nincs külső beágyazás, ez már nem fért bele az adónkból, mi?
 * - echotv.hu - nagyon szép ez a HTML5 player, de elférne valami embed kód és oembed támogatás
 * - rtl.hu - nincs, az ő szar Silverlight alapú lejátszójukat nem lehet embedelni
 * - atv.hu - nincs külső beágyazás. Ha Carnationtől olvassa valaki, igazán megcsinálhatnátok ;)
 * - klubradio.hu - nincs külső beágyazás
 * - digisport.hu - nincs külső beágyazás 
 * - musicfm.hu - csak javascriptes "popupban" jelennek meg a zenék, embedálható lenne csak nincs ember aki a linkeket jelenleg használja
 *              mert az oldal ahova a linkek vezetnek csúnyább, mint egy Csivava kifordítva
 * - inforadio.hu - belehetne ágyazni, csak a linkeket a hosszuk miatt senki nem használja,
 * 	          igaz 2009-ben amikor az oldal készült, még nem volt ilyen igény
 * - vivatv.hu, mtv.co.hu - megoldottam, de egy oembeddel elegánsabb lenne srácok
 *
 * Bármilyen észrevételt, panaszt bánatot ide írjatok:
 * http://hu.wordpress.org/contact/
 *
 */
function magyar_video_embeds()
{
    wp_embed_register_handler( 'vivatvhu', '#http://(www\.)?vivatv\.hu/([0-9a-zA-Z\-\_\/]+)/(?:videos)/([0-9a-zA-Z\-\_\/]+)\-([\d]+)#i', 'wp_embed_handler_vivatvhu' );
    wp_embed_register_handler( 'mtvcohu', '#http://(www\.)?mtv\.co\.hu/([0-9a-zA-Z\-\_\/]+)/(?:videos)/([0-9a-zA-Z\-\_\/]+)\-([\d]+)#i', 'wp_embed_handler_mtvcohu' );
    wp_embed_register_handler( 'mnohu', '#http://(www\.)?mno\.hu/(?:videok)/([\d]+)#i', 'wp_embed_handler_mnohu' );
    wp_embed_register_handler( 'tv2hu', '#http://(www\.)?tv2\.hu/(musoraink)/([0-9a-zA-Z\-\_]+)/([\d]+)_([0-9a-zA-Z\-\_]+)\.html#i', 'wp_embed_handler_tv2hu' );
    
    wp_oembed_add_provider( '#http://(www\.)?noltv\.hu/video/([\d]+)\.html#i', 'http://www.noltv.hu/services/oembed', true );
    wp_oembed_add_provider( '#http://(www\.)?indavideo\.hu/video/(.*)#i', 'http://www.indavideo.hu/oembed', true );
    wp_oembed_add_provider( '#http://(www\.)?videa\.hu/videok/(.*)#i', 'http://videa.hu/oembed', true );
    wp_oembed_add_provider( '#http://(www\.)?pixter\.hu/video\?id=(.*)#i', 'http://www.pixter.hu/oembed.{format}', true );
}
add_action( 'init', 'magyar_video_embeds' );