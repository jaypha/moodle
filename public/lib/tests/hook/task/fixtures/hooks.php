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

/**
 * Fixtures for task test hooks.
 *
 * @package   core
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\hook\task\scheduled_task_starting;
use core\hook\task\scheduled_task_complete;
use core\hook\task\adhoc_task_starting;
use core\hook\task\adhoc_task_complete;
use core\hook\task\task_execute_hook_test;

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => scheduled_task_starting::class,
        'callback' => [
            task_execute_hook_test::class,
            'scheduled_task_starting',
        ],
    ],
    [
        'hook' => scheduled_task_complete::class,
        'callback' => [
            task_execute_hook_test::class,
            'scheduled_task_complete',
        ],
    ],
    [
        'hook' => adhoc_task_starting::class,
        'callback' => [
            task_execute_hook_test::class,
            'adhoc_task_starting',
        ],
    ],
    [
        'hook' => adhoc_task_complete::class,
        'callback' => [
            task_execute_hook_test::class,
            'adhoc_task_complete',
        ],
    ],
];
