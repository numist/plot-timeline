<?php

if(!defined("LIBPATH")) {
   $libpath = "..";
   set_include_path(get_include_path() . PATH_SEPARATOR . $libpath);
   define("LIBPATH", $libpath);
}

//
// Time function to return current timestamp as a float
// From: http://php.net/microtime
//
if(!function_exists('microtime_float')) {
   function microtime_float()
   {
      list($usec, $sec) = explode(" ", microtime());
      return ((float)$usec + (float)$sec);
   }
// this could be a common enough function, so test its output
} else if(!is_float(microtime_float()))
   /* testing the exact output may not work, since this func is supposed to be
    * _very_ ganular, so test the type and rely on faith for the rest.       */
   trigger_error("microtime_float() already defined, and output not a float!", 
      E_USER_ERROR);

if(!function_exists("de_jong")) {
function de_jong(&$im, $x, $y, $a, $b, $c, $d) {
   $i = 0;

   $xsize = imagesx($im);
   $yzise = imagesy($im);
   
   $xn = sin($a * $y) - cos($b * $x);
   $yn = sin($c * $x) - cos($d * $y);
   $x = $xn;
   $y = $yn;
   $time = microtime_float();
   while(imagecolorat($im, 
               round((imagesx($im) - 1) * $x / 4) + (imagesx($im) - 1) / 2, 
               round((imagesy($im) - 1) * $y / 4) + (imagesy($im) - 1) / 2)
         !== 0xFFFFFF)
   {  $i++;
      $color = imagecolorat($im, 
            round((imagesx($im) - 1) * $x / 4) + (imagesx($im) - 1) / 2, 
            round((imagesy($im) - 1) * $y / 4) + (imagesy($im) - 1) / 2);
      $color += 0x010101;
      imagesetpixel($im, 
            round((imagesx($im) - 1) * $x / 4) + (imagesx($im) - 1) / 2, 
            round((imagesy($im) - 1) * $y / 4) + (imagesy($im) - 1) / 2, 
            $color);
      $xn = sin($a * $y) - cos($b * $x);
      $yn = sin($c * $x) - cos($d * $y);
      $x = $xn;
      $y = $yn;
   }
   if(microtime_float() - $time > .1) {
      echo "\r".round($i / (microtime_float() - $time))." steps per second";
   }
   return $i;
}
}

###############################################################################
$plot->log("initializing variables");
// go
if(!isset($h) || !isset($w)) {
   $w = 640;
   $h = 480;
}
if(!isset($a) || !isset($b) || !isset($c) || !isset($d)) {
   $a = 3;
   $b = 3;
   $c = 3;
   $d = 3;
}
if(!isset($renderfile)) {
   $renderfile = "render.png";
}
$i = 0;
$im = imagecreatetruecolor($w + 1, $h + 1);
$plot->log("beginning math loop");
$elapsed = microtime_float();
for($x = 0; $x < imagesx($im); $x++) {
   for($y = 0; $y < imagesy($im); $y++) {
      if(imagecolorat($im, $x, $y) !== 0xFFFFFF)
         $i += de_jong($im, ($x / $w - 1) * 2, 
               ($y / $h - 1) * 2, $a, $b, $c, $d);
   }
}
echo "\r";
$elapsed = microtime_float() - $elapsed;
$plot->log("completed in $i iterations in ".
      number_format($elapsed, 5, '.', '')." seconds (".
      number_format($i / $elapsed, 2, '.', '')." i/t)");
$plot->log("writing image to $renderfile");
imagepng($im, $renderfile);
$plot->log("done");

?>
