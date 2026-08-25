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

/**
 * Task to perform the activity completion criteria check.
 *
 * @package   core
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
class completion_criteria_activity_check_task extends scheduled_task {
    use completion_criteria_check_trait;

    #[\Override]
    public function get_name() {
        return get_string('taskcompletioncriteriaactivitycheck', 'admin');
    }

    #[\Override]
    public function execute() {
        global $CFG;

        if (!empty($CFG->enablecompletion)) {
            require_once($CFG->libdir . '/completionlib.php');
            require_once($CFG->dirroot . '/completion/criteria/completion_criteria_activity.php');

            // We only want completions performed since the last task run. We need the start time so there are no gaps.
            $timefrom = $this->get_timefrom('activity');

            $class = new \completion_criteria_activity();
            $class->cron($timefrom);
            $this->update_timefrom('activity');
        }
    }
}
