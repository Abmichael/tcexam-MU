<?php
//============================================================+
// File name   : tce_ai_generate.php
// Begin       : 2024-06-XX
// Last Update : 2024-06-XX
//
// Description : Generate questions using AI and import to TCExam.
//
// Author: Abraham Michael
//
// (c) Copyright:
//               Nicola Asuni
//               Tecnick.com LTD
//               www.tecnick.com
//               info@tecnick.com
//
// License:
//    Copyright (C) 2004-2025 Nicola Asuni - Tecnick.com LTD
//    See LICENSE.TXT file for more information.
//============================================================+

/**
 * @file
 * Generate questions using AI and import to TCExam.
 * @package com.tecnick.tcexam.admin
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once('../config/tce_config.php');

    $api_key = getenv('GEMINI_API_KEY') ?: '<YOUR_API_KEY>';

    $module      = escapeshellarg($_POST['module']);
    $description = escapeshellarg($_POST['desc']);
    $num_questions = intval($_POST['n']);

    $subjects_arr = array_map('trim', explode(',', $_POST['subjects']));
    $subjects_cli = implode(' ', array_map('escapeshellarg', $subjects_arr));

    // Use cache folder for output file
    $output_file = K_PATH_CACHE . 'ai.tsv';

    $python = '"python"';   // adjust if different
    $script = __DIR__ . DIRECTORY_SEPARATOR . 'gen.py';

    $cmd = "$python $script "
        . "--api_key " . escapeshellarg($api_key) . " "
        . "--module $module "
        . "--description $description "
        . "--subjects $subjects_cli "
        . "--num_questions $num_questions "
        . "--output " . escapeshellarg($output_file);

    $output = shell_exec($cmd . ' 2>&1');
    error_log("CMD: $cmd");
    error_log("PY OUT: $output");

    if (!file_exists($output_file)) {
        die('Generation failed — check logs.');
    }

    $basename = basename($output_file);

    // If user requested download (or always, as per prompt), push file for download
    if (isset($_GET['download']) || true) { // always download for now
        header('Content-Description: File Transfer');
        header('Content-Type: text/tab-separated-values');
        header('Content-Disposition: attachment; filename="' . $basename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($output_file));
        readfile($output_file);
        flush();
        // Optionally, after download, redirect to import page (JS or meta refresh)
        // echo '<script>window.location.href = "tce_import_questions.php?file=' . urlencode($basename) . '&preview=1";</script>';
        exit;
    }

    header('Location: tce_import_questions.php?file=' . urlencode($basename) . '&preview=1');
    exit;
}

// Now include everything else and output HTML
require_once('../config/tce_config.php');
$pagelevel = K_AUTH_ADMIN_MODULES;
require_once('../../shared/code/tce_authorization.php');
$thispage_title = 'AI Question Generator';
require_once('../code/tce_page_header.php');
require_once('../../shared/code/tce_functions_form.php');

echo '<div class="container">' . K_NEWLINE;
echo '<div class="tceformbox">' . K_NEWLINE;
?><style>
    #ai-loader-overlay {
        display: none;
        position: fixed;
        z-index: 9999;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        align-items: center;
        justify-content: center;
        text-align: center;
    }

    #ai-loader-overlay .loader-box {
        display: inline-block;
        padding: 2em 3em;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 16px rgba(0, 0, 0, 0.15);
        font-size: 1.2em;
        color: #333;
    }
</style>
<form method="POST" action="<?php echo htmlspecialchars($_SERVER['SCRIPT_NAME']); ?>">
    <div class="row">
        <span class="label"><label for="module">Module</label></span>
        <span class="formw"><input type="text" name="module" id="module" required placeholder="e.g. Mobile App Development"></span>
    </div>
    <div class="row">
        <span class="label"><label for="desc">Subject Description</label></span>
        <span class="formw"><textarea style="height: 150px;" name="desc" id="desc"></textarea></span>
    </div>
    <div class="row">
        <span class="label"><label for="subjects">Subjects (comma-separated)</label></span>
        <span class="formw"><input type="text" name="subjects" id="subjects" placeholder="e.g. Flutter, React" required></span>
    </div>
    <div class="row">
        <span class="label"><label for="n">Questions</label></span>
        <span class="formw"><input name="n" id="n" type="number" value="10" min="1" max="50"></span>
    </div>
    <div class="row">
        <span class="formw">
            <input type="submit" value="Generate" />
        </span>
    </div>
    <?php echo F_getCSRFTokenField() . K_NEWLINE; ?>
</form>
<div id="ai-loader-overlay">
    <div class="loader-box">Generating questions, please wait...</div>
</div>
<script>
    document.querySelector('form').addEventListener('submit', function() {
        document.getElementById('ai-loader-overlay').style.display = 'flex';
    });
</script>
<?php
echo '</div>' . K_NEWLINE;
echo '</div>' . K_NEWLINE;
require_once('../code/tce_page_footer.php');

//============================================================+
// END OF FILE
//============================================================+
?>