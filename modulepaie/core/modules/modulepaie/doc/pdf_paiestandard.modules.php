<?php
/* Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    core/modules/modulepaie/doc/pdf_paiestandard.modules.php
 * \ingroup modulepaie
 * \brief   Modèle PDF de bulletin de paie au format légal français (clarifié).
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
dol_include_once('/modulepaie/lib/modulepaie.lib.php');

/**
 * Modèle PDF "paiestandard" : bulletin de paie français.
 */
class pdf_paiestandard
{
	/** @var DoliDB */
	public $db;
	/** @var string */
	public $error = '';
	/** @var string */
	public $type = 'pdf';
	public $format;
	public $marge_gauche;
	public $marge_droite;
	public $marge_haute;
	public $marge_basse;

	/**
	 * Constructor
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf;
		$this->db = $db;
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
		$this->format = array(210, 297); // A4
	}

	/**
	 * Write the PDF file.
	 *
	 * @param  PaieBulletin $object      Bulletin
	 * @param  Translate    $outputlangs Output language
	 * @return int                       1 if OK, <=0 if KO
	 */
	public function write_file($object, $outputlangs)
	{
		global $conf, $langs;

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		$outputlangs->loadLangs(array("main", "modulepaie@modulepaie"));

		if (empty($conf->modulepaie->dir_output)) {
			$conf->modulepaie->dir_output = DOL_DATA_ROOT.'/modulepaie';
		}

		$object->fetchSalarie();

		$dir = $conf->modulepaie->dir_output.'/'.dol_sanitizeFileName($object->ref);
		if (!file_exists($dir)) {
			if (dol_mkdir($dir) < 0) {
				$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
				return -1;
			}
		}
		$file = $dir.'/'.dol_sanitizeFileName($object->ref).'.pdf';

		$pdf = pdf_getInstance($this->format);
		$default_font_size = pdf_getPDFFontSize($outputlangs);
		$pdf->SetAutoPageBreak(1, 0);
		if (class_exists('TCPDF')) {
			$pdf->setPrintHeader(false);
			$pdf->setPrintFooter(false);
		}
		$pdf->SetFont(pdf_getPDFFont($outputlangs));
		$pdf->SetTitle($outputlangs->convToOutputCharset($outputlangs->transnoentities("PdfTitle").' '.$object->ref));
		$pdf->SetSubject($outputlangs->transnoentities("BulletinPaie"));
		$pdf->SetCreator("Dolibarr ".DOL_VERSION." - Module Paie");
		$pdf->SetAuthor($outputlangs->convToOutputCharset(getDolGlobalString('MODULEPAIE_EMPLOYEUR_NOM', '')));
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);

		$pdf->Open();
		$pdf->AddPage();

		$w = 210 - $this->marge_gauche - $this->marge_droite; // usable width
		$x = $this->marge_gauche;
		$y = $this->marge_haute;

		// ---------- HEADER: title + employer / employee ----------
		$pdf->SetFont('', 'B', 14);
		$pdf->SetXY($x, $y);
		$pdf->Cell($w, 8, $outputlangs->transnoentities("PdfTitle"), 0, 1, 'C');
		$y += 9;

		// Period line.
		$pdf->SetFont('', '', 9);
		$period = $outputlangs->transnoentities("PdfPeriod").' : '.dol_print_date($object->date_debut, '%d/%m/%Y').' - '.dol_print_date($object->date_fin, '%d/%m/%Y');
		if ($object->date_paiement) {
			$period .= '   |   '.$outputlangs->transnoentities("DatePaiement").' : '.dol_print_date($object->date_paiement, '%d/%m/%Y');
		}
		$pdf->SetXY($x, $y);
		$pdf->Cell($w, 5, $period, 0, 1, 'C');
		$y += 8;

		// Two boxes: employer (left), employee (right).
		$boxw = ($w - 4) / 2;
		$boxh = 34;
		$yboxstart = $y;

		// Employer.
		$pdf->Rect($x, $y, $boxw, $boxh);
		$pdf->SetXY($x + 2, $y + 1.5);
		$pdf->SetFont('', 'B', 8);
		$pdf->Cell($boxw - 4, 4, $outputlangs->transnoentities("PdfEmployer"), 0, 1, 'L');
		$pdf->SetFont('', '', 8);
		$emp = array();
		$emp[] = getDolGlobalString('MODULEPAIE_EMPLOYEUR_NOM', $conf->global->MAIN_INFO_SOCIETE_NOM ?? '');
		$adr = getDolGlobalString('MODULEPAIE_EMPLOYEUR_ADRESSE');
		if ($adr) {
			$emp[] = $adr;
		}
		$cpville = trim(getDolGlobalString('MODULEPAIE_EMPLOYEUR_CP').' '.getDolGlobalString('MODULEPAIE_EMPLOYEUR_VILLE'));
		if ($cpville) {
			$emp[] = $cpville;
		}
		if (getDolGlobalString('MODULEPAIE_EMPLOYEUR_SIRET')) {
			$emp[] = 'SIRET : '.getDolGlobalString('MODULEPAIE_EMPLOYEUR_SIRET');
		}
		if (getDolGlobalString('MODULEPAIE_EMPLOYEUR_APE')) {
			$emp[] = 'APE/NAF : '.getDolGlobalString('MODULEPAIE_EMPLOYEUR_APE');
		}
		if (getDolGlobalString('MODULEPAIE_EMPLOYEUR_URSSAF')) {
			$emp[] = 'URSSAF : '.getDolGlobalString('MODULEPAIE_EMPLOYEUR_URSSAF');
		}
		$pdf->SetXY($x + 2, $y + 6);
		$pdf->MultiCell($boxw - 4, 3.6, $outputlangs->convToOutputCharset(implode("\n", $emp)), 0, 'L');

		// Employee.
		$xe = $x + $boxw + 4;
		$pdf->Rect($xe, $yboxstart, $boxw, $boxh);
		$pdf->SetXY($xe + 2, $yboxstart + 1.5);
		$pdf->SetFont('', 'B', 8);
		$pdf->Cell($boxw - 4, 4, $outputlangs->transnoentities("PdfEmployee"), 0, 1, 'L');
		$pdf->SetFont('', '', 8);
		$sal = array();
		$sal[] = $object->salarie ? $object->salarie->getFullName($outputlangs) : '';
		if ($object->contrat) {
			if ($object->contrat->emploi) {
				$sal[] = $outputlangs->transnoentities("Emploi").' : '.$object->contrat->emploi;
			}
			if ($object->contrat->qualification || $object->contrat->classification) {
				$sal[] = trim($object->contrat->qualification.' '.$object->contrat->classification);
			}
			if ($object->contrat->coefficient) {
				$sal[] = $outputlangs->transnoentities("Coefficient").' : '.$object->contrat->coefficient;
			}
			if ($object->contrat->num_secu) {
				$sal[] = $outputlangs->transnoentities("NumeroSecu").' : '.$object->contrat->num_secu;
			}
			if ($object->contrat->matricule) {
				$sal[] = $outputlangs->transnoentities("Matricule").' : '.$object->contrat->matricule;
			}
			if ($object->contrat->date_entree) {
				$sal[] = $outputlangs->transnoentities("DateEntree").' : '.dol_print_date($object->contrat->date_entree, '%d/%m/%Y');
			}
		}
		$pdf->SetXY($xe + 2, $yboxstart + 6);
		$pdf->MultiCell($boxw - 4, 3.6, $outputlangs->convToOutputCharset(implode("\n", array_filter($sal))), 0, 'L');

		$y = $yboxstart + $boxh + 4;

		// ---------- TABLE OF LINES ----------
		// Column widths.
		$cl = 62;   // label
		$cb = 24;   // base
		$cts = 20;  // taux salarial
		$cms = 24;  // part salariale
		$ctp = 18;  // taux patronal
		$cmp = $w - ($cl + $cb + $cts + $cms + $ctp); // part patronale (fills rest)

		// Table header (short labels sized to fit column widths, no overflow).
		$pdf->SetFont('', 'B', 7);
		$pdf->SetFillColor(230, 230, 230);
		$pdf->SetXY($x, $y);
		$pdf->Cell($cl, 6, $outputlangs->transnoentities("PdfColRubrique"), 1, 0, 'L', 1);
		$pdf->Cell($cb, 6, $outputlangs->transnoentities("PdfColBase"), 1, 0, 'C', 1);
		$pdf->Cell($cts, 6, $outputlangs->transnoentities("PdfColTaux"), 1, 0, 'C', 1);
		$pdf->Cell($cms, 6, $outputlangs->transnoentities("PdfColPartSal"), 1, 0, 'C', 1);
		$pdf->Cell($ctp, 6, $outputlangs->transnoentities("PdfColTaux"), 1, 0, 'C', 1);
		$pdf->Cell($cmp, 6, $outputlangs->transnoentities("PdfColPartPat"), 1, 1, 'C', 1);
		$y += 6;

		// Group lines.
		$gains = array();
		$byCat = array();
		foreach ($object->lignes as $l) {
			if ($l->type == 'gain') {
				$gains[] = $l;
			} else {
				$byCat[$l->categorie][] = $l;
			}
		}

		$lineh = 5;
		$pagebreaklimit = 297 - $this->marge_basse - 60;

		// Gains category.
		$y = $this->pdfCategoryRow($pdf, $x, $y, $w, $lineh, $outputlangs->transnoentities("CatGain"));
		foreach ($gains as $l) {
			$y = $this->pdfLine($pdf, $object, $l, $x, $y, array($cl, $cb, $cts, $cms, $ctp, $cmp), $lineh, $outputlangs);
			$y = $this->checkPageBreak($pdf, $y, $pagebreaklimit);
		}

		// Cotisation categories in legal order.
		foreach (modulepaieOrderedCategories() as $cat) {
			if (empty($byCat[$cat])) {
				continue;
			}
			$y = $this->pdfCategoryRow($pdf, $x, $y, $w, $lineh, modulepaieCategorieLabel($cat));
			foreach ($byCat[$cat] as $l) {
				$y = $this->pdfLine($pdf, $object, $l, $x, $y, array($cl, $cb, $cts, $cms, $ctp, $cmp), $lineh, $outputlangs);
				$y = $this->checkPageBreak($pdf, $y, $pagebreaklimit);
			}
		}

		// Totals row.
		$pdf->SetFont('', 'B', 8);
		$pdf->SetFillColor(215, 225, 240);
		$pdf->SetXY($x, $y);
		$pdf->Cell($cl, 6, $outputlangs->transnoentities("SalaireBrut").' / '.$outputlangs->transnoentities("Total"), 1, 0, 'L', 1, '', 1);
		$pdf->Cell($cb, 6, price($object->brut, 0, $outputlangs, 0, -1, 2), 1, 0, 'R', 1);
		$pdf->Cell($cts, 6, '', 1, 0, 'R', 1);
		$pdf->Cell($cms, 6, price($object->total_cot_sal, 0, $outputlangs, 0, -1, 2), 1, 0, 'R', 1);
		$pdf->Cell($ctp, 6, '', 1, 0, 'R', 1);
		$pdf->Cell($cmp, 6, price($object->total_cot_pat, 0, $outputlangs, 0, -1, 2), 1, 1, 'R', 1);
		$y += 8;

		// ---------- SUMMARY ----------
		$sumw = $w;
		$labw = $w - 40;
		$pdf->SetFont('', 'B', 9);
		$pdf->SetXY($x, $y);
		$pdf->Cell($labw, 6, $outputlangs->transnoentities("NetAPayer"), 'LTR', 0, 'L');
		$pdf->Cell(40, 6, price($object->net_a_payer, 0, $outputlangs, 0, -1, 2, $conf->currency), 'TR', 1, 'R');
		$y += 6;

		$pdf->SetFont('', '', 8.5);
		$rows = array(
			array($outputlangs->transnoentities("NetImposable"), $object->net_imposable),
			array($outputlangs->transnoentities("NetSocial"), $object->net_social),
			array($outputlangs->transnoentities("CoutEmployeur"), $object->cout_employeur),
		);
		foreach ($rows as $r) {
			$pdf->SetXY($x, $y);
			$pdf->Cell($labw, 5, $r[0], 'LR', 0, 'L');
			$pdf->Cell(40, 5, price($r[1], 0, $outputlangs, 0, -1, 2, $conf->currency), 'R', 1, 'R');
			$y += 5;
		}
		$pdf->SetXY($x, $y);
		$pdf->Cell($w, 0, '', 'T', 1);
		$y += 3;

		// ---------- CUMULS ----------
		$pdf->SetFont('', '', 8);
		$pdf->SetXY($x, $y);
		$cumul = $outputlangs->transnoentities("CumulBrut").' : '.price($object->cumul_brut, 0, $outputlangs, 0, -1, 2, $conf->currency);
		$cumul .= '    '.$outputlangs->transnoentities("CumulNetImposable").' : '.price($object->cumul_net_imp, 0, $outputlangs, 0, -1, 2, $conf->currency);
		$cumul .= '    '.$outputlangs->transnoentities("CumulNetSocial").' : '.price($object->cumul_net_social, 0, $outputlangs, 0, -1, 2, $conf->currency);
		$pdf->MultiCell($w, 4, $outputlangs->convToOutputCharset($cumul), 0, 'L');
		$y = $pdf->GetY() + 1;

		// Congés payés.
		if ($object->conges_acquis || $object->conges_pris || $object->conges_solde) {
			$cp = $outputlangs->transnoentities("PdfPaidLeave").' : ';
			$cp .= $outputlangs->transnoentities("CongesAcquis").' '.$object->conges_acquis.' | ';
			$cp .= $outputlangs->transnoentities("CongesPris").' '.$object->conges_pris.' | ';
			$cp .= $outputlangs->transnoentities("CongesSolde").' '.$object->conges_solde.' '.$outputlangs->transnoentities("Jours");
			$pdf->SetXY($x, $y);
			$pdf->MultiCell($w, 4, $outputlangs->convToOutputCharset($cp), 0, 'L');
			$y = $pdf->GetY() + 1;
		}

		// ---------- LEGAL MENTIONS ----------
		$pdf->SetFont('', 'I', 7);
		$pdf->SetXY($x, $y + 2);
		$pdf->MultiCell($w, 3.2, $outputlangs->convToOutputCharset($outputlangs->transnoentities("PdfNetSocialInfo")), 0, 'L');
		$pdf->SetXY($x, $pdf->GetY() + 1);
		$pdf->MultiCell($w, 3.2, $outputlangs->convToOutputCharset($outputlangs->transnoentities("PdfConservation")), 0, 'L');

		// Convention collective footer.
		if ($object->contrat && $object->contrat->convention) {
			$pdf->SetXY($x, $pdf->GetY() + 1);
			$pdf->MultiCell($w, 3.2, $outputlangs->convToOutputCharset($outputlangs->transnoentities("Convention").' : '.$object->contrat->convention), 0, 'L');
		}

		// ---------- OUTPUT ----------
		$pdf->Close();
		$pdf->Output($file, 'F');

		if (!empty($conf->global->MAIN_UMASK)) {
			@chmod($file, octdec($conf->global->MAIN_UMASK));
		}

		$object->last_main_doc = dol_sanitizeFileName($object->ref).'.pdf';
		$sql = "UPDATE ".MAIN_DB_PREFIX."paie_bulletin SET last_main_doc = '".$this->db->escape($object->last_main_doc)."' WHERE rowid = ".((int) $object->id);
		$this->db->query($sql);

		return 1;
	}

	/**
	 * Print a category header row.
	 *
	 * @param  TCPDF  $pdf   PDF
	 * @param  float  $x     X
	 * @param  float  $y     Y
	 * @param  float  $w     Width
	 * @param  float  $h     Height
	 * @param  string $label Label
	 * @return float         New Y
	 */
	private function pdfCategoryRow($pdf, $x, $y, $w, $h, $label)
	{
		$pdf->SetFont('', 'B', 7.5);
		$pdf->SetFillColor(245, 245, 245);
		$pdf->SetXY($x, $y);
		$pdf->Cell($w, $h, ' '.$label, 'LR', 1, 'L', 1);
		return $y + $h;
	}

	/**
	 * Print a single line of the payslip.
	 *
	 * @param  TCPDF             $pdf         PDF
	 * @param  PaieBulletin      $object      Bulletin
	 * @param  PaieBulletinLigne $l           Line
	 * @param  float             $x           X
	 * @param  float             $y           Y
	 * @param  array             $cols        Column widths
	 * @param  float             $h           Row height
	 * @param  Translate         $outputlangs Language
	 * @return float                          New Y
	 */
	private function pdfLine($pdf, $object, $l, $x, $y, $cols, $h, $outputlangs)
	{
		list($cl, $cb, $cts, $cms, $ctp, $cmp) = $cols;
		$pdf->SetFont('', '', 7.5);
		$pdf->SetXY($x, $y);

		$label = $l->label;
		if ($l->nombre > 0) {
			$label .= ' ('.price($l->nombre, 0, $outputlangs, 0, -1, 2).')';
		}

		$isgain = ($l->type == 'gain');
		// stretch=1: shrink text horizontally if wider than the cell (no overflow on next column).
		$pdf->Cell($cl, $h, $outputlangs->convToOutputCharset($label), 'LR', 0, 'L', 0, '', 1);
		$pdf->Cell($cb, $h, price($l->base, 0, $outputlangs, 0, -1, 2), 'LR', 0, 'R');

		if ($isgain) {
			$pdf->Cell($cts, $h, '', 'LR', 0, 'R');
			$montant = ($l->sens == 'moins' ? '-' : '').price($l->base, 0, $outputlangs, 0, -1, 2);
			$pdf->Cell($cms, $h, $montant, 'LR', 0, 'R');
			$pdf->Cell($ctp, $h, '', 'LR', 0, 'R');
			$pdf->Cell($cmp, $h, '', 'LR', 1, 'R');
		} else {
			$pdf->Cell($cts, $h, ($l->taux_salarial ? price($l->taux_salarial, 0, $outputlangs, 0, -1, 3).' %' : ''), 'LR', 0, 'R');
			$pdf->Cell($cms, $h, ($l->montant_salarial ? price($l->montant_salarial, 0, $outputlangs, 0, -1, 2) : ''), 'LR', 0, 'R');
			$pdf->Cell($ctp, $h, ($l->taux_patronal ? price($l->taux_patronal, 0, $outputlangs, 0, -1, 3).' %' : ''), 'LR', 0, 'R');
			$pdf->Cell($cmp, $h, ($l->montant_patronal ? price($l->montant_patronal, 0, $outputlangs, 0, -1, 2) : ''), 'LR', 1, 'R');
		}
		return $y + $h;
	}

	/**
	 * Add a page if content reaches the bottom limit.
	 *
	 * @param  TCPDF $pdf   PDF
	 * @param  float $y     Current Y
	 * @param  float $limit Bottom limit
	 * @return float        New Y
	 */
	private function checkPageBreak($pdf, $y, $limit)
	{
		if ($y > $limit) {
			$pdf->AddPage();
			return $this->marge_haute;
		}
		return $y;
	}
}
