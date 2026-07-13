<?php
define('NO_OUTPUT_BUFFERING', true);
require_once(__DIR__ . '/config.php');

$uid = optional_param('uid', 0, PARAM_INT);
$cid = optional_param('cid', 0, PARAM_INT);

if (!$uid) {
    die("Missing uid");
}

$user = $DB->get_record('user', array('id' => $uid), '*', MUST_EXIST);
complete_user_login($user);

if ($cid) {
    redirect(new moodle_url('/mod/coursedashboard/view.php', array('id' => $cid)));
} else {
    redirect(new moodle_url('/'));
}
