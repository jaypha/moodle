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
 * Ad-hoc task wrapper to run a full course completion check with optional constraints.
 *
 * Custom data structure (stdClass) accepted:
 *  - timefrom: int (optional)
 *  - courseid: int (optional)
 *  - verbose: bool (optional)
 *  - noemail: bool (optional)
 *
 * @package   core
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_criteria_full_check_task extends adhoc_task {
    use completion_criteria_check_trait;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskcompletioncriteriafullcheck', 'admin');
    }

    /**
     * Execute the ad-hoc task: extract custom data and delegate to perform_regular_completion.
     *
     * @return void
     */
    public function execute() {
        global $COMPLETION_CRITERIA_TYPES, $CFG, $DB;

        if (!empty($CFG->enablecompletion)) {
            require_once($CFG->libdir . '/completionlib.php');

            // Extract custom data.
            $data = $this->get_custom_data();
            $courseid = $data->courseid ?? null;
            $timefrom = $data->timefrom ?? null;
            $verbose = $data->verbose ?? false;
            $noemail = $data->noemail ?? false;

            if ($noemail) {
                if ($verbose) {
                    mtrace('Suppressing emails during run.');
                }
                $CFG->noemeailever = 1;
                // Disable all message apis as well (record which ones were enabled, to re-enable afterwards).
                $enabledprocessors = $DB->get_records('message_processors', ['enabled' => 1]);
                $DB->execute('UPDATE {message_processors} SET enabled =  0');
            }

            if ($verbose) {
                $constraints = ['timefrom' => $timefrom, 'courseid' => $courseid];
                mtrace('Constraints: ' . json_encode($constraints));
            }

            // Perform each check in turn.

            foreach ($COMPLETION_CRITERIA_TYPES as $type) {
                $object = 'completion_criteria_' . $type;
                require_once($CFG->dirroot . '/completion/criteria/' . $object . '.php');

                $class = new $object();
                if (method_exists($class, 'cron')) {
                    if ($verbose) {
                        mtrace('Running ' . $object . '->cron()');
                    }
                    $class->cron(timefrom: $timefrom, courseid: $courseid);
                    // Set the time for future use of timefrom constraints.
                    $this->update_timefrom($type);
                }
            }

            // Finally, re-enable emails if we disabled them.
            if ($noemail) {
                if ($verbose) {
                    mtrace('Re-enabling emails.');
                }
                $CFG->noemeailever = 0;
                [$insql, $inparams] = $DB->get_in_or_equal(array_column($enabledprocessors, 'id'));
                $DB->execute('UPDATE {message_processors} SET enabled = 1 WHERE id ' . $insql, $inparams);
            }
        }
    }
}
