<?php
/**
 * @file Archiver.php
 * @date 2012-08-15 09:38 PDT
 *
 * @modifications
 * 1.0.0 - 2012-03-15 - Created
 * 1.0.1 - 2012-08-15 - Change: m_rootDir path
 */


/**
 * @package YahooDataRetrievalSystem
 * @subpackage Archiver
 */
class Archiver {
  var $m_rootDir = './data/Archiver';

  var $_curlopts = array(
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_FOLLOWLOCATION => true
  );
  var $_lastFetch = 0;


  function Archiver($opts=null) { 
    if( is_array($opts) ) { 
      foreach( array_keys($opts) as $k ) { 
        $this->setOpt($k,$opts[$k]);
      }
    }
    return $this;
  } // END: constructor Archiver($opts=null)


  function acquire() {
    error_log("Abstract method.");
    return false;
  } // END: function acquire()


  function setOpt($k,$v=null) { 
    switch($k) { 
      case 'DATA_DIR': 
        $this->m_rootDir = rtrim($v,'/');
        break;
      default:
        error_log("Unrecognized option: `$k`");
        return false;
    }
    return true;
  } // END: function setOpt($k,$v=null)


  function curl_setopt($k,$v) {
    if( $v === null ) { 
      unset($this->_curlopts[$k]);
    } else { 
      $this->_curlopts[$k] = $v;
    }
    return true;
  } // END: function curl_setopt($k,$v)


  function lastRun($ts=null) { 
    if( $ts===null ) {
      return $this->m_getState('lastRun',0);
    }
    return $this->m_setState('lastRun',$ts);
  } // END: function lastRun($ts=null)


  function m_setState($k,$v=true) {
    $fpath = $this->m_getStateFile();
    $obj = array();
    if( file_exists($fpath) ) {
      $obj = unserialize(file_get_contents($fpath));
    }
    if( $v === null ) {
      if( isset($obj[$k]) ) {
        unset($obj[$k]);
      }
    } else {
      $obj[$k] = $v;
    }
    return $this->file_put_contents($fpath,serialize($obj));
  } // END: function m_setState($k,$v=true)


  function m_getState($k=null,$dft=null) {
    $fpath = $this->m_getStateFile();
    if( !file_exists($fpath) ) {
      return $dft;
    }
    $obj = unserialize(file_get_contents($fpath));
    return ($k===null) ? $obj : ((isset($obj[$k])) ? $obj[$k] : $dft);
  } // END: function m_getState($k=null,$dft=null)


  function m_getStateFile() { 
    return $this->_mreplaceExtension(__FILE__,'status');
  } // END: function m_getStateFile()
  
  
  function m_replaceExtension($fpath,$newext) { 
    $ext = pathinfo($fpath,PATHINFO_EXTENSION);
    if( basename($fpath)===$ext ) {
      return "$fpath.$newext";
    }
    return sprintf(
      "%s%s%s.%s",
      dirname($fpath),
      DIRECTORY_SEPARATOR,
      basename($fpath,".$ext"),
      $newext
    );
  } // END: function m_replaceExtension($fpath,$newext)


  function fetch($url,$fpath,$delay=0) {
    $delay = $this->_lastFetch + $delay - time();
    if( $delay > 0 ) { 
      sleep($delay);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    foreach( array_keys($this->_curlopts) as $k ) { 
      curl_setopt($ch, $k, $this->_curlopts[$k]);
    }

    $contents = curl_exec($ch);
    $http_code = curl_getinfo($ch,CURLINFO_HTTP_CODE);
    if(curl_errno($ch) || $http_code >= 400 ) {
      if( strlen($contents) < 512 ) {
        curl_close($ch);
        return false;
      }
    }
    curl_close($ch);

    if( $fpath!==false ) { 
      if( !$this->m_mkdir(dirname($fpath)) ) { 
        return false;
      }
      $this->file_put_contents($fpath,$contents);
    }
    $this->_lastFetch = time();
    return $contents;
  } // END: function fetch($url,$fpath,$delay=0)


  function fetchWithCache($url,$fpath,$age,$delay=0) { 
    if( file_exists($fpath) && filemtime($fpath)+$age > time() ) { 
      return file_get_contents($fpath);
    }
    return $this->fetch($url,$fpath,$delay);
  } // END: function fetchWithCache($url,$fpath,$age,$delay=0)


  function file_put_contents($fpath,$txt) { 
    $this->m_mkdir(dirname($fpath));
    $fp = fopen($fpath,'w+');
    return !( !$fp || !fwrite($fp,$txt) || !fclose($fp) );
  } // END: function file_put_contents($fpath,$txt)


  function m_mkdir($dpath) {
    if( !empty($dpath) && !is_dir($dpath) ) { 
      $this->m_mkdir(dirname($dpath));
    }
    return (empty($dpath) || is_dir($dpath)) ? true : mkdir($dpath);
  } // END: function m_mkdir($dpath)

} // END: class Archiver($opts=null)




/*

EventsArchiver extends Archiver
* getEventsBySymbol(symbol)
* getEventsByDate(date)
* getEventsByDateRange(date0,date1)


DividendsArchiver extends EventsArchiver
++ acquire()
+ getEventsBySymbol
+ getEventsByDate
+ getEventsByDateRange


SplitsArchiver extends EventsArchiver
++ acquire()
+ getEventsBySymbol
+ getEventsByDate
+ getEventsByDateRange


SymbolChangesArchiver extends EventsArchiver
++ acquire()
+ getEventsBySymbol
+ getEventsByDate
+ getEventsByDateRange

*/
// EOF -- Archiver.php
?>
