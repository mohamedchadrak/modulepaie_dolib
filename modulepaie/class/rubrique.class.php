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
 * \file    class/rubrique.class.php
 * \ingroup modulepaie
 * \brief   Catalogue des rubriques de paie (gains et cotisations).
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Classe représentant une rubrique de paie réutilisable.
 */
class PaieRubrique extends CommonObject
{
	/** @var string Id to identify managed object */
	public $element = 'paierubrique';
	/** @var string Name of table without prefix */
	public $table_element = 'paie_rubrique';
	/** @var string Picto */
	public $picto = 'generic';

	public $rowid;
	public $id;
	public $entity;
	public $ref;
	public $label;
	public $type;        // gain, cotisation, net, info
	public $categorie;   // sante, atmp, retraite, famille, chomage, csgcrds, autres, gain, allegement, net
	public $base_type;   // brut, plafond_ss, tranche2, csg, fixe, manuel
	public $base_fixe;
	public $taux_salarial;
	public $taux_patronal;
	public $sens;        // plus, moins
	public $soumis;
	public $imposable;
	public $position;
	public $active;
	public $note;

	/**
	 * Catégories légales pour le bulletin clarifié.
	 * @var array
	 */
	public static $categories = array(
		'gain'       => 'CatGain',
		'sante'      => 'CatSante',
		'atmp'       => 'CatAtmp',
		'retraite'   => 'CatRetraite',
		'famille'    => 'CatFamille',
		'chomage'    => 'CatChomage',
		'csgcrds'    => 'CatCsgCrds',
		'autres'     => 'CatAutres',
		'allegement' => 'CatAllegement',
		'net'        => 'CatNet',
	);

	/**
	 * Constructor
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Create rubrique in database.
	 *
	 * @param  User $user User that creates
	 * @return int        Id of created object if OK, <0 if KO
	 */
	public function create(User $user)
	{
		global $conf;

		$this->ref = trim($this->ref);
		$this->label = trim($this->label);
		if (empty($this->ref) || empty($this->label)) {
			$this->error = 'RefAndLabelRequired';
			return -1;
		}
		if (empty($this->entity)) {
			$this->entity = $conf->entity;
		}

		$now = dol_now();

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."paie_rubrique(";
		$sql .= "entity, ref, label, type, categorie, base_type, base_fixe, taux_salarial, taux_patronal, sens, soumis, imposable, position, active, note, date_creation, fk_user_creat";
		$sql .= ") VALUES (";
		$sql .= ((int) $this->entity);
		$sql .= ", '".$this->db->escape($this->ref)."'";
		$sql .= ", '".$this->db->escape($this->label)."'";
		$sql .= ", '".$this->db->escape($this->type ? $this->type : 'cotisation')."'";
		$sql .= ", '".$this->db->escape($this->categorie ? $this->categorie : 'autres')."'";
		$sql .= ", '".$this->db->escape($this->base_type ? $this->base_type : 'brut')."'";
		$sql .= ", ".((float) price2num($this->base_fixe));
		$sql .= ", ".((float) price2num($this->taux_salarial));
		$sql .= ", ".((float) price2num($this->taux_patronal));
		$sql .= ", '".$this->db->escape($this->sens ? $this->sens : 'plus')."'";
		$sql .= ", ".((int) $this->soumis);
		$sql .= ", ".((int) $this->imposable);
		$sql .= ", ".((int) $this->position);
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
		$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."paie_rubrique");
		$this->rowid = $this->id;
		$this->db->commit();
		return $this->id;
	}

	/**
	 * Load object in memory from database.
	 *
	 * @param  int    $id  Id
	 * @param  string $ref Ref (used if $id empty)
	 * @return int         >0 if OK, 0 if not found, <0 if KO
	 */
	public function fetch($id, $ref = '')
	{
		global $conf;

		$sql = "SELECT rowid, entity, ref, label, type, categorie, base_type, base_fixe,";
		$sql .= " taux_salarial, taux_patronal, sens, soumis, imposable, position, active, note";
		$sql .= " FROM ".MAIN_DB_PREFIX."paie_rubrique";
		if ($id) {
			$sql .= " WHERE rowid = ".((int) $id);
		} else {
			$sql .= " WHERE ref = '".$this->db->escape($ref)."' AND entity IN (0, ".((int) $conf->entity).")";
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
		$this->ref = $obj->ref;
		$this->label = $obj->label;
		$this->type = $obj->type;
		$this->categorie = $obj->categorie;
		$this->base_type = $obj->base_type;
		$this->base_fixe = $obj->base_fixe;
		$this->taux_salarial = $obj->taux_salarial;
		$this->taux_patronal = $obj->taux_patronal;
		$this->sens = $obj->sens;
		$this->soumis = $obj->soumis;
		$this->imposable = $obj->imposable;
		$this->position = $obj->position;
		$this->active = $obj->active;
		$this->note = $obj->note;
		return 1;
	}

	/**
	 * Update object in database.
	 *
	 * @param  User $user User that modifies
	 * @return int        >0 if OK, <0 if KO
	 */
	public function update(User $user)
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX."paie_rubrique SET";
		$sql .= " ref = '".$this->db->escape(trim($this->ref))."'";
		$sql .= ", label = '".$this->db->escape(trim($this->label))."'";
		$sql .= ", type = '".$this->db->escape($this->type)."'";
		$sql .= ", categorie = '".$this->db->escape($this->categorie)."'";
		$sql .= ", base_type = '".$this->db->escape($this->base_type)."'";
		$sql .= ", base_fixe = ".((float) price2num($this->base_fixe));
		$sql .= ", taux_salarial = ".((float) price2num($this->taux_salarial));
		$sql .= ", taux_patronal = ".((float) price2num($this->taux_patronal));
		$sql .= ", sens = '".$this->db->escape($this->sens)."'";
		$sql .= ", soumis = ".((int) $this->soumis);
		$sql .= ", imposable = ".((int) $this->imposable);
		$sql .= ", position = ".((int) $this->position);
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
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."paie_rubrique WHERE rowid = ".((int) $this->id);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Return list of rubriques.
	 *
	 * @param  int    $activeonly 1 to return only active rubriques
	 * @param  string $type       Filter on type (empty = all)
	 * @return PaieRubrique[]|int Array of rubriques, or <0 if KO
	 */
	public function fetchAll($activeonly = 0, $type = '')
	{
		global $conf;

		$result = array();
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."paie_rubrique";
		$sql .= " WHERE entity IN (0, ".((int) $conf->entity).")";
		if ($activeonly) {
			$sql .= " AND active = 1";
		}
		if ($type) {
			$sql .= " AND type = '".$this->db->escape($type)."'";
		}
		$sql .= " ORDER BY position ASC, rowid ASC";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$r = new PaieRubrique($this->db);
			$r->fetch($obj->rowid);
			$result[] = $r;
		}
		return $result;
	}

	/**
	 * Return clickable name (link).
	 *
	 * @param  int    $withpicto 0=no picto, 1=with picto
	 * @return string            HTML link
	 */
	public function getNomUrl($withpicto = 0)
	{
		$url = dol_buildpath('/modulepaie/rubrique_card.php', 1).'?id='.$this->id;
		$label = $this->ref.' - '.$this->label;
		$link = '<a href="'.$url.'" title="'.dol_escape_htmltag($label).'">';
		$linkend = '</a>';
		$result = $link;
		if ($withpicto) {
			$result .= img_object('', $this->picto, 'class="paddingright"');
		}
		$result .= dol_escape_htmltag($this->ref);
		$result .= $linkend;
		return $result;
	}

	/**
	 * Seed the default catalog of French payroll rubriques.
	 * Rates are usual 2025 reference rates and MUST be checked against the
	 * applicable convention collective and URSSAF notifications.
	 *
	 * @param  int $entity Entity id
	 * @return int         Number of rubriques created
	 */
	public function seedDefault($entity = 1)
	{
		global $user;

		$nb = 0;
		$defaults = self::getDefaultCatalog();
		foreach ($defaults as $d) {
			$r = new PaieRubrique($this->db);
			$r->entity = $entity;
			$r->ref = $d['ref'];
			$r->label = $d['label'];
			$r->type = $d['type'];
			$r->categorie = $d['categorie'];
			$r->base_type = $d['base_type'];
			$r->base_fixe = isset($d['base_fixe']) ? $d['base_fixe'] : 0;
			$r->taux_salarial = isset($d['ts']) ? $d['ts'] : 0;
			$r->taux_patronal = isset($d['tp']) ? $d['tp'] : 0;
			$r->sens = isset($d['sens']) ? $d['sens'] : 'plus';
			$r->soumis = isset($d['soumis']) ? $d['soumis'] : 1;
			$r->imposable = isset($d['imposable']) ? $d['imposable'] : 1;
			$r->position = $d['position'];
			$r->active = isset($d['active']) ? $d['active'] : 1;
			$uid = is_object($user) ? $user : new User($this->db);
			if ($r->create($uid) > 0) {
				$nb++;
			}
		}
		return $nb;
	}

	/**
	 * Return the default catalog definition (French payroll).
	 *
	 * @return array
	 */
	public static function getDefaultCatalog()
	{
		return array(
			// ---- GAINS ----
			array('ref' => 'SALBASE', 'label' => 'Salaire de base', 'type' => 'gain', 'categorie' => 'gain', 'base_type' => 'manuel', 'sens' => 'plus', 'soumis' => 1, 'imposable' => 1, 'position' => 10),
			array('ref' => 'HSUP25', 'label' => 'Heures supplémentaires 125%', 'type' => 'gain', 'categorie' => 'gain', 'base_type' => 'manuel', 'sens' => 'plus', 'soumis' => 1, 'imposable' => 1, 'position' => 20, 'active' => 0),
			array('ref' => 'PRIME', 'label' => 'Prime', 'type' => 'gain', 'categorie' => 'gain', 'base_type' => 'manuel', 'sens' => 'plus', 'soumis' => 1, 'imposable' => 1, 'position' => 30, 'active' => 0),

			// ---- SANTE ----
			array('ref' => 'SANTE_SS', 'label' => 'Sécurité sociale - Maladie Maternité Invalidité Décès', 'type' => 'cotisation', 'categorie' => 'sante', 'base_type' => 'brut', 'ts' => 0, 'tp' => 7.00, 'position' => 100),
			array('ref' => 'SANTE_COMP_PREV', 'label' => 'Complémentaire Incapacité Invalidité Décès (prévoyance)', 'type' => 'cotisation', 'categorie' => 'sante', 'base_type' => 'plafond_ss', 'ts' => 0.50, 'tp' => 0.50, 'position' => 110, 'active' => 0),
			array('ref' => 'SANTE_MUTUELLE', 'label' => 'Complémentaire Santé (mutuelle)', 'type' => 'cotisation', 'categorie' => 'sante', 'base_type' => 'fixe', 'base_fixe' => 40, 'ts' => 50, 'tp' => 50, 'position' => 120, 'active' => 0),

			// ---- ACCIDENTS DU TRAVAIL ----
			array('ref' => 'ATMP', 'label' => 'Accidents du travail - Maladies professionnelles', 'type' => 'cotisation', 'categorie' => 'atmp', 'base_type' => 'brut', 'ts' => 0, 'tp' => 2.00, 'position' => 200),

			// ---- RETRAITE ----
			array('ref' => 'RETRAITE_SS_PLAF', 'label' => 'Sécurité sociale plafonnée', 'type' => 'cotisation', 'categorie' => 'retraite', 'base_type' => 'plafond_ss', 'ts' => 6.90, 'tp' => 8.55, 'position' => 300),
			array('ref' => 'RETRAITE_SS_DEPLAF', 'label' => 'Sécurité sociale déplafonnée', 'type' => 'cotisation', 'categorie' => 'retraite', 'base_type' => 'brut', 'ts' => 0.40, 'tp' => 2.02, 'position' => 310),
			array('ref' => 'RETRAITE_ARRCO_T1', 'label' => 'Complémentaire Tranche 1 (Agirc-Arrco)', 'type' => 'cotisation', 'categorie' => 'retraite', 'base_type' => 'plafond_ss', 'ts' => 3.15, 'tp' => 4.72, 'position' => 320),
			array('ref' => 'RETRAITE_CEG_T1', 'label' => 'Contribution équilibre général Tranche 1', 'type' => 'cotisation', 'categorie' => 'retraite', 'base_type' => 'plafond_ss', 'ts' => 0.86, 'tp' => 1.29, 'position' => 330),
			array('ref' => 'RETRAITE_ARRCO_T2', 'label' => 'Complémentaire Tranche 2 (Agirc-Arrco)', 'type' => 'cotisation', 'categorie' => 'retraite', 'base_type' => 'tranche2', 'ts' => 8.64, 'tp' => 12.95, 'position' => 340),
			array('ref' => 'RETRAITE_CEG_T2', 'label' => 'Contribution équilibre général Tranche 2', 'type' => 'cotisation', 'categorie' => 'retraite', 'base_type' => 'tranche2', 'ts' => 1.08, 'tp' => 1.62, 'position' => 350),
			array('ref' => 'RETRAITE_CET', 'label' => 'Contribution équilibre technique', 'type' => 'cotisation', 'categorie' => 'retraite', 'base_type' => 'brut', 'ts' => 0.14, 'tp' => 0.21, 'position' => 360, 'active' => 0),

			// ---- FAMILLE ----
			array('ref' => 'FAMILLE_ALLOC', 'label' => 'Allocations familiales', 'type' => 'cotisation', 'categorie' => 'famille', 'base_type' => 'brut', 'ts' => 0, 'tp' => 3.45, 'position' => 400),

			// ---- ASSURANCE CHOMAGE ----
			array('ref' => 'CHOMAGE', 'label' => 'Assurance chômage', 'type' => 'cotisation', 'categorie' => 'chomage', 'base_type' => 'brut', 'ts' => 0, 'tp' => 4.05, 'position' => 500),
			array('ref' => 'CHOMAGE_AGS', 'label' => 'Fonds de garantie des salaires (AGS)', 'type' => 'cotisation', 'categorie' => 'chomage', 'base_type' => 'brut', 'ts' => 0, 'tp' => 0.25, 'position' => 510),
			array('ref' => 'CHOMAGE_APEC', 'label' => 'APEC (cadres)', 'type' => 'cotisation', 'categorie' => 'chomage', 'base_type' => 'plafond_ss', 'ts' => 0.024, 'tp' => 0.036, 'position' => 520, 'active' => 0),

			// ---- CSG / CRDS ----
			// CSG déductible : réduit le net imposable (imposable=1 => déductible)
			array('ref' => 'CSG_DED', 'label' => 'CSG déductible de l\'impôt sur le revenu', 'type' => 'cotisation', 'categorie' => 'csgcrds', 'base_type' => 'csg', 'ts' => 6.80, 'tp' => 0, 'imposable' => 1, 'position' => 600),
			// CSG/CRDS non déductible : ne réduit PAS le net imposable (imposable=0)
			array('ref' => 'CSG_CRDS_NONDED', 'label' => 'CSG/CRDS non déductible de l\'impôt sur le revenu', 'type' => 'cotisation', 'categorie' => 'csgcrds', 'base_type' => 'csg', 'ts' => 2.90, 'tp' => 0, 'imposable' => 0, 'position' => 610),

			// ---- ALLEGEMENTS PATRONAUX ----
			array('ref' => 'ALLEG_GEN', 'label' => 'Allègement général de cotisations patronales', 'type' => 'cotisation', 'categorie' => 'allegement', 'base_type' => 'manuel', 'ts' => 0, 'tp' => 0, 'sens' => 'moins', 'position' => 700, 'active' => 0),
		);
	}
}
