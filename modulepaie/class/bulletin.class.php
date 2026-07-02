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
 * \file    class/bulletin.class.php
 * \ingroup modulepaie
 * \brief   Bulletin de paie et moteur de calcul (format légal français).
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
dol_include_once('/modulepaie/class/contrat.class.php');
dol_include_once('/modulepaie/class/rubrique.class.php');
dol_include_once('/modulepaie/class/bulletinligne.class.php');

/**
 * Classe représentant un bulletin de paie.
 */
class PaieBulletin extends CommonObject
{
	public $element = 'paiebulletin';
	public $table_element = 'paie_bulletin';
	public $table_element_line = 'paie_bulletin_ligne';
	public $fk_element = 'fk_bulletin';
	public $picto = 'bill';

	const STATUS_DRAFT = 0;
	const STATUS_VALIDATED = 1;
	const STATUS_PAID = 9;

	public $id;
	public $rowid;
	public $entity;
	public $ref;
	public $fk_user;      // salarié (user Dolibarr)
	public $fk_contrat;
	public $date_debut;
	public $date_fin;
	public $date_paiement;
	public $salaire_base;
	public $heures;
	public $taux_horaire;
	public $plafond_ss;
	public $brut;
	public $total_cot_sal;
	public $total_cot_pat;
	public $net_a_payer;
	public $net_imposable;
	public $net_social;
	public $cout_employeur;
	public $cumul_brut;
	public $cumul_net_imp;
	public $cumul_net_social;
	public $conges_acquis;
	public $conges_pris;
	public $conges_solde;
	public $status;
	/** @var int Id of the bank transaction line created when the bulletin is paid */
	public $fk_bank;
	public $note_public;
	public $note_private;
	public $model_pdf;
	public $last_main_doc;
	public $date_creation;
	public $date_validation;
	public $fk_user_valid;

	/** @var PaieBulletinLigne[] */
	public $lignes = array();
	/** @var PaieContrat */
	public $contrat;
	/** @var User */
	public $salarie;

	/**
	 * Constructor
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
		$this->status = self::STATUS_DRAFT;
	}

	/**
	 * Return the monthly social security ceiling (plafond mensuel SS) from config.
	 *
	 * @return float
	 */
	public static function getPMSS()
	{
		$v = (float) getDolGlobalString('MODULEPAIE_PMSS', '3925');
		return $v > 0 ? $v : 3925;
	}

	/**
	 * Return next reference for a new bulletin.
	 *
	 * @return string
	 */
	public function getNextNumRef()
	{
		global $conf;

		$year = $this->date_debut ? dol_print_date($this->date_debut, '%y') : dol_print_date(dol_now(), '%y');
		$month = $this->date_debut ? dol_print_date($this->date_debut, '%m') : dol_print_date(dol_now(), '%m');
		$prefix = 'BULL'.$year.$month.'-';

		$sql = "SELECT ref FROM ".MAIN_DB_PREFIX."paie_bulletin";
		$sql .= " WHERE ref LIKE '".$this->db->escape($prefix)."%'";
		$sql .= " AND entity IN (".getEntity('paie_bulletin').")";
		$sql .= " ORDER BY ref DESC LIMIT 1";
		$resql = $this->db->query($sql);
		$counter = 0;
		if ($resql && $this->db->num_rows($resql)) {
			$obj = $this->db->fetch_object($resql);
			$counter = (int) substr($obj->ref, -4);
		}
		$counter++;
		return $prefix.sprintf('%04d', $counter);
	}

	/**
	 * Create bulletin (header + lines) in database.
	 *
	 * @param  User $user   User that creates
	 * @param  int  $notrigger 1 to disable triggers
	 * @return int           Id if OK, <0 if KO
	 */
	public function create(User $user, $notrigger = 0)
	{
		global $conf;

		if (empty($this->fk_user)) {
			$this->error = 'EmployeeRequired';
			return -1;
		}
		if (empty($this->date_debut) || empty($this->date_fin)) {
			$this->error = 'PeriodRequired';
			return -1;
		}
		if (empty($this->entity)) {
			$this->entity = $conf->entity;
		}
		if (empty($this->ref) || $this->ref == '(PROV)') {
			$this->ref = $this->getNextNumRef();
		}
		if (empty($this->plafond_ss)) {
			$this->plafond_ss = self::getPMSS();
		}
		if (empty($this->model_pdf)) {
			$this->model_pdf = getDolGlobalString('MODULEPAIE_PDF_MODEL', 'paiestandard');
		}

		// Make sure totals are computed.
		$this->computeTotals();

		$now = dol_now();

		$this->db->begin();

		$sql = "INSERT INTO ".MAIN_DB_PREFIX."paie_bulletin(";
		$sql .= "entity, ref, fk_user, fk_contrat, date_debut, date_fin, date_paiement, salaire_base, heures, taux_horaire,";
		$sql .= " plafond_ss, brut, total_cot_sal, total_cot_pat, net_a_payer, net_imposable, net_social, cout_employeur,";
		$sql .= " cumul_brut, cumul_net_imp, cumul_net_social, conges_acquis, conges_pris, conges_solde,";
		$sql .= " status, note_public, note_private, model_pdf, date_creation, fk_user_creat";
		$sql .= ") VALUES (";
		$sql .= ((int) $this->entity);
		$sql .= ", '".$this->db->escape($this->ref)."'";
		$sql .= ", ".((int) $this->fk_user);
		$sql .= ", ".($this->fk_contrat ? ((int) $this->fk_contrat) : "NULL");
		$sql .= ", '".$this->db->idate($this->date_debut)."'";
		$sql .= ", '".$this->db->idate($this->date_fin)."'";
		$sql .= ", ".($this->date_paiement ? "'".$this->db->idate($this->date_paiement)."'" : "NULL");
		$sql .= ", ".((float) price2num($this->salaire_base));
		$sql .= ", ".((float) price2num($this->heures));
		$sql .= ", ".((float) price2num($this->taux_horaire));
		$sql .= ", ".((float) price2num($this->plafond_ss));
		$sql .= ", ".((float) price2num($this->brut));
		$sql .= ", ".((float) price2num($this->total_cot_sal));
		$sql .= ", ".((float) price2num($this->total_cot_pat));
		$sql .= ", ".((float) price2num($this->net_a_payer));
		$sql .= ", ".((float) price2num($this->net_imposable));
		$sql .= ", ".((float) price2num($this->net_social));
		$sql .= ", ".((float) price2num($this->cout_employeur));
		$sql .= ", ".((float) price2num($this->cumul_brut));
		$sql .= ", ".((float) price2num($this->cumul_net_imp));
		$sql .= ", ".((float) price2num($this->cumul_net_social));
		$sql .= ", ".((float) price2num($this->conges_acquis));
		$sql .= ", ".((float) price2num($this->conges_pris));
		$sql .= ", ".((float) price2num($this->conges_solde));
		$sql .= ", ".((int) $this->status);
		$sql .= ", ".($this->note_public ? "'".$this->db->escape($this->note_public)."'" : "NULL");
		$sql .= ", ".($this->note_private ? "'".$this->db->escape($this->note_private)."'" : "NULL");
		$sql .= ", '".$this->db->escape($this->model_pdf)."'";
		$sql .= ", '".$this->db->idate($now)."'";
		$sql .= ", ".((int) $user->id);
		$sql .= ")";

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -2;
		}
		$this->id = $this->rowid = $this->db->last_insert_id(MAIN_DB_PREFIX."paie_bulletin");

		// Insert lines
		foreach ($this->lignes as $ligne) {
			$ligne->fk_bulletin = $this->id;
			$res = $this->insertLine($ligne);
			if ($res < 0) {
				$this->db->rollback();
				return -3;
			}
		}

		$this->db->commit();
		return $this->id;
	}

	/**
	 * Insert a single line into database.
	 *
	 * @param  PaieBulletinLigne $ligne Line
	 * @return int                      Id if OK, <0 if KO
	 */
	public function insertLine($ligne)
	{
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."paie_bulletin_ligne(";
		$sql .= "fk_bulletin, fk_rubrique, ref, label, type, categorie, nombre, base, taux_salarial, montant_salarial,";
		$sql .= " taux_patronal, montant_patronal, sens, soumis, imposable, position";
		$sql .= ") VALUES (";
		$sql .= ((int) $ligne->fk_bulletin);
		$sql .= ", ".($ligne->fk_rubrique ? ((int) $ligne->fk_rubrique) : "NULL");
		$sql .= ", ".($ligne->ref ? "'".$this->db->escape($ligne->ref)."'" : "NULL");
		$sql .= ", '".$this->db->escape($ligne->label)."'";
		$sql .= ", '".$this->db->escape($ligne->type)."'";
		$sql .= ", '".$this->db->escape($ligne->categorie)."'";
		$sql .= ", ".((float) price2num($ligne->nombre));
		$sql .= ", ".((float) price2num($ligne->base));
		$sql .= ", ".((float) price2num($ligne->taux_salarial));
		$sql .= ", ".((float) price2num($ligne->montant_salarial));
		$sql .= ", ".((float) price2num($ligne->taux_patronal));
		$sql .= ", ".((float) price2num($ligne->montant_patronal));
		$sql .= ", '".$this->db->escape($ligne->sens ? $ligne->sens : 'plus')."'";
		$sql .= ", ".((int) $ligne->soumis);
		$sql .= ", ".((int) $ligne->imposable);
		$sql .= ", ".((int) $ligne->position);
		$sql .= ")";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$ligne->rowid = $this->db->last_insert_id(MAIN_DB_PREFIX."paie_bulletin_ligne");
		return $ligne->rowid;
	}

	/**
	 * Load bulletin in memory from database.
	 *
	 * @param  int    $id  Id
	 * @param  string $ref Ref (used if $id empty)
	 * @return int         >0 if OK, 0 if not found, <0 if KO
	 */
	public function fetch($id, $ref = '')
	{
		$sql = "SELECT rowid, entity, ref, fk_user, fk_contrat, date_debut, date_fin, date_paiement, salaire_base, heures, taux_horaire,";
		$sql .= " plafond_ss, brut, total_cot_sal, total_cot_pat, net_a_payer, net_imposable, net_social, cout_employeur,";
		$sql .= " cumul_brut, cumul_net_imp, cumul_net_social, conges_acquis, conges_pris, conges_solde,";
		$sql .= " status, fk_bank, note_public, note_private, model_pdf, last_main_doc, date_creation, date_validation, fk_user_valid";
		$sql .= " FROM ".MAIN_DB_PREFIX."paie_bulletin";
		if ($id) {
			$sql .= " WHERE rowid = ".((int) $id);
		} else {
			$sql .= " WHERE ref = '".$this->db->escape($ref)."' AND entity IN (".getEntity('paie_bulletin').")";
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
		$this->fk_user = $obj->fk_user;
		$this->fk_contrat = $obj->fk_contrat;
		$this->date_debut = $this->db->jdate($obj->date_debut);
		$this->date_fin = $this->db->jdate($obj->date_fin);
		$this->date_paiement = $this->db->jdate($obj->date_paiement);
		$this->salaire_base = $obj->salaire_base;
		$this->heures = $obj->heures;
		$this->taux_horaire = $obj->taux_horaire;
		$this->plafond_ss = $obj->plafond_ss;
		$this->brut = $obj->brut;
		$this->total_cot_sal = $obj->total_cot_sal;
		$this->total_cot_pat = $obj->total_cot_pat;
		$this->net_a_payer = $obj->net_a_payer;
		$this->net_imposable = $obj->net_imposable;
		$this->net_social = $obj->net_social;
		$this->cout_employeur = $obj->cout_employeur;
		$this->cumul_brut = $obj->cumul_brut;
		$this->cumul_net_imp = $obj->cumul_net_imp;
		$this->cumul_net_social = $obj->cumul_net_social;
		$this->conges_acquis = $obj->conges_acquis;
		$this->conges_pris = $obj->conges_pris;
		$this->conges_solde = $obj->conges_solde;
		$this->status = $obj->status;
		$this->fk_bank = $obj->fk_bank;
		$this->note_public = $obj->note_public;
		$this->note_private = $obj->note_private;
		$this->model_pdf = $obj->model_pdf;
		$this->last_main_doc = $obj->last_main_doc;
		$this->date_creation = $this->db->jdate($obj->date_creation);
		$this->date_validation = $this->db->jdate($obj->date_validation);
		$this->fk_user_valid = $obj->fk_user_valid;

		$this->fetchLines();
		return 1;
	}

	/**
	 * Load lines of the bulletin into $this->lignes.
	 *
	 * @return int Number of lines, or <0 if KO
	 */
	public function fetchLines()
	{
		$this->lignes = array();
		$sql = "SELECT rowid, fk_bulletin, fk_rubrique, ref, label, type, categorie, nombre, base, taux_salarial, montant_salarial,";
		$sql .= " taux_patronal, montant_patronal, sens, soumis, imposable, position";
		$sql .= " FROM ".MAIN_DB_PREFIX."paie_bulletin_ligne";
		$sql .= " WHERE fk_bulletin = ".((int) $this->id);
		$sql .= " ORDER BY position ASC, rowid ASC";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$this->lignes[] = new PaieBulletinLigne($obj);
		}
		return count($this->lignes);
	}

	/**
	 * Load salarié (user) and contrat into memory.
	 *
	 * @return int >0 if OK
	 */
	public function fetchSalarie()
	{
		$u = new User($this->db);
		if ($u->fetch($this->fk_user) > 0) {
			$this->salarie = $u;
		}
		if ($this->fk_contrat) {
			$c = new PaieContrat($this->db);
			if ($c->fetch($this->fk_contrat) > 0) {
				$this->contrat = $c;
			}
		}
		return 1;
	}

	/**
	 * Pre-fill the bulletin lines from a contrat and the active rubrique catalog.
	 * The base salary line uses the contrat monthly base salary.
	 *
	 * @param  PaieContrat $contrat Employee contract
	 * @return int                  >0 if OK, <0 if KO
	 */
	public function buildFromContrat(PaieContrat $contrat)
	{
		$this->fk_user = $contrat->fk_user;
		$this->fk_contrat = $contrat->id;
		$this->salaire_base = $contrat->salaire_base;
		$this->heures = $contrat->temps_travail ? $contrat->temps_travail : 151.67;
		$this->taux_horaire = $contrat->taux_horaire;
		if (empty($this->plafond_ss)) {
			$this->plafond_ss = self::getPMSS();
		}

		$rub = new PaieRubrique($this->db);
		$rubriques = $rub->fetchAll(1); // active only
		if (!is_array($rubriques)) {
			$this->error = $rub->error;
			return -1;
		}

		$this->lignes = array();
		foreach ($rubriques as $r) {
			// Skip employer-only "cadre" contributions for non-cadre if relevant (APEC).
			if ($r->ref == 'CHOMAGE_APEC' && $contrat->categorie != 'cadre') {
				continue;
			}
			$l = new PaieBulletinLigne();
			$l->fk_rubrique = $r->id;
			$l->ref = $r->ref;
			$l->label = $r->label;
			$l->type = $r->type;
			$l->categorie = $r->categorie;
			$l->taux_salarial = $r->taux_salarial;
			$l->taux_patronal = $r->taux_patronal;
			$l->sens = $r->sens;
			$l->soumis = $r->soumis;
			$l->imposable = $r->imposable;
			$l->position = $r->position;

			if ($r->type == 'gain') {
				// Base salary line = contract base salary; others start at 0.
				if ($r->ref == 'SALBASE') {
					$l->base = $contrat->salaire_base;
					$l->nombre = $this->heures;
				} else {
					$l->base = 0;
				}
			} else {
				// Cotisation: base computed later in computeTotals.
				$l->base = ($r->base_type == 'fixe') ? $r->base_fixe : 0;
				// Remember base_type via a transient property for recompute.
				$l->base_type = $r->base_type;
				$l->base_fixe = $r->base_fixe;
			}
			$this->lignes[] = $l;
		}

		$this->computeTotals();
		return 1;
	}

	/**
	 * Compute the assiette (base) for a cotisation line given brut and plafond.
	 *
	 * @param  string $base_type brut, plafond_ss, tranche2, csg, fixe, manuel
	 * @param  float  $brut      Gross salary subject to contributions
	 * @param  float  $plafond   Monthly SS ceiling
	 * @param  float  $base_fixe Fixed base if base_type = fixe
	 * @param  float  $current   Current stored base (used for manuel)
	 * @return float
	 */
	public static function computeBase($base_type, $brut, $plafond, $base_fixe = 0, $current = 0)
	{
		switch ($base_type) {
			case 'plafond_ss':
				return min($brut, $plafond);
			case 'tranche2':
				return max(0, min($brut, 8 * $plafond) - $plafond);
			case 'csg':
				// Assiette CSG/CRDS : 98,25% du brut (abattement 1,75% pour frais professionnels).
				return round($brut * 0.9825, 2);
			case 'fixe':
				return $base_fixe;
			case 'manuel':
				return $current;
			case 'brut':
			default:
				return $brut;
		}
	}

	/**
	 * Recompute all line amounts and header totals following the French payslip rules.
	 *
	 * @return void
	 */
	public function computeTotals()
	{
		$plafond = $this->plafond_ss ? $this->plafond_ss : self::getPMSS();

		// 1) Compute brut = sum of gain lines subject to contributions.
		$brut = 0;
		foreach ($this->lignes as $l) {
			if ($l->type == 'gain' && $l->soumis) {
				$brut += (float) $l->base;
			}
		}
		$this->brut = round($brut, 2);
		$this->salaire_base = $this->salaire_base ? $this->salaire_base : $brut;

		// 2) Compute each cotisation line base and amounts.
		$total_cot_sal = 0;
		$total_cot_pat = 0;
		$sal_deductible = 0;      // salarial contributions deductible from taxable income
		$reintegration = 0;       // employer-paid benefits reintegrated in taxable income
		$gains_non_soumis = 0;    // net gains not subject to contributions (sens plus)
		$retenues = 0;            // deductions from net (sens moins on gain lines)

		foreach ($this->lignes as $l) {
			if ($l->type == 'gain') {
				if (!$l->soumis) {
					if ($l->sens == 'moins') {
						$retenues += (float) $l->base;
					} else {
						$gains_non_soumis += (float) $l->base;
					}
				}
				continue;
			}
			if ($l->type != 'cotisation') {
				continue;
			}

			$base_type = isset($l->base_type) ? $l->base_type : $this->guessBaseType($l);
			$base_fixe = isset($l->base_fixe) ? $l->base_fixe : 0;
			$l->base = self::computeBase($base_type, $this->brut, $plafond, $base_fixe, $l->base);

			$l->montant_salarial = round($l->base * (float) $l->taux_salarial / 100, 2);
			$l->montant_patronal = round($l->base * (float) $l->taux_patronal / 100, 2);

			// Employer reductions (allègements) reduce employer cost.
			$signe_pat = ($l->sens == 'moins') ? -1 : 1;

			$total_cot_sal += $l->montant_salarial;
			$total_cot_pat += $signe_pat * $l->montant_patronal;

			// Deductibility from taxable income (CSG déductible + all classic contributions).
			if ($l->imposable) {
				$sal_deductible += $l->montant_salarial;
			}

			// Employer-funded complementary health = taxable benefit reintegrated.
			if ($l->categorie == 'sante' && strpos($l->ref, 'MUTUELLE') !== false) {
				$reintegration += $l->montant_patronal;
			}
		}

		$this->total_cot_sal = round($total_cot_sal, 2);
		$this->total_cot_pat = round($total_cot_pat, 2);

		// 3) Net amounts.
		$this->net_a_payer = round($this->brut - $this->total_cot_sal + $gains_non_soumis - $retenues, 2);
		$this->net_social = round($this->brut - $this->total_cot_sal, 2);
		$this->net_imposable = round($this->brut - $sal_deductible + $reintegration, 2);
		$this->cout_employeur = round($this->brut + $this->total_cot_pat, 2);

		// 4) Cumuls annuels (bulletins validés de la même année).
		$this->computeCumuls();
	}

	/**
	 * Guess base type for a line by reloading its rubrique (fallback when not set).
	 *
	 * @param  PaieBulletinLigne $l Line
	 * @return string
	 */
	private function guessBaseType($l)
	{
		if (empty($l->fk_rubrique)) {
			return 'manuel';
		}
		$r = new PaieRubrique($this->db);
		if ($r->fetch($l->fk_rubrique) > 0) {
			$l->base_fixe = $r->base_fixe;
			return $r->base_type;
		}
		return 'manuel';
	}

	/**
	 * Compute annual cumulated amounts (previous validated bulletins + current).
	 *
	 * @return void
	 */
	public function computeCumuls()
	{
		$year = $this->date_debut ? dol_print_date($this->date_debut, '%Y') : dol_print_date(dol_now(), '%Y');
		$start = $year.'-01-01';

		$sql = "SELECT SUM(brut) as b, SUM(net_imposable) as ni, SUM(net_social) as ns";
		$sql .= " FROM ".MAIN_DB_PREFIX."paie_bulletin";
		$sql .= " WHERE fk_user = ".((int) $this->fk_user);
		$sql .= " AND entity IN (".getEntity('paie_bulletin').")";
		$sql .= " AND status >= ".self::STATUS_VALIDATED;
		$sql .= " AND date_debut >= '".$this->db->escape($start)."'";
		if (!empty($this->id)) {
			$sql .= " AND rowid <> ".((int) $this->id);
		}
		$resql = $this->db->query($sql);
		$prev_b = $prev_ni = $prev_ns = 0;
		if ($resql && $this->db->num_rows($resql)) {
			$obj = $this->db->fetch_object($resql);
			$prev_b = (float) $obj->b;
			$prev_ni = (float) $obj->ni;
			$prev_ns = (float) $obj->ns;
		}
		$this->cumul_brut = round($prev_b + $this->brut, 2);
		$this->cumul_net_imp = round($prev_ni + $this->net_imposable, 2);
		$this->cumul_net_social = round($prev_ns + $this->net_social, 2);
	}

	/**
	 * Update header (and recompute totals) in database.
	 *
	 * @param  User $user User that modifies
	 * @return int        >0 if OK, <0 if KO
	 */
	public function update(User $user)
	{
		$this->computeTotals();

		$sql = "UPDATE ".MAIN_DB_PREFIX."paie_bulletin SET";
		$sql .= " fk_user = ".((int) $this->fk_user);
		$sql .= ", fk_contrat = ".($this->fk_contrat ? ((int) $this->fk_contrat) : "NULL");
		$sql .= ", date_debut = '".$this->db->idate($this->date_debut)."'";
		$sql .= ", date_fin = '".$this->db->idate($this->date_fin)."'";
		$sql .= ", date_paiement = ".($this->date_paiement ? "'".$this->db->idate($this->date_paiement)."'" : "NULL");
		$sql .= ", salaire_base = ".((float) price2num($this->salaire_base));
		$sql .= ", heures = ".((float) price2num($this->heures));
		$sql .= ", taux_horaire = ".((float) price2num($this->taux_horaire));
		$sql .= ", plafond_ss = ".((float) price2num($this->plafond_ss));
		$sql .= ", brut = ".((float) price2num($this->brut));
		$sql .= ", total_cot_sal = ".((float) price2num($this->total_cot_sal));
		$sql .= ", total_cot_pat = ".((float) price2num($this->total_cot_pat));
		$sql .= ", net_a_payer = ".((float) price2num($this->net_a_payer));
		$sql .= ", net_imposable = ".((float) price2num($this->net_imposable));
		$sql .= ", net_social = ".((float) price2num($this->net_social));
		$sql .= ", cout_employeur = ".((float) price2num($this->cout_employeur));
		$sql .= ", cumul_brut = ".((float) price2num($this->cumul_brut));
		$sql .= ", cumul_net_imp = ".((float) price2num($this->cumul_net_imp));
		$sql .= ", cumul_net_social = ".((float) price2num($this->cumul_net_social));
		$sql .= ", conges_acquis = ".((float) price2num($this->conges_acquis));
		$sql .= ", conges_pris = ".((float) price2num($this->conges_pris));
		$sql .= ", conges_solde = ".((float) price2num($this->conges_solde));
		$sql .= ", note_public = ".($this->note_public ? "'".$this->db->escape($this->note_public)."'" : "NULL");
		$sql .= ", note_private = ".($this->note_private ? "'".$this->db->escape($this->note_private)."'" : "NULL");
		$sql .= ", model_pdf = '".$this->db->escape($this->model_pdf ? $this->model_pdf : 'paiestandard')."'";
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
	 * Replace all lines in database with the current $this->lignes.
	 *
	 * @return int >0 if OK, <0 if KO
	 */
	public function saveLines()
	{
		$this->db->begin();
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."paie_bulletin_ligne WHERE fk_bulletin = ".((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		foreach ($this->lignes as $l) {
			$l->fk_bulletin = $this->id;
			if ($this->insertLine($l) < 0) {
				$this->db->rollback();
				return -2;
			}
		}
		$this->db->commit();
		return 1;
	}

	/**
	 * Validate the bulletin (assign definitive ref and set status).
	 *
	 * @param  User $user User that validates
	 * @return int        >0 if OK, <0 if KO
	 */
	public function validate(User $user)
	{
		if ($this->status != self::STATUS_DRAFT) {
			$this->error = 'AlreadyValidated';
			return 0;
		}
		$this->computeTotals();

		$this->db->begin();
		$now = dol_now();
		$sql = "UPDATE ".MAIN_DB_PREFIX."paie_bulletin SET";
		$sql .= " status = ".self::STATUS_VALIDATED;
		$sql .= ", date_validation = '".$this->db->idate($now)."'";
		$sql .= ", fk_user_valid = ".((int) $user->id);
		$sql .= ", brut = ".((float) price2num($this->brut));
		$sql .= ", total_cot_sal = ".((float) price2num($this->total_cot_sal));
		$sql .= ", total_cot_pat = ".((float) price2num($this->total_cot_pat));
		$sql .= ", net_a_payer = ".((float) price2num($this->net_a_payer));
		$sql .= ", net_imposable = ".((float) price2num($this->net_imposable));
		$sql .= ", net_social = ".((float) price2num($this->net_social));
		$sql .= ", cout_employeur = ".((float) price2num($this->cout_employeur));
		$sql .= ", cumul_brut = ".((float) price2num($this->cumul_brut));
		$sql .= ", cumul_net_imp = ".((float) price2num($this->cumul_net_imp));
		$sql .= ", cumul_net_social = ".((float) price2num($this->cumul_net_social));
		$sql .= " WHERE rowid = ".((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		$this->status = self::STATUS_VALIDATED;
		$this->date_validation = $now;
		$this->fk_user_valid = $user->id;
		$this->db->commit();
		return 1;
	}

	/**
	 * Set the bulletin as paid.
	 * If a bank account is configured (MODULEPAIE_BANK_ACCOUNT) and the auto
	 * bank entry option is on (MODULEPAIE_AUTO_BANK, default on), a bank
	 * transaction of -net_a_payer is written on the account and linked to the
	 * bulletin (like invoice payments do).
	 *
	 * @param  User $user User
	 * @return int        >0 if OK, <0 if KO
	 */
	public function setPaid(User $user)
	{
		global $conf, $langs;

		$this->db->begin();

		$bankline = 0;
		$accountid = (int) getDolGlobalString('MODULEPAIE_BANK_ACCOUNT');
		$autobank = getDolGlobalString('MODULEPAIE_AUTO_BANK', '1');
		if ($accountid > 0 && $autobank && empty($this->fk_bank) && (isModEnabled('banque') || isModEnabled('bank'))) {
			require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
			$acc = new Account($this->db);
			if ($acc->fetch($accountid) > 0) {
				$this->fetchSalarie();
				$label = $langs->trans("Salaire").' '.$this->ref;
				if ($this->salarie) {
					$label .= ' - '.$this->salarie->getFullName($langs);
				}
				$datepaie = $this->date_paiement ? $this->date_paiement : dol_now();
				// Negative amount: money leaves the account (net to pay).
				$bankline = $acc->addline($datepaie, 'VIR', $label, -1 * abs((float) $this->net_a_payer), '', 0, $user);
				if ($bankline <= 0) {
					$this->error = $acc->error ? $acc->error : 'ErrorCreatingBankLine';
					$this->db->rollback();
					return -2;
				}
			}
		}

		$sql = "UPDATE ".MAIN_DB_PREFIX."paie_bulletin SET status = ".self::STATUS_PAID;
		if ($bankline > 0) {
			$sql .= ", fk_bank = ".((int) $bankline);
		}
		$sql .= " WHERE rowid = ".((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		$this->status = self::STATUS_PAID;
		if ($bankline > 0) {
			$this->fk_bank = $bankline;
		}
		$this->db->commit();
		return 1;
	}

	/**
	 * Set back to draft.
	 * If a bank transaction was created when the bulletin was paid, it is
	 * removed so accounting stays consistent (unless already reconciled).
	 *
	 * @param  User $user User
	 * @return int        >0 if OK, <0 if KO
	 */
	public function setDraft(User $user)
	{
		$this->db->begin();

		// Remove the linked bank line if any.
		if (!empty($this->fk_bank) && (isModEnabled('banque') || isModEnabled('bank'))) {
			require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
			$bl = new AccountLine($this->db);
			if ($bl->fetch($this->fk_bank) > 0) {
				if (!empty($bl->rappro)) {
					// Reconciled bank line: refuse to silently break accounting.
					$this->error = 'BankLineReconciled';
					$this->db->rollback();
					return -3;
				}
				if ($bl->delete($user) < 0) {
					$this->error = $bl->error;
					$this->db->rollback();
					return -2;
				}
			}
		}

		$sql = "UPDATE ".MAIN_DB_PREFIX."paie_bulletin SET status = ".self::STATUS_DRAFT.", date_validation = NULL, fk_user_valid = NULL, fk_bank = NULL WHERE rowid = ".((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		$this->status = self::STATUS_DRAFT;
		$this->fk_bank = null;
		$this->db->commit();
		return 1;
	}

	/**
	 * Delete bulletin and its lines.
	 *
	 * @param  User $user User that deletes
	 * @return int        >0 if OK, <0 if KO
	 */
	public function delete(User $user)
	{
		$this->db->begin();
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."paie_bulletin_ligne WHERE fk_bulletin = ".((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."paie_bulletin WHERE rowid = ".((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -2;
		}
		$this->db->commit();
		return 1;
	}

	/**
	 * Return list of bulletins.
	 *
	 * @param  int $fk_user Filter on employee (0 = all)
	 * @param  int $status  Filter on status (-1 = all)
	 * @return array|int    Array of stdClass rows, or <0 if KO
	 */
	public function fetchAll($fk_user = 0, $status = -1)
	{
		$result = array();
		$sql = "SELECT rowid, ref, fk_user, date_debut, date_fin, brut, total_cot_sal, net_a_payer, net_imposable, net_social, status";
		$sql .= " FROM ".MAIN_DB_PREFIX."paie_bulletin";
		$sql .= " WHERE entity IN (".getEntity('paie_bulletin').")";
		if ($fk_user > 0) {
			$sql .= " AND fk_user = ".((int) $fk_user);
		}
		if ($status >= 0) {
			$sql .= " AND status = ".((int) $status);
		}
		$sql .= " ORDER BY date_debut DESC, rowid DESC";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$result[] = $obj;
		}
		return $result;
	}

	/**
	 * Return the label of a status.
	 *
	 * @param  int $mode 0=long label, 3=picto only, 5=short+picto, 6=long+picto
	 * @return string
	 */
	public function getLibStatut($mode = 0)
	{
		global $langs;
		$labels = array(
			self::STATUS_DRAFT => $langs->trans('StatusDraft'),
			self::STATUS_VALIDATED => $langs->trans('StatusValidated'),
			self::STATUS_PAID => $langs->trans('StatusPaid'),
		);
		$statusType = array(
			self::STATUS_DRAFT => 'status0',
			self::STATUS_VALIDATED => 'status4',
			self::STATUS_PAID => 'status6',
		);
		$label = isset($labels[$this->status]) ? $labels[$this->status] : $this->status;
		$type = isset($statusType[$this->status]) ? $statusType[$this->status] : 'status0';
		if (function_exists('dolGetStatus')) {
			return dolGetStatus($label, $label, '', $type, $mode);
		}
		return $label;
	}

	/**
	 * Return clickable name (link).
	 *
	 * @param  int $withpicto 0=no picto, 1=with picto
	 * @return string HTML link
	 */
	public function getNomUrl($withpicto = 0)
	{
		$url = dol_buildpath('/modulepaie/bulletin_card.php', 1).'?id='.$this->id;
		$result = '<a href="'.$url.'">';
		if ($withpicto) {
			$result .= img_object('', $this->picto, 'class="paddingright"');
		}
		$result .= dol_escape_htmltag($this->ref);
		$result .= '</a>';
		return $result;
	}

	/**
	 * Generate the PDF document for this bulletin.
	 *
	 * @param  string     $modele  Force model
	 * @param  Translate  $outputlangs Output language
	 * @return int                 >0 if OK, <0 if KO
	 */
	public function generateDocument($modele = '', $outputlangs = null)
	{
		global $conf, $langs, $user;

		if (empty($modele)) {
			$modele = $this->model_pdf ? $this->model_pdf : 'paiestandard';
		}
		if (empty($outputlangs)) {
			$outputlangs = $langs;
		}

		$file = dol_buildpath('/modulepaie/core/modules/modulepaie/doc/pdf_'.$modele.'.modules.php');
		if (!file_exists($file)) {
			$this->error = 'PDF model not found: '.$modele;
			return -1;
		}
		require_once $file;
		$classname = 'pdf_'.$modele;
		$obj = new $classname($this->db);

		$this->fetchSalarie();
		$result = $obj->write_file($this, $outputlangs);
		if ($result <= 0) {
			$this->error = $obj->error;
			return -1;
		}
		return 1;
	}
}
