<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Migration functionality.
 *
 * @package    local_qlogin_shomokh
 * @copyright  2026 Shomokh Al-Elm <support@shomokh.edu.sa>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

require_login();
$context = context_system::instance();
require_capability('local/qlogin_shomokh:manage', $context);
$url = new moodle_url('/local/qlogin_shomokh/migration.php');
$PAGE->set_context($context);
$PAGE->set_url($url);
$PAGE->set_title(get_string('migration:title', 'local_qlogin_shomokh'));
$PAGE->set_heading(format_string($SITE->fullname));
$repairform = new \local_qlogin_shomokh\form\repair_phone_form($url);

if ($repairdata = $repairform->get_data()) {
    try {
        $corrected = \local_qlogin_shomokh\migration::repair_repeated_country_code(
            (int)$repairdata->repairuserid,
            (int)$USER->id
        );
        redirect($url, get_string(
            'migration:repairsuccess',
            'local_qlogin_shomokh',
            \local_qlogin_shomokh\manager::mask_phone($corrected)
        ));
    } catch (moodle_exception $exception) {
        \core\notification::error($exception->getMessage());
    } catch (Throwable $exception) {
        debugging($exception->getMessage(), DEBUG_DEVELOPER);
        \core\notification::error(get_string('migration:repairfailed', 'local_qlogin_shomokh'));
    }
}

$action = optional_param('action', '', PARAM_ALPHA);
$download = optional_param('download', '', PARAM_ALPHA);
if ($download === 'csv') {
    require_sesskey();
    require_once($CFG->libdir . '/csvlib.class.php');
    $export = \local_qlogin_shomokh\migration::scan(PHP_INT_MAX);
    $csv = new csv_export_writer();
    $csv->set_filename('qlogin-shomokh-migration-' . gmdate('Y-m-d'));
    $csv->add_data([
        get_string('migration:userid', 'local_qlogin_shomokh'),
        get_string('fullname'),
        get_string('migration:category', 'local_qlogin_shomokh'),
        get_string('migration:phonealias', 'local_qlogin_shomokh'),
        get_string('migration:source', 'local_qlogin_shomokh'),
        get_string('migration:duplicateemail', 'local_qlogin_shomokh'),
    ]);
    foreach ($export['details'] as $record) {
        $csv->add_data([
            $record->userid,
            $record->fullname,
            get_string('migration:' . $record->category, 'local_qlogin_shomokh'),
            $record->phone === '' ? '' : \local_qlogin_shomokh\manager::mask_phone($record->phone),
            $record->source,
            $record->duplicateemail ? get_string('yes') : get_string('no'),
        ]);
    }
    $csv->download_file();
    exit;
}
if ($action === 'linksafe') {
    require_sesskey();
    $linked = \local_qlogin_shomokh\migration::link_safe((int)$USER->id);
    redirect($url, get_string(
        $linked ? 'migration:linksuccess' : 'migration:nosafe',
        'local_qlogin_shomokh',
        $linked
    ));
}
if ($action === 'trustlegacy') {
    require_sesskey();
    $trusted = \local_qlogin_shomokh\migration::trust_legacy_emails();
    redirect($url, get_string(
        $trusted ? 'migration:trustsuccess' : 'migration:notrustneeded',
        'local_qlogin_shomokh',
        $trusted
    ));
}

$scan = \local_qlogin_shomokh\migration::scan(200);
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('migration:title', 'local_qlogin_shomokh'));
echo html_writer::tag('p', get_string('migration:intro', 'local_qlogin_shomokh'));
echo $OUTPUT->heading(get_string('migration:repairtitle', 'local_qlogin_shomokh'), 3);
echo html_writer::tag('p', get_string('migration:repairintro', 'local_qlogin_shomokh'));
$repairform->display();
echo $OUTPUT->notification(get_string('migration:selfclaiminfo', 'local_qlogin_shomokh'), 'info');
if ((bool)get_config('local_qlogin_shomokh', 'requireemail')) {
    echo $OUTPUT->notification(get_string('migration:trustconfirm', 'local_qlogin_shomokh'), 'warning');
    $trusturl = new moodle_url($url, ['action' => 'trustlegacy', 'sesskey' => sesskey()]);
    echo $OUTPUT->single_button($trusturl, get_string('migration:trustlegacy', 'local_qlogin_shomokh'), 'post');
}
echo $OUTPUT->heading(get_string('migration:summary', 'local_qlogin_shomokh'), 3);
$summarytable = new html_table();
$summarytable->head = [get_string('migration:category', 'local_qlogin_shomokh'), get_string('count')];
foreach (['total', 'phoneusername', 'mapped', 'safe', 'duplicate', 'missing', 'invalid', 'duplicateemail'] as $key) {
    $summarytable->data[] = [get_string('migration:' . $key, 'local_qlogin_shomokh'), $scan['summary'][$key]];
}
echo html_writer::table($summarytable);
$downloadurl = new moodle_url($url, ['download' => 'csv', 'sesskey' => sesskey()]);
echo $OUTPUT->single_button($downloadurl, get_string('migration:downloadcsv', 'local_qlogin_shomokh'), 'get');
if ($scan['summary']['safe'] > 0) {
    echo $OUTPUT->notification(get_string('migration:linkconfirm', 'local_qlogin_shomokh'), 'info');
    $linkurl = new moodle_url($url, ['action' => 'linksafe', 'sesskey' => sesskey()]);
    echo $OUTPUT->single_button($linkurl, get_string('migration:linksafe', 'local_qlogin_shomokh'), 'post');
}

echo html_writer::tag('p', get_string('migration:limited', 'local_qlogin_shomokh', 200));
$table = new html_table();
$table->head = [
    get_string('migration:userid', 'local_qlogin_shomokh'),
    get_string('fullname'),
    get_string('migration:category', 'local_qlogin_shomokh'),
    get_string('migration:phonealias', 'local_qlogin_shomokh'),
    get_string('migration:source', 'local_qlogin_shomokh'),
    get_string('migration:duplicateemail', 'local_qlogin_shomokh'),
];
foreach ($scan['details'] as $record) {
    $table->data[] = [
        html_writer::link(
            new moodle_url('/user/editadvanced.php', ['id' => $record->userid]),
            (string)$record->userid
        ),
        s($record->fullname),
        get_string('migration:' . $record->category, 'local_qlogin_shomokh'),
        $record->phone === '' ? '-' : s(\local_qlogin_shomokh\manager::mask_phone($record->phone)),
        s($record->source ?: '-'),
        $record->duplicateemail ? get_string('yes') : get_string('no'),
    ];
}
echo html_writer::table($table);
echo $OUTPUT->footer();
