<?php

if(!defined("LIBPATH")) {
   $libpath = "..";
   set_include_path(get_include_path() . PATH_SEPARATOR . $libpath);
   define("LIBPATH", $libpath);
}

// for color pallate support, uncomment lines commented out with # in this file
#require_once("color_palette.php");

if(!function_exists("de_jong_fast_draw")) {
   function de_jong_fast_draw($filename, $array) {
      global $dejong_max, $bgcolor, $fgcolor, $colors, $plot;
      $xsize = count($array);
      $ysize = count($array[0]);
      $plot->log("allocating image");
      $im = imagecreatetruecolor($xsize, $ysize);
      $plot->log("drawing image");
      $bgr = ($bgcolor & 0xff0000) >> 16;
      $bgg = ($bgcolor & 0xff00) >> 8;
      $bgb = ($bgcolor & 0xff);
      $r = (($fgcolor & 0xff0000) >> 16) - $bgr;
      $g = (($fgcolor & 0xff00) >> 8) - $bgg;
      $b = (($fgcolor & 0xff)) - $bgb;
      for($x = 0; $x < $xsize; $x++) {
         for($y = 0; $y < $ysize; $y++) {
            $ratio = (float)($array[$x][$y] / $dejong_max);
#            $color = $colors[(int)($ratio*255)];
            $color = (int)($r * $ratio + $bgr) << 16;
            $color += (int)($g * $ratio + $bgg) << 8;
            $color += (int)($b * $ratio + $bgb);
            imagesetpixel($im, $x, $y, $color);
         }
      }
      $plot->log("writing image to $filename");
      imagepng($im, $filename);
   }
}

if(!function_exists("de_jong_fast")) {
   function de_jong_fast(&$array, $x, $y, $a, $b, $c, $d) {
      
      global $dejong_max;
      if($dejong_max == 0) $dejong_max = 2147483647;  // maxsize(int)!
      $i = 0;
      
      $xsize = count($array);
      $ysize = count($array[0]);
      
      if(abs($x) > 2 || abs($y) > 2) {
         // fix this
         $x = ($x / $xsize - 1) * 2;
         $y = ($y / $ysize - 1) * 2;
      }
      
      $xn = sin($a * $y) - cos($b * $x);
      $yn = sin($c * $x) - cos($d * $y);
      $x = $xn;
      $y = $yn;
      $ax = ($x + 2) * ($xsize / 4);
      $ay = ($y + 2) * ($ysize / 4);
      // timing
      $time = microtime(true);
      
      while($array[$ax][$ay] < $dejong_max) {
         $i++;
         $array[$ax][$ay]++;
         $xn = sin($a * $y) - cos($b * $x);
         $yn = sin($c * $x) - cos($d * $y);
         $x = $xn;
         $y = $yn;
         $ax = ($x + 2) * ($xsize / 4);
         $ay = ($y + 2) * ($ysize / 4);
      }
      // helpful output
      if(microtime(true) - $time > .1) {
         clearline();
         echo round($i / (microtime(true) - $time))." steps per second";
      }

      return $i;
   }
}

if(!function_exists("clearline")) {
   function clearline() {
      echo "\r";
      for($x = 0; $x < 79; $x++)
         echo " ";
      echo "\r";
      return;
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
if(!isset($renderfile))
   $renderfile = "render.png";
if(!isset($dejong_max))
   $dejong_max = 2147483647;  // maxsize(int)!
if(!isset($fgcolor))
   $fgcolor = 0xFFFFFF;
$plot->log("initializing data array");
$arr = array();
for($x = 0; $x < $w; $x++) {
   $arr[$x] = array();
   for($y = 0; $y < $h; $y++)
      $arr[$x][$y] = 0;
}
$i = 0;
$plot->log("beginning math loop");
$elapsed = microtime(true);
for($x = 0; $x < $w; $x++) {
   for($y = 0; $y < $h; $y++) {
      if($arr[$x][$y] < $dejong_max )
         $i += de_jong_fast($arr, ($x / $w - 1) * 2, 
               ($y / $h - 1) * 2, $a, $b, $c, $d);
   }
}
clearline();
$elapsed = microtime(true) - $elapsed;
$plot->log("completed in $i iterations in ".
      number_format($elapsed, 5, '.', '')." seconds (".
      number_format($i / $elapsed, 2, '.', '')." i/t)");
$plot->log("creating image");
de_jong_fast_draw($renderfile, $arr);
$plot->log("done");
unset($arr);

?>
