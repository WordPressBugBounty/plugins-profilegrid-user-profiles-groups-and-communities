<?php
$dbhandler  = new PM_DBhandler();
$pmrequests = new PM_request();
$license = new Profile_Magic_License();
$textdomain = $this->profile_magic;
$path       =  plugin_dir_url( __FILE__ );
$identifier = 'SETTINGS';


$pg_premium_license_key = $dbhandler->get_global_option_value( 'pg_premium_license_key','' );
    $pg_premium_license_status = $dbhandler->get_global_option_value( 'pg_premium_license_status', '' );
    $pg_premium_license_response = $dbhandler->get_global_option_value( 'pg_premium_license_response', '' );
    $is_any_ext_activated = $license->pg_get_activate_extensions();
    
  //print_r($is_any_ext_activated);
    $key = 'pg_premium';
    $id = $key.'_license_key';
    $response = $key.'_license_response';
    $status = $key.'_license_status';
    $pg_license_key = $dbhandler->get_global_option_value($id,'' );
    $pg_license_response = $dbhandler->get_global_option_value($response,'' );
    $pg_license_status = $dbhandler->get_global_option_value($status,'' );
    $deactivate_license_btn = $key.'_license_deactivate';
    $activate_license_btn = $key.'_license_activate';
    $bundle_id = $dbhandler->get_global_option_value($key.'_license_id','70264' );
                                          
?>


    <form name="pm_license_settings" class="pg-setting-table-main" id="pm_license_settings" method="post">
    <!-----Dialogue Box Starts----->

      <h2 class="pg-setting-tab-content">
        <?php esc_html_e( 'License Settings', 'profilegrid-user-profiles-groups-and-communities' ); ?>
      </h2>
    
      <p><strong>Read about activating licenses <a target="_blank" href="https://profilegrid.co/how-to-activate-profilegrid-licenses">here</a></strong></p>

            
            <table class="form-table">
                <tbody>
                    <tr>
                        <td class="pg-form-table-wrapper" colspan="2">
                            <table class="pg-form-table-setting pg-setting-table widefat">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Name', 'profilegrid-user-profiles-groups-and-communities' );?></th>
                                        <th><?php esc_html_e( 'License Key', 'profilegrid-user-profiles-groups-and-communities' );?></th>
                                        <th><?php esc_html_e( 'Validity', 'profilegrid-user-profiles-groups-and-communities' );?></th>
                                        <th><?php esc_html_e( 'Action', 'profilegrid-user-profiles-groups-and-communities' );?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    
                                    <tr class="<?php esc_attr_e($key);?>">
                                            <td>
                                            <div class="pg-purchase-selector">
                                            <select onchange="pg_on_change_bundle(this.value)">
                                                <option> <?php esc_html_e( 'Select Bundle', 'profilegrid-user-profiles-groups-and-communities' );?></option>
                                                <option value="70264" <?php selected('70264',$bundle_id); ?>><?php esc_html_e('ProfileGrid Premium','profilegrid-user-profiles-groups-and-communities');?></option>
                                                <option value="70261" <?php selected('70261',$bundle_id); ?>><?php esc_html_e('ProfileGrid Premium+','profilegrid-user-profiles-groups-and-communities');?></option>
                                                <option value="70271" <?php selected('70271',$bundle_id); ?>><?php esc_html_e('MetaBundle','profilegrid-user-profiles-groups-and-communities');?></option>
                                                <option value="70269" <?php selected('70269',$bundle_id); ?>><?php esc_html_e('MetaBundle+','profilegrid-user-profiles-groups-and-communities');?></option>
                                            </select>

                                                <span class="pg-tooltips" tooltip="<?php esc_html_e( 'If you have purchased a Bundle, please select the name of the Bundle and enter its license key in the corresponding input box', 'profilegrid-user-profiles-groups-and-communities' );?>" tooltip-position="top"></span>
                                            </div>
                                            </td>
                                            
                                         <td><input id="<?php esc_attr_e($id);?>" name="<?php esc_attr_e($id);?>" type="text" class="regular-text pg-box-wrap pg-license-block" data-prefix="<?php esc_attr_e($bundle_id);?>" data-key="<?php esc_attr_e($key);?>" value="<?php esc_attr_e($pg_license_key); ?>" placeholder="<?php esc_html_e( 'Please Enter License Key', 'profilegrid-user-profiles-groups-and-communities' );?>" /></td>
                    <td>         
                        <span class="license-expire-date" style="padding-bottom:2rem;" >
                            <?php
                            if ( ! empty( $pg_license_response->expires ) && ! empty( $pg_license_status ) && $pg_license_status == 'valid' ) {
                                if( $pg_license_response->expires == 'lifetime' ){
                                    esc_html_e( 'Your License key is activated for lifetime', 'profilegrid-user-profiles-groups-and-communities' );
                                }else{
                                    echo sprintf( esc_html__('Your License Key expires on %s', 'profilegrid-user-profiles-groups-and-communities' ), esc_html(date( 'F d, Y', strtotime( $pg_license_response->expires ) )) );
                                }
                            } else {
                                $expire_date = '';
                            }
                            ?>
                        </span>
                    </td>
                    <td>
                        <span class="<?php esc_attr_e($key);?>-license-status-block">
                            <?php if ( isset( $pg_license_key ) && ! empty( $pg_license_key )) { ?>
                                <?php if ( isset( $pg_license_status ) && $pg_license_status !== false && $pg_license_status == 'valid' ) { ?>
                                    <button type="button" class="button action pg-my-2 pg_license_deactivate" name="<?php esc_attr_e($deactivate_license_btn);?>" id="<?php esc_attr_e($deactivate_license_btn);?>" data-prefix="<?php esc_attr_e($bundle_id);?>" data-key="<?php esc_attr_e($key);?>" value="<?php esc_html_e( 'Deactivate License', 'profilegrid-user-profiles-groups-and-communities' );?>"><?php esc_html_e( 'Deactivate License', 'profilegrid-user-profiles-groups-and-communities' );?></button>
                                <?php } elseif( ! empty( $pg_license_status ) && $pg_license_status == 'invalid' ) { ?>
                                    <button type="button" class="button action pg-my-2 pg_license_activate" name="<?php esc_attr_e($activate_license_btn);?>" id="<?php esc_attr_e($activate_license_btn);?>" data-prefix="<?php esc_attr_e($bundle_id);?>" data-key="<?php esc_attr_e($key);?>" value="<?php esc_html_e( 'Activate License', 'profilegrid-user-profiles-groups-and-communities' );?>"><?php esc_html_e( 'Activate License', 'profilegrid-user-profiles-groups-and-communities' );?></button>
                                <?php }else{ ?>
                                    <button type="button" class="button action pg-my-2 pg_license_activate" name="<?php esc_attr_e($activate_license_btn);?>" id="<?php esc_attr_e($activate_license_btn);?>" data-prefix="<?php esc_attr_e($bundle_id);?>" data-key="<?php esc_attr_e($key);?>" value="<?php esc_html_e( 'Activate License', 'profilegrid-user-profiles-groups-and-communities' );?>" style="<?php if ( empty( $pg_license_key ) ){ echo 'display:none'; } ?>"><?php esc_html_e( 'Activate License', 'profilegrid-user-profiles-groups-and-communities' );?></button>
                                <?php } }else{ ?>
                                    <button type="button" class="button action pg-my-2 pg_license_activate" name="<?php esc_attr_e($activate_license_btn);?>" id="<?php esc_attr_e($activate_license_btn);?>" data-prefix="<?php esc_attr_e($bundle_id);?>" data-key="<?php esc_attr_e($key);?>" value="<?php esc_html_e( 'Activate License', 'profilegrid-user-profiles-groups-and-communities' );?>" style="display:none;"><?php esc_html_e( 'Activate License', 'profilegrid-user-profiles-groups-and-communities' );?></button>
                                <?php } ?>
                        </span>
                    </td>
                                         
                                    </tr>
                                    
                                    <?php if( isset( $is_any_ext_activated ) && !empty($is_any_ext_activated ) ) {
                                        foreach($is_any_ext_activated as $key=>$product):
                                        if(empty($product) || $product[0]=='')
                                        {
                                            continue;
                                        }
                                        //echo $key;die;
                                          $id = $key.'_license_key';
                                          $response = $key.'_license_response';
                                          $status = $key.'_license_status';
                                          $pg_license_key = $dbhandler->get_global_option_value($id,'' );
                                          $pg_license_response = $dbhandler->get_global_option_value($response,'' );
                                          $pg_license_status = $dbhandler->get_global_option_value($status,'' );
                                          $deactivate_license_btn = $key.'_license_deactivate';
                                          $activate_license_btn = $key.'_license_activate';
                                        ?>
                                    
                                            <tr valign="top" class="<?php esc_attr_e($key);?>">
                    <td><?php esc_html_e( $product[1], 'profilegrid-user-profiles-groups-and-communities' );?></td>
                    <td><input id="<?php esc_attr_e($id);?>" name="<?php esc_attr_e($id);?>" type="text" class="regular-text pg-box-wrap pg-license-block" data-prefix="<?php esc_attr_e($product[0]);?>" data-key="<?php esc_attr_e($key);?>" value="<?php esc_attr_e($pg_license_key); ?>" placeholder="<?php esc_html_e( 'Please Enter License Key', 'profilegrid-user-profiles-groups-and-communities' );?>" /></td>
                    <td>         
                        <span class="license-expire-date" style="padding-bottom:2rem;" >
                            <?php
                            if ( ! empty( $pg_license_response->expires ) && ! empty( $pg_license_status ) && $pg_license_status == 'valid' ) {
                                if( $pg_license_response->expires == 'lifetime' ){
                                    esc_html_e( 'Your License key is activated for lifetime', 'profilegrid-user-profiles-groups-and-communities' );
                                }else{
                                    echo sprintf( esc_html__('Your License Key expires on %s', 'profilegrid-user-profiles-groups-and-communities' ), esc_html(date( 'F d, Y', strtotime( $pg_license_response->expires ) )) );
                                }
                            } else {
                                $expire_date = '';
                            }
                            ?>
                        </span>
                    </td>
                    <td>
                        <span class="<?php esc_attr_e($key);?>-license-status-block">
                            <?php if ( isset( $pg_license_key ) && ! empty( $pg_license_key )) { ?>
                                <?php if ( isset( $pg_license_status ) && $pg_license_status !== false && $pg_license_status == 'valid' ) { ?>
                                    <button type="button" class="button action pg-my-2 pg_license_deactivate" name="<?php esc_attr_e($deactivate_license_btn);?>" id="<?php esc_attr_e($deactivate_license_btn);?>" data-prefix="<?php esc_attr_e($product[0]); ?>" data-key="<?php esc_attr_e($key);?>" value="<?php esc_html_e( 'Deactivate License', 'profilegrid-user-profiles-groups-and-communities' );?>"><?php esc_html_e( 'Deactivate License', 'profilegrid-user-profiles-groups-and-communities' );?></button>
                                <?php } elseif( ! empty( $pg_license_status ) && $pg_license_status == 'invalid' ) { ?>
                                    <button type="button" class="button action pg-my-2 pg_license_activate" name="<?php esc_attr_e($activate_license_btn);?>" id="<?php esc_attr_e($activate_license_btn);?>" data-prefix="<?php esc_attr_e($product[0]); ?>" data-key="<?php esc_attr_e($key);?>" value="<?php esc_html_e( 'Activate License', 'profilegrid-user-profiles-groups-and-communities' );?>"><?php esc_html_e( 'Activate License', 'profilegrid-user-profiles-groups-and-communities' );?></button>
                                <?php }else{ ?>
                                    <button type="button" class="button action pg-my-2 pg_license_activate" name="<?php esc_attr_e($activate_license_btn);?>" id="<?php esc_attr_e($activate_license_btn);?>" data-prefix="<?php esc_attr_e($product[0]); ?>" data-key="<?php esc_attr_e($key);?>" value="<?php esc_html_e( 'Activate License', 'profilegrid-user-profiles-groups-and-communities' );?>" style="<?php if ( empty( $pg_license_key ) ){ echo 'display:none'; } ?>"><?php esc_html_e( 'Activate License', 'profilegrid-user-profiles-groups-and-communities' );?></button>
                                <?php } }else{ ?>
                                    <button type="button" class="button action pg-my-2 pg_license_activate" name="<?php esc_attr_e($activate_license_btn);?>" id="<?php esc_attr_e($activate_license_btn);?>" data-prefix="<?php esc_attr_e($product[0]); ?>" data-key="<?php esc_attr_e($key);?>" value="<?php esc_html_e( 'Activate License', 'profilegrid-user-profiles-groups-and-communities' );?>" style="display:none;"><?php esc_html_e( 'Activate License', 'profilegrid-user-profiles-groups-and-communities' );?></button>
                                <?php } ?>
                        </span>
                    </td>
                </tr>
         
                                    
                                    <?php endforeach; } ?>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                </tbody>
            </table>
        
        
  </form>


        <div class="pg-status-update-model pg-status-success-model" id="pg-extension-license-status">
            <div class="pg-notification-overlay"></div>
            <div class="pg-modal-wrap-toast">
                <div class="pg-modal-container rm-dbfl">
                    <div class="pg-status-close" onclick="pg_close_toast()">×</div>
                    <div class="pg-dbfl pg-status-box-row pg-status-update-body" id="pg-extension-license-message">
                   
                    </div>
                </div>
            </div>
        </div>

  

<!-- comment

<div id="pg-setting-popup" class="pg-setting-modal-view" style="display: none;">
        <div class="pg-setting-modal-overlay pg-setting-popup-overlay-fade-in"></div>
        <div class="pg-setting-modal-wrap pg-setting-popup-out">
            <div class="pg-setting-modal-titlebar">
                <span class="pg-setting-modal-close">×</span>
            </div>
            <div class="pg-setting-container">
                <div class="pg-extension-wrap" id="pg-license-wrapper">
                    
                </div>
            </div>
            
        </div>
</div>

 -->
 
 <style>
  .pg-tooltip {
    border: 1px solid #ccc;
    padding: 5px;
    display: inline-block;
  }
 </style>