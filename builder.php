<?php
/**
 * @file builder.php
 * @date 2012-10-26 09:21 PDT
 * @author Paul Reuter
 * @version 1.0.0
 *
 * @modifications
 * 1.0.0 - 2012-10-26 - Exfiltrated from SymbolListArchiver.php
 */


require_once(dirname(__FILE__).'/Archiver.php');
require_once(dirname(__FILE__).'/HTMLTable.php');
require_once(dirname(__FILE__).'/SymbolListArchiver.php');


function main($args) { 
  $pname = array_shift($args);

  $lst = new SymbolListArchiver();
  if( $lst->acquire() ) { 
    $map = $lst->getSymbolNameMap();
    $n=0;
    foreach( $map as $k=>$v ) { 
      printf("%5d\t%s\t%s\n",++$n,$k,$v);
    }
  } else { 
    error_log("epic failure.");
  }
} // END: function main($args)


$args = (isset($argv)) ? $argv : array(basename(__FILE__));
main($args);

// EOF -- builder.php
?>
