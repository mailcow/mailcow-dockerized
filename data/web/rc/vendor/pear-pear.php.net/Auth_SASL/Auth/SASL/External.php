<?php
// +-----------------------------------------------------------------------+ 
// | Copyright (c) 2008 Christoph Schulz                                   | 
// | All rights reserved.                                                  | 
// |                                                                       | 
// | Redistribution and use in source and binary forms, with or without    | 
// | modification, are permitted provided that the following conditions    | 
// | are met:                                                              | 
// |                                                                       | 
// | o Redistributions of source code must retain the above copyright      | 
// |   notice, this list of conditions and the following disclaimer.       | 
// | o Redistributions in binary form must reproduce the above copyright   | 
// |   notice, this list of conditions and the following disclaimer in the | 
// |   documentation and/or other materials provided with the distribution.| 
// | o The names of the authors may not be used to endorse or promote      | 
// |   products derived from this software without specific prior written  | 
// |   permission.                                                         | 
// |                                                                       | 
// | THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS   | 
// | "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT     | 
// | LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR | 
// | A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT  | 
// | OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, | 
// | SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT      | 
// | LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, | 
// | DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY | 
// | THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT   | 
// | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE | 
// | OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.  | 
// |                                                                       | 
// +-----------------------------------------------------------------------+ 
// | Author: Christoph Schulz <develop@kristov.de>                         | 
// +-----------------------------------------------------------------------+ 
// 
// $Id$

/**
* Implmentation of EXTERNAL SASL mechanism
*
* @author  Christoph Schulz <develop@kristov.de>
* @access  public
* @version 1.0.3
* @package Auth_SASL
*/

require_once('Auth/SASL/Common.php');

class Auth_SASL_External extends Auth_SASL_Common
{
    /**
    * Returns EXTERNAL response
    *
    * @param  string $authcid   Authentication id (username)
    * @param  string $pass      Password
    * @param  string $authzid   Autorization id
    * @return string            EXTERNAL Response
    */
    function getResponse($authcid, $pass, $authzid = '')
    {
        return $authzid;
    }
}
?>
