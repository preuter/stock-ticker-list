<?php
 /**
  * Parse HTML extracting information. An SGMLTable would be different.
  *
  * @file HTMLTable.php
  * @date 12/7/2015 
  * @author mukmuk623
  * @version 1.0.7


class HTMLTable { 
  var $title; // Store the caption
  var $head;  // Header rows
  var $body;  // Body rows
  var $foot;  // Footer rows

  function HTMLTable() { 
    $this->initialize();
    return $this;
  } // END: function HTMLTable()


  function FromHTML($dat) { 
    return HTMLTable::FromText($dat);
  } // END: function FromHTML($dat)


  function FromText($dat) { 
    $tp = new HTMLTable();
    if( !$tp->parse($dat) ) { 
      error_log($tp->error);
      return false;
    }
    return $tp->tables;
  } // END: function FromText($dat)


  function FromFile($fpath) { 
    if( !is_readable($fpath) ) { 
      return false;
    }
    return HTMLTable::FromText(file_get_contents($fpath));
  } // END: function FromFile($fpath)


  function initialize() { 
    $this->title = '';
    $this->head = array();
    $this->body = array();
    $this->foot = array();
    return true;
  } // END: function initialize()



  function applyCallback($cb) { 
    foreach($this->head as $r=>$tr) { 
      foreach($tr as $c=>$td) { 
        if( is_a($td,'HTMLTable') ) { 
          $this->head[$r][$c]->applyCallback($cb);
        } else {
          $this->head[$r][$c] = call_user_func($cb,$td);
        }
      }
    }
    foreach($this->body as $r=>$tr) { 
      foreach($tr as $c=>$td) { 
        if( is_a($td,'HTMLTable') ) { 
          $this->body[$r][$c]->applyCallback($cb);
          $td->applyCallback($cb);
        } else {
          $this->body[$r][$c] = call_user_func($cb,$td);
        }
      }
    }
    foreach($this->foot as $r=>$tr) { 
      foreach($tr as $c=>$td) { 
        if( is_a($td,'HTMLTable') ) { 
          $this->foot[$r][$c]->applyCallback($cb);
        } else {
          $this->foot[$r][$c] = call_user_func($cb,$td);
        }
      }
    }
    return true;
  } // END: function applyCallback(&$cb)


  function matching($preg,$limit=1) { 
    $tabs = array($this);
    return $this->m_matching($tabs,$preg,$limit);
  } // END: function matching($preg,$limit=1)


  function containing($txt,$limit=1) { 
    $tabs = array($this);
    $txt = strtolower($txt);
    return $this->m_containing($tabs,$txt,$limit);
  } // END: function containing($txt)


  function largest() { 
    return $this->m_largest($this,$this,0);
  } // END: function largest()


  function deepest() { 
    return $this->m_deepest($this,0,$this,0);
  } // END: function deepest()



  /**
   * Extract table market from an HTML document starting from $target.
   * Search for $target and extract the table $numShifts after the text.
   * If $numShifts is negative, counts backwards.
   *
   * example: Extract table containing anchor: ($src,$target,-1)
   * example: Extract very next table after anchor: ($src,$target,0)
   * example: Skip next table, extract second: ($src,$target,1)
   *
   * @param string &$src Given HTML to search.
   * @param string $target What to look for in html (case insensitive)
   * @param int $numShifts Number of tables to count.
   * @return string HTML containing the requested table.
   */
  function extractTableHTML(&$src,$target,$numShifts=0) { 
    $start = stripos($src,$target);
    if( $start===false ) { 
      return false;
    }

    if( $numShifts >= 0 ) { 
    // Find tables after anchor

      do{ 
        $start = stripos($src,'<table',$start+1);
        $numShifts -= 1;
      } while( $numShifts >= 0 && $start!==false);

      if( $start!==false ) { 
        $tmp = substr($src,$start);
        $tabs = HTMLTable::extractTablesHTML($tmp);
        return (count($tabs)>0) ? $tabs[0] : false;
      }

    } else { 
    // find tables before anchor

      $stack = array();
      $ix = -1;
      while( $ix!==false && $ix <= $start ) { 
        $ix = stripos($src,'<table',$ix+1);
        $stack[] = $ix;
      }
      array_pop($stack);

      $top = false;
      while(!empty($stack) && $numShifts < 0) { 
        $top = array_pop($stack);
        $numShifts += 1;
      }

      if( $top!==false && $numShifts>=0 ) { 
        $tmp = substr($src,$top);
        $tabs = HTMLTable::extractTablesHTML($tmp);
        return (count($tabs)>0) ? $tabs[0] : false;
      }
    }

    return false;
  } // END: function extractTableHTML(&$src,$target,$binside=true)


  /**
   * Extract table markup from an HTML document.
   *
   * @static
   * @param string &$txt Text from which to extract table HTML
   * @return array of html strings containing table markup.
   */
  function extractTablesHTML(&$txt) { 
    $curr = -1;
    $next = 0;
    $len = strlen($txt);
    $tabs = array();

    while( true ) {
      $open = stripos($txt,'<table',$next);
      $close = stripos($txt,'</table>',$next);

      // terminal condition:
      if( $open===false && $close===false ) {
        // No tables present in context.
        return $tabs;
      }

      // terminal condition:
      else if( $open!==false && $close===false ) {
        // dangling table
        return $tabs;
      }

      // terminal condition:
      else if( $close!==false && $open===false ) {
        // Last table
        if( $curr >= 0 ) {
          $tabs[] = substr($txt,$curr,$close-$curr+8);
        }
        // if not opened, table was nested.
        return $tabs;
      }

      // else: common condition
      if( $open < $close ) {
        $curr = $open;
      } else { // $open >= $close
        if( $curr >= 0 ) {
          $tabs[] = substr($txt,$curr,$close-$curr+8);
        }
        $curr = -1;
      }

      $next = min($open,$close)+1;
    } // end: while( true )

    return $tabs;
  } // END: function extractTablesHTML(&$txt)


  function m_matching(&$tabs,$preg,$limit) { 
    $result = array();
    while( !empty($tabs) ) { 
      $table = array_shift($tabs);
      if( $this->m_matching_scan($tabs,$preg,$table->head) ) { 
        $result[] = $table;
      }
      else if( $this->m_matching_scan($tabs,$preg,$table->body) ) { 
        $result[] = $table;
      }
      else if( $this->m_matching_scan($tabs,$preg,$table->foot) ) { 
        $result[] = $table;
      }
      if( $limit > 0 && count($result)==$limit ) { 
        return $result;
      }
    }
    return $result;
  } // END: function m_matching(&$tabs,$preg,$limit)


  function m_matching_scan(&$tabs,$preg,&$mat) { 
    if( empty($mat) ) { 
      return false;
    }
    foreach($mat as $tr) { 
      foreach($tr as $td) { 
        if( is_a($td,'HTMLTable') ) { 
          $tabs[] = $td;
        } else if( preg_match($preg,$td) ) { 
          return true;
        }
      }
    }
    return false;
  } // END: function m_matching_scan(&$tabs,$preg,&$mat)


  function m_containing(&$tabs,$txt,$limit) { 
    $result = array();
    while( !empty($tabs) ) { 
      $table = array_shift($tabs);
      if( $this->m_containing_scan($tabs,$txt,$table->head) ) { 
        $result[] = $table;
      }
      else if( $this->m_containing_scan($tabs,$txt,$table->body) ) { 
        $result[] = $table;
      }
      else if( $this->m_containing_scan($tabs,$txt,$table->foot) ) { 
        $result[] = $table;
      }
      if( $limit > 0 && count($result)==$limit ) { 
        return $result;
      }
    }
    return $result;
  } // END: function m_containing(&$tabs,$txt,$limit)


  function m_containing_scan(&$tabs,$txt,&$mat) { 
    if( empty($mat) ) { 
      return false;
    }
    foreach($mat as $tr) { 
      foreach($tr as $td) { 
        if( is_a($td,'HTMLTable') ) { 
          $tabs[] = $td;
        } else if( strpos(strtolower($td),$txt) !== false ) { 
          return true;
        }
      }
    }
    return false;
  } // END: function m_containing_scan(&$tabs,$txt,&$mat)



  function m_largest(&$table,&$best,$maxcells) { 
    $cells = 0;
    foreach($table->body as $tr) { 
      foreach($tr as $td) { 
        $cells += 1;
      }
    }
    if( $cells > $maxcells ) { 
      $best = $table;
      $maxcells = $cells;
    }
    foreach($table->body as $tr) { 
      foreach($tr as $td) { 
        if( is_a($td,'HTMLTable') ) { 
          $best = $this->m_largest($td,$best,$maxcells);
        }
      }
    }
    return $best;
  } // END: function m_largest(&$table,&$best,$maxcells)


  function m_deepest(&$table,$level,&$best,$maxdepth) { 
    if( $level > $maxdepth ) { 
      $best = $table;
      $maxdepth = $level;
    }
    foreach($table->body as $tr) { 
      foreach($tr as $td) { 
        if( is_a($td,'HTMLTable') ) { 
          $best = $this->m_deepest($td,$level+1,$best,$maxdepth);
        }
      }
    }
    return $best;
  } // END: function m_deepest(&$table,$level,&$best,$depth)


  function parse($dat) { 
    $tags = $this->m_tags($dat);
    $found = array(); // array of tables
    $stack = array(); // stack of tags
    $stack_ix = 0;
    $allowed = array('table','tr','td','th','thead','tbody','tfoot','caption');

    foreach($tags as $tag) { 
      if( !in_array($tag['name'],$allowed) ) { 
        continue;
      }

      if( $tag['type'] === 'open' ) { 

        $tag['$'] = array();
        $tag['_'] = '';
        if( !empty($stack) ) {
          $stack_ix += 1;
        }
        $stack[$stack_ix] = $tag;

      } else if( $tag['type'] === 'close' ) { 

        if( $stack[$stack_ix]===null
        ||  $stack[$stack_ix]['name']!==$tag['name']
        ||  $stack[$stack_ix]['type']!=='open' ) { 
          $this->error = "parse error: close without matching open.";
          // silently ignore
          // return false;
          continue;
        }

        $top = array_pop($stack);
        $top['type'] = 'node';
        if( !empty($stack) ) { 
          $stack_ix -= 1;
        }

        if( $tag['name']=='td' || $tag['name']=='th' ) { 
          $start = $top['offset'] + $top['length'];
          $top['_'] = substr($dat,$start,$tag['offset']-$start);

        } else if( $tag['name']=='caption' ) { 
          $start = $top['offset'] + $top['length'];
          $top['_'] = substr($dat,$start,$tag['offset']-$start);
        }

        if( empty($stack) ) { 
          $found[] = $top;
        } else { 
          $stack[$stack_ix]['$'][] = $top;
        }

      } else { // type is complete

        $tag['type'] = 'node';
        $tag['$'] = array();
        $tag['_'] = '';

        if( empty($stack) ) { 
          $found[] = $tag;
        } else { 
          $stack[$stack_ix]['$'][] = $tag;
        }

      }

    } // end -- foreach($tags as $tag)

    if( !empty($stack) ) { 
      $this->error = "parse error: unbalanced table structure.";
      return false;
    }

    $results = array();
    foreach($found as $item) { 
      if( $item['name'] === 'table' ) { 
        // Special case: root table must exist.
        $table = new HTMLTable();
        foreach($item['$'] as $child) { 
          if( !$this->buildFromHierarchy($table,$child) ) { 
            return false;
          }
        }
        $results[] = $table;
      }
    }
    $this->tables = $results;
    return true;
  } // END: function parse($dat);


  function buildFromHierarchy(&$table,&$node) { 
    if( $node['name'] == 'table' ) { 
    // Build a table that will be inserted into a cell
      $obj = new HTMLTable();
      foreach($node['$'] as $child) { 
        if( !$this->buildFromHierarchy($obj,$child) ) { 
          return false;
        }
      }
      return $obj;
    }

    else if( $node['name'] == 'caption' ) { 
    // Assign the current table's title
      $table->title = $node['_'];
      return true;
    }


    // Process child nodes
    $kids = array();
    $nths = 0;
    foreach($node['$'] as $child) { 
      if( $child['name']=='th' ) { 
        if( isset($child['attr']) && isset($child['attr']['colspan']) ) { 
          $nths += intVal($child['attr']['colspan']);
        } else { 
          $nths += 1;
        }
      }
      $kid = $this->buildFromHierarchy($table,$child);
      if( $kid===false ) { 
        return false;
      }
      if( $kid!==true ) { 
        $kids[] = $kid;
      }
    }

    if( $node['name'] == 'td' || $node['name'] == 'th' ) {
    // Return the cell contents
      if( count($kids) > 1 ) { 
        $this->error = "build error: td expects 1 child";
        return false;
      }
      if( count($kids)<1 ) { 
        // A `td` cannot have a `td`.
        // If the cell has a child, then it does not contain text.
        // This is not true of HTML, but in our table parser it is true.
        $kids[] = $node['_'];
      }
      // Expand colspans by appending empty cells.
      if( isset($node['attr']) && isset($node['attr']['colspan']) ) { 
        $nc = intVal($node['attr']['colspan']) -1;
        while( $nc > 0 ) { 
          $kids[] = '';
          $nc -= 1;
        }
      }
      return $kids;
    }

    else if( $node['name'] == 'tr' ) { 
    // Append a row of cells
    // NB: we return an array of cells above if colspan is set.
      $kids = $this->m_flatten($kids);
      if( $nths == count($kids) ) { 
        $table->head[] = $kids;
      } else { 
        $table->body[] = $kids;
      }
      return true;
    }

    else if( $node['name'] == 'thead' ) { 
    // Remove from table the last elements added to it.
    // Assign as header
      if( !empty($kids) ) { 
        $table->head = array_merge(
          $table->head,
          array_splice($table->body,0-count($kids))
        );
      }
      return true;
    }
    
    else if( $node['name'] == 'tfoot' ) { 
    // Remove from table the last elements added to it.
    // Assign as footer
      $table->foot = array_splice($table->body,0-count($kids));
      return true;
    }

    else if( $node['name'] == 'tbody' ) { 
    // All the work has been done
      return true;
    }

    $this->error = "build error: unrecognized name `".$node['name']."`";
    return false;
  } // END: function buildFromHierarchy(&$node)


  function getTable($withHeader=true,$withFooter=true) { 
    $mat = ($withHeader) ? $this->head : array();
    $mat = array_merge($mat,$this->body);
    if( $withFooter ) { 
      $mat = array_merge($mat,$this->foot);
    }
    return $mat;
  } // END: function getTable($withHeader=true)


  function getHeader() { 
    return $this->head;
  }

  function getBody() { 
    return $this->body;
  }

  function getFooter() { 
    return $this->foot;
  }


  function m_flatten($a) { 
    if( !is_array($a) ) { 
      return array($a);
    }
    $b = array();
    foreach($a as $k=>$v) { 
      if( is_array($v) ) { 
        $b = array_merge($b,$this->m_flatten($v));
      } else { 
        $b[] = $v;
      }
    }
    return $b;
  } // END: function m_flatten(&$a)


  function m_tags(&$text) { 
    $pat = '/<(\/?)([^>\ ]+)([^>]*?)(\/?)>/';
    if( !preg_match_all($pat,$text,$sets,PREG_OFFSET_CAPTURE|PREG_SET_ORDER) ) {
      return false;
    }
    $results = array();
    foreach($sets as $set) {
      $offset = $set[0][1];
      $length = strlen($set[0][0]);
      if( strlen($set[1][0]) == 1 ) {
      // Check if closing slash exists at start of tag
        $tagType = 'close';
      } else if( strlen($set[4][0]) == 1 ) {
      // Check if closing slash exists at end of tag
        $tagType = 'complete';
      } else {
      // Otherwise it's an open tag
        $tagType = 'open';
      }
      $tagName = strtolower(trim($set[2][0]));
      $attrs = $this->m_attrs($set[3][0]);

      $results[] = array(
        'name' => $tagName,
        'type' => $tagType,
        'attr' => $attrs,
        'offset' => $offset,
        'length' => $length
      );
    }
    return $results;
  } // END: function m_tags(&$text)


  function m_attrs(&$text) { 
    $pat = '/\b([^=\s]+)=(?:(?:([\'"])(.*?)\2)|(?:([^\s]*)))/s';
    if( !preg_match_all($pat,$text,$sets,PREG_SET_ORDER) ) {
      return null;
    }
    // Parse attributes
    $attrs = array();
    foreach( $sets as $attr ) {
      if( count($attr) == 4 ) {
        $key = $attr[1];
        $val = html_entity_decode($attr[3]);
      } else if( count($attr) == 5 ) {
        $key = $attr[1];
        $val = html_entity_decode($attr[4]);
      } else {
        // error_log("Attribute improperly formatted.");
        continue;
      }
      $attrs[$key] = $val;
    }
    return $attrs;
  } // END: function m_attrs(&$text)

} // END: class HTMLTable


// $arr = HTMLTable::FromFile('tests/test7.txt');
// foreach($arr as $obj) { 
//   print_r($obj->containing('dog'));
// }


// EOF -- HTMLTable.php
?>
