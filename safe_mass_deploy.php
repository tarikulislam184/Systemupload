<?php
// safe_mass_deploy.php
// Purpose: Safely deploy a file into every writable subdirectory under a base directory
// NOTE: Use only on servers you own/administer. Remove or protect this script after use.

// ---------------- CONFIGURATION ----------------
// Set a strong admin password before using. For best practice, use server auth instead
$ADMIN_PASSWORD = '007';
// Default filename to write into each directory
$DEFAULT_TARGET_FILENAME = 'test.php';
// Log file (kept inside script directory)
$LOG_FILE = __DIR__ . '/mass_deploy.log';
// Maximum number of preview items to display (prevents huge outputs)
$MAX_PREVIEW = 200;

// ------------------ HELPERS --------------------
function log_msg($msg) {
    global $LOG_FILE;
    $time = date('Y-m-d H:i:s');
    @file_put_contents($LOG_FILE, "[$time] $msg\n", FILE_APPEND | LOCK_EX);
}

function in_base_dir($base, $path) {
    $baseReal = rtrim(realpath($base), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $pathReal = realpath($path);
    if ($pathReal === false) return false;
    $pathReal = rtrim($pathReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return strpos($pathReal, $baseReal) === 0;
}

function find_directories_recursive($dir) {
    $dirs = [];
    if (!is_dir($dir)) return $dirs;
    $dirs[] = rtrim($dir, DIRECTORY_SEPARATOR);
    try {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($it as $item) {
            if ($item->isDir()) $dirs[] = $item->getPathname();
        }
    } catch (Exception $e) {
        // In case of permission errors on some trees, we still return what we have
        log_msg('Recursive scan error: ' . $e->getMessage());
    }
    return $dirs;
}

// ---------------- PROCESS FORM ----------------
$messages = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = $_POST['admin_password'] ?? '';
    if ($pw !== $ADMIN_PASSWORD) {
        $messages[] = ['type' => 'error', 'text' => 'Invalid admin password.'];
        log_msg("Failed auth attempt from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    } else {
        $baseDir = trim($_POST['base_dir'] ?? getcwd());
        $targetFilename = trim($_POST['target_filename'] ?? $DEFAULT_TARGET_FILENAME);
        $dryRun = isset($_POST['dry_run']);
        $content = '';

        // Uploaded file precedence
        if (!empty($_FILES['upload_file']['tmp_name']) && is_uploaded_file($_FILES['upload_file']['tmp_name'])) {
            $content = file_get_contents($_FILES['upload_file']['tmp_name']);
        } else {
            $content = $_POST['file_content'] ?? '';
        }

        if ($content === '') {
            $messages[] = ['type' => 'error', 'text' => 'No content provided. Upload a file or paste content.'];
        } else {
            $baseReal = realpath($baseDir);
            if ($baseReal === false || !is_dir($baseReal)) {
                $messages[] = ['type' => 'error', 'text' => "Base directory not found: " . htmlspecialchars($baseDir)];
                log_msg("Invalid base dir: $baseDir");
            } elseif (!in_base_dir($baseReal, $baseReal)) {
                $messages[] = ['type' => 'error', 'text' => 'Base directory check failed.'];
                log_msg("Base dir check failed for: $baseDir");
            } else {
                $allDirs = find_directories_recursive($baseReal);
                $written = 0; $skipped = 0; $failed = 0; $previewList = [];

                foreach ($allDirs as $dir) {
                    if (!in_base_dir($baseReal, $dir)) { $skipped++; continue; }

                    $dest = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $targetFilename;
                    $previewList[] = $dest;

                    if ($dryRun) continue;

                    if (!is_writable($dir)) { $skipped++; log_msg("Skipped (not writable): $dest"); continue; }

                    $tmp = tempnam(sys_get_temp_dir(), 'mdp_');
                    if ($tmp === false) { $failed++; log_msg("temp file failed for $dest"); continue; }
                    if (file_put_contents($tmp, $content) === false) { $failed++; @unlink($tmp); log_msg("write to temp failed for $dest"); continue; }
                    if (!rename($tmp, $dest)) { $failed++; @unlink($tmp); log_msg("rename to $dest failed"); continue; }

                    $written++; log_msg("WROTE: $dest");
                }

                if ($dryRun) {
                    $messages[] = ['type' => 'info', 'text' => "Dry run: " . count($previewList) . " target(s) found. No files were written."];
                    log_msg("Dry run requested by admin from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ". Targets: " . count($previewList));
                } else {
                    $messages[] = ['type' => 'success', 'text' => "Done. Written: $written, Skipped: $skipped, Failed: $failed. See log for details."];
                    log_msg("Deploy run by admin from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ". Written:$written Skipped:$skipped Failed:$failed");
                }

                // preview
                $messages[] = ['type' => 'preview', 'text' => implode("\n", array_slice($previewList, 0, $MAX_PREVIEW))];
                if (count($previewList) > $MAX_PREVIEW) {
                    $messages[] = ['type' => 'info', 'text' => 'Preview truncated to first ' . $MAX_PREVIEW . ' targets. Full log contains all paths.'];
                }
            }
        }
    }
}

// ---------------- HTML OUTPUT ----------------
?><!doctype html>
<html><head><meta charset="utf-8"><title>Safe Mass Deploy (Admin Only)</title>
<style>body{font-family:Arial,Helvetica,sans-serif;margin:16px}label{display:block;margin:8px 0}textarea{font-family:monospace}</style>
</head><body>
<h2>Safe Mass Deploy (Admin Only)</h2>

<?php foreach ($messages as $m): ?>
    <div style="border:1px solid #ccc;margin:8px;padding:8px;background:<?php echo $m['type']=='error' ? '#ffd6d6' : ($m['type']=='success' ? '#d6ffd9' : '#f0f0f0'); ?>;">
        <?php if ($m['type'] === 'preview'): ?>
            <strong>Preview (first <?php echo $MAX_PREVIEW; ?> targets):</strong><br>
            <textarea rows="10" cols="100" readonly><?php echo htmlspecialchars($m['text']); ?></textarea>
        <?php else: ?>
            <?php echo nl2br(htmlspecialchars($m['text'])); ?>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data">
    <label>Admin Password: <input type="password" name="admin_password" required></label>
    <label>Base Directory (must be inside server and accessible):<br>
        <input type="text" name="base_dir" style="width:600px" value="<?php echo htmlspecialchars(getcwd()); ?>">
    </label>
    <label>Target filename to write (e.g. <?php echo htmlspecialchars($DEFAULT_TARGET_FILENAME); ?>):<br>
        <input type="text" name="target_filename" value="<?php echo htmlspecialchars($DEFAULT_TARGET_FILENAME); ?>">
    </label>

    <strong>Provide content to deploy (choose one):</strong><br>
    <label>Upload file: <input type="file" name="upload_file" accept=".html,.htm,.txt,.php"></label>
    <em>— or —</em>
    <label>Paste content:<br>
        <textarea name="file_content" rows="8" cols="100" placeholder="Paste HTML or text content here"></textarea>
    </label>

    <label><input type="checkbox" name="dry_run" checked> Dry run (do not write; just preview targets)</label><br>

    <button type="submit">Execute</button>
</form>

<hr>
<p><strong>Security & usage notes</strong></p>
<ul>
    <li>Change <code>$ADMIN_PASSWORD</code> in the script before use. For production, prefer server-side auth (htpasswd, HTTP auth, or OS user) instead of hardcoding.</li>
    <li>Run a <em>Dry run</em> first to preview targets. Inspect the preview and the log file (<code>mass_deploy.log</code>).</li>
    <li>Ensure the account running the webserver has the correct file-system permissions. The script will skip non-writable directories.</li>
    <li>Keep this script inaccessible to the public (remove after use, protect by IP/.htaccess).</li>
    <li>If you want to push updates to remote servers, consider using secure tools like <code>rsync/ssh</code> or configuration management (Ansible).</li>
</ul>

</body></html>
