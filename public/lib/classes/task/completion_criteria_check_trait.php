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
 * Trait to share timefrom methods common to criteria tasks.
 *
 * @package   core
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait completion_criteria_check_trait {
    /**
     * Calculate the timefrom to pass to the completion check.
     *
     * @param string $type
     * @return int|null
     * @throws \dml_exception
     */
    protected function get_timefrom(string $type): ?int {
        // We only want completions performed since the last task run. We need the start time so there are no gaps.
        $laststarttime = get_config('core', 'completion_criteria_' . $type . '_check_lasttime');
        if ($laststarttime) {
            // We prefer to get the actual start time for the previous run, which we can get from the config, if
            // available. We subtract 1, just to be sure there are no gaps.
            $timefrom = $laststarttime - 1;
        } else {
            // We don't know the start time. So set the time to an hour before the last run (finish time) to
            // minimise the chance of a gap.
            $timefrom = $this->get_last_run_time() - HOURSECS;
        }
        // This would only happen when there are no previous runs.
        if ($timefrom <= 0) {
            $timefrom = null;
        }

        return $timefrom;
    }

    /**
     * Update the timefrom for the next run.
     *
     * @param string $type
     */
    protected function update_timefrom(string $type) {
        set_config('completion_criteria_' . $type . '_check_lasttime', $this->get_timestarted());
    }
}
