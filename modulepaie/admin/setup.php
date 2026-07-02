<?php
/* Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    admin/setup.php
 * \ingroup modulepaie
 * \brief   Page de configuration du module Paie (employeur + valeurs par défaut).
 */

// Load Dolibarr environment
$res = 0;
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
dol_include_once('/modulepaie/lib/modulepaie.lib.php');

$langs->loadLangs(array("admin", "modulepaie@modulepaie"));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

$configItems = array(
	'MODULEPAIE_EMPLOYEUR_NOM' => 'alpha',
	'MODULEPAIE_EMPLOYEUR_SIRET' => 'alpha',
	'MODULEPAIE_EMPLOYEUR_APE' => 'alpha',
	'MODULEPAIE_EMPLOYEUR_URSSAF' => 'alpha',
	'MODULEPAIE_EMPLOYEUR_ADRESSE' => 'restricthtml',
	'MODULEPAIE_EMPLOYEUR_CP' => 'alpha',
	'MODULEPAIE_EMPLOYEUR_VILLE' => 'alpha',
	'MODULEPAIE_EMPLOYEUR_CONVENTION' => 'alpha',
	'MODULEPAIE_PMSS' => 'alpha',
	'MODULEPAIE_PDF_MODEL' => 'aZ09',
);

/*
 * Actions
 */
if ($action == 'update') {
	$error = 0;
	$db->begin();
	foreach ($configItems as $key => $type) {
		$value = GETPOST($key, $type);
		if ($key == 'MODULEPAIE_PMSS') {
			$value = price2num($value);
		}
		$res = dolibarr_set_const($db, $key, $value, 'chaine', 0, '', $conf->entity);
		if ($res <= 0) {
			$error++;
		}
	}
	if (!$error) {
		$db->commit();
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		$db->rollback();
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
}

/*
 * View
 */
$form = new Form($db);
llxHeader('', $langs->trans("ModulePaieSetup"));

$linkback = '<a href="'.($backtopage ? $backtopage : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("ModulePaieSetup"), $linkback, 'title_setup');

$head = modulepaieAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans("ModulePaieSetup"), -1, "modulepaie@modulepaie");

print '<span class="opacitymedium">'.$langs->trans("ModulePaieSetupPage").'</span><br><br>';

print info_admin($langs->trans("WarningRates"));

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td class="titlefield">'.$langs->trans("Employeur").'</td><td></td></tr>';

$fields = array(
	'MODULEPAIE_EMPLOYEUR_NOM' => 'EmployeurRaisonSociale',
	'MODULEPAIE_EMPLOYEUR_SIRET' => 'EmployeurSiret',
	'MODULEPAIE_EMPLOYEUR_APE' => 'EmployeurApe',
	'MODULEPAIE_EMPLOYEUR_URSSAF' => 'EmployeurUrssaf',
	'MODULEPAIE_EMPLOYEUR_ADRESSE' => 'EmployeurAdresse',
	'MODULEPAIE_EMPLOYEUR_CP' => 'EmployeurCp',
	'MODULEPAIE_EMPLOYEUR_VILLE' => 'EmployeurVille',
	'MODULEPAIE_EMPLOYEUR_CONVENTION' => 'EmployeurConvention',
);
foreach ($fields as $key => $labelkey) {
	print '<tr class="oddeven"><td>'.$langs->trans($labelkey).'</td><td>';
	if ($key == 'MODULEPAIE_EMPLOYEUR_ADRESSE') {
		print '<textarea name="'.$key.'" class="quatrevingtpercent" rows="2">'.dol_escape_htmltag(getDolGlobalString($key)).'</textarea>';
	} else {
		print '<input type="text" name="'.$key.'" class="minwidth300" value="'.dol_escape_htmltag(getDolGlobalString($key)).'">';
	}
	print '</td></tr>';
}

print '<tr class="liste_titre"><td>'.$langs->trans("Parameters").'</td><td></td></tr>';
print '<tr class="oddeven"><td>'.$langs->trans("PlafondSS").'</td><td>';
print '<input type="text" name="MODULEPAIE_PMSS" class="width100" value="'.dol_escape_htmltag(getDolGlobalString('MODULEPAIE_PMSS', '3925')).'"> €';
print ' <span class="opacitymedium">'.$langs->trans("PlafondSSHelp").'</span>';
print '</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans("ModelePDF").'</td><td>';
$models = array('paiestandard' => 'paiestandard');
print $form->selectarray('MODULEPAIE_PDF_MODEL', $models, getDolGlobalString('MODULEPAIE_PDF_MODEL', 'paiestandard'), 0);
print '</td></tr>';

print '</table>';

print '<br><div class="center">';
print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

print dol_get_fiche_end();

llxFooter();
$db->close();
