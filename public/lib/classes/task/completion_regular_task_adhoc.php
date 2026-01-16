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
 * Ad-hoc task wrapper to run regular completion processing with optional constraints.
 *
 * Custom data structure (stdClass) accepted:
 *  - constraints: array of constraints (optional)
 *  - mtraceprogress: bool (optional)
 *
 * @package   core
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_regular_task_adhoc extends adhoc_task {
    use completion_regular_task_trait;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskcompletionregular_adhoc', 'admin');
    }

    /**
     * Execute the ad-hoc task: extract custom data and delegate to perform_regular_completion.
     *
     * @return void
     */
    public function execute() {
        $data = $this->get_custom_data();

        $constraints = [];
        $mtraceprogress = true;

        if (!empty($data)) {
            if (isset($data->constraints)) {
                // Accept either array, object or JSON-encoded string.
                if (is_string($data->constraints)) {
                    $data->constraints = json_decode($data->constraints);
                }
                if (is_array($data->constraints)) {
                    $constraints = $data->constraints;
                } else if (is_object($data->constraints)) {
                    $constraints = (array) $data->constraints;
                }
            }

            if (isset($data->mtraceprogress)) {
                $mtraceprogress = (bool) $data->mtraceprogress;
            }
        }

        $this->perform_regular_completion($constraints, $mtraceprogress);
    }
}
