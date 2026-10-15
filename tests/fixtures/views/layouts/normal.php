<?php

echo '<main title="' . htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . $content . '</main>';
