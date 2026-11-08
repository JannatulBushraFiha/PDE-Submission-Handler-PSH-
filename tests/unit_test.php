<?php
namespace assignsubmission_pde\tests;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/submission/pde/classes/observer.php');

class unit_test extends \advanced_testcase {

    // -------------------------------------------------------------------------
    // 1. HMAC Hashing
    // -------------------------------------------------------------------------

    public function test_hash_student_id_is_deterministic(): void {
        $hash1 = \assignsubmission_pde\observer::hash_student_id(12345678);
        $hash2 = \assignsubmission_pde\observer::hash_student_id(12345678);
        $this->assertSame($hash1, $hash2);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash1);
    }

    public function test_hash_student_id_differs_with_different_input(): void {
        $hash1 = \assignsubmission_pde\observer::hash_student_id(11111111);
        $hash2 = \assignsubmission_pde\observer::hash_student_id(22222222);
        $this->assertNotSame($hash1, $hash2);
    }

    public function test_hash_output_is_not_raw_student_id(): void {
        $userid = 12345678;
        $hash   = \assignsubmission_pde\observer::hash_student_id($userid);
        $this->assertNotSame((string)$userid, $hash);
    }

    public function test_hash_differs_when_hmac_key_changes(): void {
        $this->resetAfterTest();
        set_config('hmac_key', 'keyA', 'assignsubmission_pde');
        $hash1 = \assignsubmission_pde\observer::hash_student_id(12345678);
        set_config('hmac_key', 'keyB', 'assignsubmission_pde');
        $hash2 = \assignsubmission_pde\observer::hash_student_id(12345678);
        $this->assertNotSame($hash1, $hash2);
    }

    // -------------------------------------------------------------------------
    // 2. Metadata Construction
    // -------------------------------------------------------------------------

    public function test_metadata_contains_required_fields(): void {
        $this->resetAfterTest();
        global $DB;
        $meta = \assignsubmission_pde\observer::build_metadata(1, 42, 'abc123hash', $DB);
        $this->assertArrayHasKey('student_id',        $meta);
        $this->assertArrayHasKey('submission_number',  $meta);
        $this->assertArrayHasKey('previous_attempts',  $meta);
        $this->assertArrayHasKey('previous_feedback',  $meta);
        $this->assertArrayHasKey('uc_settings',        $meta);
    }

    public function test_metadata_student_id_field_uses_hash_not_raw_userid(): void {
        $this->resetAfterTest();
        global $DB;
        $hash = \assignsubmission_pde\observer::hash_student_id(42);
        $meta = \assignsubmission_pde\observer::build_metadata(1, 42, $hash, $DB);
        $this->assertSame($hash, $meta['student_id']);
        $this->assertNotSame('42', $meta['student_id']);
    }

    public function test_metadata_submission_number_is_1_on_first_submission(): void {
        $this->resetAfterTest();
        global $DB;
        $meta = \assignsubmission_pde\observer::build_metadata(1, 42, 'abc123hash', $DB);
        $this->assertSame(1, $meta['submission_number']);
    }

    public function test_metadata_submission_number_increments_from_log(): void {
        $this->resetAfterTest();
        global $DB;
        $DB->insert_record('assignsubmission_pde_log', (object)[
            'assignment'       => 1,
            'userid'           => 42,
            'student_hash'     => 'hash1',
            'attempt_number'   => 1,
            'timecreated'      => time(),
            'violations_count' => 0,
            'topic_results'    => '{}',
            'feedback'         => '',
        ]);
        $DB->insert_record('assignsubmission_pde_log', (object)[
            'assignment'       => 1,
            'userid'           => 42,
            'student_hash'     => 'hash1',
            'attempt_number'   => 2,
            'timecreated'      => time(),
            'violations_count' => 0,
            'topic_results'    => '{}',
            'feedback'         => '',
        ]);
        $meta = \assignsubmission_pde\observer::build_metadata(1, 42, 'hash1', $DB);
        $this->assertSame(3, $meta['submission_number']);
    }

    public function test_metadata_previous_feedback_null_on_first_submission(): void {
        $this->resetAfterTest();
        global $DB;
        $meta = \assignsubmission_pde\observer::build_metadata(1, 42, 'abc123hash', $DB);
        $this->assertNull($meta['previous_feedback']);
    }

    public function test_metadata_previous_feedback_populated_from_log(): void {
        $this->resetAfterTest();
        global $DB;
        $DB->insert_record('assignsubmission_pde_log', (object)[
            'assignment'       => 1,
            'userid'           => 42,
            'student_hash'     => 'hash1',
            'attempt_number'   => 1,
            'timecreated'      => time(),
            'violations_count' => 0,
            'topic_results'    => '{}',
            'feedback'         => '<p>Great use of loops.</p>',
        ]);
        $meta = \assignsubmission_pde\observer::build_metadata(1, 42, 'hash1', $DB);
        $this->assertStringContainsString('Great use of loops.', $meta['previous_feedback']);
    }

    // -------------------------------------------------------------------------
    // 3. UC Settings
    // -------------------------------------------------------------------------

    public function test_uc_settings_contains_expected_topic_keys(): void {
        $this->resetAfterTest();
        global $DB;
        $meta     = \assignsubmission_pde\observer::build_metadata(1, 42, 'abc123hash', $DB);
        $uc       = $meta['uc_settings'];
        $this->assertArrayHasKey('Variables',      $uc);
        $this->assertArrayHasKey('Functions',       $uc);
        $this->assertArrayHasKey('Classes',         $uc);
        $this->assertArrayHasKey('language',        $uc);
    }

    public function test_uc_settings_language_defaults_to_processing(): void {
        $this->resetAfterTest();
        global $DB;
        $meta = \assignsubmission_pde\observer::build_metadata(1, 42, 'abc123hash', $DB);
        $this->assertSame('processing', $meta['uc_settings']['language']);
    }

    // -------------------------------------------------------------------------
    // 4. Feedback Write Logic
    // -------------------------------------------------------------------------

    public function test_feedback_write_inserts_on_first_submission(): void {
        $this->resetAfterTest();
        global $DB;
        \assignsubmission_pde\observer::write_feedback(1, 42, '<p>Good effort.</p>', $DB, FORMAT_HTML);
        $grade = $DB->get_record('assign_grades', ['assignment' => 1, 'userid' => 42]);
        $this->assertNotFalse($grade);
        $feedback = $DB->get_record('assignfeedback_comments',
            ['assignment' => 1, 'grade' => $grade->id]);
        $this->assertNotFalse($feedback);
        $this->assertStringContainsString('Good effort.', $feedback->commenttext);
    }

    public function test_feedback_write_preserves_html_tags(): void {
        $this->resetAfterTest();
        global $DB;
        \assignsubmission_pde\observer::write_feedback(
            1, 42, '<p><strong>Well done</strong> on your loops.</p>', $DB, FORMAT_HTML
        );
        $grade    = $DB->get_record('assign_grades', ['assignment' => 1, 'userid' => 42]);
        $feedback = $DB->get_record('assignfeedback_comments',
            ['assignment' => 1, 'grade' => $grade->id]);
        $this->assertStringContainsString('<strong>', $feedback->commenttext);
        $this->assertStringContainsString('</p>',     $feedback->commenttext);
    }

    public function test_feedback_write_updates_on_resubmission(): void {
        $this->resetAfterTest();
        global $DB;
        \assignsubmission_pde\observer::write_feedback(
            1, 42, '<p>First attempt feedback.</p>', $DB, FORMAT_HTML
        );
        \assignsubmission_pde\observer::write_feedback(
            1, 42, '<p>Second attempt feedback.</p>', $DB, FORMAT_HTML
        );
        $grade   = $DB->get_record('assign_grades', ['assignment' => 1, 'userid' => 42]);
        $records = $DB->get_records('assignfeedback_comments',
            ['assignment' => 1, 'grade' => $grade->id]);
        $this->assertCount(1, $records);
        $record = reset($records);
        $this->assertStringContainsString('Second attempt feedback.', $record->commenttext);
    }

// -------------------------------------------------------------------------
// 5. Submission Guard Logic
// -------------------------------------------------------------------------

public function test_plugin_enabled_by_default(): void {
    $this->resetAfterTest();
    // No config row exists for fake assignid → defaults to enabled
    $enabled = \assignsubmission_pde\observer::is_plugin_enabled(99999);
    $this->assertTrue($enabled);
}

public function test_submission_allowed_when_no_limit_set(): void {
    $this->resetAfterTest();
    global $DB;
    // No log records and no maxsubmissions config → should be allowed
    $allowed = \assignsubmission_pde\observer::is_submission_allowed(99999, 99999, $DB);
    $this->assertTrue($allowed);
}
}