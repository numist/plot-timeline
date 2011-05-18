<?php
/** functest.php
 * exhaustive functionality test for the plotter class
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

#$pause = 1000000;
$pause = 0;

ini_set("memory_limit", "1M");

error_reporting(E_ALL);
require("plot-timeline.php");

// init new plotter
$plot = new plot_timeline();
$plot->log("set up logging");
###############################################################################
usleep(round(rand()*$pause/getrandmax()));
$plot->log("closing and reopening plotter");
// now to test plotter aggregation
$plot->saveData("func.txt");
unset($plot);
// init new plotter
$plot = new plot_timeline();
$plot->loadData("func.txt");
// use relative paths and log to stdout only
$plot->log("reinstated plotter, ".($plot->entries())." entries");
###############################################################################
usleep(round(rand()*$pause/getrandmax()));
$plot->log("testing custom message coloration");
$color = 0xFFFFFF;
for($i = 0; $i < 15; $i++) {
   if($i > 0) usleep(round($pause/1000));
   $plot->log("color test iteration $i, color 0x".dechex($color), 
         $color);
   $color -= 0x111111;
}
unset($color);
###############################################################################
// more test cases here...
###############################################################################
$plot->log("ok, we're done.");

ini_set("memory_limit", "8M");

$plot->saveImage("func.png");

?>
