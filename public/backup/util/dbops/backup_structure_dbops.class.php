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

/**
 * @package    moodlecore
 * @subpackage backup-dbops
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Non instantiable helper class providing DB support to the backup_structure stuff
 *
 * This class contains various static methods available for all the DB operations
 * performed by the backup_structure stuff (mainly @backup_nested_element class)
 *
 * TODO: Finish phpdocs
 */
abstract class backup_structure_dbops extends backup_dbops {

    public static function get_iterator($element, $params, $processor) {
        global $DB;

        // Check we are going to get_iterator for one backup_nested_element
        if (! $element instanceof backup_nested_element) {
            throw new base_element_struct_exception('backup_nested_element_expected');
        }

        // If var_array, table and sql are null, and element has no final elements it is one nested element without source
        // Just return one 1 element iterator without information
        if ($element->get_source_array() === null && $element->get_source_table() === null &&
            $element->get_source_sql() === null && count($element->get_final_elements()) == 0) {
            return new backup_array_iterator(array(0 => null));

        } else if ($element->get_source_array() !== null) { // It's one array, return array_iterator
            return new backup_array_iterator($element->get_source_array());

        } else if ($element->get_source_table() !== null) { // It's one table, return recordset iterator
            return $DB->get_recordset($element->get_source_table(), self::convert_params_to_values($params, $processor), $element->get_source_table_sortby());

        } else if ($element->get_source_sql() !== null) { // It's one sql, return recordset iterator
            return $DB->get_recordset_sql($element->get_source_sql(), self::convert_params_to_values($params, $processor));

        } else { // No sources, supress completely, using null iterator
            return new backup_null_iterator();
        }
    }

    public static function convert_params_to_values($params, $processor) {
        $newparams = array();
        foreach ($params as $key => $param) {
            $newvalue = null;
            // If we have a base element, get its current value, exception if not set
            if ($param instanceof base_atom) {
                if ($param->is_set()) {
                    $newvalue = $param->get_value();
                } else {
                    throw new base_element_struct_exception('valueofparamelementnotset', $param->get_name());
                }

            } else if (is_int($param) && $param < 0) { // Possibly one processor variable, let's process it
                // See @backup class for all the VAR_XXX variables available.
                // Note1: backup::VAR_PARENTID is handled by nested elements themselves
                // Note2: trying to use one non-existing var will throw exception
                $newvalue = $processor->get_var($param);

            // Else we have one raw param value, use it
            } else {
                $newvalue = $param;
            }

            $newparams[$key] = $newvalue;
        }
        return $newparams;
    }

    /** @var array Record itemids of annotations to avoid storing duplicates. */
    protected static $backupids = [];

    public static function insert_backup_ids_record($backupid, $itemname, $itemid) {
        global $DB;
        // We need to do some magic with scales (that are stored in negative way)
        if ($itemname == 'scale') {
            $itemid = -($itemid);
        }
        // Now, we skip any annotation with negatives/zero/nulls, ids table only stores true id (always > 0)
        if ($itemid <= 0 || is_null($itemid)) {
            return;
        }
        if (!isset(self::$backupids[$backupid][$itemname][$itemid])) {
            $DB->insert_record('backup_ids_temp', array('backupid' => $backupid, 'itemname' => $itemname, 'itemid' => $itemid));
            self::$backupids[$backupid][$itemname][$itemid] = true;
        }
    }

    /**
     * Bulk insert an array of backup IDs.
     *
     * @param array $backupitems Each member must have a backupid, and itemname, and an itemid.
     */
    public static function insert_backup_ids_records(array $backupitems) {
        global $DB;

        $toinsert = [];
        foreach ($backupitems as $item) {
            // We need to do some magic with scales (that are stored in negative way).
            if ($item['itemname'] == 'scale') {
                $item['itemid'] = -($item['itemid']);
            }
            // Now, we skip any annotation with negatives/zero/nulls, ids table only stores true id (always > 0).
            if ($item['itemid'] <= 0 || is_null($item['itemid'])) {
                continue;
            }

            if (!isset(self::$backupids[$item['backupid']][$item['itemname']][$item['itemid']])) {
                $toinsert[] = $item;
                self::$backupids[$item['backupid']][$item['itemname']][$item['itemid']] = true;
            }
        }
        if (count($toinsert) > 0) {
            $DB->insert_records('backup_ids_temp', $toinsert);
        }
    }

    /**
     * Remove backup IDs.
     *
     * @param string $backupid
     * @param string $itemname
     */
    public static function delete_backup_ids(string $backupid, string $itemname) {
        global $DB;
        $DB->delete_records('backup_ids_temp', ['backupid' => $backupid, 'itemname' => $itemname]);
        unset(self::$backupids[$backupid][$itemname]);
    }

    /**
     * Purge all backup IDs. Call this whenever the temp ID table is truncated or deleted.
     * @return void
     */
    public static function purge_backup_ids() {
        self::$backupids = [];
    }

    /**
     * Adds backup id database record for all files in the given file area.
     *
     * @param string $backupid Backup ID
     * @param int $contextid Context id
     * @param string $component Component
     * @param string|array|null $filearea A single file area or an array of file areas.
     * @param int|array|null $itemid A single item ID or an array of item IDs.
     * @param \core\progress\base $progress
     */
    public static function annotate_files($backupid, $contextid, $component, $filearea, $itemid,
            ?\core\progress\base $progress = null) {
        global $DB;
        $sql = 'SELECT id
                  FROM {files}
                 WHERE contextid = ?
                   AND component = ?';
        $params = array($contextid, $component);

        if (!is_null($filearea)) { // Add filearea to query and params if necessary
            [$fileareasql, $fileareaparams] = $DB->get_in_or_equal($filearea);
            $sql .= ' AND filearea ' . $fileareasql;
            $params = array_merge($params, $fileareaparams);
        }

        if (!is_null($itemid)) { // Add itemid to query and params if necessary
            [$itemidsql, $itemidparams] = $DB->get_in_or_equal($itemid);
            $sql .= ' AND itemid ' . $itemidsql;
            $params = array_merge($params, $itemidparams);
        }
        if ($progress) {
            $progress->start_progress('');
        }
        $rs = $DB->get_recordset_sql($sql, $params);
        $items = [];
        foreach ($rs as $record) {
            // Do an insert every 1000 records.
            if (count($items) == 1000) {
                if ($progress) {
                    $progress->progress();
                }
                self::insert_backup_ids_records($items);
                $items = [];
            }
            $items[] = ['backupid' => $backupid, 'itemname' => 'file', 'itemid' => $record->id];
        }
        self::insert_backup_ids_records($items);
        if ($progress) {
            $progress->end_progress();
        }
        $rs->close();
    }

    /**
     * Moves all the existing 'item' annotations to their final 'itemfinal' ones
     * for a given backup.
     *
     * @param string $backupid Backup ID
     * @param string $itemname Item name
     * @param \core\progress\base $progress Progress tracker
     */
    public static function move_annotations_to_final($backupid, $itemname, \core\progress\base $progress) {
        global $DB;
        $progress->start_progress('move_annotations_to_final');
        $rs = $DB->get_recordset('backup_ids_temp', array('backupid' => $backupid, 'itemname' => $itemname));
        $progress->progress();
        $ids = [];
        $concat = $DB->sql_concat('itemname', "'final'");
        foreach($rs as $annotation) {
            if (!isset(self::$backupids[$backupid][$itemname . 'final'][$annotation->itemid])) {
                if (count($ids) == 1000) {
                    [$inorequal, $params] = $DB->get_in_or_equal($ids);
                    $DB->execute("UPDATE {backup_ids_temp} SET itemname = $concat WHERE id $inorequal", $params);
                    $progress->progress();
                    $ids = [];
                }
                $ids[] = $annotation->id;
                self::$backupids[$backupid][$itemname . 'final'][$annotation->itemid] = true;
            }
            $progress->progress();
        }
        $rs->close();
        if (count($ids)) {
            [$inorequal, $params] = $DB->get_in_or_equal($ids);
            $DB->execute("UPDATE {backup_ids_temp} SET itemname = $concat WHERE id $inorequal", $params);
            $progress->progress();
        }
        // All the remaining $itemname annotations can be safely deleted
        self::delete_backup_ids($backupid, $itemname);
        $progress->end_progress();
    }

    /**
     * Returns true/false if there are annotations for a given item
     */
    public static function annotations_exist($backupid, $itemname) {
        global $DB;
        return (
            isset(self::$backupids[$backupid][$itemname]) ||
            $DB->count_records('backup_ids_temp', ['backupid' => $backupid, 'itemname' => $itemname])
        );
    }
}
