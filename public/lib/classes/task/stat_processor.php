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

/**
 * A class to help create mean, variance and other statistics.
 * The mean and variance are calculated using Welford's algorithm.
 *
 * @package core
 * @author Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stat_processor {
    /** @var float */
    private float $currentmean = 0;
    /** @var float Sum of the square of deviations, so far. */
    private float $currentssd = 0;
    /** @var int */
    private int $currentcount = 0;
    /** @var float */
    private float $currentsum = 0;
    /** @var float */
    private float $currentmax = 0.0;
    /** @var bool */
    private bool $changed = false;

    /**
     * Constructor. Gives starting values.
     *
     * @param float $startcount
     * @param float $startmean
     * @param float $startssd
     * @param float $startsum
     * @param float $startmax
     */
    public function __construct(
        float $startcount = 0,
        float $startmean = 0,
        float $startssd = 0,
        float $startsum = 0,
        float $startmax = 0
    ) {
        if ($startcount != 0) {
            $this->currentmean = $startmean;
            $this->currentssd = $startssd;
            $this->currentcount = $startcount;
            $this->currentsum = $startsum;
            $this->currentmax = $startmax;
        }
    }

    /**
     * Adds multiple values.
     *
     * @param array $values
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
        $this->currentmax = max($this->currentmax, $nextval);
        $this->changed = true;
    }

    /**
     * Merge the stats of this processor with the stats of another processor and return a new processor with the result.
     *
     * @param stat_processor $other
     * @return stat_processor
     */
    public function merge(stat_processor $other): stat_processor {
        $delta = $other->mean - $this->mean;
        $count = $other->count + $this->count;
        $mean = $this->mean + $delta * ($other->count / $count);
        $ssd = $this->ssd + $other->ssd + $delta * $delta * ($this->count * $other->count / $count);
        $sum = $this->currentsum + $other->currentsum;
        $max = max($this->currentmax, $other->currentmax);
        return new stat_processor($count, $mean, $ssd, $sum, $max);
    }

    /**
     * Get property.
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name) {
        switch ($name) {
            case 'changed':
                return $this->changed;
            case 'mean':
                return $this->currentmean;
            case 'ssd': // Sum of the square of deviations.
                return $this->currentssd;
            case 'sd': // Standard deviation.
                return ($this->currentcount > 1) ? sqrt($this->currentssd / ($this->currentcount - 1)) : 0;
            case 'count':
                return $this->currentcount;
            case 'max':
                return $this->currentmax;
            case 'sum':
                return $this->currentsum;
            default:
                throw new \moodle_exception("Unknown property $name");
        }
    }

    /**
     * Export to a record object, suitable for insertion into a database.
     *
     * @param \stdClass|null $record
     * @return \stdClass
     */
    public function to_record(?\stdClass $record = null): \stdClass {
        if ($record === null) {
            $record = new \stdClass();
        }
        $record->count = $this->currentcount;
        $record->ssd = $this->currentssd;
        $record->sd = $this->sd;
        $record->mean = $this->currentmean;
        $record->sumduration = $this->currentsum;
        $record->maxduration = $this->currentmax;
        return $record;
    }
}
