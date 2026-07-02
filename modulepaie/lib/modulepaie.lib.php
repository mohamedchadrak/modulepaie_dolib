<?php
/* Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    lib/modulepaie.lib.php
 * \ingroup modulepaie
 * \brief   Fonctions communes du module Paie.
 */

/**
 * Return the tabs head for the admin/setup pages of the module.
 *
 * @return array Array of tabs
 */
function modulepaieAdminPrepareHead()
{
	global $langs, $conf;
	$langs->load("modulepaie@modulepaie");

	$h = 0;
	$head = array();
	$head[$h][0] = dol_buildpath("/modulepaie/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'modulepaie@modulepaie');
	complete_head_from_modules($conf, $langs, null, $head, $h, 'modulepaie@modulepaie', 'remove');

	return $head;
}

/**
 * Return the tabs head for a bulletin card.
 *
 * @param  PaieBulletin $object Bulletin
 * @return array                Array of tabs
 */
function bulletinPrepareHead($object)
{
	global $langs, $conf;
	$langs->load("modulepaie@modulepaie");

	$h = 0;
	$head = array();
	$head[$h][0] = dol_buildpath("/modulepaie/bulletin_card.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("BulletinPaie");
	$head[$h][2] = 'card';
	$h++;

	$head[$h][0] = dol_buildpath("/modulepaie/bulletin_document.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("Documents");
	$head[$h][2] = 'documents';
	$h++;

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'bulletin@modulepaie');
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'bulletin@modulepaie', 'remove');

	return $head;
}

/**
 * Return the tabs head for a salarié card.
 *
 * @param  PaieContrat $object Contrat
 * @return array               Array of tabs
 */
function salariePrepareHead($object)
{
	global $langs, $conf;
	$langs->load("modulepaie@modulepaie");

	$h = 0;
	$head = array();
	$head[$h][0] = dol_buildpath("/modulepaie/salarie_card.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("Salarie");
	$head[$h][2] = 'card';
	$h++;

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'salarie@modulepaie');
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'salarie@modulepaie', 'remove');

	return $head;
}

/**
 * Return the translated label of a rubrique category.
 *
 * @param  string $categorie Category code
 * @return string            Translated label
 */
function modulepaieCategorieLabel($categorie)
{
	global $langs;
	$map = array(
		'gain' => 'CatGain',
		'sante' => 'CatSante',
		'atmp' => 'CatAtmp',
		'retraite' => 'CatRetraite',
		'famille' => 'CatFamille',
		'chomage' => 'CatChomage',
		'csgcrds' => 'CatCsgCrds',
		'autres' => 'CatAutres',
		'allegement' => 'CatAllegement',
		'net' => 'CatNet',
	);
	return isset($map[$categorie]) ? $langs->trans($map[$categorie]) : $categorie;
}

/**
 * Ordered list of legal categories for the clarified payslip.
 *
 * @return array
 */
function modulepaieOrderedCategories()
{
	return array('sante', 'atmp', 'retraite', 'famille', 'chomage', 'csgcrds', 'autres', 'allegement');
}
