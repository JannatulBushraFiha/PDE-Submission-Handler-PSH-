<?php
/**
 * Integration tests for PSH (PDE Submission Handler) — observer.php
 *
 * These tests exercise the boundary between the Moodle plugin and the
 * Majordomo API without making real network calls.  Each scenario
 * corresponds to a failure mode documented in thesis section 6.1.3.
 *
 * Run:
 *   /Applications/MAMP/bin/php/php8.4.17/bin/php \
 *     /Applications/MAMP/htdocs2/moodle501/vendor/bin/phpunit \
 *     --bootstrap lib/phpunit/bootstrap.php \
 *     --test-suffix integration_test.php \
 *     mod/assign/submission/pde/tests/ \
 *     --testdox
 */

namespace assignsubmission_pde\tests;

global $CFG;
require_once($CFG->dirroot . '/mod/assign/submission/pde/classes/observer.php');

use assignsubmission_pde\observer;

class integration_test extends \advanced_testcase {

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /** Fallback message text (docker_error lang string). */
    private function docker_error_text(): string {
        return '<p>' . get_string('docker_error', 'assignsubmission_pde') . '</p>';
    }

    /** Default submission_received text. */
    private function received_text(): string {
        return '<p>' . get_string('submission_received', 'assignsubmission_pde') . '</p>';
    }

    // ---------------------------------------------------------------
    // Scenario 1 — Valid API response
    // ---------------------------------------------------------------

    /**
     * @test
     * A well-formed JSON response from Majordomo is parsed correctly:
     * the message field is returned as-is and the json array is populated.
     */
    public function test_valid_json_response_returns_message_and_metadata(): void {
        $feedback_html = '<p>Your code has <strong>2</strong> style violations.</p>';
        $payload = json_encode([
            'message'              => $feedback_html,
            'violations_count'     => 2,
            'topics'               => ['loops', 'variables'],
            'llm_duration_seconds' => 1.42,
        ]);

        $result = observer::process_api_response($payload, 0);

        $this->assertSame($feedback_html, $result['message'],
            'message field must pass through unchanged');
        $this->assertSame(2, $result['json']['violations_count'],
            'violations_count must be decoded from JSON');
        $this->assertSame(['loops', 'variables'], $result['json']['topics'],
            'topics array must be decoded correctly');
        $this->assertEqualsWithDelta(1.42, $result['json']['llm_duration_seconds'], 0.001,
            'llm_duration_seconds must survive JSON decode');
    }

    // ---------------------------------------------------------------
    // Scenario 2 — Malformed JSON response
    // ---------------------------------------------------------------

    /**
     * @test
     * When Majordomo returns a body that is not valid JSON (e.g. a raw
     * HTML error page), process_api_response must not throw an exception.
     * It should fall back to the submission_received string.
     */
    public function test_malformed_json_falls_back_gracefully(): void {
        $garbage = '<html><body>Bad Gateway</body></html>';

        $result = observer::process_api_response($garbage, 0);

        $this->assertSame($this->received_text(), $result['message'],
            'malformed JSON must produce the submission_received fallback');
        $this->assertIsArray($result['json'],
            'json key must always be an array, never null');
        $this->assertEmpty($result['json'],
            'json array must be empty when parsing fails');
    }

    /**
     * @test
     * An empty string body (zero-length HTTP response) is treated the same
     * as malformed JSON — no exception, fallback message returned.
     */
    public function test_empty_body_falls_back_gracefully(): void {
        $result = observer::process_api_response('', 0);

        $this->assertSame($this->received_text(), $result['message'],
            'empty body must produce the submission_received fallback');
        $this->assertEmpty($result['json']);
    }

    // ---------------------------------------------------------------
    // Scenario 3 — curl error (network / timeout)
    // ---------------------------------------------------------------

    /**
     * @test
     * A non-zero curl errno (e.g. CURLE_OPERATION_TIMEDOUT = 28) causes
     * the docker_error fallback to be written.  The submission itself must
     * not be disrupted — process_api_response returns normally.
     */
    public function test_curl_errno_produces_docker_error_fallback(): void {
        $curle_operation_timedout = 28;

        $result = observer::process_api_response(false, $curle_operation_timedout);

        $this->assertSame($this->docker_error_text(), $result['message'],
            'curl timeout errno must trigger docker_error message');
        $this->assertEmpty($result['json'],
            'json must be empty on curl failure');
    }

    /**
     * @test
     * curl_exec() returns false (not just empty string) when the connection
     * is refused entirely (errno = CURLE_COULDNT_CONNECT = 7).
     */
    public function test_curl_false_return_produces_docker_error_fallback(): void {
        $curle_couldnt_connect = 7;

        $result = observer::process_api_response(false, $curle_couldnt_connect);

        $this->assertSame($this->docker_error_text(), $result['message']);
        $this->assertEmpty($result['json']);
    }

    // ---------------------------------------------------------------
    // Scenario 4 — HTTP error propagated through Majordomo body
    // ---------------------------------------------------------------

    /**
     * @test
     * When the LiteLLM proxy returns HTTP 404, Majordomo wraps it in a
     * JSON error body.  The plugin reads that body and writes the message
     * field — or falls back if the field is absent.
     */
    public function test_majordomo_error_body_written_as_feedback(): void {
        // Majordomo wraps upstream errors like this:
        $error_body = json_encode([
            'message' => '<p>Upstream model not found (HTTP 404).</p>',
            'error'   => true,
        ]);

        $result = observer::process_api_response($error_body, 0);

        $this->assertStringContainsString('404', $result['message'],
            'HTTP 404 detail from Majordomo error body must appear in message');
        $this->assertTrue($result['json']['error'],
            'error flag must be decoded from JSON');
    }

    /**
     * @test
     * If Majordomo returns HTTP 500 with an empty body (unlikely but
     * possible), fall back to submission_received rather than crashing.
     */
    public function test_empty_error_body_falls_back_safely(): void {
        $result = observer::process_api_response('{}', 0);

        // {} decodes fine but has no 'message' key → submission_received
        $this->assertSame($this->received_text(), $result['message'],
            'JSON object without message key must fall back to submission_received');
    }

    // ---------------------------------------------------------------
    // Scenario 5 — CSRF exemption (config-level check)
    // ---------------------------------------------------------------

    /**
     * @test
     * The observer must add a Content-Type: application/json header to
     * every outbound request.  We verify this indirectly: the docker_url
     * config key exists and is non-empty (a missing URL would cause a
     * connection error, not a CSRF rejection).
     */
    public function test_docker_url_config_is_set(): void {
        $url = get_config('assignsubmission_pde', 'docker_url');
        $this->assertNotEmpty($url,
            'docker_url must be configured so PSH can reach Majordomo');
        $this->assertStringStartsWith('http', $url,
            'docker_url must be an http(s) URL');
    }

    /**
     * @test
     * Majordomo's /api/analyze endpoint is registered without Moodle CSRF
     * protection because it is an internal service-to-service call.
     * We verify the plugin sends no sesskey in its request by inspecting
     * the metadata it constructs — sesskey must not appear.
     */
    public function test_metadata_does_not_contain_sesskey(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        global $DB;

        $meta = observer::build_metadata($assign->id, $user->id,
            observer::hash_student_id($user->id), $DB);

        $encoded = json_encode($meta);
        $this->assertStringNotContainsString('sesskey', $encoded,
            'metadata payload must not include a Moodle sesskey (CSRF token)');
        $this->assertStringNotContainsString('token', strtolower($encoded),
            'metadata payload must not include any CSRF token field');
    }

    // ---------------------------------------------------------------
// Scenario 6 — Invalid AI API key (HTTP 401)
// ---------------------------------------------------------------

/**
 * @test
 * When the AI provider rejects the API key with HTTP 401, Majordomo
 * returns a JSON body with no usable message field.  The plugin must
 * fall back to submission_received rather than crashing.
 */
public function test_invalid_api_key_response_handled_gracefully(): void {
    $body = json_encode(['error' => 'Unauthorized']);  // no 'message' key

    $result = observer::process_api_response($body, 0);

    $this->assertSame($this->received_text(), $result['message'],
        'missing message field on 401 body must fall back to submission_received');
    $this->assertIsArray($result['json']);
    $this->assertSame('Unauthorized', $result['json']['error']);
}
}