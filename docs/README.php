This file is written to take benefit from PHP syntax highlighting. <?php/*

plot-timeline PHP Execution Plotter
Copyright 2006 - 2007 Scott Perry, Creative Common ShareAlike 2.5 License
For license details, see: http://creativecommons.org/licenses/by-sa/2.5/

###############################################################################
QUICKSTART: 

To create plotter data directly from within your script:

1. ensure that plot-timeline.php is in your include path.

******************************/
$path = './plot-timeline/src';
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
/******************************
* if you want to get really fancy, write a pathmunge() function, or get mine
  from tests/functest.php or tests/driver.php.

2. at the beginning of your script's execution, insert the following code:
******************************/
// include plot-timeline lib
require("plot-timeline.php");
// initialize object/begin plotting
$p = new plot_timeline();
/******************************

3. Sprinkle calls to $p->log() in your code, such as the example below:
******************************/
$p->log("started doing stuff");
/******************************

4. When your code has finished execution, insert the following code, changing
   file paths as necessary:
******************************/
$datafile = "/tmp/data.dat";   // temporary file for plotting data
$plot->writeData($datafile);
/******************************

5. Done.  your data was written to /tmp/data.dat

###############################################################################
Image rendering:

*/
$p = new plot_timeline();
$p->loadData("data.dat");
$p->saveImage("data.png");
/*

note that if you want, you could just render the image at the end of your file,
there's no reason not to other than rendering takes a while.

if you want to use the data: URI, that is also supported, just call

*/
echo $p->dataURI();
/*

###############################################################################
Other methods for use:

There are other ways to use this script other than the method listed above.
See sections below.

-------------------------------------------------------------------------------
Disabling plotter:

To disable the plotter in code that it has been integrated into, pass a 
(bool)false into the constructor, and the plotter functions will all return 
as soon as called, removing almost all the overhead that the plotter adds.

You can also insert the following line after intializing the data object:
******************************/
$p->enabled(false);
/******************************

-------------------------------------------------------------------------------
Custom colours in plot-timeline::log() calls:

The plot-timeline::log() function has an optional third parameter which can be
used as a color, an 0xAARRGGBB integer representing an alpha channel, red 
channel, green channel, and blue channel.  The alpha channel can be any value
between and including 0 (00) through 126 (7E).  127 (0x7F) is fully 
transparent, so is not supported.  Using an alpha channel value of 127 will 
cause the plotter to fall back to using automatic colouration based on the 
filename portion of the call.  Why you'd use alpha channels in plot-timeline
is beyond me...  don't bother.
Values for RR, GG and BB can be between and include 0 (00) through 255 (FF).

******************************/
$p->log("rawr! COLOURS!", 0x776655);
/******************************

###############################################################################

Getting immersed:
Some folks learn better from looking at code in the wild, instead of reading
docs.  I understand, it's the same with me.  Why don't you look at the tests/
directory, and run driver.php or functest.php from there to see how the whole
thing works? (make sure your CWD is tests when you run them)

If you have any further issues, come on down to find me as numist on freenode
or on AIM.

*/?>