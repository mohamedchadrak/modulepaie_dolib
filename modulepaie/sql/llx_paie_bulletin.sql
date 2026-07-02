-- Copyright (C) 2026 Mohamed Chadrak <chadrakassani@gmail.com>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- En-tête des bulletins de paie

CREATE TABLE llx_paie_bulletin(
	rowid            integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	entity           integer DEFAULT 1 NOT NULL,
	ref              varchar(64) NOT NULL,
	fk_user          integer NOT NULL,
	fk_contrat       integer,
	date_debut       date NOT NULL,
	date_fin         date NOT NULL,
	date_paiement    date,
	salaire_base     double(24,8) DEFAULT 0,
	heures           double(10,4) DEFAULT 151.67,
	taux_horaire     double(24,8) DEFAULT 0,
	plafond_ss       double(24,8) DEFAULT 0,
	brut             double(24,8) DEFAULT 0,
	total_cot_sal    double(24,8) DEFAULT 0,
	total_cot_pat    double(24,8) DEFAULT 0,
	net_a_payer      double(24,8) DEFAULT 0,
	net_imposable    double(24,8) DEFAULT 0,
	net_social       double(24,8) DEFAULT 0,
	cout_employeur   double(24,8) DEFAULT 0,
	cumul_brut       double(24,8) DEFAULT 0,
	cumul_net_imp    double(24,8) DEFAULT 0,
	cumul_net_social double(24,8) DEFAULT 0,
	conges_acquis    double(10,4) DEFAULT 0,
	conges_pris      double(10,4) DEFAULT 0,
	conges_solde     double(10,4) DEFAULT 0,
	status           integer DEFAULT 0 NOT NULL,
	note_public      text,
	note_private     text,
	model_pdf        varchar(255) DEFAULT 'paiestandard',
	last_main_doc    varchar(255),
	date_creation    datetime NOT NULL,
	date_validation  datetime,
	tms              timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat    integer,
	fk_user_modif    integer,
	fk_user_valid    integer
) ENGINE=innodb;
