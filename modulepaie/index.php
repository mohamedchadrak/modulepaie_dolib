<?php
/* Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    index.php
 * \ingroup modulepaie
 * \brief   Page d'accueil du module Paie.
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

global $conf, $db, $langs, $user;

dol_include_once('/modulepaie/class/bulletin.class.php');
dol_include_once('/modulepaie/class/contrat.class.php');

$langs->loadLangs(array("modulepaie@modulepaie"));

$canreadall = !empty($user->rights->modulepaie->bulletin->read);
$canreadmy = !empty($user->rights->modulepaie->bulletin->readmy);
if (!$canreadall && !$canreadmy) {
	accessforbidden();
}
// A self-service employee (only own bulletins) is redirected to their space.
if (!$canreadall && $canreadmy) {
	header("Location: ".dol_buildpath('/modulepaie/mesbulletins.php', 1));
	exit;
}

llxHeader('', $langs->trans("MenuPaie"));

print load_fiche_titre($langs->trans("MenuPaie"), '', 'modulepaie@modulepaie');

print '<div class="fichecenter"><div class="fichethirdleft">';

// Latest bulletins
$bulletin = new PaieBulletin($db);
$all = $bulletin->fetchAll();

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th colspan="4">'.$langs->trans("Bulletins").'</th></tr>';
if (is_array($all) && count($all)) {
	$i = 0;
	foreach ($all as $obj) {
		if ($i >= 10) {
			break;
		}
		$b = new PaieBulletin($db);
		$b->id = $obj->rowid;
		$b->ref = $obj->ref;
		$b->status = $obj->status;
		$u = new User($db);
		$u->fetch($obj->fk_user);
		print '<tr class="oddeven">';
		print '<td>'.$b->getNomUrl(1).'</td>';
		print '<td>'.dol_escape_htmltag($u->getFullName($langs)).'</td>';
		print '<td class="right">'.price($obj->net_a_payer, 0, $langs, 1, -1, -1, $conf->currency).'</td>';
		print '<td class="right">'.$b->getLibStatut(5).'</td>';
		print '</tr>';
		$i++;
	}
} else {
	print '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
}
print '</table>';

print '</div><div class="fichetwothirdright">';

// Counts
$nbsal = 0;
$contrat = new PaieContrat($db);
$contrats = $contrat->fetchAll();
if (is_array($contrats)) {
	$nbsal = count($contrats);
}

print '<div class="box">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans("Statistics").'</th><th class="right"></th></tr>';
print '<tr class="oddeven"><td><a href="'.dol_buildpath('/modulepaie/salarie_list.php', 1).'">'.$langs->trans("Salaries").'</a></td><td class="right">'.$nbsal.'</td></tr>';
print '<tr class="oddeven"><td><a href="'.dol_buildpath('/modulepaie/bulletin_list.php', 1).'">'.$langs->trans("Bulletins").'</a></td><td class="right">'.(is_array($all) ? count($all) : 0).'</td></tr>';
print '</table>';
print '</div>';

if (!getDolGlobalString('MODULEPAIE_EMPLOYEUR_NOM')) {
	print info_admin($langs->trans("ModulePaieSetupPage").' : <a href="'.dol_buildpath('/modulepaie/admin/setup.php', 1).'">'.$langs->trans("ModulePaieSetup").'</a>');
}

print '</div></div>';

llxFooter();
$db->close();
