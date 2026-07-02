<?php
/* Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    salarie_list.php
 * \ingroup modulepaie
 * \brief   Liste des salariés.
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

global $conf, $db, $langs, $user;

dol_include_once('/modulepaie/class/contrat.class.php');
$langs->loadLangs(array("modulepaie@modulepaie", "users"));

if (!$user->rights->modulepaie->bulletin->read) {
	accessforbidden();
}

llxHeader('', $langs->trans("Salaries"));

$newcardbutton = '';
if (!empty($user->rights->modulepaie->config->write)) {
	$newcardbutton = dolGetButtonTitle($langs->trans('NewSalarie'), '', 'fa fa-plus-circle', dol_buildpath('/modulepaie/salarie_card.php', 1).'?action=create');
}

print load_fiche_titre($langs->trans("Salaries"), $newcardbutton, 'user');

$contrat = new PaieContrat($db);
$list = $contrat->fetchAll();

print '<div class="div-table-responsive">';
print '<table class="tagtable liste centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans("Matricule").'</th>';
print '<th>'.$langs->trans("Salarie").'</th>';
print '<th>'.$langs->trans("Emploi").'</th>';
print '<th>'.$langs->trans("TypeContrat").'</th>';
print '<th class="right">'.$langs->trans("SalaireBase").'</th>';
print '<th class="center">'.$langs->trans("Active").'</th>';
print '</tr>';

if (is_array($list) && count($list)) {
	foreach ($list as $c) {
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($c->matricule).'</td>';
		print '<td>'.$c->getNomUrl(1).'</td>';
		print '<td>'.dol_escape_htmltag($c->emploi).'</td>';
		print '<td>'.dol_escape_htmltag($c->type_contrat).'</td>';
		print '<td class="right">'.price($c->salaire_base, 0, $langs, 1, -1, 2, $conf->currency).'</td>';
		print '<td class="center">'.($c->active ? img_picto('', 'tick') : '').'</td>';
		print '</tr>';
	}
} else {
	print '<tr><td colspan="6"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
}
print '</table>';
print '</div>';

llxFooter();
$db->close();
