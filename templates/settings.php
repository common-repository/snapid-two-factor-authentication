<?php
global $wp_roles;
?>
<input type="hidden" id="snapid-customer-id"  name="snapid_options[customer_id]" value="<?php echo esc_attr( $options['customer_id'] ); ?>" />
<input type="hidden" id="snapid-app-id" name="snapid_options[app_id]" value="<?php echo esc_attr( $options['app_id'] ); ?>" />
<input type="hidden" id="snapid-app-sub-id" name="snapid_options[app_sub_id]" value="<?php echo esc_attr( $options['app_sub_id'] ); ?>" />
<input type="hidden" id="snapid-hide-videos" name="snapid_options[hide_videos]" value="<?php echo esc_attr( $options['hide_videos'] ); ?>" />
<input type="hidden" id="snapid-terms-and-conditions" name="snapid_options[terms_and_conditions]" value="<?php echo $options['terms_and_conditions'] ? 1 : 0; ?>" />

<div id="snapid-enabled-wrap" class="snapid-settings">
	<h3>Plugin Settings</h3>
	<p class="description">Have a question? Want to give us feedback or contact us? <a href="https://textpower.zendesk.com/hc/en-us/categories/202529568-SnapID-Login-and-Authentication" target="_blank">Visit the SnapID&trade; support site</a>.</p>
	<table id="snapid-form-table" class="form-table">
		<?php if ( ! $options['is_snapid_setup'] ) : ?>
			<tr valign="top">
				<th scope="row">
					Register SnapID&trade;
				</th>
				<td>
					<div class="spinner snapid-spinner"><span>Registering site...</span></div>
					<div class="snapid-clearfix"></div>
					<div class="snapid-display-message"></div>
					<button id="snapid-register" class="snapid-register"><span>Register with SnapID&trade;</span></button>
					<p>
						<label for="snapid-terms-agree"><input type="checkbox" id="snapid-terms-agree" value="1" /> By checking this box, you agree to SnapID's&trade; <a href="https://secure.textkey.com/snapid/termsandconditions.php" target="_blank">Terms and Conditions</a>.</label>
					</p>
					<p class="description">Have your cellphone ready before registering.</p>
					<?php
					echo wp_kses( $options['profile_hidden_fields'],
						array(
							'input' => array(
								'id' => array(),
								'type' => array(),
								'value' => array()
							)
						)
					);
					echo wp_kses_post( $options['auth_modal'] );
					?>
				</td>
			</tr>
		<?php else : ?>
			<tr valign="top">
				<th scope="row"><label for="snapid-enable-one-step-y">Enable One-Step Login</label></th>
				<td>
					<label class="snapid-radio"><input type="radio" id="snapid-enabled-one-step-y" name="snapid_options[one_step_enabled]" value="1" <?php checked( (bool) $options['one_step_enabled'] ); ?> /> Yes</label>
					<label class="snapid-radio"><input type="radio" id="snapid-enabled-one-step-n" name="snapid_options[one_step_enabled]" value="0" <?php checked( !(bool) $options['one_step_enabled'] ); ?> /> No</label>
					<?php $one_step_toggle = (bool) $options['one_step_enabled'] ? 'display: block' : 'display: none'; ?>
					<div class="snapid-roles-wrap" style="<?php echo esc_attr( $one_step_toggle ); ?>">
						<p class="description">Allow SnapID&trade; One-Step Login for the following roles.</p>
						<?php
						foreach( $wp_roles->get_names() as $role ) {
							$role_name = before_last_bar( $role );
							echo '<p><label><input type="checkbox" name="snapid_options[' . esc_attr( strtolower( $role_name ) ) . ']" value="1" ' . checked( $options[strtolower( $role_name )], 1, false ) . '/> ' . esc_html( $role_name ) . '</label></p>';
						}
						?>
					</div>
				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="snapid-enable-two-step-y">Enable Two-Step Login</label></th>
				<td>
					<label class="snapid-radio"><input type="radio" id="snapid-enabled-two-step-y" name="snapid_options[two_step_enabled]" value="1" <?php checked( (bool) $options['two_step_enabled'] ); ?> /> Yes</label>
					<label class="snapid-radio"><input type="radio" id="snapid-enabled-two-step-n" name="snapid_options[two_step_enabled]" value="0" <?php checked( !(bool) $options['two_step_enabled'] ); ?> /> No</label>
					<?php $two_step_toggle = (bool) $options['two_step_enabled'] ? 'display: block' : 'display: none'; ?>
					<div class="snapid-roles-wrap" style="<?php echo esc_attr( $two_step_toggle ); ?>">
						<p class="description">Require SnapID&trade; Two-Step Login for the following roles.</p>
						<?php
						foreach( $wp_roles->get_names() as $role ) {
							$role_name = before_last_bar( $role );
							echo '<p><label><input type="checkbox" name="snapid_options[' . esc_attr( strtolower( $role_name ) ) . ']" value="2" ' . checked( $options[strtolower( $role_name )], 2, false ) . '/> ' . esc_html( $role_name ) . '</label></p>';
						}
						?>
					</div>

					<?php // TODO Refactor these modals into a template and clean it up. ?>

					<div class="modal snapid-modal snapid-example snapid-example-1">
						<a href="#" style="display: none;" class="snapid-prev">&larr; Previous</a>
						<a href="#" class="snapid-next">Next &rarr;</a>
							<img class="snapid-selected" src="<?php echo plugins_url( 'images/examples/one-step-login-1.jpg', dirname( __FILE__ ) ); ?>" />
							<img src="<?php echo plugins_url( 'images/examples/one-step-login-2.jpg', dirname( __FILE__ ) ); ?>" />
					</div>

					<div class="modal snapid-modal snapid-example snapid-example-2">
						<a href="#" style="display: none;" class="snapid-prev">&larr; Previous</a>
						<a href="#" class="snapid-next">Next &rarr;</a>
						<img class="snapid-selected" src="<?php echo plugins_url( 'images/examples/two-step-login-1.jpg', dirname( __FILE__ ) ); ?>" />
						<img src="<?php echo plugins_url( 'images/examples/two-step-login-2.jpg', dirname( __FILE__ ) ); ?>" />
					</div>

				</td>
			</tr>
			<tr valign="top">
				<th scope="row"><label for="snapid-credentials">Site Credentials</label></th>
				<td>
					<?php if ( ! empty( $options['customer_id'] ) ) : ?>
					<p class="snapid-creds"><strong>Customer ID:</strong><?php echo esc_html( $options['customer_id'] ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $options['app_id'] ) ) : ?>
					<p class="snapid-creds"><strong>Application ID:</strong><?php echo esc_html( $options['app_id'] ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $options['app_sub_id'] ) ) : ?>
					<p class="snapid-creds"><strong>Application Sub ID:</strong><?php echo esc_html( $options['app_sub_id'] ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		<?php endif; ?>
	</table>
	<?php
	if ( $options['is_snapid_setup'] ) {
		submit_button();
	}
	?>
</div>
<div class="snapid-videos">
	<h3>Instructional Videos</h3>
	<div class="snapid-video-wrapper">
		<p class="description"><strong>One-Step Login</strong> lets you eliminate usernames and passwords. Use this for ultimate convenience.</p>
		<iframe src="https://player.vimeo.com/video/158143689" width="100%" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>
	</div>
	<div class="snapid-video-wrapper">
		<p class="description"><strong>Two-Step Login</strong> adds two-factor-authentication to your existing WordPress login. Use this for ultimate security.</p>
		<iframe src="https://player.vimeo.com/video/158143690" width="100%" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>
	</div>
</div>
<div class="snapid-toggle-videos" title="Toggle Instructional Videos"><span></span>Videos</div>
<div class="snapid-clearfix"></div>
