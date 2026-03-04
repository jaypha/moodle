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

namespace core\task;

use core\exception\moodle_exception;

/**
 * Simple task to run the regular completion cron. Will process any completion criteria that have
 * been met since the last run.
 *
 * @package    core
 * @copyright  2015 Josh Willcock
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
class completion_regular_task extends scheduled_task {
    use completion_regular_task_trait;

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
        global $CFG;
        if ($CFG->enablecompletion) {

            // We only want completions performed since the last task run. We need the start time so there are no
            // gaps.
            $laststarttime = get_config('core', 'last_completion_regular_task_run');
            if ($laststarttime) {
                // We prefer to get the actual start time for the previous run, which we can get from the config, if
                // available. We subtract 1, just to be sure there are no gaps.
                $timefrom = $laststarttime - 1;
            } else {
                // We don't know the start time. So set the time to an hour before the last run (finish time) to
                // minimise the chance of a gap.
                $timefrom = $this->get_last_run_time() - HOURSECS;
            }
            // This would only happen when there are no previous runs.
            if ($timefrom <= 0) {
                $timefrom = null;
            }

            $this->perform_regular_completion(['timefrom' => $timefrom]);
            aggregate_completions(0, true);

            set_config('last_completion_regular_task_run', $this->get_timestarted());
        }
    }
}
