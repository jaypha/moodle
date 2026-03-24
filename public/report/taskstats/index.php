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
 * Basic page for displaying stats.
 *
 * @package report_taskstats
 * @author Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use report_taskstats\task_report_table;
use report_taskstats\lib;

require_once(dirname(__FILE__) . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login(null, false);

$context = context_system::instance();
require_capability('moodle/site:config', $context);
admin_externalpage_setup('report_taskstats');

$download = optional_param('download', '', PARAM_ALPHA);

$url = new moodle_url("/report/taskstats/index.php");

$table = new task_report_table('task_report_table');

$PAGE->set_context($context);
$PAGE->set_url($url);

$pluginname = get_string('pluginname', 'report_taskstats');

$table->is_downloading($download, 'taskstats', 'taskstats');
$table->define_baseurl($url);
$table->make_columns();

if (!$table->is_downloading()) {
    $PAGE->set_title($pluginname);
    $PAGE->set_pagelayout('admin');
    $PAGE->set_heading($pluginname);
    echo $OUTPUT->header();

    if ($DB->count_records(lib::TABLE_TASKSTATS) != 0) {
        $reseturl = new moodle_url("/report/taskstats/reset.php");
        $resetbutton = new \single_button($reseturl, get_string('reset'));
        $resetbutton->add_confirm_action(get_string('resetwarning', 'report_taskstats'));
        echo $OUTPUT->render($resetbutton);
    }
}

$table->out(40, true); // TODO replace with a value from settings.

if (!$table->is_downloading()) {
    echo $OUTPUT->footer();
}
