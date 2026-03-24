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

namespace report_taskstats\local;

/**
 * A processor to calculate mean, SD, and sum as values are added. Uses Welford's algorithm.
 *
 * @package report_taskstats
 * @author Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class statprocessor {
    /** @var float|int */
    private $currentmean = 0;
    /** @var float|int */
    private $currentssd = 0;
    /** @var float|int */
    private $currentcount = 0;
    /** @var float|int */
    private $currentsum = 0;
    /** @var bool */
    private $changed = false;

    /**
     * @param float $startcount
     * @param float $startmean
     * @param float $startssd
     * @param float $currentsum
     */
    public function __construct(float $startcount = 0, float $startmean = 0,float $startssd = 0, float $startsum = 0) {
        if ($startcount != 0) {
            $this->currentmean = $startmean;
            $this->currentssd = $startssd;
            $this->currentcount = $startcount;
            $this->currentsum = $startsum;
        }
    }

    /**
     * Adds multiple values.
     *
     * @param array $values
     * @return void
     */
    public function add_values(array $values) {
        foreach ($values as $value) {
            $this->add_value($value);
        }
    }

    /**
     * Adds a value.
     *
     * @param float $nextval The next value to be added
     */
    public function add_value(float $nextval) {
        ++$this->currentcount;
        $delta1 = $nextval - $this->currentmean;
        $this->currentmean += $delta1 / $this->currentcount;
        $delta2 = $nextval - $this->currentmean;
        $this->currentssd += $delta1 * $delta2;
        $this->currentsum += $nextval;
        $this->changed = true;
    }

    /**
     * @param $name
     * @return bool|float|int|void
     */
    public function __get($name) {
        switch ($name) {
            case 'changed':
                return $this->changed;
            case 'mean':
                return $this->currentmean;
            case 'ssd':
                return $this->currentssd;
            case 'sd';
                return ($this->currentcount > 1) ? sqrt($this->currentssd / ($this->currentcount - 1)) : 0;
            case 'count':
                return $this->currentcount;
            case 'sum':
                return $this->currentsum;
        }
    }
}
