<?php

echo '<main>partial outer layout' . $content;
ob_start();
echo '<section>partial nested layout';
ob_start();
echo '<p>partial deepest layout</p>';
throw $failure;
