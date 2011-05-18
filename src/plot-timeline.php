<?php
/** plot-timeline.php
 * plot-timeline PHP Execution Plotter
 * Copyright 2008 Scott Perry <numist@numist.net>, 
 * Public Domain.
 *
 * plot-timeline in php inspired by Federico Mena-Quintero's python
 * implementation for benchmarking C code:
 * http://primates.ximian.com/~federico/news-2006-03.html#09
 *
 * NOTE:
 * rendering to an image can be very memory and time intensive, so rendering as part of the script
 * being profiled or rendering large datasets is not advocated unless you have the time and memory
 * to burn.
 */

// TODO: I can probably be more specific about the version.  later.
$version = explode(".", PHP_VERSION);
$version = (int)array_shift($version);
if($version < 5) {
	trigger_error("plot-timeline does not support PHP versions < 5", E_USER_ERROR);
}

/*    _   _                            _               
 *   | |_(_)_ __ ___   ___ _ __    ___| | __ _ ___ ___ 
 *   | __| | '_ ` _ \ / _ \ '__|  / __| |/ _` / __/ __|
 *   | |_| | | | | | |  __/ |    | (__| | (_| \__ \__ \
 *    \__|_|_| |_| |_|\___|_|     \___|_|\__,_|___/___/
 *
 * this class is used for fairly precise timings and allows the plot-timeline class to lessen
 * the Heisenberg effect of profiling by pausing and restarting the timer at the beginning and
 * end of the log() function.
 */
class Timer {
	private $start;
	private $stop;

	/* initialization/timer start methods */
	function __construct($startedat=null) {
		if($startedat === null) {
			// we'll accept no argument, in which case the timer starts now
			$this->start();
		} else if (is_numeric($startedat) || strtotime($startedat) !== false) {
			// or we'll accept a number (or string representing a time)
			if(!is_numeric($startedat)) {
				$startedat = strtotime($startedat);
			}
			// in which case, the timer started then
			$this->start = $startedat;
		} else {
			// or we'll destroy the world.
			trigger_error("$startedat is not a valid time!", E_USER_ERROR);
		}
	}

	function restart() {
		$this->start();
	}

	function start() {
		$this->start = microtime(true);
		$this->stop = false;
	}

	/* timer flow control methods */
	function stop() {
		return $this->pause();
	}

	function pause() {
	   if(!$this->stop) {
		   $this->stop = microtime(true);
	   }
		return $this->split();
	}

	function cont() {
		if($this->stop == false) return;
		$this->start += microtime(true) - $this->stop;
		$this->stop = false;
	}

	/* getter methods */
	function split() {
		if($this->stop) {
			return $this->stop - $this->start;
		}
		return microtime(true) - $this->start;
	}
}

class plot_timeline {
	
	/*          _       _        _   _                _ _                     _               
	 *    _ __ | | ___ | |_     | |_(_)_ __ ___   ___| (_)_ __   ___      ___| | __ _ ___ ___ 
	 *   | '_ \| |/ _ \| __|____| __| | '_ ` _ \ / _ \ | | '_ \ / _ \    / __| |/ _` / __/ __|
	 *   | |_) | | (_) | ||_____| |_| | | | | | |  __/ | | | | |  __/   | (__| | (_| \__ \__ \
	 *   | .__/|_|\___/ \__|     \__|_|_| |_| |_|\___|_|_|_| |_|\___|    \___|_|\__,_|___/___/
	 *   |_|
	 *
	 * yes, the timer class is contained in this class as well.  chill.
	 * this class provides the logging functionality, as well as code for loading an existing
	 * result set and the output code for rendering the results.
	 */
	
	// timer is an instanciation of the Timer class for timelining plog events.
	private $timer;
	
	/* the plog contains all of the logged data.  this is currently kept in memory which means
	 * for small scripts, the size of the plog will be visible in the memory usage for the script.
	 * the workaround for this is to write it to disk, which would slow it down quite a bit when
	 * instrumentation is enabled.  given that most of the time when I'm debugging memory issues
	 * the problem is that I'm running out of it, a few K of plog are not going to bother me
	 * compared to a few M of errant memory.
	 */
	private $plog;
	
	/* this variable is only set when logging is stopped and finished, and stores a render of
	 * the plog data in image resource form, for writing using dataURI() or saveImage().
	 */
	private $render;
	
	/* accept log() calls?
	 */
	private $enabled;
	
	private $stack = array(0);
	
	function __construct() {
		$this->plog = array();
		$this->enabled = true;
		$this->timer = null;
		$this->render = null;
		$this->ticks = false;
	}
	
	public function enabled($bool) {
	   $this->enabled = $bool ? true : false;
	}
	
	public function entries() {
	   return count($this->plog);
	}
	
	/*
	 * logging
	 */	
	private static function return_bytes($val) {
      $val = trim($val);
      $last = strtolower($val{strlen($val)-1});
      switch($last) {
         // The 'G' modifier is available since PHP 5.1.0
         case 'g':
            $val *= 1024;
         case 'm':
            $val *= 1024;
         case 'k':
            $val *= 1024;
      }

      return $val;
   }
	
	private static function return_human($val) {
      if(!is_numeric($val)) return false;
      $letters = array("", "K", "M", "G", "T", "P");
      $i = 0;
      while($val > 1023) {
         $i++;
         $val /= 1024;
      } 
      if(round($val) != $val)
         return number_format($val, 2).$letters[$i];
      return $val.$letters[$i];
   }
	
	private static function spaces($int) {
      if(!is_int($int)) trigger_error("need an integer", E_USER_ERROR);
      
      $str = '';
      for($i = 0; $i < $int; $i++) $str .= " ";
      return $str;
   }
   
   // called at the beginning of all blocks
   public function entry() {
      if(!$this->enabled) return;
		if($this->timer) $this->timer->pause();
		$elapsed = $this->timer ? $this->timer->split() : (float)0;
	   
      $trace = debug_backtrace();
      $height = count($stack) - 1;
      array_push($this->stack, count($trace));
      
      // do we log this as an entry?
      if($this->stack[count($this->stack) - 1] > $this->stack[count($this->stack) - 2]) {
         // format our time
   		$time = number_format($elapsed, 5, '.', '');
   		
         $filename = $trace[0]['file'];
   		$line = $trace[0]['line'];
   		if(count($trace) > 1) {
   			$func = array_key_exists("class", $trace[1]) ?
   				$trace[1]['class'].$trace[1]['type'].$trace[1]['function'] : $trace[1]['function'];
   		} else {
   			$func = "main";
   		}

   		$entry = array("time"  => $time, 
                        "filename"  => $filename,
    			            "func" => $func,
   			            "line" => $line,
                        "text"  => self::spaces($height)."entry");

          $entry["mem_used"] = memory_get_usage();
          $entry["mem_avail"] = self::return_bytes(ini_get("memory_limit"));

          if($color != null && is_numeric($color)) {
             $entry['color'] = $color;
          }

   	    $this->plog[] = $entry;
   	    
   	    // start the timer if it hasn't been started already
   	    if(!$this->timer) $this->timer = new Timer();
      }
      
      if($this->timer) $this->timer->cont();
   }
   
   // called at the end of all blocks
   public function escape() {
      if(!$this->enabled) return;
		if($this->timer) $this->timer->pause();
		$elapsed = $this->timer ? $this->timer->split() : (float)0;
	   
      $height = array_pop($this->stack);
      
      // do we log this as an entry?
      if($height > $this->stack[count($this->stack) - 1]) {
         $trace = debug_backtrace();
         
         // format our time
   		$time = number_format($elapsed, 5, '.', '');
   		
         $filename = $trace[0]['file'];
   		$line = $trace[0]['line'];
   		if(count($trace) > 1) {
   			$func = array_key_exists("class", $trace[1]) ?
   				$trace[1]['class'].$trace[1]['type'].$trace[1]['function'] : $trace[1]['function'];
   		} else {
   			$func = "main";
   		}

   		$entry = array("time"  => $time, 
                        "filename"  => $filename,
    			            "func" => $func,
   			            "line" => $line,
                        "text"  => self::spaces($height - 1)."exit");

          $entry["mem_used"] = memory_get_usage();
          $entry["mem_avail"] = self::return_bytes(ini_get("memory_limit"));

          if($color != null && is_numeric($color)) {
             $entry['color'] = $color;
          }

   	    $this->plog[] = $entry;
   	    
   	    // start the timer if it hasn't been started already
   	    if(!$this->timer) $this->timer = new Timer();
      }
      
      if($this->timer) $this->timer->cont();
   }
	
	public static function req($filename) {
	   $tmpfile = self::req_rewrite($filename);
	   require($tmpfile);
	}
	
	public static function req_once($filename) {
	   $tmpfile = self::req_rewrite($filename);
	   require_once($tmpfile);
	}
	
	public static function inc($filename) {
	   $tmpfile = self::req_rewrite($filename);
	   include($tmpfile);
	}
	
	public static function inc_once($filename) {
	   $tmpfile = self::req_rewrite($filename);
	   include_once($tmpfile);
	}
	
	private static function req_rewrite($filename) {
	   $tmpfile = sys_get_temp_dir().(substr(sys_get_temp_dir(), -1) == DIRECTORY_SEPARATOR ?
	              "" : DIRECTORY_SEPARATOR).str_replace("/", '_$_', $filename);
	   // luckily for us, file_get_contents checks the include path, so we don't have to
	   // if it fails, pass up the filename and let the appropriate function fail as it should.
	   $file = file_get_contents($filename);
	   if(!$file) return $filename;
	   
	   $file = str_replace(array("{", "}", "require(", "require_once(", "include(", "include_once("),
	      array('{ global $p; $p->entry();', ';$p->escape();}',
	         'plot_timeline::req(', 'plot_timeline::req_once(',
	         'plot_timeline::inc(', 'plot_timeline::inc_once('), $file);
	   file_put_contents($tmpfile, $file);
	   
	   var_dump($file);
	   
	   return $tmpfile;
	}
	
	/* log an event.
	 * this method is a little tricky in that it gathers information about where it was called by
	 * looking at the backtrace.  while this is an expensive operation time-wise, the timer is
	 * paused while in the log function, so it's not really a concern.
	 * XXX: THIS FUNCTION IS NOT THREAD-SAFE!
	 */
	function log($message, $color=null) {
	   if(!$this->enabled) return;
		if($this->timer) $this->timer->pause();
		$elapsed = $this->timer ? $this->timer->split() : (float)0;
		
		// adding to the log invalidates any existing renders.
		if($this->render) {
		   unset($this->render);
		}
		
		// format our time
		$time = number_format($elapsed, 5, '.', '');
		
		// get our file, line, and function
		$trace = debug_backtrace();
		$filename = $trace[0]['file'];
		$line = $trace[0]['line'];
		if(count($trace) > 1) {
			$func = array_key_exists("class", $trace[1]) ?
				$trace[1]['class'].$trace[1]['type'].$trace[1]['function'] : $trace[1]['function'];
		} else {
			$func = "main";
		}

		$entry = array("time"  => $time, 
                     "filename"  => $filename,
 			            "func" => $func,
			            "line" => $line,
                     "text"  => $message);

       $entry["mem_used"] = memory_get_usage();
       $entry["mem_avail"] = self::return_bytes(ini_get("memory_limit"));
       
       if($color != null && is_numeric($color)) {
          $entry['color'] = $color;
       }

	    $this->plog[] = $entry;
	    
		if($this->timer) $this->timer->cont();
		else $this->timer = new Timer();
	}
	
	
	/*
	 * file operations
	 */
	/* load a previous plog from disk.  while you can continue using an old plog, this is not
	 * a common use case. in fact, I've never wanted or needed to do this. */
	function loadData($filename) {
	   $data = file_get_contents($filename);
	   if(!$data) {
	      trigger_error("could not read $filename", E_USER_ERROR);
	   }
		$data = unserialize($data);
		if(!$data) {
		   trigger_error("could not unserialize data from $filename", E_USER_ERROR);
		}
		extract($data);
		if(!isset($plog) || !isset($started)) {
		   trigger_error("data in $filename is erroneous or incomplete", E_USER_ERROR);
		}
		$this->plog = $plog;
		$this->timer = new Timer($started);
	}
	
	/* save the current plog to disk for rendering/continuing later */
	function saveData($filename) {
	   $started = time() - $this->timer->split();
	   $store = array("started" => $started, "plog" => $this->plog);
	   file_put_contents($filename, serialize($store));
	}
	
	/* save a render of the plog to file.  plogs are saved as png only */
	function saveImage($filename) {
		if(!$this->render) $this->render();
		
		imagepng($this->render, $filename);
	}
	
	/* this function makes use of the data URI scheme (learn more at
	 * http://en.wikipedia.org/wiki/Data:_URI_scheme ) for embedding a profile chart at the end of
	 * the page being profiled.  because rendering is expensive, this is a fairly costly operation,
	 * but there are times when this is worth it.
	 */
	function dataURI() {
		if(!$this->render) $this->render();
		
		ob_start();
	    imagepng($this->render);
	    $image = ob_get_contents();
	    ob_end_clean();

	    $base64 = base64_encode($image);
	    return "data:image/png;base64,$base64";
	}
	
	/*
	 * rendering
	 */
	private static function plot_data_compare($a, $b) {
	   if($a['time'] === $b['time'])
	      return 0;

	   return ($a['time'] < $b['time'] ? -1 : 1);
	}

	/**
	 * function imageSmoothAlphaLine() - version 1.0
	 * from: http://us2.php.net/manual/en/function.imageline.php
	 *
	 * Draws a smooth line with alpha-functionality
	 *
	 * @param  ident    the image to draw on
	 * @param  integer  x1    ( >0 )
	 * @param  integer  y1    ( >0 )
	 * @param  integer  x2    ( >0 )
	 * @param  integer  y2    ( >0 )
	 * @param  integer  color (0x00000000 - 0x7FFFFFFF)
	 *
	 * @access  public
	 *
	 * @author  DASPRiD <d@sprid.de>
	 */
	private static function imageSmoothAlphaLine ($image, $x1, $y1, $x2, $y2, $color) {

	  /* added by Scott Perry so arguments are the same as imageline: */
	  /* */ $alpha = (0x7F000000 & $color) >> 24;
	  /* */ $r     = (0x00FF0000 & $color) >> 16;
	  /* */ $g     = (0x0000FF00 & $color) >> 8;
	  /* */ $b     = (0x000000FF & $color);
	  /* end addition */

	  $icr = $r;
	  $icg = $g;
	  $icb = $b;
	  $dcol = imagecolorallocatealpha($image, $icr, $icg, $icb, $alpha);

	  if ($y1 == $y2 || $x1 == $x2)
	   imageline($image, $x1, $y2, $x1, $y2, $dcol);
	  else {
	   $m = ($y2 - $y1) / ($x2 - $x1);
	   $b = $y1 - $m * $x1;

	   if (abs ($m) <2) {
	     $x = min($x1, $x2);
	     $endx = max($x1, $x2) + 1;

	     while ($x < $endx) {
	       $y = $m * $x + $b;
	       $ya = ($y == floor($y) ? 1: $y - floor($y));
	       $yb = ceil($y) - $y;

	       $trgb = ImageColorAt($image, $x, floor($y));
	       $tcr = ($trgb >> 16) & 0xFF;
	       $tcg = ($trgb >> 8) & 0xFF;
	       $tcb = $trgb & 0xFF;
	       imagesetpixel($image, $x, floor($y), imagecolorallocatealpha($image, ($tcr * $ya + $icr * $yb), ($tcg * $ya + $icg * $yb), ($tcb * $ya + $icb * $yb), $alpha));

	       $trgb = ImageColorAt($image, $x, ceil($y));
	       $tcr = ($trgb >> 16) & 0xFF;
	       $tcg = ($trgb >> 8) & 0xFF;
	       $tcb = $trgb & 0xFF;
	       imagesetpixel($image, $x, ceil($y), imagecolorallocatealpha($image, ($tcr * $yb + $icr * $ya), ($tcg * $yb + $icg * $ya), ($tcb * $yb + $icb * $ya), $alpha));

	       $x++;
	     }
	   } else {
	     $y = min($y1, $y2);
	     $endy = max($y1, $y2) + 1;

	     while ($y < $endy) {
	       $x = ($y - $b) / $m;
	       $xa = ($x == floor($x) ? 1: $x - floor($x));
	       $xb = ceil($x) - $x;

	       $trgb = ImageColorAt($image, floor($x), $y);
	       $tcr = ($trgb >> 16) & 0xFF;
	       $tcg = ($trgb >> 8) & 0xFF;
	       $tcb = $trgb & 0xFF;
	       imagesetpixel($image, floor($x), $y, imagecolorallocatealpha($image, ($tcr * $xa + $icr * $xb), ($tcg * $xa + $icg * $xb), ($tcb * $xa + $icb * $xb), $alpha));

	       $trgb = ImageColorAt($image, ceil($x), $y);
	       $tcr = ($trgb >> 16) & 0xFF;
	       $tcg = ($trgb >> 8) & 0xFF;
	       $tcb = $trgb & 0xFF;
	       imagesetpixel ($image, ceil($x), $y, imagecolorallocatealpha($image, ($tcr * $xb + $icr * $xa), ($tcg * $xb + $icg * $xa), ($tcb * $xb + $icb * $xa), $alpha));

	       $y ++;
	     }
	   }
	  }
	} // end of 'imageSmoothAlphaLine()' function

	private static function approx_round($number) {
		if(!is_numeric($number))
		   return false;
		else if($number == 0)
		   return number_format($number, 2);
		$realnum = $number;
		
		$places = 1;
		while($number < 1) {
		   $number *= 10;
		   $places += 1;
		}
		return number_format($realnum, $places);
	}

	/* the render function converts the data in the plog to an image displaying all the data in
	 * a meaningful way.  it's really large, but what it does is very simple.
	 */
	private function render() {
		// add some last data…
		$this->plog["posix_times"] = posix_times();
		
		$funcs = array("imagecreatetruecolor",
		               "imageline",
		               "imagepng",
		               "imagestring",
		               "imagefontwidth", 
		               "imagefontheight");

		foreach ($funcs as $func)
		   if(!function_exists($func))
		      trigger_error("Required function $func not found!", E_USER_ERROR);
		unset($funcs, $func);
		
		//
		// Initialize variables we need
		//
		// this needs to be populated proper
		$colors = array(  // (R.RR, G.GG, B.BB)
		      0x1e4a7d,   // (0.12, 0.29, 0.49)
		      0x5c82b5,   // (0.36, 0.51, 0.71)
		      0xbf4f4d,   // (0.75, 0.31, 0.30)
		      0x9eba61,   // (0.62, 0.73, 0.38)
		      0x8066a1,   // (0.50, 0.40, 0.63)
		      0x4aabc7,   // (0.29, 0.67, 0.78)
		      0xf59e57);  // (0.96, 0.62, 0.34)

		$memory_limit_color = 0x333333;

		$memory_usage_color = 0x181818;

		$fontsize = 2;

		$fontcolor = 0x7f7f7f;
		$user_color = 0x001f67;
		$sys_color = 0x25851a;

		define("PLOT_DRAW_PERCENT", 0.25);

		$data =& $this->plog;
		
		//
		// Get things we need to know
		//   

		// filter out the stuff that we dont want or need
		/* this fixes the spurious data bug that showed up when resume functionality
		 * was added to the plotter */
		$count = 0;
		while(array_key_exists($count, $data))
		   $count++;
		$rawdata = $data;
		$data = array();
		for($i = 0; $i < $count; $i++)
		   $data[$i] = $rawdata[$i];
		unset($i, $count);

		// get our maxes
		$max_mem_used = 0;
		$max_mem_avail = 0;
		foreach ($data as $point) {
		   if(array_key_exists("mem_used", $point) 
		         && $max_mem_used < $point["mem_used"])
		      $max_mem_used =  $point["mem_used"];
        
		   if(array_key_exists("mem_avail", $point)
		         && $max_mem_avail < $point["mem_avail"])
		      $max_mem_avail = $point["mem_avail"];
		}
        
		if($max_mem_avail > 2 * $max_mem_used) {
		   $max_memory = round($max_mem_used * 1.5);   
		} else {
		   // we need the max absolute value for memory for scaling
		   $max_memory = $max_mem_avail > $max_mem_used ? 
		      $max_mem_avail : $max_mem_used;
		}

		// sort the array based on time, to ensure that hacky data is valid for testing
		usort($data, array("plot_timeline", "plot_data_compare"));

		// get valuable information for rendering…
		$longest = 0;
		$c = 0;
		$files = array();
		for ($i = 0; $i < count($data); $i++) {
			$filelabel = explode(DIRECTORY_SEPARATOR, $data[$i]['filename']);
			$filelabel = $filelabel[count($filelabel) - 1];
			$filelabel .= ":".$data[$i]['line']." ".$data[$i]['func']."()";
		   // store longest string value
		   if($longest < strlen( $data[$i]['time'].
		                         $filelabel.
		                         $data[$i]['text']) + 6)
		      $longest = strlen( $data[$i]['time'].
		                         $filelabel.
		                         $data[$i]['text']) + 6;

			$data[$i]['file'] = $filelabel;
			
			$files[$data[$i]['filename']] = 1;
		}
		unset($i, $c);
		// posix times longer?
		if(array_key_exists("posix_times", $rawdata)) {
		   $posix_time = "system: ".self::approx_round($rawdata["posix_times"]["stime"]/100).
		      "s user: ".self::approx_round($rawdata["posix_times"]["utime"]/100).
		      "s real: ".self::approx_round($data[count($data) - 1]["time"])."s";
		   $longest = $longest > strlen($posix_time) ? $longest : strlen($posix_time);
		   unset($posix_time);
		}
		$memory_size = "max memory used: ".self::return_human($max_mem_used).
		  "B   max memory avail: ".self::return_human($max_mem_avail)."B";
		$longest = $longest > strlen($memory_size) ? $longest : strlen($memory_size);
		unset($max_mem_used, $max_mem_avail);
		/* now set up the colours per entry */
		for($i = 0; $i < count($data); $i++) {
		   if(!array_key_exists('color', $data[$i]))
		      $data[$i]['color'] = count($files) > 5 ?
					$colors[hexdec(substr(md5($data[$i]['filename']), -5)) % count($colors)] :
					$colors[hexdec(substr(md5($data[$i]['func']), -5)) % count($colors)];
		}
		unset($i, $colors, $files);
		$maxtime = $data[count($data) - 1]['time'];
		
      if($maxtime == 0) trigger_error("please log more than one point next time", E_USER_ERROR);
		//
		// Allocate space for an image
		//
		$height = count($data) * imagefontheight($fontsize);
		$width = $longest * imagefontwidth($fontsize) + 400;

		if(array_key_exists("posix_times", $rawdata))
		   $height += imagefontheight($fontsize);

	   $height += imagefontheight($fontsize);

		$im = imagecreatetruecolor($width, $height + 2) or 
		   trigger_error("could not allocate image", E_USER_ERROR);
		unset($width);
		if(!$im) trigger_error("returned resource not valid", E_USER_ERROR);

		//
		// Draw.
		//
		// calculate the multiplier for mapping time to a point
		$mul = $height / $maxtime;
      
		//
		// draw memory usage first first, so everything else goes on top of it
		//
      
		// now...  play within 0 <= y <= 400 for memory
		$pixperbyte = 400 / $max_memory;
		$memory_step = 0;
		$lastdelta = 0;
		$lastpoint = 0;

		// and now the drawing
		for ($y = 0; $y < count($data); $y++) {
		   if(array_key_exists("mem_used", $data[$y])) {
		      // bar graph!
		      imagefilledrectangle($im, 
		            floor(400 - $data[$y]["mem_used"] * $pixperbyte), 
		            $y * imagefontheight($fontsize), 
		            400,
		            ($y + 1) * imagefontheight($fontsize) - 1,
		            $memory_usage_color);
		      // and labels, if applicable
		      if(defined("PLOT_DRAW_PERCENT") &&
		            ($data[$y]["mem_used"]< $memory_step * (1 - PLOT_DRAW_PERCENT)||
		            $data[$y]["mem_used"] > $memory_step * (1 + PLOT_DRAW_PERCENT))
		            ) {
		         $xstart = $data[$y]["mem_used"] * $pixperbyte - imagefontwidth($fontsize);
		         if($xstart < imagefontwidth($fontsize) * 
		               (strlen(self::return_human($data[$y]["mem_used"])."B") + 1))
		            $xstart = imagefontwidth($fontsize) * 
		               (strlen(self::return_human($data[$y]["mem_used"])."B") + 1);
		         imagestring($im, $fontsize,
		               400 - $xstart,
		               $y * imagefontheight($fontsize),
		               self::return_human($data[$y]["mem_used"])."B",
		               $fontcolor);
		         $memory_step = $data[$y]["mem_used"];
		      }
		      if(($lastdelta < 0 && $data[$y]["mem_used"] - $lastpoint > 0) ||
		            ($lastdelta > 0 && $data[$y]["mem_used"] - $lastpoint < 0)) {
		         $xstart = $lastpoint * $pixperbyte - imagefontwidth($fontsize);
		         if($xstart < imagefontwidth($fontsize) * 
		               (strlen(self::return_human($lastpoint)."B") + 1))
		            $xstart = imagefontwidth($fontsize) * 
		               (strlen(self::return_human($lastpoint)."B") + 1);
		         imagestring($im, $fontsize,
		               400 - $xstart,
		               ($y - 1) * imagefontheight($fontsize),
		               self::return_human($lastpoint)."B",
		               $fontcolor);
		      }
		      $lastdelta = $data[$y]["mem_used"] - $lastpoint;
		      $lastpoint = $data[$y]["mem_used"];
		   }
		   // dont draw the available memory limit if we can't see it
		   if(array_key_exists("mem_avail", $data[$y]) 
		         && $data[$y]["mem_avail"] < $max_memory)
		      imageline($im,
		            floor(400 - $data[$y]["mem_avail"] * $pixperbyte),
		            $y * imagefontheight($fontsize),
		            floor(400 - $data[$y]["mem_avail"] * $pixperbyte),
		            ($y + 1) * imagefontheight($fontsize) - 1,
		            $memory_limit_color);
		}
		unset($max_memory, $pixperbyte, $memory_step, $lastdelta, $lastpoint, 
		      $xstart);

		unset($memory_limit_color, $memory_usage_color);

		// draw posix times graph
		if(array_key_exists("posix_times", $rawdata)) {
		   $posix_ratio = 400 / $data[count($data) - 1]["time"];

		   $sys = round($rawdata["posix_times"]["stime"]/100 * $posix_ratio);
		   $user = $sys + round($rawdata["posix_times"]["utime"]/100 * $posix_ratio);

		   imagefilledrectangle($im, 
		         400 - $user,
		         count($data) * imagefontheight($fontsize),
		         400 - $sys,
		         (count($data) + 1) * imagefontheight($fontsize),
		         $user_color);
		   imagefilledrectangle($im,
		         400 - $sys,
		         count($data) * imagefontheight($fontsize),
		         400,
		         (count($data) + 1) * imagefontheight($fontsize),
		         $sys_color);
		   unset($posix_ratio, $sys, $user);
		}

		// draw oneline memory graph
		// eeehhhh, figure out what it should do first...

		// figure out what division lengths to use
		$time = ($maxtime / $height) * 25;
		unset($maxtime);
		$division = 1;
		while($time > 1) {
		   $time /= 10;
		   $division *= 10;
		}
		while($time < 1) {
		   $time *= 10;
		   $division /= 10;
		}
		while($division * $mul < imagefontheight($fontsize) * 2.5)
		   $division *= 10;
		unset($time);

		// draw axis labels
		$x = 0;
		$longest = 0;
		while(round(($x * $division)*$mul) < $height) {
		   // calculate maximum label length for other drawings to use as an offset
		   if(strlen($x * $division) > $longest) 
		      $longest = strlen($x * $division);

		   // draw the label below where the line should be if there is enough room
		   if(round(($x * $division)*$mul) < $height - imagefontheight($fontsize))
		      imagestring($im, $fontsize, 
		                  1, round(($x * $division)*$mul) + 1,
		                  $division * $x, $fontcolor);
		   // otherwise, draw the label above the line
		   else
		      imagestring($im, $fontsize, 
		              1, round(($x * $division)*$mul) - imagefontheight($fontsize) - 1,
		              $division * $x, $fontcolor);
		   $x++;
		}

		// draw division lines
		$xoffset = $longest * imagefontwidth($fontsize) + 2;
		if($xoffset < 20) $xoffset = 20;
		$x = 0;
		while(round(($x * $division)*$mul) < $height) {
		   imageline($im,
		         0, round(($x * $division)*$mul) + 1,
		         $xoffset, round(($x * $division)*$mul) + 1,
		         0x7F7F7F);
		   $x++;
		}
		unset($longest, $height, $division);

		// draw data lines
		for($y = 0; $y < count($data); $y++) {

		   // do the drawing thang
		   // leader
		   imageline($im, 
		             $xoffset + 1, round($mul * $data[$y]['time']) + 1, 
		             $xoffset + 20, round($mul * $data[$y]['time']) + 1, 
		             $data[$y]['color']);
		   // connector
		   if(round($mul*$data[$y]['time']) + 1 !== 
		      round(($y+.5)*imagefontheight($fontsize)))
		      /* imageSmoothAlphaLine has this odd problem that if the line is exactly
		       * horizontal or exactly vertical, it won't draw, so we check to see if
		       * the line is horizontal first, before calling.  The upside to this is 
		       * that GD2's imageline is WAY FASTER than imageSmoothAlphaLine. */
		      self::imageSmoothAlphaLine($im,
		                $xoffset + 20, round($mul * $data[$y]['time']) + 1,
		                380, round(($y + .5) * imagefontheight($fontsize)),
		                $data[$y]['color']);
		   else
		      imageline($im,
		                $xoffset + 20, round($mul * $data[$y]['time']) + 1,
		                380, round(($y + .5) * imagefontheight($fontsize)),
		                $data[$y]['color']);
		   // tailer
		   imageline($im,
		             380, round(($y + .5) * imagefontheight($fontsize)),
		             400, round(($y + .5) * imagefontheight($fontsize)),
		             $data[$y]['color']);
		   // text string
		   imagestring($im, $fontsize, 
		         400 + imagefontwidth($fontsize), 
		         $y * imagefontheight($fontsize), 
		         $data[$y]['time'].": ".$data[$y]['file'].": ".$data[$y]['text'],
		         $data[$y]['color']);

		   // draw time deltas between long gaps
		   if($y > 0) {
		      $point = $mul * $data[$y]['time'] + 1;
		      $pointprev = $mul * $data[$y - 1]['time'] + 1;
		      $midpoint = round(($point + $pointprev)/2);

		      if($point - $pointprev > imagefontheight($fontsize) * 2)
		         imagestring($im, $fontsize, $xoffset + 3, 
		            $midpoint - imagefontheight($fontsize)/2,
		            self::return_human($data[$y]['time'] - $data[$y - 1]['time']), $fontcolor);
		   } else {
		      $point = $mul * $data[$y]['time'] + 1;
		      $pointprev = 1;
		      $midpoint = round(($point + $pointprev)/2);

		      if($point - $pointprev > imagefontheight($fontsize) * 2)
		         imagestring($im, $fontsize, $xoffset + 3, 
		            $midpoint - imagefontheight($fontsize)/2,
		            self::return_human($data[$y]['time']), $fontcolor);
		   }
		}
		unset($point, $mul, $xoffset, $pointprev, $midpoint);

		// posix times?
		if(array_key_exists("posix_times", $rawdata)) {
		   $x = 400 + imagefontwidth($fontsize);
		   imagestring($im, $fontsize,
		         $x,
		         $y * imagefontheight($fontsize),
		         "time breakdown: ",
		         $fontcolor);
		   $x += 16 * imagefontwidth($fontsize);
		   imagestring($im, $fontsize, 
		         $x, 
		         $y * imagefontheight($fontsize), 
		         "system: ".self::approx_round($rawdata["posix_times"]["stime"]/100)."s",
		         $sys_color);
		   $x += (strlen(self::approx_round($rawdata["posix_times"]["stime"]/100)) + 10) * 
		      imagefontwidth($fontsize);
		   imagestring($im, $fontsize, 
		         $x, 
		         $y * imagefontheight($fontsize), 
		         "user: ".self::approx_round($rawdata["posix_times"]["utime"]/100)."s",
		         $user_color);
		   $x += (strlen(self::approx_round($rawdata["posix_times"]["utime"]/100)) + 8) * 
		      imagefontwidth($fontsize);
		   imagestring($im, $fontsize,
		         $x,
		         $y * imagefontheight($fontsize),
		         "real: ".self::approx_round($data[count($data) - 1]["time"])."s",
		         $fontcolor);
		   $y++;
		}
		unset($user_color, $sys_color, $data, $rawdata, $x);

		imagestring($im, $fontsize, 
		      400 + imagefontwidth($fontsize), 
		      $y * imagefontheight($fontsize), 
		      $memory_size, $fontcolor);
		$y++;

		unset($fontsize, $fontcolor, $memory_size, $y);
		
		$this->render = $im;
	}
}

// include argv if we were called directly
if(basename($argv[0]) == basename(__FILE__)) {
   $p = new plot_timeline();
   plot_timeline::req($argv[1]);
   $p->saveData('data.txt');
}

?>