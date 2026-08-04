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
 * Task Statistics.
 * Access from Site admin -> Server -> Tasks -> Task statistics
 *
 * @package    core_admin
 * @copyright  2026 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../config.php');
require_once("{$CFG->libdir}/adminlib.php");
require_once("tool/task/lib.php");

use core_admin\reportbuilder\local\systemreports\task_stats;
use core_reportbuilder\system_report_factory;

$url = new \moodle_url('/admin/taskstats.php');
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$strheading = get_string('taskstatistics', 'admin');
$PAGE->set_title($strheading);
$PAGE->set_heading($strheading);

admin_externalpage_setup('taskstats');

$deleteid = optional_param('deleteid', null, PARAM_INT);
$deleteall = optional_param('deleteall', 0, PARAM_INT);
$statid = optional_param('id', null, PARAM_INT);
$download = optional_param('download', false, PARAM_BOOL);
$filter = optional_param('filter', null, PARAM_TEXT);

// Reset all stats.
if ($deleteall) {
    require_sesskey();

    $DB->delete_records(core\task\manager::TABLE_TASKSTATS);

    redirect(
        $url,
        get_string('delete_all_task_stat_notice', 'admin'),
        messagetype: \core\output\notification::NOTIFY_SUCCESS,
    );
}

// Reset one stat.
if ($deleteid) {
    // The initial request just shows the confirmation page; we don't do anything further unless
    // they confirm.
    if (!optional_param('confirm', 0, PARAM_INT)) {
        echo $OUTPUT->header();
        $confirmurl = clone $url;
        $confirmurl->param('confirm', 1);
        $confirmurl->param('deleteid', $deleteid);
        $confirmurl->param('sesskey', sesskey());
        echo $OUTPUT->confirm(
            get_string('confirm_delete_task_stat', 'admin'),
            new single_button(
                $confirmurl,
                get_string('ok'),
            ),
            new single_button(
                $url,
                get_string('cancel'),
                'get'
            )
        );
        echo $OUTPUT->footer();
        exit;
    }

    require_sesskey();

    $DB->delete_records(core\task\manager::TABLE_TASKSTATS, ['id' => $deleteid]);

    redirect(
        $url,
        get_string('delete_task_stat_notice', 'admin'),
        messagetype: \core\output\notification::NOTIFY_SUCCESS,
    );
}

echo $OUTPUT->header();

if ($DB->count_records('task_stats') !== 0) {
    $deleteurl = new moodle_url('/admin/taskstats.php', ['deleteall' => 1]);
    $deletebutton = new \single_button($deleteurl, get_string('deleteall'));
    $deletebutton->add_confirm_action(get_string('confirm_delete_all_task_stat', 'admin'));
    echo $OUTPUT->render($deletebutton);
}

$report = system_report_factory::create(task_stats::class, context_system::instance());

echo $report->output();
echo $OUTPUT->footer();
