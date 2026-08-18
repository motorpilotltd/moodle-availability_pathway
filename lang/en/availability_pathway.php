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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Language strings for availability_pathway.
 *
 * @package    availability_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['anyoption'] = 'any option (meets the completion rule)';
$string['description'] = 'Require the user to have made a particular selection in a pathway activity.';
$string['error_selectcm'] = 'You must select a pathway activity.';
$string['label_cm'] = 'Pathway activity';
$string['label_option'] = 'Required option';
$string['missing'] = '(Missing activity)';
$string['missingoption'] = '(Missing option)';
$string['pluginname'] = 'Restriction by pathway';
$string['privacy:metadata'] = 'The Restriction by pathway plugin does not store any personal data.';
$string['requires_any'] = 'You have made a choice in <strong>{$a}</strong>';
$string['requires_not_any'] = 'You have not made a choice in <strong>{$a}</strong>';
$string['requires_not_option'] = 'You did not choose <strong>{$a->option}</strong> in <strong>{$a->activity}</strong>';
$string['requires_option'] = 'You chose <strong>{$a->option}</strong> in <strong>{$a->activity}</strong>';
$string['title'] = 'Pathway';
