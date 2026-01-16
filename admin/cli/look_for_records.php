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
 * Find and fix bad course completion records.
 *
 * @package core
 * @author Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/completionlib.php');

function check_activity() {
    global $DB;

    // Get all users who meet this criteria
    $sql = "SELECT DISTINCT c.id AS course,
                                cr.id AS criteriaid,
                                mc.userid AS userid,
                                mc.timemodified AS timecompleted
                           FROM {course_completion_criteria} cr
                           JOIN {course} c ON cr.course = c.id
                           JOIN {course_modules} cm ON cm.id = cr.moduleinstance
                           JOIN {course_modules_completion} mc ON mc.coursemoduleid = cr.moduleinstance
                      LEFT JOIN {course_completion_crit_compl} cc ON cc.criteriaid = cr.id AND cc.userid = mc.userid
                          WHERE cr.criteriatype = :criteriatype
                            AND c.enablecompletion = 1
                            AND cc.id IS NULL
                            AND (
                                       (mc.completionstate IN (:completionstate, :completionstatepass))
                                    OR (mc.completionstate = :completionstatefail AND cm.completionpassgrade = 0)
                                )";

    $params = [
        'criteriatype' => COMPLETION_CRITERIA_TYPE_ACTIVITY,
        'contextlevel' => CONTEXT_COURSE,
        'completionstate' => COMPLETION_COMPLETE,
        'completionstatepass' => COMPLETION_COMPLETE_PASS,
        'completionstatefail' => COMPLETION_COMPLETE_FAIL
    ];

    $count = 0;

    // Loop through completions, and mark as complete.
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $record) {
        $completion = new \completion_criteria_completion((array) $record, DATA_OBJECT_FETCH_BY_KEY);
        $completion->mark_complete($record->timecompleted);
        ++$count;
    }
    $rs->close();
    return $count;
}

function check_course() {
    global $DB;

    // Get all users who meet this criteria.
    $sql = "SELECT DISTINCT c.id AS course,
                                cr.id AS criteriaid,
                                cc.userid AS userid,
                                cc.timecompleted AS timecompleted
                           FROM {course_completion_criteria} cr
                           JOIN {course} c ON cr.course = c.id
                           JOIN {course_completions} cc ON cc.course = cr.courseinstance
                      LEFT JOIN {course_completion_crit_compl} ccc ON ccc.criteriaid = cr.id AND ccc.userid = cc.userid
                          WHERE cr.criteriatype = :criteriatype
                            AND c.enablecompletion = 1
                            AND ccc.id IS NULL
                            AND cc.timecompleted IS NOT NULL";

    $params = ['criteriatype' => COMPLETION_CRITERIA_TYPE_COURSE];

    $count = 0;

    // Loop through completions, and mark as complete.
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $record) {
        $completion = new completion_criteria_completion((array) $record, DATA_OBJECT_FETCH_BY_KEY);
        $completion->mark_complete($record->timecompleted);
        ++$count;
    }
    $rs->close();
    return $count;
}

function check_date() {
    global $DB;

    // Get all users who match meet this criteria.
    $sql = "SELECT DISTINCT c.id AS course,
                                cr.timeend AS timeend,
                                cr.id AS criteriaid,
                                ra.userid AS userid
                           FROM {course_completion_criteria} cr
                           JOIN {course} c ON cr.course = c.id
                           JOIN {context} con ON con.instanceid = c.id
                           JOIN {role_assignments} ra ON ra.contextid = con.id
                      LEFT JOIN {course_completion_crit_compl} cc ON cc.criteriaid = cr.id AND cc.userid = ra.userid
                          WHERE cr.criteriatype = :criteriatype
                            AND con.contextlevel = :contextlevel
                            AND c.enablecompletion = 1
                            AND cc.id IS NULL
                            AND cr.timeend < :timeend";

    $params = [
        'criteriatype' => COMPLETION_CRITERIA_TYPE_DATE,
        'contextlevel' => CONTEXT_COURSE,
        'timeend' => time(),
    ];

    $count = 0;

    // Loop through completions, and mark as complete.
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $record) {
        $completion = new completion_criteria_completion((array) $record, DATA_OBJECT_FETCH_BY_KEY);
        $completion->mark_complete($record->timeend);
        ++$count;
    }
    $rs->close();
    return $count;
}

function check_duration() {
    global $DB;

    /*
     * Get all users who match meet this criteria.
     *
     * We can safely ignore duplicate enrolments for
     * a user in a course here as we only care if
     * one of the enrolments has passed the set
     * duration.
     */
    $sql = "SELECT c.id AS course,
                       cr.id AS criteriaid,
                       u.id AS userid,
                       ue.timestart AS otimestart,
                       (ue.timestart + cr.enrolperiod) AS ctimestart,
                       ue.timecreated AS otimeenrolled,
                       (ue.timecreated + cr.enrolperiod) AS ctimeenrolled
                  FROM {user} u
                  JOIN {user_enrolments} ue ON ue.userid = u.id
                  JOIN {enrol} e ON e.id = ue.enrolid
                  JOIN {course} c ON c.id = e.courseid
                  JOIN {course_completion_criteria} cr ON c.id = cr.course
             LEFT JOIN {course_completion_crit_compl} cc ON cc.criteriaid = cr.id AND cc.userid = u.id
                 WHERE cr.criteriatype = :criteriatype
                   AND c.enablecompletion = 1
                   AND cc.id IS NULL
                   AND (
                           (ue.timestart > 0 AND (ue.timestart + cr.enrolperiod) < :timestart1)
                           OR (ue.timestart = 0 AND ue.timecreated > 0 AND (ue.timecreated + cr.enrolperiod) < :timestart2)
                       )";

    $now = time();
    $params = [
        'criteriatype' => COMPLETION_CRITERIA_TYPE_DURATION,
        'timestart1' => $now,
        'timestart2' => $now,
    ];

    $count = 0;

    // Loop through completions, and mark as complete.
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $record) {
        $completion = new completion_criteria_completion((array) $record, DATA_OBJECT_FETCH_BY_KEY);

        // Use time start if not 0, otherwise use timeenrolled.
        if ($record->otimestart) {
            $completion->mark_complete($record->ctimestart);
        } else {
            $completion->mark_complete($record->ctimeenrolled);
        }
        ++$count;
    }
    $rs->close();
    return $count;
}

function check_grade() {
    global $DB;

    // Get all users who meet this criteria.
    $sql = "SELECT DISTINCT c.id AS course,
                                cr.id AS criteriaid,
                                gg.userid AS userid,
                                gg.finalgrade AS gradefinal,
                                gg.timemodified AS timecompleted
                           FROM {course_completion_criteria} cr
                           JOIN {course} c ON cr.course = c.id
                           JOIN {grade_items} gi ON gi.courseid = c.id AND gi.itemtype = 'course'
                           JOIN {grade_grades} gg ON gg.itemid = gi.id
                      LEFT JOIN {course_completion_crit_compl} cc ON cc.criteriaid = cr.id AND cc.userid = gg.userid
                          WHERE cr.criteriatype = :criteriatype
                            AND c.enablecompletion = 1
                            AND cc.id IS NULL
                            AND gg.finalgrade >= cr.gradepass";

    $params = ['criteriatype' => COMPLETION_CRITERIA_TYPE_GRADE];

    $count = 0;
    // Loop through completions, and mark as complete.
    $rs = $DB->get_recordset_sql($sql, $params);
    foreach ($rs as $record) {
        $completion = new completion_criteria_completion((array) $record, DATA_OBJECT_FETCH_BY_KEY);
        $completion->mark_complete($record->timecompleted);
        ++$count;
    }
    $rs->close();
    return $count;
}

/**
 * Start execution.
 */

mtrace("About to run cron completion update with updated SQL conditions. Notifications have been disabled temporarily to stop old completion notifications being emitted.");

// First disable all site messaging & emails.
// We are about to trigger potentially a lot of notifications for really old course completions,
// which we don't want to send out.
$CFG->noemeailever = 1;

// Disable all message apis as well (record which ones were enabled, to re-enable afterwards).
$enabledprocessors = $DB->get_records('message_processors', ['enabled' => 1]);
$DB->execute('UPDATE {message_processors} SET enabled =  0');

// Run the completion checks.
$c1 = check_activity();
echo "Activity completion records updated: $c1\n";
$c2 = check_course();
echo "Course completion records updated: $c2\n";
$c3 = check_date();
echo "Date completion records updated: $c3\n";
$c4 = check_duration();
echo "Duration completion records updated: $c4\n";
$c5 = check_grade();
echo "Grade completion records updated: $c5\n";

sleep(1);
aggregate_completions(0);

$CFG->noemeailever = 0;
[$insql, $inparams] = $DB->get_in_or_equal(array_column($enabledprocessors, 'id'));
$DB->execute('UPDATE {message_processors} SET enabled = 1 WHERE id ' . $insql, $inparams);
