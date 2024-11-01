<?php
/*
 * WordPress Custom API Data Hooks
 */
 
if( !defined( 'ABSPATH' ) ) exit;

// 屏蔽不常用 REST
if( wp_miniprogram_option('gutenberg') ) {
	add_filter( 'rest_endpoints', function( $endpoints ) {
		unset( $endpoints['/wp/v2/users'] );
		unset( $endpoints['/wp/v2/users/me'] );
		unset( $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		unset( $endpoints['/wp/v2/posts/(?P<parent>[\d]+)/revisions']);
		unset( $endpoints['/wp/v2/posts/(?P<parent>[\d]+)/revisions/(?P<id>[\d]+)']);
		unset( $endpoints['/wp/v2/posts/(?P<id>[\d]+)/autosaves']);
		unset( $endpoints['/wp/v2/posts/(?P<parent>[\d]+)/autosaves/(?P<id>[\d]+)']);
		unset( $endpoints['/wp/v2/pages/(?P<parent>[\d]+)/revisions']);
		unset( $endpoints['/wp/v2/pages/(?P<parent>[\d]+)/revisions/(?P<id>[\d]+)']);
		unset( $endpoints['/wp/v2/pages/(?P<id>[\d]+)/autosaves']);
		unset( $endpoints['/wp/v2/pages/(?P<parent>[\d]+)/autosaves/(?P<id>[\d]+)']);
		unset( $endpoints['/wp/v2/comments']);
		unset( $endpoints['/wp/v2/comments/(?P<id>[\d]+)']);
		unset( $endpoints['/wp/v2/statuses']);
		unset( $endpoints['/wp/v2/statuses/(?P<status>[\w-]+)']);
		unset( $endpoints['/wp/v2/settings']);
		unset( $endpoints['/wp/v2/themes']);
		return $endpoints;
	});
}

add_action( 'rest_api_init', function () {
	$controller = array();
	$controller[] = new WP_REST_Setting_Router();
	$controller[] = new WP_REST_Posts_Router();
	$controller[] = new WP_REST_Custom_Router();
	$controller[] = new WP_REST_Pages_Router();
	$controller[] = new WP_REST_Comments_Router();
	$controller[] = new WP_REST_Qrcode_Router();
	$controller[] = new WP_REST_Users_Router();
	$controller[] = new WP_REST_Auth_Router();
	$controller[] = new WP_REST_Subscribe_Router();
	$controller[] = new WP_REST_Advert_Router();
	$controller[] = new WP_REST_Menu_Router();
	$controller[] = new WP_REST_Security_Router();
	foreach ( $controller as $control ) {
		$control->register_routes();
	}
} );

add_filter( 'rest_prepare_post', function ( $data, $post, $request ) {
	$_data   = $data->data;
	$post_id = $post->ID;
	if( !isset($_data["week"]) ) {
	    $_data["week"] 	  = get_wp_post_week( $post->post_date );
	}
	if( is_miniprogram() || is_debug() ) {
	    $session 		  = "";
		$user_id 		  = 0;
	    if( ! isset($_data["author"]["id"]) ) {
	        unset($_data['author']);
    	    $_data["author"]["id"] 			= (int)$post->post_author;
    		$_data["author"]["name"] 		= get_the_author_meta('nickname', $post->post_author);
    		$_data["author"]["avatar"]  	= get_avatar_url( $post->post_author );
    		if( get_user_meta($post->post_author, 'avatar', true) ) {
    			$_data["author"]["avatar"]  = get_user_meta($post->post_author, 'avatar', true);
    		}
    		$_data["author"]["description"] = get_the_author_meta('description', $post->post_author);
	    }
	    
	    if( ! isset($_data["user_id"]) ) {
			if( isset($request['access_token']) ) {
				$session 	  = trim($request['access_token']);
				$access_token = base64_decode( $session );
				$users 		  = MP_Auth::login( $access_token );
				if( $users ) {
    				$user_id  = $users->ID;
    			}
			}
    		$_data["user_id"] = $user_id;
	    }
	    if( !isset($_data["meta"]["thumbnail"]) ) {
			$_data["meta"]["thumbnail"] = wanzi_post_thumbnail( $post_id );
		}
		if( !isset($_data["meta"]["views"]) ) {
			$_data["meta"]["views"]     = (int)get_post_meta( $post_id, "views" ,true );
		}
		if( !isset($_data["meta"]["count"]) ) {
			$_data["meta"]["count"]     = wanzi_text_mb_strlen( wp_strip_all_tags( $post->post_content ) );
		}
	    if( !isset($_data["meta"]["source"]) && get_post_meta( $post_id, "source" ,true ) ) {
			$_data["meta"]["source"] 	= get_post_meta( $post_id, "source" ,true );
		}
		if( !isset($_data["comments"]) ) {
		    $_data["comments"] 			= wanzi_count_comment_type( $post_id, 'comment' );
		}
		if( !isset($_data["isfav"]) ) {
		    $_data["isfav"] 			= (bool)wanzi_comment_post_status( $post_id, $user_id, 'fav' );
		}
		if( !isset($_data["favs"]) ) {
		    $_data["favs"] 			    = wanzi_count_comment_type( $post_id, 'fav' );
		}
		if( !isset($_data["islike"]) ) {
		    $_data["islike"] 			= (bool)wanzi_comment_post_status( $post_id, $user_id, 'like' );
		}
		if( !isset($_data["likes"]) ) {
		    $_data["likes"] 			= wanzi_count_comment_type( $post_id, 'like' );
		}
		$taxonomies 	  = get_object_taxonomies( $post->post_type );
		if( !empty($taxonomies) ) {
    		foreach( $taxonomies as $taxonomy ) {
        		$terms 					= wp_get_post_terms($post_id, $taxonomy);
        		foreach( $terms as $term ) {
        			$tax 				= array();
        			$cover 				= wp_miniprogram_option('thumbnail');
        			if( get_term_meta( $term->term_id, 'cover', true ) ) {
        				$cover 			= get_term_meta( $term->term_id, 'cover', true );
        			}
            		$tax["id"] 			= $term->term_id;
            		$tax["name"] 		= $term->name;
            		$tax["description"] = $term->description;
            		$tax["cover"] 		= apply_filters( 'wanzi_thumbnail', $cover, 'full', $taxonomy );
            		if( $taxonomy === 'post_tag' ) { $taxonomy = "tag"; }
            		$_data[$taxonomy][] = $tax;
            	}
    		}
		}
	}
	if( wp_miniprogram_option('mediaon') && ( get_post_meta( $post_id, 'video', true ) || get_post_meta( $post_id, 'audio', true ) ) ) {
		$_data["media"]['cover'] 	= get_post_meta( $post_id, 'cover', true ) ? get_post_meta( $post_id, 'cover' ,true ) : wanzi_post_thumbnail( $post_id );
		$_data["media"]['author'] 	= get_post_meta( $post_id, 'author', true );
		$_data["media"]['title'] 	= get_post_meta( $post_id, 'title', true );
		$_data["media"]['video'] 	= get_post_meta( $post_id, 'video', true );
		$_data["media"]['audio'] 	= get_post_meta( $post_id, 'audio', true );
	}
	if( isset( $request['id'] ) ) {
		if( is_smart_miniprogram() && !isset($_data["smartprogram"]["title"]) ) {
			$keywords 		= get_post_meta( $post_id, "keywords", true );
			if( ! $keywords ) {
				$tags = wp_get_post_tags( $post_id );
				$keywords 	= implode(",", wp_list_pluck( $tags, 'name' ));
			}
			$_data["smartprogram"]["title"] 		= html_entity_decode( get_the_title( $post_id ) ) .'-'.get_bloginfo('name');
			$_data["smartprogram"]["keywords"] 		= $keywords;
			$_data["smartprogram"]["description"] 	= wp_strip_all_tags( wp_trim_excerpt( "", $post_id ), true );
			$_data["smartprogram"]["image"] 		= wanzi_post_gallery( $post_id );
			$_data["smartprogram"]["visit"] 		= array( 'pv' => (int)get_post_meta( $post_id, "views" ,true ) );
			$_data["smartprogram"]["comments"] 		= wanzi_count_comment_type( $post_id, 'comment' );
			$_data["smartprogram"]["likes"] 		= wanzi_count_comment_type( $post_id, 'like' );
			$_data["smartprogram"]["collects"] 		= wanzi_count_comment_type( $post_id, 'fav' );
		}
		if( ! get_post_meta( $post_id, 'video', true ) ) {
			$_data["content"]["rendered"] 			= apply_filters( 'the_video_content', $post->post_content );
		}
		$_data["post_favs"] 						= wanzi_get_comments_by_type( $post_id, 'fav' );
		$_data["post_likes"] 						= wanzi_get_comments_by_type(  $post_id, 'like' );
		if( wp_miniprogram_option("prevnext") ) {
			$category = get_the_category( $post_id );
			$next     = get_next_post( $category[0]->term_id, '', 'category' );
			$previous = get_previous_post( $category[0]->term_id, '', 'category' );
			if( !empty($next->ID) ) {
				$_data["next_post"]["id"] 					= $next->ID;
				$_data["next_post"]["title"]["rendered"] 	= $next->post_title;
				$_data["next_post"]["thumbnail"] 			= wanzi_post_thumbnail( $next->ID );
				$_data["next_post"]["views"] 				= (int)get_post_meta( $next->ID, "views" ,true );
			}
			if( !empty($previous->ID) ) {
				$_data["prev_post"]["id"] 					= $previous->ID;
				$_data["prev_post"]["title"]["rendered"] 	= $previous->post_title;
				$_data["prev_post"]["thumbnail"] 			= wanzi_post_thumbnail( $previous->ID );
				$_data["prev_post"]["views"] 				= (int)get_post_meta( $previous->ID, "views" ,true );
			}
		}
	} else {
		if( ! wp_miniprogram_option("post_content") ) {
			unset($_data['content']);
		}
		if( wp_miniprogram_option("post_picture") ) {
			$_data["pictures"] = wanzi_post_gallery( $post_id );
		}
	}
	if( is_miniprogram() ) {
		unset($_data['categories']);
		unset($_data['tags']);
		unset($_data["_edit_lock"]);
		unset($_data["_edit_last"]);
		unset($_data['featured_media']);
		unset($_data['ping_status']);
		unset($_data['template']);
		unset($_data['slug']);
		unset($_data['status']);
		unset($_data['modified_gmt']);
		unset($_data['post_format']);
		unset($_data['date_gmt']);
		unset($_data['guid']);
		unset($_data['curies']);
		unset($_data['modified']);
		unset($_data['status']);
		unset($_data['comment_status']);
		unset($_data['sticky']);    
		unset($_data['_links']);
	}
    $data->data = $_data;
	return $data;
}, 10, 3 );

add_filter( 'rest_prepare_page', function ( $data, $post, $request ) {
	$_data   = $data->data;
	$post_id = $post->ID;
	if( !isset($_data["week"]) ) {
	    $_data["week"] 	  = get_wp_post_week( $post->post_date );
	}
	if( is_miniprogram() || is_debug() ) {
	    if( ! isset($_data["author"]["id"]) ) {
	        unset($_data['author']);
    	    $_data["author"]["id"] 			= (int)$post->post_author;
    		$_data["author"]["name"] 		= get_the_author_meta('nickname', $post->post_author);
    		$_data["author"]["avatar"]  	= get_avatar_url( $post->post_author );
    		if( get_user_meta($post->post_author, 'avatar', true) ) {
    			$_data["author"]["avatar"]  = get_user_meta($post->post_author, 'avatar', true);
    		}
    		$_data["author"]["description"] = get_the_author_meta('description', $post->post_author);
	    }
	    if( !isset($_data["except"]) ) {
	        $_data["except"] 				= (bool)get_post_meta( $post_id, "except", true );
	    }
	    if( !isset($_data["menu"]["icon"]) ) {
	        $_data["menu"]["icon"] 			= get_post_meta( $post_id, "icon", true );
		    $_data["menu"]["title"] 		= get_post_meta( $post_id, "title" ,true );
	    }
	    if( !isset($_data["meta"]["thumbnail"]) ) {
			$_data["meta"]["thumbnail"]     = wanzi_post_thumbnail( $post_id );
		}
		if( !isset($_data["meta"]["views"]) ) {
			$_data["meta"]["views"]         = (int)get_post_meta( $post_id, "views" ,true );
		}
		if( !isset($_data["comments"]) ) {
		    $_data["comments"] 			= wanzi_count_comment_type( $post_id, 'comment' );
		}
		if( !isset($_data["favs"]) ) {
		    $_data["favs"] 			    = wanzi_count_comment_type( $post_id, 'fav' );
		}
		if( !isset($_data["likes"]) ) {
		    $_data["likes"] 			= wanzi_count_comment_type( $post_id, 'like' );
		}
		if( !isset($_data["excerpt"]) ) {
		    $_data["excerpt"]["rendered"] 	= html_entity_decode( wp_trim_words( wp_strip_all_tags( $post->post_content ), 100, '...' ) );
		}
	}
	if( ! isset( $request['id'] ) ) {
		if( wp_miniprogram_option("post_content") ) {
			unset($_data['content']);
		}
	} else {
		if( is_smart_miniprogram() && !isset($_data["smartprogram"]["title"]) ) {
			$_data["smartprogram"]["title"] 		= html_entity_decode( get_the_title( $post_id ) ) .'-'.get_bloginfo('name');
			$_data["smartprogram"]["keywords"] 		= get_post_meta( $post_id, "keywords", true );
			$_data["smartprogram"]["description"] 	= html_entity_decode( wp_trim_words( wp_strip_all_tags( $post->post_content ), 100, '...' ) );
			$_data["smartprogram"]["image"] 		= wanzi_post_gallery( $post_id );
			$_data["smartprogram"]["visit"] 		= array( 'pv' => (int)get_post_meta( $post_id, "views" ,true ) );
			$_data["smartprogram"]["comments"] 		= wanzi_count_comment_type( $post_id, 'comment' );
			$_data["smartprogram"]["likes"] 		= wanzi_count_comment_type( $post_id, 'like' );
			$_data["smartprogram"]["collects"] 		= wanzi_count_comment_type( $post_id, 'fav' );
		}
	}
	if( is_miniprogram() ) {
		unset($_data["_edit_lock"]);
		unset($_data["_edit_last"]);
		unset($_data['featured_media']);
		unset($_data['ping_status']);
		unset($_data['template']);
		unset($_data['slug']);
		unset($_data['modified_gmt']);
		unset($_data['post_format']);
		unset($_data['date_gmt']);
		unset($_data['guid']);
		unset($_data['curies']);
		unset($_data['modified']);
		unset($_data['status']);
		unset($_data['comment_status']);
		unset($_data['sticky']);    
		unset($_data['_links']);
	}
    $data->data = $_data;
	return $data;
}, 10, 3 );

add_filter( 'rest_prepare_category', function ( $data, $item, $request ) {
	$term_id 			= $item->term_id;
	$args 				= array('category' => $term_id, 'numberposts'  => 1);
	$posts 				= get_posts($args);
	if( !empty($posts) ) {
		$recent_date 	= $posts[0]->post_date;
	} else {
		$recent_date 	= '无更新';
	}
    if( get_term_meta($item->term_id, 'cover', true) ) {
        $cover 			= get_term_meta($item->term_id, 'cover', true);
    } else {
		$cover 			= wp_miniprogram_option('thumbnail');
	}
	$except = true;
	if( get_term_meta($item->term_id, 'except', true) ) {
		$except 		= false;
	}
	if( isset($request['id']) ) {
		if( wp_miniprogram_option('bd_appkey') && wp_miniprogram_option('bd_secret') ) {
			$smartprogram["title"] 			= $item->name .'-'.get_bloginfo('name');
			$smartprogram["keywords"] 		= $item->name;
			$smartprogram["description"] 	= $item->description;
			$data->data['smartprogram'] 	= $smartprogram;
		}
	}
	$data->data['cover'] 					= apply_filters( 'wanzi_thumbnail', $cover, 'full', 'category' );
	$data->data['date'] 					= $recent_date;
	$data->data['except'] 					= $except;
	return $data;
}, 10, 3 );

add_filter( 'rest_prepare_post_tag', function ( $data, $item, $request ) {
	$term_id 	= $item->term_id;
    if( get_term_meta($item->term_id, 'cover', true) ) {
        $cover 	= get_term_meta($item->term_id, 'cover', true);
    } else {
		$cover 	= wp_miniprogram_option('thumbnail');
	}
	$except = true;
	if( get_term_meta($item->term_id, 'except', true) ) {
		$except = false;
	}
	$data->data['cover'] 					= apply_filters( 'wanzi_thumbnail', $cover, 'full', 'post_tag' );
	$data->data['except'] 					= $except;
	if( isset($request['id']) ) {
		if( wp_miniprogram_option('bd_appkey') && wp_miniprogram_option('bd_secret') ) {
			$smartprogram["title"] 			= $item->name .'-'.get_bloginfo('name');
			$smartprogram["keywords"] 		= $item->name;
			$smartprogram["description"] 	= $item->description;
			$data->data['smartprogram'] 	= $smartprogram;
		}
	}
	return $data;
}, 10, 3 );

// wp-rest-cache/includes/api/class-endpoint-api.php 232 row isset($result['data']) 检测数据
if( in_array( 'wp-rest-cache/wp-rest-cache.php', apply_filters( 'active_plugins', get_option('active_plugins') ) ) && wp_miniprogram_option('rest_cache') ) {
    add_filter( 'wp_rest_cache/allowed_endpoints', function ( $allowed_endpoints ) {
        if ( ! isset( $allowed_endpoints[ 'mp/v1' ] ) ) {
            $allowed_endpoints[ 'mp/v1' ] = array(
                'setting',
                'posts',
                'pages',
                'comments',
                'advert',
                'menu'
            );
        }
        return $allowed_endpoints;
    }, 10, 1 );
}

add_filter( 'the_content', function ( $content ) {
	$post_id = get_the_ID();
	if( wp_miniprogram_option('mediaon') ) {
		$media_author 	= '';
		$media_title 	= '';
		$cover_url 		= wanzi_post_thumbnail( $post_id );
		if( get_post_meta( $post_id, 'cover' ,true ) ) {
			$cover_url 	= get_post_meta( $post_id, 'cover' ,true );
		} 

		if( get_post_meta( $post_id, 'author' ,true ) ) {
			$media_author = 'author="'.get_post_meta( $post_id, 'author' ,true ).'" ';
		}
		if( get_post_meta( $post_id, 'title' ,true ) ) {
			$media_title  = ' title="'.get_post_meta( $post_id, 'title' ,true ).'" ';
		}
		$video_id 		  = get_post_meta($post_id,'video',true);
		$audio_id 		  = get_post_meta($post_id,'audio',true);
		if( !empty($video_id) && wp_miniprogram_option('qvideo') ) {
			$video 		  = wanzi_parse_tencent_video( $video_id );
			if( $video ) {
				$video_code = '<p><video '.$media_author.$media_title.' controls="controls" poster="'.$cover_url.'" src="'.$video.'" width="100%"></video></p>';
			} else {
				$video_code = '<p><video '.$media_author.$media_title.' controls="controls" poster="'.$cover_url.'" src="'.$video_id.'" width="100%"></video></p>';
			}
			$content 		= $video_code.$content;
		}
		if( !empty($audio_id) ) {
			$audio_code 	= '<p><audio '.$media_author.$media_title.' controls="controls" src="'.$audio_id.'" width="100%"></audio></p>';
			$content 		= $audio_code.$content;
		}
	}
	return $content;
} );

add_filter( 'the_video_content', function( $content ) {
	preg_match("/https\:\/\/v\.qq\.com\/x\/page\/(.*?)\.html/",$content, $qvideo);
	preg_match("/https\:\/\/v\.qq\.com\/cover\/(.*?)\/(.*?)\.html/",$content, $tencent);
	preg_match_all('|<img.*?src=[\'"](.*?)[\'"].*?>|i', do_shortcode($content), $matches);
	$url = '';
	$video = '';
	$thumbnails = "";
	if( $matches && isset($matches[1]) && isset($matches[1][0]) ) {
		$thumbnails = 'poster="'.$thumbnails.'" ';
	}
	if( $qvideo || $tencent ) {
		if( $qvideo ) {
			$url = $qvideo[0];
		} else if( $tencent ) {
			$url = $tencent[0];
		}
		if( $url ) {
			$video = wanzi_parse_tencent_video( $url );
		}
		if( $video ) {
			return preg_replace('~<video (.*?)></video>~s','<video '.$thumbnails.'src="'.$video.'" controls="controls" width="100%"></video>', $content);
		}
	}
	return $content;
} );

add_filter('category_description', 'wp_strip_all_tags');

add_filter( 'user_contactmethods', function( $userInfo ) {
	$userInfo['gender'] 				= __( '性别' );
	$userInfo['openid'] 				= __( 'OpenID' );
	$userInfo['avatar'] 				= __( '微信头像' );
	$userInfo['city'] 					= __( '所在城市' );
	$userInfo['province'] 				= __( '所在省份' );
	$userInfo['country'] 				= __( '所在国家' );
	$userInfo['language'] 				= __( '系统语言' );
	$userInfo['expire_in'] 				= __( '缓存有效期' );
	return $userInfo;
});

add_action( 'personal_options_update', 'update_miniprogam_platform' );
add_action( 'edit_user_profile_update', 'update_miniprogam_platform' );

function update_miniprogam_platform( $user_id ) {
	if( !current_user_can( 'edit_user', $user_id ) )
    	return false;
	update_user_meta( $user_id, 'platform', $_POST['platform'] );
}

add_action( 'show_user_profile', 'add_miniprogam_platform_source' );
add_action( 'edit_user_profile', 'add_miniprogam_platform_source' );

function add_miniprogam_platform_source( $user ) { ?>
<table class="form-table">       
    <tr>
        <th><label for="dropdown">平台用户</label></th>
        <td>
            <?php $selected = get_the_author_meta( 'platform', $user->ID ); ?>
            <select name="platform" id="platform">
				<option value="website" <?php selected( $selected, 'website', true ) ?>>网站注册</option>
                <option value="wechat" <?php selected( $selected, 'wechat', true ) ?>>微信小程序</option>
				<option value="tencent" <?php selected( $selected, 'tencent', true ) ?>>QQ 小程序</option>
				<option value="baidu" <?php selected( $selected, 'baidu', true ) ?>>百度小程序</option>
				<option value="toutiao" <?php selected( $selected, 'toutiao', true ) ?>>头条小程序</option>
            </select>
            <span class="description">用户注册来源所属平台</span>
        </td>
    </tr>
</table>
<?php }

add_filter( 'manage_users_columns', function ( $columns ){ 
	$columns["registered"] = "注册时间";
	$columns["platform"] = "注册平台";
	return $columns;
});
add_action( 'manage_users_custom_column', function ( $value, $column_name, $user_id ) {
	$user 			= get_userdata( $user_id );
	if( 'registered' == $column_name ) {
		$value 		= get_date_from_gmt($user->user_registered);
	} else if( 'platform' == $column_name ) {
		$platform 	= get_user_meta($user->ID, 'platform', true);
		if( $platform == 'wechat' ) {
			$value 	= '微信小程序';
		} elseif( $platform == 'tencent' ) {
			$value 	= 'QQ 小程序';
		} elseif( $platform == 'baidu' ) {
			$value 	= '百度小程序';
		} elseif( $platform == 'toutiao' ) {
			$value 	= '头条小程序';
		} else {
			$value 	= '网站用户';
		}
	}
	return $value;
}, 10, 3 );

add_action('admin_head-edit-comments.php', function ( ) {
	echo'<style type="text/css">
		.column-type { width:80px; }
		</style>';
});
add_filter( 'manage_edit-comments_columns', function ( $columns ) {
	$columns[ 'type' ] = __( '类型' );
	return $columns;
});
add_action( 'manage_comments_custom_column', function  ( $column_name, $comment_id ) {
	switch( $column_name ) {
		case "type":
			$type = get_comment_type( );
			switch( $type ) {
				case 'fav' :
					echo "收藏";
					break;
				case 'like' :
					echo "点赞";
					break;
				case 'comment' :
					echo "评论";
					break;
				default :
					echo $commenttxt;
			}
	}
}, 10, 2 );

if( wp_miniprogram_option('reupload') ) {
	add_filter('wp_handle_upload_prefilter',function ($file) {
		$time 			= date("YmdHis");
		$file['name'] 	= $time . "" . mt_rand(1, 100) . "." . pathinfo($file['name'], PATHINFO_EXTENSION);
		return $file;
	});
}

if( wp_miniprogram_option('gutenberg') || is_debug() ) {
	add_filter('use_block_editor_for_post_type', '__return_false');
}

add_shortcode('qvideo', function ( $attr ) {
	extract(
        shortcode_atts(
            array(
				'vid' => ''
            ), 
            $attr
        )
	);
	if( strpos($vid, 'v.qq.com') === false ) {
		$url = 'https://v.qq.com/x/page/'.$vid.'.html';
	} else {
		$url =  $vid;
	}
	$video = wanzi_parse_tencent_video( $url );
	if( $video ) {
		$output = '<p><video controls="controls" poster="https://puui.qpic.cn/qqvideo_ori/0/'.$vid.'_496_280/0" src="'.$video.'" width="100%"></video></p>';
	} else {
		$output = '<p>腾讯视频参数不支持，请重新检查！</p>';
	}
	return $output;
});

add_action( 'admin_print_footer_scripts', function ( ) {
	if( wp_script_is('quicktags') ) {
?>
    <script type="text/javascript">
    QTags.addButton( 'qvideo', '腾讯视频', '[qvideo vid="腾讯视频 vid 或 url"]','' );
    </script>
<?php
    }
} );