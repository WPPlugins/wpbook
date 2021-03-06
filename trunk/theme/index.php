<?php
/* first include just sets up WP settings */
include_once(WP_PLUGIN_DIR . '/wpbook/theme/config_wp_settings.php');

if(isset($_REQUEST['app_tab'])) { // this is an app tab
  // output tab in iFrame mode
  include_once(WP_PLUGIN_DIR . '/wpbook/theme/tab.php');
  die(); // nothing more to do once this has occured
}

/* this include sets up the FB client, needed for the other parts but not the tab */
include_once(WP_PLUGIN_DIR . '/wpbook/theme/config.php');

// Check for permissions
if(isset($_REQUEST['is_permissions'])) { // we're looking for extended permissions
	$receiver_url = WP_PLUGIN_URL . '/wpbook/theme/default/xd_receiver.html';
	?>
	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
        "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
	<html xmlns="http://www.w3.org/1999/xhtml"
        xmlns:fb="http://www.facebook.com/2008/fbml">
	<head>
	<title><?php bloginfo('name'); ?> :: Facebook Blog Application</title>
	<?php echo '<!-- this is called via plugins_url -->'; ?>
	<link rel="stylesheet" href="<?php echo plugins_url( 'default/style.css', __FILE__ ); ?>"
      type="text/css" media="screen" />
	<BASE TARGET="_top">
	</head>
	<body>
	<p>This page is where you can check and grant extended permissions, which enable WPBook to publish to your personal wall and/or to the walls of fan pages.</p>
	<p>To correct any of these (or if you see no further info below), <a href="
	<?php
	$my_permissions_url = 'https://www.facebook.com/dialog/oauth?client_id=' . $api_key
	. '&redirect_uri='. $proto .'://apps.facebook.com/' . $app_url .'/&scope=read_stream,publish_actions,manage_pages,user_groups';
	echo $my_permissions_url;
	?>" target="_top">Grant or re-grant permissions for your userid.</a> (This is required if you intend to publish to your personal wall OR any fan pages.)</p>
	<?php  // try catch wrapped call to FB API /me to get logged in users ID
	$user = $facebook->getUser();
	if ($user) {
    	try {
		$user_profile = $facebook->api('/'.$user);
		} catch (FacebookApiException $e) {
		 echo '<pre>'.htmlspecialchars(print_r($e, true)).'</pre>';
		 $user = null;
		}
	}
	if($user_profile) {
		?>
		<p>The Facebook profile ID you are currently logged in to Facebook as is <?php echo $user_profile['id']; ?>. You have defined 			<?php echo $target_admin; ?> as your Facebook user id in WPBook Settings.</p>
		<?php
  		if($user_profile['id'] != $target_admin) {
    		echo '<p><strong>Your Facebook Profile ID must match the ID with which you are logged in to Facebook, or else an access token will
    not be stored and publishing to Facebook will fail. Please update the "YOUR Facebook Profile ID" setting in WPBook settings to match
    the userid shown above.</p>';
		}
		?>
		<p>FB profile <?php echo $user_profile['id']; ?> has granted these permissions:
  		<?php 
  	  	// v2.1 of the API doesn't allow for FQL. Will need to convert to graph calls. 
  	  	try {
  	  	  $my_permissions = $facebook->api('/'.$user_profile['id'] .'/permissions','GET');
  	  	} catch (FacebookApiException $e) {
  	  	  if(WPBOOKDEBUG) {
			  $wpbook_message = 'Caught exception checking permissions: ' .  $e->getMessage() .'Error code: '. $e->getCode();  
			  $fp = @fopen($debug_file, 'a');
			  $debug_string=date("Y-m-d H:i:s",time())." :". $wpbook_message  ."\n";
			  fwrite($fp, $debug_string);
		  } // end if debug
  	  	}
  	  	echo '<ul>'; 
  	  	foreach($my_permissions['data'] as $wpbook_permission) {
  	  		echo '<li>' . $wpbook_permission['permission'] .' - '. $wpbook_permission['status'] .' </li>';	  	
  	  	}
  		echo '</ul></p>';  
  	  	$access_token = get_option('wpbook_user_access_token','');
		if($access_token != '') {
			echo '<p>An access token for this user has been stored.</p>';
			echo '<p>Stored access token is '. $access_token . '</p>';
			echo '<p>Active access token is '. $facebook->getAccessToken() . '</p>';
		} else {
    		echo '<p><strong>Error: No access token has been stored for this user -';
    		echo 'probably due to a misconfiguration. Check to see that the ProfileID ';
    		echo 'with which you are logged in to Facebook matches the one set in ';
    		echo 'WPBook settings, as listed above</strong></p>';
  	  	}

		if((!empty($wpbookAdminOptions['fb_page_target'])) && ($wpbookAdminOptions['stream_publish_pages'] == "true")) {
			echo "<p>You've indicated you wish to publish to this page: ". $wpbookAdminOptions['fb_page_target'] ."</p>";
			$fb_response = $facebook->api('/me/accounts/');
			foreach($fb_response['data'] as $page) {
				if ($page['id'] == $wpbookAdminOptions['fb_page_target']) {
					$my_wp_page_name = $page['name'];
					if($page['access_token']) {
						update_option('wpbook_page_access_token',$page['access_token']);
						echo '<p>An access token corresponding to this page has been stored.</p>';
		  			  	echo '<p>' . $page['access_token'] . '</p>';
        			} else {
          			  	echo '<p><strong>ERROR: No access token corresponding to this page was ';
          				echo 'found or stored.</strong> This likely means that either: ';
          			  	echo '<ul><li>The PageID is entered incorrectly in WPBook settings, or</li>';
          			  	echo '<li>The Facebook profile currently logged in has not granted the ';
          			  	echo '"manage_pages" permission appropriately, or</li>';
          			  	echo '<li>The Facebook profile logged in (and for which an access token ';
          			  	echo 'has been stored) is not eligible to grant manage_pages for the page ';
          			  	echo 'in question (not an admin).</li></ul></p>';
        			}
      		  	}
    		}
		} // end if fb_page_target is set

		if((!empty($wpbookAdminOptions['wpbook_target_group'])) && ($wpbookAdminOptions['stream_publish_pages'] == "true")) {
			echo "<p>You've indicated you wish to publish to this group: ". $wpbookAdminOptions['wpbook_target_group'] ."</p>";
			if(($my_permissions[0][publish_stream] == 1) && ($my_permissions[0][offline_access] == 1)) {
				echo '<p>The user access token includes publish stream and offline access permissions.</p>';
			} else {
				echo '<p><strong>ERROR: The user access token does not include publish stream ';
				echo 'and offline access permissions.</strong></p>';
			}
		} // end if wp_target_group is set
	} // end of if $user_profile
?>
</blockquote></p>
<div id="fb-root"></div>
  <script>
    window.fbAsyncInit = function() {
      FB.init({appId: <?php echo $api_key; ?>, status: true, cookie: true, xfbml: true});
      FB.Canvas.setAutoGrow();
    };
  (function() {
   var e = document.createElement('script'); e.async = true;
   e.src = document.location.protocol + '//connect.facebook.net/en_US/all.js';
   document.getElementById('fb-root').appendChild(e);
   }());
  </script>

  </body>
  </html>
  <?php
} // end of the permissions page, now regular themed page

if((!isset($_REQUEST['app_tab'])) && (!isset($_REQUEST['is_permissions']))) {  // this is the regular blog page
  $receiver_url = WP_PLUGIN_URL . '/wpbook/theme/default/xd_receiver.html';
  ?>
  <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"
      "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
  <html xmlns="http://www.w3.org/1999/xhtml"
      xmlns:fb="http://www.facebook.com/2008/fbml">
  <head>
  <title><?php bloginfo('name'); ?> :: Facebook Blog Application</title>
  <?php if ( is_singular() ) wp_enqueue_script( 'comment-reply' ); ?>
  <?php wp_head(); ?>
  <?php echo '<!-- this is called via plugins_url -->'; ?>

  <link rel="stylesheet" href="<?php echo plugins_url( 'default/style.css', __FILE__ ); ?>"
      type="text/css" media="screen" />
  <BASE TARGET="_top">
  </head>
  <body>
  <?php
  if(isset($_REQUEST['debug'])) {
	  $my_now = time();
	  echo "<div><h3>debug</h3>";
	  echo "<p>Access token is ". $access_token ."</p>";
	  echo "<p>Time currently is  " . $my_now ."</p>";
	  echo "<p>Expiration time is ". $token_debug['data']['expires_at'] ."</p>";
	  $my_difference = $token_debug['data']['expires_at'] - $my_now;
	  echo "<p>Difference is ". $my_difference ."</p>" ;
  }
  if(isset($_REQUEST['fb_page_id'])) {
    echo " <div><h3>Thank You!</h3> <p>This application has been added to your page's profile.</p>";
    echo "<p>You can return to your page to see the updated information.</p>";
    echo "<p>Thanks!</p></div>";
    echo "</body></html>";
  }
  ?>
  <div class="wpbook_header">
  <?php
  echo '<h3><a href="'. $proto .'://apps.facebook.com/'. $app_url .'/" target="_top">'. get_bloginfo('name') .'</a></h3>';
  if(($show_pages == "true") && ($show_pages_menu == "true")){
    echo '<div id="underlinemenu" class="clearfix"><ul><li>Pages:</li>';
    if ($exclude_pages_true == "true"){
      wp_list_pages("sort_column=menu_order&depth=1&title_li=&exclude=$exclude_pages_list");
    } else {
      wp_list_pages("sort_column=menu_order&depth=1&title_li=");
    }
    echo '</ul></div>';
  } //end show pages menu
  echo '</div>';
  if(is_page()){ // is a page
    echo '<div id="content"><div class="box_head clearfix">';
    echo '<h3 class="wpbook_box_header">'. the_title() .'</a></h3>';
    if (have_posts()) : while (have_posts()) : the_post();
      the_content();
      endwhile;
    else:
      echo '<p>';
      _e('Sorry, page does not exist.');
      echo '</p>';
    endif;
    echo '</div>';
  } // end if is_page()
  else {
    if(is_archive()){
      echo '<div class="archive">';
      if (is_category()) {
        echo '<p><b>';
        printf( __('You are currently browsing the %1$s archives for the \'%2$s\' category.'), $app_name, single_cat_title('', false) );
        echo '</b></p>';
      } elseif (is_day()) {
        echo '<p><b>You are currently browsing the '. $app_name .' archives for the day '. the_time('l, F jS, Y') .'.</b></p>';
      } elseif (is_month()) {
        echo '<p><b>You are currently browsing the '. $app_name .' archives for '. the_time('F, Y') .'.</b></p>';
      } elseif (is_year()) {
        echo '<p><b>You are currently browsing the '. $app_name .' archives  for the year '. the_time('Y') .'.</b></p>';
      } elseif (is_search()) {
        echo '<p><b>You have searched the '. $app_name .' archives for <strong>"'. wp_specialchars($s) .'"</strong>. </b></p>';
      } elseif (isset($_REQUEST['paged']) && !empty($_REQUEST['paged'])) {
        echo '<p><b>You are currently browsing the '. $app_name.' archives.</b></p>';
      }	elseif(is_tag()){
        echo '<p><b>';
        printf( __('You are currently browsing the %1$s archives for the \'%2$s\' tag.'), $app_name, single_tag_title('', false) );
        echo '</b></p>';
      }
    } // end of if is_archive()
    ?>
    </div>
    <div id="content">
    <?php
    if (have_posts()) :
      while (have_posts()) :
        the_post();
        if (is_single() || $wp_query->is_single || $wp_query->is_singular) {
          previous_post_link('&laquo; Previous Post: %link <br />',
                             '%title',FALSE,'');
          next_post_link('Next Post: %link &raquo;<br />',
                         '%title',FALSE,'');
        } //end if single
        ?>
        <div class="box_head clearfix" id="post-<?php the_ID(); ?>">
        <h3 class="wpbook_box_header">
        <?php
        if($show_date_title == "true"){
          the_time($timestamp_date_format);
          echo(" - ");
        }
        ?><a href="<?php the_permalink(); ?>" target="_top">
        <?php
          the_title();
        ?></a></h3><?php
        if(($show_custom_header_footer == "header") || ($show_custom_header_footer == "both")){
          echo( '<div id="custom_header">'.custom_header_footer($custom_header,$timestamp_date_format,$timestamp_time_format) .'</div>');
        } // end if for showing customer header

        if(($enable_share == "true" || $enable_external_link == "true") && ($links_position == "top")) {
          echo '<p>';
          if($enable_share == "true"){
          ?><span class="wpbook_share_button"><?php
            echo '<a onclick="window.open(\'http://www.facebook.com/sharer.php?s=100&amp;p[title]=';
            echo urlencode(get_the_title());
            echo '&amp;p[summary]=';
            echo urlencode((wp_filter_nohtml_kses(apply_filters('the_content',get_the_excerpt()))));
            if((function_exists('has_post_thumbnail')) && (has_post_thumbnail())) {
              $my_thumb_id = get_post_thumbnail_id();
              $my_thumb_array = wp_get_attachment_image_src($my_thumb_id);
              $my_image = $my_thumb_array[0]; // this should be the url
              echo '&amp;p[images][0]=';
              echo urlencode($my_image);
            }
            echo '&amp;p[url]=';
            echo urlencode(get_permalink());
            echo "','sharer','toolbar=0,status=0,width=626,height=436'); return false;\"";
            echo ' class="share" title="Send this to friends or post it on your profile.">Share This Post</a>';
            echo '</span>';
          } // end if for enable_share
          if($enable_external_link == "true"){
            ?><span class="wpbook_external_post"><a href="<?php echo get_external_post_url(get_permalink()); ?>" title="View this post outside Facebook at <?php bloginfo('name'); ?>">View post on <?php bloginfo('name'); ?></a></span><?php
          } // end if for enable external_link
          echo '</p>';
        } // end links_position _top

        the_content();

        // echo custom footer
        if(($show_custom_header_footer == "footer") || ($show_custom_header_footer == "both")){
          echo('<div id="custom_footer">'.custom_header_footer($custom_footer,$timestamp_date_format,$timestamp_time_format) .'</div>');
        } // endif for footer

        // get share link
        if(($enable_share == "true" || $enable_external_link == "true") && ($links_position == "bottom")) {
          echo '<p>';
          if($enable_share == "true"){
            ?><span class="wpbook_share_button"><?php
            echo '<a onclick="window.open(\'http://www.facebook.com/sharer.php?s=100&amp;p[title]=';
            echo urlencode(get_the_title());
            echo '&amp;p[summary]=';
            echo urlencode((wp_filter_nohtml_kses(apply_filters('the_content',get_the_excerpt()))));
              if((function_exists('has_post_thumbnail')) && (has_post_thumbnail())) {
                $my_thumb_id = get_post_thumbnail_id();
                $my_thumb_array = wp_get_attachment_image_src($my_thumb_id);
                $my_image = $my_thumb_array[0]; // this should be the url
                echo '&amp;p[images][0]=';
                echo urlencode($my_image);
              }
              echo '&amp;p[url]=';
            echo urlencode(get_permalink());
            echo "','sharer','toolbar=0,status=0,width=626,height=436'); return false;\"";
            echo ' class="share" title="Send this to friends or post it on your profile.">Share This Post</a>';
            echo '</span>';
          } // end if for enable_share
          if($enable_external_link == "true"){
            ?><span class="wpbook_external_post"><a href="<?php echo get_external_post_url(get_permalink()); ?>" title="View this post outside Facebook at <?php bloginfo('name'); ?>">View post on <?php bloginfo('name'); ?></a></span><?php
          }
          echo '</p>';
        } // end if for enable share, external, bottom
        echo '</div>';

        comments_template();

      endwhile; // while have posts

      $wpbook_next_page = get_next_posts_link();
      $wpbook_prev_page = get_previous_posts_link();

      if($wpbook_prev_page || $wpbook_next_page) {
        echo '<h3 class="wpbook_box_header">More Posts</h3>';
        echo '<p>';
        if ($wpbook_prev_page)
          echo $wpbook_prev_page;
        if ($wpbook_prev_page && $wpbook_next_page)
          echo  ' | ';
        if ($wpbook_next_page)
          echo $wpbook_next_page;
        echo '</p>';
      }

    endif; // if have posts
    echo '</div>';
  } //end if else for if_page() - blog or archive

  if($show_pages == "true" && $show_page_list=="true"){
    ?><div class="box_head clearfix">
    <h3 class="wpbook_box_header">
    <?php _e('Pages'); ?></h3>
    <ul>
    <?php
    if ($exclude_pages_true == "true"){
      wp_list_pages("sort_column=menu_order&title_li=&exclude=$exclude_pages_list");
    } else {
      wp_list_pages("sort_column=menu_order&title_li=");
    } ?>
    </ul>
    </div>
    <?php
  } // end if for show pages, show list

  if($show_post_list == "true"){
    ?><div class="box_head clearfix">
    <h3 class="wpbook_box_header">
    <?php _e('Recent Posts'); ?></h3>
    <ul><?php wp_recent_posts($recent_post_list_amount); ?></ul>
    </div>
    <?php
  }

  if ($give_credit == "true"){
    ?><div class="box_head clearfix" style="padding: 5px 0 0 0;">
    <p><small>This Facebook Application powered by <a href="http://www.wordpress.org/extend/plugins/wpbook/">the WPBook plugin</a>
      for <a href="http://www.wordpress.org/">WordPress</a>.</small></p>
    </div><?php
  }
  ?>
  <div id="fb-root"></div>
  <script>
    window.fbAsyncInit = function() {
      FB.init({appId: <?php echo $api_key; ?>, status: true, cookie: true, xfbml: true});
      FB.Canvas.setAutoGrow();
    };
    (function() {
      var e = document.createElement('script'); e.async = true;
      e.src = document.location.protocol + '//connect.facebook.net/en_US/all.js';
      document.getElementById('fb-root').appendChild(e);
    }());
  </script>
  </body>
  <?php
  } // end else for if (fb_page_id)
?>
</html>
