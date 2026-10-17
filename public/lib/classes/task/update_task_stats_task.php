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
 * Aggregates the task durations recorded by cron processes into the task statistics.
 *
 * @package   core
 * @copyright 2026 Catalyst IT
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_task_stats_task extends scheduled_task {
    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskupdatetaskstats', 'admin');
    }

    /**
     * Processes task.
     */
    public function execute() {
        if (!manager::update_task_stats()) {
            // Another process is writing to the statistics. The pending durations will be picked up on the next run.
            mtrace('Task statistics are locked by another process, skipping.');
        }
    }
}
