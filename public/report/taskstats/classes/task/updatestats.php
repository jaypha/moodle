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

namespace report_taskstats\task;

use core\task\scheduled_task;
use report_taskstats\local\statprocessor;

/**
 * Updates statistics based on logs.
 *
 * @package report_taskstats
 * @author Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class updatestats extends scheduled_task {
    /** @var string  */
    public const TABLE_TASKSTATS = 'report_taskstats_stats';

    /** @var string  */
    public const TABLE_TASK_LOG = 'task_log';

    /**
     * Get the name of the task.
     *
     * @return \lang_string|string
     */
    public function get_name() {
        return get_string('updatestats_task', 'report_taskstats');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        // Read in the taskstats table and setup processing objects.

        $lasttime = (int) get_config('report_taskstats', 'lasttime');

        $means = [];
        $records = $DB->get_records(self::TABLE_TASKSTATS);
        foreach ($records as $record) {
            $means[$record->classname] = new statprocessor($record->n, $record->mean, $record->ssd, $record->sumduration);
        }

        // Read in the task logs and add values to the processor objects.
        $thistime = time();
        $select = 'timeend >= :lasttime and timeend < :thistime';
        $params = [
            'lasttime' => $lasttime,
            'thistime' => $thistime,
        ];

        $recordset = $DB->get_recordset_select(self::TABLE_TASK_LOG, $select, $params);

        foreach ($recordset as $record) {
            $classname = $record->classname;
            if (!array_key_exists($classname, $means)) {
                $means[$classname] = new statprocessor();
                $statobj = new \stdClass();
                $statobj->component = $record->component;
                $statobj->classname = $classname;
                $statobj->type = $record->type;
                $records[] = $statobj;
            }
            $means[$classname]->add_value($record->timeend - $record->timestart);
        }
        $recordset->close();

        // Get new calculated values from the objects and update records (or insert) in the database.
        foreach ($records as $record) {
            $mean = $means[$record->classname];
            // Only update if it has actually changed.
            if ($mean->changed) {
                $record->n = $mean->count;
                $record->ssd = $mean->ssd;
                $record->sd = $mean->sd;
                $record->mean = $mean->mean;
                $record->sumduration = $mean->sum;

                if (isset($record->id)) {
                    $DB->update_record(self::TABLE_TASKSTATS, $record, true);
                } else {
                    $DB->insert_record(self::TABLE_TASKSTATS, $record);
                }
            }
        }

        set_config('lasttime', $thistime,'report_taskstats');
    }
}
