<?php
/**
 * @file SymbolListArchiver.php
 * @date 2012-10-26 09:50 PDT
 * @author Paul Reuter
 * @version 1.0.3
 *
 * @modifications
 * 1.0.0 - 2012-03-16 - Created
 * 1.0.1 - 2012-08-15 - BugFix: case sensitivity. Change m_rootDir
 * 1.0.2 - 2012-10-26 - Modify: Moved execute code to builder.php
 * 1.0.3 - 2012-10-26 - Modify: Added getExchangeSymbolNameTuple()
 */


require_once(dirname(__FILE__).'/Archiver.php');
require_once(dirname(__FILE__).'/HTMLTable.php');


/**
 * @package YahooDataRetrievalSystem
 * @subpackage Archiver
 */
class SymbolListArchiver extends Archiver {
  var $m_rootDir = './data/SymbolListArchiver';

  function SymbolListArchiver($opts=null) { 
    parent::Archiver($opts);
    return $this;
  } // END: function SymbolListArchiver($opts=null)


  function acquire() {
    $isOk = true;
    $ach = new NasdaqSymbolListArchiver(array('DATA_DIR'=>$this->m_rootDir));
    if( !$ach->acquire('NASDAQ') ) { 
      $isOk = false;
    }
    if( !$ach->acquire('NYSE') ) { 
      $isOk = false;
    }
    if( !$ach->acquire('AMEX') ) { 
      $isOk = false;
    }
    $ach = new OtcSymbolListArchiver(array('DATA_DIR'=>$this->m_rootDir));
    if( !$ach->acquire() ) { 
      $isOk = false;
    }
    $ach = new EtfSymbolListArchiver(array('DATA_DIR'=>$this->m_rootDir));
    if( !$ach->acquire() ) { 
      $isOk = false;
    }
    return $isOk;
  } // END: function acquire()


  function getSymbols() { 
    return array_keys($this->getSymbolNameMap());
  } // END: function getSymbols()

  
  function getSymbolNameMap() { 
    $map = array();
    foreach ($this->getExchangeSymbolNameTuple() as $row) { 
      $map[$row[1]] = $row[2];
    }
    return $map;
  } // END: function getSymbolNameMap()


  function getExchangeSymbolNameTuple() { 
    $tuple = array();

    $ach = new NasdaqSymbolListArchiver(array('DATA_DIR'=>$this->m_rootDir));
    $mat = $ach->parseCSVFile($ach->getExchangeFilePath('NASDAQ'));
    $ihdr = array_flip(array_shift($mat));
    foreach($mat as $row) { 
      $tuple[] = array('NASDAQ',$row[$ihdr['Symbol']],$row[$ihdr['Name']]);
    }

    $mat = $ach->parseCSVFile($ach->getExchangeFilePath('NYSE'));
    $ihdr = array_flip(array_shift($mat));
    foreach($mat as $row) { 
      $tuple[] = array('NYSE',$row[$ihdr['Symbol']],$row[$ihdr['Name']]);
    }

    $mat = $ach->parseCSVFile($ach->getExchangeFilePath('AMEX'));
    $ihdr = array_flip(array_shift($mat));
    foreach($mat as $row) { 
      $tuple[] = array('AMEX',$row[$ihdr['Symbol']],$row[$ihdr['Name']]);
    }

    $ach = new OtcSymbolListArchiver(array('DATA_DIR'=>$this->m_rootDir));
    $mat = $ach->parseCSVFile($ach->getFilePath());
    $ihdr = array_flip(array_shift($mat));
    foreach($mat as $row) { 
      $tuple[] = array(
        'OTCBB',
        $ach->getSymbolFromRow($row,$ihdr),
        $row[$ihdr['Security Name']]
      );
    }

    // NB: Etfs parse HTML, hence separate parser.
    $ach = new EtfSymbolListArchiver(array('DATA_DIR'=>$this->m_rootDir));
    $mat = $ach->parseFile($ach->getFilePath());
    $ihdr = array_flip(array_shift($mat));
    foreach($mat as $row) { 
      $tuple[] = array('ETF',$row[$ihdr['Ticker']],$row[$ihdr['Fund Name']]);
    }

    return $tuple;
  } // END: function getSymbolNameExchangeTuple()


  function parseCSVFile($fpath) {
    $mat = array();
    if( !is_readable($fpath) ) { 
      return $mat;
    }
    $fp = fopen($fpath,'r');
    $ncol = 0;
    while( ($row=fgetcsv($fp)) !== false ) { 
      if( count($row)==1 && empty($row[0]) ) { 
        continue;
      }
      $mat[] = $row;
    }
    fclose($fp);
    return $this->m_applyCallback(array($this,'m_scrubEntities'),$mat);
  } // END: function parseCSVFile($fpath)


  function m_applyCallback($cb,$mat) { 
    foreach( array_keys($mat) as $i ) { 
      $mat[$i] = array_map($cb,$mat[$i]);
    }
    return $mat;
  } // END: function m_applyCallback($cb,$mat)


  function m_scrubEntities($str) {
    return html_entity_decode($str,ENT_QUOTES);
  } // END: function m_scrubEntities($str)


} // END: class SymbolListArchiver extends Archiver


/**
 * @package YahooDataRetrievalSystem
 * @subpackage Archiver
 */
class NasdaqSymbolListArchiver extends SymbolListArchiver { 

  function NasdaqSymbolListArchiver($opts=null) { 
    parent::SymbolListArchiver($opts);
    return $this;
  } // END: function NasdaqSymbolListArchiver($opts=null)


  function acquire($exchange) {
    $exchange = trim(strtolower($exchange));
    if( !in_array($exchange,array('amex','nyse','nasdaq')) ) { 
      return false;
    }
    $url = 'http://www.nasdaq.com/screening/companies-by-name.aspx'.
           '?letter=0&exchange='.$exchange.'&render=download';
    $fpath = $this->getExchangeFilePath($exchange);
    $this->fetchWithCache($url,$fpath,300);
    return array($fpath);
  } // END: function acquire($exchange)


  function getExchangeFilePath($exchange,$datets=null) { 
    $datets = (is_int($datets)) ? $datets : time();
    return sprintf(
      "%s/%s/%s-%s.csv", 
      $this->m_rootDir, date("Y/Ymd",$datets), 
      strtolower($exchange), date("Ymd",$datets)
    );
  } // END: function getExchangeFilePath($exchange,$datets=time())

} // END: class NasdaqSymbolListArchiver extends SymbolListArchiver



/**
 * @package YahooDataRetrievalSystem
 * @subpackage Archiver
 */
class OtcSymbolListArchiver extends SymbolListArchiver { 

  function OtcSymbolListArchiver($opts=null) { 
    parent::SymbolListArchiver($opts);
    return $this;
  } // END: function OtcSymbolListArchiver($opts=null)


  function acquire() {
    $url = 'http://www.otcmarkets.com/reports/symbol_info.csv';
    $fpath = $this->getFilePath();
    $this->fetchWithCache($url,$fpath,300);
    return array($fpath);
  } // END: function acquire()

  function getSymbolFromRow(&$row,&$ihdr) {
    if( strpos($row[$ihdr['OTC Tier']],'Pink') !== false ) { 
      return $row[$ihdr['Symbol']].'.PK';
    }
    return $row[$ihdr['Symbol']].'.OB';
  } // END: function getSymbolFromRow(&$row,&$ihdr)


  function getFilePath($datets=null) { 
    $datets = (is_int($datets)) ? $datets : time();
    return sprintf(
      "%s/%s/otc-%s.csv", 
      $this->m_rootDir, date("Y/Ymd",$datets), date("Ymd",$datets)
    );
  } // END: function getFilePath($datets=null)

} // END: class OtcSymbolListArchiver extends SymbolListArchiver


/**
 * @package YahooDataRetrievalSystem
 * @subpackage Archiver
 */
class EtfSymbolListArchiver extends SymbolListArchiver { 
  function EtfSymbolListArchiver($opts=null) { 
    parent::SymbolListArchiver($opts);
    return $this;
  } // END: function EtfSymbolListArchiver($opts=null)


  function acquire() {
    $url = 'http://finance.yahoo.com/etf/browser/mkt';
    $fpath = $this->getFilePath();
    $dat = $this->fetchWithCache($url,$fpath.'.tmp',300);
    if( preg_match('/Showing\s+\d+\s*\-\s*\d+\s+of\s+(\d+)/is',$dat,$pts) ) { 
      $url = 'http://finance.yahoo.com/etf/browser/mkt'.
             '?c=0&k=5&f=0&o=d&cs=1&ce='.intVal($pts[1]);
      $this->fetchWithCache($url,$fpath,300);
      return array($fpath);
    }
    return false;
  } // END: function acquire()


  function parseFile($fpath) { 
    $html = file_get_contents($fpath);
    $html = HTMLTable::extractTableHTML($html,'Fund Name',-1);
    $html = str_replace('&nbsp;',' ',$html);
    list($htab) = HTMLTable::FromHTML($html);
    unset($html);
    $htab->applyCallback('strip_tags');
    $htab->applyCallback('trim');
    $hdr = current($htab->head);
    $nhdr = count($hdr);
    $mat = $htab->body;
    reset($mat); // NB: strange bug. HTMLTable->body currently set at 2nd row
    while( count(current($mat)) != $nhdr ) { 
      array_shift($mat);
    }
    array_unshift($mat,$hdr);
    return $mat;
  } // END: function parseFile($fpath)


  function getFilePath($datets=null) { 
    $datets = (is_int($datets)) ? $datets : time();
    return sprintf(
      "%s/%s/etf-%s.html", 
      $this->m_rootDir, date("Y/Ymd",$datets), date("Ymd",$datets)
    );
  } // END: function getFilePath($datets=null)

} // END: class EtfSymbolListArchiver extends SymbolListArchiver

// EOF -- SymbolListArchiver.php
?>
