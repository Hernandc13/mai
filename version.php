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

defined('MOODLE_INTERNAL') || die();

/**
 * Version metadata for the local_mai plugin.
 *
 * @package   local_mai
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Número de versión del plugin (AAAAMMDDVV).
$plugin->version   = 2025111312;

// Versión mínima de Moodle (4.5.0).
$plugin->requires  = 2024100700;

// Ramas soportadas: desde 4.5 hasta 5.1.
$plugin->supported = [405, 501];

$plugin->component = 'local_mai';
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0-alpha';
