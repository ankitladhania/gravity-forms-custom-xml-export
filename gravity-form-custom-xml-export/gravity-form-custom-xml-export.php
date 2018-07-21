<?php
/**
 * Plugin Name:     Gravity forms Custom XML export
 * Plugin URI:      https://github.com/ankitladhania/gravity-forms-custom-xml-export
 * Description:     Writes out selected Gravity Forms entries as XML files
 * Author:          Ankit Ladhania
 * Author URI:      https://www.codementor.io/ankitladhania
 * Text Domain:     gravity-forms-custom-xml-export
 * Domain Path:     /languages
 * Version:         1.0.0
 *
 * @package         GravityFormCustomXMLExport
 */

if ( ! defined( 'WPINC' ) ) {
  die;
}

if (is_admin()) {
  add_filter('gform_export_menu', 'custom_xml_export_menu_item');

  // display content for custom menu item when selected
  add_action('gform_export_page_custom_xml_export', 'custom_xml_export_page');
  add_action('wp_ajax_custom_xml_export_fetch_form_fields', 'custom_xml_export_fetch_form_fields');
  add_action('admin_post_custom_xml_export_export_xml', 'custom_xml_export_export_xml');
}

const WP_OPTION_NAME = 'custom_xml_export_plugin_custom_fields';
const WP_ON_THE_GO_OPTION_NAME = 'custom_xml_export_plugin_on_the_go_fields';

/**
 * Main function that adds the Export menu item
 *
 * @param $menu_items
 * @return array
 */
function custom_xml_export_menu_item( $menu_items ) {

  $menu_items[] = array(
    'name' => 'custom_xml_export',
    'label' => __( 'XML Export' )
  );

  return $menu_items;
}

/**
 * Adds the html template to render the form selection dropdown
 */
function custom_xml_export_page() {

  add_action('admin_footer', 'custom_xml_export_js');

  GFExport::page_header();
  ?>
  <div class="wrap">
    <p class="textleft">Select a form below to export entries. Once you have selected a form you may select the fields you would like to export and then define optional filters for field values and the date range. When you click the download button below, Gravity Forms will create a XML file for you to save to your computer.</p>
    <div class="hr-divider"></div>
    <form id="custom_xml_export_form" method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" >
      <input type="hidden" name="action" value="custom_xml_export_export_xml" />
      <!-- Start of Form selection drop down -->
      <table class="form-table">
        <tr valign="top">

          <th scope="row">
            <label for="export_form"><?php esc_html_e( 'Select A Form', 'gravityforms' ); ?></label> <?php gform_tooltip( 'export_select_form' ) ?>
          </th>
          <td>

            <select id="export_form" name="export_form" onchange="SelectExportForm(jQuery(this).val());">
              <option value=""><?php esc_html_e( 'Select a form', 'gravityforms' ); ?></option>
              <?php
              $forms = RGFormsModel::get_forms( null, 'title' );
              foreach ( $forms as $form ) {
                ?>
                <option value="<?php echo absint( $form->id ) ?>"><?php echo esc_html( $form->title ) ?></option>
                <?php
              }
              ?>
            </select>

          </td>
        </tr>
      </table>
      <!-- End of Form selection drop down -->
      <!-- Start of entry field selector -->
      <div id="select_custom_fields"  style="display: none;">
        <div class="wrap row">
          <h4>Select Fields and their name to export in XML</h4>
        </div>
        <table>
        </table>
      </div>
      <div id="add_custom_field" class="row wrap" style="display: none;">
        <button class="add-new-h2" style="margin-left: 20px;">Add Field</button>

      </div>
      <!-- End of entry field selector -->
      <!-- Start of Custom field like BatchNumber -->
      <div id="add_on_the_go_fields"  style="display: none; margin-top: 20px;">
        <div class="wrap row">
          <h4>Select Fields and their name to export in XML</h4>
        </div>
        <table>
        </table>
      </div>
      <div id="add_on_the_go_fields_button" class="row wrap" style="display: none;">
        <button class="add-new-h2" style="margin-left: 20px;">Add Custom Field</button>
      </div>
      <!-- End of Custom field -->

      <div id="export_custom_field" class="row wrap" style="display: none;">
        <input type="submit" id="submit_button" class="button button-large button-primary alignright" value="Export" />
      </div>
    </form>
  </div>
  <?php

  GFExport::page_footer();

}

/**
 * Adds the script used to render the saved mapping for the fields
 */
function custom_xml_export_js() {
  //print out the sack ajax library
  wp_print_scripts( array( 'sack' ));
  ?>
  <script type="text/javascript" >
    var $fields = null;
    var $field_number = 0;

    /**
     * Called when we select a form in the form selection drop down
     */
    function SelectExportForm(form_id) {

      // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
      var mysack = new sack(ajaxurl);
      mysack.execute = 1;
      mysack.method = 'POST';
      mysack.setVar("action", "custom_xml_export_fetch_form_fields");
      mysack.setVar('form_id', form_id);
      mysack.onError = function () {
        alert(<?php echo json_encode( __( 'Ajax error while selecting a form', 'gravityforms' ) ); ?>)
      };
      mysack.runAJAX();

      return true;
    }


    /**
     * This function is called when the server returns field mapping
     * @param fields
     * @param saved_custom_fields
     * @param saved_on_the_go
     * @constructor
     */
    function SetFields(fields, saved_custom_fields, saved_on_the_go) {
      $fields = fields;
      jQuery('#select_custom_fields table').empty();
      jQuery('#add_on_the_go_fields table').empty();
      jQuery('#select_custom_fields').show();
      jQuery('#add_custom_field').show();
      jQuery('#export_custom_field').show();
      jQuery('#add_on_the_go_fields').show();
      jQuery('#add_on_the_go_fields_button').show();
      if (saved_custom_fields) {
        for (var key in saved_custom_fields) {
          addNewField(saved_custom_fields[key]);
        }
      }

      if (saved_on_the_go) {
        for (var key in saved_on_the_go) {
          addCustomField(saved_on_the_go[key]);
        }
      }
      jQuery(document).off('click').on('click', '#add_custom_field > button', function (e) {
        e.preventDefault();
        addNewField();
      });
      jQuery(document).on('click', '#add_on_the_go_fields_button > button', function (e) {
        e.preventDefault();
        addCustomField();
      });
      jQuery(document).on('click', '#custom_xml_export_form > #submit_button', function (e) {
        if (jQuery('#select_custom_fields > tr').length == 1) {
          e.preventDefault();
          alert('Please add atleast one custom field');
        }
      });
      jQuery(document).on('click', 'a.remove_field_button', function(e){
        e.preventDefault();
        _this = jQuery(this);
        _this.closest('tr').remove();
      });
    }

    /**
     * Returns the template for entry fields drop-down
     * @param i
     * @param selected optional
     * @returns {string}
     */
    function getSelect(i, selected) {
      var str = '<select name="custom_fields['+i+'][0]" style="width: 300px;"><option>Select a Field</option>';

      for(var j =0; j < $fields.length; j++) {
        var field = $fields[j];
        if (selected && selected == field[0]) {
          str += '<option value=' + field[0] + ' selected>' + field[1] + '</option>';
        } else {
          str += '<option value=' + field[0] + '>' + field[1] + '</option>';
        }
      }

      str += '</select>';

      return str;
    }

    /**
     * Adds a new entry field to the form with dropdown and input
     * @param selected_field optional
     */
    function addNewField(selected_field) {
      var str = '<tr>';

      if (!selected_field) {
        selected_field = [];
      }

      str += '<td>' + getSelect($field_number, selected_field[0]) + '</td>';
      if (selected_field[1]) {
        str += '<td><input style="margin-left: 20px;" type="text" name="custom_fields[' + $field_number + '][1]" value="'+selected_field[1]+'" placeholder="Custom Field name" />';
      } else {
        str += '<td><input style="margin-left: 20px;" type="text" name="custom_fields[' + $field_number + '][1]" placeholder="Custom Field name" />';
      }

      str += '<td><a href="#" class="remove_field_button">Remove</a></td>';

      jQuery('#select_custom_fields  > table').append(str);
      $field_number++;
    }


    /**
     * Adds a custom field to the form like BatchNumber
     * @param selected_field
     */
    function addCustomField(selected_field) {
      var str = '<tr>';

      if (selected_field && selected_field[0]) {
        str += '<td style="margin-right: 20px;"><input type="text" name="on_the_go_fields['+$field_number+'][0]" placeholder="Custom field name" value="'+selected_field[0]+'" />';
      } else {
        str += '<td style="margin-right: 20px;"><input type="text" name="on_the_go_fields['+$field_number+'][0]" placeholder="Custom field name" />';
      }

      if (selected_field && selected_field[1]) {
        str += '<td><input type="text" name="on_the_go_fields[' + $field_number + '][1]" placeholder="Field value" value="'+selected_field[1]+'" />';
      } else {
        str += '<td><input type="text" name="on_the_go_fields[' + $field_number + '][1]" placeholder="Field value" />';
      }
      str += '<td><a href="#" class="remove_field_button">Remove</a></td>';

      jQuery('#add_on_the_go_fields > table').append(str);
      $field_number++;
    }
  </script>
  <?php
}

/**
 * API to fetch already saved mapping for entry fields and custom fields
 */
function custom_xml_export_fetch_form_fields() {
  if (is_admin()) {
    $form_id = intval($_POST['form_id']);
    $form    = RGFormsModel::get_form_meta( $form_id );

    $form = gf_apply_filters( array( 'gform_form_export_page', $form_id ), $form );

    $saved_custom_field = get_option(WP_OPTION_NAME.$form_id);
    $saved_on_the_go_fields = get_option(WP_ON_THE_GO_OPTION_NAME.$form_id);


    $filter_settings      = GFCommon::get_field_filter_settings( $form );
    $filter_settings_json = json_encode( $filter_settings );
    $fields               = array();

    $form = GFExport::add_default_export_fields( $form );

    if ( is_array( $form['fields'] ) ) {
      /* @var GF_Field $field */
      foreach ( $form['fields'] as $field ) {
        $inputs = $field->get_entry_inputs();
        if ( is_array( $inputs ) ) {
          foreach ( $inputs as $input ) {
            $fields[] = array( $input['id'], GFCommon::get_label( $field, $input['id'] ) );
          }
        } else if ( ! $field->displayOnly ) {
          $fields[] = array( $field->id, GFCommon::get_label( $field ) );
        }
      }
    }

    $field_json = GFCommon::json_encode( $fields );

    if (empty($saved_custom_field)) {
      $saved_custom_field = 'null';
    }
    if (empty($saved_on_the_go_fields)) {
      $saved_on_the_go_fields = 'null';
    }

    die("SetFields($field_json, $saved_custom_field, $saved_on_the_go_fields)");
  }
}

/**
 * Helper function to convert PHP associative arrays to XML fields
 */
function array_to_xml( $data, &$xml_data ) {
  foreach( $data as $key => $value ) {
    if( is_numeric($key) ){
      $key = 'data';
    }
    if( is_array($value) ) {
      $subnode = $xml_data->addChild($key);
      array_to_xml($value, $subnode);
    } else {
      $xml_data->addChild("$key",htmlspecialchars("$value"));
    }
  }
}


/**
 * Exports the custom fields as XML and
 * saves them for future reference
 */
function custom_xml_export_export_xml() {
  if (is_admin()) {

    $to_return = [];

    $on_the_go_fields = isset($_POST['on_the_go_fields']) ? $_POST['on_the_go_fields'] : [];

    $form_id = intval($_POST['export_form']);
    $form    = RGFormsModel::get_form_meta( $form_id );
    $form_fields = isset($_POST['custom_fields']) ? $_POST['custom_fields'] : [];

    /**
     * Saving the custom fields
     * 1. the fields from entries
     * 2. the custom fields that we add
     */
    update_option(WP_OPTION_NAME.$form_id, json_encode($form_fields));
    update_option(WP_ON_THE_GO_OPTION_NAME.$form_id, json_encode($on_the_go_fields));

    $search_criteria['status']        = 'active';
    $search_criteria['field_filters'] = GFCommon::get_field_filters_from_post( $form );

    $form = GFExport::add_default_export_fields( $form );

    $fields = [];
    foreach ($form_fields as $form_field) {
      $fields[] = $form_field[0];
    }

    $field_rows = GFExport::get_field_row_count( $form, $fields, 0);

    //$sorting = array( 'key' => 'date_created', 'direction' => 'DESC', 'type' => 'info' );
    $sorting = array( 'key' => 'id', 'direction' => 'DESC', 'type' => 'info' );

    $leads = GFAPI::get_entries( $form_id, $search_criteria, $sorting );

    $leads = gf_apply_filters( array( 'gform_leads_before_export', $form_id ), $leads, $form );
    $eway_token_key = '';

    foreach ( $leads as $lead ) {
      GFCommon::log_debug( __METHOD__ . '(): Processing entry #' . $lead['id'] );
      $one_row = [];

      if ( isset($lead['payment_status']) && strtolower($lead['payment_status']) != 'paid') {
        continue;
      }

      foreach ($on_the_go_fields as $on_the_go_field) {
        $one_row[$on_the_go_field[0]] = $on_the_go_field[1];
      }

      foreach ( $form_fields as $form_field ) {
        $field_id = $form_field[0];
        switch ( $field_id ) {
          case 'date_created' :
          case 'payment_date' :
            $value = $lead[ $field_id ];
            if ( $value ) {
              $lead_gmt_time   = mysql2date( 'G', $value );
              $lead_local_time = GFCommon::get_local_timestamp( $lead_gmt_time );
              $value           = date_i18n( 'Y-m-d H:i:s', $lead_local_time, true );
            }
            break;
          default :
            $field = RGFormsModel::get_field( $form, $field_id );

            $value = is_object( $field ) ? $field->get_value_export( $lead, $field_id, false, true ) : rgar( $lead, $field_id );
            $value = apply_filters( 'gform_export_field_value', $value, $form_id, $field_id, $lead );

            //GFCommon::log_debug( "GFExport::start_export(): Value for field ID {$field_id}: {$value}" );
            break;
        }

        if ( isset( $field_rows[ $field_id ] ) ) {
          $list = empty( $value ) ? array() : $value;

          foreach ( $list as $row ) {
            $row_values = array_values( $row );
            $row_str    = implode( '|', $row_values );

            if ( strpos( $row_str, '=' ) === 0 ) {
              // Prevent Excel formulas
              $row_str = "'" . $row_str;
            }

            $value = $row_str;
          }


        } else {
          if ( is_array( $value ) ) {
            $value = implode( '|', $value );
          }

          if ( strpos( $value, '=' ) === 0 ) {
            // Prevent Excel formulas
            $value = "'" . $value;
          }
        }


        $one_row[$form_field[1]] = $value;
      }
      $to_return[] = $one_row;
    }


    $xml_data = new SimpleXMLElement('<?xml version="1.0"?><CustomXML></CustomXML>');

    header('Content-disposition: attachment; filename="custom-xml-export-'.date('m-d-Y', time()).'.xml"');
    header('Content-type: "text/xml"; charset="utf8"');
    array_to_xml($to_return,$xml_data);
    print $xml_data->asXML();

    die();
  }
}
?>