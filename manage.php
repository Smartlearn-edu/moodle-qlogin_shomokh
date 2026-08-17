<?php
// This file is part of Moodle - https://moodle.org/

/** Verification queue and exemptions. @package local_qlogin_shomokh */
require_once('../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/qlogin_shomokh:manage', $context);
$url = new moodle_url('/local/qlogin_shomokh/manage.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title(get_string('manage:title', 'local_qlogin_shomokh'));
$PAGE->set_heading(format_string($SITE->fullname));

$delete = optional_param('delete', 0, PARAM_INT);
if ($delete) {
    require_sesskey();
    \local_qlogin_shomokh\verification::delete_exemption($delete);
    redirect($url, get_string('manage:deleted', 'local_qlogin_shomokh'));
}
$form = new \local_qlogin_shomokh\form\exemption_form($url);
if ($data = $form->get_data()) {
    $exists = $data->scope === 'user'
        ? $DB->record_exists('user', ['id' => $data->scopeid, 'deleted' => 0])
        : $DB->record_exists('cohort', ['id' => $data->scopeid]);
    if (!$exists) {
        \core\notification::error(get_string('manage:notfound', 'local_qlogin_shomokh'));
    } else {
        \local_qlogin_shomokh\verification::set_exemption($data->scope, (int)$data->scopeid,
            $data->channel, $data->reason, (int)$USER->id);
        if ($data->scope === 'user') {
            \local_qlogin_shomokh\enforcement::release_if_complete((int)$data->scopeid);
        } else {
            foreach ($DB->get_records('cohort_members', ['cohortid' => $data->scopeid]) as $member) {
                \local_qlogin_shomokh\enforcement::release_if_complete((int)$member->userid);
            }
        }
        redirect($url, get_string('manage:saved', 'local_qlogin_shomokh'));
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage:title', 'local_qlogin_shomokh'));
echo $OUTPUT->heading(get_string('manage:queue', 'local_qlogin_shomokh'), 3);
$namefields = array_map(static function(string $field): string {
    return 'u.' . $field;
}, \core_user\fields::for_name()->get_required_fields());
$nameselects = implode(', ', $namefields);
$sql = "SELECT v.id, v.userid, v.channel, v.target, v.state, v.expiresat, $nameselects
          FROM {local_qlogin_shomokh_verify} v
          JOIN {user} u ON u.id = v.userid
         WHERE v.state IN (:pending, :expired)
      ORDER BY v.expiresat ASC";
$records = $DB->get_records_sql($sql, ['pending' => 'pending', 'expired' => 'expired'], 0, 200);
$table = new html_table();
$table->head = [
    get_string('fullname'),
    get_string('manage:channel', 'local_qlogin_shomokh'),
    get_string('manage:target', 'local_qlogin_shomokh'),
    get_string('status'),
    get_string('manage:deadline', 'local_qlogin_shomokh'),
];
foreach ($records as $record) {
    $target = $record->channel === 'email' ? \local_qlogin_shomokh\manager::mask_email($record->target)
        : \local_qlogin_shomokh\manager::mask_phone($record->target);
    $table->data[] = [fullname($record), get_string('channel:' . $record->channel, 'local_qlogin_shomokh'),
        s($target), get_string('state:' . $record->state, 'local_qlogin_shomokh'), userdate($record->expiresat)];
}
echo html_writer::table($table);
echo $OUTPUT->heading(get_string('manage:exemptions', 'local_qlogin_shomokh'), 3);
$form->display();
$table = new html_table();
$table->head = [
    get_string('manage:scope', 'local_qlogin_shomokh'),
    get_string('manage:scopeid', 'local_qlogin_shomokh'),
    get_string('manage:channel', 'local_qlogin_shomokh'),
    get_string('manage:reason', 'local_qlogin_shomokh'),
    get_string('actions'),
];
foreach ($DB->get_records('local_qlogin_shomokh_exempt', [], 'timecreated DESC') as $record) {
    $deleteurl = new moodle_url($url, ['delete' => $record->id, 'sesskey' => sesskey()]);
    $table->data[] = [s($record->scope), $record->scopeid, s($record->channel), s($record->reason),
        $OUTPUT->single_button($deleteurl, get_string('delete'), 'post')];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
