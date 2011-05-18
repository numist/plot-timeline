<?php

# color_palette.php
# Joe Auricchio, 2006
# Public Domain. Use, share, learn, and enjoy!

if(!function_exists("_red"))
{
   function _red($i)
   {
      return (int)(16 * sqrt($i));
   }
}
if(!function_exists("_green")) {
   function _green($i)
   {
      return (int)((sin( ($i/255)*3.141592653589793) ) * 255);
   }
}
if(!function_exists("_blue"))
{
   function _blue($i)
   {
      return (int)((cos( ($i/255)*3.141592653589793) ) * 255);
   }
}

trigger_error("Building color table...", E_USER_NOTICE);
$colors = array();
for($i = 0; $i < 256; $i++)
{
  $colors[$i] = _red($i) << 16;
  $colors[$i] = _green($i) << 8;
  $colors[$i] = _blue($i) ;
}
trigger_error("Done building color table", E_USER_NOTICE);

