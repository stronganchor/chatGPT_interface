<?php
/*
 * Plugin Name: * ChatGPT Interface
 * Description: A simple plugin to interact with the ChatGPT API.
 * Version: 1.0
 * Author: Strong Anchor Tech
 * Author URI: https://stronganchortech.com
*/

/* 
 * Add Admin Page under the Tools menu
 */
function chatgpt_admin_page() {
    add_submenu_page(
        'tools.php',
        'ChatGPT Interface',
        'ChatGPT Interface',
        'manage_options',
        'chatgpt-interface',
        'chatgpt_interface_callback'
    );
}
add_action('admin_menu', 'chatgpt_admin_page');

/* 
 * Display Admin Page content
 */
function chatgpt_interface_callback() {
    ?>
    <div class="wrap">
        <h2>ChatGPT Interface</h2>
        <form method="post" action="options.php">
            <?php settings_fields('chatgpt-settings-group'); ?>
            <?php do_settings_sections('chatgpt-settings-group'); ?>
            <table class="form-table">
                <tr valign="top">
                <th scope="row">ChatGPT API Key</th>
                <td><input type="text" name="chatgpt_api_key" value="<?php echo esc_attr(get_option('chatgpt_api_key')); ?>" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/*
 * Register and Store the API Key
 */
function chatgpt_settings() {
    register_setting('chatgpt-settings-group', 'chatgpt_api_key');
}
add_action('admin_init', 'chatgpt_settings');

/* 
 * Function to interact with ChatGPT API
 */
function chatgpt_send_message($message) {
    $api_key = get_option('chatgpt_api_key'); // Retrieve API key from WordPress settings
    $url = 'https://api.openai.com/v1/chat/completions';

    $headers = [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ];

    $body = json_encode([
        'model' => 'gpt-3.5-turbo',
        'messages' => [['role' => 'user', 'content' => $message]],
        'temperature' => 0.7
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $response = curl_exec($ch);
	
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        error_log("ChatGPT cURL Error: " . $error_msg);
        curl_close($ch);
        return "Error: cURL error - " . $error_msg;
    }

    curl_close($ch);

    $decoded_response = json_decode($response, true);
    if (isset($decoded_response['error'])) {
        error_log("ChatGPT API Error: " . $decoded_response['error']['message']);
        //return "Error: " . $decoded_response['error']['message'];
        return "Error: " . $decoded_response['error']['message'] . "    ChatGPT Raw Response: " . $response; //debugging code TODO: revert to line above
    }

    $decoded_response = json_decode($response, true);
    if (!isset($decoded_response['choices'][0]['message']['content'])) {
        error_log("ChatGPT Error: Unexpected API response format.");
        //return "Error: Unexpected API response format.";
        return "Error: Unexpected API response format." . "    ChatGPT Raw Response: " . $response; //debugging code TODO: revert to line above
    }

    return $decoded_response['choices'][0]['message']['content'];
}


/*
 * Set up the shortcode [chatgpt_form]
 */
function chatgpt_shortcode_callback($atts) {
    $output = '';

    if (isset($_POST['chatgpt_message'])) {
        $user_message = sanitize_text_field($_POST['chatgpt_message']);
        $response = chatgpt_send_message($user_message);
        $output .= '<div class="chatgpt-response">Zeki: ' . esc_html($response) . '</div>';
    }

    $output .= '
    <form method="post">
        <textarea name="chatgpt_message" required placeholder="Type your message..."></textarea>
        <input type="submit" value="Send" />
    </form>
    ';

    return $output;
}
add_shortcode('chatgpt_form', 'chatgpt_shortcode_callback');

/*
 * Convert the inputted text into an audio file using OpenAI's TTS API
 */
function generate_text_to_speech($input_text, $voice = 'onyx') {
    $api_key = get_option('chatgpt_api_key'); // Retrieve API key from WordPress settings

    $url = 'https://api.openai.com/v1/audio/speech';

    $headers = [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ];

    $body = json_encode([
        'model' => 'tts-1-hd',
        'voice' => $voice,
        'input' => $input_text
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        return "Error: cURL error - " . $error_msg;
    }

    curl_close($ch);

    // Check if the response is binary data
    if (!preg_match('/^[[:print:]\r\n\t]*$/', $response)) {
        // Format and sanitize the filename
        $formatted_text = substr(strtolower(preg_replace('/[^a-zA-Z0-9_]+/', '_', $input_text)), 0, 100); // Replace non-alphanumeric characters with underscore and limit to 100 chars
        $audio_file_name = 'tts_' . $voice . '_' . $formatted_text . '.mp3'; // Create the file name
        $audio_file_path = wp_upload_dir()['path'] . '/' . $audio_file_name;

        file_put_contents($audio_file_path, $response);

        // Return the URL to the saved audio file
        return wp_upload_dir()['url'] . '/' . $audio_file_name;
    } else {
        return "Error: Unexpected API response format.";
    }
}


/*
 * Creates a shortcode that receives text input, converts it to audio and displays it with an audio player.
 */
function tts_shortcode_callback() {
    $output = '<form method="post">';
    $output .= '<textarea name="tts_input_text" required placeholder="Enter text to convert to speech..."></textarea><br>';

    // Voice selection dropdown
    $output .= '<label for="voice_selection">Choose a voice:</label>';
    $output .= '<select name="voice_selection" id="voice_selection">';
    $voices = ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'];
    foreach ($voices as $voice) {
        $selected = ($voice == 'shimmer') ? 'selected' : '';
        $output .= '<option value="' . $voice . '" ' . $selected . '>' . ucfirst($voice) . '</option>';
    }
    $output .= '</select><br>';

    $output .= '<input type="submit" value="Convert to Speech" />';
    $output .= '</form>';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['tts_input_text'])) {
        $input_text = sanitize_text_field($_POST['tts_input_text']);
        $selected_voice = isset($_POST['voice_selection']) ? $_POST['voice_selection'] : 'shimmer';
        $audio_url = generate_text_to_speech($input_text, $selected_voice);

        if (filter_var($audio_url, FILTER_VALIDATE_URL)) {
            $output .= '<audio controls><source src="' . esc_url($audio_url) . '" type="audio/mpeg">Your browser does not support the audio element.</audio>';
        } else {
            $output .= '<div class="error-message">' . esc_html($audio_url) . '</div>'; // Display error message
        }
    }

    return $output;
}
add_shortcode('tts_form', 'tts_shortcode_callback');

?>
