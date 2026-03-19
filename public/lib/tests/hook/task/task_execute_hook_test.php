<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace core\hook\task;

use core\cron;
use core\task\manager;
use core\task\scheduled_test_task;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for task before/after notification hooks.
 *
 * @package   core
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(scheduled_task_starting::class)]
#[CoversClass(scheduled_task_complete::class)]
#[CoversClass(adhoc_task_starting::class)]
#[CoversClass(adhoc_task_complete::class)]
final class task_execute_hook_test extends \advanced_testcase {
    /** @var int Hook count for _scheduled_task_starting. */
    private static $beforescheduledcount = 0;

    /** @var int Hook count for _scheduled_task_complete with success. */
    private static $afterscheduledcount = 0;

    /** @var int Hook count for _scheduled_task_complete with failure. */
    private static $afterscheduledfailcount = 0;

    /** @var int Hook count for adhoc_task_starting. */
    private static $beforeadhoccount = 0;

    /** @var int Hook count for adhoc_task_complete with success. */
    private static $afteradhoccount = 0;

    /** @var int Hook count for adhoc_task_complete with failure. */
    private static $afteradhocfailcount = 0;

    /** @var array Keep track of active tasks. Starting will increment a count against the task ID. Finishing will
     *             decrement. At the end of the execution, the count should be zero.
     */
    private static $scheduledtaskcounts = [];

    /** @var array Keep track of active tasks. Starting will increment a count against the task ID. Finishing will
     *             decrement. At the end of the execution, the count should be zero.
     */
    private static $adhoctaskcounts = [];

    /**
     * Sets up the PHP unit hook callbacks.
     */
    private static function setup_hook_callbacks() {
        // Replace the version of the manager in the DI container with a phpunit one.
        \core\di::set(
            \core\hook\manager::class,
            \core\hook\manager::phpunit_get_instance([
                // Load a list of hooks for `test_plugin1` from the fixture file.
                'test_core_task' => __DIR__ .
                    '/fixtures/hooks.php',
            ]),
        );
    }

    /**
     * Callback for _scheduled_task_starting hook.
     *
     * @param scheduled_task_starting $hook
     */
    public static function scheduled_task_starting(scheduled_task_starting $hook) {
        ++self::$beforescheduledcount;
        if (!isset(self::$scheduledtaskcounts[$hook->taskid])) {
            self::$scheduledtaskcounts[$hook->taskid] = 0;
        }
        ++self::$scheduledtaskcounts[$hook->taskid];
    }

    /**
     * Callback for _scheduled_task_complete for hook.
     *
     * @param scheduled_task_complete $hook
     */
    public static function scheduled_task_complete(scheduled_task_complete $hook) {
        if ($hook->succeeded) {
            ++self::$afterscheduledcount;
        } else {
            ++self::$afterscheduledfailcount;
        }
        --self::$scheduledtaskcounts[$hook->taskid];
    }

    /**
     * Callback for adhoc_task_starting for hook.
     *
     * @param adhoc_task_starting $hook
     */
    public static function adhoc_task_starting(adhoc_task_starting $hook) {
        ++self::$beforeadhoccount;
        if (!isset(self::$adhoctaskcounts[$hook->task->get_id()])) {
            self::$adhoctaskcounts[$hook->task->get_id()] = 0;
        }
        ++self::$adhoctaskcounts[$hook->task->get_id()];
    }

    /**
     * Callback for adhoc_task_complete for hook.
     *
     * @param adhoc_task_complete $hook
     */
    public static function adhoc_task_complete(adhoc_task_complete $hook) {
        if ($hook->succeeded) {
            ++self::$afteradhoccount;
        } else {
            ++self::$afteradhocfailcount;
        }
        --self::$adhoctaskcounts[$hook->task->get_id()];
    }

    /**
     * Tests hooks for scheduled tasks.
     */
    public function test_scheduled_task_dispatch(): void {
        global $DB;

        $this->resetAfterTest();
        $this->preventResetByRollback();

        // Disable all the tasks, so we can insert our own and be sure it's the only one being run.
        $DB->set_field('task_scheduled', 'disabled', 1);

        cron::setup_user();
        self::$beforescheduledcount = 0;
        self::$afterscheduledcount = 0;
        self::$afterscheduledfailcount = 0;
        self::$scheduledtaskcounts = [];
        self::setup_hook_callbacks();

        $task1 = new scheduled_test_task();
        $task1->set_minute('*');
        $task1->set_next_run_time(time() - HOURSECS);
        $DB->insert_record('task_scheduled', manager::record_from_scheduled_task($task1));

        // Test successful execution.
        $next1 = manager::get_next_scheduled_task(time());
        manager::scheduled_task_starting($next1);
        $next1->execute();
        manager::scheduled_task_complete($next1);

        $this->assertEquals(1, self::$beforescheduledcount);
        $this->assertEquals(1, self::$afterscheduledcount);
        $this->assertEquals(0, self::$afterscheduledfailcount);

        // Test failing execution.
        \core\task\manager::scheduled_task_starting($next1);
        $next1->execute();
        \core\task\manager::scheduled_task_failed($next1);

        $this->assertEquals(2, self::$beforescheduledcount);
        $this->assertEquals(1, self::$afterscheduledcount);
        $this->assertEquals(1, self::$afterscheduledfailcount);

        // Test that for each task start, there was a task finish.
        foreach (self::$scheduledtaskcounts as $count) {
            $this->assertEquals(0, $count);
        }
    }

    /**
     * Tests hooks for adhoc tasks.
     */
    public function test_adhoc_task_dispatch(): void {
        $this->resetAfterTest();

        $clock = $this->mock_clock_with_frozen();

        cron::setup_user();
        self::$beforeadhoccount = 0;
        self::$afteradhoccount = 0;
        self::$afteradhocfailcount = 0;
        self::$adhoctaskcounts = [];
        self::setup_hook_callbacks();

        // Succeeding task.
        $task = new \core\task\adhoc_test_task();
        manager::queue_adhoc_task($task);
        $task = manager::get_next_adhoc_task($clock->time());
        \core\task\manager::adhoc_task_starting($task);
        $task->execute();
        \core\task\manager::adhoc_task_complete($task);

        $this->assertEquals(1, self::$beforeadhoccount);
        $this->assertEquals(1, self::$afteradhoccount);
        $this->assertEquals(0, self::$afteradhocfailcount);

        // Failing task.
        $task = new \core\task\adhoc_test_task();
        manager::queue_adhoc_task($task);
        $task = manager::get_next_adhoc_task($clock->time());
        \core\task\manager::adhoc_task_starting($task);
        $task->execute();
        \core\task\manager::adhoc_task_failed($task);

        $this->assertEquals(2, self::$beforeadhoccount);
        $this->assertEquals(1, self::$afteradhoccount);
        $this->assertEquals(1, self::$afteradhocfailcount);

        // Test that for each task start, there was a task finish.
        foreach (self::$adhoctaskcounts as $count) {
            $this->assertEquals(0, $count);
        }
    }
}
