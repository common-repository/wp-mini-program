<?php

if ( !defined( 'ABSPATH' ) ) exit;

include( MINI_PROGRAM_REST_API.'include/dashboard.php' );
include( MINI_PROGRAM_REST_API.'include/custom.php' );
include( MINI_PROGRAM_REST_API.'include/hooks.php' );
include( MINI_PROGRAM_REST_API.'include/utils.php' );
include( MINI_PROGRAM_REST_API.'include/auth.php' );
include( MINI_PROGRAM_REST_API.'include/notices.php' );
include( MINI_PROGRAM_REST_API.'include/subscribe.php' );
include( MINI_PROGRAM_REST_API.'router/setting.php' );
include( MINI_PROGRAM_REST_API.'router/users.php' );
include( MINI_PROGRAM_REST_API.'router/posts.php' );
include( MINI_PROGRAM_REST_API.'router/custom.php' );
include( MINI_PROGRAM_REST_API.'router/pages.php' );
include( MINI_PROGRAM_REST_API.'router/comments.php' );
include( MINI_PROGRAM_REST_API.'router/qrcode.php' );
include( MINI_PROGRAM_REST_API.'router/auth.php' );
include( MINI_PROGRAM_REST_API.'router/subscribe.php' );
include( MINI_PROGRAM_REST_API.'router/advert.php' );
include( MINI_PROGRAM_REST_API.'router/menu.php' );
include( MINI_PROGRAM_REST_API.'router/security.php' );

// 时区
if( ! function_exists('datetime_timezone') ) {
    function datetime_timezone( ) {
        $timezone_string = get_option( 'timezone_string' );
        if( $timezone_string ) {
            return $timezone_string;
        }
        $offset    = (float) get_option( 'gmt_offset' );
        $hours     = (int) $offset;
        $minutes   = ( $offset - $hours );
        $sign      = ( $offset < 0 ) ? '-' : '+';
        $abs_hour  = abs( $hours );
        $abs_mins  = abs( $minutes * 60 );
        $tz_offset = sprintf( '%s%02d:%02d', $sign, $abs_hour, $abs_mins );
        return $tz_offset;
    }
}

if( ! function_exists('get_userdata_by_meta') ) {
    function get_userdata_by_meta( $key, $value ) {
        global $wpdb;
	    $table_name = $wpdb->prefix . 'usermeta';
        $where = $wpdb->prepare("AND meta_key = %s AND meta_value = %s ", esc_sql($key), esc_sql($value));
        $sql = "SELECT * FROM $table_name WHERE $where";
		$sql = @str_replace('WHERE AND','WHERE', $sql);
        $data = $wpdb->get_row( $sql );
		return $data;
    }
}

// 统计文章字符
if( ! function_exists('wanzi_text_mb_strlen') ) {
    function wanzi_text_mb_strlen( $content ) {
        if( ! empty($content) ) {
            $count = (int)mb_strlen( preg_replace( '/\s/', '', html_entity_decode( strip_tags( $content ) ) ),'UTF-8' );
        } else {
            $count = 0;
        }
        return $count;
    }
}

// 之前时间格式
if( ! function_exists('datetime_before') ) {
    function datetime_before( $the_time ) {
        date_default_timezone_set( datetime_timezone( ) );
        $now        = time( ); 
        $time       = strtotime( $the_time );
        $duration   = $now - $time;
        if( $duration < 0 ) {
            return date("Y年m月d日", $time); 
        } else if( $duration < 60 ) {
            return $duration.'秒前'; 
        } else if( $duration < 3600 ) {
            return floor( $duration/60 ).'分钟前'; 
        } else if( $duration < 86400 ) {
            return floor( $duration/3600).'小时前';
        } else if( $duration <  604800 ) {
            return floor( $duration/86400 ).'天前';
        } else {
            return date("Y年m月d日", $time); 
        }
    }
}

if( ! function_exists('get_wp_post_week') ) {
    function get_wp_post_week( $the_time ) {
        $datetime = strtotime( $the_time );
        $trans = date("Y-m-d", $datetime);
        $weekarray = array("日", "一", "二", "三", "四", "五", "六");
        return '星期'.$weekarray[date("w",strtotime($trans))];
    }
}

function wanzi_post_thumbnail( $post_id = 0, $size = 'full' ) {
    if( ! $post_id ) {
        global $post;
        $post_id = $post->ID;
    }
    $thumbnails = get_post_meta( $post_id, 'thumbnail', true );
    if( ! empty($thumbnails) ) {
		return apply_filters( 'wanzi_thumbnail', $thumbnails, $size, get_post_type( $post_id ) );
	} else if( has_post_thumbnail($post_id) ) {
        $attachment_id = get_post_thumbnail_id( $post_id );
        if( $attachment_id ) {
            $attachment = wp_get_attachment_image_src($attachment_id, $size);
			return apply_filters( 'wanzi_thumbnail', $attachment[0], $size, get_post_type( $post_id ) );
        } else {
            $thumbHtml = get_the_post_thumbnail( $post_id, $size );
            if( preg_match('/src=\"(.*?)\"/', $thumbHtml, $attachment) ) {
				return apply_filters( 'wanzi_thumbnail', $attachment[1], $size, get_post_type( $post_id ) );
            } else {
				$thumbnails = wp_miniprogram_option( 'thumbnail' ); // 指定默认链接
				return apply_filters( 'wanzi_thumbnail', $thumbnails, $size, get_post_type( $post_id ) );
			}
        }
    } else {
        $wp_post = get_post( $post_id );
		$post_content = $wp_post->post_content;
        preg_match_all('|<img.*?src=[\'"](.*?)[\'"].*?>|i', do_shortcode($post_content), $contents);
		if( $contents && isset($contents[1]) && isset($contents[1][0]) ) {     
			$thumbnails = $contents[1][0];
		}
        if( !empty($thumbnails) ) {
			return apply_filters( 'wanzi_thumbnail', $thumbnails, $size, get_post_type( $post_id ) );
		} else {
			$thumbnails = wp_miniprogram_option('thumbnail'); // 指定默认链接
			return apply_filters( 'wanzi_thumbnail', $thumbnails, $size, get_post_type( $post_id ) );
		}
    }
}

function wanzi_post_gallery( $post_id = 0, $number = '', $size = 'full' ) {
    if( ! $post_id ) {
        global $post;
        $post_id = $post->ID;
    }
    $galleries          = array( );
    $the_post       	= get_post( $post_id );
	$post_content   	= $the_post->post_content;
    if( has_shortcode( $post->post_content, 'gallery' ) ) {
        $galley         = get_post_galleries_images( $the_post );
        foreach( $galley as $_gallery ) {
            foreach( $_gallery as $image ) {
                $galleries[] = $image;
            }
        }
    } else {
        preg_match_all('|<img.*?src=[\'"](.*?)[\'"].*?>|i', do_shortcode($post_content), $contents);
        if( $contents && isset($contents[1]) ) {
            $_images    = $contents[1];
            for( $i = 0; $i < count($contents[1]); $i++ ) {
                $galleries[] = $contents[1][$i];
            }
        }
    }
    if( $number && ! empty($galleries) ) {
        $output = array( );
        for( $i = 0; $i < $number; $i++ ) { 
            $output[] = $galleries[$i];
        }
        return apply_filters( 'wanzi_galleries', $output, $size, get_post_type( $post_id ) );
    }
    return apply_filters( 'wanzi_galleries', $galleries, $size, get_post_type( $post_id ) );
}

// 推送订阅消息错误码信息
function mp_subscribe_errcode_msg( $key ) {
    $msg = array(
        '0' => __('消息推送成功','imahui'),
        '40003' => __('用户 OpenID 错误','imahui'),
        '40037' => __('订阅模板 ID 错误','imahui'),
        '43101' => __('用户拒绝接受消息','imahui'),
        '47003' => __('模板参数不准确','imahui'),
        '41030' => __('页面路径不正确','imahui')
    );
    return isset($msg[$key]) ? $msg[$key] : '';
}

// Admin footer text
add_filter( 'admin_footer_text', function ( $text ) {
    $text = '<span id="footer-thankyou">感谢使用 <a href=http://cn.wordpress.org/ target="_blank">WordPress</a>进行创作，<a target="_blank" rel="nofollow" href="https://www.weitimes.com/">点击访问</a> WordPress 小程序专业版。</span>';
    return $text;
} );