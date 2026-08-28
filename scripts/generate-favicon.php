<?php

if (! extension_loaded('gd')) {
    fwrite(STDERR, "GD extension required\n");
    exit(1);
}

$src = __DIR__.'/../public/images/numart-logo.jpg';
if (! is_file($src)) {
    fwrite(STDERR, "Source image missing: {$src}\n");
    exit(1);
}

$img = imagecreatefromjpeg($src);
$w = imagesx($img);
$h = imagesy($img);
$size = (int) min($w, $h * 0.55);
$x = (int) (($w - $size) / 2);
$y = (int) ($h * 0.08);
$crop = imagecrop($img, ['x' => $x, 'y' => $y, 'width' => $size, 'height' => $size]);
imagedestroy($img);

if ($crop === false) {
    fwrite(STDERR, "Crop failed\n");
    exit(1);
}

$public = __DIR__.'/../public';
foreach ([32 => 'favicon.png', 180 => 'apple-touch-icon.png', 512 => 'icon-512.png'] as $px => $file) {
    $out = imagescale($crop, $px, $px, IMG_BILINEAR_FIXED);
    imagepng($out, $public.'/'.$file);
    imagedestroy($out);
}

$png = file_get_contents($public.'/favicon.png');
$header = pack('vvv', 0, 1, 1);
$entry = pack('CCCCvvVV', 32, 32, 0, 0, 1, 32, strlen($png), 22);
file_put_contents($public.'/favicon.ico', $header.$entry.$png);

imagedestroy($crop);
echo "Favicons generated in public/\n";
