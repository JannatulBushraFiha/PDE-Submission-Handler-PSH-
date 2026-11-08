<?php
namespace assignsubmission_pde;

defined('MOODLE_INTERNAL') || die();

error_log('PDE PLUGIN: observer.php loaded');

class observer {

    private static function get_docker_url(): string {
        return get_config('assignsubmission_pde', 'docker_url') ?: 'http://127.0.0.1:5003/api/analyze';
    }

    private static function get_docker_timeout(): int {
        return (int)(get_config('assignsubmission_pde', 'docker_timeout') ?: 30);
    }

    private static function get_assignment_plugin_config(int $assignid, string $name, $default = null) {
        global $DB;
        $record = $DB->get_record('assign_plugin_config', [
            'assignment' => $assignid,
            'plugin'     => 'pde',
            'subtype'    => 'assignsubmission',
            'name'       => $name,
        ]);
        return $record ? $record->value : $default;
    }

    public static function hash_student_id(int $userid): string {
        $hmac_key = get_config('assignsubmission_pde', 'hmac_key') ?: 'default-key';
        return hash_hmac('sha256', (string)$userid, $hmac_key);
    }

    public static function is_plugin_enabled(int $realassignid): bool {
    $enabled = self::get_assignment_plugin_config($realassignid, 'enabled', '1');
    return !(!$enabled || $enabled === '0');
    }

    public static function is_submission_allowed(int $realassignid, int $userid, $DB): bool {
    $maxsubmissions = (int) self::get_assignment_plugin_config($realassignid, 'maxsubmissions', 0);
        if ($maxsubmissions > 0) {
        $count = $DB->count_records('assignsubmission_pde_log', [
            'assignment' => $realassignid,
            'userid'     => $userid,
        ]);
        return $count < $maxsubmissions;
    }
       return true;
    }

    public static function write_feedback(int $realassignid, int $userid, string $message, $DB, int $format = FORMAT_HTML): void {
        $grade = $DB->get_record('assign_grades', [
            'assignment' => $realassignid,
            'userid'     => $userid,
        ], '*', IGNORE_MISSING);

        if (!$grade) {
            $gradeid = $DB->insert_record('assign_grades', (object)[
                'assignment'    => $realassignid,
                'userid'        => $userid,
                'timecreated'   => time(),
                'timemodified'  => time(),
                'grader'        => -1,
                'grade'         => -1,
                'attemptnumber' => 0,
            ]);
        } else {
            $gradeid = $grade->id;
        }

        $feedback = $DB->get_record('assignfeedback_comments', [
            'assignment' => $realassignid,
            'grade'      => $gradeid,
        ]);

        if ($feedback) {
            $feedback->commenttext   = $message;
            $feedback->commentformat = $format;
            $DB->update_record('assignfeedback_comments', $feedback);
        } else {
            $DB->insert_record('assignfeedback_comments', (object)[
                'assignment'    => $realassignid,
                'grade'         => $gradeid,
                'commenttext'   => $message,
                'commentformat' => $format,
            ]);
        }
    }

    public static function build_metadata(int $realassignid, int $userid, string $student_hash, $DB): array {
        $attempts = $DB->get_records('assignsubmission_pde_log', [
            'assignment' => $realassignid,
            'userid'     => $userid,
        ], 'attempt_number ASC');

        $previous_attempts = [];
        $attempt_number    = 1;

        if (!empty($attempts)) {
            $attempt_number = count($attempts) + 1;
            foreach ($attempts as $attempt) {
                $previous_attempts[] = [
                    'attempt'   => (int)$attempt->attempt_number,
                    'timestamp' => date('Y-m-d H:i:s', $attempt->timecreated),
                    'topics'    => $attempt->topic_results ? json_decode($attempt->topic_results, true) : [],
                ];
            }
        }

        $previous_feedback = null;
        if (!empty($attempts)) {
           foreach (array_reverse($attempts) as $attempt) {
            if (!empty($attempt->feedback) && 
            strpos($attempt->feedback, 'Feedback is being generated') === false &&
            strpos($attempt->feedback, 'docker_error') === false) {
            $previous_feedback = strip_tags($attempt->feedback);
            break;
        }
    }
}
        $topics = ['variables', 'conditionals', 'iteration', 'functions', 'classes', 'arrays', 'eventhandling'];
        $topic_labels = [
            'variables'     => 'Variables',
            'conditionals'  => 'Conditionals',
            'iteration'     => 'Iteration',
            'functions'     => 'Functions',
            'classes'       => 'Classes',
            'arrays'        => 'Arrays',
            'eventhandling' => 'Event Handling',
        ];

        $now = time();
        $uc_settings = [];
        foreach ($topics as $key) {
    $from  = (int)(self::get_assignment_plugin_config($realassignid, $key . '_from')  ?? 0);
    $until = (int)(self::get_assignment_plugin_config($realassignid, $key . '_until') ?? 0);

    $after_from  = ($from  === 0 || $now >= $from);
    $before_until = ($until === 0 || $now <= $until);
    $enabled = $after_from && $before_until;

    $uc_settings[$topic_labels[$key]] = $enabled;
}

        $uc_settings['language'] = self::get_assignment_plugin_config($realassignid, 'language', 'processing');

        return [
            'student_id'        => $student_hash,
            'submission_number' => $attempt_number,
            'previous_attempts' => $previous_attempts,
            'previous_feedback' => $previous_feedback,
            'uc_settings'       => $uc_settings,
        ];
    }

    /**
 * Parse a raw Majordomo HTTP response into a normalised [message, json] pair.
 * Extracted so integration tests can exercise error-handling paths without
 * making a real HTTP call.
 *
 * @param  string|false $response  Raw curl_exec() return value.
 * @param  int          $errno     curl_errno() value (0 = success).
 * @return array{message: string, json: array}
 */
public static function process_api_response(string|false $response, int $errno): array {
    if ($errno !== 0 || $response === false) {
        $message = '<p>' . get_string('docker_error', 'assignsubmission_pde') . '</p>';
        $json    = [];
    } else {
        $json    = json_decode($response, true) ?? [];
        $message = $json['message'] ?? '<p>' . get_string('submission_received', 'assignsubmission_pde') . '</p>';
    }
    return ['message' => $message, 'json' => $json];
}

    private static function save_to_log(int $realassignid, int $userid, string $student_hash, int $attempt_number, array $response_meta, string $feedback, $DB): void {
        $topic_results = json_encode($response_meta['topics'] ?? []);
        $violations    = (int)($response_meta['violations_count'] ?? 0);

        $DB->insert_record('assignsubmission_pde_log', (object)[
            'assignment'       => $realassignid,
            'userid'           => $userid,
            'student_hash'     => $student_hash,
            'attempt_number'   => $attempt_number,
            'timecreated'      => time(),
            'violations_count' => $violations,
            'topic_results'    => $topic_results,
            'feedback'         => $feedback,
        ]);
    }

    public static function on_submission(\mod_assign\event\assessable_submitted $event): void {
        global $DB;

        error_log('PDE PLUGIN: on_submission called');

        $data         = $event->get_data();
        $assignid     = $data['contextinstanceid'] ?? null;
        $submissionid = $data['objectid'] ?? null;
        $userid       = $data['userid'] ?? null;

        error_log('PDE PLUGIN: assignid=' . $assignid . ' submissionid=' . $submissionid . ' userid=' . $userid);

        if (!$assignid || !$userid) {
            error_log('PDE PLUGIN: missing assignid or userid');
            return;
        }

        $cm           = get_coursemodule_from_id('assign', $assignid);
        $realassignid = $cm ? $cm->instance : $assignid;

        error_log('PDE PLUGIN: realassignid=' . $realassignid);

        // NEW — replace with:
if (!self::is_plugin_enabled($realassignid)) {
    error_log('PDE PLUGIN: feedback disabled for this assignment');
    return;
}

if (!self::is_submission_allowed($realassignid, $userid, $DB)) {
    error_log('PDE PLUGIN: max submissions reached');
    self::write_feedback(
        $realassignid, $userid,
        '<p>You have reached the maximum number of submissions for this assignment.</p>',
        $DB, FORMAT_HTML
    );
    return;
}

        $fs      = get_file_storage();
        $context = \context::instance_by_id($data['contextid']);
        $files   = $fs->get_area_files(
            $context->id,
            'assignsubmission_file',
            'submission_files',
            $submissionid,
            'id',
            false
        );

        error_log('PDE PLUGIN: files count = ' . count($files));

        if (empty($files)) {
            error_log('PDE PLUGIN: no files found');
            return;
        }

        $file     = reset($files);
        $filename = $file->get_filename();
        error_log('PDE PLUGIN: file found = ' . $filename);

        $tmppath = make_temp_directory('assignsubmission_pde') . '/' . $filename;
        $file->copy_content_to($tmppath);

        $student_hash = self::hash_student_id($userid);
        $metadata     = self::build_metadata($realassignid, $userid, $student_hash, $DB);
        $metadata_b64 = base64_encode(json_encode($metadata));

        error_log('PDE PLUGIN: attempt #' . $metadata['submission_number']);

        $dockerurl = self::get_docker_url();
        $timeout   = self::get_docker_timeout();

        error_log('PDE PLUGIN: docker url = ' . $dockerurl);

                // --- Performance timing ---
        $t_start = microtime(true);

        $ch = curl_init($dockerurl);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_POSTFIELDS     => [
                'file'     => new \CURLFile($tmppath, 'application/octet-stream', $filename),
                'metadata' => $metadata_b64,
            ],
        ]);

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        curl_close($ch);

        $t_after_majordomo = microtime(true);

        @unlink($tmppath);

        ['message' => $message, 'json' => $json] = self::process_api_response($response, $errno);

        // LLM time is reported by Majordomo in the response
        $t_llm = isset($json['llm_duration_seconds']) ? (float)$json['llm_duration_seconds'] : null;

        $t_before_db = microtime(true);

        self::save_to_log(
            $realassignid,
            $userid,
            $student_hash,
            $metadata['submission_number'],
            $json,
            $message,
            $DB
        );

        $visibility = self::get_assignment_plugin_config($realassignid, 'feedbackvisibility', 'immediate');
        if ($visibility === 'afterdue') {
            $assign_record = $DB->get_record('assign', ['id' => $realassignid]);
            $duedate       = $assign_record ? $assign_record->duedate : 0;
            if ($duedate && time() < $duedate) {
                $duedate_str = userdate($duedate, get_string('strftimedatetimeshort', 'langconfig'));
                error_log('PDE PLUGIN: feedback hidden until due date');
                self::write_feedback(
                    $realassignid,
                    $userid,
                    "<p>Automated feedback will be available after <strong>{$duedate_str}</strong>.</p>",
                    $DB,
                    FORMAT_HTML
                );
                return;
            }
        }

        try {
            self::write_feedback($realassignid, $userid, $message, $DB, FORMAT_HTML);
            error_log('PDE PLUGIN: feedback written successfully');
        } catch (\Exception $e) {
            error_log('PDE PLUGIN: database error = ' . $e->getMessage());
        }

        $t_end = microtime(true);

        // --- Log timing to JSON file ---
        $majordomo_total = round($t_after_majordomo - $t_start, 3);
        $db_write        = round($t_end - $t_before_db, 3);
        $total           = round($t_end - $t_start, 3);
        $zita_only       = $t_llm !== null ? round($majordomo_total - $t_llm, 3) : null;

        $timing_entry = [
            'submission'            => $metadata['submission_number'],
            'userid'                => $userid,
            'timestamp'             => date('Y-m-d H:i:s'),
            'majordomo_total_s'     => $majordomo_total,
            'zita_s'                => $zita_only,
            'llm_s'                 => $t_llm,
            'db_write_s'            => $db_write,
            'total_s'               => $total,
        ];

        $logfile = sys_get_temp_dir() . '/pde_timing.json';
        $existing = file_exists($logfile) ? json_decode(file_get_contents($logfile), true) : [];
        $existing[] = $timing_entry;
        file_put_contents($logfile, json_encode($existing, JSON_PRETTY_PRINT));

        error_log('PDE PLUGIN: timing logged — total=' . $total . 's, majordomo=' . $majordomo_total . 's, db=' . $db_write . 's');
    }
}