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

namespace core_completion;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Test completion criteria.
 *
 * @package   core_completion
 * @category  test
 * @copyright 2021 Mikhail Golenkov <mikhailgolenkov@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\completion_criteria_date::class)]
#[CoversClass(\completion_criteria_grade::class)]
#[CoversClass(\completion_criteria_duration::class)]
final class completion_criteria_test extends \advanced_testcase {

    /**
     * Test setup.
     */
    public function setUp(): void {
        global $CFG;
        require_once($CFG->dirroot.'/completion/criteria/completion_criteria.php');
        require_once($CFG->dirroot.'/completion/criteria/completion_criteria_course.php');
        require_once($CFG->dirroot.'/completion/criteria/completion_criteria_activity.php');
        require_once($CFG->dirroot.'/completion/criteria/completion_criteria_duration.php');
        require_once($CFG->dirroot.'/completion/criteria/completion_criteria_grade.php');
        require_once($CFG->dirroot.'/completion/criteria/completion_criteria_date.php');
        parent::setUp();

        $this->setAdminUser();
        $this->resetAfterTest();
    }

    /**
     * Test that activity completion dates are used when activity criteria is marked as completed.
     */
    public function test_completion_criteria_activity(): void {
        global $DB;
        $timestarted = time();

        // Create a course, an activity and enrol a user.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id], ['completion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id);

        // Set completion criteria and mark the user to complete the criteria.
        $criteriadata = (object) [
            'id' => $course->id,
            'criteria_activity' => [$assign->cmid => 1],
        ];
        $criterion = new \completion_criteria_activity();
        $criterion->update_config($criteriadata);
        $cmassign = get_coursemodule_from_id('assign', $assign->cmid);
        $completion = new \completion_info($course);
        $completion->update_state($cmassign, COMPLETION_COMPLETE, $user->id);

        // Completion criteria for the user is supposed to be marked as completed at now().
        $result = \core_completion_external::get_activities_completion_status($course->id, $user->id);
        $actual = reset($result['statuses']);
        $this->assertEquals(1, $actual['state']);
        $this->assertGreaterThanOrEqual($timestarted, $actual['timecompleted']);

        // And the whole course is marked as completed at now().
        $ccompletion = new \completion_completion(['userid' => $user->id, 'course' => $course->id]);
        $this->assertGreaterThanOrEqual($timestarted, $ccompletion->timecompleted);
        $this->assertTrue($ccompletion->is_complete());
    }

    /**
     * Test that enrolment timestart are used when duration criteria is marked as completed.
     */
    public function test_completion_criteria_duration_timestart(): void {
        global $DB;
        $timestarted = 1610000000;
        $durationperiod = DAYSECS;

        // Create a course and users.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', null, 'manual', $timestarted);

        // Set completion criteria.
        $criteriadata = (object) [
            'id' => $course->id,
            'criteria_duration' => 1,
            'criteria_duration_days' => $durationperiod,
        ];
        $criterion = new \completion_criteria_duration();
        $criterion->update_config($criteriadata);

        // Run completion scheduled task.
        $task = new \core\task\completion_regular_task();
        $this->expectOutputRegex("/Marking complete/");
        $task->execute();
        // Hopefully, some day MDL-33320 will be fixed and all these sleeps
        // and double cron calls in behat and unit tests will be removed.
        sleep(1);
        $task->execute();

        // The course for User is supposed to be marked as completed at $timestarted + $durationperiod.
        $ccompletion = new \completion_completion(['userid' => $user->id, 'course' => $course->id]);
        $this->assertEquals($timestarted + $durationperiod, $ccompletion->timecompleted);
        $this->assertTrue($ccompletion->is_complete());

        // Now we want to check the scenario where "now" sits in the middle of the timestart + duration
        // and timecreated + duration window.
        $nowtime = time();
        $timestarted = $nowtime - $durationperiod + (2 * DAYSECS);
        $timecreated = $nowtime - $durationperiod - (2 * DAYSECS);

        // Using a new user for this.
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student', null, 'manual', $timestarted);

        // We need to manually update the enrollment's time created.
        $DB->set_field('user_enrolments', 'timecreated', $timecreated, ['userid' => $user->id]);

        // Run the completion cron. See MDL-33320.
        $task->execute();
        sleep(1);
        $task->execute();

        // We do NOT expect the user to be complete currently.
        $ccompletion = new \completion_completion(['userid' => $user->id, 'course' => $course->id]);
        $this->assertFalse($ccompletion->is_complete());

        // Now, finally, we will move the timestart to be in the past, but still after the timecreated.
        $timestarted = $timecreated + DAYSECS;
        $DB->set_field('user_enrolments', 'timestart', $timestarted, ['userid' => $user->id]);

        // Run the completion cron. See MDL-33320.
        $task->execute();
        sleep(1);
        $task->execute();

        // Now they should be complete.
        $ccompletion = new \completion_completion(['userid' => $user->id, 'course' => $course->id]);
        $this->assertEquals($timestarted + $durationperiod, $ccompletion->timecompleted);
        $this->assertTrue($ccompletion->is_complete());
    }

    /**
     * Test that enrolment timecreated are used when duration criteria is marked as completed.
     */
    public function test_completion_criteria_duration_timecreated(): void {
        global $DB;

        $timecreated = 1620000000;
        $durationperiod = DAYSECS;

        // Create a course and users.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        // Create and enrol user with an empty time start, but update the record like it was created at $timecreated.
        $user = $this->getDataGenerator()->create_and_enrol($course);
        $DB->set_field('user_enrolments', 'timecreated', $timecreated, ['userid' => $user->id]);

        // Set completion criteria.
        $criteriadata = (object) [
            'id' => $course->id,
            'criteria_duration' => 1,
            'criteria_duration_days' => $durationperiod,
        ];
        $criterion = new \completion_criteria_duration();
        $criterion->update_config($criteriadata);

        // Run completion scheduled task.
        $task = new \core\task\completion_regular_task();
        $this->expectOutputRegex("/Marking complete/");
        $task->execute();

        // Hopefully, some day MDL-33320 will be fixed and all these sleeps
        // and double cron calls in behat and unit tests will be removed.
        sleep(1);
        $task->execute();

        // The course for user is supposed to be marked as completed at $timecreated + $durationperiod.
        $ccompletion = new \completion_completion(['userid' => $user->id, 'course' => $course->id]);
        $this->assertEquals($timecreated + $durationperiod, $ccompletion->timecompleted);
        $this->assertTrue($ccompletion->is_complete());
    }

    /**
     * Test that criteria date is used as a course completion date.
     */
    public function test_completion_criteria_date(): void {
        global $DB;
        $timeend = 1610000000;

        // Create a course and enrol a user.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id);

        // Set completion criteria.
        $criteriadata = (object) [
            'id' => $course->id,
            'criteria_date' => 1,
            'criteria_date_value' => $timeend,
        ];
        $criterion = new \completion_criteria_date();
        $criterion->update_config($criteriadata);

        // Run completion scheduled task.
        $task = new \core\task\completion_regular_task();
        $this->expectOutputRegex("/Marking complete/");
        $task->execute();
        // Hopefully, some day MDL-33320 will be fixed and all these sleeps
        // and double cron calls in behat and unit tests will be removed.
        sleep(1);
        $task->execute();

        // The course is supposed to be marked as completed at $timeend.
        $ccompletion = new \completion_completion(['userid' => $user->id, 'course' => $course->id]);
        $this->assertEquals($timeend, $ccompletion->timecompleted);
        $this->assertTrue($ccompletion->is_complete());
    }

    /**
     * Test that criteria date is used as a course completion date, using a timefrom parameter.
     */
    public function test_completion_criteria_date_with_timefrom(): void {
        global $DB;
        $timeend = 1610000000;

        // Create a course and enrol a user.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id);

        // Set completion criteria.
        $criteriadata = (object) [
            'id' => $course->id,
            'criteria_date' => 1,
            'criteria_date_value' => $timeend,
        ];
        $criterion = new \completion_criteria_date();
        $criterion->update_config($criteriadata);

        // Run completion scheduled task.
        $task = new \core\task\completion_regular_task();
        $task->set_last_run_time($timeend + 2 * HOURSECS);
        $this->expectOutputRegex("/Marking complete/");
        $task->execute();
        // Hopefully, some day MDL-33320 will be fixed and all these sleeps
        // and double cron calls in behat and unit tests will be removed.
        sleep(1);
        $task->execute();

        // The course is supposed to be marked as completed at $timeend.
        $ccompletion = new \completion_completion(['userid' => $user->id, 'course' => $course->id]);
        $this->assertFalse($ccompletion->is_complete());

        // Run completion scheduled task.
        $task = new \core\task\completion_regular_task();
        $task->set_last_run_time($timeend - 2 * HOURSECS);
        $this->expectOutputRegex("/Marking complete/");
        $task->execute();
        // Hopefully, some day MDL-33320 will be fixed and all these sleeps
        // and double cron calls in behat and unit tests will be removed.
        sleep(1);
        $task->execute();

        // The course is supposed to be marked as completed at $timeend.
        $ccompletion = new \completion_completion(['userid' => $user->id, 'course' => $course->id]);
        $this->assertTrue($ccompletion->is_complete());
    }

    /**
     * Test that grade timemodified is used when grade criteria is marked as completed.
     */
    public function test_completion_criteria_grade(): void {
        global $DB;
        $timegraded = 1610000000;

        // Create a course and enrol a couple of users.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, $studentrole->id);

        // Set completion criteria.
        $criteriadata = (object) [
            'id' => $course->id,
            'criteria_grade' => 1,
            'criteria_grade_value' => 66,
        ];
        $criterion = new \completion_criteria_grade();
        $criterion->update_config($criteriadata);

        $coursegradeitem = \grade_item::fetch_course_item($course->id);

        // Grade User 1 with a passing grade.
        $grade1 = new \grade_grade();
        $grade1->itemid = $coursegradeitem->id;
        $grade1->timemodified = $timegraded;
        $grade1->userid = $user1->id;
        $grade1->finalgrade = 80;
        $grade1->insert();

        // Grade User 2 with a non-passing grade.
        $grade2 = new \grade_grade();
        $grade2->itemid = $coursegradeitem->id;
        $grade2->timemodified = $timegraded;
        $grade2->userid = $user2->id;
        $grade2->finalgrade = 40;
        $grade2->insert();

        // Run completion scheduled task.
        $task = new \core\task\completion_regular_task();
        $this->expectOutputRegex("/Marking complete/");
        $task->execute();
        // Hopefully, some day MDL-33320 will be fixed and all these sleeps
        // and double cron calls in behat and unit tests will be removed.
        sleep(1);
        $task->execute();

        // The course for User 1 is supposed to be marked as completed when the user was graded.
        $ccompletion = new \completion_completion(['userid' => $user1->id, 'course' => $course->id]);
        $this->assertEquals($timegraded, $ccompletion->timecompleted);
        $this->assertTrue($ccompletion->is_complete());

        // The course for User 2 is supposed to be marked as not completed.
        $ccompletion = new \completion_completion(['userid' => $user2->id, 'course' => $course->id]);
        $this->assertFalse($ccompletion->is_complete());
    }

    /**
     * Test that only grades completed since last time are marked as completed.
     */
    public function test_completion_criteria_grade_with_timefrom(): void {
        global $DB;
        $timegraded = 1610000000;

        // Create a course and enrol a couple of users.
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $user1 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user($user1->id, $course->id, $studentrole->id);

        // Set completion criteria.
        $criteriadata = (object) [
            'id' => $course->id,
            'criteria_grade' => 1,
            'criteria_grade_value' => 66,
        ];
        $criterion = new \completion_criteria_grade();
        $criterion->update_config($criteriadata);

        $coursegradeitem = \grade_item::fetch_course_item($course->id);

        // Grade User 1 with a passing grade.
        $grade1 = new \grade_grade();
        $grade1->itemid = $coursegradeitem->id;
        $grade1->timemodified = $timegraded;
        $grade1->userid = $user1->id;
        $grade1->finalgrade = 80;
        $grade1->insert();

        // Run completion scheduled task.
        $task = new \core\task\completion_regular_task();
        $task->set_last_run_time($timegraded + 2 * HOURSECS);
        $this->expectOutputRegex("/Marking complete/");
        $task->execute();
        // Hopefully, some day MDL-33320 will be fixed and all these sleeps
        // and double cron calls in behat and unit tests will be removed.
        sleep(1);
        $task->execute();

        // The course for User 1 is supposed to be not completed as it does not fall in the timeframe.
        $ccompletion = new \completion_completion(['userid' => $user1->id, 'course' => $course->id]);
        $this->assertFalse($ccompletion->is_complete());

        // Run again but with an earlier cutoff.
        $task = new \core\task\completion_regular_task();
        $task->set_last_run_time($timegraded - 2 * HOURSECS);
        $this->expectOutputRegex("/Marking complete/");
        $task->execute();
        // Hopefully, some day MDL-33320 will be fixed and all these sleeps
        // and double cron calls in behat and unit tests will be removed.
        sleep(1);
        $task->execute();

        // The course for User 1 is supposed to be marked as completed.
        $ccompletion = new \completion_completion(['userid' => $user1->id, 'course' => $course->id]);
        $this->assertTrue($ccompletion->is_complete());
    }

    /**
     * Test completion_criteria_duration with a course constraint.
     */
    public function test_completion_criteria_duration_with_course_constraint(): void {
        global $DB;
        $period = DAYSECS;
        $now = time();

        // Create two courses with completion enabled.
        $course1 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $course2 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        // Create users and enrol them so they meet the duration requirement (timestart in the past).
        $timestart = $now - ($period + 100);
        $user1 = $this->getDataGenerator()->create_and_enrol($course1, 'student', null, 'manual', $timestart);
        $user2 = $this->getDataGenerator()->create_and_enrol($course2, 'student', null, 'manual', $timestart);

        // Set duration criteria for both courses.
        $criteriadata = (object) [
            'id' => $course1->id,
            'criteria_duration' => 1,
            'criteria_duration_days' => $period,
        ];
        $criterion = new \completion_criteria_duration();
        $criterion->update_config($criteriadata);

        $criteriadata->id = $course2->id;
        $criterion = new \completion_criteria_duration();
        $criterion->update_config($criteriadata);

        // Run the ad-hoc task with a course constraint for course1.
        $task = new \core\task\completion_regular_task_adhoc();
        $task->set_custom_data((object)[
            'constraints' => ['courseid' => $course1->id],
            'mtraceprogress' => false,
        ]);
        $task->execute();
        // Ensure items flagged during the first pass are processed in the second run (see MDL-33320 behaviour).
        sleep(1);
        // Run the scheduled task to perform aggregation.
        $task = new \core\task\completion_regular_task();
        $this->expectOutputRegex("/Marking complete/");
        $task->execute();

        // Course1 user should be marked complete, course2 user should not.
        $ccompletion1 = new \completion_completion(['userid' => $user1->id, 'course' => $course1->id]);
        $this->assertTrue($ccompletion1->is_complete());

        $ccompletion2 = new \completion_completion(['userid' => $user2->id, 'course' => $course2->id]);
        $this->assertFalse($ccompletion2->is_complete());
    }

    /**
     * Test completion_criteria_date with a course constraint.
     */
    public function test_completion_criteria_date_with_course_constraint(): void {
        global $DB;
        $timeend = 1610000000;

        // Create two courses and users.
        $course1 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $course2 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course2->id, $studentrole->id);

        // Set completion date criteria for both courses.
        $criteriadata = (object) [
            'id' => $course1->id,
            'criteria_date' => 1,
            'criteria_date_value' => $timeend,
        ];
        $criterion = new \completion_criteria_date();
        $criterion->update_config($criteriadata);

        $criteriadata->id = $course2->id;
        $criterion = new \completion_criteria_date();
        $criterion->update_config($criteriadata);

        // Run the ad-hoc task with a course constraint for course1.
        $task = new \core\task\completion_regular_task_adhoc();
        $task->set_custom_data((object)[
            'constraints' => ['courseid' => $course1->id],
            'mtraceprogress' => false,
        ]);
        $task->execute();
        // Ensure items flagged during the first pass are processed in the second run.
        sleep(1);
        // Run the scheduled task to perform aggregation.
        $task = new \core\task\completion_regular_task();
        $this->expectOutputRegex("/Marking complete/");
        $task->execute();

        // Course1 user should be marked complete at $timeend, course2 user should not be complete.
        $ccompletion1 = new \completion_completion(['userid' => $user1->id, 'course' => $course1->id]);
        $this->assertEquals($timeend, $ccompletion1->timecompleted);
        $this->assertTrue($ccompletion1->is_complete());

        $ccompletion2 = new \completion_completion(['userid' => $user2->id, 'course' => $course2->id]);
        $this->assertFalse($ccompletion2->is_complete());
    }

    /**
     * Test completion_criteria_grade with a course constraint (ensure separate criterion instances).
     */
    public function test_completion_criteria_grade_with_course_constraint(): void {
        global $DB;
        $timegraded = 1615000000;

        // Create two courses and enroll users.
        $course1 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $course2 = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($user2->id, $course2->id, $studentrole->id);

        // Set grade criteria on both courses. Use separate instances for each course.
        $criteriadata1 = (object) [
            'id' => $course1->id,
            'criteria_grade' => 1,
            'criteria_grade_value' => 50,
        ];
        $criterion1 = new \completion_criteria_grade();
        $criterion1->update_config($criteriadata1);

        $criteriadata2 = (object) [
            'id' => $course2->id,
            'criteria_grade' => 1,
            'criteria_grade_value' => 50,
        ];
        $criterion2 = new \completion_criteria_grade();
        $criterion2->update_config($criteriadata2);

        // Create course grade items and insert grades for both users.
        $coursegradeitem1 = \grade_item::fetch_course_item($course1->id);
        $grade1 = new \grade_grade();
        $grade1->itemid = $coursegradeitem1->id;
        $grade1->timemodified = $timegraded;
        $grade1->userid = $user1->id;
        $grade1->finalgrade = 80;
        $grade1->insert();

        $coursegradeitem2 = \grade_item::fetch_course_item($course2->id);
        $grade2 = new \grade_grade();
        $grade2->itemid = $coursegradeitem2->id;
        $grade2->timemodified = $timegraded;
        $grade2->userid = $user2->id;
        $grade2->finalgrade = 80;
        $grade2->insert();

        // Run the adhoc task constrained to course1 only.
        $task = new \core\task\completion_regular_task_adhoc();
        $task->set_custom_data((object)[
            'constraints' => ['courseid' => $course1->id],
            'mtraceprogress' => false,
        ]);
        $task->execute();
        // Ensure second pass processes items flagged during the first pass.
        sleep(1);
        // Run the scheduled task to perform aggregation.
        $task = new \core\task\completion_regular_task();
        $this->expectOutputRegex("/Marking complete/");
        $task->execute();

        // Course1 user should be marked complete, course2 user should not be processed.
        $ccompletion1 = new \completion_completion(['userid' => $user1->id, 'course' => $course1->id]);
        $this->assertEquals($timegraded, $ccompletion1->timecompleted);
        $this->assertTrue($ccompletion1->is_complete());

        $ccompletion2 = new \completion_completion(['userid' => $user2->id, 'course' => $course2->id]);
        $this->assertFalse($ccompletion2->is_complete());
    }
}
