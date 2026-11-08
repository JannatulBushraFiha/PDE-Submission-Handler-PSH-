<?php
require_once('../../../../config.php');
require_once($CFG->dirroot.'/mod/assign/locallib.php');

$cmid = required_param('cmid', PARAM_INT);
$cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($cm->course, true, $cm);
require_capability('mod/assign:grade', $context);

$assign = new assign($context, $cm, $cm->course);
$assignid = $assign->get_instance()->id;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $DB->delete_records('assign_plugin_config', [
        'assignment' => $assignid,
        'plugin' => 'pde',
        'subtype' => 'assignsubmission',
    ]);
    foreach (['enabled', 'maxsubmissions', 'feedbackvisibility'] as $key) {
        $DB->insert_record('assign_plugin_config', (object)[
            'assignment' => $assignid,
            'plugin'     => 'pde',
            'subtype'    => 'assignsubmission',
            'name'       => $key,
            'value'      => required_param($key, PARAM_RAW),
        ]);
    }
    redirect(new moodle_url('/mod/assign/view.php', ['id' => $cmid]));
}

// Get current values
function get_pde_config($DB, $assignid, $name, $default = null) {
    $r = $DB->get_record('assign_plugin_config', [
        'assignment' => $assignid,
        'plugin' => 'pde',
        'subtype' => 'assignsubmission',
        'name' => $name,
    ]);
    return $r ? $r->value : $default;
}

$enabled = get_pde_config($DB, $assignid, 'enabled', '1');
$maxsubmissions = get_pde_config($DB, $assignid, 'maxsubmissions', '0');
$feedbackvisibility = get_pde_config($DB, $assignid, 'feedbackvisibility', 'immediate');

$PAGE->set_url(new moodle_url('/mod/assign/submission/pde/settings_panel.php', ['cmid' => $cmid]));
$PAGE->set_context($context);
$PAGE->set_title('PDE Feedback Settings');
$PAGE->set_heading('PDE Feedback Settings');

echo $OUTPUT->header();
?>

<div style="max-width:600px; margin:20px auto;">
    <h3>⚙️ PDE Automated Feedback Settings</h3>
    <form method="post">
        <table class="generaltable" style="width:100%;">
            <tr>
                <td><label for="enabled"><strong>Enable automated feedback</strong></label></td>
                <td>
                    <select name="enabled" id="enabled" class="form-control">
                        <option value="1" <?= $enabled == '1' ? 'selected' : '' ?>>Yes</option>
                        <option value="0" <?= $enabled == '0' ? 'selected' : '' ?>>No</option>
                    </select>
                </td>
            </tr>
            <tr>
                <td><label for="maxsubmissions"><strong>Maximum submissions</strong><br><small>0 = unlimited</small></label></td>
                <td>
                    <input type="number" name="maxsubmissions" id="maxsubmissions"
                           value="<?= (int)$maxsubmissions ?>" min="0" class="form-control" style="width:100px;">
                </td>
            </tr>
            <tr>
                <td><label for="feedbackvisibility"><strong>Feedback visibility</strong></label></td>
                <td>
                    <select name="feedbackvisibility" id="feedbackvisibility" class="form-control">
                        <option value="immediate" <?= $feedbackvisibility === 'immediate' ? 'selected' : '' ?>>
                            Immediately after submission
                        </option>
                        <option value="afterdue" <?= $feedbackvisibility === 'afterdue' ? 'selected' : '' ?>>
                            After due date
                        </option>
                    </select>
                </td>
            </tr>
        </table>
        <div style="margin-top:16px;">
            <input type="hidden" name="sesskey" value="<?= sesskey() ?>">
            <button type="submit" class="btn btn-primary">Save settings</button>
            <a href="<?= new moodle_url('/mod/assign/view.php', ['id' => $cmid]) ?>" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php
echo $OUTPUT->footer();