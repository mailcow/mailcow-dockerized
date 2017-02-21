/*
 +-----------------------------------------------------------------------+
 | Roundcube installer cleint function                                   |
 |                                                                       |
 | This file is part of the Roundcube web development suite              |
 | Copyright (C) 2009-2012, The Roundcube Dev Team                       |
 |                                                                       |
 | Licensed under the GNU General Public License version 3 or            |
 | any later version with exceptions for skins & plugins.                |
 | See the README file for a full license statement.                     |
 |                                                                       |
 +-----------------------------------------------------------------------+
 | Author: Thomas Bruederli <roundcube@gmail.com>                        |
 +-----------------------------------------------------------------------+
*/

function toggleblock(id, link)
{
  var block = document.getElementById(id);
  
  return false;
}


function addhostfield()
{
  var container = document.getElementById('defaulthostlist');
  var row = document.createElement('div');
  var input = document.createElement('input');
  var link = document.createElement('a');
  
  input.name = '_default_host[]';
  input.size = '30';
  link.href = '#';
  link.onclick = function() { removehostfield(this.parentNode); return false };
  link.className = 'removelink';
  link.innerHTML = 'remove';
  
  row.appendChild(input);
  row.appendChild(link);
  container.appendChild(row);
}


function removehostfield(row)
{
  var container = document.getElementById('defaulthostlist');
  container.removeChild(row);
}


