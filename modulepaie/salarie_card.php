<?php
/* Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    salarie_card.php
 * \ingroup modulepaie
 * \brief   Fiche salarié (création / édition / consultation).
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

global $conf, $db, $langs, $user;

dol_include_once('/modulepaie/class/contrat.class.php');
dol_include_once('/modulepaie/lib/modulepaie.lib.php');
$langs->loadLangs(array("modulepaie@modulepaie", "users"));

if (!$user->rights->modulepaie->bulletin->read) {
	accessforbidden();
}
$candwrite = !empty($user->rights->modulepaie->config->write);

$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');

$object = new PaieContrat($db);
$form = new Form($db);

if ($id > 0) {
	$object->fetch($id);
	$object->fetchUser();
}

/*
 * Actions
 */
if ($action == 'add' && $candwrite) {
	$object->fk_user = GETPOST('fk_user', 'int');
	$object->matricule = GETPOST('matricule', 'alpha');
	$object->num_secu = GETPOST('num_secu', 'alpha');
	$object->emploi = GETPOST('emploi', 'alpha');
	$object->qualification = GETPOST('qualification', 'alpha');
	$object->classification = GETPOST('classification', 'alpha');
	$object->coefficient = GETPOST('coefficient', 'alpha');
	$object->niveau = GETPOST('niveau', 'alpha');
	$object->echelon = GETPOST('echelon', 'alpha');
	$object->convention = GETPOST('convention', 'alpha');
	$object->type_contrat = GETPOST('type_contrat', 'alpha');
	$object->categorie = GETPOST('categorie', 'alpha');
	$object->salaire_base = price2num(GETPOST('salaire_base', 'alpha'));
	$object->temps_travail = price2num(GETPOST('temps_travail', 'alpha'));
	$object->date_entree = dol_mktime(0, 0, 0, GETPOST('date_entreemonth', 'int'), GETPOST('date_entreeday', 'int'), GETPOST('date_entreeyear', 'int'));
	$object->date_anciennete = dol_mktime(0, 0, 0, GETPOST('date_anciennetemonth', 'int'), GETPOST('date_annciennetjeday', 'int'), GETPOST('date_anciennetyear', 'int'));
	$object->active = GETPOST('active', 'int') ? 1 : 0;
	$object->note = GETPOST('note', 'restricthtml');

	if (empty($object->fk_user)) {
		setEventMessages($langs->trans("SelectUser"), null, 'errors');
		$action = 'create';
	} else {
		$result = $object->create($user);
		if ($result > 0) {
			setEventMessages($langs->trans("SalarieCree"), null, 'mesgs');
			header("Location: ".$_SERVER["PHP_SELF"]."?id=".$result);
			exit;
		} else {
			setEventMessages($object->error, null, 'errors');
			$action = 'create';
		}
	}
}

if ($action == 'update' && $candwrite && $id > 0) {
	$object->matricule = GETPOST('matricule', 'alpha');
	$object->num_secu = GETPOST('num_secu', 'alpha');
	$object->emploi = GETPOST('emploi', 'alpha');
	$object->qualification = GETPOST('qualification', 'alpha');
	$object->classification = GETPOST('classification', 'alpha');
	$object->coefficient = GETPOST('coefficient', 'alpha');
	$object->niveau = GETPOST('niveau', 'alpha');
	$object->echelon = GETPOST('echelon', 'alpha');
	$object->convention = GETPOST('convention', 'alpha');
	$object->type_contrat = GETPOST('type_contrat', 'alpha');
	$object->categorie = GETPOST('categorie', 'alpha');
	$object->salaire_base = price2num(GETPOST('salaire_base', 'alpha'));
	$object->temps_travail = price2num(GETPOST('temps_travail', 'alpha'));
	$object->taux_horaire = 0; // recomputed
	$object->date_entree = dol_mktime(0, 0, 0, GETPOST('date_entreemonth', 'int'), GETPOST('date_entreeday', 'int'), GETPOST('date_entreeyear', 'int'));
	$object->active = GETPOST('active', 'int') ? 1 : 0;
	$object->note = GETPOST('note', 'restricthtml');
	$result = $object->update($user);
	if ($result > 0) {
		setEventMessages($langs->trans("SalarieModifie"), null, 'mesgs');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id);
		exit;
	} else {
		setEventMessages($object->error, null, 'errors');
	}
}

if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' && $candwrite && $id > 0) {
	if ($object->delete($user) > 0) {
		setEventMessages($langs->trans("SalarieSupprime"), null, 'mesgs');
		header("Location: ".dol_buildpath('/modulepaie/salarie_list.php', 1));
		exit;
	} else {
		setEventMessages($object->error, null, 'errors');
	}
}

/*
 * View
 */
llxHeader('', $langs->trans("Salarie"));

$typescontrat = array('CDI' => 'CDI', 'CDD' => 'CDD', 'Interim' => 'Intérim', 'Apprentissage' => 'Apprentissage', 'Stage' => 'Stage');
$categories = array('non_cadre' => $langs->trans('NonCadre'), 'cadre' => $langs->trans('Cadre'));

if ($action == 'create') {
	print load_fiche_titre($langs->trans("NewSalarie"), '', 'user');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print dol_get_fiche_head();
	print '<table class="border centpercent">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans("UtilisateurLie").'</td><td>';
	print $form->select_dolusers(GETPOST('fk_user', 'int'), 'fk_user', 1);
	print '</td></tr>';
	print '<tr><td>'.$langs->trans("Matricule").'</td><td><input type="text" name="matricule" value="'.dol_escape_htmltag(GETPOST('matricule')).'"></td></tr>';
	print '<tr><td>'.$langs->trans("NumeroSecu").'</td><td><input type="text" name="num_secu" class="minwidth200" value="'.dol_escape_htmltag(GETPOST('num_secu')).'"></td></tr>';
	print '<tr><td>'.$langs->trans("Emploi").'</td><td><input type="text" name="emploi" class="minwidth300" value="'.dol_escape_htmltag(GETPOST('emploi')).'"></td></tr>';
	print '<tr><td>'.$langs->trans("Qualification").'</td><td><input type="text" name="qualification" value="'.dol_escape_htmltag(GETPOST('qualification')).'"></td></tr>';
	print '<tr><td>'.$langs->trans("Classification").'</td><td><input type="text" name="classification" value="'.dol_escape_htmltag(GETPOST('classification')).'"></td></tr>';
	print '<tr><td>'.$langs->trans("Coefficient").'</td><td><input type="text" name="coefficient" class="width75" value="'.dol_escape_htmltag(GETPOST('coefficient')).'"></td></tr>';
	print '<tr><td>'.$langs->trans("Niveau").' / '.$langs->trans("Echelon").'</td><td><input type="text" name="niveau" class="width75" value="'.dol_escape_htmltag(GETPOST('niveau')).'"> <input type="text" name="echelon" class="width75" value="'.dol_escape_htmltag(GETPOST('echelon')).'"></td></tr>';
	print '<tr><td>'.$langs->trans("Convention").'</td><td><input type="text" name="convention" class="minwidth300" value="'.dol_escape_htmltag(GETPOST('convention') ? GETPOST('convention') : getDolGlobalString('MODULEPAIE_EMPLOYEUR_CONVENTION')).'"></td></tr>';
	print '<tr><td>'.$langs->trans("TypeContrat").'</td><td>'.$form->selectarray('type_contrat', $typescontrat, GETPOST('type_contrat') ? GETPOST('type_contrat') : 'CDI').'</td></tr>';
	print '<tr><td>'.$langs->trans("CategorieSalarie").'</td><td>'.$form->selectarray('categorie', $categories, GETPOST('categorie') ? GETPOST('categorie') : 'non_cadre').'</td></tr>';
	print '<tr><td>'.$langs->trans("DateEntree").'</td><td>'.$form->selectDate('', 'date_entree', 0, 0, 1, '', 1, 0).'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans("SalaireBase").'</td><td><input type="text" name="salaire_base" class="width100" value="'.dol_escape_htmltag(GETPOST('salaire_base')).'"> '.$conf->currency.'</td></tr>';
	print '<tr><td>'.$langs->trans("TempsTravail").'</td><td><input type="text" name="temps_travail" class="width75" value="'.dol_escape_htmltag(GETPOST('temps_travail') ? GETPOST('temps_travail') : '151.67').'"> h</td></tr>';
	print '<tr><td>'.$langs->trans("ContratActif").'</td><td><input type="checkbox" name="active" value="1" checked></td></tr>';
	print '<tr><td>'.$langs->trans("Note").'</td><td><textarea name="note" class="quatrevingtpercent" rows="2"></textarea></td></tr>';
	print '</table>';
	print dol_get_fiche_end();

	print '<div class="center">';
	print '<input type="submit" class="button button-save" value="'.$langs->trans("Create").'">';
	print ' &nbsp; <a class="button button-cancel" href="'.dol_buildpath('/modulepaie/salarie_list.php', 1).'">'.$langs->trans("Cancel").'</a>';
	print '</div>';
	print '</form>';
} elseif ($id > 0 && $action == 'edit' && $candwrite) {
	print load_fiche_titre($langs->trans("Salarie").' - '.$object->getFullName(), '', 'user');
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';

	print dol_get_fiche_head();
	print '<table class="border centpercent">';
	print '<tr><td class="titlefieldcreate">'.$langs->trans("Salarie").'</td><td>'.dol_escape_htmltag($object->getFullName()).'</td></tr>';
	print '<tr><td>'.$langs->trans("Matricule").'</td><td><input type="text" name="matricule" value="'.dol_escape_htmltag($object->matricule).'"></td></tr>';
	print '<tr><td>'.$langs->trans("NumeroSecu").'</td><td><input type="text" name="num_secu" class="minwidth200" value="'.dol_escape_htmltag($object->num_secu).'"></td></tr>';
	print '<tr><td>'.$langs->trans("Emploi").'</td><td><input type="text" name="emploi" class="minwidth300" value="'.dol_escape_htmltag($object->emploi).'"></td></tr>';
	print '<tr><td>'.$langs->trans("Qualification").'</td><td><input type="text" name="qualification" value="'.dol_escape_htmltag($object->qualification).'"></td></tr>';
	print '<tr><td>'.$langs->trans("Classification").'</td><td><input type="text" name="classification" value="'.dol_escape_htmltag($object->classification).'"></td></tr>';
	print '<tr><td>'.$langs->trans("Coefficient").'</td><td><input type="text" name="coefficient" class="width75" value="'.dol_escape_htmltag($object->coefficient).'"></td></tr>';
	print '<tr><td>'.$langs->trans("Niveau").' / '.$langs->trans("Echelon").'</td><td><input type="text" name="niveau" class="width75" value="'.dol_escape_htmltag($object->niveau).'"> <input type="text" name="echelon" class="width75" value="'.dol_escape_htmltag($object->echelon).'"></td></tr>';
	print '<tr><td>'.$langs->trans("Convention").'</td><td><input type="text" name="convention" class="minwidth300" value="'.dol_escape_htmltag($object->convention).'"></td></tr>';
	print '<tr><td>'.$langs->trans("TypeContrat").'</td><td>'.$form->selectarray('type_contrat', $typescontrat, $object->type_contrat).'</td></tr>';
	print '<tr><td>'.$langs->trans("CategorieSalarie").'</td><td>'.$form->selectarray('categorie', $categories, $object->categorie).'</td></tr>';
	print '<tr><td>'.$langs->trans("DateEntree").'</td><td>'.$form->selectDate($object->date_entree, 'date_entree', 0, 0, 1, '', 1, 0).'</td></tr>';
	print '<tr><td>'.$langs->trans("SalaireBase").'</td><td><input type="text" name="salaire_base" class="width100" value="'.dol_escape_htmltag($object->salaire_base).'"> '.$conf->currency.'</td></tr>';
	print '<tr><td>'.$langs->trans("TempsTravail").'</td><td><input type="text" name="temps_travail" class="width75" value="'.dol_escape_htmltag($object->temps_travail).'"> h</td></tr>';
	print '<tr><td>'.$langs->trans("ContratActif").'</td><td><input type="checkbox" name="active" value="1" '.($object->active ? 'checked' : '').'></td></tr>';
	print '<tr><td>'.$langs->trans("Note").'</td><td><textarea name="note" class="quatrevingtpercent" rows="2">'.dol_escape_htmltag($object->note).'</textarea></td></tr>';
	print '</table>';
	print dol_get_fiche_end();

	print '<div class="center">';
	print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
	print ' &nbsp; <a class="button button-cancel" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'">'.$langs->trans("Cancel").'</a>';
	print '</div>';
	print '</form>';
} elseif ($id > 0) {
	$head = salariePrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans("Salarie"), -1, 'user');

	if ($action == 'delete') {
		print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$id, $langs->trans("Delete"), $langs->trans("ConfirmDeleteBulletin"), 'confirm_delete', '', 0, 1);
	}

	$linkback = '<a href="'.dol_buildpath('/modulepaie/salarie_list.php', 1).'">'.$langs->trans("BackToList").'</a>';
	print '<div class="refidno"></div>';
	dol_banner_tab($object, 'id', $linkback, 0, '', '', dol_escape_htmltag($object->getFullName()));

	print '<div class="fichecenter"><div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">'.$langs->trans("Matricule").'</td><td>'.dol_escape_htmltag($object->matricule).'</td></tr>';
	print '<tr><td>'.$langs->trans("NumeroSecu").'</td><td>'.dol_escape_htmltag($object->num_secu).'</td></tr>';
	print '<tr><td>'.$langs->trans("Emploi").'</td><td>'.dol_escape_htmltag($object->emploi).'</td></tr>';
	print '<tr><td>'.$langs->trans("Qualification").'</td><td>'.dol_escape_htmltag($object->qualification).' '.dol_escape_htmltag($object->classification).'</td></tr>';
	print '<tr><td>'.$langs->trans("Coefficient").'</td><td>'.dol_escape_htmltag($object->coefficient).'</td></tr>';
	print '<tr><td>'.$langs->trans("Convention").'</td><td>'.dol_escape_htmltag($object->convention).'</td></tr>';
	print '<tr><td>'.$langs->trans("TypeContrat").'</td><td>'.dol_escape_htmltag($object->type_contrat).' ('.($object->categorie == 'cadre' ? $langs->trans('Cadre') : $langs->trans('NonCadre')).')</td></tr>';
	print '<tr><td>'.$langs->trans("DateEntree").'</td><td>'.($object->date_entree ? dol_print_date($object->date_entree, 'day') : '').'</td></tr>';
	print '<tr><td>'.$langs->trans("SalaireBase").'</td><td>'.price($object->salaire_base, 0, $langs, 1, -1, 2, $conf->currency).'</td></tr>';
	print '<tr><td>'.$langs->trans("TempsTravail").'</td><td>'.$object->temps_travail.' h</td></tr>';
	print '<tr><td>'.$langs->trans("TauxHoraire").'</td><td>'.price($object->taux_horaire, 0, $langs, 1, -1, 4, $conf->currency).'</td></tr>';
	print '</table>';
	print '</div>';

	print dol_get_fiche_end();

	// Buttons
	print '<div class="tabsAction">';
	if ($candwrite) {
		print '<a class="butAction" href="'.dol_buildpath('/modulepaie/bulletin_card.php', 1).'?action=create&fk_user='.$object->fk_user.'">'.$langs->trans("NewBulletin").'</a>';
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=edit">'.$langs->trans("Modify").'</a>';
		print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=delete">'.$langs->trans("Delete").'</a>';
	}
	print '</div>';

	// Bulletins of this employee
	dol_include_once('/modulepaie/class/bulletin.class.php');
	$bulletin = new PaieBulletin($db);
	$bulls = $bulletin->fetchAll($object->fk_user);
	print load_fiche_titre($langs->trans("Bulletins"), '', '');
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans("Ref").'</th><th>'.$langs->trans("Periode").'</th><th class="right">'.$langs->trans("NetAPayerCourt").'</th><th class="right">'.$langs->trans("Statut").'</th></tr>';
	if (is_array($bulls) && count($bulls)) {
		foreach ($bulls as $obj) {
			$b = new PaieBulletin($db);
			$b->id = $obj->rowid; $b->ref = $obj->ref; $b->status = $obj->status;
			print '<tr class="oddeven"><td>'.$b->getNomUrl(1).'</td>';
			print '<td>'.dol_print_date($db->jdate($obj->date_debut), 'day').' - '.dol_print_date($db->jdate($obj->date_fin), 'day').'</td>';
			print '<td class="right">'.price($obj->net_a_payer, 0, $langs, 1, -1, 2, $conf->currency).'</td>';
			print '<td class="right">'.$b->getLibStatut(5).'</td></tr>';
		}
	} else {
		print '<tr><td colspan="4"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
	}
	print '</table>';
}

llxFooter();
$db->close();
