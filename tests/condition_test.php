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

use core_availability\mock_info;
use core_availability\tree;
use mod_pathway\local\manager;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/availability/tests/fixtures/mock_info.php');
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->dirroot . '/course/lib.php');

/**
 * Tests for the pathway availability condition.
 *
 * @package    availability_pathway
 * @copyright  2026 Jon Bolton, Simon Lewis
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      availability_pathway
 * @covers     \availability_pathway\condition
 */
final class condition_test extends \advanced_testcase {
    /**
     * Clear mod_pathway's static answer cache between tests; ids are reused.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        manager::reset_caches();
    }

    /**
     * Create a course, user and pathway with two options.
     *
     * @return array [course, user, instance, cm, options]
     */
    protected function create_environment(): array {
        $generator = self::getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id, 'student');

        $instance = $generator->create_module('pathway', [
            'course' => $course->id,
            'options' => ['Red', 'Blue'],
        ]);
        $cm = get_fast_modinfo($course)->get_cm($instance->cmid);
        $options = array_values(manager::get_options($instance->id));

        return [$course, $user, $instance, $cm, $options];
    }

    public function test_constructor_validates_structure(): void {
        $condition = new condition((object) ['cm' => 7, 'option' => 3]);
        $this->assertEquals((object) ['type' => 'pathway', 'cm' => 7, 'option' => 3], $condition->save());

        // The option defaults to "any".
        $condition = new condition((object) ['cm' => 7]);
        $this->assertEquals(0, $condition->save()->option);

        $this->expectException(\coding_exception::class);
        new condition((object) ['option' => 3]);
    }

    public function test_constructor_rejects_non_numeric_cm(): void {
        $this->expectException(\coding_exception::class);
        new condition((object) ['cm' => 'seven']);
    }

    public function test_constructor_rejects_non_numeric_option(): void {
        $this->expectException(\coding_exception::class);
        new condition((object) ['cm' => 7, 'option' => 'three']);
    }

    public function test_get_json(): void {
        $this->assertEquals(
            (object) ['type' => 'pathway', 'cm' => 4, 'option' => 9],
            condition::get_json(4, 9)
        );
        $this->assertEquals(0, condition::get_json(4)->option);
    }

    public function test_is_available(): void {
        $this->resetAfterTest();
        [$course, $user, $instance, $cm, $options] = $this->create_environment();
        $info = new mock_info($course, $user->id);

        $any = new condition(condition::get_json($cm->id));
        $red = new condition(condition::get_json($cm->id, (int) $options[0]->id));
        $blue = new condition(condition::get_json($cm->id, (int) $options[1]->id));

        // No answer yet.
        $this->assertFalse($any->is_available(false, $info, false, $user->id));
        $this->assertTrue($any->is_available(true, $info, false, $user->id));

        manager::save_answer($instance, $cm, (int) $options[0]->id, $user->id);

        $this->assertTrue($any->is_available(false, $info, false, $user->id));
        $this->assertTrue($red->is_available(false, $info, false, $user->id));
        $this->assertFalse($blue->is_available(false, $info, false, $user->id));
        $this->assertFalse($red->is_available(true, $info, false, $user->id));
        $this->assertTrue($blue->is_available(true, $info, false, $user->id));
    }

    public function test_is_available_fails_closed_when_activity_missing(): void {
        $this->resetAfterTest();
        [$course, $user] = $this->create_environment();
        $info = new mock_info($course, $user->id);

        $condition = new condition(condition::get_json(999999));
        $this->assertFalse($condition->is_available(false, $info, false, $user->id));
    }

    public function test_get_description(): void {
        $this->resetAfterTest();
        [$course, $user, , $cm, $options] = $this->create_environment();
        $info = new mock_info($course, $user->id);

        // The raw description carries the core placeholder; format_info()
        // resolves it to the activity name.
        $any = new condition(condition::get_json($cm->id));
        $description = $info->format_info($any->get_description(true, false, $info), $course);
        $this->assertStringContainsString($cm->name, $description);

        $red = new condition(condition::get_json($cm->id, (int) $options[0]->id));
        $description = $info->format_info($red->get_description(true, false, $info), $course);
        $this->assertStringContainsString('Red', $description);
        $this->assertStringContainsString($cm->name, $description);

        $notdescription = $info->format_info($red->get_description(true, true, $info), $course);
        $this->assertNotEquals($description, $notdescription);
    }

    public function test_get_description_with_missing_targets(): void {
        $this->resetAfterTest();
        [$course, $user, , $cm] = $this->create_environment();
        $info = new mock_info($course, $user->id);

        // Missing activity.
        $condition = new condition(condition::get_json(999999, 1));
        $this->assertStringContainsString(
            get_string('missing', 'availability_pathway'),
            $condition->get_description(true, false, $info)
        );

        // An option belonging to a different pathway instance must not have
        // its name shown against this activity.
        $other = self::getDataGenerator()->create_module('pathway', [
            'course' => $course->id,
            'options' => ['Foreign'],
        ]);
        $foreignoption = array_values(manager::get_options($other->id))[0];

        $condition = new condition(condition::get_json($cm->id, (int) $foreignoption->id));
        $description = $condition->get_description(true, false, $info);
        $this->assertStringNotContainsString('Foreign', $description);
        $this->assertStringContainsString(
            get_string('missingoption', 'availability_pathway'),
            $description
        );
    }

    public function test_is_applied_to_user_lists(): void {
        $condition = new condition(condition::get_json(1));
        $this->assertTrue($condition->is_applied_to_user_lists());
    }

    public function test_filter_user_list(): void {
        $this->resetAfterTest();
        [$course, $user1, $instance, $cm, $options] = $this->create_environment();

        $generator = self::getDataGenerator();
        $user2 = $generator->create_user();
        $user3 = $generator->create_user();
        $generator->enrol_user($user2->id, $course->id, 'student');
        $generator->enrol_user($user3->id, $course->id, 'student');

        // User 1 chose Red, user 2 chose Blue, user 3 has not chosen.
        manager::save_answer($instance, $cm, (int) $options[0]->id, $user1->id);
        manager::save_answer($instance, $cm, (int) $options[1]->id, $user2->id);

        $info = new mock_info($course, $user1->id);
        $checker = new \core_availability\capability_checker($info->get_context());
        $users = [$user1->id => $user1, $user2->id => $user2, $user3->id => $user3];

        // Any option: everyone who has chosen.
        $any = new condition(condition::get_json($cm->id));
        $this->assertEqualsCanonicalizing(
            [$user1->id, $user2->id],
            array_keys($any->filter_user_list($users, false, $info, $checker))
        );
        $this->assertEqualsCanonicalizing(
            [$user3->id],
            array_keys($any->filter_user_list($users, true, $info, $checker))
        );

        // A specific option: only its choosers.
        $red = new condition(condition::get_json($cm->id, (int) $options[0]->id));
        $this->assertEqualsCanonicalizing(
            [$user1->id],
            array_keys($red->filter_user_list($users, false, $info, $checker))
        );
        $this->assertEqualsCanonicalizing(
            [$user2->id, $user3->id],
            array_keys($red->filter_user_list($users, true, $info, $checker))
        );

        // Missing target activity fails closed, like is_available().
        $missing = new condition(condition::get_json(999999));
        $this->assertSame([], $missing->filter_user_list($users, false, $info, $checker));
        $this->assertEqualsCanonicalizing(
            [$user1->id, $user2->id, $user3->id],
            array_keys($missing->filter_user_list($users, true, $info, $checker))
        );
    }

    public function test_update_dependency_id(): void {
        $condition = new condition(condition::get_json(10, 20));

        $this->assertFalse($condition->update_dependency_id('course_modules', 99, 100));
        $this->assertTrue($condition->update_dependency_id('course_modules', 10, 11));
        $this->assertTrue($condition->update_dependency_id('pathway_option', 20, 21));
        $this->assertFalse($condition->update_dependency_id('groups', 20, 22));

        $saved = $condition->save();
        $this->assertEquals(11, $saved->cm);
        $this->assertEquals(21, $saved->option);
    }

    public function test_duplication_preserves_the_condition(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enableavailability', 1);

        [$course, , , $cm, $options] = $this->create_environment();

        $page = self::getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'availability' => json_encode(tree::get_root_json([
                condition::get_json($cm->id, (int) $options[0]->id),
            ])),
        ]);
        $pagecm = get_fast_modinfo($course)->get_cm($page->cmid);

        // Duplicating the restricted page must keep the untouched pathway ids,
        // even though the pathway itself is not part of the activity backup.
        $newcm = duplicate_module($course, $pagecm);

        $availability = json_decode($DB->get_field('course_modules', 'availability', ['id' => $newcm->id]));
        $this->assertEquals($cm->id, $availability->c[0]->cm);
        $this->assertEquals($options[0]->id, $availability->c[0]->option);
    }

    public function test_course_restore_remaps_the_condition(): void {
        global $CFG, $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('enableavailability', 1);

        // Keep the unpacked backup directory, as the restore controller
        // consumes it directly rather than the packaged .mbz file.
        $CFG->keeptempdirectoriesonbackup = true;

        [$course, , , $cm, $options] = $this->create_environment();

        self::getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'name' => 'Restricted page',
            'availability' => json_encode(tree::get_root_json([
                condition::get_json($cm->id, (int) $options[0]->id),
            ])),
        ]);

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id
        );
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        $newcourseid = \restore_dbops::create_new_course('Restored', 'RSTAV1', $course->category);
        $rc = new \restore_controller(
            $backupid,
            $newcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $USER->id,
            \backup::TARGET_NEW_COURSE
        );
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        $newmodinfo = get_fast_modinfo($newcourseid);
        $newpathwaycm = null;
        $newpagecm = null;
        foreach ($newmodinfo->cms as $newcm) {
            if ($newcm->modname === 'pathway') {
                $newpathwaycm = $newcm;
            }
            if ($newcm->modname === 'page') {
                $newpagecm = $newcm;
            }
        }
        $this->assertNotNull($newpathwaycm);
        $this->assertNotNull($newpagecm);

        $newoptions = array_values(manager::get_options((int) $newpathwaycm->instance));
        $availability = json_decode($DB->get_field('course_modules', 'availability', ['id' => $newpagecm->id]));

        // Both ids must point at the restored copies, not the originals.
        $this->assertEquals($newpathwaycm->id, $availability->c[0]->cm);
        $this->assertEquals($newoptions[0]->id, $availability->c[0]->option);
        $this->assertNotEquals($cm->id, $availability->c[0]->cm);
        $this->assertNotEquals($options[0]->id, $availability->c[0]->option);
    }
}
