<?php

/**
 * Replace the Gravatar by QQ avatar for those who do not have a Gravatar, 
 * and cache all the avatars. 
 * Location of cache: ABSPATH . 'avatar/' .
 * Please make sure that exec() is enabled.
 */
function my_get_avatar($avatar, $id_or_email, $size) {
	
	// Get the author's email.
	$email = '';
	if ( is_numeric($id_or_email) ) {
		$id = (int) $id_or_email;
		$user = get_userdata($id);
		if ( $user )
			$email = $user->user_email;
	} elseif ( is_object($id_or_email) ) {
		if ( !empty($id_or_email->user_id) ) {
			$id = (int) $id_or_email->user_id;
			$user = get_userdata($id);
			if ( $user)
				$email = $user->user_email;
		} elseif ( !empty($id_or_email->comment_author_email) ) {
			$email = $id_or_email->comment_author_email;
		}
	} else {
		$email = $id_or_email;
	}
	
	// Get the url of avatar
	$pattern = '/(?=http)[-\w:\/\.?&#;=]+/';
	$url_count = preg_match_all($pattern, $avatar, $original_avatar_url);
	$avatar_size = array();
	$avatar_url = array();
	$pattern = '/(?<=s=)\d+/';
	for ( $i = 0; $i < $url_count; ++$i) {		
		preg_match($pattern, $original_avatar_url[0][$i], $size_array);	
		$avatar_url[$i] = $original_avatar_url[0][$i];
		$avatar_size[$i] = $size_array[0];
	}
	
	// Check if the author has a gravatar.
	$hashkey = md5(strtolower(trim($email)));
	$test_url = 'http://www.gravatar.com/avatar/' . $hashkey . '?d=404';
	$data = wp_cache_get($hashkey);
	if ( false === $data ) {
		$response = wp_remote_head($test_url);
		if( is_wp_error($response) ) {
			$data = 'not200';
		} else {
			$data = $response['response']['code'];
		}
	    wp_cache_set($hashkey, $data, $group = '', $expire = 60*5);
	}
	
	if ( $data != '200' ) {		
		// The author doesn't have a gravatar.
		if ( stripos($email,"@qq.com") ) {
			// If he uses QQ mail, let WordPress use his QQ avatar instead.
			// Set the size of QQ avatar.
			for ( $i = 0; $i < $url_count; ++$i) {					
				if ( $avatar_size[$i] <= 100 ) $qq_avatar_size = 100;
				elseif ( $size <= 140 ) $qq_avatar_size = 140;
				elseif ( $size <= 240 ) $qq_avatar_size = 240;
				else $qq_avatar_size = 640;
				// q1.qlogo.cn, q3.qlogo.cn, q4.qlogo.cn also work.
				$avatar_url[$i] = 'http://q2.qlogo.cn/g?b=qq&nk=' . $email . '&s=' . $qq_avatar_size;
			}
		}
	}

	// Unfortunately I don't know what encrypt method the QQ avatar interface accepts.
	// So to protect the author's privacy, I have to cache the avatars.
	$wp_url = get_bloginfo('wpurl');
	// Caching by creating and executing another php file to avert waiting.
	// Use random filename to avoid interference.
	$sh_filename = 'avatar_downloader-' . rand() . '.php';
	$sh_file = fopen(ABSPATH . 'avatar/' . $sh_filename, "w");
	fwrite($sh_file, "<?php\n");
	for ( $i = 0; $i < $url_count; ++$i) {
		$file_path = ABSPATH . 'avatar/' . $hashkey . '-' . $avatar_size[$i] . '.jpg';
		// 1209600s = 14d, the avatars will be cached for 2 weeks. You can change the period.
		$lifetime = 1209600; 
		if ( !is_file($file_path) || (time() - filemtime($file_path) ) > $lifetime) { 
			// If the file doesn't exist or it has been out of date, then update it.
			// It's necessary to use "-N --no-use-server-timestamps".
			$txt = "exec(\"wget -N --no-use-server-timestamps -O '" . $file_path . "' '" . $avatar_url[$i] . "'\");\n";			
			fwrite($sh_file, $txt); 
		}
		else $avatar = str_replace($original_avatar_url[0][$i], $wp_url . '/avatar/' . $hashkey . '-' . $avatar_size[$i] . '.jpg', $avatar);
		if ( filesize($file_path) < 500 ) copy($wp_url . '/avatar/default.jpg', $file_path);
	}
	// Make the temporary php file delete itself after being executed.
	fwrite($sh_file, "\$file = '" . ABSPATH . 'avatar/' . $sh_filename . "';\n");
	fwrite($sh_file, "if ( file_exists(\$file) ) @unlink(\$file);\n");
	fwrite($sh_file, "?>\n");
	fclose($sh_file);
	exec("php '" . ABSPATH . "avatar/" . $sh_filename . "' > /dev/null &");
	return $avatar;	
}
add_filter( 'get_avatar', 'my_get_avatar', 10, 3);

?>
