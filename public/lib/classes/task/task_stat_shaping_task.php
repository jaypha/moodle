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
 * Shapes task statistics to allow a weighted average.
 * Every time this task runs, the task count and duration is 'shaped'. That is, reduced by a certian percentage.
 * This allows future values that get added to have more influence on the average.
 *
 * @package core
 * @author Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class task_stat_shaping_task extends scheduled_task {
    /** @var int  Lock timeout in seconds. */
    public const LOCK_TIMEOUT = 60;

    /**
     * Get a descriptive name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskstatshapingtask', 'admin');
    }

    /**
     * Processes task.
     */
    public function execute() {
        global $DB, $CFG;

        $shapingamount = (100 - $CFG->taskstat_shape_amount) / 100;
        $shapingthreshold = $CFG->taskstat_shape_threshold;

        // Prevent update_task_stats_task from overwriting the shaped values with ones it read beforehand.
        $lockfactory = \core\lock\lock_config::get_lock_factory('cron');
        if (!$lock = $lockfactory->get_lock(manager::TASKSTATS_LOCK, self::LOCK_TIMEOUT)) {
            throw new \moodle_exception('locktimeout');
        }

        try {
            $records = $DB->get_records(manager::TABLE_TASKSTATS);
            foreach ($records as $record) {
                if ($record->count < $shapingthreshold) {
                    continue;
                }

                $record->count = round($record->count * $shapingamount);
                $record->sumduration *= $shapingamount;

                $DB->update_record(manager::TABLE_TASKSTATS, $record, true);
            }
        } finally {
            $lock->release();
        }
    }
}
