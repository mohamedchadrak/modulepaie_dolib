<?php
/* Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \defgroup   modulepaie     Module Paie (fiches de paie françaises)
 * \brief      Module de gestion des bulletins de paie au format légal français.
 * \file       htdocs/custom/modulepaie/core/modules/modModulePaie.class.php
 * \ingroup    modulepaie
 * \brief      Description and activation file for the module Paie
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module ModulePaie
 */
class modModulePaie extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;
		$this->db = $db;

		// Id for module (must be unique). Range 500000..599999 reserved for external modules.
		$this->numero = 500180;
		// Key text used to identify module (for permissions, menus, etc.)
		$this->rights_class = 'modulepaie';

		$this->family = "hr";
		$this->module_position = '90';
		$this->name = preg_replace('/^mod/i', '', get_class($this));
		$this->description = "Gestion des bulletins de paie au format légal français";
		$this->descriptionlong = "Module permettant d'éditer des fiches de paie conformes au format légal français (bulletin clarifié, net social).";

		$this->editor_name = 'Mohamed Chadrak';
		$this->editor_url = 'https://github.com/mohamedchadrak/modulepaie_dolib';

		$this->version = '1.1.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
		$this->picto = 'modulepaie@modulepaie';

		// Data directories to create when module is enabled.
		$this->dirs = array("/modulepaie/temp");

		// Config pages.
		$this->config_page_url = array("setup.php@modulepaie");

		// Dependencies
		$this->hidden = false;
		$this->depends = array('modUser');
		$this->requiredby = array();
		$this->conflictwith = array();
		$this->langfiles = array("modulepaie@modulepaie");
		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(16, 0);

		// Constants
		$this->const = array();

		// New pages on tabs
		$this->tabs = array();

		// Dictionaries
		$this->dictionaries = array();

		// Boxes/Widgets
		$this->boxes = array();

		// Cronjobs
		$this->cronjobs = array();

		// Permissions provided by this module
		$this->rights = array();
		$r = 0;

		$this->rights[$r][0] = $this->numero.'01';
		$this->rights[$r][1] = 'Lire les bulletins de paie';
		$this->rights[$r][4] = 'bulletin';
		$this->rights[$r][5] = 'read';
		$r++;

		$this->rights[$r][0] = $this->numero.'02';
		$this->rights[$r][1] = 'Créer/modifier les bulletins de paie';
		$this->rights[$r][4] = 'bulletin';
		$this->rights[$r][5] = 'write';
		$r++;

		$this->rights[$r][0] = $this->numero.'03';
		$this->rights[$r][1] = 'Supprimer les bulletins de paie';
		$this->rights[$r][4] = 'bulletin';
		$this->rights[$r][5] = 'delete';
		$r++;

		$this->rights[$r][0] = $this->numero.'04';
		$this->rights[$r][1] = 'Valider les bulletins de paie';
		$this->rights[$r][4] = 'bulletin';
		$this->rights[$r][5] = 'validate';
		$r++;

		$this->rights[$r][0] = $this->numero.'05';
		$this->rights[$r][1] = 'Consulter ses propres bulletins de paie (self-service salarié)';
		$this->rights[$r][4] = 'bulletin';
		$this->rights[$r][5] = 'readmy';
		$r++;

		$this->rights[$r][0] = $this->numero.'11';
		$this->rights[$r][1] = 'Gérer les salariés et le catalogue de rubriques';
		$this->rights[$r][4] = 'config';
		$this->rights[$r][5] = 'write';
		$r++;

		// Main menu entries
		$this->menu = array();
		$r = 0;

		// Top menu
		$this->menu[$r++] = array(
			'fk_menu' => '',
			'type' => 'top',
			'titre' => 'MenuPaie',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'modulepaie',
			'leftmenu' => '',
			'url' => '/modulepaie/index.php',
			'langs' => 'modulepaie@modulepaie',
			'position' => 1000 + $r,
			'enabled' => '$conf->modulepaie->enabled',
			'perms' => '$user->rights->modulepaie->bulletin->read || $user->rights->modulepaie->bulletin->readmy',
			'target' => '',
			'user' => 2,
		);

		// Left menu - Mes bulletins (self-service salarié)
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=modulepaie',
			'type' => 'left',
			'titre' => 'MenuMesBulletins',
			'mainmenu' => 'modulepaie',
			'leftmenu' => 'modulepaie_mesbulletins',
			'url' => '/modulepaie/mesbulletins.php',
			'langs' => 'modulepaie@modulepaie',
			'position' => 1000 + $r,
			'enabled' => '$conf->modulepaie->enabled',
			'perms' => '$user->rights->modulepaie->bulletin->readmy',
			'target' => '',
			'user' => 2,
		);

		// Left menu - Bulletins
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=modulepaie',
			'type' => 'left',
			'titre' => 'MenuBulletins',
			'mainmenu' => 'modulepaie',
			'leftmenu' => 'modulepaie_bulletin',
			'url' => '/modulepaie/bulletin_list.php',
			'langs' => 'modulepaie@modulepaie',
			'position' => 1000 + $r,
			'enabled' => '$conf->modulepaie->enabled',
			'perms' => '$user->rights->modulepaie->bulletin->read',
			'target' => '',
			'user' => 2,
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=modulepaie,fk_leftmenu=modulepaie_bulletin',
			'type' => 'left',
			'titre' => 'MenuNewBulletin',
			'mainmenu' => 'modulepaie',
			'leftmenu' => 'modulepaie_bulletin_new',
			'url' => '/modulepaie/bulletin_card.php?action=create',
			'langs' => 'modulepaie@modulepaie',
			'position' => 1000 + $r,
			'enabled' => '$conf->modulepaie->enabled',
			'perms' => '$user->rights->modulepaie->bulletin->write',
			'target' => '',
			'user' => 2,
		);

		// Left menu - Salariés
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=modulepaie',
			'type' => 'left',
			'titre' => 'MenuSalaries',
			'mainmenu' => 'modulepaie',
			'leftmenu' => 'modulepaie_salarie',
			'url' => '/modulepaie/salarie_list.php',
			'langs' => 'modulepaie@modulepaie',
			'position' => 1000 + $r,
			'enabled' => '$conf->modulepaie->enabled',
			'perms' => '$user->rights->modulepaie->bulletin->read',
			'target' => '',
			'user' => 2,
		);
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=modulepaie,fk_leftmenu=modulepaie_salarie',
			'type' => 'left',
			'titre' => 'MenuNewSalarie',
			'mainmenu' => 'modulepaie',
			'leftmenu' => 'modulepaie_salarie_new',
			'url' => '/modulepaie/salarie_card.php?action=create',
			'langs' => 'modulepaie@modulepaie',
			'position' => 1000 + $r,
			'enabled' => '$conf->modulepaie->enabled',
			'perms' => '$user->rights->modulepaie->config->write',
			'target' => '',
			'user' => 2,
		);

		// Left menu - Rubriques
		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=modulepaie',
			'type' => 'left',
			'titre' => 'MenuRubriques',
			'mainmenu' => 'modulepaie',
			'leftmenu' => 'modulepaie_rubrique',
			'url' => '/modulepaie/rubrique_list.php',
			'langs' => 'modulepaie@modulepaie',
			'position' => 1000 + $r,
			'enabled' => '$conf->modulepaie->enabled',
			'perms' => '$user->rights->modulepaie->config->write',
			'target' => '',
			'user' => 2,
		);
	}

	/**
	 * Function called when module is enabled.
	 * The init function adds constants, boxes, permissions and menus
	 * (defined in constructor) into Dolibarr database.
	 * It also creates data directories.
	 *
	 * @param  string $options Options when enabling module ('', 'noboxes')
	 * @return int             1 if OK, 0 if KO
	 */
	public function init($options = '')
	{
		global $conf, $langs;

		$result = $this->_load_tables('/modulepaie/sql/');
		if ($result < 0) {
			return -1; // Do not activate module if error 'not allowed' returned when loading module SQL queries (the _load_table run sql with run_sql with the error allowed parameter set to 'default')
		}

		// Migrations for existing installations (errors ignored if already applied).
		$this->db->query("ALTER TABLE ".MAIN_DB_PREFIX."paie_bulletin ADD COLUMN fk_bank integer DEFAULT NULL");

		// Create extrafields during init
		//include_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

		// Load default rubriques catalog (French payroll contributions)
		$this->_load_default_rubriques();

		// Permissions
		$this->remove($options);

		$sql = array();

		return $this->_init($sql, $options);
	}

	/**
	 * Function called when module is disabled.
	 *
	 * @param  string $options Options when disabling module ('', 'noboxes')
	 * @return int             1 if OK, 0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}

	/**
	 * Load the default catalog of French payroll rubriques if the table is empty.
	 *
	 * @return int <0 if KO, >=0 if OK
	 */
	private function _load_default_rubriques()
	{
		global $conf;

		// Only seed if table exists and is empty.
		$sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."paie_rubrique WHERE entity IN (0, ".((int) $conf->entity).")";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		if ($obj && $obj->nb > 0) {
			return 0; // Already seeded.
		}

		require_once dirname(__FILE__).'/../../class/rubrique.class.php';
		$rub = new PaieRubrique($this->db);
		$rub->seedDefault((int) $conf->entity);

		return 1;
	}
}
