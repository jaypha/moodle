<?php

namespace report_taskstats;

/**
 * Tests for statprocessor class.
 *
 * @package report_taskstats
 * @author Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class statprocessor_test extends \advanced_testcase {
    public function test_stat_processor() {
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
        $mean = new local\statprocessor();
        foreach ($data1 as $row) {
            $mean->add_value($row['value']);
            $this->assertEquals($row['count'], $mean->count);
            $this->assertEquals($row['mean'], round($mean->mean, 3));
            $this->assertEquals($row['ssd'], round($mean->ssd, 3));
            $this->assertEquals($row['sd'], round($mean->sd, 3));
            $this->assertEquals($row['sum'], $mean->sum);
        }

        // Test adding values as a group, using a known starting point.
        $mean = new local\statprocessor(4, 6.75, 32.75, 27);
        $mean->add_values($data2);

        $this->assertEquals($finalcount, $mean->count);
        $this->assertEquals($finalmean, round($mean->mean, 3));
        $this->assertEquals($finalssd, round($mean->ssd, 3));
        $this->assertEquals($finalsd, round($mean->sd, 3));
        $this->assertEquals($finalsum, $mean->sum);
    }
}
