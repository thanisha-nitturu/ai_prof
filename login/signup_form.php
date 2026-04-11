<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * User sign-up form.
 *
 * @package    core
 * @subpackage auth
 * @copyright  1999 onwards Martin Dougiamas  http://dougiamas.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/user/profile/lib.php');
require_once($CFG->dirroot . '/user/editlib.php');
require_once('lib.php');

class login_signup_form extends moodleform implements renderable, templatable {
    function definition() {
        global $USER, $CFG ,$OUTPUT, $SITE;

        $mform = $this->_form;

        // --- 1. DYNAMIC LOGO INJECTION ---
        // This asks Moodle: "Do we have a logo?" If yes, display it.
        $logourl = $OUTPUT->get_logo_url();
        if ($logourl) {
            $logohtml = '<div class="login-logo text-center mb-4">
                            <img src="' . $logourl . '" class="img-fluid" alt="' . $SITE->fullname . '">
                         </div>';
            $mform->addElement('html', $logohtml);
        } else {
            // Fallback: Just show the Site Name if no image exists
            $mform->addElement('html', '<h2 class="login-heading text-center mb-4">' . $SITE->fullname . '</h2>');
        }
        $mform->addElement('text', 'email', get_string('email'), 'maxlength="100" size="25"');
        $mform->setType('email', core_user::get_property_type('email'));
        $mform->addRule('email', get_string('missingemail'), 'required', null, 'client');
        $mform->setForceLtr('email');

        if (!empty($CFG->passwordpolicy)){
            $mform->addElement('static', 'passwordpolicyinfo', '', print_password_policy());
        }
        $mform->addElement('password', 'password', get_string('password'), [
            'maxlength' => MAX_PASSWORD_CHARACTERS,
            'size' => 12,
            'autocomplete' => 'new-password'
        ]);
        $mform->setType('password', core_user::get_property_type('password'));
        $mform->addRule('password', get_string('missingpassword'), 'required', null, 'client');
        $mform->addRule('password', get_string('maximumchars', '', MAX_PASSWORD_CHARACTERS),
            'maxlength', MAX_PASSWORD_CHARACTERS, 'client');
            
            
        // --- Customization: Password Confirmation Field ---
        $mform->addElement('password', 'passwordcheck', 'Confirm password', ['maxlength' => MAX_PASSWORD_CHARACTERS, 'size' => 12]);
        $mform->setType('passwordcheck', core_user::get_property_type('password'));
        $mform->addRule('passwordcheck', get_string('missingpassword'), 'required', null, 'client');
        
        // This rule forces the client-side browser to check if they match
        //$mform->addRule(array('password', 'passwordcheck'), get_string('passwordsdiffer'), 'compare', null, 'client');

        
//      $mform->addElement('text', 'email2', get_string('emailagain'), 'maxlength="100" size="25"');
//      $mform->setType('email2', core_user::get_property_type('email'));
//      $mform->addRule('email2', get_string('missingemail'), 'required', null, 'client');
//      $mform->setForceLtr('email2');

        // --- FIX: Add email2 as a hidden field so Moodle doesn't break ---
        $mform->addElement('hidden', 'email2', '');
        $mform->setType('email2', core_user::get_property_type('email'));
        
        // This Javascript ensures that whatever the user types in 'email' 
        // gets copied into our hidden 'email2' field.
        $js = "
            <script>
                document.getElementById('id_email').addEventListener('input', function() {
                    document.getElementById('id_email2').value = this.value;
                });
            </script>
        ";
        $mform->addElement('html', $js);
          
        
        $namefields = useredit_get_required_name_fields();
        foreach ($namefields as $field) {
            $mform->addElement('text', $field, get_string($field), 'maxlength="100" size="30"');
            $mform->setType($field, core_user::get_property_type('firstname'));
            $stringid = 'missing' . $field;
            if (!get_string_manager()->string_exists($stringid, 'moodle')) {
                $stringid = 'required';
            }
            $mform->addRule($field, get_string($stringid), 'required', null, 'client');
        }

        $mform->addElement('text', 'username', get_string('username'), 'maxlength="100" size="12" autocapitalize="none"');
        $mform->setType('username', PARAM_RAW);
        $mform->addRule('username', get_string('missingusername'), 'required', null, 'client');

        // $mform->addElement('text', 'city', get_string('city'), 'maxlength="120" size="20"');
        // $mform->setType('city', core_user::get_property_type('city'));
        // if (!empty($CFG->defaultcity)) {
        //     $mform->setDefault('city', $CFG->defaultcity);
        // }

        // $country = get_string_manager()->get_list_of_countries();
        // $default_country[''] = get_string('selectacountry');
        // $country = array_merge($default_country, $country);
        // $mform->addElement('select', 'country', get_string('country'), $country);

        // if( !empty($CFG->country) ){
        //     $mform->setDefault('country', $CFG->country);
        // }else{
        //     $mform->setDefault('country', '');
        // }

        profile_signup_fields($mform);

        if (signup_captcha_enabled()) {
            $mform->addElement('recaptcha', 'recaptcha_element', get_string('security_question', 'auth'));
            $mform->addHelpButton('recaptcha_element', 'recaptcha', 'auth');
            $mform->closeHeaderBefore('recaptcha_element');
        }

        // Hook for plugins to extend form definition.
        core_login_extend_signup_form($mform);

        // Add "Agree to sitepolicy" controls. By default it is a link to the policy text and a checkbox but
        // it can be implemented differently in custom sitepolicy handlers.
        $manager = new \core_privacy\local\sitepolicy\manager();
        $manager->signup_form($mform);

        // Add JavaScript to restructure the form for side-by-side layout
        $js = "
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Wait a bit for Moodle to fully render the form
            setTimeout(function() {
                var form = document.querySelector('#page-login-signup .signupform');
                if (!form) return;
                
                // 1. Create password row container
                var passwordField = document.getElementById('fitem_id_password');
                var confirmField = document.getElementById('fitem_id_passwordcheck');
                
                if (passwordField && confirmField) {
                    var passwordRow = document.createElement('div');
                    passwordRow.className = 'password-row-container';
                    
                    // Insert the container before password field
                    passwordField.parentNode.insertBefore(passwordRow, passwordField);
                    
                    // Move both fields into the container
                    passwordRow.appendChild(passwordField);
                    passwordRow.appendChild(confirmField);
                }
                
                // 2. Create name row container
                var firstnameField = document.getElementById('fitem_id_firstname');
                var lastnameField = document.getElementById('fitem_id_lastname');
                
                if (firstnameField && lastnameField) {
                    var nameRow = document.createElement('div');
                    nameRow.className = 'name-row-container';
                    
                    // Insert the container before firstname field
                    firstnameField.parentNode.insertBefore(nameRow, firstnameField);
                    
                    // Move both fields into the container
                    nameRow.appendChild(firstnameField);
                    nameRow.appendChild(lastnameField);
                }
                
            }, 100);
        });
        </script>
        ";
        $mform->addElement('html', $js);

        // buttons
        $this->set_display_vertical();
        $this->add_action_buttons(true, get_string('createaccount'));

    }

    function definition_after_data(){
        $mform = $this->_form;
        $mform->applyFilter('username', 'trim');

        // Trim required name fields.
        foreach (useredit_get_required_name_fields() as $field) {
            $mform->applyFilter($field, 'trim');
        }
    }

    /**
     * Validate user supplied data on the signup form.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    // public function validation($data, $files) {
    //     $errors = parent::validation($data, $files);

    //     // Extend validation for any form extensions from plugins.
    //     // $errors = array_merge($errors, core_login_validate_extend_signup_form($data));
  
    //     if ($data['password'] !== $data['passwordcheck']) {
    //         $errors['passwordcheck'] = get_string('passwordsdiffer');
    //     }
    //     // ------------------------------------------------

    //     // Extend validation for any form extensions from plugins.
    //     $errors = array_merge($errors, core_login_validate_extend_signup_form($data));

    //     if (signup_captcha_enabled()) {
    //         $recaptchaelement = $this->_form->getElement('recaptcha_element');
    //         if (!empty($this->_form->_submitValues['g-recaptcha-response'])) {
    //             $response = $this->_form->_submitValues['g-recaptcha-response'];
    //             if (!$recaptchaelement->verify($response)) {
    //                 $errors['recaptcha_element'] = get_string('incorrectpleasetryagain', 'auth');
    //             }
    //         } else {
    //             $errors['recaptcha_element'] = get_string('missingrecaptchachallengefield');
    //         }
    //     }

    //     $errors += signup_validate_data($data, $files);

    //     return $errors;
    // }
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // 1. Custom Password Check
        if ($data['password'] !== $data['passwordcheck']) {
            $errors['passwordcheck'] = get_string('passwordsdiffer');
        }

        // 2. Run standard Moodle validation
        $errors += signup_validate_data($data, $files);

        // 3. THE FIX: Force 'email2' to be valid.
        // Since the field is hidden, we don't care if it doesn't match. 
        // We remove any error Moodle raised for it.
        if (isset($errors['email2'])) {
            unset($errors['email2']);
        }

        if (signup_captcha_enabled()) {
            $recaptchaelement = $this->_form->getElement('recaptcha_element');
            if (!empty($this->_form->_submitValues['g-recaptcha-response'])) {
                $response = $this->_form->_submitValues['g-recaptcha-response'];
                if (!$recaptchaelement->verify($response)) {
                    $errors['recaptcha_element'] = get_string('incorrectpleasetryagain', 'auth');
                }
            } else {
                $errors['recaptcha_element'] = get_string('missingrecaptchachallengefield');
            }
        }

        return $errors;
    }
    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output Used to do a final render of any components that need to be rendered for export.
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        ob_start();
        $this->display();
        $formhtml = ob_get_contents();
        ob_end_clean();
        $context = [
            'formhtml' => $formhtml
        ];
        return $context;
    }
}
