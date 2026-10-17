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

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests the aggregation of pending task durations into the task statistics.
 *
 * @package core
 * @author Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(update_task_stats_task::class)]
#[CoversClass(manager::class)]
final class update_task_stats_task_test extends \advanced_testcase {
    /**
     * Insert a pending duration.
     *
     * @param string $classname
     * @param float $duration
     */
    private function add_pending(string $classname, float $duration): void {
        global $DB;
        $DB->insert_record(manager::TABLE_TASKSTATS_PENDING, [
            'type' => database_logger::TYPE_SCHEDULED,
            'component' => 'core',
            'classname' => $classname,
            'duration' => $duration,
        ]);
    }

    /**
     * Test that pending durations are merged into new and existing statistics, and then removed.
     */
    public function test_execute(): void {
        global $DB;
        $this->resetAfterTest();

        // Existing stats for task_a: values 2 and 4.
        $existing = new stat_processor();
        $existing->add_values([2, 4]);
        $record = $existing->to_record((object) [
            'type' => database_logger::TYPE_SCHEDULED,
            'component' => 'core',
            'classname' => '\core\task\task_a',
        ]);
        $DB->insert_record(manager::TABLE_TASKSTATS, $record);

        $this->add_pending('\core\task\task_a', 6);
        $this->add_pending('\core\task\task_b', 1);
        $this->add_pending('\core\task\task_b', 3);

        // Use a small batch size to exercise batching.
        $this->assertTrue(manager::update_task_stats(2));

        $this->assertEquals(0, $DB->count_records(manager::TABLE_TASKSTATS_PENDING));

        $a = $DB->get_record(manager::TABLE_TASKSTATS, ['classname' => '\core\task\task_a']);
        $this->assertEquals(3, $a->count);
        $this->assertEqualsWithDelta(4, $a->mean, 0.0001);
        $this->assertEqualsWithDelta(12, $a->sumduration, 0.0001);
        $this->assertEqualsWithDelta(6, $a->maxduration, 0.0001);
        $this->assertEqualsWithDelta(2, $a->sd, 0.0001);

        $b = $DB->get_record(manager::TABLE_TASKSTATS, ['classname' => '\core\task\task_b']);
        $this->assertEquals(2, $b->count);
        $this->assertEqualsWithDelta(2, $b->mean, 0.0001);
        $this->assertEqualsWithDelta(3, $b->maxduration, 0.0001);
    }
}
