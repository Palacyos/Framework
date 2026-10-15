<?php

echo '<main>layout:outer|';
ob_start();
echo 'layout:inner|' . $content;
ob_start();
echo '|layout:deep</main>';
