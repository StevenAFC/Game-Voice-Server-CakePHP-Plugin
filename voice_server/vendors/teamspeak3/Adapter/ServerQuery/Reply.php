<?php

/**
 * TeamSpeak 3 PHP Framework
 *
 * $Id: Reply.php 2010-01-18 21:54:35 sven $
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package   TeamSpeak3
 * @version   1.0.22-beta
 * @author    Sven 'ScP' Paulsen
 * @copyright Copyright (c) 2010 by Planet TeamSpeak. All rights reserved.
 */

/**
 * Provides methods to analyze and format a ServerQuery reply.
 * 
 * @package  TeamSpeak3_Adapter_ServerQuery_Reply
 * @category TeamSpeak3_Adapter_ServerQuery
 */
class TeamSpeak3_Adapter_ServerQuery_Reply
{
  /**
   * Stores the command used to get this reply.
   *
   * @var TeamSpeak3_Helper_String
   */
  private $cmd = null;

  /**
   * Stores the servers reply (if available).
   *
   * @var TeamSpeak3_Helper_String
   */
  private $rpl = null;
  
  /**
   * Stores an assoc array containing the error info for this reply.
   *
   * @var array
   */
  private $err = array();
    
  /**
   * Creates a new TeamSpeak3_Adapter_ServerQuery_Reply object.
   *
   * @param  array  $rpl
   * @param  string $cmd
   * @return TeamSpeak3_Adapter_ServerQuery_Reply
   */
  public function __construct(array $rpl, $cmd = null)
  {
    $this->cmd = new TeamSpeak3_Helper_String($cmd);
    
    $this->fetchError(array_pop($rpl));
    $this->fetchReply($rpl);
  }
  
  /**
   * Returns the reply as an TeamSpeak3_Helper_String object.
   *
   * @return TeamSpeak3_Helper_String
   */
  public function toString()
  {
    return (!func_num_args()) ? $this->rpl->unescape() : $this->rpl;
  }
  
  /**
   * Returns the reply as a standard PHP array where each element represents one item.
   *
   * @return array
   */
  public function toLines()
  {
    if(!count($this->rpl)) return array();
    
    $list = $this->toString(0)->split(TeamSpeak3::SEPERATOR_LIST);
    
    if(!func_num_args())
    {
      for($i = 0; $i < count($list); $i++) $list[$i]->unescape();
    }

    return $list;
  }
  
  /**
   * Returns the reply as a standard PHP array where each element represents one item in table format.
   *
   * @return array
   */
  public function toTable()
  {
    $table = array();

    foreach($this->toLines(0) as $cells)
    {
      $pairs = $cells->split(TeamSpeak3::SEPERATOR_CELL);
      
      if(!func_num_args())
      {
        for($i = 0; $i < count($pairs); $i++) $pairs[$i]->unescape();
      }
      
      $table[] = $pairs;
    }

    return $table;
  }
  
  /**
   * Returns a multi-dimensional array containing the reply splitted in multiple rows and columns.
   *
   * @return array
   */
  public function toArray()
  {
    $array = array();
    $table = $this->toTable(1);

    for($i = 0; $i < count($table); $i++)
    {
      foreach($table[$i] as $pair)
      {
        if(!$pair->contains(TeamSpeak3::SEPERATOR_PAIR))
        {
          $array[$i][$pair->toString()] = null;
        }
        else
        {
          list($ident, $value) = $pair->split(TeamSpeak3::SEPERATOR_PAIR, 2);
        
          $array[$i][$ident->toString()] = $value->is_numeric() ? $value->toInt() : (!func_num_args() ? $value->unescape() : $value);
        }
      }
    }

    return $array;
  }
  
  /**
   * Returns a multi-dimensional assoc array containing the reply splitted in multiple rows and columns.
   * The identifier specified by key will be used while indexing the array.
   *
   * @param  $key
   * @return array
   */
  public function toAssocArray($ident)
  {
    $nodes = (func_num_args() > 1) ? $this->toArray(1) : $this->toArray();
    $array = array();
    
    foreach($nodes as $key => $val)
    {
      if(array_key_exists($ident, $val))
      {
        $array[(is_int($val[$ident])) ? $val[$ident] : $val[$ident]->toString()] = $val;
      }
      else
      {
        throw new TeamSpeak3_Adapter_ServerQuery_Exception("invalid parameter", 0x602);
      }
    }

    return $array;
  }
  
  /**
   * Returns an array containing the reply splitted in multiple rows and columns.
   *
   * @return array
   */
  public function toList()
  {
    $array = func_num_args() ? $this->toArray(1) : $this->toArray();
    
    if(count($array) == 1)
    {
      return array_shift($array);
    }
  }
  
  /**
   * Returns an array containing stdClass objects.
   *
   * @return ArrayObject
   */
  public function toObjectArray()
  {
    $array = (func_num_args() > 1) ? $this->toArray(1) : $this->toArray();
    
    for($i = 0; $i < count($array); $i++)
    {
      $array[$i] = (object) $array[$i];
    }
    
    return $array;
  }
  
  /**
   * Returns the value for a specified error property.
   *
   * @param  string $ident
   * @param  mixed  $default
   * @return mixed
   */
  public function getErrorProperty($ident, $default = null)
  {
    return (array_key_exists($ident, $this->err)) ? $this->err[$ident] : $default;
  }
  
  /**
   * Parses a ServerQuery error and returns a TeamSpeak3_Adapter_ServerQuery_Exception object.
   *
   * @param  string $err
   * @throws TeamSpeak3_Adapter_ServerQuery_Exception
   * @return void
   */
  private function fetchError($err)
  {
    $cells = $err->section(TeamSpeak3::SEPERATOR_CELL, 1, 3);
    
    foreach($cells->split(TeamSpeak3::SEPERATOR_CELL) as $pair)
    {
      list($ident, $value) = $pair->split(TeamSpeak3::SEPERATOR_PAIR);
      
      $this->err[$ident->toString()] = $value->is_numeric() ? $value->toInt() : $value->unescape();
    }
    
    if($this->getErrorProperty("id", 0x00) != 0x00)
    {
      if($permid = $this->getErrorProperty("failed_permid"))
      {
        $suffix = " (failed on " . $this->cmd->section(TeamSpeak3::SEPERATOR_CELL) . " " . $permid . "/0x" . strtoupper(dechex($permid)) . ")";
      }
      elseif($details = $this->getErrorProperty("extra_msg"))
      {
        $suffix = " (" . $details . ")";
      }
      else
      {
        $suffix = "";
      }
      
      throw new TeamSpeak3_Adapter_ServerQuery_Exception($this->getErrorProperty("msg") . $suffix, $this->getErrorProperty("id"));
    }
  }
  
  /**
   * Parses a ServerQuery reply and returns a TeamSpeak3_Helper_String object.
   *
   * @param  string $rpl
   * @throws TeamSpeak3_Adapter_ServerQuery_Exception
   * @return void
   */
  private function fetchReply($rpl)
  {
    foreach($rpl as $key => $val)
    {
      if($val->startsWith(TeamSpeak3::EVENT)) unset($rpl[$key]);
    }
    
    $this->rpl = new TeamSpeak3_Helper_String(implode(TeamSpeak3::SEPERATOR_LIST, $rpl));
  }
}
