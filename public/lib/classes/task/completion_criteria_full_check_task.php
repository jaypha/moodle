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
 *  - constraints: array of constraints (optional)
 *  - mtraceprogress: bool (optional)
 *  - supressmessages: bool (optional)
 *
 * @package   core
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_criteria_full_check_task extends adhoc_task {
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

        $data = $this->get_custom_data();

        if (!empty($CFG->enablecompletion)) {
            require_once($CFG->libdir . '/completionlib.php');

            $constraints = [];
            $mtraceprogress = true;
            $suppressmessages = false;

            if (!empty($data)) {
                if (isset($data->constraints)) {
                    // Accept either array, object or JSON-encoded string.
                    if (is_string($data->constraints)) {
                        $data->constraints = json_decode($data->constraints);
                    }
                    if (is_array($data->constraints)) {
                        $constraints = $data->constraints;
                    } else if (is_object($data->constraints)) {
                        $constraints = (array)$data->constraints;
                    }
                }

                if (isset($data->mtraceprogress)) {
                    $mtraceprogress = (bool)$data->mtraceprogress;
                }

                if (isset($data->suppressmessages)) {
                    $suppressmessages = (bool)$data->suppressmessages;
                }
            }

            if ($suppressmessages) {
                if ($mtraceprogress && debugging()) {
                    mtrace('Suppressing notifications during run.');
                }
                $CFG->noemeailever = 1;
                // Disable all message apis as well (record which ones were enabled, to re-enable afterwards).
                $enabledprocessors = $DB->get_records('message_processors', ['enabled' => 1]);
                $DB->execute('UPDATE {message_processors} SET enabled =  0');
            }

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

            if ($suppressmessages) {
                if ($mtraceprogress && debugging()) {
                    mtrace('Re-enabling notifications.');
                }
                $CFG->noemeailever = 0;
                [$insql, $inparams] = $DB->get_in_or_equal(array_column($enabledprocessors, 'id'));
                $DB->execute('UPDATE {message_processors} SET enabled = 1 WHERE id ' . $insql, $inparams);
            }
        }
    }
}
