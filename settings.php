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
 * pagseguro enrolments plugin settings and presets.
 *
 * @package    enrol
 * @subpackage pagseguro
 * @copyright  2010 Eugene Venter
 * @author     Eugene Venter - based on code by Petr Skoda and others
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    $settings->add(new admin_setting_heading('enrol_pagseguro_settings', '', get_string('pluginname_desc', 'enrol_pagseguro')));

    $settings->add(new admin_setting_configtext('enrol_pagseguro/pagsegurobusiness', get_string('businessemail', 'enrol_pagseguro'), get_string('businessemail_desc', 'enrol_pagseguro'), '', PARAM_EMAIL));

    $settings->add(new admin_setting_configtext('enrol_pagseguro/pagsegurotoken', get_string('businesstoken', 'enrol_pagseguro'), get_string('businesstoken_desc', 'enrol_pagseguro'), '', PARAM_RAW));

    $settings->add(new admin_setting_configcheckbox('enrol_pagseguro/mailstudents', get_string('mailstudents', 'enrol_pagseguro'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_pagseguro/mailteachers', get_string('mailteachers', 'enrol_pagseguro'), '', 0));

    $settings->add(new admin_setting_configcheckbox('enrol_pagseguro/mailadmins', get_string('mailadmins', 'enrol_pagseguro'), '', 0));

    $settings->add(new admin_setting_heading('enrol_pagseguro_defaults',
        get_string('enrolinstancedefaults', 'admin'), get_string('enrolinstancedefaults_desc', 'admin')));

    $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                     ENROL_INSTANCE_DISABLED => get_string('no'));
    $settings->add(new admin_setting_configselect('enrol_pagseguro/status',
        get_string('status', 'enrol_pagseguro'), get_string('status_desc', 'enrol_pagseguro'), ENROL_INSTANCE_DISABLED, $options));

    $settings->add(new admin_setting_configtext('enrol_pagseguro/cost', get_string('cost', 'enrol_pagseguro'), '', 0, PARAM_FLOAT, 4));

    $settings->add(new admin_setting_configtext('enrol_pagseguro/currency', get_string('currency', 'enrol_pagseguro'), get_string('currency_desc', 'enrol_pagseguro'), 'BRL', PARAM_RAW));

    /*$pagsegurocurrencies = array('BRL' => 'Brazilian Real',
    						  'USD' => 'US Dollars',
                              'EUR' => 'Euros',
                              'JPY' => 'Japanese Yen',
                              'GBP' => 'British Pounds',
                              'CAD' => 'Canadian Dollars',
                              'AUD' => 'Australian Dollars'
                             );
    $settings->add(new admin_setting_configselect('enrol_pagseguro/currency', get_string('currency', 'enrol_pagseguro'), '', 'BRL', $pagsegurocurrencies));
	*/
    
    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_pagseguro/roleid',
            get_string('defaultrole', 'enrol_pagseguro'), get_string('defaultrole_desc', 'enrol_pagseguro'), $student->id, $options));
    }

    $settings->add(new admin_setting_configtext('enrol_pagseguro/enrolperiod',
        get_string('enrolperiod', 'enrol_pagseguro'), get_string('enrolperiod_desc', 'enrol_pagseguro'), 0, PARAM_INT));
}
