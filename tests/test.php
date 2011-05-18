<?php
for($i = 0; $i < $times; $i++) {
   $random = rand() % $mod;
   usleep($random);
}
$plot->log("finished $i iterations");
?>
