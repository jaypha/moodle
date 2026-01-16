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
 * Simple task to run the regular completion cron. Will process any completion criteria that have
 * been met since the last run.
 *
 * @package    core
 * @copyright  2015 Josh Willcock
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */
class completion_criteria_activity_check_task extends scheduled_task {
    use completion_criteria_check_trait;

    #[\Override]
    public function get_name() {
        return get_string('taskcompletioncriteriaactivitycheck', 'admin');
    }

    #[\Override]
    public function execute() {
        $constraints = [
            'timefrom' => $this->get_timefrom('activity'),
        ];

        $this->execute_inner('activity', $constraints, true);
        set_config('completion_criteria_activity_check_lasttime', $this->get_timestarted());
    }
}
