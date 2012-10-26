<?php
/**
 * @file builder.php
 * @date 2012-10-26 09:50 PDT
 * @author Paul Reuter
 * @version 1.0.1
 *
 * @modifications
 * 1.0.0 - 2012-10-26 - Exfiltrated from SymbolListArchiver.php
 * 1.0.1 - 2012-10-26 - Modify: Added header, column for exchange.
 */


require_once(dirname(__FILE__).'/Archiver.php');
require_once(dirname(__FILE__).'/HTMLTable.php');
require_once(dirname(__FILE__).'/SymbolListArchiver.php');


function main($args) { 
  $pname = array_shift($args);

  $lst = new SymbolListArchiver();
  if( $lst->acquire() ) { 
    $tuple = $lst->getExchangeSymbolNameTuple();
    array_multisort($tuple);
    $n=0;
    printf("%5s\t%s\t%s\t%s\n","ID","Exchange","Symbol","Name");
    foreach( $tuple as $record ) { 
      list($exch,$sym,$name) = $record;
      printf("%5d\t%s\t%s\t%s\n",++$n,$exch,$sym,$name);
    }
  } else { 
    error_log("epic failure.");
  }
} // END: function main($args)


$args = (isset($argv)) ? $argv : array(basename(__FILE__));
main($args);

// EOF -- builder.php
?>
