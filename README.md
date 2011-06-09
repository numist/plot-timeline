plot-timeline
=============

A PHP Execution Plotter

Quickstart
----------

To create plotter data directly from within your script:

• At the beginning of your script's execution, insert the following code:

    // include plot-timeline lib
    require("plot-timeline.php");
    // initialize object/begin plotting
    $p = new plot_timeline();

• Sprinkle calls to $p->log() in your code, such as the example below:

    $p->log("started doing stuff");

• When your code has finished execution, insert the following code, changing paths as necessary:

    $datafile = "/tmp/data.dat";   // temporary file for plotting data
    $plot->writeData($datafile);

• Done. Your plot data was written to /tmp/data.dat.

Image rendering
---------------

If you have a data file that you want to render to an image, all you need is the following:

    require("plot-timeline.php");
    $p = new plot_timeline();
    $p->loadData("data.dat");
    $p->saveImage("data.png");

If you want, you could just render the image at the end of your file, but rendering is expensive, so it's not recommended that you make a habit of it unless you care about the output. If you do render in place, saving/loading the data file is unnecessary.

plot-timeline also supports rendering to a data: URI, so profiling information can be shown at the bottom of your web page. The `plot_timeline::dataURI()` function will return the data.

Advanced methods
----------------

There are other ways to use this script other than the method listed above:

### Disabling the plotter

To disable the plotter in code that it has been integrated into, pass `false` into the constructor, and the plotter functions will all return as soon as called, removing most of the overhead that the plotter adds.

You can also call `plot_timeline::enabled(bool)` at any time to enable or disable logging.

### Custom colours at log-time

The plot-timeline::log() function has an optional third parameter which can be used as a color, an `0xAARRGGBB` integer representing an alpha channel, red channel, green channel, and blue channel.  The alpha channel can be any value between and including 0 (00) through 126 (7E).  127 (0x7F) is fully transparent, and will cause the plotter to fall back to using automatic colouration based on the filename or function name of the caller.

Values for RR, GG and BB can be between and include 0 (00) through 255 (FF).

    $p->log("rawr! COLOURS!", 0x776655);

I just want to look at pictures!
--------------------------------

If you `cd` to the `tests/` directory and run `driver.php` or `functest.php`, some example programs with varying efficiency will be profiled and the resulting graph saved for your enjoyment.

License
-------

© 2006 - 2011 Scott Perry, plot-timeline's source code is released under the [Creative Commons Attribution 3.0 Unported License](http://creativecommons.org/licenses/by/3.0/).