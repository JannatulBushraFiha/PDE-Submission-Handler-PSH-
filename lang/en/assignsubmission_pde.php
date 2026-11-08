<?php
// Language strings for assignsubmission_pde

$string['pluginname']                = 'PDE Submission Handler';
$string['pde:view']                  = 'View PDE submission';
$string['submission_received']       = 'Your submission was received and is being processed.';
$string['docker_error']              = 'Docker could not be reached. Please try again later.';

// Docker settings
$string['docker_heading']            = 'Docker Configuration';
$string['docker_heading_desc']       = 'Configure the connection to the Docker processing container.';
$string['docker_url']                = 'Docker endpoint URL';
$string['docker_url_desc']           = 'The full URL of the Docker container processing endpoint. Example: http://localhost:5001/process';
$string['docker_timeout']            = 'Request timeout (seconds)';
$string['docker_timeout_desc']       = 'Maximum number of seconds to wait for a response from the Docker container before giving up.';
$string['max_file_size']             = 'Maximum file size (MB)';
$string['max_file_size_desc']        = 'Maximum size in megabytes of the zip file accepted for processing.';

// AI settings
$string['ai_heading']                = 'AI Configuration';
$string['ai_heading_desc']           = 'Configure the AI provider used to generate natural language feedback.';
$string['gemini_api_key'] = 'AI provider API key';
$string['gemini_api_key_desc'] = 'API key for the configured AI provider. Leave blank to return raw static analysis output only.';
$string['ai_model']                  = 'Gemini model';
$string['ai_model_desc']             = 'The Gemini model to use for feedback generation.';

// Behaviour settings
$string['behaviour_heading']         = 'Plugin Behaviour';
$string['behaviour_heading_desc']    = 'General settings controlling how the plugin operates.';
$string['plugin_enabled']            = 'Enable plugin';
$string['plugin_enabled_desc']       = 'When disabled, the plugin will not process submissions or generate feedback.';
$string['max_violations']            = 'Maximum violations to display';
$string['max_violations_desc']       = 'Maximum number of violations shown to students in the feedback field. Higher numbers may overwhelm students.';

// Unit convenor settings

$string['uc_enabled']                    = 'Enable automated feedback';
$string['uc_enabled_help']               = 'When enabled, Zita static analysis and AI feedback will run automatically when a student submits.';
$string['uc_maxsubmissions']             = 'Maximum submissions allowed';
$string['uc_maxsubmissions_help']        = 'Maximum number of times a student can submit. Set to 0 for unlimited submissions.';
$string['uc_feedbackvisibility']         = 'Feedback visibility';
$string['uc_feedbackvisibility_help']    = 'Choose when students can see their automated feedback.';
$string['uc_visibility_immediate']       = 'Immediately after submission';
$string['uc_visibility_afterdue']        = 'After the assignment due date';
$string['uc_language']                   = 'Programming language';
$string['uc_language_help']              = 'Select the programming language for this assignment. Currently only Processing is supported. Additional languages are planned for future releases.';
$string['uc_topicdates']                 = 'Topic Feedback Availability';
$string['uc_topic_from']  = 'Available from';
$string['uc_topic_until'] = 'Available until';
$string['uc_topic_from_enable']  = 'Set start date';
$string['uc_topic_until_enable'] = 'Set end date';