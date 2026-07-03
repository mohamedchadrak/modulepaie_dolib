<?php
/* Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    rubrique_card.php
 * \ingroup modulepaie
 * \brief   Fiche rubrique de paie (création / édition).
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

global $conf, $db, $langs, $user;

dol_include_once('/modulepaie/class/rubrique.class.php');
dol_include_once('/modulepaie/lib/modulepaie.lib.php');
$langs->loadLangs(array("modulepaie@modulepaie"));

if (empty($user->rights->modulepaie->config->write)) {
	accessforbidden();
}

$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');

$object = new PaieRubrique($db);
$form = new Form($db);
if ($id > 0) {
	$object->fetch($id);
}

$types = array('gain' => $langs->trans('Gain'), 'cotisation' => $langs->trans('Cotisation'));
$categories = array(
	'gain' => $langs->trans('CatGain'),
	'sante' => $langs->trans('CatSante'),
	'atmp' => $langs->trans('CatAtmp'),
	'retraite' => $langs->trans('CatRetraite'),
	'famille' => $langs->trans('CatFamille'),
	'chomage' => $langs->trans('CatChomage'),
	'csgcrds' => $langs->trans('CatCsgCrds'),
	'autres' => $langs->trans('CatAutres'),
	'allegement' => $langs->trans('CatAllegement'),
);
$basetypes = array(
	'brut' => $langs->trans('BaseTypeBrut'),
	'plafond_ss' => $langs->trans('BaseTypePlafond'),
	'tranche2' => $langs->trans('BaseTypeTranche2'),
	'csg' => $langs->trans('BaseTypeCsg'),
	'fixe' => $langs->trans('BaseTypeFixe'),
	'manuel' => $langs->trans('BaseTypeManuel'),
);
$sens = array('plus' => $langs->trans('SensPlus'), 'moins' => $langs->trans('SensMoins'));

/*
 * Actions
 */
function hydrateFromPost($object)
{
	$object->ref = GETPOST('ref', 'alpha');
	$object->label = GETPOST('label', 'alpha');
	$object->type = GETPOST('type', 'alpha');
	$object->categorie = GETPOST('categorie', 'alpha');
	$object->base_type = GETPOST('base_type', 'alpha');
	$object->base_fixe = price2num(GETPOST('base_fixe', 'alpha'));
	$object->taux_salarial = price2num(GETPOST('taux_salarial', 'alpha'));
	$object->taux_patronal = price2num(GETPOST('taux_patronal', 'alpha'));
	$object->sens = GETPOST('sens', 'alpha');
	$object->soumis = GETPOST('soumis', 'int') ? 1 : 0;
	$object->imposable = GETPOST('imposable', 'int') ? 1 : 0;
	$object->position = GETPOST('position', 'int');
	$object->active = GETPOST('active', 'int') ? 1 : 0;
	$object->note = GETPOST('note', 'restricthtml');
	return $object;
}

if ($action == 'add') {
	$object = hydrateFromPost($object);
	$result = $object->create($user);
	if ($result > 0) {
		setEventMessages($langs->trans("RubriqueCree"), null, 'mesgs');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$result);
		exit;
	} else {
		setEventMessages($object->error, null, 'errors');
		$action = 'create';
	}
}
if ($action == 'update' && $id > 0) {
	$object = hydrateFromPost($object);
	$object->id = $id;
	if ($object->update($user) > 0) {
		setEventMessages($langs->trans("RubriqueModifiee"), null, 'mesgs');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id);
		exit;
	} else {
		setEventMessages($object->error, null, 'errors');
	}
}
if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' && $id > 0) {
	if ($object->delete($user) > 0) {
		setEventMessages($langs->trans("RubriqueSupprimee"), null, 'mesgs');
		header("Location: ".dol_buildpath('/modulepaie/rubrique_list.php', 1));
		exit;
	}
}

/*
 * View
 */
llxHeader('', $langs->trans("Rubrique"));

if ($action == 'create' || ($action == 'edit' && $id > 0)) {
	$isnew = ($action == 'create');
	print load_fiche_titre($isnew ? $langs->trans("NewRubrique") : $langs->trans("Rubrique"), '', 'generic');
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].($isnew ? '' : '?id='.$id).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="'.($isnew ? 'add' : 'update').'">';

	print dol_get_fiche_head();
	print '<table class="border centpercent">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans("RefRubrique").'</td><td><input type="text" name="ref" value="'.dol_escape_htmltag($object->ref).'" '.($isnew ? '' : '').'></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans("LabelRubrique").'</td><td><input type="text" name="label" class="minwidth300" value="'.dol_escape_htmltag($object->label).'"></td></tr>';
	print '<tr><td>'.$langs->trans("TypeRubrique").'</td><td>'.$form->selectarray('type', $types, $object->type ? $object->type : 'cotisation').'</td></tr>';
	print '<tr><td>'.$langs->trans("CategorieRubrique").'</td><td>'.$form->selectarray('categorie', $categories, $object->categorie ? $object->categorie : 'autres').'</td></tr>';
	print '<tr><td>'.$langs->trans("BaseType").'</td><td>'.$form->selectarray('base_type', $basetypes, $object->base_type ? $object->base_type : 'brut').'</td></tr>';
	print '<tr><td>'.$langs->trans("BaseFixe").'</td><td><input type="text" name="base_fixe" class="width100" value="'.dol_escape_htmltag($object->base_fixe).'"></td></tr>';
	print '<tr><td>'.$langs->trans("TauxSalarial").'</td><td><input type="text" name="taux_salarial" class="width75" value="'.dol_escape_htmltag($object->taux_salarial).'"> %</td></tr>';
	print '<tr><td>'.$langs->trans("TauxPatronal").'</td><td><input type="text" name="taux_patronal" class="width75" value="'.dol_escape_htmltag($object->taux_patronal).'"> %</td></tr>';
	print '<tr><td>'.$langs->trans("Sens").'</td><td>'.$form->selectarray('sens', $sens, $object->sens ? $object->sens : 'plus').'</td></tr>';
	print '<tr><td>'.$langs->trans("Soumis").'</td><td><input type="checkbox" name="soumis" value="1" '.((!$id || $object->soumis) ? 'checked' : '').'></td></tr>';
	print '<tr><td>'.$langs->trans("Imposable").'</td><td><input type="checkbox" name="imposable" value="1" '.((!$id || $object->imposable) ? 'checked' : '').'></td></tr>';
	print '<tr><td>'.$langs->trans("Position").'</td><td><input type="text" name="position" class="width75" value="'.dol_escape_htmltag($object->position ? $object->position : 100).'"></td></tr>';
	print '<tr><td>'.$langs->trans("Active").'</td><td><input type="checkbox" name="active" value="1" '.((!$id || $object->active) ? 'checked' : '').'></td></tr>';
	print '<tr><td>'.$langs->trans("Note").'</td><td><textarea name="note" class="quatrevingtpercent" rows="2">'.dol_escape_htmltag($object->note).'</textarea></td></tr>';
	print '</table>';
	print dol_get_fiche_end();

	print '<div class="center">';
	print '<input type="submit" class="button button-save" value="'.($isnew ? $langs->trans("Create") : $langs->trans("Save")).'">';
	print ' &nbsp; <a class="button button-cancel" href="'.dol_buildpath('/modulepaie/rubrique_list.php', 1).'">'.$langs->trans("Cancel").'</a>';
	print '</div>';
	print '</form>';
} elseif ($id > 0) {
	print load_fiche_titre($langs->trans("Rubrique").' - '.dol_escape_htmltag($object->ref), '', 'generic');

	if ($action == 'delete') {
		print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$id, $langs->trans("Delete"), $langs->trans("ConfirmDeleteBulletin"), 'confirm_delete', '', 0, 1);
	}

	$linkback = '<a href="'.dol_buildpath('/modulepaie/rubrique_list.php', 1).'">'.$langs->trans("BackToList").'</a>';
	print dol_get_fiche_head();
	print '<div class="refidno">'.$linkback.'</div>';
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">'.$langs->trans("RefRubrique").'</td><td>'.dol_escape_htmltag($object->ref).'</td></tr>';
	print '<tr><td>'.$langs->trans("LabelRubrique").'</td><td>'.dol_escape_htmltag($object->label).'</td></tr>';
	print '<tr><td>'.$langs->trans("TypeRubrique").'</td><td>'.(isset($types[$object->type]) ? $types[$object->type] : $object->type).'</td></tr>';
	print '<tr><td>'.$langs->trans("CategorieRubrique").'</td><td>'.modulepaieCategorieLabel($object->categorie).'</td></tr>';
	print '<tr><td>'.$langs->trans("BaseType").'</td><td>'.(isset($basetypes[$object->base_type]) ? $basetypes[$object->base_type] : $object->base_type).'</td></tr>';
	if ($object->base_type == 'fixe') {
		print '<tr><td>'.$langs->trans("BaseFixe").'</td><td>'.price($object->base_fixe).'</td></tr>';
	}
	print '<tr><td>'.$langs->trans("TauxSalarial").'</td><td>'.$object->taux_salarial.' %</td></tr>';
	print '<tr><td>'.$langs->trans("TauxPatronal").'</td><td>'.$object->taux_patronal.' %</td></tr>';
	print '<tr><td>'.$langs->trans("Active").'</td><td>'.($object->active ? $langs->trans("Yes") : $langs->trans("No")).'</td></tr>';
	print '</table>';
	print dol_get_fiche_end();

	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=edit&token='.newToken().'">'.$langs->trans("Modify").'</a>';
	print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=delete&token='.newToken().'">'.$langs->trans("Delete").'</a>';
	print '</div>';
}

llxFooter();
$db->close();
