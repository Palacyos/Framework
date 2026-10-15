<?php

echo '<p>partial outer view</p>';
ob_start();
echo '<p>partial nested view</p>';
ob_start();
echo '<p>partial deepest view</p>';
throw $failure;
