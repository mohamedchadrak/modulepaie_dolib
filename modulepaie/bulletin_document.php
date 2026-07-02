<?php
/* Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    bulletin_document.php
 * \ingroup modulepaie
 * \brief   Onglet documents (PDF) d'un bulletin de paie.
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
$langs->loadLangs(array("modulepaie@modulepaie", "other"));

if (!$user->rights->modulepaie->bulletin->read) {
	accessforbidden();
}
if (empty($conf->modulepaie->dir_output)) {
	$conf->modulepaie->dir_output = DOL_DATA_ROOT.'/modulepaie';
}

$id = GETPOST('id', 'int');
$action = GETPOST('action', 'aZ09');

$object = new PaieBulletin($db);
if ($id > 0) {
	$object->fetch($id);
	$object->fetchSalarie();
}

if ($action == 'builddoc' && !empty($user->rights->modulepaie->bulletin->write)) {
	$result = $object->generateDocument($object->model_pdf, $langs);
	if ($result > 0) {
		setEventMessages($langs->trans("FileGenerated"), null, 'mesgs');
	} else {
		setEventMessages($object->error, null, 'errors');
	}
}

llxHeader('', $langs->trans("BulletinPaie"));

if ($id > 0) {
	$head = bulletinPrepareHead($object);
	print dol_get_fiche_head($head, 'documents', $langs->trans("BulletinPaie"), -1, $object->picto);

	$linkback = '<a href="'.dol_buildpath('/modulepaie/bulletin_list.php', 1).'">'.$langs->trans("BackToList").'</a>';
	dol_banner_tab($object, 'ref', $linkback, 0, 'ref', 'ref', '');
	print dol_get_fiche_end();

	print '<div class="tabsAction">';
	if ($object->status >= PaieBulletin::STATUS_VALIDATED && !empty($user->rights->modulepaie->bulletin->write)) {
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&action=builddoc&token='.newToken().'">'.$langs->trans("GenererPDF").'</a>';
	}
	print '</div>';

	$dir = $conf->modulepaie->dir_output.'/'.dol_sanitizeFileName($object->ref);
	$cardurl = dol_buildpath('/modulepaie/bulletin_card.php', 1).'?id='.$object->id;
	$viewurl = $cardurl.'&action=viewpdf&token='.newToken();
	$dlurl = $cardurl.'&action=downloadpdf&token='.newToken();

	print load_fiche_titre($langs->trans("Documents"), '', '');
	print '<table class="noborder centpercent"><tr class="liste_titre"><th>'.$langs->trans("File").'</th><th class="right">'.$langs->trans("Size").'</th><th class="center">'.$langs->trans("Date").'</th><th></th></tr>';
	$files = is_dir($dir) ? dol_dir_list($dir, 'files', 0, '\.pdf$') : array();
	if (count($files)) {
		foreach ($files as $f) {
			print '<tr class="oddeven"><td><a href="'.$viewurl.'" target="_blank">'.img_pdf().' '.dol_escape_htmltag($f['name']).'</a></td>';
			print '<td class="right">'.dol_print_size($f['size']).'</td>';
			print '<td class="center">'.dol_print_date($f['date'], 'dayhour').'</td>';
			print '<td class="center"><a href="'.$dlurl.'" title="'.$langs->trans("TelechargerPDF").'">'.img_picto('', 'download').'</a></td></tr>';
		}
	} else {
		print '<tr><td colspan="4"><span class="opacitymedium">'.$langs->trans("None").'</span></td></tr>';
	}
	print '</table>';

	// Embedded preview like invoices.
	if (count($files)) {
		print '<br>';
		print load_fiche_titre($langs->trans("Preview"), '', '');
		print '<div class="centpercent" style="border:1px solid #ccc;">';
		print '<iframe src="'.$viewurl.'#toolbar=1" style="width:100%;height:800px;border:0;" title="'.dol_escape_htmltag($object->ref).'.pdf"></iframe>';
		print '</div>';
	}
}

llxFooter();
$db->close();
