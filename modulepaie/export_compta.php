<?php
/* Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    export_compta.php
 * \ingroup modulepaie
 * \brief   Export CSV du journal de paie (écritures comptables 641/645/421/431/437/4421).
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

global $conf, $db, $langs, $user;

dol_include_once('/modulepaie/class/bulletin.class.php');
dol_include_once('/modulepaie/lib/modulepaie.lib.php');
$langs->loadLangs(array("modulepaie@modulepaie", "users", "compta"));

if (!$user->rights->modulepaie->bulletin->read) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$year = GETPOST('year', 'int') ? GETPOST('year', 'int') : (int) dol_print_date(dol_now(), '%Y');
$month = GETPOST('month', 'int'); // 0 = whole year

/**
 * Build the payroll journal entries for a list of bulletins.
 * Each bulletin produces a balanced block:
 *   Débit  641 (salaires bruts)         = brut
 *   Débit  645 (charges patronales)     = total_cot_pat
 *   Crédit 431 (URSSAF & organismes)    = cotisations sal+pat hors retraite
 *   Crédit 437 (retraite complémentaire)= cotisations sal+pat retraite
 *   Crédit 4421 (PAS)                   = montant_pas
 *   Crédit 421 (personnel)              = net payé après impôt
 *
 * @param  DoliDB $db        Database
 * @param  array  $bulletins Rows from PaieBulletin::fetchAll()
 * @return array             Array of entries: [date, piece, compte, libelle, debit, credit]
 */
function buildJournalPaie($db, $bulletins)
{
	$cpt_sal = getDolGlobalString('MODULEPAIE_CPT_SALAIRES', '641000');
	$cpt_chg = getDolGlobalString('MODULEPAIE_CPT_CHARGES', '645000');
	$cpt_pers = getDolGlobalString('MODULEPAIE_CPT_PERSONNEL', '421000');
	$cpt_urssaf = getDolGlobalString('MODULEPAIE_CPT_URSSAF', '431000');
	$cpt_retraite = getDolGlobalString('MODULEPAIE_CPT_RETRAITE', '437000');
	$cpt_pas = getDolGlobalString('MODULEPAIE_CPT_PAS', '442100');

	$entries = array();
	foreach ($bulletins as $row) {
		$b = new PaieBulletin($db);
		if ($b->fetch($row->rowid) <= 0) {
			continue;
		}
		$b->fetchSalarie();
		$nom = $b->salarie ? $b->salarie->getFullName($GLOBALS['langs']) : '';
		$dateecr = dol_print_date($b->date_fin, '%d/%m/%Y');
		$piece = $b->ref;

		// Split contributions between URSSAF-like (431) and retraite (437), signed
		// the same way as computeTotals (allègements 'moins' reduce the employer part).
		$org_urssaf = 0.0;
		$org_retraite = 0.0;
		foreach ($b->lignes as $l) {
			if ($l->type != 'cotisation') {
				continue;
			}
			$signe = ($l->sens == 'moins') ? -1 : 1;
			$tot = (float) $l->montant_salarial + $signe * (float) $l->montant_patronal;
			if ($l->categorie == 'retraite') {
				$org_retraite += $tot;
			} else {
				$org_urssaf += $tot;
			}
		}
		$org_urssaf = round($org_urssaf, 2);
		$org_retraite = round($org_retraite, 2);

		$netpaye = ($b->net_apres_impot > 0 || $b->montant_pas > 0) ? $b->net_apres_impot : $b->net_a_payer;

		// Débits
		$entries[] = array($dateecr, $piece, $cpt_sal, 'Salaires bruts '.$piece.' - '.$nom, round($b->brut, 2), 0);
		if (abs($b->total_cot_pat) > 0.004) {
			$entries[] = array($dateecr, $piece, $cpt_chg, 'Charges patronales '.$piece.' - '.$nom, round($b->total_cot_pat, 2), 0);
		}
		// Crédits
		if (abs($org_urssaf) > 0.004) {
			$entries[] = array($dateecr, $piece, $cpt_urssaf, 'URSSAF et organismes '.$piece.' - '.$nom, 0, $org_urssaf);
		}
		if (abs($org_retraite) > 0.004) {
			$entries[] = array($dateecr, $piece, $cpt_retraite, 'Retraite complémentaire '.$piece.' - '.$nom, 0, $org_retraite);
		}
		if (abs($b->montant_pas) > 0.004) {
			$entries[] = array($dateecr, $piece, $cpt_pas, 'Prélèvement à la source '.$piece.' - '.$nom, 0, round($b->montant_pas, 2));
		}
		$entries[] = array($dateecr, $piece, $cpt_pers, 'Net à payer '.$piece.' - '.$nom, 0, round($netpaye, 2));
	}
	return $entries;
}

/**
 * Fetch validated/paid bulletins of the period.
 *
 * @param  DoliDB $db    Database
 * @param  int    $year  Year
 * @param  int    $month Month (0 = whole year)
 * @return array         Rows (rowid, ref, ...)
 */
function fetchBulletinsPeriode($db, $year, $month)
{
	$sql = "SELECT rowid, ref, fk_user, date_debut, date_fin, brut, total_cot_sal, total_cot_pat, net_a_payer, status";
	$sql .= " FROM ".MAIN_DB_PREFIX."paie_bulletin";
	$sql .= " WHERE entity IN (".getEntity('paie_bulletin').")";
	$sql .= " AND status >= ".PaieBulletin::STATUS_VALIDATED;
	if ($month > 0) {
		$start = sprintf('%04d-%02d-01', $year, $month);
		$end = date('Y-m-t', strtotime($start));
	} else {
		$start = $year.'-01-01';
		$end = $year.'-12-31';
	}
	$sql .= " AND date_debut >= '".$db->escape($start)."' AND date_debut <= '".$db->escape($end)."'";
	$sql .= " ORDER BY date_debut ASC, ref ASC";
	$rows = array();
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$rows[] = $obj;
		}
	}
	return $rows;
}

/*
 * Action : téléchargement CSV
 */
if ($action == 'exportcsv') {
	$bulls = fetchBulletinsPeriode($db, $year, $month);
	$entries = buildJournalPaie($db, $bulls);

	$filename = 'journal_paie_'.$year.($month > 0 ? sprintf('-%02d', $month) : '').'.csv';
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	// BOM UTF-8 pour Excel.
	echo "\xEF\xBB\xBF";
	$out = fopen('php://output', 'w');
	fputcsv($out, array('Date', 'Journal', 'Pièce', 'Compte', 'Libellé', 'Débit', 'Crédit'), ';');
	$tdeb = 0; $tcred = 0;
	foreach ($entries as $e) {
		fputcsv($out, array($e[0], 'PAIE', $e[1], $e[2], $e[3], price2num($e[4]), price2num($e[5])), ';');
		$tdeb += $e[4];
		$tcred += $e[5];
	}
	fputcsv($out, array('', '', '', '', 'TOTAL', price2num(round($tdeb, 2)), price2num(round($tcred, 2))), ';');
	fclose($out);
	exit;
}

/*
 * Vue
 */
llxHeader('', $langs->trans("ExportCompta"));

print load_fiche_titre($langs->trans("ExportCompta"), '', 'accounting');
print '<span class="opacitymedium">'.$langs->trans("ExportComptaIntro").'</span><br><br>';

$form = new Form($db);

print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
print '<div class="marginbottomonly">';
print $langs->trans("Year").': <input type="text" name="year" class="width50" value="'.$year.'"> ';
$months = array(0 => $langs->trans("FullYear"));
for ($m = 1; $m <= 12; $m++) {
	$months[$m] = dol_print_date(dol_mktime(12, 0, 0, $m, 1, 2000), '%B');
}
print $langs->trans("Month").': '.$form->selectarray('month', $months, $month);
print ' <input type="submit" class="button small" value="'.$langs->trans("Refresh").'">';
print ' <a class="button" href="'.$_SERVER["PHP_SELF"].'?action=exportcsv&year='.$year.'&month='.$month.'&token='.newToken().'">'.$langs->trans("TelechargerCSV").'</a>';
print '</div>';
print '</form>';

// Aperçu des écritures.
$bulls = fetchBulletinsPeriode($db, $year, $month);
$entries = buildJournalPaie($db, $bulls);

print '<div class="div-table-responsive">';
print '<table class="tagtable liste centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans("Date").'</th><th>'.$langs->trans("Piece").'</th><th>'.$langs->trans("CompteComptable").'</th>';
print '<th>'.$langs->trans("Label").'</th><th class="right">'.$langs->trans("Debit").'</th><th class="right">'.$langs->trans("Credit").'</th>';
print '</tr>';

$tdeb = 0; $tcred = 0;
if (count($entries)) {
	foreach ($entries as $e) {
		print '<tr class="oddeven">';
		print '<td>'.$e[0].'</td><td>'.dol_escape_htmltag($e[1]).'</td><td>'.dol_escape_htmltag($e[2]).'</td>';
		print '<td>'.dol_escape_htmltag($e[3]).'</td>';
		print '<td class="right">'.($e[4] ? price($e[4], 0, $langs, 1, -1, 2) : '').'</td>';
		print '<td class="right">'.($e[5] ? price($e[5], 0, $langs, 1, -1, 2) : '').'</td>';
		print '</tr>';
		$tdeb += $e[4]; $tcred += $e[5];
	}
	print '<tr class="liste_total"><td colspan="4" class="right"><strong>'.$langs->trans("Total").'</strong></td>';
	print '<td class="right"><strong>'.price(round($tdeb, 2), 0, $langs, 1, -1, 2).'</strong></td>';
	print '<td class="right"><strong>'.price(round($tcred, 2), 0, $langs, 1, -1, 2).'</strong></td></tr>';
	if (abs($tdeb - $tcred) > 0.005) {
		print '<tr><td colspan="6" class="error">'.$langs->trans("EcrituresDesequilibrees").'</td></tr>';
	}
} else {
	print '<tr><td colspan="6"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
}
print '</table>';
print '</div>';

llxFooter();
$db->close();
