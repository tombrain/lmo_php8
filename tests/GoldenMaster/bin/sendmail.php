<?php
// Ersatz fuer das Mailprogramm in den Tests (php.ini sendmail_path, siehe Support/Runner.php):
// legt jede Mail als Datei im angegebenen Ordner ab, nichts wird verschickt.
// Liegt dort eine Datei "fail", wird ein fehlgeschlagener Versand nachgestellt.
$dir = $argv[1];
if (is_file($dir . '/fail')) {
    exit(1);
}
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}
file_put_contents(sprintf('%s/%03d.eml', $dir, count(glob($dir . '/*.eml')) + 1), stream_get_contents(STDIN));
