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
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests the statisic shaping.
 *
 * @package core
 * @author Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(task_stat_shaping_task::class)]
final class task_stat_shaping_task_test extends \advanced_testcase {
    /**
     * Provider for test_shaping_task().
     *
     * @return array[]
     */
    public static function provider_test_shaping_task(): array {
        return [
            'simple50.1' => [
                'testdata' => ['count' => 4, 'sumduration' => 12],
                'expecteddata' => (object) ['count' => '2', 'sumduration' => '6'],
                'shapeamount' => 50,
                'threshold' => 4,
            ],
            'simple50.2' => [
                'testdata' => ['count' => 7, 'sumduration' => 15],
                'expecteddata' => (object) ['count' => '4', 'sumduration' => '7.5'],
                'shapeamount' => 50,
                'threshold' => 4,
            ],
            'underthreshold' => [
                'testdata' => ['count' => 7, 'sumduration' => 15],
                'expecteddata' => (object) ['count' => '7', 'sumduration' => '15'],
                'shapeamount' => 50,
                'threshold' => 8,
            ],
            'simple25' => [
                'testdata' => ['count' => 7, 'sumduration' => 15],
                'expecteddata' => (object) ['count' => '5', 'sumduration' => '11.25'],
                'shapeamount' => 25,
                'threshold' => 4,
            ],
        ];
    }

    /**
     * Tests the task stat shaping task.
     *
     * @param array $testdata
     * @param object $expecteddata
     * @param int $shapeamount
     * @param int $threshold
     */
    #[DataProvider('provider_test_shaping_task')]
    public function test_shaping_task(array $testdata, object $expecteddata, int $shapeamount, int $threshold): void {
        global $DB, $CFG;
        $this->resetAfterTest();

        $CFG->taskstat_shape_amount = $shapeamount;
        $CFG->taskstat_shape_threshold = $threshold;

        $toinsert = [
            'type' => database_logger::TYPE_SCHEDULED,
            'component' => 'core',
            'classname' => '\core\task\task',
            'count' => $testdata['count'],
            'sumduration' => $testdata['sumduration'],
            'maxduration' => 6,
            'mean' => 4,
            'ssd' => 2,
            'sd' => 1,
        ];

        $id = $DB->insert_record(manager::TABLE_TASKSTATS, $toinsert, true);
        $task = new task_stat_shaping_task();
        $task->execute();

        $data = $DB->get_record(manager::TABLE_TASKSTATS, ['id' => $id], 'count, sumduration');
        $this->assertEquals($expecteddata, $data);
    }
}
