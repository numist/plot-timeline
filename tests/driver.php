<?php
/** driver.php
 * real-world plotter example script
 */

if(!function_exists("pathmunge")) {
   function pathmunge($path, $after=false) {
      if(!ereg('(^|:)'.$path.'($|:)', get_include_path())) {
         echo "added $path to path\n";
         if($after)
            set_include_path(get_include_path() . PATH_SEPARATOR . $path);
         else
            set_include_path($path . PATH_SEPARATOR . get_include_path());
      }
      
      return;
   }
}

pathmunge("../src");

error_reporting(E_ALL);
require("plot-timeline.php");

define("PLOT_TRACK_MEMORY", true);
ini_set("memory_limit", "2M");

// init new plotter
$plot = new plot_timeline();
$plot->log("set up logging");
###############################################################################
// make some data
$times = 100;
$mod = 100000;
for($i = 0; $i < $times; $i++) {
   $random = rand() % $mod;
   usleep($random);
}
$plot->log("finished $i iterations");
###############################################################################
$renderfile = "test.png";
$dejong_max = 255;
$fgcolor = 0xFFFFFF;
$bgcolor = 0x000000;
$w = 100;
$h = 100;
//$a = $b = $c = $d = 2;
$a = 1.4;
$b = -2.3;
$c = 2.4;
$d = -2.1;
require("dejong.fast.php");
unset($renderfile);
$plot->log("returned from dejong.fast.php");
###############################################################################
// make some more data
require("test.php");
$plot->log("returned from test.php");
###############################################################################
$renderfile = "ball.png";
$dejong_max = 255;
$fgcolor = 0xCCCCFF;
$bgcolor = 0x000000;
$h = $w = 100;
$a = 100;
$b = 100;
$c = 100;
$d = 100;
require("dejong.fast.php");
unset($renderfile);
$plot->log("returned from dejong.fast.php");
###############################################################################
// make some more data
require("test.php");
$plot->log("returned from test.php");
###############################################################################
// now to test plotter aggregation

$plot->log("closing and reopening plotter", 0x7f7f7f);

$plot->saveData("data.txt");
unset($plot);
// init new plotter
$plot = new plot_timeline();
$plot->loadData("data.txt");
$plot->log("reinstated plotter, ".($plot->entries()).
      " entries", 0x7f7f7f);
###############################################################################
$h = $w = 64;
$a = $b = $c = $d = 3;
require("dejong.php");
$plot->log("returned from dejong.php");
###############################################################################
// make some more data
require("test.php");
$plot->log("returned from test.php");
###############################################################################
$color = 0xFFFFFF;
for($i = 0; $i < 10; $i++) {
   $plot->log("color test iteration $i, color 0x".dechex($color), $color);
   $color -= 0x111111;
}
$plot->log("returned from color test loop");
###############################################################################

// write to file
$plot->log("closing plotter");

$plot->saveImage("data.png");

echo "done.\n";
?>
