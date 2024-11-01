<?php
/*
 * WordPress Custom API Data Hooks
 */
 
if( !defined( 'ABSPATH' ) ) exit;

add_filter('mp_category_term_options', function ( $options ) {
	$options['cover'] = array(
		'title' => '封面', 
		'type' => 'upload'
	);
	return $options;
});

add_filter('mp_post_tag_term_options', function ( $options ) {
	$options['cover'] = array(
		'title' => '封面', 
		'type' => 'upload'
	);
	return $options;
});

add_filter( 'mp_post_meta_options', function ( $options ) {
	$fields = array();
	$options['post-box']['title'] = '文章设置';
	$options['post-box']['type'] = 'post';
	$fields['videoAd'] = ['title'=>'激励广告阅读', 'type'=>'checkbox', 'description'=>'是否启用激励视频阅读，需前端填写激励广告id'];
	$fields['source'] = ['title'=>'出处/作者', 'type'=>'text', 'class' => 'regular-text','description'=>'文章引用来源/出处,或填写文章作者'];
	$fields['thumbnail'] = ['title'=>'自定义缩略图', 'type'=>'upload','class' => 'regular-text','description'=>'自定义缩略图地址.注意:设置后无须另行设置特色图像'];
	if( wp_miniprogram_option('mediaon') ) {
		$fields['cover'] = ['title'=>'封面图像', 'type'=>'upload','class' => 'regular-text','description'=>'视频封面,不设置则采用文章缩略图'];
		$fields['author'] = ['title'=>'视频作者', 'type'=>'text','class' => 'regular-text','description'=>'视频表演作者'];
		$fields['title'] = ['title'=>'作品名称', 'type'=>'text','class' => 'regular-text','description'=>'视频作品名称'];
		$fields['video'] = ['title'=>'视频地址', 'type'=>'upload',	'class' => 'regular-text'];
		$fields['audio'] = ['title'=>'音频地址', 'type'=>'upload',	'class' => 'regular-text'];
	}
	if( wp_miniprogram_option('bd_appkey') && wp_miniprogram_option('bd_secret') ) {
		$fields['keywords'] = ['title'=>'Web 关键词', 'type'=>'text', 'class' => 'regular-text','description'=>'百度小程序 Web 化页面关键词设置, 多个关键词用英文逗号隔开'];
	}
	$options['post-box']['fields'] = $fields;
	return $options;
} );

add_filter( 'mp_page_meta_options', function ( $options ) {
	$options['page-box'] =  [
		'title'   => '页面设置',
		'type'	  => 'page',
		'fields'  => [
			'icon'			=>['title'=>'ICON',	'type'=>'text',	'class' => 'regular-text','description'=>'页面列表项 ICON ，用于个人中心页输出页面列表的图标'],
			'title'			=>['title'=>'标题',	'type'=>'text',	'class' => 'regular-text','description'=>'页面列表项标题，用于个人中心页输出页面列表的标题简称'],
			'thumbnail'		=>['title'=>'自定义缩略图',	'type'=>'upload','class' => 'regular-text','description'=>'自定义缩略图地址.注意:设置后无须另行设置特色图像']
		]
	];
	if( wp_miniprogram_option('bd_appkey') && wp_miniprogram_option('bd_secret') ) {
		$options['page-box']['fields']['keywords'] = ['title'=>'Web 关键词', 'type'=>'text', 'class' => 'regular-text','description'=>'百度小程序 Web 化页面关键词设置, 多个关键词用英文逗号隔开'];
	}
	return $options;
} );

if( wp_miniprogram_option('sticky') ) {
	add_filter( 'views_edit-post', function ( $views ) {
		global $current_user, $wp_query;
		$query = array(  
			'post_type'   => 'post',  
			'post_status' => 'publish',
			'meta_key'	=> 'focus'
		);
		$result = new WP_Query( $query );
		$class = isset($_GET['focus'])  ? ' class="current"' : '';  
		$views[] = sprintf(__('<a href="%s" ' .$class.' aria-current="page">推荐文章 <span class="count">(%d)</span></a>', 'focus'), admin_url('edit.php?post_type=post&focus=true'), $result->found_posts); 
		return $views; 
	});
	add_filter('parse_query', function ( $query ) {
		global $pagenow;
		if( is_admin() && 'edit.php' == $pagenow && isset($_GET[ 'focus' ]) ) {
			$query->query_vars[ 'post_type' ]    = 'post';
			$query->query_vars[ 'post_status' ]  = 'publish';
			$query->query_vars[ 'meta_key' ] 	 = 'focus';
		}
		return $query;
	});
}

add_filter('admin_comment_types_dropdown', function ( $comment_types ) {
	unset($comment_types['pings']);
	return array_merge($comment_types, ['fav'=>'收藏'], ['like'=>'喜欢']);
});

add_action('parse_comment_query', function ( $comment_query ) {
	if( is_singular() ) {
		if( isset($comment_query->query_vars['parent']) && $comment_query->query_vars['parent'] == 0 ) {
			$comment_query->query_vars['type__not_in']	= array( 'fav' , 'like' );
		}	
	}
});

if( wp_miniprogram_option("we_submit") ) {
	add_action('publish_post', 'we_miniprogram_posts_submit_pages', 10, 1);
	add_action('publish_to_publish', function ( ) {
		remove_action('publish_post', 'we_miniprogram_posts_submit_pages', 10, 1);
	},11,1);
}
function we_miniprogram_posts_submit_pages( $post_id ) {
	$submit = array( );
	$submit['wechat'] = apply_filters( 'mp_we_submit_pages', $post_id );
	if( wp_miniprogram_option('bd_submit') && wp_miniprogram_option('bd_appkey') && wp_miniprogram_option('bd_secret') ) {
		$submit['baidu'] = apply_filters( 'mp_bd_submit_pages', $post_id );
	}
	return $submit;
}
