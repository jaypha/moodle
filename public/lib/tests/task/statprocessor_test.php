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
 * Tests for statprocessor class.
 *
 * @package core
 * @author Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(stat_processor::class)]
final class statprocessor_test extends \advanced_testcase {
    /**
     * Tests the add_value function.
     */
    public function test_add_value(): void {
        $data1 = [
            ['count' => 1, 'value' => 5, 'mean' => 5, 'ssd' => 0, 'sd' => 0, 'sum' => 5],
            ['count' => 2, 'value' => 3, 'mean' => 4, 'ssd' => 2, 'sd' => 1.414, 'sum' => 8],
            ['count' => 3, 'value' => 9, 'mean' => 5.667, 'ssd' => 18.667, 'sd' => 3.055, 'sum' => 17],
            ['count' => 4, 'value' => 10, 'mean' => 6.75, 'ssd' => 32.75, 'sd' => 3.304, 'sum' => 27],
        ];

        $data2 = [9, 2, 6, 6];
        $finalmean = 6.25;
        $finalssd = 59.5;
        $finalsd = 2.915;
        $finalsum = 50;
        $finalcount = 8;

        // Test adding values one at a time, from scratch.
        $mean = new stat_processor();
        foreach ($data1 as $row) {
            $mean->add_value($row['value']);
            $this->assertEquals($row['count'], $mean->count);
            $this->assertEquals($row['mean'], round($mean->mean, 3));
            $this->assertEquals($row['ssd'], round($mean->ssd, 3));
            $this->assertEquals($row['sd'], round($mean->sd, 3));
            $this->assertEquals($row['sum'], $mean->sum);
        }

        // Test adding values as a group, using a known starting point.
        $mean = new stat_processor(4, 6.75, 32.75, 27);
        $mean->add_values($data2);

        $this->assertEquals($finalcount, $mean->count);
        $this->assertEquals($finalmean, round($mean->mean, 3));
        $this->assertEquals($finalssd, round($mean->ssd, 3));
        $this->assertEquals($finalsd, round($mean->sd, 3));
        $this->assertEquals($finalsum, $mean->sum);
    }

    /**
     * Tests the merge function.
     */
    public function test_merge(): void {
        $data = [[5, 3, 9, 10], [9, 2, 6, 6]];

        $finalmean = 6.25;
        $finalssd = 59.5;
        $finalsd = 2.915;
        $finalsum = 50;
        $finalcount = 8;

        $processors = [];
        foreach ($data as $subdata) {
            $processor = new stat_processor();
            foreach ($subdata as $value) {
                $processor->add_value($value);
            }
            $processors[] = $processor;
        }

        $finalprocessor = new stat_processor();
        foreach ($processors as $processor) {
            $finalprocessor = $finalprocessor->merge($processor);
        }

        $this->assertEquals($finalcount, $finalprocessor->count);
        $this->assertEquals($finalmean, round($finalprocessor->mean, 3));
        $this->assertEquals($finalssd, round($finalprocessor->ssd, 3));
        $this->assertEquals($finalsd, round($finalprocessor->sd, 3));
        $this->assertEquals($finalsum, $finalprocessor->sum);
    }
}
