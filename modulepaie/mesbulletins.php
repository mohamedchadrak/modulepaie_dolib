<?php
/* Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    mesbulletins.php
 * \ingroup modulepaie
 * \brief   Espace self-service : chaque salarié consulte et télécharge SES bulletins.
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

global $conf, $db, $langs, $user;

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/modulepaie/class/bulletin.class.php');
dol_include_once('/modulepaie/lib/modulepaie.lib.php');
$langs->loadLangs(array("modulepaie@modulepaie", "users"));

// Accessible à tout salarié disposant du droit "readmy" (ou aux RH via "read").
$canreadmy = !empty($user->rights->modulepaie->bulletin->readmy);
$canreadall = !empty($user->rights->modulepaie->bulletin->read);
if (!$canreadmy && !$canreadall) {
	accessforbidden();
}
if (empty($conf->modulepaie->dir_output)) {
	$conf->modulepaie->dir_output = DOL_DATA_ROOT.'/modulepaie';
}

$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');

/**
 * Load a bulletin and ENFORCE that it belongs to the connected user.
 * A pure "readmy" user can only ever reach their own bulletins.
 *
 * @param  DoliDB $db   Database
 * @param  User   $user Connected user
 * @param  int    $id   Bulletin id
 * @param  bool   $canreadall Whether the user may read any bulletin (HR)
 * @return PaieBulletin|null
 */
function loadOwnBulletin($db, $user, $id, $canreadall)
{
	$b = new PaieBulletin($db);
	if ($b->fetch($id) <= 0) {
		return null;
	}
	// Ownership check: a self-service employee only sees their own validated bulletins.
	if (!$canreadall) {
		if ((int) $b->fk_user !== (int) $user->id) {
			return null;
		}
		if ($b->status < PaieBulletin::STATUS_VALIDATED) {
			return null;
		}
	}
	$b->fetchSalarie();
	return $b;
}

/*
 * Action : téléchargement sécurisé du PDF.
 */
if (($action == 'downloadpdf' || $action == 'viewpdf') && $id > 0) {
	$object = loadOwnBulletin($db, $user, $id, $canreadall);
	if (!$object) {
		accessforbidden($langs->trans("AccesNonAutoriseBulletin"));
	}
	$file = $conf->modulepaie->dir_output.'/'.dol_sanitizeFileName($object->ref).'/'.dol_sanitizeFileName($object->ref).'.pdf';
	if (!dol_is_file($file) && $object->status >= PaieBulletin::STATUS_VALIDATED) {
		$object->generateDocument($object->model_pdf, $langs);
	}
	if (dol_is_file($file)) {
		clearstatcache();
		$disposition = ($action == 'viewpdf') ? 'inline' : 'attachment';
		header('Content-Type: application/pdf');
		header('Content-Disposition: '.$disposition.'; filename="'.basename($file).'"');
		header('Content-Length: '.dol_filesize($file));
		header('Cache-Control: private, must-revalidate');
		readfile($file);
		exit;
	}
	setEventMessages($langs->trans("PDFEnCoursGeneration"), null, 'warnings');
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id);
	exit;
}

/*
 * Vue
 */
llxHeader('', $langs->trans("MesBulletins"));

// -------- DÉTAIL D'UN BULLETIN --------
if ($id > 0) {
	$object = loadOwnBulletin($db, $user, $id, $canreadall);
	if (!$object) {
		print info_admin($langs->trans("AccesNonAutoriseBulletin"), 0, 0, 'error');
		llxFooter();
		$db->close();
		exit;
	}

	$backurl = dol_buildpath('/modulepaie/mesbulletins.php', 1);
	print load_fiche_titre($langs->trans("DetailBulletin").' - '.$object->ref, '<a href="'.$backurl.'">'.$langs->trans("BackToList").'</a>', 'bill');

	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?action=downloadpdf&id='.$object->id.'&token='.newToken().'">'.$langs->trans("TelechargerPDF").'</a>';
	print '</div>';

	$salname = $object->salarie ? $object->salarie->getFullName($langs) : '';
	print '<div class="fichecenter"><div class="fichehalfleft">';
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">'.$langs->trans("Salarie").'</td><td>'.dol_escape_htmltag($salname).'</td></tr>';
	print '<tr><td>'.$langs->trans("Periode").'</td><td>'.dol_print_date($object->date_debut, 'day').' → '.dol_print_date($object->date_fin, 'day').'</td></tr>';
	print '<tr><td>'.$langs->trans("DatePaiement").'</td><td>'.($object->date_paiement ? dol_print_date($object->date_paiement, 'day') : '').'</td></tr>';
	print '</table></div>';

	print '<div class="fichehalfright">';
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">'.$langs->trans("SalaireBrut").'</td><td class="right">'.price($object->brut, 0, $langs, 1, -1, 2, $conf->currency).'</td></tr>';
	print '<tr><td>'.$langs->trans("TotalCotisationsSalariales").'</td><td class="right">'.price($object->total_cot_sal, 0, $langs, 1, -1, 2, $conf->currency).'</td></tr>';
	print '<tr><td><strong>'.$langs->trans("NetAPayer").'</strong></td><td class="right"><strong>'.price($object->net_a_payer, 0, $langs, 1, -1, 2, $conf->currency).'</strong></td></tr>';
	print '<tr><td>'.$langs->trans("ImpotSource").' ('.price($object->taux_pas, 0, $langs, 1, -1, 2).' %)</td><td class="right">'.price($object->montant_pas, 0, $langs, 1, -1, 2, $conf->currency).'</td></tr>';
	print '<tr><td><strong>'.$langs->trans("NetPaye").'</strong></td><td class="right"><strong>'.price($object->net_apres_impot, 0, $langs, 1, -1, 2, $conf->currency).'</strong></td></tr>';
	print '<tr><td>'.$langs->trans("NetImposable").'</td><td class="right">'.price($object->net_imposable, 0, $langs, 1, -1, 2, $conf->currency).'</td></tr>';
	print '<tr><td>'.$langs->trans("NetSocial").'</td><td class="right">'.price($object->net_social, 0, $langs, 1, -1, 2, $conf->currency).'</td></tr>';
	print '</table></div></div>';
	print '<div class="clearboth"></div>';

	// Détail des lignes (lecture seule), regroupées comme sur le bulletin.
	$gains = array();
	$byCat = array();
	foreach ($object->lignes as $l) {
		if ($l->type == 'gain') {
			$gains[] = $l;
		} else {
			$byCat[$l->categorie][] = $l;
		}
	}
	print '<br><div class="div-table-responsive"><table class="tagtable liste centpercent noborder">';
	print '<tr class="liste_titre"><th>'.$langs->trans("LabelRubrique").'</th><th class="right">'.$langs->trans("Base").'</th>';
	print '<th class="right">'.$langs->trans("PartSalariale").'</th><th class="right">'.$langs->trans("PartPatronale").'</th></tr>';

	$printLine = function ($l) use ($langs, $conf) {
		print '<tr class="oddeven"><td>'.dol_escape_htmltag($l->label);
		if ($l->nombre > 0) {
			print ' <span class="opacitymedium">('.$l->nombre.')</span>';
		}
		print '</td><td class="right">'.price($l->base, 0, $langs, 1, -1, 2).'</td>';
		if ($l->type == 'gain') {
			print '<td class="right">'.($l->sens == 'moins' ? '-' : '').price($l->base, 0, $langs, 1, -1, 2, $conf->currency).'</td><td></td>';
		} else {
			print '<td class="right">'.($l->montant_salarial ? price($l->montant_salarial, 0, $langs, 1, -1, 2, $conf->currency) : '').'</td>';
			print '<td class="right">'.($l->montant_patronal ? price($l->montant_patronal, 0, $langs, 1, -1, 2, $conf->currency) : '').'</td>';
		}
		print '</tr>';
	};

	print '<tr class="liste_titre_add"><td colspan="4"><strong>'.$langs->trans("CatGain").'</strong></td></tr>';
	foreach ($gains as $l) {
		$printLine($l);
	}
	foreach (modulepaieOrderedCategories() as $cat) {
		if (empty($byCat[$cat])) {
			continue;
		}
		print '<tr class="liste_titre_add"><td colspan="4"><strong>'.modulepaieCategorieLabel($cat).'</strong></td></tr>';
		foreach ($byCat[$cat] as $l) {
			$printLine($l);
		}
	}
	print '<tr class="liste_total"><td class="right"><strong>'.$langs->trans("SalaireBrut").'</strong></td>';
	print '<td class="right"><strong>'.price($object->brut, 0, $langs, 1, -1, 2, $conf->currency).'</strong></td>';
	print '<td class="right"><strong>'.price($object->total_cot_sal, 0, $langs, 1, -1, 2, $conf->currency).'</strong></td>';
	print '<td class="right">'.price($object->total_cot_pat, 0, $langs, 1, -1, 2, $conf->currency).'</td></tr>';
	print '</table></div>';

	// Aperçu PDF intégré (comme les factures).
	$pdffile = $conf->modulepaie->dir_output.'/'.dol_sanitizeFileName($object->ref).'/'.dol_sanitizeFileName($object->ref).'.pdf';
	if (dol_is_file($pdffile)) {
		$viewurl = $_SERVER["PHP_SELF"].'?action=viewpdf&id='.$object->id.'&token='.newToken();
		print '<br>';
		print load_fiche_titre($langs->trans("Preview"), '', '');
		print '<div class="centpercent" style="border:1px solid #ccc;">';
		print '<iframe src="'.$viewurl.'#toolbar=1" style="width:100%;height:800px;border:0;" title="'.dol_escape_htmltag($object->ref).'.pdf"></iframe>';
		print '</div>';
	}

	llxFooter();
	$db->close();
	exit;
}

// -------- LISTE DE MES BULLETINS --------
print load_fiche_titre($langs->trans("MesBulletins"), '', 'bill');
print '<span class="opacitymedium">'.$langs->trans("MesBulletinsIntro").'</span><br><br>';

$bulletin = new PaieBulletin($db);
// Un RH voit tout ; un salarié self-service ne voit que les siens.
$fk_user_filter = $canreadall ? 0 : $user->id;
$list = $bulletin->fetchAll($fk_user_filter);

print '<div class="div-table-responsive"><table class="tagtable liste centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans("Ref").'</th>';
print '<th>'.$langs->trans("Periode").'</th>';
print '<th class="right">'.$langs->trans("SalaireBrut").'</th>';
print '<th class="right">'.$langs->trans("NetAPayerCourt").'</th>';
print '<th class="center">'.$langs->trans("Statut").'</th>';
print '<th class="center"></th>';
print '</tr>';

$nb = 0;
if (is_array($list) && count($list)) {
	foreach ($list as $obj) {
		// Sécurité : ne montrer que les bulletins validés pour un self-service.
		if (!$canreadall && $obj->status < PaieBulletin::STATUS_VALIDATED) {
			continue;
		}
		$b = new PaieBulletin($db);
		$b->id = $obj->rowid; $b->ref = $obj->ref; $b->status = $obj->status;
		$viewurl = $_SERVER["PHP_SELF"].'?id='.$obj->rowid;
		$dlurl = $_SERVER["PHP_SELF"].'?action=downloadpdf&id='.$obj->rowid.'&token='.newToken();
		print '<tr class="oddeven">';
		print '<td><a href="'.$viewurl.'">'.dol_escape_htmltag($obj->ref).'</a></td>';
		print '<td>'.dol_print_date($db->jdate($obj->date_debut), 'day').' - '.dol_print_date($db->jdate($obj->date_fin), 'day').'</td>';
		print '<td class="right">'.price($obj->brut, 0, $langs, 1, -1, 2, $conf->currency).'</td>';
		print '<td class="right">'.price($obj->net_a_payer, 0, $langs, 1, -1, 2, $conf->currency).'</td>';
		print '<td class="center">'.$b->getLibStatut(5).'</td>';
		print '<td class="center"><a class="button small" href="'.$viewurl.'">'.$langs->trans("ConsulterBulletin").'</a> ';
		print '<a class="button small" href="'.$dlurl.'">'.$langs->trans("TelechargerPDF").'</a></td>';
		print '</tr>';
		$nb++;
	}
}
if ($nb == 0) {
	print '<tr><td colspan="6"><span class="opacitymedium">'.$langs->trans("AucunBulletinDispo").'</span></td></tr>';
}
print '</table></div>';

llxFooter();
$db->close();
