<?php
/* Copyright (C) 2015   Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) 2016	Laurent Destailleur		<eldy@users.sourceforge.net>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

use Luracast\Restler\RestException;

require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

/**
 * API that allows to log in with an user account.
 */
class Login
{

	function __construct() {
		global $db;
		$this->db = $db;
	}

	/**
	 * Login
	 *
	 * Request the API token for a couple username / password. 
	 * Using method POST is recommanded for security reasons (method GET is often logged by default by web servers with parameters so with login and pass into server log file).
	 * Both method are provided for developer conveniance. Best is to not use at all the login API method and enter directly the "api_key" into field at the top right of page (Note: "api_key" can be found/set on the user page). 
	 * 
	 * @param   string  $login			User login
	 * @param   string  $password		User password
	 * @param   int     $entity			Entity (when multicompany module is used). Empty means 1=first company.
	 * @param   int     $reset          Reset token (0=get current token, 1=ask a new token and canceled old token. This means access using current existing API token of user will fails: new token will be required for new access)
     * @return  array                   Response status and user token
     *
	 * @throws RestException
	 * 
	 * @url GET /
	 * @url POST /
	 */
	public function index($login, $password, $entity=0, $reset=0) {

	    global $conf, $dolibarr_main_authentication, $dolibarr_auto_user;
	    
		// Authentication mode
		if (empty($dolibarr_main_authentication))
			$dolibarr_main_authentication = 'http,dolibarr';
		// Authentication mode: forceuser
		if ($dolibarr_main_authentication == 'forceuser' && empty($dolibarr_auto_user))
			$dolibarr_auto_user = 'auto';
		// Set authmode
		$authmode = explode(',', $dolibarr_main_authentication);

		include_once DOL_DOCUMENT_ROOT . '/core/lib/security2.lib.php';
		$login = checkLoginPassEntity($login, $password, $entity, $authmode);
		if (empty($login))
		{
			throw new RestException(403, 'Access denied');
		}

		$token = 'failedtogenerateorgettoken';
		
		$tmpuser=new User($this->db);
		$tmpuser->fetch(0, $login);
		
		// Renew the hash
		if (empty($tmpuser->api_key) || $reset)
		{
    		// Generate token for user
    		$token = dol_hash($login.uniqid().$conf->global->MAIN_API_KEY,1);
    
    		// We store API token into database
    		$sql = "UPDATE ".MAIN_DB_PREFIX."user";
    		$sql.= " SET api_key = '".$this->db->escape($token)."'";
    		$sql.= " WHERE login = '".$this->db->escape($login)."'";
    
    		dol_syslog(get_class($this)."::login", LOG_DEBUG);	// No log
    		$result = $this->db->query($sql);
    		if (!$result)
    		{
    			throw new RestException(500, 'Error when updating api_key for user :'.$this->db->lasterror());
    		}
		}
		else
		{
            $token = $tmpuser->api_key;
		}
		
		//return token
		return array(
			'success' => array(
				'code' => 200,
				'token' => $token,
				'message' => 'Welcome ' . $login.($reset?' - Token is new':' - This is your token (generated by a previous call). You can use it to make any REST API call, or enter it into the DOLAPIKEY field to use the Dolibarr API explorer.')
			)
		);
	}
}
