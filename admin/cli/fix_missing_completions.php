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
 * Script to fix any completion records that were not fully processed in the past.
 *
 * Past bugs have left, on occasion, completion records that were not fully processsed.
 * This script will perform a completion criteria check on five criteria: activity, course completion,
 * grade, date and duration.
 *
 * Note that this script does not perform an aggregation. This will be done by the next run of the
 * completion_regular_task scheduled task. If cron is not being used, this task should be run manually.
 * Then completions should be ... complete.
 *
 * @package core
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
define('NO_OUTPUT_BUFFERING', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Get CLI options.
[$options, $unrecognised] = cli_get_params(
    [
        'help' => false,
        'timefrom' => null,
        'courseid' => null,
        'verbose' => false,
        'no-email' => false,
    ],
    [
        'h' => 'help',
        'c' => 'courseid',
    ]
);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL . '  ', $unrecognised);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln(<<<EOT
Perform completion checks to fix any completion records that are not fully processed.

Options:
  -h, --help              Print this help.
  -c, --courseid=ID       Limit the check to a single course.
      --timefrom=TS       Only check records that were updated after this timestamp.
      --verbose           Give extra output.
      --no-email          Do not send out emails for course completion.

Examples:
  # Run a full completion check for all courses.
  \$ php admin/cli/fix_missing_completions.php

  # Run the check for course 42 only.
  \$ php admin/cli/fix_missing_completions.php --courseid=42

EOT);
    exit(0);
}

if (empty($CFG->enablecompletion)) {
    cli_error('Completion is disabled site-wide ($CFG->enablecompletion is off); the task would do nothing.');
}

// Run as admin so the criteria cron has the expected capabilities/context.
\core\session\manager::set_user(get_admin());

// Build the optional constraints from the provided options.
if ($options['courseid'] !== null) {
    $courseid = $options['courseid'];
    if (!ctype_digit($courseid)) {
        cli_error("Invalid courseid value '$courseid'.");
    }
    if (!$DB->record_exists('course', ['id' => $courseid])) {
        cli_error("Course with id $courseid does not exist.");
    }
}
if ($options['timefrom'] !== null) {
    $timefrom = $options['timefrom'];
    if (!ctype_digit($timefrom)) {
        cli_error("Invalid timefrom value '$timefrom'.");
    }
}

// Create a full check task and attach its custom data.
$task = new \core\task\completion_criteria_full_check_task();
$task->set_custom_data((object) [
    'timefrom' => $options['timefrom'],
    'courseid' => $options['courseid'],
    'verbose' => (bool) $options['verbose'],
    'noemail' => (bool) $options['no-email'],
]);

if ($options['verbose']) {
    cli_writeln('Executing completion_criteria_full_check_task.');
}
// Run it right now.
$task->execute();
if ($options['verbose']) {
    cli_writeln('Done.');
}
