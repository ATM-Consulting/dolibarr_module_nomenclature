<?php
/* Copyright (C) 2003      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@capnetworks.com>
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

/**
 * 	\defgroup   nomenclature     Module nomenclature
 *  \brief      Example of a module descriptor.
 *				Such a file must be copied into htdocs/nomenclature/core/modules directory.
 *  \file       htdocs/nomenclature/core/modules/modnomenclature.class.php
 *  \ingroup    nomenclature
 *  \brief      Description and activation file for module nomenclature
 */
include_once DOL_DOCUMENT_ROOT .'/core/modules/DolibarrModules.class.php';


/**
 *  Description and activation class for module nomenclature
 */
class modnomenclature extends DolibarrModules
{
	/**
	 *   Constructor. Define names, constants, directories, boxes, permissions
	 *
	 *   @param      DoliDB		$db      Database handler
	 */
	function __construct($db)
	{
        global $langs,$conf;

        $this->db = $db;

		$this->editor_name = 'ATM Consulting';
		$this->editor_url = 'https://www.atm-consulting.fr';
		// Id for module (must be unique).
		// Use here a free id (See in Home -> System information -> Dolibarr for list of used modules id).
		$this->numero = 104580; // 104000 to 104999 for ATM CONSULTING
		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'nomenclature';

		// Family can be 'crm','financial','hr','projects','products','ecm','technic','other'
		// It is used to group modules in module setup page
		$this->family = "ATM Consulting - GPAO";
		// Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
		$this->name = preg_replace('/^mod/i','',get_class($this));
		// Module description, used if translation string 'ModuleXXXDesc' not found (where XXX is value of numeric property 'numero' of module)
		$this->description = "Description of module nomenclature";
		// Possible values for version are: 'development', 'experimental', 'dolibarr' or version

		$this->version = '4.13.1';

		// Url to the file with your last numberversion of this module
		require_once __DIR__ . '/../../class/techatm.class.php';
		$this->url_last_version = \nomenclature\TechATM::getLastModuleVersionUrl($this);


		// Key used in llx_const table to save module status enabled/disabled (where MYMODULE is value of property name of module in uppercase)
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		// Where to store the module in setup page (0=common,1=interface,2=others,3=very specific)
		$this->special = 0;
		// Name of image file used for this module.
		// If file is in theme/yourtheme/img directory under name object_pictovalue.png, use this->picto='pictovalue'
		// If file is in module/img directory under name object_pictovalue.png, use this->picto='pictovalue@module'
		$this->picto='nomenclature.svg@nomenclature';

		$this->module_parts = array(
			'hooks'=>array(
			    'propalcard'
                , 'ordercard'
                , 'stockproductcard'
                , 'projectOverview'
				, 'projectdao'
                , 'listof'
                , 'ofcard'
            )
            ,'triggers'=>1
            ,'models' => 1
            ,'css'=>array('/nomenclature/css/nomenclature.css')
		);

		// Data directories to create when module is enabled.
		// Example: this->dirs = array("/nomenclature/temp");
		$this->dirs = array();

		// Config pages. Put here list of php page, stored into nomenclature/admin directory, to use to setup module.
		$this->config_page_url = array("nomenclature_setup.php@nomenclature");

		// Dependencies
		$this->hidden = false;			// A condition to hide module
		$this->depends = array();		// List of modules id that must be enabled if this module is enabled
		$this->requiredby = array();	// List of modules id to disable if this one is disabled
		$this->conflictwith = array();	// List of modules id this module is in conflict with
		$this->phpmin = array(7,4);					// Minimum version of PHP required by module
		$this->need_dolibarr_version = array(17,0);	// Minimum version of Dolibarr required by module
		$this->langfiles = array("nomenclature@nomenclature");

		$this->const[] = array('NOMENCLATURE_USE_TIME_BEFORE_LAUNCH','chaine','1','',0,'current');
		$this->const[] = array('NOMENCLATURE_USE_TIME_PREPARE','chaine','1','',0,'current');
		$this->const[] = array('NOMENCLATURE_USE_TIME_DOING','chaine','1','',0,'current');
		$this->const[] = array('NOMENCLATURE_CLOSE_ON_APPLY_NOMENCLATURE_PRICE','chaine','1','',0,'current');

		$this->tabs = array(
		    'product:+nomenclature:Nomenclature:nomenclature@nomenclature:$user->hasRight("nomenclature","read"):/nomenclature/nomenclature.php?fk_product=__ID__'
            ,'thirdparty:+nomenclaturecoef:Coefficient:nomenclature@nomenclature:$user->hasRight("nomenclature","tiers","updatecoef"):/nomenclature/nomenclature_coef.php?socid=__ID__&fiche=tiers'
        	,'propal:+nomenclaturecoef:Coefficient:nomenclature@nomenclature:$user->hasRight("nomenclature","propal","updatecoef"):/nomenclature/nomenclature_coef.php?id=__ID__&fiche=propal'
        	,'propal:+nomenclature:Nomenclatures:nomenclature@nomenclature:$user->hasRight("nomenclature","read") && getDolGlobalInt("NOMENCLATURE_SPEED_CLICK_SELECT"):/nomenclature/nomenclature-speed.php?id=__ID__&object=propal'
        	,'propal:+nomenclature:Nomenclatures:nomenclature@nomenclature:$user->hasRight("nomenclature","read") && !getDolGlobalInt("NOMENCLATURE_SPEED_CLICK_SELECT"):/nomenclature/nomenclature-detail.php?id=__ID__&object=propal'
        	,'order:+nomenclature:Nomenclatures:nomenclature@nomenclature:$user->hasRight("nomenclature","read") && getDolGlobalInt("NOMENCLATURE_SPEED_CLICK_SELECT"):/nomenclature/nomenclature-speed.php?id=__ID__&object=commande'
        	,'order:+nomenclature:Nomenclatures:nomenclature@nomenclature:$user->hasRight("nomenclature","read") && !getDolGlobalInt("NOMENCLATURE_SPEED_CLICK_SELECT"):/nomenclature/nomenclature-detail.php?id=__ID__&object=commande'
            ,'product:+nomenclaturecoef:Coefficient:nomenclature@nomenclature:$user->hasRight("nomenclature","product","updatecoef"):/nomenclature/nomenclature_coef_product.php?id=__ID__&fiche=product'
		    ,'project:+projectfeedback:Projectfeedback:nomenclature@nomenclature:$user->hasRight("nomenclature","read") && getDolGlobalInt("NOMENCLATURE_FEEDBACK"):/nomenclature/tab_project_feedback.php?id=__ID__'
		    ,'project:+projectfeedbackhistory:Projectfeedbackhistory:nomenclature@nomenclature:$user->hasRight("nomenclature","read") && getDolGlobalInt("NOMENCLATURE_FEEDBACK") && isModEnabled("stock"):/nomenclature/tab_project_feedback_history.php?id=__ID__'
        );

        // Dictionaries
	    if (isModEnabled('nomenclature'))
        {
        	$conf->nomenclature=new stdClass();
        	$conf->nomenclature->enabled=0;
        }
		$this->dictionaries=array();

        // Boxes
		// Add here list of php file(s) stored in core/boxes that contains class to show a box.
        $this->boxes = array();			// List of boxes
		// Example:
		//$this->boxes=array(array(0=>array('file'=>'myboxa.php','note'=>'','enabledbydefaulton'=>'Home'),1=>array('file'=>'myboxb.php','note'=>''),2=>array('file'=>'myboxc.php','note'=>'')););

		// Permissions
		$this->rights = array();		// Permission array used by this module
		$r=0;

		// Add here list of permission defined by an id, a label, a boolean and two constant strings.
		// Example:
        $this->rights[$r][0] = $this->numero + $r;  // Permission id (must not be already used)
        $this->rights[$r][1] = 'nomenclatureRead';  // Permission label
        $this->rights[$r][3] = 0;                   // Permission by default for new user (0/1)
        $this->rights[$r][4] = 'read';              // In php code, permission will be checked by test if ($user->hasRight("permkey", "level1", "level2"))
        $r++;

        $this->rights[$r][0] = $this->numero + $r;  // Permission id (must not be already used)
        $this->rights[$r][1] = 'nomenclatureWrite';  // Permission label
        $this->rights[$r][3] = 0;                   // Permission by default for new user (0/1)
        $this->rights[$r][4] = 'write';              // In php code, permission will be checked by test if ($user->hasRight("permkey", "level1", "level2"))
        $r++;


        $this->rights[$r][0] = $this->numero + $r;  // Permission id (must not be already used)
        $this->rights[$r][1] = 'nomenclatureShowPrice';  // Permission label
        $this->rights[$r][3] = 0;                   // Permission by default for new user (0/1)
        $this->rights[$r][4] = 'showPrice';              // In php code, permission will be checked by test if ($user->hasRight("permkey", "level1", "level2"))
        $r++;


        $this->rights[$r][0] = $this->numero + $r;  // Permission id (must not be already used)
        $this->rights[$r][1] = 'Personnaliser les coefficients d\'un tiers';  // Permission label
        $this->rights[$r][3] = 0;                   // Permission by default for new user (0/1)
        $this->rights[$r][4] = 'tiers';              // In php code, permission will be checked by test if ($user->hasRight("permkey", "level1", "level2"))
        $this->rights[$r][5] = 'updatecoef';
        $r++;


        $this->rights[$r][0] = $this->numero + $r;  // Permission id (must not be already used)
        $this->rights[$r][1] = 'Personnaliser les coefficients d\'une propal';  // Permission label
        $this->rights[$r][3] = 0;                   // Permission by default for new user (0/1)
        $this->rights[$r][4] = 'propal';              // In php code, permission will be checked by test if ($user->hasRight("permkey", "level1", "level2"))
        $this->rights[$r][5] = 'updatecoef';
        $r++;

       	$this->rights[$r][0] = $this->numero + $r;  // Permission id (must not be already used)
        $this->rights[$r][1] = 'massUpdate';  // Permission label
        $this->rights[$r][3] = 0;                   // Permission by default for new user (0/1)
        $this->rights[$r][4] = 'global';              // In php code, permission will be checked by test if ($user->hasRight("permkey", "level1", "level2"))
        $this->rights[$r][5] = 'massUpdate';              // In php code, permission will be checked by test if ($user->hasRight("permkey", "level1", "level2"))
        $r++;


		// Main menu entries
		$this->menu = array();			// List of menus to add
		$r=0;

		// Example to declare a Left Menu entry into an existing Top menu entry:
		$this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=products',		    // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
									'type'=>'left',			                // This is a Left menu entry
									'titre'=>'massUpdateMenu',
									'mainmenu'=>'products',
									'leftmenu'=>'nomenclature',
									'url'=>'/nomenclature/massUpdate.php',
									'langs'=>'nomenclature@nomenclature',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
									'position'=>100,
									'enabled'=>'isModEnabled("nomenclature")',  // Define condition to show or hide menu entry. Use '$conf->nomenclature->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
									'perms'=>'$user->hasRight("nomenclature","global","massUpdate")',			                // Use 'perms'=>'$user->hasRight("nomenclature", "level1", "level2")' if you want your menu with a permission rules
									'target'=>'',
									'user'=>0);				                // 0=Menu for internal users, 1=external users, 2=both
		 $r++;

		 $this->menu[$r]=array(	'fk_menu'=>'fk_mainmenu=products,fk_leftmenu=product',		    // Use 'fk_mainmenu=xxx' or 'fk_mainmenu=xxx,fk_leftmenu=yyy' where xxx is mainmenucode and yyy is a leftmenucode
		     'type'=>'left',			                // This is a Left menu entry
		     'titre'=>'Nomenclatures',
		     'mainmenu'=>'products',
		     'leftmenu'=>'nomenclature',
		     'url'=>'/nomenclature/list.php',
		     'langs'=>'nomenclature@nomenclature',	        // Lang file to use (without .lang) by module. File must be in langs/code_CODE/ directory.
		     'position'=>100,
		     'enabled'=>'isModEnabled("nomenclature")',  // Define condition to show or hide menu entry. Use '$conf->nomenclature->enabled' if entry must be visible if module is enabled. Use '$leftmenu==\'system\'' to show if leftmenu system is selected.
		     'perms'=>'$user->hasRight("nomenclature","read")',			                // Use 'perms'=>'$user->hasRight("nomenclature", "level1", "level2")' if you want your menu with a permission rules
		     'target'=>'',
		     'user'=>0);				                // 0=Menu for internal users, 1=external users, 2=both
		 $r++;

		// Exports
		$r=1;

	}

	/**
	 *		Function called when module is enabled.
	 *		The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *		It also creates data directories
	 *
     *      @param      string	$options    Options when enabling module ('', 'noboxes')
	 *      @return     int             	1 if OK, 0 if KO
	 */
	function init($options='')
	{
		$sql = array();

		define('INC_FROM_DOLIBARR',true);

		dol_include_once('/nomenclature/config.php');
		dol_include_once('/nomenclature/script/create-maj-base.php');

		$result=$this->_load_tables('/nomenclature/sql/');

		return $this->_init($sql, $options);
	}

	/**
	 *		Function called when module is disabled.
	 *      Remove from database constants, boxes and permissions from Dolibarr database.
	 *		Data directories are not deleted
	 *
     *      @param      string	$options    Options when enabling module ('', 'noboxes')
	 *      @return     int             	1 if OK, 0 if KO
	 */
	function remove($options='')
	{
		$sql = array();

		return $this->_remove($sql, $options);
	}

}
