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

namespace core_admin\reportbuilder\local\entities;

use core\lang_string;
use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\report\column;

/**
 * Task statistic report entity
 *
 * @package     core_admin
 * @copyright   2026 Catalyst IT
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class task_stat extends base {
    /**
     * Database tables that the entity expects to be present in the main SQL or in JOINs added to it
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return ['task_stats'];
    }

    /**
     * The default title for this entity
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('taskstatistics', 'admin');
    }

    /**
     * Columns available from this entity
     *
     * @return column[]
     */
    protected function get_available_columns(): array {
        $tablealias = $this->get_table_alias('task_stats');

        // Name column.
        $columns[] = (new column(
            'name',
            new lang_string('name'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.classname")
            ->set_is_sortable(true)
            ->add_callback(static function (string $classname): string {
                $output = '';
                if (class_exists($classname)) {
                    $task = new $classname();
                    if ($task instanceof \core\task\task_base) {
                        $output = $task->get_name();
                    }
                }
                $output .= \html_writer::tag('div', "{$classname}", [
                    'class' => 'small text-muted',
                ]);
                return $output;
            });

        // Component column.
        $columns[] = (new column(
            'component',
            new lang_string('plugin'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.component")
            ->set_is_sortable(true);

        // Type column.
        $columns[] = (new column(
            'type',
            new lang_string('tasktype', 'admin'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->add_field("{$tablealias}.type")
            ->set_is_sortable(true)
            ->add_callback(static function ($value): string {
                if (\core\task\database_logger::TYPE_SCHEDULED === (int) $value) {
                    return get_string('task_type:scheduled', 'admin');
                }
                return get_string('task_type:adhoc', 'admin');
            });

        // Count column.
        $columns[] = (new column(
            'count',
            new lang_string('count', 'admin'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_INTEGER)
            ->add_field("{$tablealias}.count")
            ->set_is_sortable(true);

        // Sum duration column.
        $columns[] = (new column(
            'sumduration',
            new lang_string('sumduration', 'admin'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field("{$tablealias}.sumduration")
            ->set_is_sortable(true)
            ->add_callback(self::round(...));

        // Max duration column.
        $columns[] = (new column(
            'max',
            new lang_string('maxduration', 'admin'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field("{$tablealias}.maxduration")
            ->set_is_sortable(true)
            ->add_callback(self::round(...));

        // Mean column.
        $columns[] = (new column(
            'mean',
            new lang_string('mean', 'admin'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field("{$tablealias}.mean")
            ->set_is_sortable(true)
            ->add_callback(self::round(...));

        // Standard deviation column.
        $columns[] = (new column(
            'sd',
            new lang_string('standard_deviation', 'admin'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->add_field("{$tablealias}.sd")
            ->set_is_sortable(true)
            ->add_callback(self::round(...));

        return $columns;
    }

    /**
     * Rounds a value.
     *
     * @param float $value
     * @return string
     */
    protected static function round(float $value): string {
        return sprintf('%.3f', round($value, 3));
    }
}
