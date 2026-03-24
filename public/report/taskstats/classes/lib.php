<?php

namespace report_taskstats;

/**
 * Miscellaneous functions for the plugin
 *
 * @package report_taskstats
 * @author Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lib
{
    /** @var string  */
    public const TABLE_TASKSTATS = 'report_taskstats_stats';

    /**
     * Clear the database table of the records.
     * @param array $classnames
     */
    public static function clear_stats(array $classnames = []) {
        global $DB;
        if (empty($classnames)) {
            $DB->delete_records(self::TABLE_TASKSTATS);
        } else {
            [$insql, $params] = $DB->get_in_or_equal($classnames, true);
            $DB->delete_records_select(self::TABLE_TASKSTATS, 'classname ' . $insql, $params);
        }
        set_config('lasttime', null,'report_taskstats');
    }
}
