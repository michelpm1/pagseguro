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
 * Listens for Instant Payment Notification from pagseguro
 *
 * This script waits for Payment notification from pagseguro,
 * then double checks that data by sending it back to pagseguro.
 * If pagseguro verifies this then it sets up the enrolment for that
 * user.
 *
 * @package    enrol
 * @subpackage pagseguro
 * @copyright 2010 Eugene Venter
 * @author     Eugene Venter - based on code by others
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

//header("access-control-allow-origin: https://ws.pagseguro.uol.com.br");
require('../../config.php');
require_once("lib.php");
require_once($CFG->libdir.'/eventslib.php');
require_once($CFG->libdir.'/enrollib.php');

define('COMMERCE_PAGSEGURO_STATUS_AWAITING', 1);
define('COMMERCE_PAGSEGURO_STATUS_IN_ANALYSIS', 2);
define('COMMERCE_PAGSEGURO_STATUS_PAID', 3);
define('COMMERCE_PAGSEGURO_STATUS_AVAILABLE', 4);
define('COMMERCE_PAGSEGURO_STATUS_DISPUTED', 5);
define('COMMERCE_PAGSEGURO_STATUS_REFUNDED', 6);
define('COMMERCE_PAGSEGURO_STATUS_CANCELED', 7);
define('COMMERCE_PAYMENT_STATUS_SUCCESS', 'success');
define('COMMERCE_PAYMENT_STATUS_FAILURE', 'failure') ;
define('COMMERCE_PAYMENT_STATUS_PENDING', 'pending');

$userid       =  $USER->id;
$plugin       =  enrol_get_plugin('pagseguro');
$email        =  $plugin->get_config('pagsegurobusiness');
$token        =  $plugin->get_config('pagsegurotoken');

$error_returnurl   = $CFG->wwwroot.'/enrol/pagseguro/return.php';
$success_returnurl = $CFG->wwwroot.'/enrol/pagseguro/return.php';

$instanceid  = optional_param('instanceid', 0, PARAM_INT); // It is passed to PagSeguro in redirect_url, so always exist.

$plugin_instance = $DB->get_record("enrol", array("id" => $instanceid, "status" => 0));
$courseid     = $plugin_instance->courseid;
$course       = $DB->get_record('course', array('id' => $courseid));
$currency     = $plugin->get_config('currency');
$encoding     = 'UTF-8';
$item_id      = $courseid;
$item_desc    = empty($course->fullname) ? null: $course->fullname;
$item_qty     = (int)1;
$item_cost    = empty($plugin_instance->cost) ? 0.00 : number_format($plugin_instance->cost, 2);
$item_cost    = str_replace(',', '', $item_cost);
$item_amount  = $item_cost;

$redirect_url =  $CFG->wwwroot.'/enrol/pagseguro/process.php?instanceid='.$instanceid;
$submitValue  =  get_string("sendpaymentbutton", "enrol_pagseguro");

$submited = optional_param('usersubmited', 1, PARAM_INT);

$notificationType = optional_param('notificationType', '', PARAM_RAW);
$notificationCode = optional_param('notificationCode', '', PARAM_RAW);

if ($submited) {
    $url = "https://ws.pagseguro.uol.com.br/v2/checkout/?email=" . urlencode($email) . "&token=" . $token;

    $xml = "<?xml version=\"1.0\" encoding=\"$encoding\" standalone=\"yes\"?>
        <checkout>
            <currency>$currency</currency>
            <redirectURL>$redirect_url</redirectURL>
            <items>
                <item>
                    <id>$item_id</id>
                    <description>$item_desc</description>
                    <amount>$item_amount</amount>
                    <quantity>$item_qty</quantity>
                </item>
            </items>
        </checkout>";

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, Array("Content-Type: application/xml; charset=UTF-8"));
    curl_setopt($curl, CURLOPT_POSTFIELDS, trim($xml));
    $xml = curl_exec($curl);

    curl_close($curl);

    if ($xml == 'Unauthorized') {
        // Error=1 Não autorizado.
        $error_returnurl .= "?id={$courseid}&error=1";
        header("Location: $error_returnurl");
        exit;
    }

    $xml = simplexml_load_string($xml);

    if (count($xml->error) > 0) {
        $error_returnurl .= "?id={$courseid}&error=2";
        header("Location: $error_returnurl");
        exit;
    }

    header('Location: https://pagseguro.uol.com.br/v2/checkout/payment.html?code='.$xml->code);
}

// Here is the return from PagSeguro.
if (!empty($notificationCode)) {
    $transaction = null;
    // Sets the web service URL.
    $url = "https://pagseguro.uol.com.br/v2/transactions/notifications/" . $notificationCode . "?email=".$email."&token=".$token;

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $transaction = curl_exec($curl);
    curl_close($curl);

    if ($transaction == 'Unauthorized'){
        // Error=1 Não autorizado.
        $error_returnurl .= "?id={$courseid}&error=1";
        header("Location: $error_returnurl");
        exit;//Mantenha essa linha
    } else {
        $transaction_data  = serialize(trim($transaction));
        process_moodle($transaction_data, $instanceid, $courseid);
    }
}

function process_moodle($transaction_data, $instanceid, $cid) {
    global $CFG,$USER,$DB,$course;

    /// Read all the data from pagseguro and get it ready for later;
    /// we expect only valid UTF-8 encoding, it is the responsibility
    /// of user to set it up properly in pagseguro business account,
    /// it is documented in docs wiki.

    $data = new stdClass();
    $a    = new stdClass();

    $transaction = array();

    $plugin = enrol_get_plugin('pagseguro');

    $userid   = (int) isset($USER->id) && !empty($USER->id) ? $USER->id : null;
    $courseid = (int) isset($course->id) && !empty($course->id) ? $course->id : $cid;

    $transaction_xml = unserialize($transaction_data);
    $transaction = json_decode(json_encode(simplexml_load_string($transaction_xml)));

    if($transaction) {
        foreach ($transaction as $trans_key => $trans_value) {
            $trans_key = strtolower($trans_key);
            if(!is_object($trans_value)) {
                $data->$trans_key = $trans_value;
            } else {
                foreach($trans_value as $key => $value) {
                    $key = strtolower($key);
                    if(is_object($value)) {
                        foreach($value as $k => $v) {
                            $k = strtolower($k);
                            $k = $trans_key.'_'.$key.'_'.$k;
                            $data->$k = $v;
                        }
                    } else {
                        $key = $trans_key.'_'.$key;
                        $data->$key = $value;
                    }
                }
            }
        }
    } else {
        return false;
    }

    $data->xmlstring        = trim(htmlentities($transaction_xml));
    $data->business         = $plugin->get_config('pagsegurobusiness');
    $data->receiver_email   = $plugin->get_config('pagsegurobusiness');
    $data->userid           = $userid;
    $data->courseid         = $courseid;
    $data->instanceid       = $instanceid;
    $data->timeupdated      = time();

    if(!isset($data->reference) && empty($data->reference)) {
        $data->reference    = $plugin->get_config('pagsegurobusiness');
    }

    // Get the user and course records.

    if (!$user = $DB->get_record("user", array("id"=>$data->userid))) {
        message_pagseguro_error_to_admin("Not a valid user id", $data);
        return false;
    }

    if (!$course = $DB->get_record("course", array("id"=>$data->courseid))) {
        message_pagseguro_error_to_admin("Not a valid course id", $data);
        return false;
    }

    if (!$context = get_context_instance(CONTEXT_COURSE, $course->id)) {
        message_pagseguro_error_to_admin("Not a valid context id", $data);
        return false;
    }

    if (!$plugin_instance = $DB->get_record("enrol", array("id"=>$data->instanceid, "status"=>0))) {
        message_pagseguro_error_to_admin("Not a valid instance id", $data);
        return false;
    }

    /*
       Transaction Status -
       -- Waiting for Payment - 1
       -- In analysis - 2
       -- PAID - 3
       -- Available - 4
       -- In dispute - 5
       -- Returned - 6
       -- Cancelled - 7
     */

    switch ($data->status) {
        case COMMERCE_PAGSEGURO_STATUS_AWAITING: // Awaiting payment.
            $data->payment_status = COMMERCE_PAYMENT_STATUS_PENDING;
            break;
        case COMMERCE_PAGSEGURO_STATUS_IN_ANALYSIS: // Payment in analysis.
            $data->payment_status = COMMERCE_PAYMENT_STATUS_PENDING;
            break;
        case COMMERCE_PAGSEGURO_STATUS_PAID: // Paid.
            $data->payment_status = COMMERCE_PAYMENT_STATUS_SUCCESS;
            break;
        case COMMERCE_PAGSEGURO_STATUS_AVAILABLE: // Available.
            $data->payment_status = COMMERCE_PAYMENT_STATUS_SUCCESS;
            break;
        case COMMERCE_PAGSEGURO_STATUS_DISPUTED: // Payment disputed.
            $data->payment_status = COMMERCE_PAYMENT_STATUS_FAILURE;
            break;
        case COMMERCE_PAGSEGURO_STATUS_REFUNDED: // Payment refunded.
            $data->payment_status = COMMERCE_PAYMENT_STATUS_FAILURE;
            break;
        case COMMERCE_PAGSEGURO_STATUS_CANCELED: // Payment canceled.
            $data->payment_status = COMMERCE_PAYMENT_STATUS_FAILURE;
            break;
    }

    if (!in_array($data->status, array(COMMERCE_PAGSEGURO_STATUS_IN_ANALYSIS, COMMERCE_PAGSEGURO_STATUS_PAID, COMMERCE_PAGSEGURO_STATUS_AVAILABLE))) {
        $plugin->unenrol_user($plugin_instance, $data->userid);
        message_pagseguro_error_to_admin("Status not completed or pending. User unenrolled from course", $data);
        return false;
    }

    /*if ($existing = $DB->get_record("enrol_pagseguro", array("txn_id"=>$data->txn_id))) {   // Make sure this transaction doesn't exist already
      message_pagseguro_error_to_admin("Transaction $data->txn_id is being repeated!", $data);
      return false;
      }*/

    $coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);

    // Check that amount paid is the correct amount
    if ( (float) $plugin_instance->cost <= 0 ) {
        $cost = (float) $plugin->get_config('cost');
    } else {
        $cost = (float) $plugin_instance->cost;
    }

    if ($data->grossamount < $cost) {
        $cost = format_float($cost, 2);
        message_pagseguro_error_to_admin("Amount paid is not enough ($data->payment_gross < $cost))", $data);
        return false;
    }

    // All clear!
    $DB->insert_record("enrol_pagseguro", $data);

    if ($plugin_instance->enrolperiod) {
        $timestart = time();
        $timeend   = $timestart + $plugin_instance->enrolperiod;
    } else {
        $timestart = 0;
        $timeend   = 0;
    }

    // Enrol user
    $plugin->enrol_user($plugin_instance, $userid, $plugin_instance->roleid, $timestart, $timeend);

    // Pass $view=true to filter hidden caps if the user cannot see them
    if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                '', '', '', '', false, true)) {
        $users = sort_by_roleassignment_authority($users, $context);
        $teacher = array_shift($users);
    } else {
        $teacher = get_admin();
    }

    $mailstudents = $plugin->get_config('mailstudents');
    $mailteachers = $plugin->get_config('mailteachers');
    $mailadmins   = $plugin->get_config('mailadmins');
    $shortname = format_string($course->shortname, true, array('context' => $context));

    if (!empty($mailstudents)) {
        $a->coursename = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";

        $eventdata = new stdClass();
        $eventdata->modulename        = 'moodle';
        $eventdata->component         = 'enrol_pagseguro';
        $eventdata->name              = 'pagseguro_enrolment';
        $eventdata->userfrom          = $teacher;
        $eventdata->userto            = $user;
        $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
        $eventdata->fullmessage       = get_string('welcometocoursetext', '', $a);
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = '';
        $eventdata->smallmessage      = '';
        message_send($eventdata);
    }

    if (!empty($mailteachers)) {
        $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->user = fullname($user);

        $eventdata = new stdClass();
        $eventdata->modulename        = 'moodle';
        $eventdata->component         = 'enrol_pagseguro';
        $eventdata->name              = 'pagseguro_enrolment';
        $eventdata->userfrom          = $user;
        $eventdata->userto            = $teacher;
        $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
        $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml   = '';
        $eventdata->smallmessage      = '';
        message_send($eventdata);
    }

    if (!empty($mailadmins)) {
        $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->user = fullname($user);
        $admins = get_admins();
        foreach ($admins as $admin) {
            $eventdata = new stdClass();
            $eventdata->modulename        = 'moodle';
            $eventdata->component         = 'enrol_pagseguro';
            $eventdata->name              = 'pagseguro_enrolment';
            $eventdata->userfrom          = $user;
            $eventdata->userto            = $admin;
            $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
            $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';

            message_send($eventdata);
        }
    }

    $success_returnurl = $CFG->wwwroot.'/enrol/pagseguro/return.php?id='.$courseid;
    header("Location: $success_returnurl");
}

function message_pagseguro_error_to_admin($subject, $data) {
    $admin = get_admin();
    $site = get_site();

    $message = "$site->fullname:  Transaction failed.\n\n$subject\n\n";

    foreach ($data as $key => $value) {
        $message .= "$key => $value\n";
    }

    $eventdata = new stdClass();
    $eventdata->modulename        = 'moodle';
    $eventdata->component         = 'enrol_pagseguro';
    $eventdata->name              = 'pagseguro_enrolment';
    $eventdata->userfrom          = $admin;
    $eventdata->userto            = $admin;
    $eventdata->subject           = "pagseguro ERROR: ".$subject;
    $eventdata->fullmessage       = $message;
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';
    $eventdata->smallmessage      = '';
    message_send($eventdata);
}
