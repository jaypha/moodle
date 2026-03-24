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

namespace report_taskstats;

use core_table\sql_table;
use stdClass;

/**
 * Table for displaying stats.
 *
 * @package report_taskstats
 * @author Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class task_report_table extends sql_table {
    /** @var string[]  */
    const COLUMNS = [
        'classname',
        'count',
        'sumduration',
        'mean',
        'sd',
    ];

    /**
     * returns the columns defined for the table.
     *
     * @return string[]
     */
    protected function get_columns(): array {
        $columns = self::COLUMNS;
        return $columns;
    }

    /**
     * Overrides felxible_table::setup() to do some extra setup.
     *
     * @return false|\type|void
     */
    public function setup() {
        $this->put_sql();
        $retvalue = parent::setup();
        $this->set_attribute('class', $this->attributes['class'] . ' table-sm');
        return $retvalue;
    }

    /**
     * Defines the columns for this table.
     *
     * @throws \coding_exception
     */
    public function make_columns(): void {
        $headers = [];
        $columns = $this->get_columns();
        foreach ($columns as $column) {
            $headers[] = get_string('task_report_table:' . $column, 'report_taskstats');
        }

        $this->define_columns($columns);
        $this->column_class('count', 'text-right');
        $this->column_class('sumduration', 'text-right');
        $this->column_class('mean', 'text-right');
        $this->column_class('sd', 'text-right');
        $this->define_headers($headers);
    }

    /**
     * Create and set the SQL.
     *
     * @return void
     */
    protected function put_sql() {
        $fields = [
            'classname',
            'n',
            'sumduration',
            'mean',
            'sd',
        ];
        $fieldstr = implode(',', $fields);

        $fromsql = '{' . lib::TABLE_TASKSTATS . '}';

        $this->set_sql($fieldstr, $fromsql, '1=1');
    }

    /**
     * Run count column
     *
     * @param stdClass $record
     */
    public function col_count(stdClass $record) {
        return $record->n;
    }

    /**
     * Sum of durations column
     *
     * @param stdClass $record
     */
    public function col_sumduration(stdClass $record) {
        return $record->sumduration;
    }

    /**
     * Sum of durations column
     *
     * @param stdClass $record
     */
    public function col_sd(stdClass $record) {
        return $record->n > 1 ? $record->sumduration : '-';
    }
}
