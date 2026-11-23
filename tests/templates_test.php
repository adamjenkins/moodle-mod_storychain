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

namespace mod_storychain;

use mod_storychain\local\chain_manager;
use mod_storychain\local\state;

/**
 * Every template renders from real state, with every string found and sentences escaped.
 *
 * @package    mod_storychain
 * @category   test
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class templates_test extends \advanced_testcase {
    use \mod_storychain\tests\fixture_trait;

    /**
     * Render a template and check it.
     *
     * @param string $name Template name.
     * @param array $context Context.
     * @return string
     */
    protected function render(string $name, array $context): string {
        global $PAGE;
        $html = $PAGE->get_renderer('core')->render_from_template('mod_storychain/' . $name, $context);
        $this->assertStringNotContainsString('[[', $html, $name);
        $this->assertStringNotContainsString('<script>alert', $html, $name);
        return $html;
    }

    /**
     * All six templates, in every student status.
     */
    public function test_templates_render(): void {
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1900000000);
        [, $cm] = $this->make_activity([
            'chainmode' => chain_manager::MODE_PARALLEL,
            'numchains' => 2,
            'titles' => "Tale & <i>one</i>\n",
            'openingmode' => chain_manager::OPENING_TEACHER,
            'openings' => 'Once.',
            'contextlength' => 3,
        ]);
        $ann = $this->make_student('Ann');
        $ben = $this->make_student('Ben');
        $m = $this->manager($cm);
        $m->touch($ann->id);
        $m->touch($ben->id);
        $m->tick();
        $annchain = array_values(array_filter($m->get_chains(), fn($c) => (int) $c->writerid === (int) $ann->id))[0];
        $m->submit($ann->id, $annchain->id, 'x <script>alert(1)</script> y');
        $raw = $m->get_sentences($annchain->id, false)[0];
        $this->assertStringNotContainsString('<script>', $raw->content);

        $this->render('student', ['cmid' => $cm->id]);
        $this->render('teacher', ['cmid' => $cm->id, 'groupid' => 0]);

        $writing = state::student($m, $ben->id, true);
        $waiting = state::student($m, $ann->id, true);
        foreach ([$writing, $waiting] as $s) {
            $context = $s + ['status_' . $s['status'] => true, 'showstories' => (bool) $s['stories']];
            $this->render('student_state', $context);
        }
        $this->render('student_state', ['status_nogroup' => true] + state::student(null, $ann->id, true));

        $m->manage($annchain->id, 'close');
        $m->manage($annchain->id, 'present');
        $m->manage($annchain->id, 'next');
        $presenting = state::student($m, $ann->id, true);
        $this->assertSame('presenting', $presenting['status']);
        $this->render('student_state', $presenting + ['status_presenting' => true]);

        $teacher = state::teacher($m) + ['presenturl' => 'https://example.com/present.php?id=1&group=0'];
        $html = $this->render('teacher_state', $teacher);
        $this->assertStringContainsString('Tale &amp; one', $html);

        $chain = array_values(array_filter($teacher['chains'], fn($c) => $c['id'] === (int) $annchain->id))[0];
        $html = $this->render('present_state', ['chain' => $chain]);
        $this->assertStringContainsString('Once.', $html);
        $this->render('present', [
            'cmid' => $cm->id,
            'name' => 'Stories',
            'viewurl' => 'https://example.com/view.php?id=1',
            'hasothers' => true,
            'others' => [['heading' => 'Story 1', 'url' => 'https://example.com/x', 'current' => true]],
        ]);
    }
}
