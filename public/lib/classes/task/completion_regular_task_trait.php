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
 * Trait providing shared logic used by the regular completion scheduled task.
 *
 * @package   core
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait completion_regular_task_trait {
    /**
     * Run each completion criteria cron handler (if present) and then run
     * the course completion processing pass.
     *
     * Use this ad-hoc task to perform a ompletion process with special constraints or no constraints for a
     * full pass.
     *
     * Constraints are an array of key/value pairs. Supported keys are:
     * - courseid: int ID of course.
     * - timefrom: int Unix timestamp of earliest time of completions to process.
     *
     * @param array $constraints Constraints to limit what completions to process.
     * @param bool $mtraceprogress Output debug information via mtrace.
     */
    protected function perform_regular_completion(
        array $constraints = [],
        bool $mtraceprogress = true
    ) {
        global $COMPLETION_CRITERIA_TYPES, $CFG;

        if (!empty($CFG->enablecompletion)) {
            require_once($CFG->libdir . '/completionlib.php');

            foreach ($COMPLETION_CRITERIA_TYPES as $type) {
                $object = 'completion_criteria_' . $type;
                require_once($CFG->dirroot . '/completion/criteria/' . $object . '.php');

                $class = new $object();
                if (method_exists($class, 'cron')) {
                    if ($mtraceprogress && debugging()) {
                        mtrace('Running ' . $object . '->cron()');
                    }
                    $class->cron($constraints);
                }
            }
        }
    }
}
