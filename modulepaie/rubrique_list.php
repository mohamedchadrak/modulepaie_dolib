<?php
/* Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    rubrique_list.php
 * \ingroup modulepaie
 * \brief   Liste du catalogue de rubriques de paie.
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

llxHeader('', $langs->trans("Rubriques"));

$newcardbutton = dolGetButtonTitle($langs->trans('NewRubrique'), '', 'fa fa-plus-circle', dol_buildpath('/modulepaie/rubrique_card.php', 1).'?action=create');
print load_fiche_titre($langs->trans("Rubriques"), $newcardbutton, 'generic');

print info_admin($langs->trans("WarningRates"));

$rub = new PaieRubrique($db);
$list = $rub->fetchAll();

print '<div class="div-table-responsive">';
print '<table class="tagtable liste centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans("RefRubrique").'</th>';
print '<th>'.$langs->trans("LabelRubrique").'</th>';
print '<th>'.$langs->trans("CategorieRubrique").'</th>';
print '<th class="right">'.$langs->trans("TauxSalarial").'</th>';
print '<th class="right">'.$langs->trans("TauxPatronal").'</th>';
print '<th class="center">'.$langs->trans("Active").'</th>';
print '</tr>';

if (is_array($list) && count($list)) {
	foreach ($list as $r) {
		print '<tr class="oddeven">';
		print '<td>'.$r->getNomUrl(1).'</td>';
		print '<td>'.dol_escape_htmltag($r->label).'</td>';
		print '<td>'.modulepaieCategorieLabel($r->categorie).'</td>';
		print '<td class="right">'.($r->type == 'gain' ? '' : price($r->taux_salarial, 0, $langs, 1, -1, 3).' %').'</td>';
		print '<td class="right">'.($r->type == 'gain' ? '' : price($r->taux_patronal, 0, $langs, 1, -1, 3).' %').'</td>';
		print '<td class="center">'.($r->active ? img_picto('', 'tick') : '').'</td>';
		print '</tr>';
	}
} else {
	print '<tr><td colspan="6"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
}
print '</table>';
print '</div>';

llxFooter();
$db->close();
