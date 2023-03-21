<?php
/*
Plugin Name:  GF HubSpot Plugin
Plugin URI:   TBD
Description:  Wordpress plugin that integrates HubSpot with Gravity Forms
Version:      1.0
Author:       Solid Digital
Author URI:   https://www.soliddigital.com
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  gfhubspot
*/

// NOTE: currently does not support "advanced" GF fields.
// NOTE: assumes "required" fields  match in GF and HS.

add_action( 'gform_after_submission', 'gform_after_submission', 10, 2 );

function gform_after_submission($entry, $form) {
    // TODO: pull account info from option.

    $token = 'pat-na1-c8267d93-d087-4933-b545-cd2110ae43a0';
    $account_id = '24231628';
    $form_id = $form['hs_form_id'];

    $context = array(
        // TODO: include tracking script for cookie.
        // 'hutk' => isset($_COOKIE['hubspotutk']) ? $_COOKIE['hubspotutk'] : "",
        'ipAddress' => $entry['ip'],
        'pageUri' => $entry['source_url']
        // Other fields
        // pageName
    );

    // TODO: do we want to add utm fields from the referer.
    $fields = array();
    $consent = array();
    // consent fields
    // 'consentToProcess' => true/false
    // 'subscriptionTypeId' => string
    // 'text' => string

    foreach ( $form['fields'] as $field ) {
        error_log("type: ".$field->type);

        $config_raw = $field->type === 'hidden' ? $field->label : $field->cssClass;
        //error_log("config_raw: ".$config_raw);

        $hsfield_name = false;
        $type = false;
        if (property_exists($field, 'hsfieldField') && $field->hsfieldField) {
            $hsfield_name = $field->hsfieldField;
            $type = 'field';
        }

        if (!$type) { continue; }

        $value = rgar($entry, (string) $field->id);

        $fields[] = array(
            'name' => $hsfield_name,
            'value' => $value
        );
    }

    error_log("formid: ".$form_id);
    if ($form_id === "") return;

    $body = [
        'context' => $context,
        'fields' => $fields,
    ];

    $endpoint = "https://api.hsforms.com/submissions/v3/integration/secure/submit/$account_id/$form_id";

    $response = wp_remote_post($endpoint, array(
        'body' => wp_json_encode($body),
        'headers' => array(
            "Content-Type" => "application/json",
            "Authorization" => "Bearer {$token}"
        )
    ));

    error_log(print_r($response, true));
}

function get_url_params() {
    $referrer_params = $_SERVER['HTTP_REFERER'];
    $referrer_params = explode("?", $referrer_params)[1];
    $referrer_params = explode("&", $referrer_params);

    $param_array = [];
    foreach($referrer_params as $param) {
        $param = explode('=', $param);
        $param_array[$param[0]] = $param[1];
    }
    $referrer_params = $param_array;

    return $referrer_params;

}

add_action( 'gform_field_standard_settings', 'my_standard_settings', 10, 2 );
function my_standard_settings( $position, $form_id ) {

    // $position is where on settings are the field is displayed - see form_detail.php in the GF plugin - search for gform_field_standard_settings
    if ( $position == 5 ) {
        // the setting name (hsfield) has to match the name in the other callbacks
        ?>
        <li class="hsfield_setting field_setting">
            <input type="text" id="field_hsfield_value" onchange="SetFieldProperty('hsfieldField', this.value);" />
            <label for="field_hsfield_value" style="display:inline;">
                <?php _e("HubSpot Field Name", "your_text_domain"); ?>
                <?php gform_tooltip("form_field_hsfield_value") ?>
            </label>
        </li>
        <?php
    }
}
//Action to inject supporting script to the form editor page
add_action( 'gform_editor_js', 'editor_script' );
function editor_script(){
    ?>
    <script type='text/javascript'>
        //adding setting to fields of type "text"
        fieldSettings.email += ', .hsfield_setting';
        fieldSettings.hidden += ', .hsfield_setting';
        fieldSettings.text += ', .hsfield_setting';
        fieldSettings.textarea += ', .hsfield_setting';
        //binding to the load field settings event to initialize the text field
        jQuery(document).on('gform_load_field_settings', function(event, field, form){
            jQuery( '#field_hsfield_value' ).val( field['hsfieldField'] );
        });
    </script>
    <?php
}
//Filter to add a new tooltip
add_filter( 'gform_tooltips', 'add_encryption_tooltips' );
function add_encryption_tooltips( $tooltips ) {
    $tooltips['form_field_hsfield_value'] = "<h6>HubSpot Field Name</h6>Enter the field name used in HubSpot";
    return $tooltips;
}

add_filter( 'gform_form_settings_fields', function ( $fields, $form ) {
    $fields['form_options']['fields'][] = array(
        'type' => 'text',
        'name' => 'hs_form_id',
        'label' => 'HubSpot Form ID'
    );

    return $fields;
}, 10, 2 );
