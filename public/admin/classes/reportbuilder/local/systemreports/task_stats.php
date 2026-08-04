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

namespace core_admin\reportbuilder\local\systemreports;

use context_system;
use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use core_admin\reportbuilder\local\entities\task_stat;

/**
 * Task statistic report
 *
 * @package     core_admin
 * @copyright   2026 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class task_stats extends system_report {
    /**
     * Initialise the report.
     */
    protected function initialise(): void {
        // Our main entity, it contains all of the column definitions that we need.
        $entitymain = new task_stat();
        $entitymainalias = $entitymain->get_table_alias('task_stats');

        $this->set_main_table('task_stats', $entitymainalias);
        $this->add_entity($entitymain);

        // Any columns required by actions should be defined here to ensure they're always available.
        $this->add_base_fields("{$entitymainalias}.id");

        // Now we can call our helper methods to add the content we want to include in the report.
        $this->add_columns();
        $this->add_actions();

        // Set if report can be downloaded.
        $this->set_downloadable(true, get_string('taskstatistics', 'admin'));
    }

    /**
     * Get the visible name of the report
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('taskstatistics', 'admin');
    }

    /**
     * Validates access to view this report
     *
     * @return bool
     */
    protected function can_view(): bool {
        return has_capability('moodle/site:config', context_system::instance());
    }

    /**
     * Add the columns.
     */
    public function add_columns(): void {
        $entitymainalias = $this->get_entity('task_stat')->get_table_alias('task_stats');

        $this->add_columns_from_entities([
            'task_stat:name',
            'task_stat:type',
            'task_stat:count',
            'task_stat:sumduration',
            'task_stat:max',
            'task_stat:mean',
            'task_stat:sd',
        ]);
    }

    /**
     * Add the system report actions. An extra column will be appended to each row, containing all actions added here
     *
     * Note the use of ":id" placeholder which will be substituted according to actual values in the row
     */
    protected function add_actions(): void {

        // Action to view individual task log on a popup window.
        $this->add_action((new action(
            new \moodle_url('/admin/taskstats.php', ['deleteid' => ':id']),
            new \pix_icon('e/delete', ''),
            [],
            false,
            new \lang_string('delete'),
        )));
    }
}
