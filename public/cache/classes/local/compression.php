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

namespace core_cache\local;

/**
 * A trait to handle compression and decompression of values.
 *
 * @package    core_cache
 * @copyright  2026 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait compression
{
    /**
     * Compressor: none.
     */
    const COMPRESSOR_NONE = 0;

    /**
     * Compressor: PHP GZip.
     */
    const COMPRESSOR_PHP_GZIP = 1;

    /**
     * Compressor: PHP Zstandard. This requires the Zstandard extension to be installed.
     */
    const COMPRESSOR_PHP_ZSTD = 2;

    /**
     * Compressor to use.
     *
     * @var int
     */
    protected $compressor = self::COMPRESSOR_NONE;

    /**
     * Gets an array of options to use as the compressor.
     *
     * @return array
     */
    public static function config_get_compressor_options(): array {
        $arr = [
            self::COMPRESSOR_NONE     => get_string('compressor_none', 'core_cache'),
            self::COMPRESSOR_PHP_GZIP => get_string('compressor_php_gzip', 'core_cache'),
        ];

        // Check if the Zstandard PHP extension is installed.
        if (extension_loaded('zstd')) {
            $arr[self::COMPRESSOR_PHP_ZSTD] = get_string('compressor_php_zstd', 'core_cache');
        }

        return $arr;
    }

    /**
     * Adds edit form elements for compression.
     *
     * @param \MoodleQuickForm $mform
     * @return \html_quickform_element|object
     */
    public static function add_compression_edit_form_elements(\MoodleQuickForm $mform) {
        $compressoroptions = self::config_get_compressor_options();
        $element = $mform->addElement('select', 'compressor', get_string('usecompressor', 'core_cache'), $compressoroptions);
        $mform->addHelpButton('compressor', 'usecompressor', 'core_cache');
        $mform->setDefault('compressor', self::COMPRESSOR_NONE);
        $mform->setType('compressor', PARAM_INT);
        return $element;
    }

    /**
     * Compress the given value.
     *
     * @param string $value
     * @return string|false
     */
    protected function compress(string $value) {
        switch ($this->compressor) {
            case self::COMPRESSOR_NONE:
                return $value;

            case self::COMPRESSOR_PHP_GZIP:
                return gzencode($value);

            case self::COMPRESSOR_PHP_ZSTD:
                return zstd_compress($value);

            default:
                debugging("Invalid compressor: {$this->compressor}");
                return $value;
        }
    }

    /**
     * Uncompress the data.
     *
     * @param string|false $value
     * @return string|false
     */
    protected function uncompress($value) {
        if ($value === false) {
            return false;
        }

        switch ($this->compressor) {
            case self::COMPRESSOR_NONE:
                break;
            case self::COMPRESSOR_PHP_GZIP:
                $value = gzdecode($value);
                break;
            case self::COMPRESSOR_PHP_ZSTD:
                $value = zstd_uncompress($value);
                break;
            default:
                debugging("Invalid compressor: {$this->compressor}");
        }

        return $value;
    }
}
