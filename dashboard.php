<?php
require_once('../../../../config.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');

$cmid = required_param('cmid', PARAM_INT);
$cm   = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);

require_login($cm->course, true, $cm);

$context  = context_module::instance($cm->id);
$isteacher = has_capability('mod/assign:grade', $context);
$userid   = $USER->id;

$assign        = new assign($context, $cm, $cm->course);
$realassignid  = $assign->get_instance()->id;

$PAGE->set_url(new moodle_url('/mod/assign/submission/pde/dashboard.php', ['cmid' => $cmid]));
$PAGE->set_context($context);
$PAGE->set_title('PDE Feedback Dashboard');
$PAGE->set_heading('PDE Feedback Dashboard');

echo $OUTPUT->header();

if ($isteacher) {
    // ── Staff view ─────────────────────────────────────────────────────────
    $records = $DB->get_records_sql("
        SELECT userid,
               COUNT(*) as total_attempts,
               MAX(attempt_number) as last_attempt,
               MIN(violations_count) as best_violations,
               MAX(timecreated) as last_submission
        FROM {assignsubmission_pde_log}
        WHERE assignment = ?
        GROUP BY userid
        ORDER BY last_submission DESC
    ", [$realassignid]);

    $total_students    = count($records);
    $total_submissions = 0;
    foreach ($records as $r) {
        $total_submissions += $r->total_attempts;
    }

    echo '<h3>📊 Cohort Overview</h3>';
    echo "<p><strong>{$total_students} student(s)</strong> have submitted &mdash; <strong>{$total_submissions} total submissions</strong></p>";

    if (empty($records)) {
        echo '<p>No submissions yet.</p>';
    } else {
        echo '<table class="generaltable" style="width:100%;">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Total Attempts</th>
                    <th>Best Score (lowest violations)</th>
                    <th>Last Submission</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($records as $record) {
            $user     = $DB->get_record('user', ['id' => $record->userid]);
            $fullname = fullname($user);
            $date     = userdate($record->last_submission);
            $best     = $record->best_violations . ' violations';
            $url      = new moodle_url('/mod/assign/submission/pde/dashboard.php', [
                'cmid'   => $cmid,
                'userid' => $record->userid,
            ]);

            echo "<tr>
                <td>{$fullname}</td>
                <td>{$record->total_attempts}</td>
                <td>{$best}</td>
                <td>{$date}</td>
                <td><a href='{$url}'>View history</a></td>
            </tr>";
        }

        echo '</tbody></table>';

        // ── Topic overview — based on latest submission per student only ──
        echo '<h3 style="margin-top:32px;">📊 Topic Overview (Cohort)</h3>';
        echo '<p style="color:#666;font-size:0.9em;margin-top:-8px;">
            🟢 Majority passing &nbsp;|&nbsp; 🔴 Majority failing — hover for exact split
        </p>';

        $all_logs = $DB->get_records('assignsubmission_pde_log',
            ['assignment' => $realassignid], 'attempt_number ASC');

        // Keep only the latest submission per student
        $latest_per_student = [];
        foreach ($all_logs as $log) {
            $latest_per_student[$log->userid] = $log;
        }

        $topic_fails  = [];
        $topic_totals = [];
        foreach ($latest_per_student as $log) {
            if (empty($log->topic_results)) continue;
            $topics = json_decode($log->topic_results, true);
            if (!is_array($topics)) continue;
            foreach ($topics as $topic => $result) {
                $topic_totals[$topic] = ($topic_totals[$topic] ?? 0) + 1;
                if ($result === 'fail') {
                    $topic_fails[$topic] = ($topic_fails[$topic] ?? 0) + 1;
                }
            }
        }

        // Order: most failing first, then passing topics
        arsort($topic_fails);
        $ordered_topics = array_keys($topic_fails);
        foreach (array_keys($topic_totals) as $t) {
            if (!in_array($t, $ordered_topics)) {
                $ordered_topics[] = $t;
            }
        }

        if (!empty($ordered_topics)) {
            $counts      = [];
            $colors      = [];
            $fail_counts = [];
            $pass_counts = [];
            $n_students  = count($latest_per_student);

            foreach ($ordered_topics as $t) {
                $total  = $topic_totals[$t] ?? 0;
                $fails  = $topic_fails[$t]  ?? 0;
                $passes = $total - $fails;
                $counts[]      = $total;
                $fail_counts[] = $fails;
                $pass_counts[] = $passes;

                if ($fails === 0) {
                    $colors[] = '#22c55e';        // all passed — green
                } elseif ($passes === 0) {
                    $colors[] = '#ef4444';        // all failed — red
                } elseif ($fails >= $passes) {
                    $colors[] = '#ef4444';        // majority failing — red
                } else {
                    $colors[] = '#22c55e';        // majority passing — green
                }
            }

            $topics_json      = json_encode($ordered_topics);
            $counts_json      = json_encode($counts);
            $colors_json      = json_encode($colors);
            $fail_counts_json = json_encode($fail_counts);
            $pass_counts_json = json_encode($pass_counts);
            $total_json       = $n_students;

            echo "
            <div style='max-width:560px;margin-top:16px;'>
                <canvas id='topicChart' height='280'></canvas>
            </div>
            <script src='https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js'></script>
            <script>
            (function() {
                var topics     = {$topics_json};
                var counts     = {$counts_json};
                var colors     = {$colors_json};
                var failCounts = {$fail_counts_json};
                var passCounts = {$pass_counts_json};
                var total      = {$total_json};

                new Chart(document.getElementById('topicChart'), {
                    type: 'bar',
                    data: {
                        labels: topics,
                        datasets: [{
                            label: 'Students',
                            data: counts,
                            backgroundColor: colors,
                            borderRadius: 6,
                        }]
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(ctx) {
                                        var i   = ctx.dataIndex;
                                        var f   = failCounts[i];
                                        var p   = passCounts[i];
                                        var pct = ctx.raw > 0 ? Math.round(f / ctx.raw * 100) : 0;
                                        return f + '/' + ctx.raw + ' failed (' + pct + '%) — ' + p + ' passed';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                max: total,
                                ticks: { stepSize: 1 },
                                title: { display: true, text: 'Number of students' }
                            }
                        }
                    }
                });
            })();
            </script>";
        } else {
            echo '<p style="color:#666;">No topic data available.</p>';
        }
    }

    // If viewing a specific student
    $viewuserid = optional_param('userid', 0, PARAM_INT);
    if ($viewuserid) {
        $viewuser = $DB->get_record('user', ['id' => $viewuserid]);
        echo '<h3 style="margin-top:24px;">📋 ' . fullname($viewuser) . ' — Submission History</h3>';
        $userid = $viewuserid;
        goto student_view;
    }

} else {
    echo '<h3>📋 Your Submission History</h3>';
    student_view:

    $logs = $DB->get_records('assignsubmission_pde_log', [
        'assignment' => $realassignid,
        'userid'     => $userid,
    ], 'attempt_number ASC');

    if (empty($logs)) {
        echo '<p>You have not submitted yet.</p>';
    } else {
        echo '<table class="generaltable" style="width:100%;">
            <thead>
                <tr>
                    <th>Attempt</th>
                    <th>Date</th>
                    <th>Violations</th>
                    <th>Topics</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($logs as $log) {
            $date       = userdate($log->timecreated);
            $topics     = $log->topic_results ? json_decode($log->topic_results, true) : [];
            $topic_html = '<div style="display:flex;flex-wrap:wrap;gap:4px;">';

            foreach ($topics as $topic => $result) {
                $pass = $result === 'pass';
                $bg   = $pass ? '#d1fae5' : '#fee2e2';
                $fg   = $pass ? '#065f46' : '#991b1b';
                $icon = $pass ? '✓' : '✗';
                $topic_html .= "<span style='background:{$bg};color:{$fg};padding:2px 8px;border-radius:12px;font-size:0.8em;white-space:nowrap;'>{$icon} {$topic}</span>";
            }

            $topic_html .= '</div>';

            $trend = '';
            if ($log->attempt_number > 1) {
                $prev = $DB->get_record('assignsubmission_pde_log', [
                    'assignment'     => $realassignid,
                    'userid'         => $userid,
                    'attempt_number' => $log->attempt_number - 1,
                ]);
                if ($prev) {
                    if ($log->violations_count < $prev->violations_count) {
                        $trend = ' <span style="color:#22c55e">▲ Improved</span>';
                    } elseif ($log->violations_count > $prev->violations_count) {
                        $trend = ' <span style="color:#ef4444">▼ Dropped</span>';
                    } else {
                        $trend = ' <span style="color:#888">= Same</span>';
                    }
                }
            }

            echo "<tr>
                <td>#{$log->attempt_number}{$trend}</td>
                <td>{$date}</td>
                <td>{$log->violations_count}</td>
                <td>{$topic_html}</td>
            </tr>";
        }

        echo '</tbody></table>';

        $labels     = [];
        $violations = [];
        foreach ($logs as $log) {
            $labels[]     = '#' . $log->attempt_number;
            $violations[] = $log->violations_count;
        }

        $labels_json     = json_encode($labels);
        $violations_json = json_encode($violations);

        echo "
        <h3 style='margin-top:24px;'>📈 Violations Over Time</h3>
        <canvas id='progressChart' style='max-width:600px;max-height:300px;'></canvas>
        <script src='https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js'></script>
        <script>
        new Chart(document.getElementById('progressChart'), {
            type: 'line',
            data: {
                labels: {$labels_json},
                datasets: [{
                    label: 'Violations',
                    data: {$violations_json},
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99,102,241,0.1)',
                    tension: 0.3,
                    fill: true,
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Violations' } },
                    x: { title: { display: true, text: 'Submission' } }
                }
            }
        });
        </script>";
    }
}

echo $OUTPUT->footer();