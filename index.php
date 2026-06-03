<?php

/**
 * Fallback jika document root mengarah ke root project (public_html = clone repo).
 * Idealnya document root langsung ke folder public/.
 */
require __DIR__.'/public/index.php';
