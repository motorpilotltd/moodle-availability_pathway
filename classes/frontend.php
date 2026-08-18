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

namespace availability_pathway;

use mod_pathway\local\manager;

/**
 * Editing form support for availability_pathway.
 *
 * @package    availability_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class frontend extends \core_availability\frontend {
    /**
     * Strings required by the JavaScript.
     *
     * @return array
     */
    protected function get_javascript_strings() {
        return ['label_cm', 'label_option', 'anyoption', 'error_selectcm'];
    }

    /**
     * Parameters passed to the JavaScript initialiser.
     *
     * @param \stdClass $course The course.
     * @param \cm_info|null $cm The course module being edited.
     * @param \section_info|null $section The section being edited.
     * @return array
     */
    protected function get_javascript_init_params(
        $course,
        ?\cm_info $cm = null,
        ?\section_info $section = null
    ) {
        return [$this->get_choice_activities($course, $cm)];
    }

    /**
     * Only offer the condition when there is something to point it at.
     *
     * @param \stdClass $course The course.
     * @param \cm_info|null $cm The course module being edited.
     * @param \section_info|null $section The section being edited.
     * @return bool
     */
    protected function allow_add($course, ?\cm_info $cm = null, ?\section_info $section = null) {
        return !empty($this->get_choice_activities($course, $cm));
    }

    /**
     * Return the pathway activities in the course with their options.
     *
     * @param \stdClass $course The course.
     * @param \cm_info|null $cm The course module being edited, which is excluded.
     * @return array
     */
    protected function get_choice_activities($course, ?\cm_info $cm = null): array {
        $modinfo = get_fast_modinfo($course);
        $activities = [];

        foreach ($modinfo->get_instances_of('pathway') as $othercm) {
            if ($cm && (int) $cm->id === (int) $othercm->id) {
                continue;
            }
            if (!empty($othercm->deletioninprogress)) {
                continue;
            }

            $options = [];
            foreach (manager::get_options((int) $othercm->instance) as $option) {
                $options[] = (object) [
                    'id' => (int) $option->id,
                    'name' => format_string($option->text, true, ['context' => $othercm->context]),
                ];
            }

            // A pathway with no options cannot be chosen in, so a condition
            // pointing at it could never be met. Leave it out of the picker.
            if (empty($options)) {
                continue;
            }

            $activities[] = (object) [
                'id' => (int) $othercm->id,
                'name' => $othercm->get_formatted_name(),
                'options' => $options,
            ];
        }

        return $activities;
    }
}
