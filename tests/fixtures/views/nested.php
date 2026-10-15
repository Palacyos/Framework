<?php

echo 'view:outer|';
ob_start();
echo 'view:inner|';
ob_start();
echo 'view:deep';
