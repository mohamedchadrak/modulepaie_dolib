<?php
/* Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    bulletin_card.php
 * \ingroup modulepaie
 * \brief   Fiche d'un bulletin de paie.
 */

$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

global $conf, $db, $langs, $user;

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/modulepaie/class/bulletin.class.php');
dol_include_once('/modulepaie/class/contrat.class.php');
dol_include_once('/modulepaie/class/rubrique.class.php');
dol_include_once('/modulepaie/lib/modulepaie.lib.php');
$langs->loadLangs(array("modulepaie@modulepaie", "users", "bills"));

if (!$user->rights->modulepaie->bulletin->read) {
	accessforbidden();
}
$candwrite = !empty($user->rights->modulepaie->bulletin->write);
$canvalidate = !empty($user->rights->modulepaie->bulletin->validate);
$candelete = !empty($user->rights->modulepaie->bulletin->delete);
if (empty($conf->modulepaie->dir_output)) {
	$conf->modulepaie->dir_output = DOL_DATA_ROOT.'/modulepaie';
}

$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');

$object = new PaieBulletin($db);
$form = new Form($db);
if ($id > 0) {
	$object->fetch($id);
	$object->fetchSalarie();
}

/*
 * Actions
 */

// Create a new bulletin from a contrat + period.
if ($action == 'create_bulletin' && $candwrite) {
	$fk_user = GETPOST('fk_user', 'int');
	$date_debut = dol_mktime(0, 0, 0, GETPOST('date_debutmonth', 'int'), GETPOST('date_debutday', 'int'), GETPOST('date_debutyear', 'int'));
	$date_fin = dol_mktime(0, 0, 0, GETPOST('date_finmonth', 'int'), GETPOST('date_finday', 'int'), GETPOST('date_finyear', 'int'));
	$date_paie = dol_mktime(0, 0, 0, GETPOST('date_paiementmonth', 'int'), GETPOST('date_paiementday', 'int'), GETPOST('date_paiementyear', 'int'));

	$error = 0;
	if (empty($fk_user)) {
		setEventMessages($langs->trans("SelectSalarie"), null, 'errors');
		$error++;
	}
	if (empty($date_debut) || empty($date_fin)) {
		setEventMessages($langs->trans("PeriodeDebut"), null, 'errors');
		$error++;
	}

	$contrat = new PaieContrat($db);
	if (!$error && $contrat->fetch(0, $fk_user) <= 0) {
		setEventMessages($langs->trans("NoContractForUser"), null, 'errors');
		$error++;
	}

	if (!$error) {
		$object->date_debut = $date_debut;
		$object->date_fin = $date_fin;
		$object->date_paiement = $date_paie;
		$object->buildFromContrat($contrat);
		$result = $object->create($user);
		if ($result > 0) {
			setEventMessages($langs->trans("BulletinCree"), null, 'mesgs');
			header("Location: ".$_SERVER["PHP_SELF"]."?id=".$result);
			exit;
		} else {
			setEventMessages($object->error, null, 'errors');
			$action = 'create';
		}
	} else {
		$action = 'create';
	}
}

// Recalculate.
if ($action == 'recalc' && $candwrite && $id > 0 && $object->status == PaieBulletin::STATUS_DRAFT) {
	$object->plafond_ss = PaieBulletin::getPMSS();
	$object->computeTotals();
	$object->update($user);
	$object->saveLines();
	setEventMessages($langs->trans("BulletinModifie"), null, 'mesgs');
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id);
	exit;
}

// Save edited lines.
if ($action == 'savelines' && $candwrite && $id > 0 && $object->status == PaieBulletin::STATUS_DRAFT) {
	$bases = GETPOST('line_base', 'array');
	$nombres = GETPOST('line_nombre', 'array');
	$ts = GETPOST('line_ts', 'array');
	$tp = GETPOST('line_tp', 'array');
	foreach ($object->lignes as $l) {
		$key = $l->rowid;
		if (isset($bases[$key])) {
			$l->base = price2num($bases[$key]);
		}
		if (isset($nombres[$key])) {
			$l->nombre = price2num($nombres[$key]);
		}
		if (isset($ts[$key])) {
			$l->taux_salarial = price2num($ts[$key]);
		}
		if (isset($tp[$key])) {
			$l->taux_patronal = price2num($tp[$key]);
		}
		// Keep base_type from rubrique for recompute of cotisation assiettes.
	}
	$object->salaire_base = 0; // recomputed from SALBASE line
	$object->conges_acquis = price2num(GETPOST('conges_acquis', 'alpha'));
	$object->conges_pris = price2num(GETPOST('conges_pris', 'alpha'));
	$object->conges_solde = price2num(GETPOST('conges_solde', 'alpha'));
	$object->computeTotals();
	$object->update($user);
	$object->saveLines();
	setEventMessages($langs->trans("BulletinModifie"), null, 'mesgs');
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id);
	exit;
}

// Add a gain/rubrique line.
if ($action == 'addline' && $candwrite && $id > 0 && $object->status == PaieBulletin::STATUS_DRAFT) {
	$fk_rubrique = GETPOST('fk_rubrique_add', 'int');
	$montant = price2num(GETPOST('montant_add', 'alpha'));
	if ($fk_rubrique > 0) {
		$r = new PaieRubrique($db);
		if ($r->fetch($fk_rubrique) > 0) {
			$l = new PaieBulletinLigne();
			$l->fk_rubrique = $r->id;
			$l->ref = $r->ref;
			$l->label = $r->label;
			$l->type = $r->type;
			$l->categorie = $r->categorie;
			$l->taux_salarial = $r->taux_salarial;
			$l->taux_patronal = $r->taux_patronal;
			$l->sens = $r->sens;
			$l->soumis = $r->soumis;
			$l->imposable = $r->imposable;
			$l->position = $r->position;
			$l->base = ($r->base_type == 'fixe') ? $r->base_fixe : $montant;
			$l->base_type = $r->base_type;
			$l->base_fixe = $r->base_fixe;
			$object->lignes[] = $l;
			$object->computeTotals();
			$object->update($user);
			$object->saveLines();
			setEventMessages($langs->trans("BulletinModifie"), null, 'mesgs');
		}
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id."&action=editlines");
	exit;
}

// Delete a line.
if ($action == 'deleteline' && $candwrite && $id > 0 && $object->status == PaieBulletin::STATUS_DRAFT) {
	$lineid = GETPOST('lineid', 'int');
	foreach ($object->lignes as $k => $l) {
		if ($l->rowid == $lineid) {
			unset($object->lignes[$k]);
		}
	}
	$object->lignes = array_values($object->lignes);
	$object->computeTotals();
	$object->update($user);
	$object->saveLines();
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id."&action=editlines");
	exit;
}

// Validate.
if ($action == 'confirm_validate' && GETPOST('confirm') == 'yes' && $canvalidate && $id > 0) {
	if ($object->validate($user) > 0) {
		setEventMessages($langs->trans("BulletinValide"), null, 'mesgs');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id);
		exit;
	} else {
		setEventMessages($object->error, null, 'errors');
	}
}
if ($action == 'setdraft' && $candwrite && $id > 0) {
	$res = $object->setDraft($user);
	if ($res > 0) {
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id);
		exit;
	}
	setEventMessages($object->error == 'BankLineReconciled' ? $langs->trans("ErreurEcritureRapprochee") : $object->error, null, 'errors');
}
if ($action == 'setpaid' && $candwrite && $id > 0) {
	$res = $object->setPaid($user);
	if ($res > 0) {
		if ($object->fk_bank) {
			setEventMessages($langs->trans("EcritureBancaireCreee"), null, 'mesgs');
		}
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id);
		exit;
	}
	setEventMessages($object->error, null, 'errors');
}

// Delete.
if ($action == 'confirm_delete' && GETPOST('confirm') == 'yes' && $candelete && $id > 0) {
	if ($object->delete($user) > 0) {
		setEventMessages($langs->trans("BulletinSupprime"), null, 'mesgs');
		header("Location: ".dol_buildpath('/modulepaie/bulletin_list.php', 1));
		exit;
	} else {
		setEventMessages($object->error, null, 'errors');
	}
}

// Build PDF.
if ($action == 'builddoc' && $candwrite && $id > 0) {
	$result = $object->generateDocument($object->model_pdf, $langs);
	if ($result > 0) {
		setEventMessages($langs->trans("FileGenerated"), null, 'mesgs');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$id);
		exit;
	} else {
		setEventMessages($object->error, null, 'errors');
	}
}

// Direct secure download or inline preview of the PDF (generated on the fly if missing).
// 'downloadpdf' = attachment ; 'viewpdf' = inline (used by the embedded preview like invoices).
if (($action == 'downloadpdf' || $action == 'viewpdf') && $id > 0 && $object->status >= PaieBulletin::STATUS_VALIDATED) {
	$file = $conf->modulepaie->dir_output.'/'.dol_sanitizeFileName($object->ref).'/'.dol_sanitizeFileName($object->ref).'.pdf';
	if (!dol_is_file($file)) {
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
 * View
 */
llxHeader('', $langs->trans("BulletinPaie"));

// ---------- CREATE FORM ----------
if ($action == 'create') {
	print load_fiche_titre($langs->trans("NewBulletin"), '', 'bill');
	$preseluser = GETPOST('fk_user', 'int');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="create_bulletin">';

	print dol_get_fiche_head();
	print '<table class="border centpercent">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans("SelectSalarie").'</td><td>';
	// Only users that have a contrat.
	$contrat = new PaieContrat($db);
	$contrats = $contrat->fetchAll(1);
	$usersarr = array();
	if (is_array($contrats)) {
		foreach ($contrats as $c) {
			$usersarr[$c->fk_user] = $c->getFullName().' ('.$c->emploi.')';
		}
	}
	print $form->selectarray('fk_user', $usersarr, $preseluser, 1);
	if (empty($usersarr)) {
		print ' <span class="opacitymedium">'.$langs->trans("NoContractForUser").' — <a href="'.dol_buildpath('/modulepaie/salarie_card.php', 1).'?action=create">'.$langs->trans("NewSalarie").'</a></span>';
	}
	print '</td></tr>';

	// Default period = current month.
	$firstday = dol_get_first_day((int) dol_print_date(dol_now(), '%Y'), (int) dol_print_date(dol_now(), '%m'));
	$lastday = dol_get_last_day((int) dol_print_date(dol_now(), '%Y'), (int) dol_print_date(dol_now(), '%m'));
	print '<tr><td class="fieldrequired">'.$langs->trans("PeriodeDebut").'</td><td>'.$form->selectDate($firstday, 'date_debut', 0, 0, 0, '', 1, 0).'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans("PeriodeFin").'</td><td>'.$form->selectDate($lastday, 'date_fin', 0, 0, 0, '', 1, 0).'</td></tr>';
	print '<tr><td>'.$langs->trans("DatePaiement").'</td><td>'.$form->selectDate($lastday, 'date_paiement', 0, 0, 1, '', 1, 0).'</td></tr>';
	print '</table>';
	print dol_get_fiche_end();

	print '<div class="center">';
	print '<input type="submit" class="button button-save" value="'.$langs->trans("GenererBulletin").'">';
	print ' &nbsp; <a class="button button-cancel" href="'.dol_buildpath('/modulepaie/bulletin_list.php', 1).'">'.$langs->trans("Cancel").'</a>';
	print '</div>';
	print '</form>';

	llxFooter();
	$db->close();
	exit;
}

// ---------- CARD ----------
if ($id > 0) {
	$head = bulletinPrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans("BulletinPaie"), -1, $object->picto);

	if ($action == 'delete') {
		print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$id, $langs->trans("Delete"), $langs->trans("ConfirmDeleteBulletin"), 'confirm_delete', '', 0, 1);
	}
	if ($action == 'validate') {
		print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$id, $langs->trans("ValiderBulletin"), $langs->trans("ConfirmValidateBulletin"), 'confirm_validate', '', 0, 1);
	}

	$linkback = '<a href="'.dol_buildpath('/modulepaie/bulletin_list.php', 1).'">'.$langs->trans("BackToList").'</a>';
	dol_banner_tab($object, 'ref', $linkback, 0, 'ref', 'ref', '');

	$salname = $object->salarie ? $object->salarie->getFullName($langs) : '';

	print '<div class="fichecenter"><div class="fichehalfleft"><div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">'.$langs->trans("Salarie").'</td><td>'.dol_escape_htmltag($salname);
	if ($object->contrat) {
		print ' — '.dol_escape_htmltag($object->contrat->emploi);
	}
	print '</td></tr>';
	print '<tr><td>'.$langs->trans("Periode").'</td><td>'.dol_print_date($object->date_debut, 'day').' → '.dol_print_date($object->date_fin, 'day').'</td></tr>';
	print '<tr><td>'.$langs->trans("DatePaiement").'</td><td>'.($object->date_paiement ? dol_print_date($object->date_paiement, 'day') : '').'</td></tr>';
	print '<tr><td>'.$langs->trans("Statut").'</td><td>'.$object->getLibStatut(4).'</td></tr>';
	if (!empty($object->fk_bank)) {
		print '<tr><td>'.$langs->trans("EcritureBancaire").'</td><td>';
		print '<a href="'.DOL_URL_ROOT.'/compta/bank/line.php?rowid='.((int) $object->fk_bank).'">'.img_object('', 'account', 'class="paddingright"').$langs->trans("VoirEcritureBancaire").'</a>';
		print '</td></tr>';
	}
	print '</table>';
	print '</div>';

	print '<div class="fichehalfright"><div class="underbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield">'.$langs->trans("SalaireBrut").'</td><td class="right amount">'.price($object->brut, 0, $langs, 1, -1, 2, $conf->currency).'</td></tr>';
	print '<tr><td>'.$langs->trans("TotalCotisationsSalariales").'</td><td class="right">'.price($object->total_cot_sal, 0, $langs, 1, -1, 2, $conf->currency).'</td></tr>';
	print '<tr><td><strong>'.$langs->trans("NetAPayer").'</strong></td><td class="right"><strong>'.price($object->net_a_payer, 0, $langs, 1, -1, 2, $conf->currency).'</strong></td></tr>';
	print '<tr><td>'.$langs->trans("ImpotSource").' ('.price($object->taux_pas, 0, $langs, 1, -1, 2).' %)</td><td class="right">'.price($object->montant_pas, 0, $langs, 1, -1, 2, $conf->currency).'</td></tr>';
	print '<tr><td><strong>'.$langs->trans("NetPaye").'</strong></td><td class="right"><strong>'.price($object->net_apres_impot, 0, $langs, 1, -1, 2, $conf->currency).'</strong></td></tr>';
	print '<tr><td>'.$langs->trans("NetImposable").'</td><td class="right">'.price($object->net_imposable, 0, $langs, 1, -1, 2, $conf->currency).'</td></tr>';
	print '<tr><td>'.$langs->trans("NetSocial").'</td><td class="right">'.price($object->net_social, 0, $langs, 1, -1, 2, $conf->currency).'</td></tr>';
	print '<tr><td>'.$langs->trans("CoutEmployeur").'</td><td class="right">'.price($object->cout_employeur, 0, $langs, 1, -1, 2, $conf->currency).'</td></tr>';
	print '</table>';
	print '</div></div>';

	print '<div class="clearboth"></div>';
	print dol_get_fiche_end();

	// ----- LINES -----
	$editlines = ($action == 'editlines' && $candwrite && $object->status == PaieBulletin::STATUS_DRAFT);

	if ($editlines) {
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$id.'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="savelines">';
	}

	print load_fiche_titre($langs->trans("BulletinPaie"), '', '');
	print '<div class="div-table-responsive">';
	print '<table class="tagtable liste centpercent noborder">';
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans("LabelRubrique").'</th>';
	print '<th class="right">'.$langs->trans("Base").'</th>';
	print '<th class="right">'.$langs->trans("TauxSalarial").'</th>';
	print '<th class="right">'.$langs->trans("PartSalariale").'</th>';
	print '<th class="right">'.$langs->trans("TauxPatronal").'</th>';
	print '<th class="right">'.$langs->trans("PartPatronale").'</th>';
	if ($editlines) {
		print '<th></th>';
	}
	print '</tr>';

	// Group lines: gains first, then cotisations by legal category.
	$gains = array();
	$byCat = array();
	foreach ($object->lignes as $l) {
		if ($l->type == 'gain') {
			$gains[] = $l;
		} else {
			$byCat[$l->categorie][] = $l;
		}
	}

	// --- Gains ---
	print '<tr class="liste_titre_add"><td colspan="'.($editlines ? 7 : 6).'"><strong>'.$langs->trans("CatGain").'</strong></td></tr>';
	foreach ($gains as $l) {
		printBulletinLine($l, $editlines, $langs, $conf, $id);
	}

	// --- Cotisations by category ---
	foreach (modulepaieOrderedCategories() as $cat) {
		if (empty($byCat[$cat])) {
			continue;
		}
		print '<tr class="liste_titre_add"><td colspan="'.($editlines ? 7 : 6).'"><strong>'.modulepaieCategorieLabel($cat).'</strong></td></tr>';
		foreach ($byCat[$cat] as $l) {
			printBulletinLine($l, $editlines, $langs, $conf, $id);
		}
	}

	// Totals row.
	print '<tr class="liste_total">';
	print '<td class="right"><strong>'.$langs->trans("SalaireBrut").' / '.$langs->trans("Total").'</strong></td>';
	print '<td class="right"><strong>'.price($object->brut, 0, $langs, 1, -1, 2, $conf->currency).'</strong></td>';
	print '<td></td>';
	print '<td class="right"><strong>'.price($object->total_cot_sal, 0, $langs, 1, -1, 2, $conf->currency).'</strong></td>';
	print '<td></td>';
	print '<td class="right"><strong>'.price($object->total_cot_pat, 0, $langs, 1, -1, 2, $conf->currency).'</strong></td>';
	if ($editlines) {
		print '<td></td>';
	}
	print '</tr>';

	print '</table>';
	print '</div>';

	if ($editlines) {
		// Congés payés edit.
		print '<br><table class="border centpercent"><tr class="liste_titre"><td colspan="4">'.$langs->trans("CongesPayes").'</td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans("CongesAcquis").'</td><td><input type="text" name="conges_acquis" class="width75" value="'.$object->conges_acquis.'"></td>';
		print '<td>'.$langs->trans("CongesPris").'</td><td><input type="text" name="conges_pris" class="width75" value="'.$object->conges_pris.'"></td></tr>';
		print '<tr class="oddeven"><td>'.$langs->trans("CongesSolde").'</td><td><input type="text" name="conges_solde" class="width75" value="'.$object->conges_solde.'"></td><td colspan="2"></td></tr>';
		print '</table>';

		print '<div class="center" style="margin-top:10px">';
		print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
		print ' &nbsp; <a class="button button-cancel" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'">'.$langs->trans("Cancel").'</a>';
		print '</div>';
		print '</form>';

		// Add line form.
		print '<br><form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$id.'" class="marginbottomonly">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="addline">';
		$rub = new PaieRubrique($db);
		$allrub = $rub->fetchAll();
		$rubarr = array();
		if (is_array($allrub)) {
			foreach ($allrub as $r) {
				$rubarr[$r->id] = $r->ref.' - '.$r->label;
			}
		}
		print $langs->trans("AjouterLigne").': '.$form->selectarray('fk_rubrique_add', $rubarr, '', 1);
		print ' '.$langs->trans("Montant").': <input type="text" name="montant_add" class="width75">';
		print ' <input type="submit" class="button small" value="'.$langs->trans("AjouterLigne").'">';
		print '</form>';
	}

	// Cumuls.
	print '<br><table class="border centpercent"><tr class="liste_titre"><td colspan="6">'.$langs->trans("Cumuls").'</td></tr>';
	print '<tr class="oddeven"><td>'.$langs->trans("CumulBrut").'</td><td class="right">'.price($object->cumul_brut, 0, $langs, 1, -1, 2, $conf->currency).'</td>';
	print '<td>'.$langs->trans("CumulNetImposable").'</td><td class="right">'.price($object->cumul_net_imp, 0, $langs, 1, -1, 2, $conf->currency).'</td>';
	print '<td>'.$langs->trans("CumulNetSocial").'</td><td class="right">'.price($object->cumul_net_social, 0, $langs, 1, -1, 2, $conf->currency).'</td></tr>';
	print '</table>';

	// ----- ACTION BUTTONS -----
	if (!$editlines) {
		print '<div class="tabsAction">';
		if ($object->status == PaieBulletin::STATUS_DRAFT && $candwrite) {
			print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=editlines">'.$langs->trans("Modify").'</a>';
			print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=recalc&token='.newToken().'">'.$langs->trans("RecalculerBulletin").'</a>';
		}
		if ($object->status == PaieBulletin::STATUS_DRAFT && $canvalidate) {
			print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=validate">'.$langs->trans("ValiderBulletin").'</a>';
		}
		if ($object->status >= PaieBulletin::STATUS_VALIDATED) {
			print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=builddoc&token='.newToken().'">'.$langs->trans("GenererPDF").'</a>';
			print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=downloadpdf&token='.newToken().'">'.$langs->trans("TelechargerPDF").'</a>';
		}
		if ($object->status == PaieBulletin::STATUS_VALIDATED && $candwrite) {
			print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=setpaid&token='.newToken().'">'.$langs->trans("MettreEnPaiement").'</a>';
		}
		if ($object->status >= PaieBulletin::STATUS_VALIDATED && $candwrite) {
			print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=setdraft&token='.newToken().'">'.$langs->trans("RepasserBrouillon").'</a>';
		}
		if ($candelete) {
			print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=delete">'.$langs->trans("Delete").'</a>';
		}
		print '</div>';

		// Generated documents.
		print showDocuments();
	}
}

llxFooter();
$db->close();

/**
 * Print a single payslip line row.
 *
 * @param  PaieBulletinLigne $l        Line
 * @param  bool              $editlines Whether inputs are editable
 * @param  Translate         $langs    Translation
 * @param  Conf              $conf     Config
 * @param  int               $id       Bulletin id
 * @return void
 */
function printBulletinLine($l, $editlines, $langs, $conf, $id)
{
	$isgain = ($l->type == 'gain');
	print '<tr class="oddeven">';
	print '<td>'.dol_escape_htmltag($l->label);
	if ($l->nombre > 0) {
		print ' <span class="opacitymedium">('.$l->nombre.')</span>';
	}
	print '</td>';

	// Base.
	print '<td class="right">';
	if ($editlines && ($isgain || in_array($l->base_type ?? '', array('manuel', 'fixe')))) {
		print '<input type="text" name="line_base['.$l->rowid.']" class="width75 right" value="'.$l->base.'">';
	} else {
		print price($l->base, 0, $langs, 1, -1, 2);
	}
	print '</td>';

	if ($isgain) {
		// Gains: show amount in salarial column.
		print '<td></td>';
		print '<td class="right">'.($l->sens == 'moins' ? '-' : '').price($l->base, 0, $langs, 1, -1, 2, $conf->currency).'</td>';
		print '<td></td><td></td>';
	} else {
		// Taux salarial.
		print '<td class="right">';
		if ($editlines) {
			print '<input type="text" name="line_ts['.$l->rowid.']" class="width50 right" value="'.$l->taux_salarial.'">';
		} else {
			print ($l->taux_salarial ? price($l->taux_salarial, 0, $langs, 1, -1, 3).' %' : '');
		}
		print '</td>';
		print '<td class="right">'.($l->montant_salarial ? price($l->montant_salarial, 0, $langs, 1, -1, 2, $conf->currency) : '').'</td>';
		// Taux patronal.
		print '<td class="right">';
		if ($editlines) {
			print '<input type="text" name="line_tp['.$l->rowid.']" class="width50 right" value="'.$l->taux_patronal.'">';
		} else {
			print ($l->taux_patronal ? price($l->taux_patronal, 0, $langs, 1, -1, 3).' %' : '');
		}
		print '</td>';
		print '<td class="right">'.($l->montant_patronal ? price($l->montant_patronal, 0, $langs, 1, -1, 2, $conf->currency) : '').'</td>';
	}

	if ($editlines) {
		print '<td class="center"><a href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=deleteline&lineid='.$l->rowid.'&token='.newToken().'">'.img_delete().'</a></td>';
	}
	print '</tr>';
}

/**
 * Show generated PDF documents for the current bulletin, with an embedded
 * preview at the bottom of the card (same spirit as invoices).
 *
 * @return string HTML
 */
function showDocuments()
{
	global $conf, $langs, $object;

	$out = '';
	$dir = $conf->modulepaie->dir_output.'/'.dol_sanitizeFileName($object->ref);
	$viewurl = $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=viewpdf&token='.newToken();
	$dlurl = $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=downloadpdf&token='.newToken();

	$files = array();
	if (is_dir($dir)) {
		$files = dol_dir_list($dir, 'files', 0, '\.pdf$');
	}

	// --- File list (like the "Documents" box of invoices) ---
	$out .= '<div class="fichecenter"><div class="fichehalfleft">';
	$out .= load_fiche_titre($langs->trans("Documents"), '', '');
	$out .= '<table class="noborder centpercent"><tr class="liste_titre">';
	$out .= '<th>'.$langs->trans("File").'</th><th class="right">'.$langs->trans("Size").'</th><th class="center">'.$langs->trans("Date").'</th><th></th></tr>';
	if (count($files)) {
		foreach ($files as $f) {
			$out .= '<tr class="oddeven">';
			$out .= '<td><a href="'.$viewurl.'" target="_blank">'.img_pdf().' '.dol_escape_htmltag($f['name']).'</a></td>';
			$out .= '<td class="right">'.dol_print_size($f['size']).'</td>';
			$out .= '<td class="center">'.dol_print_date($f['date'], 'dayhour').'</td>';
			$out .= '<td class="center"><a href="'.$dlurl.'" title="'.$langs->trans("TelechargerPDF").'">'.img_picto('', 'download').'</a></td>';
			$out .= '</tr>';
		}
	} else {
		$out .= '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
	}
	$out .= '</table>';
	$out .= '</div></div><div class="clearboth"></div>';

	// --- Embedded PDF preview at the bottom, like invoices ---
	if (count($files)) {
		$out .= '<br>';
		$out .= load_fiche_titre($langs->trans("Preview"), '', '');
		$out .= '<div class="centpercent" style="border:1px solid #ccc;">';
		$out .= '<iframe src="'.$viewurl.'#toolbar=1" style="width:100%;height:800px;border:0;" title="'.dol_escape_htmltag($object->ref).'.pdf"></iframe>';
		$out .= '</div>';
	}

	return $out;
}
