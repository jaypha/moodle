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
 * A scheduled task.
 *
 * @package    core
 * @copyright  2015 Josh Willcock
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core\task;

use core\exception\moodle_exception;

/**
 * Simple task to run the regular completion cron.
 * @copyright  2015 Josh Willcock
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
class completion_regular_task extends scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskcompletionregular', 'admin');
    }

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {
        global $CFG, $COMPLETION_CRITERIA_TYPES;

        if ($CFG->enablecompletion) {
            require_once($CFG->libdir . "/completionlib.php");

            // Process each criteria type.
            foreach ($COMPLETION_CRITERIA_TYPES as $type) {
                $object = 'completion_criteria_' . $type;
                require_once($CFG->dirroot . '/completion/criteria/' . $object . '.php');

                $class = new $object();
                // Run the criteria type's cron method, if it has one.
                if (method_exists($class, 'cron')) {
                    if (debugging()) {
                        mtrace('Running '.$object.'->cron()');
                    }
                    // We set the time to an hour before the last run to ensure that there are no gaps.
                    $timefrom = $this->get_last_run_time() - HOURSECS;
                    // This would only happen when there are no previous runs.
                    if ($timefrom <= 0) {
                        $timefrom = null;
                    }
                    $class->cron($timefrom);
                }
            }

            aggregate_completions(0, true);
        }
    }
}
