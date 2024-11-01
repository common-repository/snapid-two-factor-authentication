<div class="wrap">
	<h2><img src="<?php echo plugins_url( '../images/SnapIDLogo.png', __FILE__ ); ?>" width="150" /></h2>
	<form method="post" class="postbox snapid-form snapid-main-form" action="<?php echo esc_attr( $options['settings'] ); ?>">
		<?php settings_fields( 'snapid_settings' ); ?>
		<?php do_action( 'snapid_settings' ); ?>
	</form>
	<div class="snapid-clearfix"></div>
	<form id="snapid-uninstall-form" method="post" class="postbox snapid-form" action="<?php echo esc_attr( $options['uninstall'] ); ?>">
		<input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce( 'snapid-uninstall' ); ?>" />
		<?php do_action( 'snapid_uninstall' ); ?>
		<?php submit_button( 'Uninstall SnapID&trade;', 'secondary' ); ?>
	</form>
</div><!-- .wrap -->
