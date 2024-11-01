<?php

if( function_exists('wp_applets_activty_bulletin') ) { 
    wp_applets_activty_bulletin( );
}

?>
<div class="wrap">
	<h2 class="mp-nav-tab-wrapper wp-clearfix">
		<?php options_nav_menu( $options ); ?>
	</h2>
	<div id="section" class="section-container wp-clearfix">
		<form id="<?php echo $option["id"]; ?>" method="post" action="options.php" enctype="multipart/form-data">
			<?php settings_fields( $option['group'] ); ?>
			<?php options_container( $option['options'], $options ); ?>
			<?php do_settings_sections( $option['group'] ); ?>
			<?php if( current_user_can( 'administrator' ) ) { ?>
				<div id="mp-submit-options">
					<input type="submit" class="button-primary" id="update" name="update" value="<?php esc_attr_e( '保存设置', $option['group'] ); ?>" />
					<?php if( isset($option['reset']) && $option['reset'] ) { ?>
						<input type="submit" class="reset-button button-secondary" id="reset" name="reset" value="<?php esc_attr_e( '恢复默认', $option['group'] ); ?>" onclick="return confirm( '<?php print esc_js( __( '警告：点击确定将恢复全部默认设置!', $option['group'] ) ); ?>' );" />
					<?php } ?>
					<div class="clear"></div>
				</div>
			<?php } ?>
		</form>
	</div><!-- / #container -->
</div><!-- / .wrap -->
<div id="scroll-bar">
	<div id="goTop" class="scroll-up"><a href="javascript:;" title="直达顶部">▲</a></div>
	<div id="down" class="scroll-down"><a href="javascript:;" title="直达底部">▼</a></div>
</div>