<?php
/* Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    class/bulletinligne.class.php
 * \ingroup modulepaie
 * \brief   Ligne (rubrique) d'un bulletin de paie.
 */

/**
 * Classe représentant une ligne de bulletin de paie.
 * Volontairement légère : la persistance est gérée par PaieBulletin.
 */
class PaieBulletinLigne
{
	public $rowid;
	public $fk_bulletin;
	public $fk_rubrique;
	public $ref;
	public $label;
	public $type;        // gain, cotisation, net, info
	public $categorie;   // gain, sante, atmp, retraite, famille, chomage, csgcrds, autres, allegement, net
	public $nombre;      // nombre d'heures/unités (facultatif)
	public $base;        // assiette / montant du gain
	public $taux_salarial;
	public $montant_salarial;
	public $taux_patronal;
	public $montant_patronal;
	public $sens;        // plus, moins
	public $soumis;      // 1 si soumis à cotisations (entre dans le brut)
	public $imposable;   // 1 si déductible du net imposable (pour cotisations)
	public $position;
	/** @var string Mode de calcul de la base (transient, hérité de la rubrique) */
	public $base_type;
	/** @var float Base fixe (transient, hérité de la rubrique) */
	public $base_fixe;

	/**
	 * Constructor. Optionally hydrate from a stdClass DB row.
	 *
	 * @param object|null $obj Row object from database
	 */
	public function __construct($obj = null)
	{
		if (is_object($obj)) {
			$this->rowid = $obj->rowid;
			$this->fk_bulletin = $obj->fk_bulletin;
			$this->fk_rubrique = $obj->fk_rubrique;
			$this->ref = $obj->ref;
			$this->label = $obj->label;
			$this->type = $obj->type;
			$this->categorie = $obj->categorie;
			$this->nombre = $obj->nombre;
			$this->base = $obj->base;
			$this->taux_salarial = $obj->taux_salarial;
			$this->montant_salarial = $obj->montant_salarial;
			$this->taux_patronal = $obj->taux_patronal;
			$this->montant_patronal = $obj->montant_patronal;
			$this->sens = $obj->sens;
			$this->soumis = $obj->soumis;
			$this->imposable = $obj->imposable;
			$this->position = $obj->position;
		} else {
			$this->nombre = 0;
			$this->base = 0;
			$this->taux_salarial = 0;
			$this->montant_salarial = 0;
			$this->taux_patronal = 0;
			$this->montant_patronal = 0;
			$this->sens = 'plus';
			$this->soumis = 1;
			$this->imposable = 1;
			$this->position = 100;
		}
	}
}
