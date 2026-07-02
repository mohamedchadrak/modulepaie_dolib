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
 */

/**
 * \file    class/contrat.class.php
 * \ingroup modulepaie
 * \brief   Fiche salarié / contrat de travail.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

/**
 * Classe représentant la fiche d'un salarié (contrat de travail).
 */
class PaieContrat extends CommonObject
{
	public $element = 'paiecontrat';
	public $table_element = 'paie_contrat';
	public $picto = 'user';

	public $id;
	public $rowid;
	public $entity;
	public $fk_user;
	public $matricule;
	public $num_secu;
	public $emploi;
	public $qualification;
	public $classification;
	public $coefficient;
	public $niveau;
	public $echelon;
	public $convention;
	public $date_entree;
	public $date_anciennete;
	public $date_sortie;
	public $type_contrat;   // CDI, CDD, ...
	public $categorie;      // cadre, non_cadre
	public $salaire_base;
	public $temps_travail;  // heures mensuelles
	public $taux_horaire;
	public $active;
	public $note;

	/** @var User Loaded employee user object */
	public $user;

	/**
	 * Constructor
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Create in database.
	 *
	 * @param  User $user User that creates
	 * @return int        Id if OK, <0 if KO
	 */
	public function create(User $user)
	{
		global $conf;

		if (empty($this->fk_user)) {
			$this->error = 'EmployeeUserRequired';
			return -1;
		}
		if (empty($this->entity)) {
			$this->entity = $conf->entity;
		}
		if (empty($this->temps_travail)) {
			$this->temps_travail = 151.67;
		}
		// Deduce hourly rate if not provided.
		if (empty($this->taux_horaire) && !empty($this->salaire_base) && !empty($this->temps_travail)) {
			$this->taux_horaire = round($this->salaire_base / $this->temps_travail, 4);
		}

		$now = dol_now();

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."paie_contrat(";
		$sql .= "entity, fk_user, matricule, num_secu, emploi, qualification, classification, coefficient, niveau, echelon, convention,";
		$sql .= " date_entree, date_anciennete, date_sortie, type_contrat, categorie, salaire_base, temps_travail, taux_horaire, active, note, date_creation, fk_user_creat";
		$sql .= ") VALUES (";
		$sql .= ((int) $this->entity);
		$sql .= ", ".((int) $this->fk_user);
		$sql .= ", ".($this->matricule ? "'".$this->db->escape($this->matricule)."'" : "NULL");
		$sql .= ", ".($this->num_secu ? "'".$this->db->escape($this->num_secu)."'" : "NULL");
		$sql .= ", ".($this->emploi ? "'".$this->db->escape($this->emploi)."'" : "NULL");
		$sql .= ", ".($this->qualification ? "'".$this->db->escape($this->qualification)."'" : "NULL");
		$sql .= ", ".($this->classification ? "'".$this->db->escape($this->classification)."'" : "NULL");
		$sql .= ", ".($this->coefficient ? "'".$this->db->escape($this->coefficient)."'" : "NULL");
		$sql .= ", ".($this->niveau ? "'".$this->db->escape($this->niveau)."'" : "NULL");
		$sql .= ", ".($this->echelon ? "'".$this->db->escape($this->echelon)."'" : "NULL");
		$sql .= ", ".($this->convention ? "'".$this->db->escape($this->convention)."'" : "NULL");
		$sql .= ", ".($this->date_entree ? "'".$this->db->idate($this->date_entree)."'" : "NULL");
		$sql .= ", ".($this->date_anciennete ? "'".$this->db->idate($this->date_anciennete)."'" : "NULL");
		$sql .= ", ".($this->date_sortie ? "'".$this->db->idate($this->date_sortie)."'" : "NULL");
		$sql .= ", '".$this->db->escape($this->type_contrat ? $this->type_contrat : 'CDI')."'";
		$sql .= ", '".$this->db->escape($this->categorie ? $this->categorie : 'non_cadre')."'";
		$sql .= ", ".((float) price2num($this->salaire_base));
		$sql .= ", ".((float) price2num($this->temps_travail));
		$sql .= ", ".((float) price2num($this->taux_horaire));
		$sql .= ", ".((int) (isset($this->active) ? $this->active : 1));
		$sql .= ", ".($this->note ? "'".$this->db->escape($this->note)."'" : "NULL");
		$sql .= ", '".$this->db->idate($now)."'";
		$sql .= ", ".((int) $user->id);
		$sql .= ")";

		$this->db->begin();
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -2;
		}
		$this->id = $this->rowid = $this->db->last_insert_id(MAIN_DB_PREFIX."paie_contrat");
		$this->db->commit();
		return $this->id;
	}

	/**
	 * Load object in memory from database.
	 *
	 * @param  int $id      Id of contrat
	 * @param  int $fk_user If set and $id empty, load by employee user id
	 * @return int          >0 if OK, 0 if not found, <0 if KO
	 */
	public function fetch($id, $fk_user = 0)
	{
		global $conf;

		$sql = "SELECT rowid, entity, fk_user, matricule, num_secu, emploi, qualification, classification,";
		$sql .= " coefficient, niveau, echelon, convention, date_entree, date_anciennete, date_sortie,";
		$sql .= " type_contrat, categorie, salaire_base, temps_travail, taux_horaire, active, note";
		$sql .= " FROM ".MAIN_DB_PREFIX."paie_contrat";
		if ($id) {
			$sql .= " WHERE rowid = ".((int) $id);
		} else {
			$sql .= " WHERE fk_user = ".((int) $fk_user)." AND entity IN (".getEntity('paie_contrat').")";
		}

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		if (!$this->db->num_rows($resql)) {
			return 0;
		}
		$obj = $this->db->fetch_object($resql);
		$this->id = $this->rowid = $obj->rowid;
		$this->entity = $obj->entity;
		$this->fk_user = $obj->fk_user;
		$this->matricule = $obj->matricule;
		$this->num_secu = $obj->num_secu;
		$this->emploi = $obj->emploi;
		$this->qualification = $obj->qualification;
		$this->classification = $obj->classification;
		$this->coefficient = $obj->coefficient;
		$this->niveau = $obj->niveau;
		$this->echelon = $obj->echelon;
		$this->convention = $obj->convention;
		$this->date_entree = $this->db->jdate($obj->date_entree);
		$this->date_anciennete = $this->db->jdate($obj->date_anciennete);
		$this->date_sortie = $this->db->jdate($obj->date_sortie);
		$this->type_contrat = $obj->type_contrat;
		$this->categorie = $obj->categorie;
		$this->salaire_base = $obj->salaire_base;
		$this->temps_travail = $obj->temps_travail;
		$this->taux_horaire = $obj->taux_horaire;
		$this->active = $obj->active;
		$this->note = $obj->note;
		return 1;
	}

	/**
	 * Load the linked Dolibarr user object into $this->user.
	 *
	 * @return int >0 if OK, <=0 if KO
	 */
	public function fetchUser()
	{
		if (empty($this->fk_user)) {
			return -1;
		}
		$u = new User($this->db);
		$res = $u->fetch($this->fk_user);
		if ($res > 0) {
			$this->user = $u;
		}
		return $res;
	}

	/**
	 * Update object in database.
	 *
	 * @param  User $user User that modifies
	 * @return int        >0 if OK, <0 if KO
	 */
	public function update(User $user)
	{
		if (empty($this->taux_horaire) && !empty($this->salaire_base) && !empty($this->temps_travail)) {
			$this->taux_horaire = round($this->salaire_base / $this->temps_travail, 4);
		}

		$sql = "UPDATE ".MAIN_DB_PREFIX."paie_contrat SET";
		$sql .= " matricule = ".($this->matricule ? "'".$this->db->escape($this->matricule)."'" : "NULL");
		$sql .= ", num_secu = ".($this->num_secu ? "'".$this->db->escape($this->num_secu)."'" : "NULL");
		$sql .= ", emploi = ".($this->emploi ? "'".$this->db->escape($this->emploi)."'" : "NULL");
		$sql .= ", qualification = ".($this->qualification ? "'".$this->db->escape($this->qualification)."'" : "NULL");
		$sql .= ", classification = ".($this->classification ? "'".$this->db->escape($this->classification)."'" : "NULL");
		$sql .= ", coefficient = ".($this->coefficient ? "'".$this->db->escape($this->coefficient)."'" : "NULL");
		$sql .= ", niveau = ".($this->niveau ? "'".$this->db->escape($this->niveau)."'" : "NULL");
		$sql .= ", echelon = ".($this->echelon ? "'".$this->db->escape($this->echelon)."'" : "NULL");
		$sql .= ", convention = ".($this->convention ? "'".$this->db->escape($this->convention)."'" : "NULL");
		$sql .= ", date_entree = ".($this->date_entree ? "'".$this->db->idate($this->date_entree)."'" : "NULL");
		$sql .= ", date_anciennete = ".($this->date_anciennete ? "'".$this->db->idate($this->date_anciennete)."'" : "NULL");
		$sql .= ", date_sortie = ".($this->date_sortie ? "'".$this->db->idate($this->date_sortie)."'" : "NULL");
		$sql .= ", type_contrat = '".$this->db->escape($this->type_contrat ? $this->type_contrat : 'CDI')."'";
		$sql .= ", categorie = '".$this->db->escape($this->categorie ? $this->categorie : 'non_cadre')."'";
		$sql .= ", salaire_base = ".((float) price2num($this->salaire_base));
		$sql .= ", temps_travail = ".((float) price2num($this->temps_travail));
		$sql .= ", taux_horaire = ".((float) price2num($this->taux_horaire));
		$sql .= ", active = ".((int) $this->active);
		$sql .= ", note = ".($this->note ? "'".$this->db->escape($this->note)."'" : "NULL");
		$sql .= ", fk_user_modif = ".((int) $user->id);
		$sql .= " WHERE rowid = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Delete object in database.
	 *
	 * @param  User $user User that deletes
	 * @return int        >0 if OK, <0 if KO
	 */
	public function delete(User $user)
	{
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."paie_contrat WHERE rowid = ".((int) $this->id);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Return list of contrats.
	 *
	 * @param  int $activeonly 1 to return only active contrats
	 * @return PaieContrat[]|int Array of contrats, or <0 if KO
	 */
	public function fetchAll($activeonly = 0)
	{
		$result = array();
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."paie_contrat";
		$sql .= " WHERE entity IN (".getEntity('paie_contrat').")";
		if ($activeonly) {
			$sql .= " AND active = 1";
		}
		$sql .= " ORDER BY rowid ASC";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$c = new PaieContrat($this->db);
			$c->fetch($obj->rowid);
			$c->fetchUser();
			$result[] = $c;
		}
		return $result;
	}

	/**
	 * Return the employee full name.
	 *
	 * @return string
	 */
	public function getFullName()
	{
		if (empty($this->user)) {
			$this->fetchUser();
		}
		if (!empty($this->user)) {
			return $this->user->getFullName($GLOBALS['langs']);
		}
		return '';
	}

	/**
	 * Return clickable name (link).
	 *
	 * @param  int $withpicto 0=no picto, 1=with picto
	 * @return string HTML link
	 */
	public function getNomUrl($withpicto = 0)
	{
		$url = dol_buildpath('/modulepaie/salarie_card.php', 1).'?id='.$this->id;
		$label = $this->getFullName();
		if (empty($label)) {
			$label = 'Salarié #'.$this->id;
		}
		$result = '<a href="'.$url.'">';
		if ($withpicto) {
			$result .= img_object('', $this->picto, 'class="paddingright"');
		}
		$result .= dol_escape_htmltag($label);
		$result .= '</a>';
		return $result;
	}
}
