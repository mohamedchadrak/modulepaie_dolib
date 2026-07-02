<?php
/* Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    bulletin_list.php
 * \ingroup modulepaie
 * \brief   Liste des bulletins de paie.
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

global $conf, $db, $langs, $user;

dol_include_once('/modulepaie/class/bulletin.class.php');
$langs->loadLangs(array("modulepaie@modulepaie", "users"));

if (!$user->rights->modulepaie->bulletin->read) {
	accessforbidden();
}

$search_user = GETPOST('search_user', 'int');
$search_status = GETPOST('search_status', 'intcomma');
if ($search_status === '') {
	$search_status = -1;
}

llxHeader('', $langs->trans("Bulletins"));

$newcardbutton = '';
if (!empty($user->rights->modulepaie->bulletin->write)) {
	$newcardbutton = dolGetButtonTitle($langs->trans('NewBulletin'), '', 'fa fa-plus-circle', dol_buildpath('/modulepaie/bulletin_card.php', 1).'?action=create');
}
print load_fiche_titre($langs->trans("Bulletins"), $newcardbutton, 'bill');

$form = new Form($db);
$bulletin = new PaieBulletin($db);
$list = $bulletin->fetchAll($search_user, $search_status);

// Filter form
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
print '<div class="marginbottomonly">';
print $langs->trans("Salarie").': '.$form->select_dolusers($search_user, 'search_user', 1);
print ' '.$langs->trans("Statut").': ';
$statuslist = array('-1' => '&nbsp;', '0' => $langs->trans('StatusDraft'), '1' => $langs->trans('StatusValidated'), '9' => $langs->trans('StatusPaid'));
print $form->selectarray('search_status', $statuslist, $search_status);
print ' <input type="submit" class="button small" value="'.$langs->trans("Search").'">';
print '</div>';
print '</form>';

print '<div class="div-table-responsive">';
print '<table class="tagtable liste centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans("Ref").'</th>';
print '<th>'.$langs->trans("Salarie").'</th>';
print '<th>'.$langs->trans("Periode").'</th>';
print '<th class="right">'.$langs->trans("SalaireBrut").'</th>';
print '<th class="right">'.$langs->trans("NetAPayerCourt").'</th>';
print '<th class="right">'.$langs->trans("NetImposable").'</th>';
print '<th class="center">'.$langs->trans("Statut").'</th>';
print '</tr>';

$totbrut = 0; $totnet = 0;
if (is_array($list) && count($list)) {
	foreach ($list as $obj) {
		$b = new PaieBulletin($db);
		$b->id = $obj->rowid; $b->ref = $obj->ref; $b->status = $obj->status;
		$u = new User($db); $u->fetch($obj->fk_user);
		print '<tr class="oddeven">';
		print '<td>'.$b->getNomUrl(1).'</td>';
		print '<td>'.dol_escape_htmltag($u->getFullName($langs)).'</td>';
		print '<td>'.dol_print_date($db->jdate($obj->date_debut), 'day').' - '.dol_print_date($db->jdate($obj->date_fin), 'day').'</td>';
		print '<td class="right">'.price($obj->brut, 0, $langs, 1, -1, 2, $conf->currency).'</td>';
		print '<td class="right">'.price($obj->net_a_payer, 0, $langs, 1, -1, 2, $conf->currency).'</td>';
		print '<td class="right">'.price($obj->net_imposable, 0, $langs, 1, -1, 2, $conf->currency).'</td>';
		print '<td class="center">'.$b->getLibStatut(5).'</td>';
		print '</tr>';
		$totbrut += $obj->brut; $totnet += $obj->net_a_payer;
	}
	print '<tr class="liste_total"><td colspan="3" class="right">'.$langs->trans("Total").'</td>';
	print '<td class="right">'.price($totbrut, 0, $langs, 1, -1, 2, $conf->currency).'</td>';
	print '<td class="right">'.price($totnet, 0, $langs, 1, -1, 2, $conf->currency).'</td>';
	print '<td colspan="2"></td></tr>';
} else {
	print '<tr><td colspan="7"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
}
print '</table>';
print '</div>';

llxFooter();
$db->close();
