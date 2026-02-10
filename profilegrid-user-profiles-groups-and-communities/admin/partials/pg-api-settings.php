<?php
/**
 * APIs / Webhooks settings screen.
 *
 * @package Profile_Magic
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'APIs / Webhooks', 'profilegrid-user-profiles-groups-and-communities' ); ?></h1>

	<form method="post">
		<?php wp_nonce_field( 'pg_api_settings_action', 'pg_api_settings_nonce' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="pg_enable_rest_api"><?php esc_html_e( 'Enable ProfileGrid APIs', 'profilegrid-user-profiles-groups-and-communities' ); ?></label>
				</th>
				<td>
					<input type="checkbox" id="pg_enable_rest_api" name="pg_enable_rest_api" class="pm_toggle" value="1" <?php checked( 1, $api_enabled ); ?> style="display:none;" />
					<label for="pg_enable_rest_api"></label>
					<p class="description">
						<?php esc_html_e( 'When enabled, ProfileGrid REST endpoints become available to external integrations. Disable to hide all endpoints instantly.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<div class="pg-save-rest-api-settings-button">
		<?php submit_button(); ?>
		</div>
	</form>

	<?php if ( $api_enabled ) : ?>
		<hr />
		<h2><?php esc_html_e( 'Endpoint Structure', 'profilegrid-user-profiles-groups-and-communities' ); ?></h2>
		<p>
			<?php esc_html_e( 'All requests are made against the base endpoint:', 'profilegrid-user-profiles-groups-and-communities' ); ?><br />
			<code><?php echo esc_html( $endpoint_base ); ?></code>
		</p>
		<p>
			<?php esc_html_e( 'Append the query parameter', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			<code>?integration=1</code>
			<?php esc_html_e( 'and specify the action you wish to execute:', 'profilegrid-user-profiles-groups-and-communities' ); ?>
		</p>

		<ul class="pg-api-actions">
			<!-- AUTH / TOKEN -->
			<li>
    <strong><?php esc_html_e( 'Get Access Token', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />

    <code><?php echo esc_html( home_url( '/wp-json/profilegrid/v1/token' ) ); ?></code><br />

    <?php esc_html_e(
        'POST body: username, application_password. Returns a short-lived token to be used in PG-Token or Authorization: Bearer header.',
        'profilegrid-user-profiles-groups-and-communities'
    ); ?>
</li>

			<!-- GROUPS: LIST / SINGLE / CREATE / UPDATE / DELETE -->
			<li>
				<strong><?php esc_html_e( 'Get All Groups', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'get_all_groups' ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'Headers: PG-Token or Authorization Bearer; optional query params page, per_page, search.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<li>
				<strong><?php esc_html_e( 'Get Single Group', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'get_single_group', 'group_id' => 123 ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'Headers: PG-Token or Authorization Bearer; provide group_id query parameter.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<li>
				<strong><?php esc_html_e( 'Create Group', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'create_group' ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'POST body: group_name (required) plus optional group properties.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<li>
				<strong><?php esc_html_e( 'Update Group', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'update_group', 'group_id' => 123 ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'POST body: group_id and any fields to update (partial update).', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<li>
				<strong><?php esc_html_e( 'Delete Group', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'delete_group', 'group_id' => 123 ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'POST body/query: group_id; optional force=1 for hard delete, otherwise soft delete.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<!-- GROUP MEMBERS -->
			<li>
				<strong><?php esc_html_e( 'Get Group Members', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'get_group_members', 'group_id' => 123 ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'Headers: PG-Token or Authorization Bearer; query params: group_id, page, per_page, search, status, role.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<li>
				<strong><?php esc_html_e( 'Add Group Members', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'add_group_members' ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'POST body: group_id plus user_ids (array/int) and/or user_emails (array) to add.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<li>
				<strong><?php esc_html_e( 'Remove Group Member', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'remove_group_member' ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'POST body: group_id and user_id to remove from that group.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<li>
				<strong><?php esc_html_e( 'Assign Group', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'assign_group' ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'POST body: group_id and user_id. Adds the user to the group or returns already_member.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<li>
				<strong><?php esc_html_e( 'Remove From Group', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'remove_from_group' ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'POST body: group_id and user_id. Removes the user from the group.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<!-- GROUP SECTIONS & FIELDS -->
			<li>
				<strong><?php esc_html_e( 'Get Group Sections', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'get_group_section', 'group_id' => 123 ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'Headers: PG-Token or Authorization Bearer; query/body: group_id. Returns all sections and their fields.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<li>
				<strong><?php esc_html_e( 'Create Group Section', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'create_group_section' ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'POST body: group_id, section_name, optional ordering, section_desc, section_options.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<li>
				<strong><?php esc_html_e( 'Update Group Section', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'update_group_section' ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'POST body: group_id, section_id and any section fields to update.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<li>
				<strong><?php esc_html_e( 'Delete Section', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'delete_section' ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'POST body: group_id and section_id. Deletes the section and its fields.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<li>
				<strong><?php esc_html_e( 'Create Group Field', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'create_group_field' ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'POST body: group_id, section_id, field_label, field_type and optional field options/settings.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<!-- USERS: LIST & DETAILS & PROFILE UPDATE -->
			<li>
				<strong><?php esc_html_e( 'Get Users', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'get_users' ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'Headers: PG-Token or Authorization Bearer; optional query: page, per_page, role, status, group_id, search.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<li>
				<strong><?php esc_html_e( 'Get User Details', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'get_user_details', 'user_id' => 123 ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'Headers: PG-Token or Authorization Bearer; query/body: user_id. Returns groups, status, profile URL and more.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<li>
				<strong><?php esc_html_e( 'Update User Profile Fields', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'update_user_profile' ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'POST body: user_id and fields (array) keyed by field_key or field_id with values to store.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<!-- USER ACTIVATION / DEACTIVATION (SINGLE) -->
			<li>
				<strong><?php esc_html_e( 'Activate User Account', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'activate_user_account' ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'POST body: user_id. Sets rm_user_status=0 and notifies relevant groups.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<li>
				<strong><?php esc_html_e( 'Deactivate User Account', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'deactivate_user_account' ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'POST body: user_id. Sets rm_user_status=1 and notifies relevant groups.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<!-- USER ACTIVATION / DEACTIVATION (BULK) -->
			<li>
				<strong><?php esc_html_e( 'Activate All Users (non-admin)', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'activate_all_user' ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'POST: No additional body required. Activates all non-admins and reports counts.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<li>
				<strong><?php esc_html_e( 'Deactivate All Users (non-admin)', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'deactivate_all_user' ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'POST: No additional body required. Deactivates all non-admins and reports counts.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<!-- MEMBERSHIP REQUESTS -->
			<li>
				<strong><?php esc_html_e( 'Get Membership Requests', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'get_membership_requests' ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'Headers: PG-Token or Authorization Bearer; optional query: group_id, user_id, status, search, page, per_page.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<li>
				<strong><?php esc_html_e( 'Create Membership Request', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'create_membership_request' ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'POST body: group_id, user_id and optional message. Creates a pending join request.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<li>
				<strong><?php esc_html_e( 'Approve Membership Request', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'approve_membership_request', 'request_id' => 456 ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'POST/query: request_id. Adds user to the group and removes the request.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<li>
				<strong><?php esc_html_e( 'Deny Membership Request', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'deny_membership_request', 'request_id' => 456 ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'POST/query: request_id; optional reason. Removes the request and notifies user.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<li>
				<strong><?php esc_html_e( 'Bulk Approve All Membership Requests (by Group)', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'bulk_approve_all_membership_requests' ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'POST body: group_id. Approves all pending requests for that group.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>

			<li>
				<strong><?php esc_html_e( 'Bulk Deny All Membership Requests (by Group)', 'profilegrid-user-profiles-groups-and-communities' ); ?></strong><br />
				<code><?php echo esc_html( add_query_arg( array( 'integration' => 1, 'action' => 'bulk_deny_all_membership_requests' ), $endpoint_base ) ); ?></code><br />
				<?php esc_html_e( 'POST body: group_id and optional reason. Denies all pending requests for that group.', 'profilegrid-user-profiles-groups-and-communities' ); ?>
			</li>
		</ul>
	<?php endif; ?>
</div>

<style>
.pg-api-actions li {
	margin-bottom: 1em;
}
.pg-save-rest-api-settings-button {
	margin-bottom: 20px;
}
.pg-save-rest-api-settings-button {
	margin: 15px 0 20px 0;
}

/* Target only the Save Changes button in this screen */
.pg-save-rest-api-settings-button .button-primary {
	background: #00bd48 !important;
	border-color: #00bd48 !important;
	box-shadow: none !important;
	color: #fff !important;
}

.pg-save-rest-api-settings-button .button-primary:hover {
	background: #00a63f !important;
	border-color: #009637 !important;
	color: #fff !important;
}

.pg-save-rest-api-settings-button .button-primary:focus {
	outline: 2px solid rgba(0, 182, 79, 0.6);
	outline-offset: 1px;
}
</style>