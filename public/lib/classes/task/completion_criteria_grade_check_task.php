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
 * Ad-hoc task to perform a grade completion criteria check whenever a course is marked as complete for a user.
 *
 * @package   core
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_criteria_grade_check_task extends adhoc_task {
    #[\Override]
    public function get_name() {
        return get_string('taskcompletioncriteriagradecheck', 'admin');
    }

    #[\Override]
    public function execute() {
        global $CFG;

        if (!empty($CFG->enablecompletion)) {
            require_once($CFG->libdir . '/completionlib.php');
            require_once($CFG->dirroot . '/completion/criteria/completion_criteria_grade.php');

            $data = $this->get_custom_data();
            $class = new \completion_criteria_grade();
            $class->cron(courseid: $data->courseid ?? null, userid: $data->userid ?? null);
        }
    }
}
