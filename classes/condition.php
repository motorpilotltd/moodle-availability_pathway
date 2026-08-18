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

use core_availability\capability_checker;
use core_availability\info;
use mod_pathway\local\manager;

/**
 * Availability condition based on a selection made in a pathway activity.
 *
 * With no option specified the condition is equivalent to the activity's own
 * completion rule (the user has made a choice). Specifying an option narrows it
 * to one branch, which is the part core's completion condition cannot express.
 *
 * @package    availability_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core_availability\condition {
    /** @var int Course module id of the pathway activity. */
    protected $cmid;

    /** @var int Required option id, or 0 for any option. */
    protected $optionid;

    /**
     * Build the condition from its saved structure.
     *
     * @param \stdClass $structure The decoded JSON structure.
     * @throws \coding_exception If the structure is malformed.
     */
    public function __construct($structure) {
        if (!isset($structure->cm) || !is_number($structure->cm)) {
            throw new \coding_exception('Missing or invalid ->cm for pathway condition');
        }
        $this->cmid = (int) $structure->cm;

        if (isset($structure->option) && !is_number($structure->option)) {
            throw new \coding_exception('Invalid ->option for pathway condition');
        }
        $this->optionid = (int) ($structure->option ?? 0);
    }

    /**
     * Return the structure to save.
     *
     * @return \stdClass
     */
    public function save() {
        return (object) [
            'type' => 'pathway',
            'cm' => $this->cmid,
            'option' => $this->optionid,
        ];
    }

    /**
     * Build a structure for use in unit tests and generators.
     *
     * @param int $cmid The pathway course module id.
     * @param int $optionid The required option id, or 0 for any.
     * @return \stdClass
     */
    public static function get_json(int $cmid, int $optionid = 0) {
        return (object) ['type' => 'pathway', 'cm' => $cmid, 'option' => $optionid];
    }

    /**
     * Decide whether the condition is met.
     *
     * @param bool $not Whether the condition is negated.
     * @param info $info Availability info for the item being checked.
     * @param bool $grabthelot Whether to prefetch for many users.
     * @param int $userid The user id.
     * @return bool
     */
    public function is_available($not, info $info, $grabthelot, $userid) {
        $modinfo = $info->get_modinfo();
        $allow = false;

        if (array_key_exists($this->cmid, $modinfo->cms)) {
            $othercm = $modinfo->cms[$this->cmid];
            if ($othercm->modname === 'pathway') {
                $allow = manager::has_selected_option((int) $othercm->instance, $this->optionid, $userid);
            }
        }

        if ($not) {
            $allow = !$allow;
        }

        return $allow;
    }

    /**
     * Describe the condition for display.
     *
     * @param bool $full Whether to show the full description.
     * @param bool $not Whether the condition is negated.
     * @param info $info Availability info.
     * @return string
     */
    public function get_description($full, $not, info $info) {
        $modinfo = $info->get_modinfo();
        $othercm = null;

        if (!array_key_exists($this->cmid, $modinfo->cms)) {
            $activity = get_string('missing', 'availability_pathway');
        } else {
            // Core's info::format_info() swaps this placeholder for the
            // correctly escaped, current activity name (linked where apt).
            $othercm = $modinfo->cms[$this->cmid];
            $activity = '<AVAILABILITY_CMNAME_' . $othercm->id . '/>';
        }

        if (!$this->optionid) {
            $key = $not ? 'requires_not_any' : 'requires_any';
            return get_string($key, 'availability_pathway', $activity);
        }

        $optiontext = $this->get_option_text($othercm);
        $key = $not ? 'requires_not_option' : 'requires_option';

        return get_string($key, 'availability_pathway', (object) [
            'activity' => $activity,
            'option' => $optiontext,
        ]);
    }

    /**
     * Return the display text of the required option.
     *
     * The option must belong to the referenced pathway activity: a stale or
     * foreign optionid would otherwise put an unrelated option's name in the
     * description while is_available() correctly denies access.
     *
     * @param \cm_info|null $othercm The referenced pathway course module, if it exists.
     * @return string
     */
    protected function get_option_text(?\cm_info $othercm): string {
        global $DB;

        if (!$othercm) {
            return get_string('missingoption', 'availability_pathway');
        }

        $option = $DB->get_record('pathway_option', ['id' => $this->optionid]);
        if (!$option || (int) $option->pathwayid !== (int) $othercm->instance) {
            return get_string('missingoption', 'availability_pathway');
        }

        return format_string(
            $option->text,
            true,
            ['context' => \context_module::instance($othercm->id)]
        );
    }

    /**
     * Debug string used by unit tests.
     *
     * @return string
     */
    protected function get_debug_string() {
        return 'cm' . $this->cmid . ' option' . $this->optionid;
    }

    /**
     * Take part in filtering user lists (grading tables, reports, messaging).
     *
     * A recorded choice is treated as branch membership, so lists for a
     * restricted activity show the branch roster rather than the whole
     * class, matching what a restriction on the mapped group would do.
     * Core adds users with moodle/course:ignoreavailabilityrestrictions
     * back itself, so staff are never filtered out.
     *
     * @return bool
     */
    public function is_applied_to_user_lists() {
        return true;
    }

    /**
     * Filter a list of users down to those who meet the condition.
     *
     * Mirrors is_available(): a missing or non-pathway target means nobody
     * matches (everybody, when negated).
     *
     * @param array $users Users indexed by id.
     * @param bool $not Whether the condition is negated.
     * @param info $info Availability info for the item being checked.
     * @param capability_checker $checker Checker for capabilities in this context.
     * @return array The filtered users, still indexed by id.
     */
    public function filter_user_list(array $users, $not, info $info, capability_checker $checker) {
        global $DB;

        $modinfo = $info->get_modinfo();
        $instanceid = 0;
        if (array_key_exists($this->cmid, $modinfo->cms)) {
            $othercm = $modinfo->cms[$this->cmid];
            if ($othercm->modname === 'pathway') {
                $instanceid = (int) $othercm->instance;
            }
        }

        $chosen = [];
        if ($instanceid) {
            $params = ['pathwayid' => $instanceid];
            if ($this->optionid) {
                $params['optionid'] = $this->optionid;
            }
            $chosen = $DB->get_records_menu('pathway_answer', $params, '', 'userid, id');
        }

        $result = [];
        foreach ($users as $id => $user) {
            $match = isset($chosen[$id]);
            if ($not) {
                $match = !$match;
            }
            if ($match) {
                $result[$id] = $user;
            }
        }

        return $result;
    }

    /**
     * Update the stored course module id after a restore.
     *
     * @param string $table The table being remapped.
     * @param int $oldid The old id.
     * @param int $newid The new id.
     * @return bool Whether anything changed.
     */
    public function update_dependency_id($table, $oldid, $newid) {
        if ($table === 'course_modules' && (int) $this->cmid === (int) $oldid) {
            $this->cmid = $newid;
            return true;
        }
        if ($table === 'pathway_option' && (int) $this->optionid === (int) $oldid) {
            $this->optionid = $newid;
            return true;
        }
        return false;
    }

    /**
     * Ask to be included in the after-restore pass so ids can be remapped.
     *
     * @param int $restoreid The restore id.
     * @param int $courseid The course id.
     * @param \base_logger $logger The logger.
     * @param string $name The item name.
     * @param \base_task $task The restore task.
     * @return bool
     */
    public function include_after_restore(
        $restoreid,
        $courseid,
        \base_logger $logger,
        $name,
        \base_task $task
    ) {
        return true;
    }

    /**
     * Remap ids after a restore, warning if the target activity is missing.
     *
     * @param string $restoreid The restore id.
     * @param int $courseid The course id.
     * @param \base_logger $logger The logger.
     * @param string $name The item name.
     * @return bool Whether the condition changed.
     */
    public function update_after_restore($restoreid, $courseid, \base_logger $logger, $name) {
        global $DB;

        $rec = \restore_dbops::get_backup_ids_record($restoreid, 'course_module', $this->cmid);
        if (!$rec || !$rec->newitemid) {
            // The pathway activity is not in this backup. On a same-course
            // restore (duplicating a restricted activity) the existing ids
            // are still valid, so keep the condition untouched.
            if ($DB->record_exists('course_modules', ['id' => $this->cmid, 'course' => $courseid])) {
                return false;
            }
            $this->cmid = 0;
            $this->optionid = 0;
            $logger->process(
                "Restored item ($name) has availability condition on module that was not restored",
                \backup::LOG_WARNING
            );
            return true;
        }

        $this->cmid = (int) $rec->newitemid;

        if ($this->optionid) {
            $optionrec = \restore_dbops::get_backup_ids_record($restoreid, 'pathway_option', $this->optionid);
            if ($optionrec && $optionrec->newitemid) {
                $this->optionid = (int) $optionrec->newitemid;
            } else if (!$DB->record_exists('pathway_option', ['id' => $this->optionid])) {
                // Do not silently widen "chose option X" to "chose anything":
                // record in the restore log that the branch link was lost.
                $logger->process(
                    "Restored item ($name) has availability condition on a pathway option " .
                        "that was not restored; falling back to any option",
                    \backup::LOG_WARNING
                );
                $this->optionid = 0;
            }
        }

        return true;
    }
}
