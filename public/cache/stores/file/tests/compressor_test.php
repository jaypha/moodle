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

namespace cachestore_file;

use core_cache\definition;
use core_cache\store;

defined('MOODLE_INTERNAL') || die();

// Include the necessary evils.
global $CFG;
require_once($CFG->dirroot . '/cache/tests/fixtures/stores.php');
require_once($CFG->dirroot . '/cache/stores/file/lib.php');

/**
 * File cache test - compressor settings.
 *
 * @package   cachestore_file
 * @author    Jason den Dulk <jasondendulk@catalyst-au.net>
 * @copyright 2026 Catalyst IT Australia {@link http://www.catalyst-au.net}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \cachestore_file
 */
final class compressor_test extends \cachestore_tests
{
    /**
     * Returns the file class name
     * @return string
     */
    protected function get_class_name() {
        return 'cachestore_file';
    }

    /**
     * Create a cachestore.
     *
     * @param int $compressor
     * @return \cachestore_file
     */
    public function create_store(int $compressor): \cachestore_file {
        /** @var definition $definition */
        $definition = definition::load_adhoc(store::MODE_APPLICATION, 'cachestore_file', 'phpunit_test');
        $config = \cachestore_file::unit_test_configuration();
        $config['compressor'] = $compressor;
        $store = new \cachestore_file('Test', $config);
        $store->initialise($definition);

        return $store;
    }

    /**
     * Run tests for store with compression.
     *
     * @return void
     */
    public function test_compressor(): void {
        $store = $this->create_store(\cachestore_file::COMPRESSOR_NONE);
        $this->run_tests($store);
        $store->purge();

        $store = $this->create_store(\cachestore_file::COMPRESSOR_PHP_GZIP);
        $this->run_tests($store);
        $store->purge();

        if (extension_loaded('zstd')) {
            $store = $this->create_store(\cachestore_file::COMPRESSOR_PHP_ZSTD);
            $this->run_tests($store);
            $store->purge();
        }
    }
}
